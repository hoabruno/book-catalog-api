<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_editor_gets_a_token_with_write_ability_and_can_use_it(): void
    {
        User::factory()->editor()->create(['email' => 'editor@example.test', 'password' => 'correct-horse']);

        $response = $this->postJson('/api/v1/tokens', [
            'email' => 'editor@example.test', 'password' => 'correct-horse', 'device_name' => 'cli',
        ])->assertCreated()->assertJsonPath('abilities', ['catalog:read', 'catalog:write']);

        $this->withToken($response->json('token'))
            ->postJson('/api/v1/books', [])
            ->assertUnprocessable(); // authenticated and authorised: only validation remains
    }

    #[Test]
    public function a_reader_token_cannot_write(): void
    {
        User::factory()->create(['email' => 'reader@example.test', 'password' => 'correct-horse']);

        $token = $this->postJson('/api/v1/tokens', [
            'email' => 'reader@example.test', 'password' => 'correct-horse', 'device_name' => 'cli',
        ])->json('token');

        $this->withToken($token)->postJson('/api/v1/books', [])->assertForbidden();
    }

    #[Test]
    public function wrong_credentials_do_not_reveal_which_field_is_wrong(): void
    {
        User::factory()->create(['email' => 'reader@example.test']);

        foreach (['reader@example.test', 'nobody@example.test'] as $email) {
            $this->postJson('/api/v1/tokens', ['email' => $email, 'password' => 'nope', 'device_name' => 'cli'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('email')
                ->assertJsonMissingValidationErrors('password');
        }
    }

    #[Test]
    public function token_requests_are_rate_limited(): void
    {
        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/tokens', ['email' => 'x@example.test', 'password' => 'nope', 'device_name' => 'cli']);
        }

        $this->postJson('/api/v1/tokens', ['email' => 'x@example.test', 'password' => 'nope', 'device_name' => 'cli'])
            ->assertTooManyRequests();
    }

    #[Test]
    public function a_token_can_be_revoked(): void
    {
        User::factory()->editor()->create(['email' => 'editor@example.test', 'password' => 'correct-horse']);
        $token = $this->postJson('/api/v1/tokens', [
            'email' => 'editor@example.test', 'password' => 'correct-horse', 'device_name' => 'cli',
        ])->json('token');

        $this->withToken($token)->deleteJson('/api/v1/tokens/current')->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/books', [])->assertUnauthorized();
    }
}
