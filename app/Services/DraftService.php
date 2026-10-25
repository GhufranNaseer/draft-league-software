<?php

namespace App\Services;

use App\Events\DraftPlayerPicked;
use App\Events\DraftTurnChanged;
use App\Models\DraftPick;
use App\Models\DraftRound;
use App\Models\League;
use App\Models\Participant;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DraftService
{
    private const LEAGUE_TYPE_MALE = 'male';

    public function normalizeLeagueType(?string $leagueType): string
    {
        $normalizedLeague = Str::of((string) $leagueType)->trim()->lower()->value();

        $availableLeagueSlugs = League::query()
            ->where('is_active', true)
            ->pluck('slug')
            ->map(fn ($slug) => (string) $slug)
            ->filter()
            ->values();

        if ($availableLeagueSlugs->isEmpty()) {
            return self::LEAGUE_TYPE_MALE;
        }

        return $availableLeagueSlugs->contains($normalizedLeague)
            ? $normalizedLeague
            : (string) $availableLeagueSlugs->first();
    }

    public function isTurnExpired(DraftRound $round): bool
    {
        if ($round->is_paused || ! $round->current_turn_started_at) {
            return false;
        }

        return abs(Carbon::now()->diffInSeconds($round->current_turn_started_at)) >= (int) $round->turn_time_seconds;
    }

    public function getEligibleCategoryIds(DraftRound $round): array
    {
        return collect([$round->category_id])
            ->merge($round->higher_category_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function findNextTeamId(DraftRound $round): ?int
    {
        $order = collect($round->pick_order)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if (empty($order)) {
            return null;
        }

        $nextPickNumber = (int) $round->current_pick_number + 1;

        // One turn per listed team in pick order.
        if ($nextPickNumber > count($order)) {
            return null;
        }

        $nextIndex = $nextPickNumber - 1;

        return $order[$nextIndex] ?? null;
    }

    public function broadcastTurnChanged(DraftRound $round, ?string $message = null): void
    {
        $freshRound = $round->fresh(['currentTeam']) ?? $round->loadMissing('currentTeam');

        try {
            event(new DraftTurnChanged($freshRound, $message));
        } catch (\Throwable $exception) {
            Log::warning('Draft turn broadcast failed.', [
                'round_id' => (int) $freshRound->id,
                'league_type' => (string) $freshRound->league_type,
                'message' => $message,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function broadcastPlayerPicked(DraftPick $pick): void
    {
        $freshPick = $pick->fresh(['participant.category', 'team', 'round']) ?? $pick->loadMissing(['participant.category', 'team', 'round']);

        try {
            event(new DraftPlayerPicked($freshPick));
        } catch (\Throwable $exception) {
            Log::warning('Draft player picked broadcast failed.', [
                'pick_id' => (int) $freshPick->id,
                'round_id' => (int) $freshPick->draft_round_id,
                'participant_id' => (int) $freshPick->participant_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Move turn to next eligible team or complete round if none remain.
     * Returns true if the round advanced, false if it completed.
     */
    public function advanceTurnOrComplete(DraftRound $round): bool
    {
        $broadcastMessage = null;
        $updatedRound = null;

        $advanced = DB::transaction(function () use ($round, &$broadcastMessage, &$updatedRound) {
            $nextTeamId = $this->findNextTeamId($round);

            if ($nextTeamId === null) {
                $round->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'current_turn_started_at' => null,
                ]);

                $broadcastMessage = 'Turn expired and the round has been completed.';
                $updatedRound = $round->fresh(['currentTeam']);

                return false;
            }

            $round->update([
                'current_team_id' => $nextTeamId,
                'current_pick_number' => (int) $round->current_pick_number + 1,
                'current_turn_started_at' => now(),
                'is_paused' => false,
                'remaining_turn_seconds' => null,
            ]);

            $updatedRound = $round->fresh(['currentTeam']);
            $broadcastMessage = ($updatedRound->currentTeam?->name ?? 'The next team').' is now on the clock.';

            return true;
        });

        if ($updatedRound && $broadcastMessage) {
            $this->broadcastTurnChanged($updatedRound, $broadcastMessage);
        }

        return $advanced;
    }

    /**
     * Check turn expiry and advance if expired.
     *
     * @return array{advanced: bool, round_closed: bool, currentTeamId: int|null, currentTurnStartedAtTs: int|null, turnTimeSeconds: int}
     */
    public function tick(DraftRound $round): array
    {
        return DB::transaction(function () use ($round) {
            $locked = DraftRound::query()->lockForUpdate()->findOrFail($round->id);

            if ($locked->status !== 'active') {
                return [
                    'advanced' => false,
                    'round_closed' => true,
                    'currentTeamId' => $locked->current_team_id ? (int) $locked->current_team_id : null,
                    'currentTurnStartedAtTs' => $locked->current_turn_started_at?->timestamp,
                    'turnTimeSeconds' => (int) $locked->turn_time_seconds,
                ];
            }

            if ($locked->is_paused) {
                return [
                    'advanced' => false,
                    'round_closed' => false,
                    'currentTeamId' => $locked->current_team_id ? (int) $locked->current_team_id : null,
                    'currentTurnStartedAtTs' => $locked->current_turn_started_at?->timestamp,
                    'turnTimeSeconds' => (int) $locked->turn_time_seconds,
                ];
            }

            if (! $locked->current_turn_started_at) {
                $locked->update(['current_turn_started_at' => now()]);
                $locked->refresh();

                return [
                    'advanced' => false,
                    'round_closed' => false,
                    'currentTeamId' => $locked->current_team_id ? (int) $locked->current_team_id : null,
                    'currentTurnStartedAtTs' => $locked->current_turn_started_at?->timestamp,
                    'turnTimeSeconds' => (int) $locked->turn_time_seconds,
                ];
            }

            if (! $this->isTurnExpired($locked)) {
                return [
                    'advanced' => false,
                    'round_closed' => false,
                    'currentTeamId' => $locked->current_team_id ? (int) $locked->current_team_id : null,
                    'currentTurnStartedAtTs' => $locked->current_turn_started_at?->timestamp,
                    'turnTimeSeconds' => (int) $locked->turn_time_seconds,
                ];
            }

            $advanced = $this->advanceTurnOrComplete($locked);
            $locked->refresh();

            return [
                'advanced' => $advanced,
                'round_closed' => ! $advanced,
                'currentTeamId' => $locked->current_team_id ? (int) $locked->current_team_id : null,
                'currentTurnStartedAtTs' => $locked->current_turn_started_at?->timestamp,
                'turnTimeSeconds' => (int) $locked->turn_time_seconds,
            ];
        });
    }

    /**
     * Pause an active draft round timer.
     */
    public function pause(DraftRound $round): DraftRound
    {
        $updated = DB::transaction(function () use ($round) {
            $locked = DraftRound::query()->lockForUpdate()->findOrFail($round->id);

            if ($locked->status !== 'active' || $locked->is_paused) {
                return $locked;
            }

            $elapsedSeconds = $locked->current_turn_started_at
                ? (int) floor(abs(Carbon::now()->diffInSeconds($locked->current_turn_started_at)))
                : 0;

            $remaining = max(0, (int) $locked->turn_time_seconds - $elapsedSeconds);

            $locked->update([
                'is_paused' => true,
                'remaining_turn_seconds' => (int) $remaining,
            ]);

            return $locked->fresh(['currentTeam']);
        });

        if ($updated->is_paused) {
            $this->broadcastTurnChanged($updated, 'Draft timer has been paused by admin.');
        }

        return $updated;
    }

    /**
     * Resume a paused draft round timer.
     */
    public function resume(DraftRound $round): DraftRound
    {
        $updated = DB::transaction(function () use ($round) {
            $locked = DraftRound::query()->lockForUpdate()->findOrFail($round->id);

            if ($locked->status !== 'active' || ! $locked->is_paused) {
                return $locked;
            }

            $remaining = (int) ($locked->remaining_turn_seconds ?? (int) $locked->turn_time_seconds);
            $elapsed = max(0, (int) $locked->turn_time_seconds - $remaining);
            $resumedStartedAt = Carbon::now()->subSeconds($elapsed);

            $locked->update([
                'is_paused' => false,
                'remaining_turn_seconds' => null,
                'current_turn_started_at' => $resumedStartedAt,
            ]);

            return $locked->fresh(['currentTeam']);
        });

        if (! $updated->is_paused) {
            $this->broadcastTurnChanged($updated, 'Draft timer has resumed.');
        }

        return $updated;
    }

    /**
     * Perform a draft pick.
     *
     * @return array{success: string}|array{error: string}
     */
    public function pick(DraftRound $round, Participant $participant, ?int $actingTeamId = null): array
    {
        $broadcastMessage = null;
        $broadcastRound = null;
        $broadcastPick = null;

        $result = DB::transaction(function () use ($round, $participant, $actingTeamId, &$broadcastMessage, &$broadcastRound, &$broadcastPick) {
            $lockedRound = DraftRound::query()->lockForUpdate()->findOrFail($round->id);

            if ($lockedRound->status !== 'active') {
                return ['error' => 'This draft round is already closed.'];
            }

            if ($lockedRound->is_paused) {
                return ['error' => 'Draft is currently paused. Player picking is suspended until resumed.'];
            }

            if ($this->isTurnExpired($lockedRound)) {
                $this->advanceTurnOrComplete($lockedRound);

                return ['error' => 'Turn time expired. Turn has been auto-skipped to the next team.'];
            }

            $lockedParticipant = Participant::query()->lockForUpdate()->findOrFail($participant->id);

            if ($lockedParticipant->status !== 'approved') {
                return ['error' => 'Only approved participants can be drafted.'];
            }

            if ($lockedParticipant->team_id !== null) {
                return ['error' => 'This player is already drafted by another team.'];
            }

            if ($this->normalizeLeagueType((string) $lockedParticipant->league_type) !== $this->normalizeLeagueType((string) $lockedRound->league_type)) {
                return ['error' => 'This player belongs to a different league.'];
            }

            $eligibleCategoryIds = $this->getEligibleCategoryIds($lockedRound);

            if ($lockedParticipant->category_id === null || ! in_array((int) $lockedParticipant->category_id, $eligibleCategoryIds, true)) {
                return ['error' => 'This player is not eligible for the active draft round category rules.'];
            }

            $currentTeam = Team::query()->lockForUpdate()->findOrFail($lockedRound->current_team_id);

            if ($this->normalizeLeagueType((string) $currentTeam->league_type) !== $this->normalizeLeagueType((string) $lockedRound->league_type)) {
                return ['error' => 'Current round team league does not match the active round league.'];
            }

            if ($actingTeamId !== null && (int) $actingTeamId !== (int) $currentTeam->id) {
                return ['error' => 'It is not your team turn to pick right now.'];
            }

            if ($currentTeam->participants()->count() >= $currentTeam->max_players) {
                return ['error' => 'Current team roster is full. Update max players or close this round.'];
            }

            $currentTeamRoundPicks = DraftPick::query()
                ->where('draft_round_id', $lockedRound->id)
                ->where('team_id', $currentTeam->id)
                ->count();

            if ($currentTeamRoundPicks >= $lockedRound->picks_per_team) {
                return ['error' => 'Current team has already completed picks for this round.'];
            }

            $createdPick = DraftPick::create([
                'draft_round_id' => $lockedRound->id,
                'team_id' => $currentTeam->id,
                'participant_id' => $lockedParticipant->id,
                'pick_number' => (int) $lockedRound->current_pick_number,
                'picked_at' => now(),
            ]);

            $broadcastPick = $createdPick;

            $lockedParticipant->update([
                'team_id' => $currentTeam->id,
                'drafted_at' => now(),
            ]);

            $nextTeamId = $this->findNextTeamId($lockedRound);

            if ($nextTeamId === null) {
                $lockedRound->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'current_turn_started_at' => null,
                    'is_paused' => false,
                    'remaining_turn_seconds' => null,
                ]);

                $broadcastMessage = 'Round completed.';
                $broadcastRound = $lockedRound->fresh(['currentTeam']);

                return ['success' => 'Pick completed. Draft round is now finished.'];
            }

            $lockedRound->update([
                'current_team_id' => $nextTeamId,
                'current_pick_number' => (int) $lockedRound->current_pick_number + 1,
                'current_turn_started_at' => null,
                'is_paused' => true,
                'remaining_turn_seconds' => (int) $lockedRound->turn_time_seconds,
            ]);

            $broadcastRound = $lockedRound->fresh(['currentTeam']);
            $broadcastMessage = ($broadcastRound->currentTeam?->name ?? 'The next team').' is now on the clock (paused).';

            return ['success' => 'Pick completed successfully.'];
        });

        if ($broadcastPick) {
            $this->broadcastPlayerPicked($broadcastPick);
        }

        if ($broadcastRound && $broadcastMessage) {
            $this->broadcastTurnChanged($broadcastRound, $broadcastMessage);
        }

        return $result;
    }
}
