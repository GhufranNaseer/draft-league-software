<?php

namespace Tests\Feature;

use App\Events\DraftTurnChanged;
use App\Livewire\DraftBoard;
use App\Livewire\TeamDraftDashboard;
use App\Models\Category;
use App\Models\DraftPick;
use App\Models\DraftRound;
use App\Models\League;
use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use App\Services\DraftService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TicketRequirementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    public function test_admin_can_toggle_league_active_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'admin@example.com']);
        $admin->assignRole('admin');

        $league = League::query()->where('slug', 'male')->firstOrFail();
        $this->assertTrue($league->is_active);

        // Deactivate league
        $this->actingAs($admin)
            ->patch(route('admin.leagues.toggle', $league->id))
            ->assertRedirect();

        $league->refresh();
        $this->assertFalse($league->is_active);

        // Reactivate league
        $this->actingAs($admin)
            ->patch(route('admin.leagues.toggle', $league->id))
            ->assertRedirect();

        $league->refresh();
        $this->assertTrue($league->is_active);
    }

    public function test_inactive_league_is_hidden_from_public_registration_and_rejected(): void
    {
        $league = League::query()->where('slug', 'female')->firstOrFail();
        $league->update(['is_active' => false]);

        // Public registration form does not show inactive league in options
        $response = $this->get(route('form.index'));
        $response->assertStatus(200);
        $response->assertDontSee('value="female"', false);

        // Submitting with inactive league is rejected by backend validation
        $submitData = [
            'first_name' => 'Sara',
            'last_name' => 'Khan',
            'nick_name' => 'SK',
            'passport_picture' => UploadedFile::fake()->image('passport.jpg', 700, 900),
            'id_picture' => UploadedFile::fake()->image('cnic.jpg', 800, 500),
            'skill_categories' => ['Right Hand Batsman'],
            'city' => 'Lahore',
            'address' => 'Mall Road',
            'mobile' => '03001234567',
            'emergency_contact' => '03007654321',
            'email' => 'sara@example.com',
            'dob' => '2000-01-01',
            'nationality' => 'Pakistan',
            'league_type' => 'female',
            'identity' => '3520112345671',
            'kit_size' => 'medium',
            'shirt_number' => '10',
        ];

        $postResponse = $this->postJson(route('form.submit'), $submitData);
        $postResponse->assertStatus(422);
        $postResponse->assertJsonValidationErrors(['league_type']);
    }

    public function test_inactive_league_is_rejected_for_team_creation(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'admin@example.com']);
        $admin->assignRole('admin');

        $league = League::query()->where('slug', 'female')->firstOrFail();
        $league->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('admin.team.create'), [
                'name' => 'Inactive League Team',
                'email' => 'inactiveteam@example.com',
                'league_type' => 'female',
            ])
            ->assertSessionHasErrors(['league_type']);
    }

    public function test_timer_pause_and_resume_preserves_remaining_time_and_prevents_expiry(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'admin@example.com']);
        $admin->assignRole('admin');

        $category = Category::query()->create(['name' => 'Platinum']);
        $team = Team::query()->create([
            'name' => 'Lions',
            'email' => 'lions@example.com',
            'league_type' => 'male',
            'max_players' => 5,
        ]);

        $round = DraftRound::query()->create([
            'league_type' => 'male',
            'category_id' => $category->id,
            'start_team_id' => $team->id,
            'current_team_id' => $team->id,
            'pick_order' => [$team->id],
            'picks_per_team' => 1,
            'turn_time_seconds' => 60,
            'current_pick_number' => 1,
            'total_picks_planned' => 1,
            'current_turn_started_at' => Carbon::now()->subSeconds(20),
            'status' => 'active',
        ]);

        $draftService = app(DraftService::class);

        // Pause the round
        $this->actingAs($admin)
            ->post(route('admin.draft.round.pause', $round->id))
            ->assertRedirect();

        $round->refresh();
        $this->assertTrue($round->is_paused);
        // Approx 40 seconds should be preserved (60 - 20)
        $this->assertGreaterThanOrEqual(38, $round->remaining_turn_seconds);
        $this->assertLessThanOrEqual(41, $round->remaining_turn_seconds);

        // In paused state, isTurnExpired must be false even if lots of time elapses
        $this->assertFalse($draftService->isTurnExpired($round));

        // In paused state, tick must not advance the turn
        $tickResult = $draftService->tick($round);
        $this->assertFalse($tickResult['advanced']);
        $this->assertFalse($tickResult['round_closed']);
        $round->refresh();
        $this->assertEquals('active', $round->status);

        // Resume the round
        $this->actingAs($admin)
            ->post(route('admin.draft.round.resume', $round->id))
            ->assertRedirect();

        $round->refresh();
        $this->assertFalse($round->is_paused);
        $this->assertNull($round->remaining_turn_seconds);

        // Elapsed time from current_turn_started_at should be approximately 20 seconds, NOT 0
        $elapsed = abs(Carbon::now()->diffInSeconds($round->current_turn_started_at));
        $this->assertGreaterThanOrEqual(18, $elapsed);
        $this->assertLessThanOrEqual(22, $elapsed);
    }

    public function test_pause_and_resume_handles_broadcaster_offline_gracefully(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'admin_offline@example.com']);
        $admin->assignRole('admin');

        $category = Category::query()->create(['name' => 'Silver']);
        $team = Team::query()->create([
            'name' => 'Tigers',
            'email' => 'tigers@example.com',
            'league_type' => 'male',
            'max_players' => 5,
        ]);

        $round = DraftRound::query()->create([
            'league_type' => 'male',
            'category_id' => $category->id,
            'start_team_id' => $team->id,
            'current_team_id' => $team->id,
            'pick_order' => [$team->id],
            'picks_per_team' => 1,
            'turn_time_seconds' => 60,
            'current_pick_number' => 1,
            'total_picks_planned' => 1,
            'current_turn_started_at' => Carbon::now()->subSeconds(15),
            'status' => 'active',
        ]);

        // Livewire Pause action must succeed without HTTP 500 even when WebSocket server is offline
        Livewire::actingAs($admin)
            ->test(DraftBoard::class, ['leagueType' => 'male'])
            ->call('pauseRound', $round->id)
            ->assertOk();

        $round->refresh();
        $this->assertTrue($round->is_paused);
        $this->assertIsInt($round->remaining_turn_seconds);
        $this->assertGreaterThanOrEqual(43, $round->remaining_turn_seconds);
        $this->assertLessThanOrEqual(45, $round->remaining_turn_seconds);

        // Resume action must succeed without HTTP 500
        Livewire::actingAs($admin)
            ->test(DraftBoard::class, ['leagueType' => 'male'])
            ->call('resumeRound', $round->id)
            ->assertOk();

        $round->refresh();
        $this->assertFalse($round->is_paused);
        $this->assertNull($round->remaining_turn_seconds);
    }

    public function test_team_draft_dashboard_displays_passport_picture_and_no_cnic_fallback(): void
    {
        $team = Team::query()->create([
            'name' => 'Hawks',
            'email' => 'hawks@example.com',
            'league_type' => 'male',
            'max_players' => 10,
        ]);

        $teamUser = User::factory()->create(['email' => 'hawks@example.com']);
        $teamUser->assignRole('team');

        $category = Category::query()->create(['name' => 'Diamond']);

        $round = DraftRound::query()->create([
            'league_type' => 'male',
            'category_id' => $category->id,
            'start_team_id' => $team->id,
            'current_team_id' => $team->id,
            'pick_order' => [$team->id],
            'picks_per_team' => 1,
            'turn_time_seconds' => 60,
            'current_pick_number' => 1,
            'total_picks_planned' => 1,
            'current_turn_started_at' => Carbon::now(),
            'status' => 'active',
        ]);

        // Player 1 has ONLY id_picture (CNIC), NO passport picture
        $playerWithoutPassport = Participant::query()->create([
            'first_name' => 'Abdullah',
            'last_name' => 'Shahid',
            'nick_name' => 'AS',
            'email' => 'abdullah@example.com',
            'mobile' => '03001111111',
            'emergency_contact' => '03002222222',
            'dob' => '1995-05-05',
            'nationality' => 'Pakistan',
            'league_type' => 'male',
            'identity' => '3520199999991',
            'kit_size' => 'large',
            'shirt_number' => '7',
            'city' => 'Karachi',
            'address' => 'Clifton',
            'status' => 'approved',
            'id_picture' => 'ids/cnic_card_secret.jpg',
            'passport_picture' => '',
            'team_id' => $team->id,
        ]);

        DraftPick::query()->create([
            'draft_round_id' => $round->id,
            'team_id' => $team->id,
            'participant_id' => $playerWithoutPassport->id,
            'pick_number' => 1,
            'picked_at' => Carbon::now(),
        ]);

        // Render Livewire component
        $component = Livewire::actingAs($teamUser)
            ->test(TeamDraftDashboard::class, ['teamId' => $team->id]);

        // CNIC image path must NOT appear in the rendered HTML
        $component->assertDontSee('ids/cnic_card_secret.jpg');
        // Placeholder person icon should be rendered
        $component->assertSee('bi-person');

        // Player 2 has a valid passport picture
        $playerWithPassport = Participant::query()->create([
            'first_name' => 'Bilal',
            'last_name' => 'Ahmed',
            'nick_name' => 'BA',
            'email' => 'bilal@example.com',
            'mobile' => '03003333333',
            'emergency_contact' => '03004444444',
            'dob' => '1996-06-06',
            'nationality' => 'Pakistan',
            'league_type' => 'male',
            'identity' => '3520188888881',
            'kit_size' => 'medium',
            'shirt_number' => '9',
            'city' => 'Karachi',
            'address' => 'Gulshan',
            'status' => 'approved',
            'id_picture' => 'ids/bilal_cnic.jpg',
            'passport_picture' => 'passports/bilal_passport.jpg',
            'team_id' => $team->id,
        ]);

        DraftPick::query()->create([
            'draft_round_id' => $round->id,
            'team_id' => $team->id,
            'participant_id' => $playerWithPassport->id,
            'pick_number' => 2,
            'picked_at' => Carbon::now(),
        ]);

        $componentAfter = Livewire::actingAs($teamUser)
            ->test(TeamDraftDashboard::class, ['teamId' => $team->id]);

        // Bilal's passport picture must be rendered, but NOT his CNIC
        $componentAfter->assertSee('passports/bilal_passport.jpg');
        $componentAfter->assertDontSee('ids/bilal_cnic.jpg');
    }

    public function test_participant_registration_accepts_7_by_9_aspect_ratio_photo(): void
    {
        // 7:9 ratio image dimensions (e.g., width: 700px, height: 900px)
        $croppedPassportPhoto = UploadedFile::fake()->image('passport_cropped_7x9.jpg', 700, 900);
        $cnicPhoto = UploadedFile::fake()->image('cnic.jpg', 1000, 600);

        $submitData = [
            'first_name' => 'Usman',
            'last_name' => 'Ali',
            'nick_name' => 'UA',
            'passport_picture' => $croppedPassportPhoto,
            'id_picture' => $cnicPhoto,
            'skill_categories' => ['Right Hand Batsman', 'Wicket Keeper'],
            'city' => 'Islamabad',
            'address' => 'F-7/2',
            'mobile' => '03009876543',
            'emergency_contact' => '03001239876',
            'email' => 'usman.ali@example.com',
            'dob' => '1998-08-15',
            'nationality' => 'Pakistan',
            'league_type' => 'male',
            'identity' => '3520155555551',
            'kit_size' => 'large',
            'shirt_number' => '18',
        ];

        $response = $this->postJson(route('form.submit'), $submitData);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $participant = Participant::query()->where('email', 'usman.ali@example.com')->firstOrFail();
        $this->assertNotNull($participant->passport_picture);
        $this->assertNotNull($participant->id_picture);
        $this->assertNotEquals($participant->passport_picture, $participant->id_picture);
        Storage::disk('public')->assertExists($participant->passport_picture);
        Storage::disk('public')->assertExists($participant->id_picture);
    }

    public function test_registration_form_renders_crop_modal_properly_outside_spinner(): void
    {
        $response = $this->get(route('form.index'));
        $response->assertStatus(200);

        // Assets must be present
        $response->assertSee('cropper.min.css');
        $response->assertSee('cropper.min.js');

        // Modal and elements must be present
        $response->assertSee('id="cropModal"', false);
        $response->assertSee('id="cropImage"', false);
        $response->assertSee('id="btnApplyCrop"', false);
        $response->assertSee('id="cropResultPreview"', false);

        // Modal must be outside the spinner container (spinner must close before cropModal)
        $content = $response->getContent();
        $spinnerPos = strpos($content, 'id="spinner"');
        $cropModalPos = strpos($content, 'id="cropModal"');
        $this->assertNotFalse($spinnerPos);
        $this->assertNotFalse($cropModalPos);

        // Ensure closing </div> for spinner container exists between spinner and cropModal
        $between = substr($content, $spinnerPos, $cropModalPos - $spinnerPos);
        $this->assertStringContainsString('</div>', $between);
        $this->assertStringContainsString('<div class="spinner"></div>', $between);

        // Verify 7:9 ratio and 700x900 dimension configs in script
        $response->assertSee('7 / 9', false);
        $response->assertSee('width: 700', false);
        $response->assertSee('height: 900', false);
    }

    public function test_repeated_pause_and_resume_cycles_are_idempotent_and_preserve_time(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'admin_timer@example.com']);
        $admin->assignRole('admin');

        $category = Category::query()->create(['name' => 'Gold']);
        $team = Team::query()->create([
            'name' => 'Eagles',
            'email' => 'eagles@example.com',
            'league_type' => 'male',
            'max_players' => 5,
        ]);

        $round = DraftRound::query()->create([
            'league_type' => 'male',
            'category_id' => $category->id,
            'start_team_id' => $team->id,
            'current_team_id' => $team->id,
            'pick_order' => [$team->id],
            'picks_per_team' => 1,
            'turn_time_seconds' => 60,
            'current_pick_number' => 1,
            'total_picks_planned' => 1,
            'current_turn_started_at' => Carbon::now()->subSeconds(10),
            'status' => 'active',
        ]);

        $draftService = app(DraftService::class);

        // 1. Initial Pause
        $draftService->pause($round);
        $round->refresh();
        $this->assertTrue($round->is_paused);
        $preserved = (int) $round->remaining_turn_seconds;
        $this->assertGreaterThanOrEqual(48, $preserved);
        $this->assertLessThanOrEqual(51, $preserved);

        // 2. Duplicate Pause (idempotent - should not change preserved time)
        $draftService->pause($round);
        $round->refresh();
        $this->assertEquals($preserved, (int) $round->remaining_turn_seconds);

        // 3. Simulated time pass while paused (should not expire or change preserved time)
        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        $this->assertFalse($draftService->isTurnExpired($round));
        $round->refresh();
        $this->assertEquals($preserved, (int) $round->remaining_turn_seconds);

        // 4. Resume
        $draftService->resume($round);
        $round->refresh();
        $this->assertFalse($round->is_paused);
        $this->assertNull($round->remaining_turn_seconds);
        $elapsedAfterResume = (int) round(abs(Carbon::now()->diffInSeconds($round->current_turn_started_at)));
        $this->assertEqualsWithDelta(60 - $preserved, $elapsedAfterResume, 1);

        // 5. Duplicate Resume (idempotent)
        $initialStartedAt = $round->current_turn_started_at;
        $draftService->resume($round);
        $round->refresh();
        $this->assertEquals($initialStartedAt->timestamp, $round->current_turn_started_at->timestamp);

        // 6. Rapid 5 pause/resume cycles
        for ($i = 0; $i < 5; $i++) {
            $draftService->pause($round);
            $round->refresh();
            $this->assertTrue($round->is_paused);
            $this->assertIsInt($round->remaining_turn_seconds);
            $this->assertGreaterThan(0, $round->remaining_turn_seconds);

            $draftService->resume($round);
            $round->refresh();
            $this->assertFalse($round->is_paused);
            $this->assertNull($round->remaining_turn_seconds);
        }

        Carbon::setTestNow(); // Reset clock
    }

    public function test_polling_active_round_does_not_advance_or_reset_when_paused(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'admin_poll@example.com']);
        $admin->assignRole('admin');

        $category = Category::query()->create(['name' => 'Bronze']);
        $team1 = Team::query()->create(['name' => 'Team A', 'email' => 'teama@example.com', 'league_type' => 'male', 'max_players' => 5]);
        $team2 = Team::query()->create(['name' => 'Team B', 'email' => 'teamb@example.com', 'league_type' => 'male', 'max_players' => 5]);

        $round = DraftRound::query()->create([
            'league_type' => 'male',
            'category_id' => $category->id,
            'start_team_id' => $team1->id,
            'current_team_id' => $team1->id,
            'pick_order' => [$team1->id, $team2->id],
            'picks_per_team' => 1,
            'turn_time_seconds' => 30,
            'current_pick_number' => 1,
            'total_picks_planned' => 2,
            'current_turn_started_at' => Carbon::now()->subSeconds(35), // Expired elapsed time
            'status' => 'active',
            'is_paused' => true,
            'remaining_turn_seconds' => 15,
        ]);

        // Livewire pollActiveRound on DraftBoard
        Livewire::actingAs($admin)
            ->test(DraftBoard::class, ['leagueType' => 'male'])
            ->call('pollActiveRound')
            ->assertOk();

        $round->refresh();
        // Must still be paused and not advanced
        $this->assertTrue($round->is_paused);
        $this->assertEquals(1, $round->current_pick_number);
        $this->assertEquals($team1->id, $round->current_team_id);
        $this->assertEquals(15, $round->remaining_turn_seconds);
        $this->assertEquals('active', $round->status);

        // Livewire pollActiveRound on TeamDraftDashboard
        Livewire::actingAs($admin)
            ->test(TeamDraftDashboard::class, ['teamId' => $team1->id])
            ->call('pollActiveRound')
            ->assertOk();

        $round->refresh();
        $this->assertTrue($round->is_paused);
        $this->assertEquals(1, $round->current_pick_number);
    }

    public function test_draft_turn_changed_broadcast_contains_timer_synchronization_data(): void
    {
        $category = Category::query()->create(['name' => 'Diamond Timer']);
        $team = Team::query()->create([
            'name' => 'Panthers',
            'email' => 'panthers@example.com',
            'league_type' => 'male',
            'max_players' => 5,
        ]);

        $round = DraftRound::query()->create([
            'league_type' => 'male',
            'category_id' => $category->id,
            'start_team_id' => $team->id,
            'current_team_id' => $team->id,
            'pick_order' => [$team->id],
            'picks_per_team' => 1,
            'turn_time_seconds' => 120,
            'current_pick_number' => 1,
            'total_picks_planned' => 1,
            'current_turn_started_at' => Carbon::now(),
            'status' => 'active',
            'is_paused' => true,
            'remaining_turn_seconds' => 85,
        ]);

        $event = new DraftTurnChanged($round, 'Timer state updated.');
        $payload = $event->broadcastWith();

        $this->assertEquals($round->id, $payload['roundId']);
        $this->assertEquals('male', $payload['leagueType']);
        $this->assertEquals(1, $payload['currentPickNumber']);
        $this->assertEquals(120, $payload['turnTimeSeconds']);
        $this->assertTrue($payload['isPaused']);
        $this->assertEquals(85, $payload['remainingTurnSeconds']);
    }

    public function test_timer_expiry_advances_turn_and_initializes_next_timer(): void
    {
        $category = Category::query()->create(['name' => 'Next Round Cat']);
        $team1 = Team::query()->create(['name' => 'Team 1', 'email' => 't1@example.com', 'league_type' => 'male', 'max_players' => 5]);
        $team2 = Team::query()->create(['name' => 'Team 2', 'email' => 't2@example.com', 'league_type' => 'male', 'max_players' => 5]);

        $round = DraftRound::query()->create([
            'league_type' => 'male',
            'category_id' => $category->id,
            'start_team_id' => $team1->id,
            'current_team_id' => $team1->id,
            'pick_order' => [$team1->id, $team2->id],
            'picks_per_team' => 1,
            'turn_time_seconds' => 60,
            'current_pick_number' => 1,
            'total_picks_planned' => 2,
            'current_turn_started_at' => Carbon::now()->subSeconds(65), // expired
            'status' => 'active',
            'is_paused' => false,
        ]);

        $draftService = app(DraftService::class);
        $this->assertTrue($draftService->isTurnExpired($round));

        $tickResult = $draftService->tick($round);
        $this->assertTrue($tickResult['advanced']);

        $round->refresh();
        $this->assertEquals(2, $round->current_pick_number);
        $this->assertEquals($team2->id, $round->current_team_id);
        $this->assertFalse($round->is_paused);
        $this->assertNull($round->remaining_turn_seconds);
        // Turn timer reset to now
        $this->assertLessThanOrEqual(2, abs(Carbon::now()->diffInSeconds($round->current_turn_started_at)));
    }

    public function test_running_round_calculates_monotonically_decreasing_remaining_seconds(): void
    {
        $category = Category::query()->create(['name' => 'Monotonic Cat']);
        $team = Team::query()->create(['name' => 'Team Mono', 'email' => 'mono@example.com', 'league_type' => 'male', 'max_players' => 5]);

        $startedAt = Carbon::now()->subSeconds(10);
        $round = DraftRound::query()->create([
            'league_type' => 'male',
            'category_id' => $category->id,
            'start_team_id' => $team->id,
            'current_team_id' => $team->id,
            'pick_order' => [$team->id],
            'picks_per_team' => 1,
            'turn_time_seconds' => 30,
            'current_pick_number' => 1,
            'total_picks_planned' => 1,
            'current_turn_started_at' => $startedAt,
            'status' => 'active',
            'is_paused' => false,
        ]);

        $event1 = new DraftTurnChanged($round);
        $payload1 = $event1->broadcastWith();
        $this->assertLessThanOrEqual(20, $payload1['remainingTurnSeconds']);
        $this->assertGreaterThanOrEqual(19, $payload1['remainingTurnSeconds']);

        // Advance simulated time by 5 seconds
        Carbon::setTestNow(Carbon::now()->addSeconds(5));

        $event2 = new DraftTurnChanged($round);
        $payload2 = $event2->broadcastWith();
        $this->assertLessThanOrEqual(15, $payload2['remainingTurnSeconds']);
        $this->assertLessThan($payload1['remainingTurnSeconds'], $payload2['remainingTurnSeconds']);

        Carbon::setTestNow();
    }

    public function test_blade_templates_render_static_x_data_to_prevent_morphdom_re_evaluation(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'admin_blade@example.com']);
        $admin->assignRole('admin');

        $team = Team::query()->create(['name' => 'Team Blade', 'email' => 'blade@example.com', 'league_type' => 'male', 'max_players' => 5]);

        $draftBoardHtml = Livewire::actingAs($admin)
            ->test(DraftBoard::class, ['leagueType' => 'male'])
            ->html();

        // Must use static x-data="ktplDraftTimerComponent()" to prevent Livewire morphdom evaluation jumps
        $this->assertStringContainsString('x-data="ktplDraftTimerComponent()"', $draftBoardHtml);
        $this->assertStringNotContainsString('x-data="ktplDraftTimerComponent({', $draftBoardHtml);

        $teamDashboardHtml = Livewire::actingAs($admin)
            ->test(TeamDraftDashboard::class, ['teamId' => $team->id])
            ->html();

        $this->assertStringContainsString('x-data="ktplDraftTimerComponent()"', $teamDashboardHtml);
        $this->assertStringNotContainsString('x-data="ktplDraftTimerComponent({', $teamDashboardHtml);
    }
}
