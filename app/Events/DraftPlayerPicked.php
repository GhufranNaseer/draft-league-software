<?php

namespace App\Events;

use App\Models\DraftPick;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DraftPlayerPicked implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public int $pickId;

    public int $roundId;

    public int $roundNumber;

    public string $leagueType;

    public int $pickNumber;

    public ?int $teamId;

    public ?string $teamName;

    public ?string $teamLogoUrl;

    public array $player;

    public string $serverTime;

    public int $serverTimeMs;

    public int $stateVersion;

    public function __construct(DraftPick $pick)
    {
        $now = now();
        $this->serverTime = $now->toIso8601String();
        $this->serverTimeMs = (int) round(microtime(true) * 1000);
        $this->stateVersion = $this->serverTimeMs;

        $pick->loadMissing(['participant.category', 'team', 'round']);
        $participant = $pick->participant;
        $skills = collect($participant?->skill_categories ?? [])->filter()->values();
        $role = $skills->isNotEmpty() ? $skills->implode(' / ') : '—';

        $passportUrl = null;
        if ($participant?->passport_picture) {
            $passportUrl = asset('storage/'.ltrim($participant->passport_picture, '/'));
        }

        $teamLogoUrl = null;
        if ($pick->team?->logo) {
            $teamLogoUrl = asset('storage/'.ltrim($pick->team->logo, '/'));
        }

        $this->pickId = (int) $pick->id;
        $this->roundId = (int) $pick->draft_round_id;
        $this->roundNumber = (int) ($pick->round?->round_number ?: $pick->draft_round_id);
        $this->leagueType = (string) ($pick->round?->league_type ?? 'male');
        $this->pickNumber = (int) $pick->pick_number;
        $this->pickedAt = $pick->picked_at?->toIso8601String();
        $this->teamId = $pick->team_id ? (int) $pick->team_id : null;
        $this->teamName = $pick->team?->name ?? '—';
        $this->teamLogoUrl = $teamLogoUrl;

        $this->player = [
            'id' => (int) ($participant?->id ?? 0),
            'fullName' => $participant ? ($participant->full_name ?: trim($participant->first_name.' '.$participant->last_name)) : '—',
            'nickName' => $participant?->nick_name ?: '—',
            'category' => $participant?->category?->name ?: ($pick->round?->category?->name ?: '—'),
            'role' => $role,
            'city' => $participant?->city ?: '—',
            'passportPictureUrl' => $passportUrl,
        ];
    }

    public function broadcastOn(): array
    {
        $channel = 'draft.league.'.$this->leagueType;

        Log::debug('DraftPlayerPicked broadcasting.', [
            'channel' => $channel,
            'pickId' => $this->pickId,
            'roundId' => $this->roundId,
            'leagueType' => $this->leagueType,
            'pickNumber' => $this->pickNumber,
            'teamName' => $this->teamName,
            'player' => $this->player,
            'serverTimeMs' => $this->serverTimeMs,
            'stateVersion' => $this->stateVersion,
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
        return 'draft.player.picked';
    }

    public function broadcastWith(): array
    {
        return [
            'pickId' => $this->pickId,
            'roundId' => $this->roundId,
            'leagueType' => $this->leagueType,
            'pickNumber' => $this->pickNumber,
            'pickedAt' => $this->pickedAt,
            'teamId' => $this->teamId,
            'teamName' => $this->teamName,
            'teamLogoUrl' => $this->teamLogoUrl,
            'player' => $this->player,
            'serverTime' => $this->serverTime,
            'serverTimeMs' => $this->serverTimeMs,
            'stateVersion' => $this->stateVersion,
        ];
    }
}
