<?php

namespace Tests\Feature\Filament;

use App\Enums\ClientStatus;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withTwoFactor()->create());
    }

    public function test_creates_an_active_client_and_records_it_in_the_audit_log(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm([
                'company_name' => 'Dupont Plomberie',
                'email' => 'contact@dupont.test',
                'siret' => '12345678901234',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = Client::sole();
        $this->assertSame('Dupont Plomberie', $client->company_name);
        $this->assertSame(ClientStatus::Active, $client->status);
        $this->assertTrue(AuditLog::where('action', 'created')->whereMorphedTo('subject', $client)->where('user_id', auth()->id())->exists());
    }

    public function test_rejects_a_siret_that_is_not_14_digits(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm([
                'company_name' => 'Dupont Plomberie',
                'email' => 'contact@dupont.test',
                'siret' => '1234',
            ])
            ->call('create')
            ->assertHasFormErrors(['siret' => 'regex']);

        $this->assertSame(0, Client::count());
    }
}
