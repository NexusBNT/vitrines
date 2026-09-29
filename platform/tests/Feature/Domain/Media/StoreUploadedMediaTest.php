<?php

namespace Tests\Feature\Domain\Media;

use App\Domain\Media\StoreUploadedMedia;
use App\Enums\MediaStatus;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreUploadedMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_moves_upload_to_originals_and_processes_it(): void
    {
        $disk = Storage::fake('media');
        $site = Site::factory()->create();
        $disk->put('incoming/abc.jpg', $this->jpeg());

        $media = (new StoreUploadedMedia)->handle($site, 'incoming/abc.jpg', 'Chantier.jpg');

        $disk->assertMissing('incoming/abc.jpg');
        $disk->assertExists($media->original_path);
        $this->assertStringStartsWith("originals/{$site->directoryName()}/", $media->original_path);
        $this->assertSame('Chantier.jpg', $media->original_name);
        $this->assertSame(MediaStatus::Ready, $media->fresh()->status);
    }

    public function test_rejects_a_script_disguised_as_an_image(): void
    {
        $disk = Storage::fake('media');
        $site = Site::factory()->create();
        $disk->put('incoming/evil.jpg', '<?php system($_GET["c"]);');

        try {
            (new StoreUploadedMedia)->handle($site, 'incoming/evil.jpg', 'evil.jpg');
            $this->fail('Le fichier aurait dû être refusé.');
        } catch (ValidationException) {
            $disk->assertMissing('incoming/evil.jpg');
            $this->assertSame(0, $site->media()->count());
        }
    }

    public function test_returns_the_existing_media_when_the_same_photo_is_uploaded_twice(): void
    {
        $disk = Storage::fake('media');
        $site = Site::factory()->create();
        $disk->put('incoming/one.jpg', $this->jpeg());
        $disk->put('incoming/two.jpg', $this->jpeg());

        $first = (new StoreUploadedMedia)->handle($site, 'incoming/one.jpg', 'one.jpg');
        $second = (new StoreUploadedMedia)->handle($site, 'incoming/two.jpg', 'two.jpg');

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $site->media()->count());
        $disk->assertMissing('incoming/two.jpg');
    }

    public function test_deleting_media_removes_original_and_variants(): void
    {
        $disk = Storage::fake('media');
        $site = Site::factory()->create();
        $disk->put('incoming/abc.jpg', $this->jpeg());
        $media = (new StoreUploadedMedia)->handle($site, 'incoming/abc.jpg', 'abc.jpg')->fresh();
        $variant = $media->thumbnailPath();

        $media->delete();

        $disk->assertMissing($media->original_path);
        $disk->assertMissing($variant);
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(640, 480);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 40));
        ob_start();
        imagejpeg($image);

        return ob_get_clean();
    }
}
