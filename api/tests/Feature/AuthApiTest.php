<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_me_logout(): void
    {
        $token = $this->postJson('/api/v1/auth/register', ['name' => 'Test', 'email' => 't@example.com', 'password' => 'geheim123'])
            ->assertCreated()
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', 't@example.com');

        $this->postJson('/api/v1/auth/login', ['email' => 't@example.com', 'password' => 'fout'])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['email' => 't@example.com', 'password' => 'geheim123'])->assertOk()->assertJsonStructure(['token']);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    }
}
