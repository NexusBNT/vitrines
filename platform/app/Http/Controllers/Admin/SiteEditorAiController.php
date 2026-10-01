<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\PageText;
use App\Domain\Content\RichText;
use App\Domain\Generation\AiException;
use App\Domain\Generation\Editor\EditorAssistant;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * IA de l'éditeur de pages. Les propositions sont renvoyées à l'éditeur, qui les fait valider avant de les appliquer.
 */
class SiteEditorAiController extends Controller
{
    public function __construct(private EditorAssistant $assistant) {}

    public function transform(Request $request, Site $site): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(EditorAssistant::ACTIONS))],
            'text' => ['required', 'string', 'max:6000'],
            'instruction' => ['nullable', 'string', 'max:500'],
            'page' => ['nullable', 'string', 'max:40'],
        ]);

        return $this->attempt(fn (): array => [
            'text' => $this->assistant->transform($site, $data['action'], $data['text'], $data['instruction'] ?? null, $this->pageText($site, $data['page'] ?? null)),
        ]);
    }

    public function write(Request $request, Site $site): JsonResponse
    {
        $data = $request->validate([
            'instruction' => ['required', 'string', 'max:1000'],
            'page' => ['nullable', 'string', 'max:40'],
        ]);

        return $this->attempt(fn (): array => [
            'blocks' => (new RichText)->sanitize($this->assistant->write($site, $data['instruction'], $this->pageText($site, $data['page'] ?? null), $this->page($site, $data['page'] ?? null)['nav_label'] ?? null)),
        ]);
    }

    public function seo(Request $request, Site $site): JsonResponse
    {
        $data = $request->validate(['page' => ['required', 'string', 'max:40']]);
        $page = $this->page($site, $data['page']);
        abort_if($page === null, 404);

        return $this->attempt(fn (): array => $this->assistant->seo($site, PageText::of($page), $page['nav_label'], $page['key'] === 'home'));
    }

    /**
     * @param  Closure(): array<string, mixed>  $callback
     */
    private function attempt(Closure $callback): JsonResponse
    {
        if (! $this->assistant->isAvailable()) {
            return response()->json(['message' => 'Aucun fournisseur d\'IA n\'est configuré.'], 503);
        }

        try {
            return response()->json($callback());
        } catch (AiException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function page(Site $site, ?string $key): ?array
    {
        return $key === null ? null : collect($site->draft_spec['pages'] ?? [])->firstWhere('key', $key);
    }

    private function pageText(Site $site, ?string $key): string
    {
        $page = $this->page($site, $key);

        return $page === null ? '' : PageText::of($page);
    }
}
