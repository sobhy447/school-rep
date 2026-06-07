<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'u@test.test', bool $active = true): User
    {
        $company = Company::create(['code' => 'C1', 'name' => 'شركة', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $company->id, 'slug' => 'system_admin', 'name' => 'مدير']);

        return User::create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => 'مستخدم',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => $active,
        ]);
    }

    public function test_login_returns_token(): void
    {
        $this->makeUser();

        $res = $this->postJson('/api/login', ['email' => 'u@test.test', 'password' => 'password']);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email', 'company', 'role', 'permissions']]]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->makeUser();

        $this->postJson('/api/login', ['email' => 'u@test.test', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->makeUser(active: false);

        $this->postJson('/api/login', ['email' => 'u@test.test', 'password' => 'password'])
            ->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_returns_current_user(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'u@test.test')
            ->assertJsonPath('data.company.name', 'شركة');
    }
}
