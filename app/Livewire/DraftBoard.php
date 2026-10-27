<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\DraftPick;
use App\Models\DraftRound;
use App\Models\LeagueRoundConfig;
use App\Models\Participant;
use App\Models\Team;
use App\Services\DraftService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DraftBoard extends Component
{
    public string $leagueType;

    public string $selectedCategoryKey = '';

    public function mount(string $leagueType): void
    {
        $this->leagueType = $leagueType;
    }

    public function selectCategory(string $categoryKey): void
    {
        $this->selectedCategoryKey = $categoryKey;
    }

    #[On('echo:draft.league.{leagueType},.draft.turn.changed')]
    #[On('echo:draft.league.{leagueType},draft.turn.changed')]
    public function turnChanged(array $event = []): void
    {
        if (! empty($event) && isset($event['roundId'])) {
            $roundId = (int) $event['roundId'];
            $pickNumber = (int) ($event['currentPickNumber'] ?? 0);
            $isPaused = (bool) ($event['isPaused'] ?? false);
            $total = (int) ($event['turnTimeSeconds'] ?? 60);
            $remaining = isset($event['remainingTurnSeconds']) && $event['remainingTurnSeconds'] !== null
                ? (int) $event['remainingTurnSeconds']
                : $total;
            $startedAt = $event['turnStartedAt'] ?? '';
            $startedAtMs = isset($event['turnStartedAtMs']) ? (int) $event['turnStartedAtMs'] : null;
            $serverTimeMs = isset($event['serverTimeMs']) ? (int) $event['serverTimeMs'] : (int) round(microtime(true) * 1000);
            $stateVersion = isset($event['stateVersion']) ? (int) $event['stateVersion'] : $serverTimeMs;
            $forceReset = ! empty($event['message']) && str_contains(strtolower($event['message']), 'reset');

            Log::debug('[DRAFT-TIMER-DEBUG] DraftBoard::turnChanged broadcast handled.', [
                'round_id' => $roundId,
                'pick_number' => $pickNumber,
                'remaining' => $remaining,
                'is_paused' => $isPaused,
                'total' => $total,
            ]);

            $this->dispatch('timer-sync', [
                'remaining' => $remaining,
                'isPaused' => $isPaused,
                'total' => $total,
                'turnKey' => $roundId.'-'.$pickNumber.($startedAt ? ('@'.$startedAt) : ''),
                'hasActiveRound' => ($event['status'] ?? '') === 'active',
                'forceReset' => $forceReset,
                'turnStartedAtMs' => $startedAtMs,
                'serverTimeMs' => $serverTimeMs,
                'stateVersion' => $stateVersion,
            ]);
        }
    }

    public function pauseRound(int $roundId): void
    {
        $round = DraftRound::find($roundId);
        if ($round) {
            $updated = app(DraftService::class)->pause($round);
            $remaining = (int) ($updated->remaining_turn_seconds ?? 0);
            $startedAt = $updated->current_turn_started_at?->toIso8601String() ?? '';
            $serverTimeMs = (int) round(microtime(true) * 1000);

            Log::debug('[DRAFT-TIMER-DEBUG] DraftBoard::pauseRound executed.', [
                'round_id' => $updated->id,
                'remaining' => $remaining,
                'is_paused' => true,
            ]);
            $this->dispatch('timer-sync', [
                'remaining' => $remaining,
                'isPaused' => true,
                'total' => (int) $updated->turn_time_seconds,
                'turnKey' => $updated->id.'-'.$updated->current_pick_number.($startedAt ? ('@'.$startedAt) : ''),
                'hasActiveRound' => true,
                'turnStartedAtMs' => null,
                'serverTimeMs' => $serverTimeMs,
                'stateVersion' => $serverTimeMs,
            ]);
        }
    }

    public function resumeRound(int $roundId): void
    {
        $round = DraftRound::find($roundId);
        if ($round) {
            $updated = app(DraftService::class)->resume($round);
            $elapsed = $updated->current_turn_started_at ? abs(Carbon::now()->diffInSeconds($updated->current_turn_started_at)) : 0;
            $rem = max(0, (int) $updated->turn_time_seconds - (int) $elapsed);
            $startedAt = $updated->current_turn_started_at?->toIso8601String() ?? '';
            $startedAtMs = $updated->current_turn_started_at ? (int) $updated->current_turn_started_at->getTimestampMs() : null;
            $serverTimeMs = (int) round(microtime(true) * 1000);

            Log::debug('[DRAFT-TIMER-DEBUG] DraftBoard::resumeRound executed.', [
                'round_id' => $updated->id,
                'remaining' => $rem,
                'is_paused' => false,
            ]);
            $this->dispatch('timer-sync', [
                'remaining' => (int) $rem,
                'isPaused' => false,
                'total' => (int) $updated->turn_time_seconds,
                'turnKey' => $updated->id.'-'.$updated->current_pick_number.($startedAt ? ('@'.$startedAt) : ''),
                'hasActiveRound' => true,
                'turnStartedAtMs' => $startedAtMs,
                'serverTimeMs' => $serverTimeMs,
                'stateVersion' => $serverTimeMs,
            ]);
        }
    }

    public function resetRoundTimer(int $roundId): void
    {
        $round = DraftRound::find($roundId);
        if ($round) {
            $updated = app(DraftService::class)->resetTimer($round);
            $total = (int) $updated->turn_time_seconds;
            $remaining = $updated->is_paused
                ? (int) ($updated->remaining_turn_seconds ?? $total)
                : $total;
            $startedAt = $updated->current_turn_started_at?->toIso8601String() ?? '';
            $startedAtMs = $updated->current_turn_started_at ? (int) $updated->current_turn_started_at->getTimestampMs() : null;
            $serverTimeMs = (int) round(microtime(true) * 1000);

            Log::debug('[DRAFT-TIMER-DEBUG] DraftBoard::resetRoundTimer executed.', [
                'round_id' => $updated->id,
                'remaining' => $remaining,
                'is_paused' => (bool) $updated->is_paused,
            ]);

            $this->dispatch('timer-sync', [
                'remaining' => (int) $remaining,
                'isPaused' => (bool) $updated->is_paused,
                'total' => $total,
                'turnKey' => $updated->id.'-'.$updated->current_pick_number.($startedAt ? ('@'.$startedAt) : ''),
                'hasActiveRound' => true,
                'forceReset' => true,
                'turnStartedAtMs' => $startedAtMs,
                'serverTimeMs' => $serverTimeMs,
                'stateVersion' => $serverTimeMs,
            ]);
        }
    }

    public function tickTurn(int $roundId): void
    {
        $round = DraftRound::find($roundId);

        if (! $round) {
            return;
        }

        app(DraftService::class)->tick($round);
    }

    public function pollActiveRound(): void
    {
        $activeRound = DraftRound::query()
            ->where('status', 'active')
            ->where('league_type', $this->leagueType)
            ->latest('id')
            ->first();

        if (! $activeRound || $activeRound->is_paused) {
            return;
        }

        app(DraftService::class)->tick($activeRound);
    }

    public function render(): View
    {
        $activeRound = DraftRound::query()
            ->with(['category', 'currentTeam'])
            ->where('status', 'active')
            ->where('league_type', $this->leagueType)
            ->latest('id')
            ->first();

        $teams = Team::query()
            ->where('league_type', $this->leagueType)
            ->withCount(['participants' => fn ($q) => $q->where('league_type', $this->leagueType)])
            ->orderBy('name')
            ->get();

        $teamsById = $teams->keyBy('id');

        $activeRoundEligibleCategoryIds = [];
        $activeRoundTeamPickCounts = [];
        $activeRoundProcessedTeamIds = [];
        $activeRoundRemainingSeconds = 0;

        if ($activeRound) {
            $draftService = app(DraftService::class);
            $activeRoundEligibleCategoryIds = $draftService->getEligibleCategoryIds($activeRound);

            $activeRoundTeamPickCounts = DraftPick::query()
                ->selectRaw('team_id, COUNT(*) as picks_count')
                ->where('draft_round_id', $activeRound->id)
                ->groupBy('team_id')
                ->pluck('picks_count', 'team_id')
                ->map(fn ($v) => (int) $v)
                ->toArray();

            $pickOrder = collect($activeRound->pick_order)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            $consumedSlots = max(0, min((int) $activeRound->current_pick_number - 1, count($pickOrder)));
            $activeRoundProcessedTeamIds = array_values(array_unique(array_slice($pickOrder, 0, $consumedSlots)));

            if ($activeRound->is_paused) {
                $activeRoundRemainingSeconds = (int) ($activeRound->remaining_turn_seconds ?? $activeRound->turn_time_seconds);
            } elseif ($activeRound->current_turn_started_at) {
                $elapsed = abs(Carbon::now()->diffInSeconds($activeRound->current_turn_started_at));
                $activeRoundRemainingSeconds = max(0, (int) $activeRound->turn_time_seconds - $elapsed);
            } else {
                $activeRoundRemainingSeconds = (int) $activeRound->turn_time_seconds;
            }
        }

        $categories = Category::query()
            ->withCount([
                'participants' => fn ($q) => $q->where('league_type', $this->leagueType),
                'participants as draftable_participants_count' => fn ($q) => $q
                    ->where('status', 'approved')
                    ->where('league_type', $this->leagueType)
                    ->whereNull('team_id'),
            ])
            ->with(['participants' => fn ($q) => $q
                ->where('status', 'approved')
                ->where('league_type', $this->leagueType)
                ->whereNull('team_id')
                ->orderBy('first_name')
                ->orderBy('last_name'),
            ])
            ->orderBy('name')
            ->get();

        $uncategorizedDraftableParticipants = Participant::query()
            ->where('status', 'approved')
            ->where('league_type', $this->leagueType)
            ->whereNull('team_id')
            ->whereNull('category_id')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $leagueRoundConfigs = LeagueRoundConfig::query()
            ->where('league_type', $this->leagueType)
            ->orderBy('round_number')
            ->get();

        $completedRoundsCount = DraftRound::query()
            ->where('status', 'completed')
            ->where('league_type', $this->leagueType)
            ->count();

        $nextLeagueRoundNumber = $completedRoundsCount + ($activeRound ? 1 : 0) + 1;
        $nextLeagueRoundConfig = $leagueRoundConfigs->firstWhere('round_number', $nextLeagueRoundNumber);
        $totalLeagueRounds = $leagueRoundConfigs->count();

        $availableCategoryKeys = $categories
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->push('uncategorized')
            ->all();

        if (! in_array($this->selectedCategoryKey, $availableCategoryKeys, true)) {
            $this->selectedCategoryKey = (string) ($categories->first()?->id ?? 'uncategorized');
        }

        return view('livewire.draft-board', [
            'activeRound' => $activeRound,
            'teams' => $teams,
            'teamsById' => $teamsById,
            'activeRoundEligibleCategoryIds' => $activeRoundEligibleCategoryIds,
            'activeRoundTeamPickCounts' => $activeRoundTeamPickCounts,
            'activeRoundProcessedTeamIds' => $activeRoundProcessedTeamIds,
            'activeRoundRemainingSeconds' => $activeRoundRemainingSeconds,
            'categories' => $categories,
            'uncategorizedDraftableParticipants' => $uncategorizedDraftableParticipants,
            'leagueRoundConfigs' => $leagueRoundConfigs,
            'completedRoundsCount' => $completedRoundsCount,
            'nextLeagueRoundNumber' => $nextLeagueRoundNumber,
            'nextLeagueRoundConfig' => $nextLeagueRoundConfig,
            'totalLeagueRounds' => $totalLeagueRounds,
            'selectedCategoryKey' => $this->selectedCategoryKey,
        ]);
    }
}
