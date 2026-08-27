<?php

namespace App\Services\Reminder;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIMessageGenerator
{
    public function generate(string $customerName, string $amount, string $currency, string $dueDate = ''): string
    {
        $apiKey = config('services.anthropic.api_key');

        if (!empty($apiKey)) {
            try {
                return $this->generateWithClaude($apiKey, $customerName, $amount, $currency, $dueDate);
            } catch (\Exception $e) {
                Log::warning('AI reminder generation failed, using template', ['error' => $e->getMessage()]);
            }
        }

        return $this->generateTemplate($customerName, $amount, $currency, $dueDate);
    }

    private function generateWithClaude(string $apiKey, string $name, string $amount, string $currency, string $dueDate): string
    {
        $currencySymbol = $currency === 'USD' ? '$' : '؋';
        $language = $this->promptLanguage();
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
                    'content' => "Write a friendly payment reminder message in {$language} for a customer named {$name} who has a pending payment of {$currencySymbol}{$amount}. The message should be polite, professional, and encourage them to clear the dues. Keep it under 150 characters. Just return the message text, no quotes.",
                ],
            ],
        ]);

        if ($response->successful()) {
            $text = $response->json('content.0.text');
            return trim($text);
        }

        throw new \RuntimeException('Claude API error: ' . $response->body());
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

    private function generateTemplate(string $name, string $amount, string $currency, string $dueDate): string
    {
        $currencySymbol = $currency === 'USD' ? '$' : '؋';
        $duePart = $dueDate !== '' ? __('messages.reminder_due_part', ['date' => $dueDate]) : '';

        return __('messages.reminder_message_template', [
            'name' => $name,
            'amount' => $currencySymbol.$amount,
            'due_part' => $duePart,
        ]);
    }
}
