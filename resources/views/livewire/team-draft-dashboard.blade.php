<div
    x-data="ktplDraftTimerComponent()"
    x-init="initTimer({
        remaining: {{ (int) $activeRoundRemainingSeconds }},
        total: {{ (int) ($activeRound->turn_time_seconds ?? 60) }},
        isPaused: {{ ($activeRound && $activeRound->is_paused) ? 'true' : 'false' }},
        turnKey: '{{ $activeRound ? ($activeRound->id . '-' . $activeRound->current_pick_number . '@' . ($activeRound->current_turn_started_at?->toIso8601String() ?? '')) : 'none' }}',
        hasActiveRound: {{ $activeRound ? 'true' : 'false' }},
        turnStartedAtMs: {{ $activeRound && $activeRound->current_turn_started_at ? $activeRound->current_turn_started_at->getTimestampMs() : 'null' }},
        serverTimeMs: {{ (int) round(microtime(true) * 1000) }},
        stateVersion: {{ (int) round(microtime(true) * 1000) }}
    })"
    x-destroy="destroyTimer()"
>
    @if($activeRound && !$activeRound->is_paused)
        <div wire:poll.10s="pollActiveRound" class="d-none"></div>
    @else
        <div wire:poll.15s="pollActiveRound" class="d-none"></div>
    @endif

    @if($errors->has('pick'))
        <div class="alert alert-danger py-2 mb-3">{{ $errors->first('pick') }}</div>
    @endif

    <h5 class="mb-3">Active Draft Round</h5>

    @if($activeRound)
        <p class="mb-1"><strong>Category:</strong> {{ $activeRound->category?->name ?: 'N/A' }}</p>
        <p class="mb-1"><strong>Current Team:</strong> {{ $activeRound->currentTeam?->name ?: 'N/A' }}</p>
        <p class="mb-1"><strong>Your Picks This Round:</strong> {{ $teamRoundPicksCount }} / {{ $activeRound->picks_per_team }}</p>
        <p class="mb-3">
            <strong>Time Remaining:</strong>
            <span wire:ignore><span x-text="`${formatTime(remaining)} / ${formatTime(total)}`"></span></span>
            <span class="badge bg-warning text-dark ms-1" x-show="isPaused" style="{{ ($activeRound && $activeRound->is_paused) ? '' : 'display: none;' }}"><i class="bi bi-pause-fill"></i> PAUSED</span>
        </p>

        @if($activeRound->is_paused)
            <div class="alert alert-warning py-2 mb-3">
                <i class="bi bi-pause-circle-fill me-1"></i> <strong>Draft Paused:</strong> Player picking is suspended until resumed by admin.
            </div>
        @elseif($isTeamTurn)
            <div class="alert alert-info py-2 mb-3">It is your turn to pick.</div>
        @else
            <div class="alert alert-secondary py-2 mb-3">Waiting for current team to finish their pick.</div>
        @endif

        @if($draftPoolParticipants->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Player</th>
                            <th>Email</th>
                            <th>Skill Category</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($draftPoolParticipants as $player)
                            <tr>
                                <td>
                                    @if($player->passport_picture)
                                        <img
                                            src="{{ asset('storage/' . ltrim($player->passport_picture, '/')) }}"
                                            alt="{{ $player->full_name }}"
                                            class="rounded"
                                            style="width: 48px; height: 48px; object-fit: cover;"
                                        >
                                    @else
                                        <span class="badge text-bg-secondary">No Photo</span>
                                    @endif
                                </td>
                                <td>{{ $player->full_name }}</td>
                                <td>{{ $player->email }}</td>
                                <td>
                                    @php $playerSkills = collect($player->skill_categories ?? [])->filter()->values()->all(); @endphp
                                    <div><strong>{{ $player->category?->name ?: 'Uncategorized' }}</strong></div>
                                    @if(count($playerSkills))
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                             @foreach($playerSkills as $skill)
                                                <span class="badge text-bg-light border">{{ $skill }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <small class="text-muted">No specific skills</small>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($activeRound->is_paused)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-secondary text-white-50"
                                            style="cursor: not-allowed; opacity: 0.65;"
                                            disabled
                                            title="Draft is currently paused"
                                        >
                                            <i class="bi bi-pause-fill"></i> Draft Paused
                                        </button>
                                    @elseif($canPick)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-primary"
                                            :disabled="isPaused"
                                            :class="isPaused ? 'btn-secondary text-white-50' : 'btn-primary'"
                                            :style="isPaused ? 'cursor: not-allowed; opacity: 0.65;' : ''"
                                            wire:click="pickPlayer({{ $player->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="pickPlayer({{ $player->id }})"
                                        >
                                            <span wire:loading.remove wire:target="pickPlayer({{ $player->id }})" x-text="isPaused ? 'Draft Paused' : 'Pick Player'">Pick Player</span>
                                            <span wire:loading wire:target="pickPlayer({{ $player->id }})">Picking...</span>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Pick Player</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No eligible players are currently available in this active round.</p>
        @endif
    @else
        <p class="text-muted mb-0">No active draft round right now.</p>
    @endif

    @if($draftActivity->count() > 0)
        <hr class="my-4">
        <h5 class="mb-3">Recent Draft Activity</h5>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Round</th>
                        <th>Pick #</th>
                        <th>Team</th>
                        <th>Player</th>
                        <th>Image</th>
                        <th>Skill Category</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($draftActivity as $activity)
                        <tr>
                            <td>{{ optional($activity->picked_at)->format('M d, Y H:i') ?: '-' }}</td>
                            <td>#{{ $activity->round_number }}</td>
                            <td>{{ $activity->pick_number }}</td>
                            <td>{{ $activity->team?->name ?: 'N/A' }}</td>
                            <td>{{ $activity->participant?->full_name ?: 'N/A' }}</td>
                            <td>
                                @if($activity->participant?->passport_picture)
                                    <img src="{{ asset('storage/' . ltrim($activity->participant->passport_picture, '/')) }}" alt="Player Image" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                                @else
                                    <div class="bg-secondary text-white d-flex align-items-center justify-content-center rounded" style="width: 50px; height: 50px;">
                                        <i class="bi bi-person" style="font-size: 1rem;"></i>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @php
                                    $activityMainCategory = $activity->round?->category?->name ?: ($activity->participant?->category?->name ?: 'Uncategorized');
                                    $activitySkills = collect($activity->participant?->skill_categories ?? [])->filter()->values()->all();
                                @endphp
                                <div><strong>{{ $activityMainCategory }}</strong></div>
                                @if(count($activitySkills))
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        @foreach($activitySkills as $skill)
                                            <span class="badge text-bg-light border">{{ $skill }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <small class="text-muted">No specific skills</small>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
