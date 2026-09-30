<?php

namespace Tests\Feature\Domain\Generation;

use App\Domain\Generation\AiException;
use App\Domain\Generation\AiManager;
use App\Domain\Generation\AiRequest;
use App\Enums\GenerationStatus;
use App\Models\AiGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

class AiManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_a_successful_call_with_tokens(): void
    {
        $manager = new AiManager(['claude' => new FakeAiProvider([['ok' => true]])]);

        $response = $manager->generate('site_content', $this->request());

        $this->assertSame(['ok' => true], $response->data);
        $generation = AiGeneration::sole();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame('claude', $generation->provider);
        $this->assertSame(3400, $generation->output_tokens);
    }

    public function test_falls_back_to_the_other_provider_on_a_temporary_failure(): void
    {
        config(['ai.fallback_provider' => 'openai']);
        $manager = new AiManager([
            'claude' => new FakeAiProvider([new AiException('Surchargé', retryable: true)]),
            'openai' => new FakeAiProvider([['ok' => true]], 'openai'),
        ]);

        $response = $manager->generate('site_content', $this->request());

        $this->assertSame('openai', $response->provider);
        $this->assertSame(['failed', 'succeeded'], AiGeneration::orderBy('id')->pluck('status')->map->value->all());
    }

    public function test_does_not_fall_back_on_a_permanent_failure(): void
    {
        config(['ai.fallback_provider' => 'openai']);
        $openai = new FakeAiProvider([['ok' => true]], 'openai');
        $manager = new AiManager([
            'claude' => new FakeAiProvider([new AiException('Clé refusée')]),
            'openai' => $openai,
        ]);

        $this->expectExceptionMessage('Clé refusée');

        try {
            $manager->generate('site_content', $this->request());
        } finally {
            $this->assertSame([], $openai->requests);
        }
    }

    public function test_uses_the_fallback_when_the_task_provider_has_no_key(): void
    {
        config(['ai.fallback_provider' => 'openai']);
        $manager = new AiManager([
            'claude' => new FakeAiProvider([], configured: false),
            'openai' => new FakeAiProvider([['ok' => true]], 'openai'),
        ]);

        $this->assertSame('openai', $manager->generate('site_content', $this->request())->provider);
    }

    public function test_explains_that_a_key_is_missing_when_no_provider_is_configured(): void
    {
        $manager = new AiManager(['claude' => new FakeAiProvider([], configured: false)]);

        $this->assertFalse($manager->isAvailable('site_content'));
        $this->expectExceptionMessage('ajoutez une clé API');

        $manager->generate('site_content', $this->request());
    }

    private function request(): AiRequest
    {
        return new AiRequest('système', 'demande', 'test', ['type' => 'object']);
    }
}
