<?php

namespace Tests\Feature;

use App\Services\Reminder\AIMessageGenerator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIMessageGeneratorTest extends TestCase
{
    protected function tearDown(): void
    {
        config()->set('services.typesafe.api_key', null);
        config()->set('services.anthropic.api_key', null);

        parent::tearDown();
    }

    public function test_without_keys_the_friendly_template_is_used(): void
    {
        $message = app(AIMessageGenerator::class)->generate('Ahmad', '500', 'AFN');

        $this->assertStringContainsString('friendly reminder', $message);
        $this->assertStringContainsString('Ahmad', $message);
        $this->assertStringContainsString('؋500', $message);
    }

    public function test_jev_tone_selects_the_firm_template(): void
    {
        config()->set('services.typesafe.api_key', 'ts-test-key');
        $this->fakeTypesafe('firm', 0.9, 0.2);

        $message = app(AIMessageGenerator::class)->generate('Ahmad', '500', 'AFN');

        $this->assertStringContainsString('outstanding balance', $message);
        $this->assertStringContainsString('Ahmad', $message);
    }

    public function test_a_final_notice_is_downgraded_when_the_customer_would_take_offense(): void
    {
        config()->set('services.typesafe.api_key', 'ts-test-key');
        $this->fakeTypesafe('final_notice', 0.9, 0.95);

        $message = app(AIMessageGenerator::class)->generate('Ahmad', '500', 'AFN');

        // Guardrail: relationship risk above the threshold downshifts the
        // last warning to a firm (not final-notice) template.
        $this->assertStringContainsString('outstanding balance', $message);
        $this->assertStringNotContainsString('despite previous reminders', $message);
    }

    public function test_low_confidence_falls_back_to_the_friendly_template(): void
    {
        config()->set('services.typesafe.api_key', 'ts-test-key');
        $this->fakeTypesafe('firm', 0.3, 0.1);

        $message = app(AIMessageGenerator::class)->generate('Ahmad', '500', 'AFN');

        $this->assertStringContainsString('friendly reminder', $message);
    }

    public function test_type_safe_failure_falls_back_to_the_friendly_template(): void
    {
        config()->set('services.typesafe.api_key', 'ts-test-key');
        Http::fake(['api.typesafe.ai/*' => Http::response('overloaded', 529)]);

        $message = app(AIMessageGenerator::class)->generate('Ahmad', '500', 'AFN');

        $this->assertStringContainsString('friendly reminder', $message);
    }

    public function test_jev_tone_guides_the_claude_prompt(): void
    {
        config()->set([
            'services.typesafe.api_key' => 'ts-test-key',
            'services.anthropic.api_key' => 'an-test-key',
        ]);
        $this->fakeTypesafe('firm', 0.9, 0.2);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => 'Claude wrote this reminder']],
        ])]);

        $message = app(AIMessageGenerator::class)->generate('Ahmad', '500', 'USD');

        $this->assertSame('Claude wrote this reminder', $message);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com')
                && str_contains($request->body(), 'firm and direct');
        });
    }

    /**
     * Fake the TypeSafe evaluate endpoint with a Jev answer set: a `tone`
     * Choice and an `offense_risk` Noul over the reminder state.
     */
    private function fakeTypesafe(string $choice, float $confidence, float $offenseRisk): void
    {
        Http::fake([
            'api.typesafe.ai/*' => Http::response([
                'model' => 'jev-1.13.0',
                'answers' => [
                    'tone' => [
                        'type' => 'choice',
                        'choice' => $choice,
                        'probabilities' => [
                            'friendly' => (float) number_format((1 - $confidence) * 0.7, 2),
                            'firm' => (float) number_format((1 - $confidence) * 0.3, 2),
                            'final_notice' => $confidence,
                        ],
                        'confidence' => $confidence,
                    ],
                    'offense_risk' => [
                        'type' => 'noul',
                        'noul' => $offenseRisk,
                    ],
                ],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
            ]),
        ]);
    }
}
