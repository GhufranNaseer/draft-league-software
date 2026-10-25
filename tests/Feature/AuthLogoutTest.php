<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_get_logout_returns_method_not_allowed_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/logout');

        $response->assertStatus(405);
        $this->assertAuthenticatedAs($user);
    }

    public function test_get_logout_returns_method_not_allowed_for_guests(): void
    {
        $response = $this->get('/logout');

        $response->assertStatus(405);
    }

    public function test_authenticated_user_can_logout_via_post_request(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_guest_cannot_logout_via_post_and_is_redirected_to_login(): void
    {
        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
    }

    public function test_portal_navbar_contains_csrf_protected_post_form_and_no_get_logout_link(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'admin_test@example.com']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);

        // Asserts presence of POST form with action to logout
        $response->assertSee('action="' . route('logout') . '"', false);
        $response->assertSee('method="POST"', false);
        $response->assertSee('type="submit"', false);

        // Asserts absence of any GET anchor tag pointing to logout
        $response->assertDontSee('href="' . route('logout') . '"', false);
        $response->assertDontSee('href="/logout"', false);
    }
}
