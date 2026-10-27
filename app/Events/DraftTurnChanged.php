<?php

namespace App\Events;

use App\Models\DraftRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DraftTurnChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public int $roundId;

    public int $roundNumber;

    public string $leagueType;

    public ?int $currentTeamId;

    public ?string $currentTeamName;

    public int $currentPickNumber;

    public int $totalPicksPlanned;

    public string $status;

    public ?string $message;

    public ?string $turnStartedAt;

    public int $turnTimeSeconds;

    public bool $isPaused;

    public bool $isRunningTurn;

    public ?int $remainingTurnSeconds;

    public ?int $turnStartedAtMs;

    public string $serverTime;

    public int $serverTimeMs;

    public int $stateVersion;

    public function __construct(DraftRound $round, ?string $message = null)
    {
        $round->loadMissing('currentTeam');

        $now = now();
        $this->serverTime = $now->toIso8601String();
        $this->serverTimeMs = (int) round(microtime(true) * 1000);
        $this->stateVersion = $this->serverTimeMs;

        $this->roundId = (int) $round->id;
        $this->roundNumber = (int) ($round->round_number ?: $round->id);
        $this->leagueType = (string) $round->league_type;
        $this->currentTeamId = $round->current_team_id ? (int) $round->current_team_id : null;
        $this->currentTeamName = $round->currentTeam?->name;
        $this->currentPickNumber = (int) $round->current_pick_number;
        $this->totalPicksPlanned = (int) $round->total_picks_planned;
        $this->status = (string) $round->status;
        $this->message = $message;
        $this->turnStartedAt = $round->current_turn_started_at?->toIso8601String();
        $this->turnStartedAtMs = $round->current_turn_started_at ? (int) $round->current_turn_started_at->getTimestampMs() : null;
        $this->turnTimeSeconds = (int) $round->turn_time_seconds;
        $this->isPaused = (bool) $round->is_paused;
        if ($this->isPaused) {
            $this->remainingTurnSeconds = $round->remaining_turn_seconds !== null ? (int) $round->remaining_turn_seconds : (int) $round->turn_time_seconds;
            $this->isRunningTurn = false;
        } elseif ($round->current_turn_started_at) {
            $elapsed = (int) floor(abs(now()->diffInSeconds($round->current_turn_started_at)));
            $this->remainingTurnSeconds = max(0, (int) $round->turn_time_seconds - $elapsed);
            $this->isRunningTurn = ($this->status === 'active' && $this->remainingTurnSeconds > 0);
        } else {
            $this->remainingTurnSeconds = 0;
            $this->isRunningTurn = false;
        }
    }

    public function broadcastOn(): array
    {
        $channel = 'draft.league.'.$this->leagueType;

        Log::debug('DraftTurnChanged broadcasting.', [
            'channel' => $channel,
            'roundId' => $this->roundId,
            'roundNumber' => $this->roundNumber,
            'leagueType' => $this->leagueType,
            'currentTeamId' => $this->currentTeamId,
            'currentTeamName' => $this->currentTeamName,
            'currentPickNumber' => $this->currentPickNumber,
            'totalPicksPlanned' => $this->totalPicksPlanned,
            'status' => $this->status,
            'turnStartedAt' => $this->turnStartedAt,
            'turnStartedAtMs' => $this->turnStartedAtMs,
            'turnTimeSeconds' => $this->turnTimeSeconds,
            'isPaused' => $this->isPaused,
            'isRunningTurn' => $this->isRunningTurn,
            'remainingTurnSeconds' => $this->remainingTurnSeconds,
            'serverTimeMs' => $this->serverTimeMs,
            'stateVersion' => $this->stateVersion,
            'message' => $this->message,
        ]);

        $channels = [new Channel($channel)];
        if ($this->leagueType === 'male') {
            $channels[] = new Channel('draft.league.males');
        } elseif ($this->leagueType === 'males') {
            $channels[] = new Channel('draft.league.male');
        } elseif ($this->leagueType === 'female') {
            $channels[] = new Channel('draft.league.females');
        } elseif ($this->leagueType === 'females') {
            $channels[] = new Channel('draft.league.female');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'draft.turn.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'roundId' => $this->roundId,
            'roundNumber' => $this->roundNumber,
            'leagueType' => $this->leagueType,
            'currentTeamId' => $this->currentTeamId,
            'currentTeamName' => $this->currentTeamName,
            'currentPickNumber' => $this->currentPickNumber,
            'totalPicksPlanned' => $this->totalPicksPlanned,
            'status' => $this->status,
            'message' => $this->message,
            'turnStartedAt' => $this->turnStartedAt,
            'turnStartedAtMs' => $this->turnStartedAtMs,
            'turnTimeSeconds' => $this->turnTimeSeconds,
            'isPaused' => $this->isPaused,
            'isRunningTurn' => $this->isRunningTurn,
            'remainingTurnSeconds' => $this->remainingTurnSeconds,
            'remainingSeconds' => $this->remainingTurnSeconds,
            'serverTime' => $this->serverTime,
            'serverTimeMs' => $this->serverTimeMs,
            'stateVersion' => $this->stateVersion,
        ];
    }
}
