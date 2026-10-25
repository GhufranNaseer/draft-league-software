<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_registration_route_remains_accessible_to_unauthenticated_visitors(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Player Registration');
    }

    public function test_login_form_renders_and_posts_to_correct_login_route(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('action="' . route('login.post') . '"', false);
        $response->assertSee('method="POST"', false);
    }

    public function test_admin_user_redirects_to_admin_dashboard_after_login(): void
    {
        $password = 'Secret123!';
        $admin = User::factory()->create([
            'email' => 'admin_redirect@example.com',
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
        $admin->assignRole('admin');

        $response = $this->post(route('login.post'), [
            'email' => $admin->email,
            'password' => $password,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        // Verify subsequent request persists session and user can access dashboard
        $subsequent = $this->get(route('admin.dashboard'));
        $subsequent->assertStatus(200);
    }

    public function test_team_user_redirects_to_team_dashboard_after_login(): void
    {
        $password = 'Secret123!';
        $teamUser = User::factory()->create([
            'email' => 'team_redirect@example.com',
            'password' => Hash::make($password),
            'is_admin' => false,
        ]);
        $teamUser->assignRole('team');

        Team::create([
            'name' => 'Test Team Alpha',
            'email' => $teamUser->email,
            'league_type' => 'male',
        ]);

        $response = $this->post(route('login.post'), [
            'email' => $teamUser->email,
            'password' => $password,
        ]);

        $response->assertRedirect(route('team.dashboard'));
        $this->assertAuthenticatedAs($teamUser);

        $subsequent = $this->get(route('team.dashboard'));
        $subsequent->assertStatus(200);
    }

    public function test_player_user_redirects_to_player_profile_after_login(): void
    {
        $password = 'Secret123!';
        $playerUser = User::factory()->create([
            'email' => 'player_redirect@example.com',
            'password' => Hash::make($password),
            'is_admin' => false,
        ]);
        $playerUser->assignRole('player');

        Participant::create([
            'first_name' => 'Test',
            'last_name' => 'Player',
            'nick_name' => 'TP',
            'email' => $playerUser->email,
            'mobile' => '03001234567',
            'emergency_contact' => '03007654321',
            'city' => 'Karachi',
            'address' => 'Clifton',
            'dob' => '2000-01-01',
            'nationality' => 'Pakistan',
            'league_type' => 'male',
            'identity' => '3520112345678',
            'kit_size' => 'L',
            'shirt_number' => '7',
            'passport_picture' => 'participants/passports/test.jpg',
            'id_picture' => 'participants/ids/test.jpg',
            'status' => 'approved',
        ]);

        $response = $this->post(route('login.post'), [
            'email' => $playerUser->email,
            'password' => $password,
        ]);

        $response->assertRedirect(route('player.profile'));
        $this->assertAuthenticatedAs($playerUser);

        $subsequent = $this->get(route('player.profile'));
        $subsequent->assertStatus(200);
    }

    public function test_login_does_not_redirect_to_public_registration_form_even_if_intended_url_is_root(): void
    {
        $password = 'Secret123!';
        $admin = User::factory()->create([
            'email' => 'admin_intended@example.com',
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
        $admin->assignRole('admin');

        // Test root URL without trailing slash
        $response = $this->withSession(['url.intended' => url('/')])
            ->post(route('login.post'), [
                'email' => $admin->email,
                'password' => $password,
            ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        // Test root URL with trailing slash
        $responseSlash = $this->withSession(['url.intended' => url('/') . '/'])
            ->post(route('login.post'), [
                'email' => $admin->email,
                'password' => $password,
            ]);

        $responseSlash->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_does_not_redirect_to_login_page_if_intended_url_is_login(): void
    {
        $password = 'Secret123!';
        $admin = User::factory()->create([
            'email' => 'admin_login_intended@example.com',
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
        $admin->assignRole('admin');

        $response = $this->withSession(['url.intended' => route('login')])
            ->post(route('login.post'), [
                'email' => $admin->email,
                'password' => $password,
            ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_authenticated_user_accessing_login_page_is_redirected_to_their_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin_already_logged_in@example.com',
            'is_admin' => true,
        ]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('login'));

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_authenticated_user_submitting_login_form_is_redirected_to_their_dashboard(): void
    {
        $teamUser = User::factory()->create([
            'email' => 'team_already_logged_in@example.com',
            'is_admin' => false,
        ]);
        $teamUser->assignRole('team');

        $response = $this->actingAs($teamUser)->post(route('login.post'), [
            'email' => $teamUser->email,
            'password' => 'irrelevant',
        ]);

        $response->assertRedirect(route('team.dashboard'));
    }

    public function test_unauthenticated_protected_route_access_redirects_to_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_invalid_credentials_preserves_error_behavior_and_redirects_back_with_errors(): void
    {
        $response = $this->from(route('login'))->post(route('login.post'), [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }
}
