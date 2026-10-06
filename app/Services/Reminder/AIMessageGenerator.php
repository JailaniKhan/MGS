<?php

namespace App\Services\Reminder;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIMessageGenerator
{
    /**
     * Reminder tones. The tone is a judgment, not text: TypeSafe's Jev
     * (System One) decides how pressing the reminder should be from the
     * debt context, and this class composes the actual message from
     * localized, tone-specific templates (or hands the tone to Claude
     * when text generation is configured).
     */
    public const TONE_FRIENDLY = 'friendly';

    public const TONE_FIRM = 'firm';

    public const TONE_FINAL_NOTICE = 'final_notice';

    /**
     * Below this confidence a Jev judgment must not drive behavior — the
     * shop's default gentle reminder is used instead (confidence-gated
     * routing, per the TypeSafe patterns docs).
     */
    private const MIN_TONE_CONFIDENCE = 0.5;

    /**
     * Above this probability of relationship damage a final-notice tone is
     * downshifted one step — never risk the customer over wording.
     */
    private const MAX_OFFENSE_RISK_FOR_FINAL_NOTICE = 0.6;

    public function generate(string $customerName, string $amount, string $currency, string $dueDate = ''): string
    {
        $tone = $this->judgeTone($customerName, $amount, $currency, $dueDate);

        $apiKey = config('services.anthropic.api_key');

        if (! empty($apiKey)) {
            try {
                return $this->generateWithClaude($apiKey, $customerName, $amount, $currency, $dueDate, $tone);
            } catch (\Exception $e) {
                Log::warning('AI reminder generation failed, using template', ['error' => $e->getMessage()]);
            }
        }

        return $this->generateTemplate($customerName, $amount, $currency, $dueDate, $tone);
    }

    /**
     * One TypeSafe request, two independent questions over the same state:
     * - `tone` (Choice): how pressing the reminder should be.
     * - `offense_risk` (Noul): would a demanding tone damage the relationship.
     * They run in parallel inside the single call; the code combines them
     * (guardrail downshift) and gates on confidence.
     */
    private function judgeTone(string $name, string $amount, string $currency, string $dueDate): string
    {
        $apiKey = config('services.typesafe.api_key');

        if (empty($apiKey)) {
            return self::TONE_FRIENDLY;
        }

        $currencyName = $currency === 'USD' ? 'US dollars' : 'Afghanis';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post(config('services.typesafe.url'), [
                    'state' => [
                        'context' => 'A shop is about to send a payment reminder to a customer whose business it wants to keep.',
                        'customer_name' => $name,
                        'amount_due' => $amount.' '.$currencyName,
                        'due_date' => $dueDate !== '' ? $dueDate : 'not stated',
                        'message_language' => $this->promptLanguage(),
                    ],
                    'model' => config('services.typesafe.model', 'jev-latest'),
                    'questions' => [
                        'tone' => [
                            'type' => 'choice',
                            'instructions' => [
                                'question' => 'How pressing should the reminder message to `customer_name` about `amount_due` be?',
                                'detail' => 'The message will be written in `message_language` and mentions the due date (`due_date`). The shop values the relationship but needs the money.',
                            ],
                            'criteria' => [
                                self::TONE_FRIENDLY => 'A polite, warm nudge; the debt is recent or the relationship is delicate.',
                                self::TONE_FIRM => 'Direct and unambiguous: payment is overdue and must be settled soon.',
                                self::TONE_FINAL_NOTICE => 'A last warning: the debt is long overdue despite earlier reminders.',
                            ],
                        ],
                        'offense_risk' => [
                            'type' => 'noul',
                            'instructions' => [
                                'question' => 'If the reminder to `customer_name` about `amount_due` used a firm, demanding tone, would that seriously risk offending the customer and damaging the business relationship?',
                                'detail' => 'The due date is `due_date`. `context`',
                            ],
                            'criteria' => [
                                'true' => 'A demanding tone would likely offend this customer and jeopardize the relationship.',
                                'false' => 'This customer would accept directness about the overdue payment without taking offense.',
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('TypeSafe API error: '.$response->status().' '.$response->body());
            }

            $answers = $response->json('answers', []);
            $tone = $answers['tone']['choice'] ?? null;
            $confidence = (float) ($answers['tone']['confidence'] ?? 0);

            if (! in_array($tone, [self::TONE_FRIENDLY, self::TONE_FIRM, self::TONE_FINAL_NOTICE], true)
                || $confidence < self::MIN_TONE_CONFIDENCE) {
                Log::info('TypeSafe Jev tone judgment not actionable, using friendly', [
                    'tone' => $tone, 'confidence' => $confidence,
                ]);

                return self::TONE_FRIENDLY;
            }

            // Guardrail: a last warning to a customer who would take offense
            // risks the relationship — downshift one step to firm.
            $offenseRisk = (float) ($answers['offense_risk']['noul'] ?? 0);
            if ($tone === self::TONE_FINAL_NOTICE && $offenseRisk > self::MAX_OFFENSE_RISK_FOR_FINAL_NOTICE) {
                Log::info('TypeSafe Jev: final notice downgraded to firm', ['offense_risk' => $offenseRisk]);
                $tone = self::TONE_FIRM;
            }

            Log::info('TypeSafe Jev reminder tone judged', [
                'tone' => $tone, 'confidence' => $confidence, 'offense_risk' => $offenseRisk,
            ]);

            return $tone;
        } catch (\Throwable $e) {
            Log::warning('TypeSafe Jev tone judgment failed, using friendly tone', ['error' => $e->getMessage()]);

            return self::TONE_FRIENDLY;
        }
    }

    private function generateWithClaude(string $apiKey, string $name, string $amount, string $currency, string $dueDate, string $tone): string
    {
        $currencySymbol = $currency === 'USD' ? '$' : '؋';
        $language = $this->promptLanguage();
        $toneInstruction = match ($tone) {
            self::TONE_FIRM => 'The tone must be firm and direct: payment is overdue and must be settled soon, while staying respectful.',
            self::TONE_FINAL_NOTICE => 'The tone must be a firm final notice: this is the last reminder and payment is required immediately, while staying professional.',
            default => 'The tone must be warm and friendly, a gentle nudge.',
        };
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 200,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Write a payment reminder message in {$language} for a customer named {$name} who has a pending payment of {$currencySymbol}{$amount}. {$toneInstruction} The message should be polite, professional, and encourage them to clear the dues. Keep it under 150 characters. Just return the message text, no quotes.",
                ],
            ],
        ]);

        if ($response->successful()) {
            $text = $response->json('content.0.text');

            return trim($text);
        }

        throw new \RuntimeException('Claude API error: '.$response->body());
    }

    /**
     * The reminder is written in the shop owner's active language.
     */
    private function promptLanguage(): string
    {
        return match (app()->getLocale()) {
            'fa' => 'Persian (Dari)',
            'ps' => 'Pashto',
            default => 'English',
        };
    }

    private function generateTemplate(string $name, string $amount, string $currency, string $dueDate, string $tone): string
    {
        $currencySymbol = $currency === 'USD' ? '$' : '؋';
        $duePart = $dueDate !== '' ? __('messages.reminder_due_part', ['date' => $dueDate]) : '';

        $key = match ($tone) {
            self::TONE_FIRM => 'messages.reminder_template_firm',
            self::TONE_FINAL_NOTICE => 'messages.reminder_template_final_notice',
            default => 'messages.reminder_message_template',
        };

        return __($key, [
            'name' => $name,
            'amount' => $currencySymbol.$amount,
            'due_part' => $duePart,
        ]);
    }
}
