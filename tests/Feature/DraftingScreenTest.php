<?php

namespace Tests\Feature;

use App\Events\DraftPlayerPicked;
use App\Events\DraftTurnChanged;
use App\Models\Category;
use App\Models\DraftPick;
use App\Models\DraftRound;
use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use App\Services\DraftService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DraftingScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create([
            'email' => 'screenadmin@example.com',
            'is_admin' => true,
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function createDraftSetup(): array
    {
        $category = Category::create([
            'name' => 'Platinum Category',
            'order_column' => 1,
            'is_default' => true,
        ]);

        $teamAUser = User::factory()->create([
            'email' => 'teama_screen@example.com',
            'is_admin' => false,
        ]);
        $teamAUser->assignRole('team');

        $teamA = Team::create([
            'name' => 'Karachi Kings',
            'email' => $teamAUser->email,
            'league_type' => 'male',
            'max_players' => 15,
        ]);

        $teamBUser = User::factory()->create([
            'email' => 'teamb_screen@example.com',
            'is_admin' => false,
        ]);
        $teamBUser->assignRole('team');

        $teamB = Team::create([
            'name' => 'Lahore Qalandars',
            'email' => $teamBUser->email,
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

        $participantA = Participant::create([
            'first_name' => 'Babar',
            'last_name' => 'Azam',
            'nick_name' => 'Bobby',
            'category_id' => $category->id,
            'email' => 'babar.screen@example.com',
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
            'skill_categories' => ['Right Hand Batsman', 'Top Order'],
            'status' => 'approved',
            'team_id' => null,
        ]);

        $participantB = Participant::create([
            'first_name' => 'Shaheen',
            'last_name' => 'Afridi',
            'nick_name' => 'Eagle',
            'category_id' => $category->id,
            'email' => 'shaheen.screen@example.com',
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
            'passport_picture' => 'participants/passports/shaheen.jpg',
            'id_picture' => 'participants/ids/shaheen.jpg',
            'skill_categories' => ['Left-Arm Fast'],
            'status' => 'approved',
            'team_id' => null,
        ]);

        return [$round, $teamA, $teamB, $participantA, $participantB, $teamAUser, $teamBUser];
    }

    public function test_guest_cannot_access_drafting_screen(): void
    {
        $response = $this->get(route('admin.drafting-screen'));
        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_drafting_screen(): void
    {
        $nonAdmin = User::factory()->create([
            'email' => 'teamuser@example.com',
            'is_admin' => false,
        ]);
        $nonAdmin->assignRole('team');

        $response = $this->actingAs($nonAdmin)->get(route('admin.drafting-screen'));
        $this->assertTrue(in_array($response->status(), [302, 403], true));
    }

    public function test_admin_can_access_drafting_screen_with_empty_state_when_no_picks(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get(route('admin.drafting-screen'));
        $response->assertStatus(200);
        $response->assertViewIs('admin.drafting-screen');
        $response->assertSee('PTPL 2026');
        $response->assertSee('AWAITING FIRST');

        $latestJson = $this->actingAs($admin)->get(route('admin.drafting-screen.latest'));
        $latestJson->assertStatus(200);
        $latestJson->assertJsonFragment(['pickId' => 0]);
    }

    public function test_admin_sees_latest_picked_player_on_initial_load(): void
    {
        $admin = $this->createAdminUser();
        [$round, $teamA, , $participantA] = $this->createDraftSetup();

        app(DraftService::class)->pick($round, $participantA, $teamA->id);

        $response = $this->actingAs($admin)->get(route('admin.drafting-screen'));
        $response->assertStatus(200);
        $response->assertSee('BABAR AZAM');
        $response->assertSee('BOBBY');
        $response->assertSee('LAHORE');
        $response->assertSee('KARACHI KINGS');

        $latestJson = $this->actingAs($admin)->get(route('admin.drafting-screen.latest'));
        $latestJson->assertStatus(200);
        $data = $latestJson->json();
        $this->assertGreaterThan(0, $data['pickId']);
        $this->assertEquals('Babar Azam', $data['player']['fullName']);
        $this->assertEquals('Bobby', $data['player']['nickName']);
        $this->assertEquals('Lahore', $data['player']['city']);
        $this->assertEquals('Karachi Kings', $data['teamName']);
    }

    public function test_successful_player_pick_dispatches_draft_player_picked_event(): void
    {
        Event::fake([DraftPlayerPicked::class]);

        [$round, $teamA, , $participantA] = $this->createDraftSetup();

        $result = app(DraftService::class)->pick($round, $participantA, $teamA->id);
        $this->assertArrayHasKey('success', $result);

        Event::assertDispatched(DraftPlayerPicked::class, function ($event) use ($teamA) {
            return $event->player['fullName'] === 'Babar Azam'
                && $event->player['nickName'] === 'Bobby'
                && $event->teamName === $teamA->name
                && $event->leagueType === 'male';
        });
    }

    public function test_drafting_screen_latest_endpoint_advances_after_consecutive_picks(): void
    {
        $admin = $this->createAdminUser();
        [$round, $teamA, $teamB, $participantA, $participantB] = $this->createDraftSetup();
        $draftService = app(DraftService::class);

        // Pick 1
        $draftService->pick($round, $participantA, $teamA->id);
        $json1 = $this->actingAs($admin)->get(route('admin.drafting-screen.latest'))->json();
        $pick1Id = $json1['pickId'];
        $this->assertEquals('Babar Azam', $json1['player']['fullName']);

        // Resume and Pick 2
        $draftService->resume($round->fresh());
        $draftService->pick($round->fresh(), $participantB, $teamB->id);

        $json2 = $this->actingAs($admin)->get(route('admin.drafting-screen.latest'))->json();
        $pick2Id = $json2['pickId'];
        $this->assertGreaterThan($pick1Id, $pick2Id);
        $this->assertEquals('Shaheen Afridi', $json2['player']['fullName']);
        $this->assertEquals('Eagle', $json2['player']['nickName']);
        $this->assertEquals('Lahore Qalandars', $json2['teamName']);
    }

    public function test_missing_optional_details_and_photos_are_handled_gracefully(): void
    {
        $admin = $this->createAdminUser();
        $category = Category::create([
            'name' => 'Gold Category',
            'order_column' => 2,
            'is_default' => false,
        ]);

        $team = Team::create([
            'name' => 'Islamabad United',
            'email' => 'isb@example.com',
            'league_type' => 'male',
            'max_players' => 15,
        ]);

        $round = DraftRound::create([
            'category_id' => $category->id,
            'round_number' => 1,
            'status' => 'active',
            'league_type' => 'male',
            'picks_per_team' => 1,
            'start_team_id' => $team->id,
            'current_team_id' => $team->id,
            'pick_order' => [$team->id],
            'current_pick_number' => 1,
            'total_picks_planned' => 1,
            'turn_time_seconds' => 60,
            'current_turn_started_at' => Carbon::now(),
            'is_paused' => false,
            'remaining_turn_seconds' => null,
        ]);

        // Participant with minimal required data, no photo, null nickname
        $minimalPlayer = Participant::create([
            'first_name' => 'Haris',
            'last_name' => 'Rauf',
            'nick_name' => '',
            'category_id' => $category->id,
            'email' => 'haris@example.com',
            'mobile' => '03001112233',
            'emergency_contact' => '03003322110',
            'city' => '',
            'address' => 'Rawalpindi',
            'dob' => '1993-11-07',
            'nationality' => 'Pakistan',
            'league_type' => 'male',
            'identity' => '3740112345678',
            'kit_size' => 'L',
            'shirt_number' => '150',
            'passport_picture' => '',
            'id_picture' => '',
            'skill_categories' => [],
            'status' => 'approved',
            'team_id' => null,
        ]);

        app(DraftService::class)->pick($round, $minimalPlayer, $team->id);

        $response = $this->actingAs($admin)->get(route('admin.drafting-screen'));
        $response->assertStatus(200);
        $response->assertSee('HARIS RAUF');

        $json = $this->actingAs($admin)->get(route('admin.drafting-screen.latest'))->json();
        $this->assertEquals('Haris Rauf', $json['player']['fullName']);
        $this->assertEquals('—', $json['player']['nickName']);
        $this->assertEquals('—', $json['player']['role']);
        $this->assertEquals('—', $json['player']['city']);
        $this->assertNull($json['player']['passportPictureUrl']);
    }

    public function test_drafting_screen_renders_countdown_and_card_view_containers_and_handles_active_round_timer(): void
    {
        $admin = $this->createAdminUser();
        [$round, $teamA] = $this->createDraftSetup();

        $response = $this->actingAs($admin)->get(route('admin.drafting-screen'));
        $response->assertStatus(200);
        $response->assertSee('id="countdownView"', false);
        $response->assertSee('id="cardView"', false);
        $response->assertSee('id="digits"', false);
        $response->assertSee('ON THE CLOCK');
        $response->assertSee(strtoupper($teamA->name));
    }

    public function test_latest_drafting_pick_endpoint_includes_active_round_timer_details(): void
    {
        $admin = $this->createAdminUser();
        [$round, $teamA] = $this->createDraftSetup();

        $json = $this->actingAs($admin)->get(route('admin.drafting-screen.latest'))->json();

        $this->assertArrayHasKey('activeRound', $json);
        $this->assertNotNull($json['activeRound']);
        $this->assertEquals($round->id, $json['activeRound']['id']);
        $this->assertEquals('active', $json['activeRound']['status']);
        $this->assertEquals($teamA->name, $json['activeRound']['currentTeamName']);
        $this->assertGreaterThan(0, $json['activeRound']['remainingSeconds']);
        $this->assertTrue($json['activeRound']['isRunningTurn']);
    }

    public function test_drafting_screen_identifies_unstarted_or_paused_turns_as_not_running(): void
    {
        $admin = $this->createAdminUser();
        [$round, $teamA] = $this->createDraftSetup();

        // 1. Turn not yet started (current_turn_started_at is null)
        $round->update(['current_turn_started_at' => null, 'is_paused' => false]);
        $json = $this->actingAs($admin)->get(route('admin.drafting-screen.latest'))->json();
        $this->assertFalse($json['activeRound']['isRunningTurn']);
        $this->assertEquals(0, $json['activeRound']['remainingSeconds']);

        // 2. Turn paused
        $round->update(['current_turn_started_at' => now(), 'is_paused' => true, 'remaining_turn_seconds' => 45]);
        $json = $this->actingAs($admin)->get(route('admin.drafting-screen.latest'))->json();
        $this->assertFalse($json['activeRound']['isRunningTurn']);
        $this->assertTrue($json['activeRound']['isPaused']);
        $this->assertEquals(45, $json['activeRound']['remainingSeconds']);
    }

    public function test_admin_can_reset_draft_round_timer(): void
    {
        $admin = $this->createAdminUser();
        [$round, $teamA] = $this->createDraftSetup();

        // Set timer to elapsed time
        $round->update([
            'current_turn_started_at' => now()->subSeconds(25),
            'turn_time_seconds' => 60,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.draft.round.reset-timer', $round->id));
        $response->assertSessionHas('success');

        $fresh = $round->fresh();
        $this->assertNotNull($fresh->current_turn_started_at);
        $this->assertLessThanOrEqual(2, abs(now()->diffInSeconds($fresh->current_turn_started_at)));
        $this->assertNull($fresh->remaining_turn_seconds);
    }

    public function test_draft_events_broadcast_on_singular_and_plural_channels_with_consistent_payload(): void
    {
        [$round, $teamA] = $this->createDraftSetup();

        $turnEvent = new DraftTurnChanged($round, 'Turn started');
        $channels = collect($turnEvent->broadcastOn())->map(fn ($c) => $c->name)->all();
        $this->assertContains('draft.league.male', $channels);
        $this->assertContains('draft.league.males', $channels);

        $payload = $turnEvent->broadcastWith();
        $this->assertArrayHasKey('roundNumber', $payload);
        $this->assertArrayHasKey('remainingSeconds', $payload);
        $this->assertEquals((int) $round->round_number, $payload['roundNumber']);

        $participant = Participant::where('category_id', $round->category_id)->first();
        $pick = DraftPick::create([
            'draft_round_id' => $round->id,
            'team_id' => $teamA->id,
            'participant_id' => $participant->id,
            'pick_number' => 1,
            'picked_at' => now(),
        ]);

        $pickEvent = new DraftPlayerPicked($pick);
        $pickChannels = collect($pickEvent->broadcastOn())->map(fn ($c) => $c->name)->all();
        $this->assertContains('draft.league.male', $pickChannels);
        $this->assertContains('draft.league.males', $pickChannels);
    }

    public function test_drafting_screen_view_contains_presentation_toggle_functions_and_dual_listeners(): void
    {
        $admin = $this->createAdminUser();
        [$round, $teamA] = $this->createDraftSetup();

        $response = $this->actingAs($admin)->get(route('admin.drafting-screen'));
        $response->assertStatus(200);

        // Verify presentation toggle methods and sync state
        $response->assertSee('showCountdownView', false);
        $response->assertSee('showCardView', false);
        $response->assertSee('showingPickedCard', false);
        $response->assertSee('toggleViewMode', false);
        $response->assertSee('draft.league.males', false);
    }

    public function test_drafting_screen_resolves_active_round_and_latest_pick_with_plural_league_slug(): void
    {
        $admin = $this->createAdminUser();
        [$round, $teamA] = $this->createDraftSetup();

        // Update round to plural 'males'
        $round->update(['league_type' => 'males']);

        $response = $this->actingAs($admin)->get(route('admin.drafting-screen', ['league' => 'male']));
        $response->assertStatus(200);
        $response->assertSee(strtoupper($teamA->name));

        $json = $this->actingAs($admin)->get(route('admin.drafting-screen.latest', ['league' => 'male']))->json();
        $this->assertNotNull($json['activeRound']);
        $this->assertEquals($round->id, $json['activeRound']['id']);
        $this->assertEquals($teamA->name, $json['activeRound']['currentTeamName']);
    }

    public function test_drafting_screen_view_contains_countdown_synchronization_and_deduplication_guards(): void
    {
        $admin = $this->createAdminUser();
        [$round, $teamA] = $this->createDraftSetup();

        $response = $this->actingAs($admin)->get(route('admin.drafting-screen'));
        $response->assertStatus(200);

        // Verify deduplication and monotonicity synchronization logic
        $response->assertSee('lastHandledTurnSig', false);
        $response->assertSee('fonts.ready', false);
        $response->assertSee('options.source === \'poll\'', false);
        $response->assertSee('DraftTimer.setRemaining(remSec)', false);
    }

    public function test_authoritative_timer_payload_includes_server_timestamps_and_state_version(): void
    {
        $admin = $this->createAdminUser();
        [$round, $teamA] = $this->createDraftSetup();

        // 1. Verify DraftTurnChanged event contains server timestamps & stateVersion
        $turnEvent = new DraftTurnChanged($round, 'Turn started');
        $turnBroadcast = $turnEvent->broadcastWith();

        $this->assertArrayHasKey('serverTime', $turnBroadcast);
        $this->assertArrayHasKey('serverTimeMs', $turnBroadcast);
        $this->assertArrayHasKey('turnStartedAtMs', $turnBroadcast);
        $this->assertArrayHasKey('stateVersion', $turnBroadcast);
        $this->assertIsInt($turnBroadcast['serverTimeMs']);
        $this->assertIsInt($turnBroadcast['stateVersion']);
        $this->assertGreaterThan(0, $turnBroadcast['serverTimeMs']);

        // 2. Verify latest drafting screen API endpoint provides serverTimeMs and activeRound timestamps
        $json = $this->actingAs($admin)->get(route('admin.drafting-screen.latest', ['league' => 'male']))->json();
        $this->assertArrayHasKey('serverTime', $json);
        $this->assertArrayHasKey('serverTimeMs', $json);
        $this->assertArrayHasKey('stateVersion', $json);
        $this->assertNotNull($json['activeRound']);
        $this->assertArrayHasKey('turnStartedAtMs', $json['activeRound']);
        $this->assertArrayHasKey('serverTimeMs', $json['activeRound']);
        $this->assertArrayHasKey('stateVersion', $json['activeRound']);
    }
}
