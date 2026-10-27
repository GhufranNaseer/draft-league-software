<?php

namespace Tests\Feature;

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
use Livewire\Livewire;
use Tests\TestCase;

class DraftPausePickingTest extends TestCase
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
            'name' => 'Platinum Category',
            'order_column' => 1,
            'is_default' => true,
        ]);

        $teamAUser = User::factory()->create([
            'email' => 'teama@example.com',
            'is_admin' => false,
        ]);
        $teamAUser->assignRole('team');

        $teamA = Team::create([
            'name' => 'Team Alpha',
            'email' => $teamAUser->email,
            'league_type' => 'male',
            'max_players' => 15,
        ]);

        $teamB = Team::create([
            'name' => 'Team Beta',
            'email' => 'teamb@example.com',
            'league_type' => 'male',
            'max_players' => 15,
        ]);

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
            'is_paused' => false,
            'remaining_turn_seconds' => null,
        ]);

        $participant = Participant::create([
            'first_name' => 'Babar',
            'last_name' => 'Azam',
            'nick_name' => 'BA',
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
            'passport_picture' => 'participants/passports/babar.jpg',
            'id_picture' => 'participants/ids/babar.jpg',
            'status' => 'approved',
            'team_id' => null,
        ]);

        return [$round, $teamA, $teamAUser, $participant];
    }

    public function test_team_can_pick_player_when_round_is_active_and_not_paused(): void
    {
        [$round, $teamA, $teamAUser, $participant] = $this->createDraftSetup();

        Livewire::actingAs($teamAUser)
            ->test(TeamDraftDashboard::class, ['teamId' => $teamA->id])
            ->assertSet('teamId', $teamA->id)
            ->assertSee('Pick Player')
            ->assertDontSee('Player picking is suspended')
            ->call('pickPlayer', $participant->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('draft_picks', [
            'draft_round_id' => $round->id,
            'team_id' => $teamA->id,
            'participant_id' => $participant->id,
        ]);

        $this->assertEquals($teamA->id, $participant->fresh()->team_id);
    }

    public function test_team_dashboard_disables_button_and_rejects_livewire_pick_when_paused(): void
    {
        [$round, $teamA, $teamAUser, $participant] = $this->createDraftSetup();

        // Pause the round
        $round->update([
            'is_paused' => true,
            'remaining_turn_seconds' => 45,
        ]);

        Livewire::actingAs($teamAUser)
            ->test(TeamDraftDashboard::class, ['teamId' => $teamA->id])
            ->assertSee('Draft Paused')
            ->assertSee('Player picking is suspended')
            ->call('pickPlayer', $participant->id)
            ->assertHasErrors(['pick']);

        // Verify pick was NOT recorded
        $this->assertDatabaseMissing('draft_picks', [
            'draft_round_id' => $round->id,
            'participant_id' => $participant->id,
        ]);

        $this->assertNull($participant->fresh()->team_id);
    }

    public function test_draft_service_pick_rejects_selection_when_round_is_paused(): void
    {
        [$round, $teamA, , $participant] = $this->createDraftSetup();

        $draftService = app(DraftService::class);
        $draftService->pause($round);

        $this->assertTrue($round->fresh()->is_paused);

        $result = $draftService->pick($round->fresh(), $participant, $teamA->id);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('paused', strtolower($result['error']));

        $this->assertDatabaseCount('draft_picks', 0);
        $this->assertNull($participant->fresh()->team_id);
    }

    public function test_team_can_pick_player_after_admin_resumes_paused_round(): void
    {
        [$round, $teamA, $teamAUser, $participant] = $this->createDraftSetup();
        $draftService = app(DraftService::class);

        // Pause
        $draftService->pause($round);
        $this->assertTrue($round->fresh()->is_paused);

        $component = Livewire::actingAs($teamAUser)
            ->test(TeamDraftDashboard::class, ['teamId' => $teamA->id])
            ->assertSee('Draft Paused');

        // Resume
        $draftService->resume($round);
        $this->assertFalse($round->fresh()->is_paused);

        // Component should re-enable and pick successfully
        $component->call('turnChanged', [
            'roundId' => $round->id,
            'currentPickNumber' => $round->current_pick_number,
            'isPaused' => false,
            'turnTimeSeconds' => 60,
            'remainingTurnSeconds' => 45,
            'status' => 'active',
        ])
            ->assertDontSee('Player picking is suspended')
            ->assertSee('It is your turn to pick.')
            ->call('pickPlayer', $participant->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('draft_picks', [
            'draft_round_id' => $round->id,
            'team_id' => $teamA->id,
            'participant_id' => $participant->id,
        ]);
        $this->assertEquals($teamA->id, $participant->fresh()->team_id);
    }

    public function test_team_controller_http_route_rejects_pick_when_round_is_paused(): void
    {
        [$round, , $teamAUser, $participant] = $this->createDraftSetup();
        $draftService = app(DraftService::class);
        $draftService->pause($round);

        $response = $this->actingAs($teamAUser)
            ->post(route('team.draft.round.pick', [$round->id, $participant->id]));

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('draft_picks', 0);
        $this->assertNull($participant->fresh()->team_id);
    }

    public function test_stale_client_cannot_force_pick_after_pause(): void
    {
        [$round, $teamA, $teamAUser, $participant] = $this->createDraftSetup();

        // Mount component while round is active and canPick is true
        $component = Livewire::actingAs($teamAUser)
            ->test(TeamDraftDashboard::class, ['teamId' => $teamA->id])
            ->assertSee('Pick Player');

        // Admin pauses round in background
        app(DraftService::class)->pause($round);

        // Stale client attempts pickPlayer call
        $component->call('pickPlayer', $participant->id)
            ->assertHasErrors(['pick']);

        // Verify no pick was recorded in database
        $this->assertDatabaseMissing('draft_picks', [
            'draft_round_id' => $round->id,
            'participant_id' => $participant->id,
        ]);
        $this->assertNull($participant->fresh()->team_id);
    }

    private function createThreeTeamDraftSetup(): array
    {
        $category = Category::create([
            'name' => 'Diamond Category',
            'order_column' => 1,
            'is_default' => true,
        ]);

        $adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);
        $adminUser->assignRole('admin');

        $teamAUser = User::factory()->create(['email' => 'teama3@example.com', 'is_admin' => false]);
        $teamAUser->assignRole('team');
        $teamA = Team::create(['name' => 'Team Alpha 3', 'email' => $teamAUser->email, 'league_type' => 'male', 'max_players' => 15]);

        $teamBUser = User::factory()->create(['email' => 'teamb3@example.com', 'is_admin' => false]);
        $teamBUser->assignRole('team');
        $teamB = Team::create(['name' => 'Team Beta 3', 'email' => $teamBUser->email, 'league_type' => 'male', 'max_players' => 15]);

        $teamCUser = User::factory()->create(['email' => 'teamc3@example.com', 'is_admin' => false]);
        $teamCUser->assignRole('team');
        $teamC = Team::create(['name' => 'Team Gamma 3', 'email' => $teamCUser->email, 'league_type' => 'male', 'max_players' => 15]);

        $round = DraftRound::create([
            'category_id' => $category->id,
            'round_number' => 1,
            'status' => 'active',
            'league_type' => 'male',
            'picks_per_team' => 1,
            'start_team_id' => $teamA->id,
            'current_team_id' => $teamA->id,
            'pick_order' => [$teamA->id, $teamB->id, $teamC->id],
            'current_pick_number' => 1,
            'total_picks_planned' => 3,
            'turn_time_seconds' => 75,
            'current_turn_started_at' => Carbon::now(),
            'is_paused' => false,
            'remaining_turn_seconds' => null,
        ]);

        $players = [];
        foreach (['Player 1', 'Player 2', 'Player 3'] as $idx => $name) {
            $players[] = Participant::create([
                'first_name' => $name,
                'last_name' => 'Tester',
                'nick_name' => "P{$idx}",
                'category_id' => $category->id,
                'email' => "player{$idx}@example.com",
                'mobile' => "0300123456{$idx}",
                'emergency_contact' => '03007654321',
                'city' => 'Karachi',
                'address' => 'Clifton',
                'dob' => '1998-05-20',
                'nationality' => 'Pakistan',
                'league_type' => 'male',
                'identity' => "422011234567{$idx}",
                'kit_size' => 'M',
                'shirt_number' => (string) (10 + $idx),
                'passport_picture' => 'participants/passports/p'.$idx.'.jpg',
                'id_picture' => 'participants/ids/p'.$idx.'.jpg',
                'status' => 'approved',
                'team_id' => null,
            ]);
        }

        return [$round, $teamA, $teamB, $teamC, $teamAUser, $teamBUser, $teamCUser, $adminUser, $players];
    }

    public function test_next_team_turn_starts_paused_with_reset_timer_after_successful_pick(): void
    {
        [$round, $teamA, $teamB, , $teamAUser, $teamBUser, , $adminUser, $players] = $this->createThreeTeamDraftSetup();

        // Team A picks Player 1
        Livewire::actingAs($teamAUser)
            ->test(TeamDraftDashboard::class, ['teamId' => $teamA->id])
            ->call('pickPlayer', $players[0]->id)
            ->assertHasNoErrors();

        $freshRound = $round->fresh();

        // 1. Next team turn is assigned
        $this->assertEquals($teamB->id, $freshRound->current_team_id);
        $this->assertEquals(2, $freshRound->current_pick_number);

        // 2. Timer is reset to configured turn duration
        $this->assertEquals(75, $freshRound->remaining_turn_seconds);

        // 3. Next turn is PAUSED in database and current_turn_started_at is null
        $this->assertTrue($freshRound->is_paused);
        $this->assertNull($freshRound->current_turn_started_at);

        // 4. Team B dashboard shows paused indicator and cannot pick
        $teamBComponent = Livewire::actingAs($teamBUser)
            ->test(TeamDraftDashboard::class, ['teamId' => $teamB->id])
            ->assertSee('Draft Paused')
            ->assertSee('Player picking is suspended')
            ->call('pickPlayer', $players[1]->id)
            ->assertHasErrors(['pick']);

        // Verify Player 2 was not picked
        $this->assertNull($players[1]->fresh()->team_id);

        // 5. Admin resumes the turn
        Livewire::actingAs($adminUser)
            ->test(DraftBoard::class, ['leagueType' => 'male'])
            ->call('resumeRound', $freshRound->id)
            ->assertHasNoErrors();

        $resumedRound = $freshRound->fresh();
        $this->assertFalse($resumedRound->is_paused);
        $this->assertNull($resumedRound->remaining_turn_seconds);
        $this->assertNotNull($resumedRound->current_turn_started_at);

        // 6. Now Team B can pick Player 2 successfully
        Livewire::actingAs($teamBUser)
            ->test(TeamDraftDashboard::class, ['teamId' => $teamB->id])
            ->assertDontSee('Player picking is suspended')
            ->call('pickPlayer', $players[1]->id)
            ->assertHasNoErrors();

        $this->assertEquals($teamB->id, $players[1]->fresh()->team_id);
    }

    public function test_repeated_polling_and_refresh_do_not_resume_paused_next_turn(): void
    {
        [$round, $teamA, $teamB, , $teamAUser, $teamBUser, , , $players] = $this->createThreeTeamDraftSetup();

        // Team A picks Player 1 -> Team B turn assigned and paused
        Livewire::actingAs($teamAUser)
            ->test(TeamDraftDashboard::class, ['teamId' => $teamA->id])
            ->call('pickPlayer', $players[0]->id)
            ->assertHasNoErrors();

        $freshRound = $round->fresh();
        $this->assertTrue($freshRound->is_paused);
        $this->assertEquals(75, $freshRound->remaining_turn_seconds);

        // Repeated polling from Team B dashboard
        $teamBComponent = Livewire::actingAs($teamBUser)
            ->test(TeamDraftDashboard::class, ['teamId' => $teamB->id]);

        $teamBComponent->call('pollActiveRound');
        $teamBComponent->call('pollActiveRound');
        $teamBComponent->call('tickTurn', $freshRound->id);

        // Re-read round from database: must strictly remain paused with full remaining time
        $afterPollRound = $round->fresh();
        $this->assertTrue($afterPollRound->is_paused);
        $this->assertEquals(75, $afterPollRound->remaining_turn_seconds);
        $this->assertEquals($teamB->id, $afterPollRound->current_team_id);
        $this->assertNull($afterPollRound->current_turn_started_at);

        // Repeated polling from Admin DraftBoard
        $adminBoard = Livewire::test(DraftBoard::class, ['leagueType' => 'male']);
        $adminBoard->call('pollActiveRound');
        $adminBoard->call('tickTurn', $freshRound->id);

        $afterAdminPollRound = $round->fresh();
        $this->assertTrue($afterAdminPollRound->is_paused);
        $this->assertEquals(75, $afterAdminPollRound->remaining_turn_seconds);
        $this->assertEquals($teamB->id, $afterAdminPollRound->current_team_id);
    }

    public function test_multiple_consecutive_picks_consistently_pause_next_turn(): void
    {
        [$round, $teamA, $teamB, $teamC, $teamAUser, $teamBUser, $teamCUser, $adminUser, $players] = $this->createThreeTeamDraftSetup();
        $draftService = app(DraftService::class);

        // Turn 1: Team A picks Player 1
        $res1 = $draftService->pick($round->fresh(), $players[0], $teamA->id);
        $this->assertArrayHasKey('success', $res1);

        // Check Turn 2 (Team B) is paused with 75 seconds
        $roundAfterPick1 = $round->fresh();
        $this->assertEquals($teamB->id, $roundAfterPick1->current_team_id);
        $this->assertEquals(2, $roundAfterPick1->current_pick_number);
        $this->assertTrue($roundAfterPick1->is_paused);
        $this->assertEquals(75, $roundAfterPick1->remaining_turn_seconds);
        $this->assertNull($roundAfterPick1->current_turn_started_at);

        // Team B attempts to pick while paused -> rejected
        $pickWhilePaused = $draftService->pick($roundAfterPick1, $players[1], $teamB->id);
        $this->assertArrayHasKey('error', $pickWhilePaused);

        // Admin resumes Turn 2
        $draftService->resume($roundAfterPick1);
        $resumedTurn2 = $round->fresh();
        $this->assertFalse($resumedTurn2->is_paused);

        // Team B picks Player 2
        $res2 = $draftService->pick($resumedTurn2, $players[1], $teamB->id);
        $this->assertArrayHasKey('success', $res2);

        // Check Turn 3 (Team C) is ALSO paused with 75 seconds
        $roundAfterPick2 = $round->fresh();
        $this->assertEquals($teamC->id, $roundAfterPick2->current_team_id);
        $this->assertEquals(3, $roundAfterPick2->current_pick_number);
        $this->assertTrue($roundAfterPick2->is_paused);
        $this->assertEquals(75, $roundAfterPick2->remaining_turn_seconds);
        $this->assertNull($roundAfterPick2->current_turn_started_at);

        // Team C attempts to pick while paused -> rejected
        $pickWhilePaused2 = $draftService->pick($roundAfterPick2, $players[2], $teamC->id);
        $this->assertArrayHasKey('error', $pickWhilePaused2);

        // Admin resumes Turn 3
        $draftService->resume($roundAfterPick2);
        $resumedTurn3 = $round->fresh();
        $this->assertFalse($resumedTurn3->is_paused);

        // Team C picks Player 3
        $res3 = $draftService->pick($resumedTurn3, $players[2], $teamC->id);
        $this->assertArrayHasKey('success', $res3);

        // Round completes
        $completedRound = $round->fresh();
        $this->assertEquals('completed', $completedRound->status);
        $this->assertFalse($completedRound->is_paused);
    }
}
