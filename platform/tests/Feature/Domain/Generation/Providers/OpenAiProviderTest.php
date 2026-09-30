<?php

namespace Tests\Feature\Domain\Generation\Providers;

use App\Domain\Generation\AiException;
use App\Domain\Generation\AiRequest;
use App\Domain\Generation\Providers\OpenAiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiProviderTest extends TestCase
{
    public function test_sends_a_strict_json_schema_request_and_decodes_the_answer(): void
    {
        Http::fake(['api.openai.test/*' => Http::response([
            'status' => 'completed',
            'model' => 'gpt-test',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"titre":"Bonjour"}']]]],
            'usage' => ['input_tokens' => 900, 'output_tokens' => 300],
        ])]);

        $response = $this->provider()->generateStructured(new AiRequest('Tu rédiges.', 'Écris.', 'demo', ['type' => 'object']));

        $this->assertSame(['titre' => 'Bonjour'], $response->data);
        $this->assertSame('openai', $response->provider);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.test/v1/responses'
            && $request['text']['format']['strict'] === true
            && $request['instructions'] === 'Tu rédiges.'
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_treats_rate_limiting_as_retryable(): void
    {
        Http::fake(['api.openai.test/*' => Http::response(['error' => ['message' => 'slow down']], 429)]);

        try {
            $this->provider()->generateStructured(new AiRequest('s', 'p', 'demo', ['type' => 'object']));
            $this->fail('Une erreur 429 aurait dû lever une exception.');
        } catch (AiException $exception) {
            $this->assertTrue($exception->retryable);
        }
    }

    public function test_is_not_configured_without_a_model(): void
    {
        $this->assertFalse((new OpenAiProvider(['api_key' => 'k', 'base_url' => 'x', 'default_model' => null, 'timeout' => 5]))->isConfigured());
    }

    private function provider(): OpenAiProvider
    {
        return new OpenAiProvider(['api_key' => 'test-key', 'base_url' => 'https://api.openai.test/v1', 'default_model' => 'gpt-test', 'timeout' => 5]);
    }
}
