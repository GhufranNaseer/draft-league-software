<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DraftPick;
use App\Models\DraftRound;
use App\Models\LeagueRoundConfig;
use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use App\Services\DraftService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClearAllRoundsCounterResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createAdminUser(): User
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'email' => 'admin_test_'.uniqid().'@example.com',
            'is_admin' => true,
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function setupLeagueWithTeams(string $leagueType = 'male', int $teamCount = 2): array
    {
        $category = Category::create([
            'name' => 'Category '.strtoupper($leagueType),
            'order_column' => 1,
            'is_default' => true,
        ]);

        $teams = [];
        for ($i = 1; $i <= $teamCount; $i++) {
            $user = User::factory()->create([
                'email' => "team_{$leagueType}_{$i}_".uniqid().'@example.com',
                'is_admin' => false,
            ]);
            $user->assignRole('team');

            $teams[] = Team::create([
                'name' => "Team {$leagueType} {$i}",
                'email' => $user->email,
                'league_type' => $leagueType,
                'max_players' => 15,
            ]);
        }

        return [$category, $teams];
    }

    public function test_non_admin_cannot_clear_league_setup(): void
    {
        $user = User::factory()->create([
            'email' => 'regular@example.com',
            'is_admin' => false,
        ]);
        $user->assignRole('team');

        $response = $this->actingAs($user)->delete(route('admin.league.setup.clear'), [
            'league_type' => 'male',
        ]);

        $this->assertTrue(in_array($response->status(), [302, 403], true));
    }

    public function test_clear_all_rounds_clears_rounds_picks_and_unassigns_participants(): void
    {
        $admin = $this->createAdminUser();
        [$category, $teams] = $this->setupLeagueWithTeams('male', 2);
        [$teamA, $teamB] = $teams;

        // Configure round 1
        LeagueRoundConfig::create([
            'league_type' => 'male',
            'round_number' => 1,
            'team_pick_order' => [$teamA->id, $teamB->id],
            'is_manually_set' => true,
        ]);

        // Create player
        $player = Participant::create([
            'first_name' => 'Babar',
            'last_name' => 'Azam',
            'nick_name' => 'Bobby',
            'category_id' => $category->id,
            'email' => 'babar@example.com',
            'mobile' => '03001234567',
            'emergency_contact' => '03007654321',
            'city' => 'Lahore',
            'address' => 'Gulberg',
            'dob' => '1994-10-15',
            'nationality' => 'Pakistan',
            'league_type' => 'male',
            'identity' => '3520112345671',
            'kit_size' => 'L',
            'shirt_number' => '56',
            'passport_picture' => '',
            'id_picture' => '',
            'skill_categories' => ['Right Hand Batsman'],
            'status' => 'approved',
            'team_id' => $teamA->id,
            'drafted_at' => Carbon::now(),
        ]);

        // Create an active round
        $round = DraftRound::create([
            'category_id' => $category->id,
            'round_number' => 1,
            'status' => 'active',
            'league_type' => 'male',
            'picks_per_team' => 1,
            'start_team_id' => $teamA->id,
            'current_team_id' => $teamA->id,
            'pick_order' => [$teamA->id, $teamB->id],
            'current_pick_number' => 1,
            'total_picks_planned' => 2,
            'turn_time_seconds' => 60,
            'current_turn_started_at' => Carbon::now(),
        ]);

        // Create a pick
        DraftPick::create([
            'draft_round_id' => $round->id,
            'team_id' => $teamA->id,
            'participant_id' => $player->id,
            'pick_number' => 1,
            'picked_at' => Carbon::now(),
        ]);

        $this->assertDatabaseHas('draft_rounds', ['league_type' => 'male']);
        $this->assertDatabaseHas('draft_picks', ['draft_round_id' => $round->id]);
        $this->assertDatabaseHas('league_round_configs', ['league_type' => 'male']);

        // Clear All Rounds
        $response = $this->actingAs($admin)->delete(route('admin.league.setup.clear'), [
            'league_type' => 'male',
        ]);

        $response->assertRedirect(route('admin.teams', ['tab' => 'league-setup', 'league' => 'male']));

        // Verify records are completely removed from DB
        $this->assertDatabaseMissing('draft_rounds', ['league_type' => 'male']);
        $this->assertDatabaseMissing('draft_picks', ['draft_round_id' => $round->id]);
        $this->assertDatabaseMissing('league_round_configs', ['league_type' => 'male']);

        // Verify player is reset to undrafted
        $player->refresh();
        $this->assertNull($player->team_id);
        $this->assertNull($player->drafted_at);
    }

    public function test_clear_all_rounds_resets_round_counter_to_start_at_round_one_after_multiple_rounds(): void
    {
        $admin = $this->createAdminUser();
        [$category, $teams] = $this->setupLeagueWithTeams('male', 2);
        [$teamA, $teamB] = $teams;

        // Simulate 28 completed rounds in the database
        for ($i = 1; $i <= 28; $i++) {
            DraftRound::create([
                'category_id' => $category->id,
                'round_number' => $i,
                'status' => 'completed',
                'league_type' => 'male',
                'picks_per_team' => 1,
                'start_team_id' => $teamA->id,
                'current_team_id' => $teamA->id,
                'pick_order' => [$teamA->id, $teamB->id],
                'current_pick_number' => 2,
                'total_picks_planned' => 2,
                'turn_time_seconds' => 60,
                'completed_at' => Carbon::now(),
            ]);
        }

        $this->assertEquals(28, DraftRound::query()->where('league_type', 'male')->count());

        // Perform Clear All Rounds
        $this->actingAs($admin)->delete(route('admin.league.setup.clear'), [
            'league_type' => 'male',
        ]);

        $this->assertEquals(0, DraftRound::query()->where('league_type', 'male')->count());

        // Now save a new league setup for Round 1
        $this->actingAs($admin)->post(route('admin.league.setup.save'), [
            'league_type' => 'male',
            'max_players' => 15,
            'round1_order' => [$teamA->id, $teamB->id],
        ]);

        // Start the first draft round
        $startResponse = $this->actingAs($admin)->post(route('admin.draft.round.start'), [
            'league_type' => 'male',
            'category_id' => $category->id,
            'picks_per_team' => 1,
            'turn_time_seconds' => 60,
        ]);

        $startResponse->assertSessionHasNoErrors();

        // Verify the newly created round has round_number = 1
        $newRound = DraftRound::query()->where('league_type', 'male')->first();
        $this->assertNotNull($newRound);
        $this->assertEquals(1, $newRound->round_number);

        // Create player and pick in round 1
        $player = Participant::create([
            'first_name' => 'Shaheen',
            'last_name' => 'Afridi',
            'nick_name' => 'Eagle',
            'category_id' => $category->id,
            'email' => 'shaheen@example.com',
            'mobile' => '03009876543',
            'emergency_contact' => '03007654321',
            'city' => 'Peshawar',
            'address' => 'Hayatabad',
            'dob' => '2000-04-06',
            'nationality' => 'Pakistan',
            'league_type' => 'male',
            'identity' => '1730112345672',
            'kit_size' => 'XL',
            'shirt_number' => '10',
            'passport_picture' => '',
            'id_picture' => '',
            'skill_categories' => ['Left-Arm Fast'],
            'status' => 'approved',
            'team_id' => null,
        ]);

        $draftService = app(DraftService::class);
        $draftService->pick($newRound, $player, $teamA->id);

        $pick = DraftPick::query()->where('participant_id', $player->id)->first();
        $this->assertNotNull($pick);
        $this->assertEquals(1, $pick->round_number);

        // Verify Team Dashboard Draft Activity table displays Round #1
        $teamUser = User::where('email', $teamA->email)->first();
        $teamDashboard = $this->actingAs($teamUser)->get(route('team.dashboard'));
        $teamDashboard->assertStatus(200);
        $teamDashboard->assertSee('#1');
        $teamDashboard->assertDontSee('#29');
    }

    public function test_another_league_rounds_and_counter_remain_intact_when_one_league_is_cleared(): void
    {
        $admin = $this->createAdminUser();

        // Setup Male League
        [$maleCat, $maleTeams] = $this->setupLeagueWithTeams('male', 2);
        [$maleA, $maleB] = $maleTeams;

        // Setup Female League
        [$femaleCat, $femaleTeams] = $this->setupLeagueWithTeams('female', 2);
        [$femaleA, $femaleB] = $femaleTeams;

        // Create 3 completed rounds in Female league
        for ($i = 1; $i <= 3; $i++) {
            DraftRound::create([
                'category_id' => $femaleCat->id,
                'round_number' => $i,
                'status' => 'completed',
                'league_type' => 'female',
                'picks_per_team' => 1,
                'start_team_id' => $femaleA->id,
                'current_team_id' => $femaleA->id,
                'pick_order' => [$femaleA->id, $femaleB->id],
                'current_pick_number' => 2,
                'total_picks_planned' => 2,
                'turn_time_seconds' => 60,
                'completed_at' => Carbon::now(),
            ]);
        }

        // Create 2 completed rounds in Male league
        for ($i = 1; $i <= 2; $i++) {
            DraftRound::create([
                'category_id' => $maleCat->id,
                'round_number' => $i,
                'status' => 'completed',
                'league_type' => 'male',
                'picks_per_team' => 1,
                'start_team_id' => $maleA->id,
                'current_team_id' => $maleA->id,
                'pick_order' => [$maleA->id, $maleB->id],
                'current_pick_number' => 2,
                'total_picks_planned' => 2,
                'turn_time_seconds' => 60,
                'completed_at' => Carbon::now(),
            ]);
        }

        $this->assertEquals(3, DraftRound::query()->where('league_type', 'female')->count());
        $this->assertEquals(2, DraftRound::query()->where('league_type', 'male')->count());

        // Clear Male League ONLY
        $this->actingAs($admin)->delete(route('admin.league.setup.clear'), [
            'league_type' => 'male',
        ]);

        // Male league is cleared
        $this->assertEquals(0, DraftRound::query()->where('league_type', 'male')->count());

        // Female league remains completely untouched!
        $this->assertEquals(3, DraftRound::query()->where('league_type', 'female')->count());
        $femaleRoundNumbers = DraftRound::query()->where('league_type', 'female')->pluck('round_number')->all();
        $this->assertEquals([1, 2, 3], $femaleRoundNumbers);
    }

    public function test_repeated_clicks_and_empty_round_scenarios_do_not_cause_errors(): void
    {
        $admin = $this->createAdminUser();

        // 1st click with no rounds existing
        $response1 = $this->actingAs($admin)->delete(route('admin.league.setup.clear'), [
            'league_type' => 'male',
        ]);
        $response1->assertRedirect();
        $response1->assertSessionHasNoErrors();

        // 2nd consecutive click
        $response2 = $this->actingAs($admin)->delete(route('admin.league.setup.clear'), [
            'league_type' => 'male',
        ]);
        $response2->assertRedirect();
        $response2->assertSessionHasNoErrors();
    }
}
