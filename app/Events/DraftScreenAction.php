<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DraftScreenAction implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public string $leagueType;

    public string $action;

    public ?array $payload;

    public string $serverTime;

    public int $serverTimeMs;

    public function __construct(string $leagueType, string $action, ?array $payload = null)
    {
        $now = now();
        $this->leagueType = $leagueType;
        $this->action = $action;
        $this->payload = $payload;
        $this->serverTime = $now->toIso8601String();
        $this->serverTimeMs = (int) round(microtime(true) * 1000);
    }

    public function broadcastOn(): array
    {
        $channel = 'draft.league.'.$this->leagueType;
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
        return 'draft.screen.action';
    }

    public function broadcastWith(): array
    {
        return [
            'leagueType' => $this->leagueType,
            'action' => $this->action,
            'payload' => $this->payload,
            'serverTime' => $this->serverTime,
            'serverTimeMs' => $this->serverTimeMs,
        ];
    }
}
