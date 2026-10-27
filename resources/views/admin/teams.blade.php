<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
    <meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.options.host') }}">
    <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.options.port') }}">
    <meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.options.scheme') }}">
    <script>
        window.__REVERB_KEY__ = "{{ config('broadcasting.connections.reverb.key') }}";
    </script>
    <title>Teams & Draft - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-teams.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/navbar.css') }}">
    @vite(['resources/js/app.js'])
    @livewireStyles
</head>
<body>
    @include('partials.portal-navbar')

    <div class="container-fluid main-container" id="teamsPage" data-active-tab="{{ $activeTab }}" data-active-draft-category="{{ $activeDraftCategory }}" data-active-league="{{ $activeLeagueType }}">
        <div class="page-header">
            <h1><i class="bi bi-trophy"></i> Team Module</h1>
            <p>Teams are managed here and players can only be assigned through drafting.</p>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                <span class="badge text-bg-dark">Active League: {{ $activeLeagueLabel }}</span>
                @foreach(($availableLeagues ?? collect()) as $league)
                    <a href="{{ route('admin.teams', ['tab' => $activeTab, 'category' => $activeDraftCategory, 'league' => $league->slug]) }}"
                       class="btn btn-sm {{ $activeLeagueType === $league->slug ? 'btn-primary' : 'btn-outline-primary' }}">
                        {{ $league->name }}
                    </a>
                @endforeach
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if(session('account_credentials'))
            @php
                $credentials = session('account_credentials');
            @endphp
            <div class="alert alert-warning">
                <strong>{{ $credentials['label'] ?? 'New Account' }} Credentials:</strong>
                <div>Email: <code>{{ $credentials['email'] ?? '' }}</code></div>
                <div>Password: <code>{{ $credentials['password'] ?? '' }}</code></div>
                <small class="text-muted">Share these once and ask user to change password after first login.</small>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <strong>Please fix the following:</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card section-card mb-4">
            <div class="card-body">
                <ul class="nav nav-tabs" id="teamTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="teams-tab" data-bs-toggle="tab" data-bs-target="#teams-tab-pane" type="button" role="tab">
                            <i class="bi bi-shield"></i> Teams
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="draft-tab" data-bs-toggle="tab" data-bs-target="#draft-tab-pane" type="button" role="tab">
                            <i class="bi bi-list-check"></i> Draft Pool
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="drafted-tab" data-bs-toggle="tab" data-bs-target="#drafted-tab-pane" type="button" role="tab">
                            <i class="bi bi-collection"></i> Drafted Players
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories-tab-pane" type="button" role="tab">
                            <i class="bi bi-tags"></i> Categories
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="league-setup-tab" data-bs-toggle="tab" data-bs-target="#league-setup-tab-pane" type="button" role="tab">
                            <i class="bi bi-calendar3"></i> League Setup
                            @if($leagueRoundConfigs->isNotEmpty())
                                <span class="badge text-bg-primary ms-1">{{ $leagueRoundConfigs->count() }}</span>
                            @endif
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-4" id="teamTabsContent">
                    <div class="tab-pane fade show active" id="teams-tab-pane" role="tabpanel" tabindex="0">
                        <div class="row g-4">
                            <div class="col-lg-4">
                                <div class="sub-card">
                                    <h4><i class="bi bi-plus-circle"></i> Create Team</h4>
                                    <form method="POST" action="{{ route('admin.team.create') }}" enctype="multipart/form-data">
                                        @csrf
                                        <input type="hidden" name="league_type" value="{{ $activeLeagueType }}">
                                        <div class="mb-3">
                                            <label class="form-label">Team Name</label>
                                            <input class="form-control" name="name" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Short Code</label>
                                            <input class="form-control" name="short_code" maxlength="10" placeholder="Optional">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Captain Name</label>
                                            <input class="form-control" name="captain_name" placeholder="Optional">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Team Login Email</label>
                                            <input type="email" class="form-control" name="email" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Team Logo</label>
                                            <input type="file" class="form-control" name="logo" accept="image/png,image/jpeg,image/jpg,image/webp">
                                            <div class="form-text">Optional. PNG, JPG, or WEBP up to 2MB.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Team Color</label>
                                            <div class="d-flex align-items-center gap-2">
                                                <input type="color" class="form-control form-control-color" name="color" value="#6c757d" title="Pick team color">
                                                <span class="form-text mb-0">Used in pick-order badges and round tables.</span>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary w-100" type="submit">Create Team</button>
                                    </form>
                                </div>
                            </div>

                            <div class="col-lg-8">
                                <div class="sub-card">
                                    <h4><i class="bi bi-list"></i> Existing Teams</h4>
                                    <p class="text-muted small mb-3">Players cannot be selected here directly. Use the Draft Pool tab.</p>
                                    <div class="table-responsive">
                                        <table class="table align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Logo</th>
                                                    <th>Team</th>
                                                    <th>Captain</th>
                                                    <th>Roster</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($teams as $team)
                                                    <tr>
                                                        <td>
                                                            @if($team->logo)
                                                                <img src="{{ asset('storage/' . $team->logo) }}" alt="{{ $team->name }} logo" class="team-logo-thumb">
                                                            @else
                                                                <div class="team-logo-placeholder"><i class="bi bi-image"></i></div>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <strong>{{ $team->name }}</strong>
                                                            @if($team->short_code)
                                                                <div class="small text-muted">{{ $team->short_code }}</div>
                                                            @endif
                                                        </td>
                                                        <td>{{ $team->captain_name ?: 'Not set' }}</td>
                                                        <td>{{ $team->participants_count }} / {{ $team->max_players }}</td>
                                                        <td>
                                                            <span style="display:inline-block;width:28px;height:28px;border-radius:50%;background:{{ $team->color ?? '#6c757d' }};border:2px solid #dee2e6;" title="{{ $team->color ?? '#6c757d' }}"></span>
                                                        </td>
                                                        <td class="actions-cell">
                                                            <button
                                                                class="btn btn-sm btn-outline-primary"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editTeamModal"
                                                                data-id="{{ $team->id }}"
                                                                data-name="{{ $team->name }}"
                                                                data-short="{{ $team->short_code }}"
                                                                data-captain="{{ $team->captain_name }}"
                                                                data-email="{{ $team->email }}"
                                                                data-league="{{ $team->league_type ?? 'male' }}"
                                                                data-max="{{ $team->max_players }}"
                                                                data-logo="{{ $team->logo ? asset('storage/' . $team->logo) : '' }}"
                                                                data-color="{{ $team->color ?? '#6c757d' }}"
                                                                onclick="setEditTeam(this)"
                                                            >
                                                                Edit
                                                            </button>
                                                            <form method="POST" action="{{ route('admin.team.delete', $team->id) }}" onsubmit="return confirm('Delete this team? Drafted players will return to draft pool.');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4">No teams found. Create your first team.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="draft-tab-pane" role="tabpanel" tabindex="0">
                        <div class="sub-card">
                            <livewire:draft-board :league-type="$activeLeagueType" :key="'draft-board-'.$activeLeagueType" />
                        </div>
                    </div>

                    <div class="tab-pane fade" id="drafted-tab-pane" role="tabpanel" tabindex="0">
                        <div class="sub-card">
                            <h4><i class="bi bi-collection"></i> Drafted Players</h4>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Player</th>
                                            <th>Team</th>
                                            <th>Skill Category</th>
                                            <th>Drafted At</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($draftedParticipants as $participant)
                                            <tr>
                                                <td>{{ $participant->full_name }}</td>
                                                <td>{{ $participant->team?->name }}</td>
                                                <td>
                                                    @php $draftedSkills = collect($participant->skill_categories ?? [])->filter()->values()->all(); @endphp
                                                    <div><strong>{{ $participant->category?->name ?: 'Uncategorized' }}</strong></div>
                                                    @if(count($draftedSkills))
                                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                                            @foreach($draftedSkills as $skill)
                                                                <span class="badge text-bg-light border">{{ $skill }}</span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <small class="text-muted">No specific skills</small>
                                                    @endif
                                                </td>
                                                <td>{{ optional($participant->drafted_at)->format('M d, Y H:i') }}</td>
                                                <td>
                                                    @if($participant->team)
                                                        <form method="POST" action="{{ route('admin.team.release', [$participant->team->id, $participant->id]) }}" onsubmit="return confirm('Release this player back to draft pool?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-sm btn-outline-warning" type="submit">Release</button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">No drafted players yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="categories-tab-pane" role="tabpanel" tabindex="0">
                        <div class="row g-4">
                            <div class="col-lg-4">
                                <div class="sub-card">
                                    <h4><i class="bi bi-tag"></i> Create Category</h4>
                                    <form method="POST" action="{{ route('admin.category.create') }}">
                                        @csrf
                                        <div class="mb-3">
                                            <label class="form-label">Category Name</label>
                                            <input class="form-control" name="name" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea class="form-control" name="description" rows="3" placeholder="Optional"></textarea>
                                        </div>
                                        <button class="btn btn-primary w-100" type="submit">Create Category</button>
                                    </form>
                                </div>
                            </div>

                            <div class="col-lg-8">
                                <div class="sub-card">
                                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                                        <div>
                                            <h4 class="mb-1"><i class="bi bi-tags"></i> Player Categories</h4>
                                            <p class="text-muted small mb-0">These categories are linked to players and used to start category-wise draft rounds.</p>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Category</th>
                                                    <th>Description</th>
                                                    <th>Players</th>
                                                    <th>Round</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($categories as $category)
                                                    <tr>
                                                        <td><strong>{{ $category->name }}</strong></td>
                                                        <td>{{ $category->description ?: 'No description' }}</td>
                                                        <td>{{ $category->participants_count }}</td>
                                                        <td>
                                                            <a href="{{ route('admin.teams', ['tab' => 'draft', 'category' => $category->id, 'league' => $activeLeagueType]) }}" class="btn btn-sm btn-outline-secondary">
                                                                Start Draft Round
                                                            </a>
                                                        </td>
                                                        <td class="actions-cell">
                                                            <button
                                                                class="btn btn-sm btn-outline-primary"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editCategoryModal"
                                                                data-id="{{ $category->id }}"
                                                                data-name="{{ $category->name }}"
                                                                data-description="{{ $category->description }}"
                                                                onclick="setEditCategory(this)"
                                                            >
                                                                Edit
                                                            </button>
                                                            <form method="POST" action="{{ route('admin.category.delete', $category->id) }}" onsubmit="return confirm('Delete this category? Linked players will become uncategorized.');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4">No categories found. Create a category to start category-wise draft rounds.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- League Setup Tab --}}
                    <div class="tab-pane fade" id="league-setup-tab-pane" role="tabpanel" tabindex="0">
                        <div class="sub-card">
                            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-4">
                                <div>
                                    <h4 class="mb-1"><i class="bi bi-calendar3"></i> League Round Setup</h4>
                                    <p class="text-muted small mb-0">Pre-plan all draft rounds. Set the full pick order for Round 1 by dragging teams; subsequent rounds are auto-generated &mdash; first-pick team moves to last each round, 2nd becomes new first. Total rounds = max players per team.</p>
                                </div>
                                @if($leagueRoundConfigs->isNotEmpty())
                                    <form method="POST" action="{{ route('admin.league.setup.clear') }}" onsubmit="return confirm('Clear the entire league round plan?');">
                                        @csrf
                                        <input type="hidden" name="league_type" value="{{ $activeLeagueType }}">
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i> Clear All Rounds</button>
                                    </form>
                                @endif
                            </div>

                            <div class="row g-4 mb-4">
                                <div class="col-lg-4">
                                    <div class="sub-card h-100">
                                        <h5><i class="bi bi-diagram-3"></i> Create League</h5>
                                        <form method="POST" action="{{ route('admin.leagues.store') }}">
                                            @csrf
                                            <div class="mb-3">
                                                <label class="form-label">League Name</label>
                                                <input class="form-control" name="name" placeholder="e.g. Legends League" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">League Slug</label>
                                                <input class="form-control" name="slug" placeholder="e.g. legends" required>
                                                <div class="form-text">Use lowercase letters, numbers, and dashes only.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="description" rows="3" placeholder="Optional"></textarea>
                                            </div>
                                            <button class="btn btn-primary w-100" type="submit">Create League</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="col-lg-8">
                                    <div class="sub-card h-100">
                                        <h5><i class="bi bi-list-stars"></i> Available Leagues</h5>
                                        <div class="table-responsive">
                                            <table class="table align-middle mb-0">
                                                 <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Slug</th>
                                                        <th>Description</th>
                                                        <th>Status</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse(($allLeagues ?? $availableLeagues ?? collect()) as $league)
                                                        <tr>
                                                            <td><strong>{{ $league->name }}</strong></td>
                                                            <td><code>{{ $league->slug }}</code></td>
                                                            <td>{{ $league->description ?: '—' }}</td>
                                                            <td>
                                                                @if($league->is_active)
                                                                    <span class="badge bg-success">Active</span>
                                                                @else
                                                                    <span class="badge bg-secondary">Inactive</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <form method="POST" action="{{ route('admin.leagues.toggle', $league->id) }}" class="d-inline">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    @if($league->is_active)
                                                                        <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Deactivate {{ $league->name }}?');">
                                                                            <i class="bi bi-pause-circle"></i> Deactivate
                                                                        </button>
                                                                    @else
                                                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                                                            <i class="bi bi-play-circle"></i> Activate
                                                                        </button>
                                                                    @endif
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="5" class="text-center text-muted py-3">No leagues available yet.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($teams->count() === 0)
                                <div class="alert alert-warning mb-0">
                                    <i class="bi bi-exclamation-triangle"></i> Create at least one team before setting up league rounds.
                                </div>
                            @else
                                <div class="round-setup-card mb-4">
                                    <h5 class="mb-3">
                                        <i class="bi bi-gear"></i>
                                        {{ $leagueRoundConfigs->isEmpty() ? 'Configure League Rounds' : 'Reconfigure League (replaces current plan)' }}
                                    </h5>
                                    <form method="POST" action="{{ route('admin.league.setup.save') }}" id="leagueSetupForm">
                                        @csrf
                                        <input type="hidden" name="league_type" value="{{ $activeLeagueType }}">
                                        @php
                                            $r1Config     = $leagueRoundConfigs->firstWhere('round_number', 1);
                                            $r1OrderIds   = $r1Config ? collect($r1Config->team_pick_order)->map(fn($id) => (int)$id)->all() : [];
                                            // Merge: saved order first, then any new teams not yet in saved order
                                            $savedTeamIds = collect($r1OrderIds);
                                            $allTeamIds   = $teams->pluck('id')->map(fn($id) => (int)$id);
                                            $newTeamIds   = $allTeamIds->diff($savedTeamIds);
                                            $orderedTeams = $savedTeamIds->merge($newTeamIds)
                                                ->map(fn($id) => $teams->firstWhere('id', $id))
                                                ->filter()
                                                ->values();
                                        @endphp
                                        <div class="row g-3">
                                            <div class="col-lg-3">
                                                <label class="form-label fw-semibold">Max Players per Team</label>
                                                <input type="number" class="form-control" name="max_players" min="1" max="99"
                                                    value="{{ $leagueRoundConfigs->count() ?: 16 }}" required>
                                                <div class="form-text">Each team gets this many players. One round per player &mdash; total rounds = this number.</div>
                                            </div>
                                            <div class="col-lg-9">
                                                <label class="form-label fw-semibold">Round 1 &mdash; Draft Pick Order</label>
                                                <p class="form-text mb-2">Drag teams to set who picks first, second, etc. Rounds 2&ndash;{{ $leagueRoundConfigs->count() ?: 16 }} are auto-generated: first-pick team moves to last each round.</p>
                                                <ul id="round1SortableList" class="list-group" style="cursor:grab; user-select:none;">
                                                    @foreach($orderedTeams as $idx => $t)
                                                        @php $teamColor = $t->color ?? '#6c757d'; @endphp
                                                        <li class="list-group-item d-flex align-items-center gap-2 py-2"
                                                            data-team-id="{{ $t->id }}"
                                                            style="border-left: 4px solid {{ $teamColor }};">
                                                            <span class="drag-handle text-muted me-1" style="cursor:grab;"><i class="bi bi-grip-vertical"></i></span>
                                                            <span class="pick-position-badge badge" style="background:{{ $teamColor }};color:#fff;">{{ $idx + 1 }}</span>
                                                            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $teamColor }};flex-shrink:0;"></span>
                                                            <span class="fw-semibold">{{ $t->name }}</span>
                                                            @if($t->short_code)
                                                                <small class="text-muted">({{ $t->short_code }})</small>
                                                            @endif
                                                            <input type="hidden" name="round1_order[]" value="{{ $t->id }}">
                                                        </li>
                                                    @endforeach
                                                </ul>
                                                <div class="form-text mt-1"><i class="bi bi-info-circle"></i> Position 1 = first pick, last position = last pick in Round 1.</div>
                                            </div>
                                            <div class="col-12">
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="bi bi-check2-circle"></i>
                                                    {{ $leagueRoundConfigs->isEmpty() ? 'Generate All Rounds' : 'Regenerate Rounds' }}
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                @if($leagueRoundConfigs->isEmpty())
                                    <div class="text-center py-5 text-muted">
                                        <i class="bi bi-calendar-x" style="font-size: 2.5rem;"></i>
                                        <p class="mt-2 mb-0">No rounds planned yet. Fill out the form above to generate the league schedule.</p>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table align-middle">
                                            <thead>
                                                <tr>
                                                    <th style="width:90px;">Round</th>
                                                    <th>Pick Order (First &rarr; Last)</th>
                                                    <th style="width:130px;">Type</th>
                                                    <th style="width:120px;">Status</th>
                                                    <th style="width:110px;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($leagueRoundConfigs as $config)
                                                    @php
                                                        $roundStatus = 'planned';
                                                        if ($config->round_number < $nextLeagueRoundNumber) {
                                                            $roundStatus = 'completed';
                                                        } elseif ($config->round_number === $nextLeagueRoundNumber && $activeRound) {
                                                            $roundStatus = 'active';
                                                        }
                                                    @endphp
                                                    <tr class="{{ $roundStatus === 'active' ? 'table-primary' : ($roundStatus === 'completed' ? 'opacity-50' : '') }}">
                                                        <td class="fw-bold">Round {{ $config->round_number }}</td>
                                                        <td>
                                                            <div class="d-flex flex-wrap gap-1">
                                                                @foreach($config->team_pick_order as $pos => $teamId)
                                                                    @php
                                                                        $pickTeam  = $teamsById->get((int)$teamId);
                                                                        $pickColor = $pickTeam?->color ?? '#6c757d';
                                                                        $isFirst   = $pos === 0;
                                                                        $isLast    = $pos === count($config->team_pick_order) - 1;
                                                                    @endphp
                                                                    <span class="badge" style="background:{{ $pickColor }};color:#fff;{{ $isFirst ? 'outline:2px solid #0d6efd;' : '' }}">
                                                                        {{ $pos + 1 }}. {{ $pickTeam?->name ?? 'Unknown' }}
                                                                        @if($isFirst)<small class="ms-1">(first)</small>@endif
                                                                        @if($isLast)<small class="ms-1">(last)</small>@endif
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        </td>
                                                        <td>
                                                            @if($config->is_manually_set)
                                                                <span class="badge text-bg-warning"><i class="bi bi-pencil"></i> Manual</span>
                                                            @else
                                                                <span class="badge text-bg-light text-dark"><i class="bi bi-arrow-repeat"></i> Auto</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($roundStatus === 'completed')
                                                                <span class="badge text-bg-success">Completed</span>
                                                            @elseif($roundStatus === 'active')
                                                                <span class="badge text-bg-primary">Active</span>
                                                            @else
                                                                <span class="badge text-bg-secondary">Planned</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($roundStatus !== 'completed')
                                                                <button
                                                                    class="btn btn-sm btn-outline-secondary"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#editLeagueRoundModal"
                                                                    data-round="{{ $config->round_number }}"
                                                                    data-first-team="{{ $config->team_pick_order[0] ?? '' }}"
                                                                    data-action="{{ route('admin.league.round.update', $config->round_number) }}"
                                                                >
                                                                    <i class="bi bi-pencil"></i> Override
                                                                </button>
                                                            @else
                                                                <span class="text-muted small">&mdash;</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i>
                                            <strong>Rotation rule:</strong> The first-pick team from round N moves to last in round N+1; the 2nd team becomes the new first pick.
                                            Use <em>Override</em> to manually reorder any future round.
                                            The planned first-pick is automatically pre-selected when starting a draft round from the <em>Draft Pool</em> tab.
                                        </small>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editLeagueRoundModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Override First Pick &mdash; Round <span id="editLeagueRoundNumber"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editLeagueRoundForm">
                    @csrf
                    @method('PATCH')
                    <div class="modal-body">
                        <input type="hidden" name="league_type" id="editLeagueRoundLeagueType" value="{{ $activeLeagueType }}">
                        <p class="text-muted small mb-3">The remaining teams keep their relative order from the existing plan, rotated so the chosen team goes first.</p>
                        <div class="mb-3">
                            <label class="form-label">First Pick Team</label>
                            <select class="form-select" name="first_team_id" id="editLeagueRoundFirstTeam" required>
                                @foreach($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Override</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editTeamModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Team</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editTeamForm" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Current Logo</label>
                            <div id="edit_team_logo_preview" class="team-logo-preview-empty">
                                <i class="bi bi-image"></i>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Team Name</label>
                            <input class="form-control" id="edit_team_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Short Code</label>
                            <input class="form-control" id="edit_team_short_code" name="short_code" maxlength="10">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Captain Name</label>
                            <input class="form-control" id="edit_team_captain_name" name="captain_name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Team Login Email</label>
                            <input type="email" class="form-control" id="edit_team_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">League</label>
                            <select class="form-select" id="edit_team_league_type" name="league_type" required>
                                @foreach(($availableLeagues ?? collect()) as $league)
                                    <option value="{{ $league->slug }}">{{ $league->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Replace Logo</label>
                            <input type="file" class="form-control" name="logo" accept="image/png,image/jpeg,image/jpg,image/webp">
                            <div class="form-text">Leave empty to keep the current logo.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Team Color</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color" id="edit_team_color" name="color" value="#6c757d" title="Pick team color">
                                <span class="form-text mb-0">Shown in pick-order badges and round tables.</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Team</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editCategoryForm">
                    @csrf
                    @method('PATCH')
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name</label>
                            <input class="form-control" id="edit_category_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="edit_category_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @php
        $adminBroadcastDriver = config('broadcasting.default');
        $adminBroadcastConnection = in_array($adminBroadcastDriver, ['reverb', 'pusher'], true)
            ? (array) config('broadcasting.connections.' . $adminBroadcastDriver, [])
            : [];
        $adminBroadcastConfig = [
            'enabled' => !empty($adminBroadcastConnection['key']),
            'channel' => 'draft.league.' . ($activeLeagueType ?? 'male'),
        ];
    @endphp
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        // ── Round 1 sortable pick-order list ────────────────────────────────────
        (function () {
            const list = document.getElementById('round1SortableList');
            if (!list) return;

            function refreshPositionBadges() {
                list.querySelectorAll('li').forEach(function (li, idx) {
                    const badge = li.querySelector('.pick-position-badge');
                    if (badge) badge.textContent = idx + 1;
                    // update the hidden input value to current team id (already set, just ensure order)
                });
            }

            Sortable.create(list, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'list-group-item-primary',
                onEnd: refreshPositionBadges,
            });
        }());
    </script>
    <script>
        function setEditTeam(button) {
            const id = button.dataset.id;
            const logo = button.dataset.logo;
            document.getElementById('editTeamForm').action = '/admin/teams/' + id;
            document.getElementById('edit_team_name').value = button.dataset.name || '';
            document.getElementById('edit_team_short_code').value = button.dataset.short || '';
            document.getElementById('edit_team_captain_name').value = button.dataset.captain || '';
            document.getElementById('edit_team_email').value = button.dataset.email || '';
            document.getElementById('edit_team_league_type').value = button.dataset.league || 'male';
            document.getElementById('edit_team_color').value = button.dataset.color || '#6c757d';

            const preview = document.getElementById('edit_team_logo_preview');

            if (logo) {
                preview.className = 'team-logo-preview';
                preview.innerHTML = '<img src="' + logo + '" alt="Team logo preview">';
            } else {
                preview.className = 'team-logo-preview-empty';
                preview.innerHTML = '<i class="bi bi-image"></i>';
            }
        }

        function setEditCategory(button) {
            document.getElementById('editCategoryForm').action = '/admin/categories/' + button.dataset.id;
            document.getElementById('edit_category_name').value = button.dataset.name || '';
            document.getElementById('edit_category_description').value = button.dataset.description || '';
        }

        const leagueRoundModal = document.getElementById('editLeagueRoundModal');
        if (leagueRoundModal) {
            leagueRoundModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) return;
                const roundNumber = button.getAttribute('data-round');
                const firstTeamId = button.getAttribute('data-first-team');
                const actionUrl   = button.getAttribute('data-action');
                document.getElementById('editLeagueRoundNumber').textContent = roundNumber || '';
                const form = document.getElementById('editLeagueRoundForm');
                if (form && actionUrl) form.action = actionUrl;
                const select = document.getElementById('editLeagueRoundFirstTeam');
                if (select && firstTeamId) select.value = firstTeamId;
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const page = document.getElementById('teamsPage');
            const activeTopTab = page?.dataset.activeTab || 'teams';
            const activeDraftCategory = page?.dataset.activeDraftCategory || 'uncategorized';
            const topTabButton = document.getElementById(activeTopTab + '-tab');
            const globalDraftRefreshKey = 'draft_force_refresh_at';
            let reloadScheduled = false;

            function reloadPage() {
                if (reloadScheduled) {
                    return;
                }

                reloadScheduled = true;
                const url = new URL(window.location.href);
                url.searchParams.set('_draft_refresh', String(Date.now()));
                window.location.replace(url.toString());
            }

            function triggerGlobalDraftRefresh() {
                try {
                    localStorage.setItem(globalDraftRefreshKey, String(Date.now()));
                } catch (error) {
                    console.warn('Unable to broadcast global draft refresh.', error);
                }
            }

            window.addEventListener('storage', function (event) {
                if (event.key !== globalDraftRefreshKey || !event.newValue) {
                    return;
                }

                if (document.visibilityState === 'visible') {
                    reloadPage();
                }
            });

            if (topTabButton) {
                bootstrap.Tab.getOrCreateInstance(topTabButton).show();
            }

            const draftCategoryButton = document.querySelector('[data-category-key="' + activeDraftCategory + '"]');
            const fallbackDraftCategoryButton = document.querySelector('#draftCategoryTabs .nav-link');

            if (draftCategoryButton) {
                bootstrap.Tab.getOrCreateInstance(draftCategoryButton).show();
            } else if (fallbackDraftCategoryButton) {
                bootstrap.Tab.getOrCreateInstance(fallbackDraftCategoryButton).show();
            }
        });
    </script>
@livewireScripts
</body>
</html>