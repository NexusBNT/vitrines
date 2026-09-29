<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_administrator(): void
    {
        $this->artisan('users:create-admin')
            ->expectsQuestion('Nom', 'Alice')
            ->expectsQuestion('Email', 'alice@example.test')
            ->expectsQuestion('Mot de passe (12 caractères minimum)', 'un-mot-de-passe-solide')
            ->assertSuccessful();

        $this->assertSame(UserRole::Admin, User::sole()->role);
    }

    public function test_refuses_a_short_password(): void
    {
        $this->artisan('users:create-admin')
            ->expectsQuestion('Nom', 'Alice')
            ->expectsQuestion('Email', 'alice@example.test')
            ->expectsQuestion('Mot de passe (12 caractères minimum)', 'court')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
