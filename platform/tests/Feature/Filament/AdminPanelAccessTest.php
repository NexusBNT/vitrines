<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Sites\Pages\EditSite;
use App\Filament\Resources\Sites\RelationManagers\MediaRelationManager;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_user_without_two_factor_is_sent_to_setup(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/clients')
            ->assertRedirect('/admin/multi-factor-authentication/set-up');
    }

    public function test_deactivated_user_is_forbidden(): void
    {
        $this->actingAs(User::factory()->withTwoFactor()->create(['is_active' => false]))
            ->get('/admin/clients')
            ->assertForbidden();
    }

    public function test_editor_can_manage_clients_and_sites(): void
    {
        $editor = User::factory()->withTwoFactor()->create();

        $this->actingAs($editor)->get('/admin/clients')->assertOk();
        $this->actingAs($editor)->get('/admin/sites/create')->assertOk();
    }

    public function test_site_edit_page_lists_its_photos(): void
    {
        $media = Media::factory()->create();
        $otherSiteMedia = Media::factory()->create();
        $this->actingAs(User::factory()->withTwoFactor()->create());

        $this->get("/admin/sites/{$media->site_id}/edit")->assertOk();

        Livewire::test(MediaRelationManager::class, ['ownerRecord' => $media->site, 'pageClass' => EditSite::class])
            ->assertCanSeeTableRecords([$media])
            ->assertCanNotSeeTableRecords([$otherSiteMedia]);
    }

    #[DataProvider('adminOnlyPages')]
    public function test_editor_is_forbidden_from_configuration_pages(string $url): void
    {
        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get($url)
            ->assertForbidden();
    }

    #[DataProvider('adminOnlyPages')]
    public function test_admin_can_open_configuration_pages(string $url): void
    {
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create())
            ->get($url)
            ->assertOk();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function adminOnlyPages(): array
    {
        return [
            'users' => ['/admin/users'],
            'plans' => ['/admin/plans'],
            'servers' => ['/admin/servers'],
        ];
    }
}
