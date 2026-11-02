<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamUpdateSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'team', 'guard_name' => 'web']);
    }

    public function test_updating_team_name_and_email_updates_existing_linked_user(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'is_admin' => true,
        ]);
        $admin->syncRoles(['admin']);

        // 1. Initial team and user
        $team = Team::create([
            'name' => 'Original Team',
            'email' => 'original@team.com',
            'league_type' => 'male',
            'max_players' => 15,
        ]);

        $teamUser = User::create([
            'name' => 'Original Team',
            'email' => 'original@team.com',
            'password' => Hash::make('Secret123!'),
            'is_admin' => false,
            'created_by_admin' => true,
        ]);
        $teamUser->syncRoles(['team']);

        // 2. Admin updates the team via PATCH /admin/teams/{id}
        $response = $this->actingAs($admin)->patch(route('admin.team.update', $team), [
            'name' => 'Updated Multan Team',
            'email' => 'newmultan@team.com',
            'league_type' => 'male',
            'color' => '#0080f0',
        ]);

        $response->assertSessionHas('success');

        // Verify team was updated
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Updated Multan Team',
            'email' => 'newmultan@team.com',
        ]);

        // Verify existing user was updated (same user id, same password hash)
        $teamUser->refresh();
        $this->assertEquals('Updated Multan Team', $teamUser->name);
        $this->assertEquals('newmultan@team.com', $teamUser->email);
        $this->assertTrue(Hash::check('Secret123!', $teamUser->password));

        // 3. Team user can login with new email and old password
        $this->post(route('logout'));
        $this->post(route('login.post'), [
            'email' => 'newmultan@team.com',
            'password' => 'Secret123!',
        ])->assertRedirect(route('team.dashboard'));

        // 4. Team dashboard loads with 200 OK (no 404!)
        $this->actingAs($teamUser)->get(route('team.dashboard'))->assertOk();
    }

    public function test_team_dashboard_self_heals_if_user_email_fell_out_of_sync(): void
    {
        $team = Team::create([
            'name' => 'Multan Kings',
            'email' => 'multan.new@team.com',
            'league_type' => 'male',
            'max_players' => 15,
        ]);

        // User still has old email but matching name
        $teamUser = User::create([
            'name' => 'Multan Kings',
            'email' => 'multan.old@team.com',
            'password' => Hash::make('Pass123!'),
            'is_admin' => false,
            'created_by_admin' => true,
        ]);
        $teamUser->syncRoles(['team']);

        // Visiting dashboard should NOT 404, should self-heal user's email
        $response = $this->actingAs($teamUser)->get(route('team.dashboard'));
        $response->assertOk();

        $teamUser->refresh();
        $this->assertEquals('multan.new@team.com', $teamUser->email);
    }

    public function test_editing_user_in_admin_users_syncs_team_model(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'is_admin' => true,
        ]);
        $admin->syncRoles(['admin']);

        $team = Team::create([
            'name' => 'Quetta Gladiators',
            'email' => 'quetta@team.com',
            'league_type' => 'male',
            'max_players' => 15,
        ]);

        $teamUser = User::create([
            'name' => 'Quetta Gladiators',
            'email' => 'quetta@team.com',
            'password' => Hash::make('Pass123!'),
            'is_admin' => false,
            'created_by_admin' => true,
        ]);
        $teamUser->syncRoles(['team']);

        // Admin updates user via PATCH /admin/users/{id}
        $response = $this->actingAs($admin)->patch(route('admin.user.edit', $teamUser->id), [
            'name' => 'Quetta Champions',
            'email' => 'champions@quetta.com',
            'roles' => ['team'],
        ]);

        $response->assertSessionHas('success');

        $team->refresh();
        $this->assertEquals('Quetta Champions', $team->name);
        $this->assertEquals('champions@quetta.com', $team->email);
    }
}
