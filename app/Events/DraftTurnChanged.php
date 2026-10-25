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

    public ?int $remainingTurnSeconds;

    public function __construct(DraftRound $round, ?string $message = null)
    {
        $round->loadMissing('currentTeam');

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
        $this->turnTimeSeconds = (int) $round->turn_time_seconds;
        $this->isPaused = (bool) $round->is_paused;
        if ($this->isPaused) {
            $this->remainingTurnSeconds = $round->remaining_turn_seconds !== null ? (int) $round->remaining_turn_seconds : (int) $round->turn_time_seconds;
        } elseif ($round->current_turn_started_at) {
            $elapsed = (int) floor(abs(now()->diffInSeconds($round->current_turn_started_at)));
            $this->remainingTurnSeconds = max(0, (int) $round->turn_time_seconds - $elapsed);
        } else {
            $this->remainingTurnSeconds = (int) $round->turn_time_seconds;
        }
    }

    public function broadcastOn(): array
    {
        $channel = 'draft.league.'.$this->leagueType;

        Log::debug('DraftTurnChanged broadcasting.', [
            'channel' => $channel,
            'roundId' => $this->roundId,
            'leagueType' => $this->leagueType,
            'currentTeamId' => $this->currentTeamId,
            'currentTeamName' => $this->currentTeamName,
            'currentPickNumber' => $this->currentPickNumber,
            'totalPicksPlanned' => $this->totalPicksPlanned,
            'status' => $this->status,
            'turnStartedAt' => $this->turnStartedAt,
            'turnTimeSeconds' => $this->turnTimeSeconds,
            'isPaused' => $this->isPaused,
            'remainingTurnSeconds' => $this->remainingTurnSeconds,
            'message' => $this->message,
        ]);

        return [new Channel($channel)];
    }

    public function broadcastAs(): string
    {
        return 'draft.turn.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'roundId' => $this->roundId,
            'leagueType' => $this->leagueType,
            'currentTeamId' => $this->currentTeamId,
            'currentTeamName' => $this->currentTeamName,
            'currentPickNumber' => $this->currentPickNumber,
            'totalPicksPlanned' => $this->totalPicksPlanned,
            'status' => $this->status,
            'message' => $this->message,
            'turnStartedAt' => $this->turnStartedAt,
            'turnTimeSeconds' => $this->turnTimeSeconds,
            'isPaused' => $this->isPaused,
            'remainingTurnSeconds' => $this->remainingTurnSeconds,
        ];
    }
}
