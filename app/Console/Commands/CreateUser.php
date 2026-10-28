<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[Signature('catalog:create-user {email} {role=editor : admin, editor or reader} {--name= : Display name}')]
#[Description('Create or update a user; the password is read from STDIN, never from the arguments')]
final class CreateUser extends Command
{
    public function handle(): int
    {
        $password = $this->input->isInteractive() && posix_isatty(STDIN)
            ? (string) $this->secret('Password')
            : rtrim((string) stream_get_contents(STDIN), "\r\n");

        $data = [
            'email' => mb_strtolower((string) $this->argument('email')),
            'role' => (string) $this->argument('role'),
            'name' => (string) ($this->option('name') ?: strstr((string) $this->argument('email'), '@', true)),
            'password' => $password,
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', Password::defaults()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(['email' => $data['email']], [
            'name' => $data['name'],
            'role' => UserRole::from($data['role']),
            'password' => $data['password'],
        ]);

        $this->components->info("User {$user->email} ({$user->role->value}) saved.");

        return self::SUCCESS;
    }
}
