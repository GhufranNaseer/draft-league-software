<?php

namespace Tests\Feature;

use App\Events\DraftTurnChanged;
use App\Livewire\DraftBoard;
use App\Livewire\TeamDraftDashboard;
use App\Models\Category;
use App\Models\DraftRound;
use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use App\Services\DraftService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class DraftSkipTurnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createDraftSetup(): array
    {
        $category = Category::create([
            'name' => 'Diamond Category',
            'order_column' => 1,
            'is_default' => true,
        ]);

        $teamAUser = User::factory()->create([
            'email' => 'teama@example.com',
            'is_admin' => false,
        ]);
        $teamAUser->assignRole('team');

        $teamA = Team::create([
            'name' => 'Karachi Kids',
            'email' => $teamAUser->email,
            'league_type' => 'male',
            'max_players' => 15,
        ]);

        $teamB = Team::create([
            'name' => 'Lahore Lions',
            'email' => 'teamb@example.com',
            'league_type' => 'male',
            'max_players' => 15,
        ]);

        $round = DraftRound::create([
            'category_id' => $category->id,
            'round_number' => 2,
            'status' => 'active',
            'league_type' => 'male',
            'picks_per_team' => 2,
            'start_team_id' => $teamA->id,
            'current_team_id' => $teamA->id,
            'pick_order' => [$teamA->id, $teamB->id, $teamA->id, $teamB->id],
            'current_pick_number' => 3,
            'total_picks_planned' => 4,
            'turn_time_seconds' => 60,
            'current_turn_started_at' => Carbon::now()->subSeconds(65),
            'is_paused' => false,
            'is_waiting_for_admin' => false,
            'remaining_turn_seconds' => null,
        ]);

        $participant = Participant::create([
            'first_name' => 'Shahid',
            'last_name' => 'Afridi',
            'nick_name' => 'BoomBoom',
            'category_id' => $category->id,
            'email' => 'afridi@example.com',
            'mobile' => '03001234568',
            'emergency_contact' => '03007654321',
            'city' => 'Karachi',
            'address' => 'Clifton',
            'dob' => '1980-03-01',
            'nationality' => 'Pakistan',
            'league_type' => 'male',
            'identity' => '4200012345671',
            'kit_size' => 'L',
            'shirt_number' => '10',
            'passport_picture' => 'participants/passports/afridi.jpg',
            'id_picture' => 'participants/ids/afridi.jpg',
            'status' => 'approved',
            'team_id' => null,
        ]);

        $admin = User::factory()->create([
            'email' => 'admin_test@example.com',
            'is_admin' => true,
        ]);
        $admin->assignRole('admin');

        return [$round, $teamA, $teamB, $participant, $teamAUser, $admin];
    }

    /**
     * Scenario 1: Team selects a player before expiry.
     * Expected: Normal player details screen. No skipped record.
     */
    public function test_scenario_1_player_selection_before_expiry_creates_pick_and_no_skip(): void
    {
        [$round, $teamA, $teamB, $participant] = $this->createDraftSetup();
        $round->update(['current_turn_started_at' => Carbon::now()]);

        $service = app(DraftService::class);
        $result = $service->pick($round, $participant, $teamA->id);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertDatabaseCount('draft_skips', 0);
        $this->assertDatabaseHas('draft_picks', [
            'draft_round_id' => $round->id,
            'team_id' => $teamA->id,
            'participant_id' => $participant->id,
        ]);
    }

    /**
     * Scenario 2: Timer expires without a player selection.
     * Expected: Countdown stops, round enters skipped_waiting_for_admin, draft stops, skip recorded.
     * Does NOT advance to next team.
     */
    public function test_scenario_2_timer_expiry_stops_at_zero_and_enters_waiting_for_admin_state(): void
    {
        [$round, $teamA] = $this->createDraftSetup();

        $service = app(DraftService::class);
        $this->assertTrue($service->isTurnExpired($round));

        $tickResult = $service->tick($round);

        $this->assertFalse($tickResult['advanced']);
        $this->assertTrue($tickResult['is_waiting_for_admin']);
        $this->assertNotNull($tickResult['skipped_pick']);

        $fresh = $round->fresh();
        $this->assertTrue($fresh->is_waiting_for_admin);
        $this->assertTrue($fresh->is_paused);
        $this->assertEquals(0, $fresh->remaining_turn_seconds);
        $this->assertEquals('skipped_waiting_for_admin', $fresh->getCurrentState());

        // Crucial: current team and pick number must NOT advance yet!
        $this->assertEquals($teamA->id, $fresh->current_team_id);
        $this->assertEquals(3, $fresh->current_pick_number);

        // Skipped record persisted
        $this->assertDatabaseHas('draft_skips', [
            'draft_round_id' => $round->id,
            'team_id' => $teamA->id,
            'round_number' => 2,
            'pick_number' => 3,
        ]);
    }

    /**
     * Scenario 3: Timer expires. Next team's timer does NOT start automatically.
     */
    public function test_scenario_3_timer_expires_does_not_start_next_timer_automatically(): void
    {
        [$round, $teamA] = $this->createDraftSetup();
        $service = app(DraftService::class);

        $service->tick($round);

        // Subsequent ticks while waiting for admin must do nothing
        $tick2 = $service->tick($round->fresh());
        $this->assertFalse($tick2['advanced']);
        $this->assertTrue($tick2['is_waiting_for_admin']);

        $fresh = $round->fresh();
        $this->assertEquals($teamA->id, $fresh->current_team_id);
        $this->assertEquals(3, $fresh->current_pick_number);
        $this->assertTrue($fresh->is_paused);
    }

    /**
     * Scenario 4: Admin refreshes dashboard while waiting after a skip.
     * Expected: Skipped state remains and SHOW TIMER button is visible.
     */
    public function test_scenario_4_admin_refreshes_dashboard_while_waiting_shows_show_timer_button(): void
    {
        [$round, , , , , $admin] = $this->createDraftSetup();
        $service = app(DraftService::class);
        $service->tick($round);

        $this->actingAs($admin);
        Livewire::test(DraftBoard::class, ['leagueType' => 'male'])
            ->assertSee('SHOW TIMER')
            ->assertSee('WAITING FOR ADMIN', false)
            ->assertSee('Karachi Kids | Round 2 | Pick 3')
            ->assertSee('SKIPPED');
    }

    /**
     * Scenario 5: Admin clicks SHOW TIMER.
     * Expected: Next team's full-duration countdown appears, previous skipped screen cleared.
     * State transitions to next pick in paused state with full duration.
     */
    public function test_scenario_5_admin_clicks_show_timer_advances_to_next_pick_in_paused_full_duration(): void
    {
        [$round, , $teamB, , , $admin] = $this->createDraftSetup();
        $service = app(DraftService::class);
        $service->tick($round);

        $this->actingAs($admin);
        Livewire::test(DraftBoard::class, ['leagueType' => 'male'])
            ->call('showTimer', $round->id);

        $fresh = $round->fresh();
        $this->assertFalse($fresh->is_waiting_for_admin);
        $this->assertTrue($fresh->is_paused);
        $this->assertEquals($teamB->id, $fresh->current_team_id);
        $this->assertEquals(4, $fresh->current_pick_number);
        $this->assertEquals(60, $fresh->remaining_turn_seconds);
        $this->assertNull($fresh->current_turn_started_at);
        $this->assertEquals('paused', $fresh->getCurrentState());
    }

    /**
     * Scenario 6: Admin clicks SHOW TIMER but does not click Resume.
     * Expected: Timer remains stopped/paused. No countdown progression.
     */
    public function test_scenario_6_timer_remains_paused_after_show_timer_until_resume(): void
    {
        [$round, , , , , $admin] = $this->createDraftSetup();
        $service = app(DraftService::class);
        $service->tick($round);

        $this->actingAs($admin);
        Livewire::test(DraftBoard::class, ['leagueType' => 'male'])
            ->call('showTimer', $round->id);

        $fresh = $round->fresh();
        $this->assertTrue($fresh->is_paused);

        // Advance simulated time by 30 seconds
        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        $tickResult = $service->tick($fresh);

        // Turn must NOT advance because it remains paused
        $this->assertFalse($tickResult['advanced']);
        $this->assertEquals(60, $fresh->fresh()->remaining_turn_seconds);
        $this->assertTrue($fresh->fresh()->is_paused);
        Carbon::setTestNow();
    }

    /**
     * Scenario 7: Admin clicks Resume.
     * Expected: Next team's countdown starts.
     */
    public function test_scenario_7_admin_clicks_resume_starts_countdown(): void
    {
        [$round, , , , , $admin] = $this->createDraftSetup();
        $service = app(DraftService::class);
        $service->tick($round);

        $this->actingAs($admin);
        $test = Livewire::test(DraftBoard::class, ['leagueType' => 'male'])
            ->call('showTimer', $round->id);

        // Now resume
        $test->call('resumeRound', $round->id);

        $fresh = $round->fresh();
        $this->assertFalse($fresh->is_paused);
        $this->assertNotNull($fresh->current_turn_started_at);
        $this->assertEquals('active', $fresh->getCurrentState());
    }

    /**
     * Scenario 8: Admin double-clicks SHOW TIMER.
     * Expected: Only one advancement.
     */
    public function test_scenario_8_double_click_show_timer_advances_only_once(): void
    {
        [$round, , $teamB, , , $admin] = $this->createDraftSetup();
        $service = app(DraftService::class);
        $service->tick($round);

        $this->actingAs($admin);
        $test = Livewire::test(DraftBoard::class, ['leagueType' => 'male']);

        // First click
        $test->call('showTimer', $round->id);

        $firstState = $round->fresh();
        $this->assertEquals($teamB->id, $firstState->current_team_id);
        $this->assertEquals(4, $firstState->current_pick_number);

        // Second click (immediate duplicate)
        $test->call('showTimer', $round->id);

        $secondState = $round->fresh();
        // Must still be pick 4, not advanced again
        $this->assertEquals($teamB->id, $secondState->current_team_id);
        $this->assertEquals(4, $secondState->current_pick_number);
    }

    /**
     * Scenario 9: Page refresh while timer is waiting for admin.
     * Expected: Same skipped screen, no automatic advancement.
     */
    public function test_scenario_9_page_refresh_preserves_waiting_state_and_skipped_screen(): void
    {
        [$round, $teamA, , , $teamAUser, $admin] = $this->createDraftSetup();
        $service = app(DraftService::class);
        $service->tick($round);

        // Test Drafting Screen GET
        $response = $this->actingAs($admin)->get(route('admin.drafting-screen', ['league' => 'male']));
        $response->assertStatus(200);
        $response->assertSee('skippedView');
        $response->assertSee('SKIPPED');
        $response->assertSee('WAITING FOR ADMIN TO SHOW NEXT TIMER');

        // Test Team Dashboard
        $this->actingAs($teamAUser);
        Livewire::test(TeamDraftDashboard::class, ['teamId' => $teamA->id])
            ->assertSee('Turn Skipped:', false)
            ->assertSee('Waiting for admin to show next timer.', false)
            ->assertSee('SKIPPED');
    }

    /**
     * Scenario 10: Draft reaches final pick and that pick is skipped.
     * Expected: Correct draft completion behavior without assigning an invalid next team.
     */
    public function test_scenario_10_final_pick_skipped_and_show_timer_completes_draft_cleanly(): void
    {
        [$round, $teamA, , , , $admin] = $this->createDraftSetup();
        // Round where this is pick 4 of 4 (the last pick in order)
        $round->update([
            'current_pick_number' => 4,
            'pick_order' => [$teamA->id, $teamA->id, $teamA->id, $teamA->id],
            'current_team_id' => $teamA->id,
            'total_picks_planned' => 4,
        ]);

        $service = app(DraftService::class);
        $service->tick($round);

        $this->assertTrue($round->fresh()->is_waiting_for_admin);

        // Admin clicks SHOW TIMER on the completed/last pick
        $this->actingAs($admin);
        Livewire::test(DraftBoard::class, ['leagueType' => 'male'])
            ->call('showTimer', $round->id);

        $fresh = $round->fresh();
        $this->assertEquals('completed', $fresh->status);
        $this->assertFalse($fresh->is_waiting_for_admin);
    }

    /**
     * Scenario 11: Admin uses existing Pause, Resume and Reset controls.
     * Expected: Existing functionality remains correct.
     */
    public function test_scenario_11_existing_pause_resume_reset_controls_remain_functional(): void
    {
        [$round, , , , , $admin] = $this->createDraftSetup();
        $round->update([
            'current_turn_started_at' => Carbon::now(),
            'is_paused' => false,
            'remaining_turn_seconds' => null,
            'is_waiting_for_admin' => false,
        ]);

        $this->actingAs($admin);
        $test = Livewire::test(DraftBoard::class, ['leagueType' => 'male']);

        // Pause
        $test->call('pauseRound', $round->id);
        $this->assertTrue($round->fresh()->is_paused);

        // Reset while paused: stays paused and resets remaining seconds to full duration
        $test->call('resetRoundTimer', $round->id);
        $fresh = $round->fresh();
        $this->assertTrue($fresh->is_paused);
        $this->assertEquals(60, $fresh->remaining_turn_seconds);

        // Resume: starts running
        $test->call('resumeRound', $round->id);
        $this->assertFalse($round->fresh()->is_paused);
        $this->assertNotNull($round->fresh()->current_turn_started_at);
    }

    /**
     * Authorization: Non-admin users cannot trigger SHOW TIMER.
     */
    public function test_non_admin_cannot_trigger_show_timer(): void
    {
        [$round, , , , $teamAUser] = $this->createDraftSetup();
        $service = app(DraftService::class);
        $service->tick($round);

        // Team user attempting Livewire action
        $this->actingAs($teamAUser);
        Livewire::test(DraftBoard::class, ['leagueType' => 'male'])
            ->call('showTimer', $round->id)
            ->assertForbidden();

        // Team user attempting HTTP POST endpoint
        $response = $this->actingAs($teamAUser)->post(route('admin.draft.round.show-timer', $round));
        $this->assertTrue(in_array($response->status(), [403, 302]));
    }

    /**
     * Broadcasting error does not interrupt workflow or return HTTP 500.
     */
    public function test_broadcasting_failure_does_not_break_skip_turn_workflow(): void
    {
        [$round] = $this->createDraftSetup();
        $service = app(DraftService::class);

        Event::shouldReceive('dispatch')
            ->andReturnUsing(function ($event) {
                if ($event instanceof DraftTurnChanged) {
                    throw new \RuntimeException('Failed to connect to 127.0.0.1 port 8080');
                }

                return null;
            });

        $result = $service->tick($round);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_waiting_for_admin']);
        $this->assertDatabaseCount('draft_skips', 1);
        $this->assertTrue($round->fresh()->is_waiting_for_admin);
    }
}
