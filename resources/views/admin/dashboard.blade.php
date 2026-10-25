<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Participant Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/navbar.css') }}">
</head>
<body>
    @include('partials.portal-navbar')

    <!-- Main Content -->
    <div class="container main-container">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="text-white mb-2">Welcome, {{ auth()->user()->name }}!</h1>
                <p class="text-white-50">Manage participants and user accounts</p>
            </div>
            <div class="col-md-4 text-md-end">
                <p class="text-white-50">Last login: {{ auth()->user()->updated_at->diffForHumans() }}</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <!-- Total Participants -->
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="stat-card primary">
                    <div class="stat-icon text-primary">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-number">{{ $total_participants }}</div>
                    <div class="stat-label">Total Participants</div>
                </div>
            </div>

            <!-- Pending -->
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="stat-card pending">
                    <div class="stat-icon" style="color: var(--warning-color);">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-number">{{ $pending }}</div>
                    <div class="stat-label">Pending Review</div>
                </div>
            </div>

            <!-- Approved -->
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="stat-card approved">
                    <div class="stat-icon text-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-number">{{ $approved }}</div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>

            <!-- Rejected -->
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="stat-card rejected">
                    <div class="stat-icon text-danger">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div class="stat-number">{{ $rejected }}</div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>

        <!-- Secondary Stats -->
        <div class="row mb-4">
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="stat-card users">
                    <div class="stat-icon" style="color: var(--secondary-color);">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-number">{{ $total_users }}</div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3 mb-3">
                <div class="stat-card users">
                    <div class="stat-icon" style="color: #0ea5e9;">
                        <i class="bi bi-shield"></i>
                    </div>
                    <div class="stat-number">{{ $total_teams }}</div>
                    <div class="stat-label">Total Teams</div>
                </div>
            </div>

            <!-- Approval Rate -->
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="stat-card primary">
                    <div class="stat-icon text-primary">
                        <i class="bi bi-percent"></i>
                    </div>
                    <div class="stat-number">
                        @if($total_participants > 0)
                            {{ round(($approved / $total_participants) * 100) }}%
                        @else
                            0%
                        @endif
                    </div>
                    <div class="stat-label">Approval Rate</div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row">
            <div class="col-12">
                <div class="action-buttons">
                    <a href="/admin/participants" class="action-btn primary">
                        <i class="bi bi-people"></i> View All Participants
                    </a>
                    <a href="/admin/participants?status=pending" class="action-btn warning" style="background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%); color: white;">
                        <i class="bi bi-hourglass-split"></i> Review Pending
                    </a>
                    <a href="/admin/users" class="action-btn success">
                        <i class="bi bi-person-plus"></i> Manage Users
                    </a>
                    <a href="/admin/teams" class="action-btn primary">
                        <i class="bi bi-trophy"></i> Team Module
                    </a>
                    <a href="{{ route('activities.index') }}" class="action-btn secondary">
                        <i class="bi bi-activity"></i> Check Activities
                    </a>
                    <a href="/admin/export" class="action-btn secondary">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                </div>
            </div>
        </div>

        <!-- User Info Card -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="user-info-card">
                    <div class="user-info">
                        <div class="user-avatar">{{ substr(auth()->user()->name, 0, 1) }}</div>
                        <div class="user-details">
                            <h6>{{ auth()->user()->name }}</h6>
                            <small>{{ auth()->user()->email }}</small>
                            <br>
                            <small class="text-success" style="font-weight: 600;">
                                <i class="bi bi-shield-check"></i> Administrator
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
