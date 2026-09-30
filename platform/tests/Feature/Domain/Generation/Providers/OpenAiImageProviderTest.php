<?php

namespace Tests\Feature\Domain\Generation\Providers;

use App\Domain\Generation\AiException;
use App\Domain\Generation\Providers\OpenAiImageProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiImageProviderTest extends TestCase
{
    public function test_generates_several_images_in_parallel_and_isolates_failures(): void
    {
        Http::fake(function (Request $request) {
            return str_contains($request['prompt'], 'refusée')
                ? Http::response(['error' => ['message' => 'Contenu refusé']], 400)
                : Http::response(['data' => [['b64_json' => base64_encode('jpeg:'.$request['prompt'])]], 'usage' => ['input_tokens' => 30, 'output_tokens' => 1300]]);
        });

        $results = $this->provider()->generateMany(['hero' => 'Atelier', 'about' => 'Scène refusée']);

        $this->assertSame('jpeg:Atelier', $results['hero']['jpeg']);
        $this->assertInstanceOf(AiException::class, $results['about']);
        $this->assertFalse($results['about']->retryable);
        Http::assertSent(fn (Request $request): bool => $request['model'] === 'gpt-image-2' && $request['output_format'] === 'jpeg' && $request['quality'] === 'medium');
    }

    private function provider(): OpenAiImageProvider
    {
        return new OpenAiImageProvider(['api_key' => 'k', 'base_url' => 'https://api.openai.test/v1', 'image_model' => 'gpt-image-2', 'image_quality' => 'medium', 'timeout' => 5]);
    }
}
