<?php

namespace Tests\Feature;

use App\Events\DraftTurnChanged;
use App\Models\Category;
use App\Models\DraftRound;
use App\Models\League;
use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MultiLeagueBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_turn_changed_event_is_dispatched_after_database_commit(): void
    {
        $this->assertContains(ShouldDispatchAfterCommit::class, class_implements(DraftTurnChanged::class));
    }

    public function test_admin_can_create_a_custom_league_and_use_it_for_round_setup(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole('admin');

        $category = Category::query()->create([
            'name' => 'Premier',
            'description' => 'Premier category',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.leagues.store'), [
                'name' => 'Legends League',
                'slug' => 'legends',
                'description' => 'Custom league for invited teams',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leagues', [
            'slug' => 'legends',
            'name' => 'Legends League',
        ]);

        $teamA = Team::query()->create([
            'name' => 'Lions',
            'email' => 'lions@example.com',
            'league_type' => 'legends',
            'max_players' => 2,
        ]);

        $teamB = Team::query()->create([
            'name' => 'Falcons',
            'email' => 'falcons@example.com',
            'league_type' => 'legends',
            'max_players' => 2,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.league.setup.save'), [
                'league_type' => 'legends',
                'max_players' => 2,
                'round1_order' => [$teamA->id, $teamB->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('league_round_configs', [
            'league_type' => 'legends',
            'round_number' => 1,
        ]);

        Event::fake([DraftTurnChanged::class]);

        $this->actingAs($admin)
            ->post(route('admin.draft.round.start'), [
                'league_type' => 'legends',
                'category_id' => $category->id,
                'picks_per_team' => 1,
                'turn_time_seconds' => 120,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('draft_rounds', [
            'league_type' => 'legends',
            'status' => 'active',
        ]);

        Event::assertDispatched(DraftTurnChanged::class, function (DraftTurnChanged $event) use ($teamA) {
            return $event->leagueType === 'legends'
                && $event->currentTeamId === $teamA->id
                && str_contains((string) $event->message, 'started');
        });
    }

    public function test_pick_in_round_broadcasts_the_next_team_turn(): void
    {
        $this->seed(DatabaseSeeder::class);

        League::query()->create([
            'name' => 'Legends League',
            'slug' => 'legends',
            'description' => 'Custom league for invited teams',
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Premier',
            'description' => 'Premier category',
        ]);

        $teamA = Team::query()->create([
            'name' => 'Lions',
            'email' => 'lions@example.com',
            'league_type' => 'legends',
            'max_players' => 5,
        ]);

        $teamB = Team::query()->create([
            'name' => 'Falcons',
            'email' => 'falcons@example.com',
            'league_type' => 'legends',
            'max_players' => 5,
        ]);

        $teamUser = User::factory()->create([
            'email' => 'lions@example.com',
        ]);
        $teamUser->assignRole('team');

        $participant = Participant::query()->create([
            'first_name' => 'Ahsan',
            'last_name' => 'Khan',
            'nick_name' => 'AK',
            'passport_picture' => 'passports/test.jpg',
            'id_picture' => 'ids/test.jpg',
            'skill_categories' => ['All Rounder'],
            'performance' => 'Strong hitter',
            'city' => 'Lahore',
            'address' => 'Street 1',
            'mobile' => '1234567890',
            'emergency_contact' => '1234567891',
            'email' => 'player@example.com',
            'dob' => '1998-01-01',
            'nationality' => 'Pakistan',
            'league_type' => 'legends',
            'identity' => 'ABCD12345',
            'kit_size' => 'large',
            'shirt_number' => '10',
            'status' => 'approved',
            'category_id' => $category->id,
        ]);

        $round = DraftRound::query()->create([
            'league_type' => 'legends',
            'category_id' => $category->id,
            'start_team_id' => $teamA->id,
            'current_team_id' => $teamA->id,
            'pick_order' => [$teamA->id, $teamB->id],
            'higher_category_ids' => [],
            'picks_per_team' => 1,
            'turn_time_seconds' => 120,
            'current_pick_number' => 1,
            'total_picks_planned' => 2,
            'current_turn_started_at' => now(),
            'status' => 'active',
        ]);

        Event::fake([DraftTurnChanged::class]);

        $this->actingAs($teamUser)
            ->post(route('team.draft.round.pick', ['round' => $round->id, 'participant' => $participant->id]))
            ->assertRedirect();

        Event::assertDispatched(DraftTurnChanged::class, function (DraftTurnChanged $event) use ($round, $teamB) {
            return $event->roundId === $round->id
                && $event->leagueType === 'legends'
                && $event->currentTeamId === $teamB->id;
        });
    }
}
