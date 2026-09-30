<?php

namespace Tests\Feature\Domain\Generation\Providers;

use Anthropic\Client;
use App\Domain\Generation\AiException;
use App\Domain\Generation\AiRequest;
use App\Domain\Generation\Providers\ClaudeProvider;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class ClaudeProviderTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $sentBodies = [];

    public function test_sends_a_structured_output_request_and_decodes_the_answer(): void
    {
        $provider = $this->provider($this->message('{"titre":"Bonjour"}'));

        $response = $provider->generateStructured(new AiRequest(
            system: 'Tu rédiges.',
            prompt: 'Écris un titre.',
            schemaName: 'demo',
            schema: ['type' => 'object', 'properties' => ['titre' => ['type' => 'string']], 'required' => ['titre'], 'additionalProperties' => false],
            images: [['media_type' => 'image/jpeg', 'data' => 'AAAA']],
        ));

        $this->assertSame(['titre' => 'Bonjour'], $response->data);
        $this->assertSame(1500, $response->inputTokens);

        $body = $this->sentBodies[0];
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame('medium', $body['output_config']['effort']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame('image', $body['messages'][0]['content'][0]['type']);
        $this->assertSame('image/jpeg', $body['messages'][0]['content'][0]['source']['media_type']);
        $this->assertSame('Tu rédiges.', $body['system']);
    }

    public function test_reports_a_refusal_as_a_non_retryable_error(): void
    {
        $provider = $this->provider($this->message('', stopReason: 'refusal'));

        try {
            $provider->generateStructured(new AiRequest('s', 'p', 'demo', ['type' => 'object']));
            $this->fail('Un refus aurait dû lever une exception.');
        } catch (AiException $exception) {
            $this->assertFalse($exception->retryable);
            $this->assertStringContainsString('refusé', $exception->getMessage());
        }
    }

    public function test_is_not_configured_without_an_api_key(): void
    {
        $this->assertFalse((new ClaudeProvider(['api_key' => null, 'default_model' => 'claude-opus-5-5', 'effort' => 'medium', 'timeout' => 10]))->isConfigured());
    }

    private function provider(Response $response): ClaudeProvider
    {
        $client = new Client(apiKey: 'test-key', requestOptions: [
            'maxRetries' => 0,
            'middleware' => [function (RequestInterface $request) use ($response): Response {
                $this->sentBodies[] = json_decode((string) $request->getBody(), true);

                return $response;
            }],
        ]);

        return new ClaudeProvider(['api_key' => 'test-key', 'default_model' => 'claude-opus-5-5', 'effort' => 'medium', 'timeout' => 10], $client);
    }

    private function message(string $text, string $stopReason = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_1',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => $text === '' ? [] : [['type' => 'text', 'text' => $text]],
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'stop_details' => null,
            'usage' => ['input_tokens' => 1500, 'output_tokens' => 800],
        ]));
    }
}
