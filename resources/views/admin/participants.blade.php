<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participants - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-participants.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/navbar.css') }}">
</head>
<body>
    @include('partials.portal-navbar')

    <!-- Main Content -->
    <div class="container-fluid main-container">
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

        <!-- Page Header -->
        <div class="page-header">
            <img src="{{ asset('images/logo.webp') }}" alt="Tournament Logo" class="page-logo">
            <h1><i class="bi bi-people"></i> Participants Management</h1>
            <p>View, approve, and manage participant submissions</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="/admin/participants">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, email, or city..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">League</label>
                        <select name="league_type" class="form-select">
                            <option value="">All Leagues</option>
                            @foreach(($leagues ?? collect()) as $league)
                                <option value="{{ $league->slug }}" {{ request('league_type') === $league->slug ? 'selected' : '' }}>{{ $league->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Filter by Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <a href="{{ route('admin.export', request()->query()) }}" class="btn btn-success">
                            <i class="bi bi-download"></i> CSV
                        </a>
                        <a href="/admin/participants" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table -->
        @if($participants->count() > 0)
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>City</th>
                                <th>League</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($participants as $participant)
                                <tr>
                                    <td>
                                        @if($participant->passport_picture)
                                            <img
                                                src="{{ asset('storage/' . ltrim($participant->passport_picture, '/')) }}"
                                                alt="{{ $participant->full_name }}"
                                                class="rounded"
                                                style="width: 150px; height: 150px; object-fit: cover;"
                                            >
                                        @else
                                            <span class="badge text-bg-secondary">No Photo</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $participant->full_name }}</strong>
                                    </td>
                                    <td>{{ $participant->email }}</td>
                                    <td>{{ substr($participant->mobile, -4, 4) }}... (encrypted)</td>
                                    <td>{{ $participant->city }}</td>
                                    <td>{{ ucfirst((string) ($participant->league_type ?? 'male')) }}</td>
                                    <td>
                                        <span class="status-badge {{ $participant->status }}">
                                            {{ ucfirst($participant->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>{{ $participant->created_at->format('M d, Y') }}</small>
                                    </td>
                                    <td>
                                        <a href="/admin/participants/{{ $participant->id }}" class="action-btn-small view">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        @if($participant->status === 'pending')
                                            <form action="/admin/participants/{{ $participant->id }}/approve" method="POST" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="action-btn-small approve" onclick="return confirm('Approve this participant?')">
                                                    <i class="bi bi-check"></i> Approve
                                                </button>
                                            </form>
                                            <form action="/admin/participants/{{ $participant->id }}/reject" method="POST" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="action-btn-small reject" onclick="return confirm('Reject this participant?')">
                                                    <i class="bi bi-x"></i> Reject
                                                </button>
                                            </form>
                                        @endif
                                        <form action="/admin/participants/{{ $participant->id }}" method="POST" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="action-btn-small delete" onclick="return confirm('Delete this participant?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($participants->hasPages())
                    <div class="d-flex justify-content-center p-3">
                        {{ $participants->links() }}
                    </div>
                @endif
            </div>
        @else
            <div class="table-container">
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>No participants found</h3>
                    <p>{{ request('search') || request('status') ? 'Try adjusting your search filters' : 'No participants have submitted the form yet' }}</p>
                </div>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
