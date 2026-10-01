<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Build\BuildPreview;
use App\Domain\Content\PagesNormalizer;
use App\Domain\Content\PageText;
use App\Domain\Content\PageTree;
use App\Domain\Content\Revisions;
use App\Domain\Content\SectionTypes;
use App\Domain\Generation\Editor\EditorAssistant;
use App\Domain\Media\StoreUploadedMedia;
use App\Domain\Sites\Design;
use App\Domain\Sites\DraftSpecFactory;
use App\Enums\MediaStatus;
use App\Enums\PlanFeature;
use App\Filament\Resources\Sites\SiteResource;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Site;
use App\Models\SiteRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * API JSON de l'éditeur de pages. Indépendante de Filament, pour pouvoir l'ouvrir plus tard aux clients.
 */
class SiteEditorApiController extends Controller
{
    public function __construct(private PagesNormalizer $normalizer) {}

    public function bootstrap(Site $site, DraftSpecFactory $drafts, BuildPreview $preview, EditorAssistant $assistant): JsonResponse
    {
        $design = $site->draft_spec !== null ? Design::forSpec($site->draft_spec) : $drafts->design($site);
        $maxPages = max(1, (int) $site->plan->max_pages);

        return response()->json([
            'site' => [
                'id' => $site->id,
                'name' => $site->brief['business_name'] ?? $site->slug,
                'activity' => $site->brief['activity'] ?? '',
                'city' => $site->brief['city'] ?? '',
                'service_area' => array_values($site->brief['service_area'] ?? []),
                'phone' => $site->brief['phone'] ?? null,
                'max_pages' => $maxPages,
                'single_page' => $maxPages === 1,
                'gallery' => $site->plan->hasFeature(PlanFeature::Gallery),
            ],
            'urls' => [
                'back' => SiteResource::getUrl('edit', ['record' => $site]),
                'design' => SiteResource::getUrl('design', ['record' => $site]),
                'preview' => $preview->url($site),
                'theme' => route('filament.admin.sites.editor.theme', ['site' => $site, 'v' => substr(md5(json_encode($design)), 0, 10)]),
            ],
            'theme' => ['body_classes' => Design::bodyClasses($design)],
            'sections' => SectionTypes::LABELS,
            'protected_pages' => PagesNormalizer::PROTECTED_PAGES,
            'reserved_slugs' => PagesNormalizer::RESERVED_SLUGS,
            'ai' => ['available' => $assistant->isAvailable()],
            'media' => $this->mediaList($site),
            'content' => $site->draft_spec === null ? null : $this->content($site),
        ]);
    }

    /**
     * Enregistre toutes les pages (enregistrement automatique de l'éditeur).
     */
    public function save(Request $request, Site $site): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string'],
            'pages' => ['required', 'array'],
        ]);

        return DB::transaction(function () use ($site, $data): JsonResponse {
            $site = Site::query()->lockForUpdate()->findOrFail($site->id);

            if ($site->draft_spec === null) {
                return response()->json(['message' => 'Ce site n\'a pas encore de contenu.'], 409);
            }

            if (! hash_equals(self::version($site), $data['version'])) {
                return response()->json([
                    'message' => 'Ces pages ont été modifiées ailleurs (autre onglet, autre personne ou rédaction par l\'IA). Rechargez pour récupérer la dernière version.',
                    'conflict' => true,
                ], 409);
            }

            $result = $this->normalizer->normalize($data['pages'], $site, array_column($site->draft_spec['pages'], 'key'));
            $spec = $site->draft_spec;
            $spec['pages'] = $result['pages'];

            Revisions::as('editor', fn () => $site->update(['draft_spec' => $spec]));

            return response()->json([
                'version' => self::version($site),
                'pages' => PageTree::toEditor($result['pages']),
                'warnings' => $result['warnings'],
            ]);
        });
    }

    /**
     * Crée un premier brouillon à partir du brief quand le site n'a encore aucun contenu.
     */
    public function draft(Site $site, DraftSpecFactory $drafts): JsonResponse
    {
        if ($site->draft_spec === null) {
            Revisions::as('draft', fn () => $site->update(['draft_spec' => $drafts->make($site)]));
            AuditLog::record('draft_generated', $site);
        }

        return response()->json(['content' => $this->content($site->refresh())]);
    }

    public function preview(Site $site, BuildPreview $preview): JsonResponse
    {
        try {
            $result = $preview->handle($site->refresh());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'La prévisualisation a échoué : '.$exception->getMessage()], 422);
        }

        return response()->json([
            'url' => $preview->url($site),
            'issues' => $result->issues,
        ]);
    }

    public function media(Site $site): JsonResponse
    {
        return response()->json(['media' => $this->mediaList($site)]);
    }

    public function upload(Request $request, Site $site, StoreUploadedMedia $store): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.config('vitrines.media.max_upload_kb'), 'mimetypes:'.implode(',', config('vitrines.media.accepted_mimes'))],
        ], [
            'file.mimetypes' => 'Seules les images JPEG, PNG ou WebP sont acceptées.',
        ]);

        $file = $request->file('file');
        $path = $file->store('incoming', config('vitrines.media.disk'));
        $media = $store->handle($site, $path, $file->getClientOriginalName());

        return response()->json(['media' => $this->mediaItem($media->refresh())], 201);
    }

    public function revisions(Site $site): JsonResponse
    {
        $revisions = $site->revisions()->with('user:id,name')->latest('id')->limit(100)->get();

        return response()->json([
            'revisions' => $revisions->map(fn (SiteRevision $revision): array => [
                'id' => $revision->id,
                'source' => $revision->source,
                'label' => $revision->sourceLabel(),
                'user' => $revision->user?->name,
                'created_at' => $revision->created_at->toIso8601String(),
                'updated_at' => $revision->updated_at->toIso8601String(),
                'pages' => count($revision->spec['pages'] ?? []),
            ]),
        ]);
    }

    /**
     * Contenu d'une version, page par page, avec le texte actuel pour la comparaison.
     */
    public function revision(Site $site, SiteRevision $revision): JsonResponse
    {
        $summarize = fn (array $pages): array => array_map(fn (array $page): array => [
            'key' => $page['key'],
            'nav_label' => $page['nav_label'],
            'slug' => $page['slug'],
            'title' => $page['title'],
            'meta_description' => $page['meta_description'],
            'text' => PageText::of($page),
        ], $pages);

        return response()->json([
            'id' => $revision->id,
            'label' => $revision->sourceLabel(),
            'created_at' => $revision->updated_at->toIso8601String(),
            'pages' => $summarize($revision->spec['pages'] ?? []),
            'current' => $summarize($site->draft_spec['pages'] ?? []),
        ]);
    }

    /**
     * Remet les pages d'une version (le design actuel est conservé). La version remplacée reste dans l'historique.
     */
    public function restore(Site $site, SiteRevision $revision): JsonResponse
    {
        $spec = $site->draft_spec ?? $revision->spec;
        $spec['pages'] = $revision->spec['pages'];
        $spec['site'] = $revision->spec['site'] ?? $spec['site'];

        Revisions::as('restore', fn () => $site->update(['draft_spec' => $spec]));
        AuditLog::record('content_restored', $site, ['revision' => $revision->id]);

        return response()->json(['content' => $this->content($site->refresh())]);
    }

    /**
     * Jeton de version : change dès que la spécification change, quel que soit l'auteur.
     */
    public static function version(Site $site): string
    {
        return hash('xxh128', json_encode($site->draft_spec));
    }

    /**
     * @return array<string, mixed>
     */
    private function content(Site $site): array
    {
        $spec = $site->draft_spec;

        return [
            'version' => self::version($site),
            'pages' => PageTree::toEditor($spec['pages']),
            'warnings' => $this->normalizer->warningsFor($spec['pages'], $site),
            'suggestions' => $spec['suggestions'] ?? [],
            'generated_by' => $spec['generated_by'] ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mediaList(Site $site): array
    {
        return $site->media()->where('status', '!=', MediaStatus::Failed)->get()->map(fn (Media $media): array => $this->mediaItem($media))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaItem(Media $media): array
    {
        $ready = $media->status === MediaStatus::Ready && $media->thumbnailPath() !== null;

        return [
            'id' => $media->id,
            'ready' => $ready,
            'thumb' => $ready ? route('filament.admin.media.thumbnail', $media) : null,
            'large' => $ready ? route('filament.admin.media.thumbnail', ['media' => $media, 'size' => 'large']) : null,
            'alt' => $media->alt,
            'caption' => $media->caption,
            'name' => $media->original_name,
            'category' => $media->category?->value,
            'category_label' => $media->category?->getLabel(),
            'ai' => $media->isAiGenerated(),
            'width' => $media->width,
            'height' => $media->height,
        ];
    }
}
