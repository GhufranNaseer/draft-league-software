<?php

namespace App\Livewire;

use App\Models\DraftPick;
use App\Models\DraftRound;
use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use App\Services\DraftService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class TeamDraftDashboard extends Component
{
    public int $teamId;

    public string $leagueType = '';

    public function mount(int $teamId): void
    {
        $this->teamId = $teamId;
        $this->leagueType = (string) (Team::find($teamId)?->league_type ?? 'male');
    }

    #[On('echo:draft.league.{leagueType},.draft.turn.changed')]
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

            Log::debug('[DRAFT-TIMER-DEBUG] TeamDraftDashboard::turnChanged broadcast handled.', [
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
                'turnKey' => $roundId.'-'.$pickNumber,
                'hasActiveRound' => ($event['status'] ?? '') === 'active',
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

    public function pickPlayer(int $participantId): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            $this->addError('pick', 'You must be logged in to pick a player.');

            return;
        }

        $team = Team::find($this->teamId);

        if (! $team) {
            $this->addError('pick', 'Team not found.');

            return;
        }

        $activeRound = DraftRound::query()
            ->where('status', 'active')
            ->where('league_type', $team->league_type)
            ->latest('id')
            ->first();

        if (! $activeRound) {
            $this->addError('pick', 'No active draft round.');

            return;
        }

        if ($activeRound->is_paused) {
            $this->addError('pick', 'Draft round is currently paused. Player picking is disabled.');

            return;
        }

        $participant = Participant::find($participantId);

        if (! $participant) {
            $this->addError('pick', 'Player not found.');

            return;
        }

        $actingTeamId = $user->isAdmin() ? null : $this->teamId;

        $result = app(DraftService::class)->pick($activeRound, $participant, $actingTeamId);

        if (isset($result['error'])) {
            $this->addError('pick', $result['error']);
        }
    }

    public function render(): View
    {
        $team = Team::with(['participants.category'])->find($this->teamId);

        if (! $team) {
            return view('livewire.team-draft-dashboard', [
                'team' => null,
                'leagueType' => $this->leagueType,
                'activeRound' => null,
                'isTeamTurn' => false,
                'isPaused' => false,
                'canPick' => false,
                'draftPoolParticipants' => collect(),
                'teamRoundPicksCount' => 0,
                'activeRoundRemainingSeconds' => 0,
                'draftActivity' => collect(),
            ]);
        }

        $activeRound = DraftRound::query()
            ->with(['category', 'currentTeam'])
            ->where('status', 'active')
            ->where('league_type', $team->league_type)
            ->latest('id')
            ->first();

        $leagueType = (string) $team->league_type;

        $isTeamTurn = $activeRound && (int) $activeRound->current_team_id === $this->teamId;
        $isPaused = (bool) ($activeRound && $activeRound->is_paused);

        $teamRoundPicksCount = 0;
        $canPick = false;
        $draftPoolParticipants = collect();
        $activeRoundRemainingSeconds = 0;

        if ($activeRound) {
            $draftService = app(DraftService::class);
            $eligibleCategoryIds = $draftService->getEligibleCategoryIds($activeRound);

            $teamRoundPicksCount = DraftPick::query()
                ->where('draft_round_id', $activeRound->id)
                ->where('team_id', $this->teamId)
                ->count();

            $canPick = $isTeamTurn
                && ! $isPaused
                && $teamRoundPicksCount < (int) $activeRound->picks_per_team
                && ! $draftService->isTurnExpired($activeRound);

            $draftPoolParticipants = Participant::query()
                ->with('category')
                ->where('status', 'approved')
                ->where('league_type', $team->league_type)
                ->whereNull('team_id')
                ->whereIn('category_id', $eligibleCategoryIds)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();

            if ($activeRound->is_paused) {
                $activeRoundRemainingSeconds = (int) ($activeRound->remaining_turn_seconds ?? $activeRound->turn_time_seconds);
            } elseif ($activeRound->current_turn_started_at) {
                $elapsed = abs(Carbon::now()->diffInSeconds($activeRound->current_turn_started_at));
                $activeRoundRemainingSeconds = max(0, (int) $activeRound->turn_time_seconds - $elapsed);
            } else {
                $activeRoundRemainingSeconds = (int) $activeRound->turn_time_seconds;
            }
        }

        $draftActivity = DraftPick::query()
            ->with(['team', 'participant.category', 'round.category'])
            ->whereHas('round', fn ($q) => $q->where('league_type', $team->league_type))
            ->latest('picked_at')
            ->limit(20)
            ->get();

        return view('livewire.team-draft-dashboard', compact(
            'team',
            'leagueType',
            'activeRound',
            'isTeamTurn',
            'isPaused',
            'canPick',
            'draftPoolParticipants',
            'teamRoundPicksCount',
            'activeRoundRemainingSeconds',
            'draftActivity'
        ));
    }
}
