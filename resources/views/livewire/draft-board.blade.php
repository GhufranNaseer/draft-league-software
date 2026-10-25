<div
    x-data="ktplDraftTimerComponent()"
    x-init="initTimer({
        remaining: {{ (int) $activeRoundRemainingSeconds }},
        total: {{ (int) ($activeRound->turn_time_seconds ?? 60) }},
        isPaused: {{ ($activeRound && $activeRound->is_paused) ? 'true' : 'false' }},
        turnKey: '{{ $activeRound ? ($activeRound->id . '-' . $activeRound->current_pick_number) : 'none' }}',
        hasActiveRound: {{ $activeRound ? 'true' : 'false' }}
    })"
    x-destroy="destroyTimer()"
>
    @if($activeRound && !$activeRound->is_paused)
        <div wire:poll.3s="pollActiveRound" class="d-none"></div>
    @endif

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-list-check"></i> Draft Rounds</h4>
            <p class="text-muted small mb-0">Each round every team picks one player in the order defined by the League Setup. Rounds auto-advance when all teams have picked.</p>
        </div>
        <a href="{{ route('admin.teams', ['tab' => 'categories', 'league' => $leagueType]) }}" class="btn btn-outline-secondary btn-sm">
            Manage Categories
        </a>
    </div>

    <div class="round-setup-card mb-4">
        <h5 class="mb-3"><i class="bi bi-gear"></i> Draft Round Setup</h5>
        @if($activeRound)
            <div class="alert alert-info mb-0">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                    <div>
                        <strong>Active Round:</strong>
                        {{ $activeRound->category?->name }}
                        <span class="mx-1">|</span>
                        Current Turn: <strong>{{ $activeRound->currentTeam?->name }}</strong>
                        <span class="mx-1">|</span>
                        Pick {{ $activeRound->current_pick_number }} / {{ collect($activeRound->pick_order)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->count() }}
                        <span class="mx-1">|</span>
                        Timer: <strong><span x-text="`${formatTime(remaining)} / ${formatTime(total)}`"></span></strong>
                        <span class="badge bg-warning text-dark ms-1" x-show="isPaused" style="{{ ($activeRound && $activeRound->is_paused) ? '' : 'display: none;' }}"><i class="bi bi-pause-fill"></i> PAUSED</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if($activeRound->is_paused)
                            <button class="btn btn-sm btn-success" wire:click="resumeRound({{ $activeRound->id }})" wire:loading.attr="disabled" type="button">
                                <i class="bi bi-play-fill"></i> <span wire:loading.remove wire:target="resumeRound">Resume</span><span wire:loading wire:target="resumeRound">Resuming...</span>
                            </button>
                        @else
                            <button class="btn btn-sm btn-warning text-dark" wire:click="pauseRound({{ $activeRound->id }})" wire:loading.attr="disabled" type="button">
                                <i class="bi bi-pause-fill"></i> <span wire:loading.remove wire:target="pauseRound">Pause</span><span wire:loading wire:target="pauseRound">Pausing...</span>
                            </button>
                        @endif
                        <form method="POST" action="{{ route('admin.draft.round.close', $activeRound->id) }}" onsubmit="return confirm('Close the active round now?');" class="d-inline mb-0">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger" type="submit">Close Round</button>
                        </form>
                    </div>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('admin.draft.round.start') }}">
                @csrf
                <input type="hidden" name="league_type" value="{{ $leagueType }}">
                @if(!$leagueRoundConfigs->count())
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle"></i>
                        Configure the <a href="{{ route('admin.teams', ['tab' => 'league-setup', 'league' => $leagueType]) }}">League Setup</a> first &mdash; it defines max players, number of rounds, and the pick order.
                    </div>
                @elseif($nextLeagueRoundNumber > $totalLeagueRounds)
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-trophy"></i>
                        All <strong>{{ $totalLeagueRounds }}</strong> league rounds have been completed. The draft is finished.
                    </div>
                @else
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="alert alert-info mb-0 py-2">
                                <strong>Round {{ $nextLeagueRoundNumber }} of {{ $totalLeagueRounds }}</strong> &mdash; Pick order:
                                @if($nextLeagueRoundConfig)
                                    <span class="ms-1 d-inline-flex flex-wrap gap-1">
                                    @foreach($nextLeagueRoundConfig->team_pick_order as $pos => $tId)
                                        @php $pt = $teamsById->get((int)$tId); $ptc = $pt?->color ?? '#6c757d'; @endphp
                                        <span class="badge" style="background:{{ $ptc }};color:#fff;">{{ $pos+1 }}. {{ $pt?->name ?? 'Unknown' }}</span>
                                    @endforeach
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Primary Category</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label">Allow Also From (Higher)</label>
                            <select name="higher_category_ids[]" class="form-select" multiple>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Optional. Hold Ctrl/Cmd to select multiple.</div>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">Picks Per Team</label>
                            <input type="hidden" name="picks_per_team" value="1">
                            <input type="text" class="form-control" value="1 Player (fixed)" readonly>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">Turn Time</label>
                            <select name="turn_time_seconds" class="form-select" required>
                                <option value="30">1/5 Minutes</option>
                                <option value="60">1 Minutes</option>
                                <option value="120">2 Minutes</option>
                                <option value="150" selected>2.5 Minutes</option>
                                <option value="180">3 Minutes</option>
                                <option value="240">4 Minutes</option>
                                <option value="300">5 Minutes</option>
                            </select>
                        </div>
                        <div class="col-lg-1 d-flex align-items-end">
                            <button class="btn btn-primary w-100" type="submit">Start Round {{ $nextLeagueRoundNumber }}</button>
                        </div>
                    </div>
                @endif
            </form>
        @endif
    </div>

    @if($activeRound)
        <div class="mb-4">
            <h6 class="mb-2">Round Progress By Team</h6>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Team</th>
                            <th>Picks This Round</th>
                            <th>Target</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($teams as $team)
                            @php
                                $teamPicks = $activeRoundTeamPickCounts[$team->id] ?? 0;
                                $isTurn = (int) $activeRound->current_team_id === (int) $team->id;
                                $turnProcessed = in_array((int) $team->id, array_map('intval', $activeRoundProcessedTeamIds ?? []), true);
                            @endphp
                            <tr>
                                <td>{{ $team->name }}</td>
                                <td>{{ $teamPicks }}</td>
                                <td>{{ $activeRound->picks_per_team }}</td>
                                <td>
                                    @if($isTurn)
                                        <span class="badge text-bg-primary">Current Turn</span>
                                    @elseif($teamPicks >= $activeRound->picks_per_team)
                                        <span class="badge text-bg-success">Completed</span>
                                    @elseif($turnProcessed)
                                        <span class="badge text-bg-warning">Turn Skipped</span>
                                    @else
                                        <span class="badge text-bg-secondary">Waiting</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <ul class="nav nav-pills draft-category-tabs mb-3" id="draftCategoryTabs" role="tablist">
        @foreach($categories as $category)
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link {{ $selectedCategoryKey === (string) $category->id ? 'active' : '' }}"
                    id="draft-category-{{ $category->id }}-tab"
                    type="button"
                    role="tab"
                    wire:click="selectCategory('{{ $category->id }}')"
                    data-category-key="{{ $category->id }}"
                >
                    {{ $category->name }}
                    <span class="category-count-badge">{{ $category->draftable_participants_count }}</span>
                </button>
            </li>
        @endforeach
        <li class="nav-item" role="presentation">
            <button
                class="nav-link {{ $selectedCategoryKey === 'uncategorized' ? 'active' : '' }}"
                id="draft-category-uncategorized-tab"
                type="button"
                role="tab"
                wire:click="selectCategory('uncategorized')"
                data-category-key="uncategorized"
            >
                Uncategorized
                <span class="category-count-badge">{{ $uncategorizedDraftableParticipants->count() }}</span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="draftCategoryTabContent">
        @foreach($categories as $category)
            <div class="tab-pane fade {{ $selectedCategoryKey === (string) $category->id ? 'show active' : '' }}" id="draft-category-{{ $category->id }}" role="tabpanel" tabindex="0">
                <div class="draft-round-header">
                    <div>
                        <h5 class="mb-1">{{ $category->name }} Round</h5>
                        <p class="text-muted small mb-0">Only approved undrafted players from this category are available.</p>
                    </div>
                    <span class="badge text-bg-light">{{ $category->draftable_participants_count }} available</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Player</th>
                                <th>Email</th>
                                <th>Skill Category</th>
                                <th>City</th>
                                <th>Draft To Team</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($category->participants as $participant)
                                <tr>
                                    <td>
                                        @if($participant->passport_picture)
                                            <img
                                                src="{{ asset('storage/' . ltrim($participant->passport_picture, '/')) }}"
                                                alt="{{ $participant->full_name }}"
                                                class="rounded"
                                                style="width: 44px; height: 44px; object-fit: cover;"
                                            >
                                        @else
                                            <span class="badge text-bg-secondary">No Photo</span>
                                        @endif
                                    </td>
                                    <td>{{ $participant->full_name }}</td>
                                    <td>{{ $participant->email }}</td>
                                    <td>
                                        @php $participantSkills = collect($participant->skill_categories ?? [])->filter()->values()->all(); @endphp
                                        <div><strong>{{ $participant->category?->name ?: 'Uncategorized' }}</strong></div>
                                        @if(count($participantSkills))
                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                @foreach($participantSkills as $skill)
                                                    <span class="badge text-bg-light border">{{ $skill }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <small class="text-muted">No specific skills</small>
                                        @endif
                                    </td>
                                    <td>{{ $participant->city }}</td>
                                    <td>
                                        @if($activeRound)
                                            @php
                                                $isEligible = in_array((int) $category->id, array_map('intval', $activeRoundEligibleCategoryIds), true);
                                                $currentTeam = $teams->firstWhere('id', $activeRound->current_team_id);
                                            @endphp

                                            @if($isEligible && $currentTeam)
                                                <form method="POST" action="{{ route('admin.draft.round.pick', [$activeRound->id, $participant->id]) }}">
                                                    @csrf
                                                    <button class="btn btn-sm btn-primary" type="submit">
                                                        Pick For {{ $currentTeam->name }}
                                                    </button>
                                                </form>
                                            @elseif(!$isEligible)
                                                <span class="badge text-bg-secondary">Not in round eligibility</span>
                                            @else
                                                <span class="badge text-bg-secondary">No current team</span>
                                            @endif
                                        @elseif($teams->count() > 0)
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Draft (Legacy)
                                                </button>
                                                <ul class="dropdown-menu">
                                                    @foreach($teams as $team)
                                                        <li>
                                                            <form method="POST" action="{{ route('admin.team.draft', [$team->id, $participant->id]) }}">
                                                                @csrf
                                                                <button class="dropdown-item" type="submit">
                                                                    {{ $team->name }} ({{ $team->participants_count }}/{{ $team->max_players }})
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @else
                                            <span class="badge text-bg-secondary">Create team first</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No available players in this category round.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div class="tab-pane fade {{ $selectedCategoryKey === 'uncategorized' ? 'show active' : '' }}" id="draft-category-uncategorized" role="tabpanel" tabindex="0">
            <div class="draft-round-header">
                <div>
                    <h5 class="mb-1">Uncategorized Round</h5>
                    <p class="text-muted small mb-0">Players here need no category or can be drafted from this fallback pool.</p>
                </div>
                <span class="badge text-bg-light">{{ $uncategorizedDraftableParticipants->count() }} available</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Player</th>
                            <th>Email</th>
                            <th>Skill Category</th>
                            <th>City</th>
                            <th>Draft To Team</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($uncategorizedDraftableParticipants as $participant)
                            <tr>
                                <td>
                                    @if($participant->passport_picture)
                                        <img
                                            src="{{ asset('storage/' . ltrim($participant->passport_picture, '/')) }}"
                                            alt="{{ $participant->full_name }}"
                                            class="rounded"
                                            style="width: 44px; height: 44px; object-fit: cover;"
                                        >
                                    @else
                                        <span class="badge text-bg-secondary">No Photo</span>
                                    @endif
                                </td>
                                <td>{{ $participant->full_name }}</td>
                                <td>{{ $participant->email }}</td>
                                <td>
                                    @php $uncatSkills = collect($participant->skill_categories ?? [])->filter()->values()->all(); @endphp
                                    <div><strong>{{ $participant->category?->name ?: 'Uncategorized' }}</strong></div>
                                    @if(count($uncatSkills))
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            @foreach($uncatSkills as $skill)
                                                <span class="badge text-bg-light border">{{ $skill }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <small class="text-muted">No specific skills</small>
                                    @endif
                                </td>
                                <td>{{ $participant->city }}</td>
                                <td>
                                    @if($activeRound)
                                        <span class="badge text-bg-secondary">Uncategorized is not part of active round rules</span>
                                    @elseif($teams->count() > 0)
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Draft
                                            </button>
                                            <ul class="dropdown-menu">
                                                @foreach($teams as $team)
                                                    <li>
                                                        <form method="POST" action="{{ route('admin.team.draft', [$team->id, $participant->id]) }}">
                                                            @csrf
                                                            <button class="dropdown-item" type="submit">
                                                                {{ $team->name }} ({{ $team->participants_count }}/{{ $team->max_players }})
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @else
                                        <span class="badge text-bg-secondary">Create team first</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No uncategorized players available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
