<?php

namespace App\Services;

use App\Events\DraftPlayerPicked;
use App\Events\DraftTurnChanged;
use App\Models\DraftPick;
use App\Models\DraftRound;
use App\Models\DraftSkip;
use App\Models\League;
use App\Models\Participant;
use App\Models\Team;
use Illuminate\Broadcasting\BroadcastException;
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

    public function broadcastTurnChanged(DraftRound $round, ?string $message = null, ?array $skippedPick = null): void
    {
        $freshRound = $round->fresh(['currentTeam']) ?? $round->loadMissing('currentTeam');

        try {
            event(new DraftTurnChanged($freshRound, $message, $skippedPick));
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
     * Get the authoritative latest skipped pick for an active round or league.
     * Single source of truth for all dashboards and screens.
     *
     * @return array{id: int, teamId: int, teamName: string, roundNumber: int, pickNumber: int, skippedAt: string|null, displayText: string}|null
     */
    public function getLatestSkip(string $leagueType, ?DraftRound $activeRound = null): ?array
    {
        $normalizedLeague = $this->normalizeLeagueType($leagueType);
        $leagueVariants = [$normalizedLeague];
        if ($normalizedLeague === 'male') {
            $leagueVariants[] = 'males';
        } elseif ($normalizedLeague === 'males') {
            $leagueVariants[] = 'male';
        } elseif ($normalizedLeague === 'female') {
            $leagueVariants[] = 'females';
        } elseif ($normalizedLeague === 'females') {
            $leagueVariants[] = 'female';
        }

        $skipRecord = null;
        if ($activeRound && $activeRound->id) {
            $skipRecord = $activeRound->skips()->with('team')->latest('id')->first();
        }

        if (! $skipRecord) {
            $skipRecord = DraftSkip::query()
                ->whereHas('round', fn ($q) => $q->whereIn('league_type', $leagueVariants))
                ->with('team')
                ->latest('id')
                ->first();
        }

        if (! $skipRecord) {
            return null;
        }

        $teamName = $skipRecord->team?->name ?? 'Unknown Team';
        $roundNumber = (int) $skipRecord->round_number;
        $pickNumber = (int) $skipRecord->pick_number;

        return [
            'id' => (int) $skipRecord->id,
            'teamId' => (int) $skipRecord->team_id,
            'teamName' => $teamName,
            'roundNumber' => $roundNumber,
            'pickNumber' => $pickNumber,
            'skippedAt' => $skipRecord->skipped_at?->toIso8601String(),
            'displayText' => "{$teamName} | Round {$roundNumber} | Pick {$pickNumber} SKIPPED",
        ];
    }

    /**
     * Handle turn expiry when countdown reaches zero without a player selection.
     * Stops the timer at 0, records a DraftSkip, sets is_waiting_for_admin to true,
     * and DOES NOT advance to the next team or start another countdown.
     *
     * @return array{id: int, teamId: int, teamName: string, roundNumber: int, pickNumber: int, skippedAt: string|null, displayText: string}|null
     */
    public function handleTurnExpiryWithoutPick(DraftRound $round): ?array
    {
        $broadcastMessage = null;
        $updatedRound = null;
        $skippedPickPayload = null;

        DB::transaction(function () use ($round, &$broadcastMessage, &$updatedRound, &$skippedPickPayload) {
            $lockedRound = DraftRound::query()->lockForUpdate()->findOrFail($round->id);

            if ($lockedRound->status !== 'active') {
                $updatedRound = $lockedRound;

                return;
            }

            if ($lockedRound->is_waiting_for_admin) {
                $updatedRound = $lockedRound->fresh(['currentTeam']);

                return;
            }

            if (! $this->isTurnExpired($lockedRound)) {
                $updatedRound = $lockedRound->fresh(['currentTeam']);

                return;
            }

            $roundNumber = (int) ($lockedRound->round_number ?: $lockedRound->id);
            $pickNumber = (int) $lockedRound->current_pick_number;
            $expiringTeamId = (int) $lockedRound->current_team_id;

            $skipRecord = DraftSkip::firstOrCreate(
                [
                    'draft_round_id' => $lockedRound->id,
                    'pick_number' => $pickNumber,
                ],
                [
                    'team_id' => $expiringTeamId,
                    'round_number' => $roundNumber,
                    'skipped_at' => now(),
                ]
            );

            $expiringTeam = Team::query()->find($expiringTeamId);
            $teamName = $expiringTeam?->name ?? 'Unknown Team';

            $skippedPickPayload = [
                'id' => (int) $skipRecord->id,
                'teamId' => $expiringTeamId,
                'teamName' => $teamName,
                'roundNumber' => $roundNumber,
                'pickNumber' => $pickNumber,
                'skippedAt' => $skipRecord->skipped_at?->toIso8601String() ?? now()->toIso8601String(),
                'displayText' => "{$teamName} | Round {$roundNumber} | Pick {$pickNumber} SKIPPED",
            ];

            // Stop timer at 0, keep current team identifiable, set waiting for admin
            $lockedRound->update([
                'is_paused' => true,
                'is_waiting_for_admin' => true,
                'remaining_turn_seconds' => 0,
                'current_turn_started_at' => null,
            ]);

            $updatedRound = $lockedRound->fresh(['currentTeam']);
            $broadcastMessage = "{$teamName} missed their turn. Round {$roundNumber} | Pick {$pickNumber} SKIPPED. Waiting for admin.";
        });

        if ($updatedRound && $broadcastMessage) {
            $this->broadcastTurnChanged($updatedRound, $broadcastMessage, $skippedPickPayload);
        }

        return $skippedPickPayload;
    }

    /**
     * Admin action to transition from a skipped turn to the next team's fresh countdown.
     * Advances to the next eligible team, resets countdown to the full configured duration,
     * leaves the timer stopped/paused, and clears the waiting-for-admin state.
     */
    public function showTimer(DraftRound $round): DraftRound
    {
        $broadcastMessage = null;
        $updatedRound = null;

        DB::transaction(function () use ($round, &$broadcastMessage, &$updatedRound) {
            $lockedRound = DraftRound::query()->lockForUpdate()->findOrFail($round->id);

            if ($lockedRound->status !== 'active') {
                $updatedRound = $lockedRound;

                return;
            }

            // Concurrency guard: if not waiting for admin, ignore to prevent duplicate click advancements
            if (! $lockedRound->is_waiting_for_admin) {
                $updatedRound = $lockedRound->fresh(['currentTeam']);

                return;
            }

            $nextTeamId = $this->findNextTeamId($lockedRound);

            if ($nextTeamId === null) {
                // Final pick was skipped and no more teams remain in pick order: complete round
                $lockedRound->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'is_waiting_for_admin' => false,
                    'is_paused' => false,
                    'current_turn_started_at' => null,
                    'remaining_turn_seconds' => null,
                ]);

                $updatedRound = $lockedRound->fresh(['currentTeam']);
                $broadcastMessage = 'All draft picks completed. Draft round is finished.';

                return;
            }

            // Advance to next eligible team/pick with fresh full duration, kept paused initially
            $lockedRound->update([
                'current_team_id' => $nextTeamId,
                'current_pick_number' => (int) $lockedRound->current_pick_number + 1,
                'current_turn_started_at' => null,
                'is_paused' => true,
                'is_waiting_for_admin' => false,
                'remaining_turn_seconds' => (int) $lockedRound->turn_time_seconds,
            ]);

            $updatedRound = $lockedRound->fresh(['currentTeam']);
            $nextTeamName = $updatedRound->currentTeam?->name ?? 'Next team';
            $broadcastMessage = "{$nextTeamName} is now on the clock (paused). Ready for admin to start timer.";
        });

        if ($updatedRound && $broadcastMessage) {
            $this->broadcastTurnChanged($updatedRound, $broadcastMessage);
        }

        return $updatedRound;
    }

    /**
     * Move turn to next eligible team or complete round if none remain.
     * Records a skip and completes round or advances.
     */
    public function advanceTurnOrComplete(DraftRound $round): bool
    {
        $this->handleTurnExpiryWithoutPick($round);
        $fresh = $round->fresh();
        if ($fresh && $fresh->is_waiting_for_admin) {
            $updated = $this->showTimer($fresh);

            return $updated->status === 'active';
        }

        return false;
    }

    /**
     * Check turn expiry and handle expiration if reached.
     *
     * @return array{advanced: bool, round_closed: bool, is_waiting_for_admin: bool, currentTeamId: int|null, currentTurnStartedAtTs: int|null, turnTimeSeconds: int}
     */
    public function tick(DraftRound $round): array
    {
        try {
            $current = DraftRound::query()->find($round->id);

            if (! $current || $current->status !== 'active') {
                return [
                    'advanced' => false,
                    'round_closed' => true,
                    'is_waiting_for_admin' => false,
                    'currentTeamId' => $current?->current_team_id ? (int) $current->current_team_id : null,
                    'currentTurnStartedAtTs' => $current?->current_turn_started_at?->timestamp,
                    'turnTimeSeconds' => (int) ($current?->turn_time_seconds ?? 60),
                ];
            }

            // If already waiting for admin, do not tick or advance
            if ($current->is_waiting_for_admin) {
                return [
                    'advanced' => false,
                    'round_closed' => false,
                    'is_waiting_for_admin' => true,
                    'currentTeamId' => $current->current_team_id ? (int) $current->current_team_id : null,
                    'currentTurnStartedAtTs' => null,
                    'turnTimeSeconds' => (int) $current->turn_time_seconds,
                ];
            }

            if ($current->is_paused) {
                return [
                    'advanced' => false,
                    'round_closed' => false,
                    'is_waiting_for_admin' => false,
                    'currentTeamId' => $current->current_team_id ? (int) $current->current_team_id : null,
                    'currentTurnStartedAtTs' => $current->current_turn_started_at?->timestamp,
                    'turnTimeSeconds' => (int) $current->turn_time_seconds,
                ];
            }

            if (! $current->current_turn_started_at) {
                $current->update(['current_turn_started_at' => now()]);
                $current->refresh();

                return [
                    'advanced' => false,
                    'round_closed' => false,
                    'is_waiting_for_admin' => false,
                    'currentTeamId' => $current->current_team_id ? (int) $current->current_team_id : null,
                    'currentTurnStartedAtTs' => $current->current_turn_started_at?->timestamp,
                    'turnTimeSeconds' => (int) $current->turn_time_seconds,
                ];
            }

            if (! $this->isTurnExpired($current)) {
                return [
                    'advanced' => false,
                    'round_closed' => false,
                    'is_waiting_for_admin' => false,
                    'currentTeamId' => $current->current_team_id ? (int) $current->current_team_id : null,
                    'currentTurnStartedAtTs' => $current->current_turn_started_at?->timestamp,
                    'turnTimeSeconds' => (int) $current->turn_time_seconds,
                ];
            }

            // Expiry without player selection: record skip, pause at 0, wait for admin
            $skippedPick = $this->handleTurnExpiryWithoutPick($current);

            return [
                'advanced' => false,
                'round_closed' => false,
                'is_waiting_for_admin' => true,
                'skipped_pick' => $skippedPick,
                'currentTeamId' => $current->current_team_id ? (int) $current->current_team_id : null,
                'currentTurnStartedAtTs' => null,
                'turnTimeSeconds' => (int) $current->turn_time_seconds,
            ];
        } catch (BroadcastException $exception) {
            Log::warning('Draft broadcast exception caught in DraftService::tick: '.$exception->getMessage(), [
                'round_id' => (int) $round->id,
                'error' => $exception->getMessage(),
            ]);

            $fresh = $round->fresh();

            return [
                'advanced' => false,
                'round_closed' => ($fresh?->status === 'completed'),
                'is_waiting_for_admin' => (bool) ($fresh?->is_waiting_for_admin ?? false),
                'currentTeamId' => $fresh?->current_team_id ? (int) $fresh->current_team_id : null,
                'currentTurnStartedAtTs' => $fresh?->current_turn_started_at?->timestamp,
                'turnTimeSeconds' => (int) ($fresh?->turn_time_seconds ?? 60),
            ];
        }
    }

    /**
     * Pause an active draft round timer.
     */
    public function pause(DraftRound $round): DraftRound
    {
        $updated = DB::transaction(function () use ($round) {
            $locked = DraftRound::query()->lockForUpdate()->findOrFail($round->id);

            if ($locked->status !== 'active' || $locked->is_paused || $locked->is_waiting_for_admin) {
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

        if ($updated->is_paused && ! $updated->is_waiting_for_admin) {
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

            if ($locked->status !== 'active' || ! $locked->is_paused || $locked->is_waiting_for_admin) {
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
     * Reset an active draft round turn timer to its full configured duration.
     */
    public function resetTimer(DraftRound $round): DraftRound
    {
        $updated = DB::transaction(function () use ($round) {
            $locked = DraftRound::query()->lockForUpdate()->findOrFail($round->id);

            if ($locked->status !== 'active' || $locked->is_waiting_for_admin) {
                return $locked;
            }

            if ($locked->is_paused) {
                $locked->update([
                    'remaining_turn_seconds' => (int) $locked->turn_time_seconds,
                    'current_turn_started_at' => null,
                ]);
            } else {
                $locked->update([
                    'remaining_turn_seconds' => null,
                    'current_turn_started_at' => now(),
                ]);
            }

            return $locked->fresh(['currentTeam']);
        });

        $this->broadcastTurnChanged($updated, 'Draft timer has been reset to '.((int) $updated->turn_time_seconds).' seconds.');

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

            if ($lockedRound->is_waiting_for_admin) {
                return ['error' => 'Current turn was skipped. Admin must show timer before making picks.'];
            }

            if ($lockedRound->is_paused) {
                return ['error' => 'Draft is currently paused. Player picking is suspended until resumed.'];
            }

            if ($this->isTurnExpired($lockedRound)) {
                $this->handleTurnExpiryWithoutPick($lockedRound);

                return ['error' => 'Turn time expired. Turn has been skipped. Admin must show timer to continue.'];
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
