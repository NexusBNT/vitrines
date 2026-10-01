<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaThumbnailController extends Controller
{
    /**
     * Sert la plus petite variante WebP d'une photo (ou une variante large avec ?size=large, pour l'éditeur),
     * pour l'administration uniquement.
     */
    public function __invoke(Request $request, Media $media): StreamedResponse
    {
        $path = $request->query('size') === 'large' ? $media->variantPath(1200) : $media->thumbnailPath();

        abort_if($path === null, 404);

        return Storage::disk(config('vitrines.media.disk'))->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
