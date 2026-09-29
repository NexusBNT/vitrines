<?php

namespace Tests\Feature\Filament;

use App\Enums\SiteStatus;
use App\Filament\Resources\Sites\Pages\CreateSite;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withTwoFactor()->create());
    }

    public function test_selecting_a_client_prefills_the_brief_with_its_details(): void
    {
        $client = Client::factory()->create([
            'company_name' => 'Martin Électricité',
            'email' => 'martin@example.test',
            'city' => 'Rennes',
        ]);

        Livewire::test(CreateSite::class)
            ->fillForm(['client_id' => $client->id])
            ->assertFormSet([
                'slug' => 'martin-electricite',
                'brief.business_name' => 'Martin Électricité',
                'brief.email' => 'martin@example.test',
                'brief.city' => 'Rennes',
            ]);
    }

    public function test_creates_a_draft_site_with_its_brief(): void
    {
        $client = Client::factory()->create();
        $plan = Plan::factory()->create();

        Livewire::test(CreateSite::class)
            ->fillForm($this->validForm($client, $plan))
            ->call('create')
            ->assertHasNoFormErrors();

        $site = Site::sole();
        $this->assertSame(SiteStatus::Draft, $site->status);
        $this->assertSame('Plombier', $site->brief['activity']);
        $this->assertSame(['Vitré', 'Fougères'], $site->brief['service_area']);
        $this->assertSame(32, strlen($site->public_key));
    }

    public function test_rejects_a_slug_with_forbidden_characters(): void
    {
        $form = $this->validForm(Client::factory()->create(), Plan::factory()->create());

        Livewire::test(CreateSite::class)
            ->fillForm([...$form, 'slug' => '../etc'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'regex']);
    }

    public function test_rejects_a_slug_already_used_by_another_site(): void
    {
        Site::factory()->create(['slug' => 'dupont']);
        $form = $this->validForm(Client::factory()->create(), Plan::factory()->create());

        Livewire::test(CreateSite::class)
            ->fillForm([...$form, 'slug' => 'dupont'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validForm(Client $client, Plan $plan): array
    {
        return [
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'slug' => 'dupont',
            'theme' => 'artisan',
            'brief' => [
                'business_name' => 'Dupont',
                'activity' => 'Plombier',
                'services' => [['name' => 'Dépannage', 'description' => null]],
                'email' => 'contact@dupont.test',
                'city' => 'Rennes',
                'service_area' => ['Vitré', 'Fougères'],
                'colors' => ['primary' => '#1d4ed8'],
                'style' => 'moderne',
            ],
        ];
    }
}
