<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/navbar.css') }}">
</head>
<body class="bg-light">
@include('partials.portal-navbar')

<div class="container py-4">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="mb-3">Filters</h5>
            <form method="GET" action="{{ route('activities.index') }}" class="row g-3">
                <div class="col-md-2">
                    <label for="team_id" class="form-label">Team</label>
                    <select name="team_id" id="team_id" class="form-select" {{ $isTeamUser ? 'disabled' : '' }}>
                        @if(!$isTeamUser)
                            <option value="">All Teams</option>
                        @endif
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}" {{ (string) $filters['team_id'] === (string) $team->id ? 'selected' : '' }}>
                                {{ $team->name }}
                            </option>
                        @endforeach
                    </select>
                    @if($isTeamUser)
                        <input type="hidden" name="team_id" value="{{ $filters['team_id'] }}">
                        <div class="form-text">Team users can only view their own activities.</div>
                    @endif
                </div>

                <div class="col-md-2">
                    <label for="round_id" class="form-label">Round</label>
                    <select name="round_id" id="round_id" class="form-select">
                        <option value="">All Rounds</option>
                        @foreach($rounds as $round)
                            <option value="{{ $round->id }}" {{ (string) $filters['round_id'] === (string) $round->id ? 'selected' : '' }}>
                                #{{ $round->id }} - {{ $round->category?->name ?: 'N/A' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="category_id" class="form-label">Category</label>
                    <select name="category_id" id="category_id" class="form-select">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ (string) $filters['category_id'] === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="from" class="form-label">From Date</label>
                    <input type="date" name="from" id="from" value="{{ $filters['from'] }}" class="form-control">
                </div>

                <div class="col-md-2">
                    <label for="to" class="form-label">To Date</label>
                    <input type="date" name="to" id="to" value="{{ $filters['to'] }}" class="form-control">
                </div>

                <div class="col-md-2">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Player/team/email">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="{{ route('activities.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-success">Export CSV</a>
                    <a href="{{ route('activities.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">All Draft Activities</h5>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Round</th>
                            <th>Pick #</th>
                            <th>Team</th>
                            <th>Player</th>
                            <th>Photo</th>
                            <th>Skill Category</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activities as $activity)
                            <tr>
                                <td>{{ optional($activity->picked_at)->format('M d, Y H:i:s') ?: '-' }}</td>
                                <td>#{{ $activity->round_number }}</td>
                                <td>{{ $activity->pick_number }}</td>
                                <td>{{ $activity->team?->name ?: 'N/A' }}</td>
                                <td>{{ $activity->participant?->full_name ?: 'N/A' }}</td>
                                <td>
                                    @if($activity->participant?->passport_picture)
                                        <img
                                            src="{{ asset('storage/' . ltrim($activity->participant->passport_picture, '/')) }}"
                                            alt="{{ $activity->participant?->full_name ?: 'Player' }}"
                                            class="rounded"
                                            style="width: 44px; height: 44px; object-fit: cover;"
                                        >
                                    @else
                                        <span class="badge text-bg-secondary">No Photo</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $mainCategory = $activity->round?->category?->name ?: ($activity->participant?->category?->name ?: 'Uncategorized');
                                        $skills = collect($activity->participant?->skill_categories ?? [])->filter()->values()->all();
                                    @endphp
                                    <div><strong>{{ $mainCategory }}</strong></div>
                                    @if(count($skills))
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            @foreach($skills as $skill)
                                                <span class="badge text-bg-light border">{{ $skill }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <small class="text-muted">No specific skills</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">No activities found for current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($activities->hasPages())
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted small">
                        Showing {{ $activities->firstItem() }} to {{ $activities->lastItem() }} of {{ $activities->total() }} activities
                    </div>
                    <div class="d-flex gap-2">
                        @if($activities->onFirstPage())
                            <span class="btn btn-sm btn-outline-secondary disabled">Previous</span>
                        @else
                            <a href="{{ $activities->previousPageUrl() }}" class="btn btn-sm btn-outline-primary">Previous</a>
                        @endif

                        @if($activities->hasMorePages())
                            <a href="{{ $activities->nextPageUrl() }}" class="btn btn-sm btn-outline-primary">Next</a>
                        @else
                            <span class="btn btn-sm btn-outline-secondary disabled">Next</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
