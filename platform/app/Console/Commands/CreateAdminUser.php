<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('users:create-admin')]
#[Description('Crée un compte administrateur (la 2FA sera demandée à la première connexion)')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $data = [
            'name' => $this->ask('Nom'),
            'email' => $this->ask('Email'),
            'password' => $this->secret('Mot de passe (12 caractères minimum)'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([...$data, 'role' => UserRole::Admin]);

        $this->info("Administrateur {$data['email']} créé.");

        return self::SUCCESS;
    }
}
