<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaThumbnailControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_download_a_thumbnail(): void
    {
        $media = Media::factory()->create();

        $this->get(route('filament.admin.media.thumbnail', $media))->assertRedirect('/admin/login');
    }

    public function test_serves_the_smallest_webp_variant(): void
    {
        Storage::fake('media')->put('variants/thumb.webp', 'webp-bytes');
        $media = Media::factory()->create([
            'variants' => [['width' => 480, 'height' => 320, 'files' => ['webp' => 'variants/thumb.webp']]],
        ]);

        $response = $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get(route('filament.admin.media.thumbnail', $media));

        $response->assertOk();
        $this->assertSame('webp-bytes', $response->streamedContent());
    }

    public function test_returns_404_while_the_photo_is_not_processed(): void
    {
        $media = Media::factory()->create(['variants' => null]);

        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get(route('filament.admin.media.thumbnail', $media))
            ->assertNotFound();
    }
}
