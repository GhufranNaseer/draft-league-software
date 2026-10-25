@php
    $user = auth()->user();

    $brandHref = route('login');
    $brandLabel = 'Portal';

    if ($user) {
        if ($user->isAdmin()) {
            $brandHref = route('admin.dashboard');
            $brandLabel = 'Admin Panel';
        } elseif ($user->hasRole('team')) {
            $brandHref = route('team.dashboard');
            $brandLabel = 'Team Portal';
        } elseif ($user->hasRole('player')) {
            $brandHref = route('player.profile');
            $brandLabel = 'Player Portal';
        }
    }
@endphp

<nav class="navbar navbar-expand-lg navbar-dark sticky-top app-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ $brandHref }}">
            <i class="bi bi-diagram-3"></i> {{ $brandLabel }}
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#portalNavbar" aria-controls="portalNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="portalNavbar">
            <ul class="navbar-nav ms-auto">
                @if($user && $user->isAdmin())
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.participants') || request()->routeIs('admin.participant.*') ? 'active' : '' }}" href="{{ route('admin.participants') }}">
                            <i class="bi bi-people"></i> Participants
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.teams') ? 'active' : '' }}" href="{{ route('admin.teams') }}">
                            <i class="bi bi-shield"></i> Teams
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.drafting-screen') ? 'active' : '' }}" href="{{ route('admin.drafting-screen') }}">
                            <i class="bi bi-tv"></i> Drafting Screen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}">
                            <i class="bi bi-person-gear"></i> Users
                        </a>
                    </li>
                @elseif($user && $user->hasRole('team'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('team.dashboard') ? 'active' : '' }}" href="{{ route('team.dashboard') }}">
                            <i class="bi bi-speedometer2"></i> Team Dashboard
                        </a>
                    </li>
                @elseif($user && $user->hasRole('player'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('player.profile') ? 'active' : '' }}" href="{{ route('player.profile') }}">
                            <i class="bi bi-person-circle"></i> Player Profile
                        </a>
                    </li>
                @endif

                @if($user)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('activities.index') ? 'active' : '' }}" href="{{ route('activities.index') }}">
                            <i class="bi bi-activity"></i> Activities
                        </a>
                    </li>
                    <li class="nav-item">
                        <form id="logout-form" method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="nav-link" style="border: none; background: none; cursor: pointer;">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </button>
                        </form>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</nav>
