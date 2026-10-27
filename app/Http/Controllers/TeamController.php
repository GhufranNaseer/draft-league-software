<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\DraftPick;
use App\Models\DraftRound;
use App\Models\League;
use App\Models\LeagueRoundConfig;
use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use App\Services\DraftService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class TeamController extends Controller
{
    private const LEAGUE_TYPE_MALE = 'male';

    private const LEAGUE_TYPE_FEMALE = 'female';

    public function __construct(private readonly DraftService $draftService) {}

    private function availableLeagues()
    {
        return League::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function leagueValidationRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:100',
            Rule::exists('leagues', 'slug')->where(fn ($query) => $query->where('is_active', true)),
        ];
    }

    private function normalizeLeagueType(?string $leagueType): string
    {
        return $this->draftService->normalizeLeagueType($leagueType);
    }

    private function leagueLabel(string $leagueType): string
    {
        $normalizedLeague = $this->normalizeLeagueType($leagueType);

        return (string) (League::query()->where('slug', $normalizedLeague)->value('name') ?? Str::headline($normalizedLeague));
    }

    private function broadcastTurnChanged(DraftRound $round, ?string $message = null): void
    {
        $this->draftService->broadcastTurnChanged($round, $message);
    }

    /**
     * Team dashboard for team-role users.
     */
    public function teamDashboard()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Unauthorized. Team access required.');
        }

        $team = Team::query()
            ->with(['participants.category'])
            ->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])
            ->first();

        if (! $user->isAdmin() && ! $user->hasRole('team')) {
            if (! $team) {
                abort(403, 'Unauthorized. Team access required.');
            }

            // Self-heal missing role assignment for linked team accounts.
            try {
                $user->syncRoles(['team']);
            } catch (Throwable $e) {
                // Continue with access based on trusted email-to-team link.
            }
        }

        if (! $team) {
            abort(404, 'No team is linked to this account email.');
        }

        $teamLeagueType = $this->normalizeLeagueType((string) $team->league_type);

        $activeRound = DraftRound::query()
            ->with(['category', 'currentTeam'])
            ->where('status', 'active')
            ->where('league_type', $teamLeagueType)
            ->latest('id')
            ->first();

        $eligibleCategoryIds = $activeRound ? $this->getEligibleCategoryIds($activeRound) : [];

        $draftPoolParticipants = collect();
        $teamRoundPicksCount = 0;
        $remainingTurnSeconds = 0;

        if ($activeRound) {
            $draftPoolParticipants = Participant::query()
                ->with('category')
                ->where('status', 'approved')
                ->where('league_type', $teamLeagueType)
                ->whereNull('team_id')
                ->whereIn('category_id', $eligibleCategoryIds)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();

            $teamRoundPicksCount = DraftPick::query()
                ->where('draft_round_id', $activeRound->id)
                ->where('team_id', $team->id)
                ->count();

            if ($activeRound->is_paused) {
                $remainingTurnSeconds = (int) ($activeRound->remaining_turn_seconds ?? 0);
            } elseif ($activeRound->current_turn_started_at) {
                $elapsed = abs(Carbon::now()->diffInSeconds($activeRound->current_turn_started_at));
                $remainingTurnSeconds = max(0, (int) $activeRound->turn_time_seconds - $elapsed);
            }
        }

        $isTeamTurn = $activeRound && (int) $activeRound->current_team_id === (int) $team->id;
        $canPick = $activeRound
            && $isTeamTurn
            && $team->participants->count() < (int) $team->max_players
            && $teamRoundPicksCount < (int) $activeRound->picks_per_team;

        $draftActivityQuery = DraftPick::query()
            ->with(['team', 'participant.category', 'round.category'])
            ->whereHas('round', function ($query) use ($teamLeagueType) {
                $query->where('league_type', $teamLeagueType);
            })
            ->orderByDesc('picked_at')
            ->orderByDesc('id');

        if (! $user->isAdmin()) {
            $draftActivityQuery->where('team_id', $team->id);
        }

        $draftActivity = $draftActivityQuery
            ->limit(25)
            ->get();

        return view('team.dashboard', [
            'team' => $team,
            'participants' => $team->participants->where('league_type', $teamLeagueType)->values(),
            'teamLeagueType' => $teamLeagueType,
            'teamLeagueLabel' => $this->leagueLabel($teamLeagueType),
            'activeRound' => $activeRound,
            'draftPoolParticipants' => $draftPoolParticipants,
            'teamRoundPicksCount' => $teamRoundPicksCount,
            'isTeamTurn' => (bool) $isTeamTurn,
            'canPick' => (bool) $canPick,
            'remainingTurnSeconds' => $remainingTurnSeconds,
            'draftActivity' => $draftActivity,
        ]);
    }

    /**
     * Full draft activity module for authenticated users.
     */
    public function activities(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        $linkedTeamId = Team::query()
            ->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])
            ->value('id');

        $isTeamUser = ! $user->isAdmin() && $user->hasRole('team');

        $filters = $request->validate([
            'team_id' => 'nullable|integer|exists:teams,id',
            'round_id' => 'nullable|integer|exists:draft_rounds,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'search' => 'nullable|string|max:100',
            'export' => 'nullable|string|in:csv',
        ]);

        $activityQuery = DraftPick::query()
            ->with(['team', 'participant.category', 'round.category']);

        if ($isTeamUser) {
            if (! $linkedTeamId) {
                abort(403, 'No team account is linked to this user.');
            }

            // Hard security boundary: non-admin team users can only view their team activity.
            $activityQuery->where('team_id', (int) $linkedTeamId);
            $filters['team_id'] = (int) $linkedTeamId;
        }

        if (! empty($filters['team_id'])) {
            $activityQuery->where('team_id', (int) $filters['team_id']);
        }

        if (! empty($filters['round_id'])) {
            $activityQuery->where('draft_round_id', (int) $filters['round_id']);
        }

        if (! empty($filters['category_id'])) {
            $activityQuery->whereHas('round', function ($query) use ($filters) {
                $query->where('category_id', (int) $filters['category_id']);
            });
        }

        if (! empty($filters['from'])) {
            $activityQuery->whereDate('picked_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $activityQuery->whereDate('picked_at', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $activityQuery->where(function ($query) use ($search) {
                $query->whereHas('participant', function ($participantQuery) use ($search) {
                    $participantQuery
                        ->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                })->orWhereHas('team', function ($teamQuery) use ($search) {
                    $teamQuery->where('name', 'like', '%'.$search.'%');
                });
            });
        }

        if (($filters['export'] ?? null) === 'csv') {
            $rows = $activityQuery
                ->orderByDesc('picked_at')
                ->orderByDesc('id')
                ->get();

            $fileName = 'draft-activities-'.now()->format('Ymd-His').'.csv';

            return response()->streamDownload(function () use ($rows) {
                $stream = fopen('php://output', 'w');

                fputcsv($stream, [
                    'picked_at',
                    'round_id',
                    'pick_number',
                    'team',
                    'player_name',
                    'player_email',
                    'category',
                ]);

                foreach ($rows as $row) {
                    fputcsv($stream, [
                        optional($row->picked_at)->format('Y-m-d H:i:s') ?: '',
                        $row->round_number,
                        $row->pick_number,
                        $row->team?->name ?: '',
                        $row->participant?->full_name ?: '',
                        $row->participant?->email ?: '',
                        $row->round?->category?->name ?: ($row->participant?->category?->name ?: 'Uncategorized'),
                    ]);
                }

                fclose($stream);
            }, $fileName, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $activities = $activityQuery
            ->orderByDesc('picked_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $teams = $isTeamUser
            ? Team::query()->where('id', (int) $linkedTeamId)->orderBy('name')->get(['id', 'name'])
            : Team::query()->orderBy('name')->get(['id', 'name']);

        $rounds = DraftRound::query()
            ->with('category:id,name')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'category_id', 'status']);

        $categories = Category::query()->orderBy('name')->get(['id', 'name']);

        return view('activities.index', [
            'activities' => $activities,
            'teams' => $teams,
            'rounds' => $rounds,
            'categories' => $categories,
            'isTeamUser' => $isTeamUser,
            'filters' => [
                'team_id' => $filters['team_id'] ?? '',
                'round_id' => $filters['round_id'] ?? '',
                'category_id' => $filters['category_id'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'search' => $filters['search'] ?? '',
                'export' => '',
            ],
        ]);
    }

    /**
     * Teams page with draft module tabs.
     */
    public function index(Request $request)
    {
        $availableLeagues = $this->availableLeagues()->where('is_active', true)->values();
        $activeLeagueType = $this->normalizeLeagueType((string) $request->query('league', self::LEAGUE_TYPE_MALE));

        $teams = Team::query()
            ->where('league_type', $activeLeagueType)
            ->withCount(['participants' => function ($query) use ($activeLeagueType) {
                $query->where('league_type', $activeLeagueType);
            }])
            ->orderBy('name')
            ->get();

        $activeRound = DraftRound::query()
            ->with(['category', 'startTeam', 'currentTeam'])
            ->where('status', 'active')
            ->where('league_type', $activeLeagueType)
            ->latest('id')
            ->first();

        $activeRoundEligibleCategoryIds = $activeRound ? $this->getEligibleCategoryIds($activeRound) : [];

        $activeRoundTeamPickCounts = $activeRound
            ? DraftPick::query()
                ->selectRaw('team_id, COUNT(*) as picks_count')
                ->where('draft_round_id', $activeRound->id)
                ->groupBy('team_id')
                ->pluck('picks_count', 'team_id')
                ->map(fn ($value) => (int) $value)
                ->toArray()
            : [];

        $activeRoundProcessedTeamIds = [];

        if ($activeRound) {
            $roundPickOrder = collect($activeRound->pick_order)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            $consumedSlots = max(0, min(((int) $activeRound->current_pick_number) - 1, count($roundPickOrder)));
            $activeRoundProcessedTeamIds = array_values(array_unique(array_slice($roundPickOrder, 0, $consumedSlots)));
        }

        $activeRoundRemainingSeconds = 0;

        if ($activeRound) {
            if ($activeRound->is_paused) {
                $activeRoundRemainingSeconds = (int) ($activeRound->remaining_turn_seconds ?? 0);
            } elseif ($activeRound->current_turn_started_at) {
                $elapsed = abs(Carbon::now()->diffInSeconds($activeRound->current_turn_started_at));
                $activeRoundRemainingSeconds = max(0, (int) $activeRound->turn_time_seconds - $elapsed);
            }
        }

        $categories = Category::query()
            ->withCount([
                'participants' => function ($query) use ($activeLeagueType) {
                    $query->where('league_type', $activeLeagueType);
                },
            ])
            ->with([
                'participants' => function ($query) use ($activeLeagueType) {
                    $query->where('status', 'approved')
                        ->where('league_type', $activeLeagueType)
                        ->whereNull('team_id')
                        ->orderBy('first_name')
                        ->orderBy('last_name');
                },
            ])
            ->withCount([
                'participants as draftable_participants_count' => function ($query) use ($activeLeagueType) {
                    $query->where('status', 'approved')
                        ->where('league_type', $activeLeagueType)
                        ->whereNull('team_id');
                },
            ])
            ->orderBy('name')
            ->get();

        $uncategorizedDraftableParticipants = Participant::query()
            ->where('status', 'approved')
            ->where('league_type', $activeLeagueType)
            ->whereNull('team_id')
            ->whereNull('category_id')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $draftedParticipants = Participant::query()
            ->with(['team', 'category'])
            ->where('league_type', $activeLeagueType)
            ->whereNotNull('team_id')
            ->orderByDesc('drafted_at')
            ->get();

        $leagueRoundConfigs = LeagueRoundConfig::query()
            ->where('league_type', $activeLeagueType)
            ->orderBy('round_number')
            ->get();
        $completedRoundsCount = DraftRound::query()
            ->where('status', 'completed')
            ->where('league_type', $activeLeagueType)
            ->count();
        $nextLeagueRoundNumber = $completedRoundsCount + ($activeRound ? 1 : 0) + 1;
        $nextLeagueRoundConfig = $leagueRoundConfigs->firstWhere('round_number', $nextLeagueRoundNumber);
        $teamsById = $teams->keyBy('id');

        return view('admin.teams', [
            'teams' => $teams,
            'categories' => $categories,
            'uncategorizedDraftableParticipants' => $uncategorizedDraftableParticipants,
            'draftedParticipants' => $draftedParticipants,
            'activeRound' => $activeRound,
            'activeRoundEligibleCategoryIds' => $activeRoundEligibleCategoryIds,
            'activeRoundTeamPickCounts' => $activeRoundTeamPickCounts,
            'activeRoundProcessedTeamIds' => $activeRoundProcessedTeamIds,
            'activeRoundRemainingSeconds' => $activeRoundRemainingSeconds,
            'leagueRoundConfigs' => $leagueRoundConfigs,
            'completedRoundsCount' => $completedRoundsCount,
            'nextLeagueRoundNumber' => $nextLeagueRoundNumber,
            'nextLeagueRoundConfig' => $nextLeagueRoundConfig,
            'teamsById' => $teamsById,
            'availableLeagues' => $availableLeagues,
            'allLeagues' => $this->availableLeagues()->values(),
            'activeLeagueType' => $activeLeagueType,
            'activeLeagueLabel' => $this->leagueLabel($activeLeagueType),
            'activeTab' => $request->query('tab', 'teams'),
            'activeDraftCategory' => (string) $request->query('category', ($categories->first()?->id ? (string) $categories->first()->id : 'uncategorized')),
        ]);
    }

    /**
     * Start a new draft round using the next league round plan.
     * Pick order always comes from LeagueRoundConfig; picks_per_team is always 1.
     */
    public function startRound(Request $request)
    {
        $validated = $request->validate([
            'league_type' => $this->leagueValidationRules(),
            'category_id' => 'required|exists:categories,id',
            'higher_category_ids' => 'nullable|array',
            'higher_category_ids.*' => 'integer|exists:categories,id',
            'picks_per_team' => 'required|integer|in:1',
            'turn_time_seconds' => 'required|integer|in:30,60,120,150,180,240,300',
        ]);

        $leagueType = $this->normalizeLeagueType((string) $validated['league_type']);

        if (DraftRound::query()->where('status', 'active')->where('league_type', $leagueType)->exists()) {
            return back()->with('error', 'Finish or close the active '.$this->leagueLabel($leagueType).' draft round before starting a new one.');
        }

        $completedCount = DraftRound::query()->where('status', 'completed')->where('league_type', $leagueType)->count();
        $nextRoundNumber = $completedCount + 1;

        $leagueConfig = LeagueRoundConfig::query()
            ->where('round_number', $nextRoundNumber)
            ->where('league_type', $leagueType)
            ->first();

        if (! $leagueConfig) {
            return back()->with('error', 'No league plan found for round '.$nextRoundNumber.'. Configure the League Setup first.');
        }

        $activeTeamIds = Team::query()->where('league_type', $leagueType)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $pickOrder = collect($leagueConfig->team_pick_order)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $activeTeamIds, true))
            ->values()
            ->all();

        if (empty($pickOrder)) {
            return back()->with('error', 'No active teams in the league plan for round '.$nextRoundNumber.'. Update League Setup.');
        }

        $higherCategoryIds = collect($validated['higher_category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id !== (int) $validated['category_id'])
            ->unique()
            ->values()
            ->all();

        $picksPerTeam = 1;
        $totalPicksPlanned = count($pickOrder);

        $round = DraftRound::create([
            'league_type' => $leagueType,
            'round_number' => $nextRoundNumber,
            'category_id' => (int) $validated['category_id'],
            'start_team_id' => $pickOrder[0],
            'current_team_id' => $pickOrder[0],
            'pick_order' => $pickOrder,
            'higher_category_ids' => $higherCategoryIds,
            'picks_per_team' => $picksPerTeam,
            'turn_time_seconds' => (int) $validated['turn_time_seconds'],
            'current_pick_number' => 1,
            'total_picks_planned' => $totalPicksPlanned,
            'current_turn_started_at' => now(),
            'status' => 'active',
        ]);

        $this->broadcastTurnChanged(
            $round,
            'Round '.$nextRoundNumber.' started. '.($round->currentTeam?->name ?? 'The first team').' is now on the clock.'
        );

        return redirect()->route('admin.teams', [
            'tab' => 'draft',
            'league' => $leagueType,
            'category' => (string) $validated['category_id'],
        ])->with('success', 'Round '.$nextRoundNumber.' started. Pick order set from league plan.');
    }

    /**
     * Pick a player for the current team in an active round.
     */
    public function pickInRound(Request $request, DraftRound $round, Participant $participant)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        $actingTeamId = null;

        if (! $user->isAdmin()) {
            $actingTeamId = Team::query()
                ->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])
                ->value('id');
        }

        $result = $this->draftService->pick($round, $participant, $actingTeamId);

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['success']);
    }

    /**
     * Check and auto-skip turn when timer expires.
     */
    public function tickRound(DraftRound $round)
    {
        $result = $this->draftService->tick($round);

        return response()->json(array_merge($result, [
            'roundId' => (int) $round->id,
            'message' => $result['advanced']
                ? 'Turn expired and moved to next team.'
                : ($result['round_closed'] ? 'Round is already closed.' : 'Turn is still active.'),
        ]));
    }

    /**
     * Close an active draft round manually.
     */
    public function closeRound(DraftRound $round)
    {
        if ($round->status !== 'active') {
            return back()->with('error', 'This draft round is already closed.');
        }

        $round->update([
            'status' => 'completed',
            'completed_at' => now(),
            'current_turn_started_at' => null,
            'is_paused' => false,
            'remaining_turn_seconds' => null,
        ]);

        $this->broadcastTurnChanged($round, 'Draft round closed successfully.');

        return back()->with('success', 'Draft round closed successfully.');
    }

    /**
     * Pause an active draft round timer.
     */
    public function pauseRound(DraftRound $round)
    {
        if ($round->status !== 'active') {
            return back()->with('error', 'Only active draft rounds can be paused.');
        }

        $this->draftService->pause($round);

        return back()->with('success', 'Draft round timer paused successfully.');
    }

    /**
     * Resume a paused draft round timer.
     */
    public function resumeRound(DraftRound $round)
    {
        if ($round->status !== 'active') {
            return back()->with('error', 'Only active draft rounds can be resumed.');
        }

        $this->draftService->resume($round);

        return back()->with('success', 'Draft round timer resumed successfully.');
    }

    /**
     * Reset an active draft round timer.
     */
    public function resetRoundTimer(DraftRound $round)
    {
        if ($round->status !== 'active') {
            return back()->with('error', 'Only active draft rounds can have their timer reset.');
        }

        $this->draftService->resetTimer($round);

        return back()->with('success', 'Draft round timer reset successfully.');
    }

    /**
     * Create a team (players are not assigned here).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:teams,name',
            'short_code' => 'nullable|string|max:10|unique:teams,short_code',
            'captain_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:teams,email',
            'league_type' => $this->leagueValidationRules(),
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $validated['color'] = $validated['color'] ?? '#6c757d';
        $validated['max_players'] = LeagueRoundConfig::query()->where('league_type', $validated['league_type'])->count() ?: 16;

        $existingUser = User::query()->where('email', $validated['email'])->first();

        if ($existingUser && $existingUser->isAdmin()) {
            return back()->withInput()->with('error', 'This email belongs to an admin account. Use a different team email.');
        }

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('team-logos', 'public');
        }

        Team::create($validated);

        $accountResult = $this->ensureTeamUserAccount($validated['name'], $validated['email']);

        if (! empty($accountResult['blocked'])) {
            return back()->with('error', $accountResult['message']);
        }

        if ($accountResult['created']) {
            return back()
                ->with('success', 'Team created successfully. Team login user has been created.')
                ->with('account_credentials', [
                    'label' => 'Team Account',
                    'email' => $accountResult['email'],
                    'password' => $accountResult['password'],
                ]);
        }

        return back()->with('success', 'Team created successfully. Existing user was linked with team access.');
    }

    /**
     * Update team details (without direct player selection).
     */
    public function update(Request $request, Team $team)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:teams,name,'.$team->id,
            'short_code' => 'nullable|string|max:10|unique:teams,short_code,'.$team->id,
            'captain_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:teams,email,'.$team->id,
            'league_type' => $this->leagueValidationRules(),
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $validated['color'] = $validated['color'] ?? $team->color ?? '#6c757d';
        $validated['max_players'] = LeagueRoundConfig::query()->where('league_type', $validated['league_type'])->count() ?: $team->max_players;

        if ($request->hasFile('logo')) {
            if ($team->logo && Storage::disk('public')->exists($team->logo)) {
                Storage::disk('public')->delete($team->logo);
            }

            $validated['logo'] = $request->file('logo')->store('team-logos', 'public');
        }

        $team->update($validated);

        $accountResult = $this->ensureTeamUserAccount($validated['name'], $validated['email']);

        if (! empty($accountResult['blocked'])) {
            return back()->with('error', $accountResult['message']);
        }

        if ($accountResult['created']) {
            return back()
                ->with('success', 'Team updated successfully. Team login user has been created.')
                ->with('account_credentials', [
                    'label' => 'Team Account',
                    'email' => $accountResult['email'],
                    'password' => $accountResult['password'],
                ]);
        }

        return back()->with('success', 'Team updated successfully.');
    }

    /**
     * Delete team and release drafted players back to pool.
     */
    public function destroy(Team $team)
    {
        DB::transaction(function () use ($team) {
            Participant::where('team_id', $team->id)->update([
                'team_id' => null,
                'drafted_at' => null,
            ]);

            if ($team->logo && Storage::disk('public')->exists($team->logo)) {
                Storage::disk('public')->delete($team->logo);
            }

            if ($team->email) {
                $teamUser = User::where('email', $team->email)->first();
                if ($teamUser && ! $teamUser->isAdmin()) {
                    $teamUser->delete();
                }
            }

            $configs = LeagueRoundConfig::where('league_type', $team->league_type)->get();
            foreach ($configs as $config) {
                $order = collect($config->team_pick_order)
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id !== (int) $team->id)
                    ->values()
                    ->all();
                $config->update(['team_pick_order' => $order]);
            }

            $team->delete();
        });

        return back()->with('success', 'Team deleted and drafted players returned to the draft pool.');
    }

    /**
     * Draft an approved player to a team.
     */
    public function draftPlayer(Request $request, Team $team, Participant $participant)
    {
        if (DraftRound::query()->where('status', 'active')->where('league_type', $team->league_type)->exists()) {
            return back()->with('error', 'Use active round picking while a draft round is running.');
        }

        if ($this->normalizeLeagueType((string) $team->league_type) !== $this->normalizeLeagueType((string) $participant->league_type)) {
            return back()->with('error', 'Team and player must belong to the same league.');
        }

        if ($participant->status !== 'approved') {
            return back()->with('error', 'Only approved participants can be drafted.');
        }

        if ($participant->team_id !== null) {
            return back()->with('error', 'This player is already drafted by another team.');
        }

        if ($team->participants()->count() >= $team->max_players) {
            return back()->with('error', 'Team roster is full. Increase max players or draft to another team.');
        }

        $participant->update([
            'team_id' => $team->id,
            'drafted_at' => now(),
        ]);

        return back()->with('success', 'Player drafted successfully.');
    }

    /**
     * Release a drafted player back to draft pool.
     */
    public function releasePlayer(Team $team, Participant $participant)
    {
        if ((int) $participant->team_id !== (int) $team->id) {
            return back()->with('error', 'Player does not belong to this team.');
        }

        $participant->update([
            'team_id' => null,
            'drafted_at' => null,
        ]);

        return back()->with('success', 'Player released back to draft pool.');
    }

    /**
     * Rotate team ids so selected team starts the pick order.
     */
    private function buildPickOrder(array $teamIds, int $startingTeamId): array
    {
        $startIndex = array_search($startingTeamId, $teamIds, true);

        if ($startIndex === false) {
            return $teamIds;
        }

        return array_values(array_merge(
            array_slice($teamIds, $startIndex),
            array_slice($teamIds, 0, $startIndex)
        ));
    }

    /**
     * Eligible categories are the selected category plus optional higher categories.
     */
    private function getEligibleCategoryIds(DraftRound $round): array
    {
        return $this->draftService->getEligibleCategoryIds($round);
    }

    /**
     * Find next team in order that still has remaining picks in this round.
     */
    private function findNextTeamId(DraftRound $round): ?int
    {
        return $this->draftService->findNextTeamId($round);
    }

    /**
     * Ensure a team-role user exists for a team email.
     */
    private function ensureTeamUserAccount(string $teamName, string $email): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user && $user->isAdmin()) {
            return [
                'blocked' => true,
                'message' => 'This email belongs to an admin account. Use a different team email.',
                'created' => false,
                'email' => $email,
                'password' => null,
            ];
        }

        if ($user) {
            $user->update([
                'name' => $teamName,
                'created_by_admin' => true,
            ]);
            $user->syncRoles(['team']);

            return [
                'created' => false,
                'email' => $user->email,
                'password' => null,
            ];
        }

        $plainPassword = 'Team@'.Str::upper(Str::random(8)).random_int(10, 99);

        $user = User::create([
            'name' => $teamName,
            'email' => $email,
            'password' => $plainPassword,
            'is_admin' => false,
            'created_by_admin' => true,
        ]);

        $user->syncRoles(['team']);

        return [
            'created' => true,
            'email' => $user->email,
            'password' => $plainPassword,
        ];
    }

    /**
     * Whether active turn timer is already expired.
     */
    private function isTurnExpired(DraftRound $round): bool
    {
        return $this->draftService->isTurnExpired($round);
    }

    /**
     * Move turn to next eligible team or complete round if none remain.
     * When the round completes, the next league round is auto-started.
     */
    private function advanceTurnOrComplete(DraftRound $round): bool
    {
        return $this->draftService->advanceTurnOrComplete($round);
    }

    // -----------------------------------------------------------------------
    // League Setup
    // -----------------------------------------------------------------------

    /**
     * Auto-start the next league round after the current one completes.
     * Carries category and turn-time forward from the just-completed round.
     * Returns true if a new round was created.
     */
    private function autoStartNextRound(DraftRound $completedRound): bool
    {
        $leagueType = $this->normalizeLeagueType((string) $completedRound->league_type);

        // Count includes the round just marked 'completed'.
        $completedCount = DraftRound::query()->where('status', 'completed')->where('league_type', $leagueType)->count();
        $nextRoundNumber = $completedCount + 1;

        $leagueConfig = LeagueRoundConfig::query()
            ->where('round_number', $nextRoundNumber)
            ->where('league_type', $leagueType)
            ->first();

        if (! $leagueConfig) {
            return false;
        }

        $activeTeamIds = Team::query()->where('league_type', $leagueType)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $pickOrder = collect($leagueConfig->team_pick_order)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $activeTeamIds, true))
            ->values()
            ->all();

        if (empty($pickOrder)) {
            return false;
        }

        $nextRound = DraftRound::create([
            'league_type' => $leagueType,
            'round_number' => $nextRoundNumber,
            'category_id' => $completedRound->category_id,
            'start_team_id' => $pickOrder[0],
            'current_team_id' => $pickOrder[0],
            'pick_order' => $pickOrder,
            'higher_category_ids' => $completedRound->higher_category_ids,
            'picks_per_team' => $completedRound->picks_per_team,
            'turn_time_seconds' => $completedRound->turn_time_seconds,
            'current_pick_number' => 1,
            'total_picks_planned' => count($pickOrder) * (int) $completedRound->picks_per_team,
            'current_turn_started_at' => now(),
            'status' => 'active',
        ]);

        $this->broadcastTurnChanged(
            $nextRound,
            'Round '.$nextRoundNumber.' started automatically. '.($nextRound->currentTeam?->name ?? 'The next team').' is now on the clock.'
        );

        return true;
    }

    /**
     * Create a new active league that can be used for teams, participants, and draft rounds.
     */
    public function storeLeague(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:leagues,name',
            'slug' => ['required', 'string', 'max:100', 'alpha_dash:ascii', 'unique:leagues,slug'],
            'description' => 'nullable|string|max:1000',
        ]);

        $league = League::query()->create([
            'name' => trim((string) $validated['name']),
            'slug' => Str::lower(trim((string) $validated['slug'])),
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'sort_order' => ((int) League::query()->max('sort_order')) + 1,
        ]);

        return redirect()
            ->route('admin.teams', ['tab' => 'league-setup', 'league' => $league->slug])
            ->with('success', $league->name.' league created successfully.');
    }

    /**
     * Toggle the active status of a league.
     */
    public function toggleLeagueStatus(League $league)
    {
        $league->update(['is_active' => ! $league->is_active]);

        $statusText = $league->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->route('admin.teams', ['tab' => 'league-setup', 'league' => League::defaultSlug()])
            ->with('success', "League '{$league->name}' has been {$statusText} successfully.");
    }

    /**
     * Save the full league round plan.
     * max_players = total rounds (each round every team picks 1 player).
     * Round 1 order set by admin via drag-sort; subsequent rounds auto-rotate
     * so the first-pick team moves to last each round.
     */
    public function saveLeagueSetup(Request $request)
    {
        $validated = $request->validate([
            'league_type' => $this->leagueValidationRules(),
            'max_players' => 'required|integer|min:1|max:99',
            'round1_order' => 'required|array',
            'round1_order.*' => 'required|integer|exists:teams,id',
        ]);

        $leagueType = $this->normalizeLeagueType((string) $validated['league_type']);

        $teamIds = Team::query()
            ->where('league_type', $leagueType)
            ->orderBy('name')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        if (count($teamIds) === 0) {
            return back()->with('error', 'Create at least one '.$this->leagueLabel($leagueType).' team before setting up that league.');
        }

        $submittedOrder = collect($validated['round1_order'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $submittedSorted = collect($submittedOrder)->sort()->values()->all();

        if ($submittedSorted !== $teamIds) {
            return back()->withInput()->with('error', 'Round 1 order must include every team exactly once.');
        }

        $maxPlayers = (int) $validated['max_players'];
        $round1Order = $submittedOrder;

        DB::transaction(function () use ($leagueType, $maxPlayers, $round1Order) {
            $this->resetLeagueDraftProgress($leagueType);
            LeagueRoundConfig::query()->where('league_type', $leagueType)->delete();

            $currentOrder = $round1Order;

            for ($roundNum = 1; $roundNum <= $maxPlayers; $roundNum++) {
                LeagueRoundConfig::create([
                    'league_type' => $leagueType,
                    'round_number' => $roundNum,
                    'team_pick_order' => $currentOrder,
                    'is_manually_set' => $roundNum === 1,
                ]);

                // Rotation: first-pick team moves to last; 2nd becomes new first.
                $currentOrder = array_merge(
                    array_slice($currentOrder, 1),
                    [$currentOrder[0]]
                );
            }

            // Sync all teams' max_players to the configured value.
            Team::query()->where('league_type', $leagueType)->update(['max_players' => $maxPlayers]);
        });

        return redirect()
            ->route('admin.teams', ['tab' => 'league-setup', 'league' => $leagueType])
            ->with('success', $this->leagueLabel($leagueType)." league setup saved: {$maxPlayers} rounds / {$maxPlayers} max players per team.");
    }

    /**
     * Manually override the pick order for a single round.
     * Only the first-pick team changes; the rest keeps their relative order
     * from the existing saved order, rotated to put the chosen team first.
     */
    public function updateLeagueRound(Request $request, int $roundNumber)
    {
        $validated = $request->validate([
            'league_type' => $this->leagueValidationRules(),
            'first_team_id' => 'required|exists:teams,id',
        ]);

        $leagueType = $this->normalizeLeagueType((string) $validated['league_type']);
        $firstTeamId = (int) $validated['first_team_id'];

        $teamExistsInLeague = Team::query()
            ->where('id', $firstTeamId)
            ->where('league_type', $leagueType)
            ->exists();

        if (! $teamExistsInLeague) {
            return back()->with('error', 'The selected team does not belong to the '.$this->leagueLabel($leagueType).' league.');
        }

        $config = LeagueRoundConfig::query()
            ->where('round_number', $roundNumber)
            ->where('league_type', $leagueType)
            ->firstOrFail();

        $existingOrder = collect($config->team_pick_order)->map(fn ($id) => (int) $id)->all();
        $newOrder = $this->buildPickOrder($existingOrder, $firstTeamId);

        $config->update([
            'team_pick_order' => $newOrder,
            'is_manually_set' => true,
        ]);

        return redirect()
            ->route('admin.teams', ['tab' => 'league-setup', 'league' => $leagueType])
            ->with('success', "Round {$roundNumber} first-pick team updated.");
    }

    /**
     * Wipe the entire league round plan.
     */
    public function clearLeagueSetup(Request $request)
    {
        $validated = $request->validate([
            'league_type' => $this->leagueValidationRules(),
        ]);

        $leagueType = $this->normalizeLeagueType((string) $validated['league_type']);

        DB::transaction(function () use ($leagueType) {
            $this->resetLeagueDraftProgress($leagueType);
            LeagueRoundConfig::query()->where('league_type', $leagueType)->delete();
        });

        return redirect()
            ->route('admin.teams', ['tab' => 'league-setup', 'league' => $leagueType])
            ->with('success', $this->leagueLabel($leagueType).' league setup cleared.');
    }

    /**
     * Clear draft progress for a league so setup regeneration starts from round 1.
     */
    private function resetLeagueDraftProgress(string $leagueType): void
    {
        $roundIds = DraftRound::query()->where('league_type', $leagueType)->pluck('id');

        DraftPick::query()->whereIn('draft_round_id', $roundIds)->delete();
        DraftRound::query()->where('league_type', $leagueType)->delete();

        Participant::query()
            ->where('league_type', $leagueType)
            ->whereNotNull('team_id')
            ->update([
                'team_id' => null,
                'drafted_at' => null,
            ]);

        // Auto-increment resets removed to prevent MySQL implicit transaction commit
    }
}
