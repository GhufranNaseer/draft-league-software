<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Team Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/navbar.css') }}">
    @vite(['resources/js/app.js'])
    @livewireStyles
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
            <h3 class="mb-2 text-center">{{ $team->name }}</h3>
            <div class="row">
                <div class="col-md-8">
                    <p class="mb-1"><strong>Email:</strong> {{ $team->email }}</p>
                    <p class="mb-1"><strong>League:</strong> {{ $teamLeagueLabel ?? ucfirst((string) ($teamLeagueType ?? $team->league_type ?? 'male')) }}</p>
                    <p class="mb-1"><strong>Captain:</strong> {{ $team->captain_name ?: 'Not set' }}</p>
                    <p class="mb-0"><strong>Roster:</strong> {{ $participants->count() }} / {{ $team->max_players }}</p>
                </div>
                <div class="col-md-4">
                    <div class="d-flex flex-column align-items-center">
                        @if($team->logo)
                            <img src="{{ asset('storage/' . ltrim($team->logo, '/')) }}" alt="Team Logo" class="img-thumbnail mb-3" style="max-width: 150px; max-height: 150px;">
                        @else
                            <div class="bg-secondary text-white d-flex align-items-center justify-content-center rounded mb-3" style="width: 150px; height: 150px;">
                                <i class="bi bi-image" style="font-size: 2rem;"></i>
                            </div>
                        @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <livewire:team-draft-dashboard :team-id="$team->id" />
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="mb-3">Draft Activity</h5>
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
                        @forelse($draftActivity as $activity)
                            <tr>
                                <td>{{ optional($activity->picked_at)->format('M d, Y H:i') ?: '-' }}</td>
                                <td>#{{ $activity->round_number }}</td>
                                <td>{{ $activity->pick_number }}</td>
                                <td>{{ $activity->team?->name ?: 'N/A' }}</td>
                                <td>{{ $activity->participant?->full_name ?: 'N/A' }}</td>
                                <td>
                                    @php $activityPlayerPhoto = $activity->participant?->passport_picture; @endphp
                                    @if($activityPlayerPhoto)
                                        <img src="{{ asset('storage/' . ltrim($activityPlayerPhoto, '/')) }}" alt="Player Image" class="img-thumbnail" style="max-width: 50px; max-height: 50px;">
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
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">No draft activity yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Drafted Players</h5>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Skill Category</th>
                            <th>Drafted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($participants as $participant)
                            <tr>
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
                                <td>{{ optional($participant->drafted_at)->format('M d, Y H:i') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">No players drafted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@livewireScripts
</body>
</html>
