<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Fixed so the token in the README works on every fresh install.
     * Sanctum only stores the SHA-256 hash, so this is the plain text half
     * of the bearer token. A real app would use createToken(), which
     * generates a random one and shows it once.
     */
    public const API_TOKEN_PLAIN = 'nib1AjgQH55Gri1Ywd3YSn1JFiSFUWDW65XTnJAN';

    public function run(): void
    {
        $this->call(VehicleSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $token = $user->tokens()->create([
            'name' => 'test',
            'token' => hash('sha256', self::API_TOKEN_PLAIN),
            'abilities' => ['*'],
        ]);

        $this->command->info("API token for {$user->email}: {$token->id}|".self::API_TOKEN_PLAIN);
    }
}
