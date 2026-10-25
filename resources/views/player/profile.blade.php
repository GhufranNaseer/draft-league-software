<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/navbar.css') }}">
</head>
<body class="bg-light">
@include('partials.portal-navbar')

<div class="container py-4">
    <div class="card">
        <div class="card-body">
            <h4 class="mb-3">{{ $participant->full_name }}</h4>

            <div class="row g-3">
                <div class="col-md-6">
                    <strong>Email</strong>
                    <div>{{ $participant->email }}</div>
                </div>
                <div class="col-md-6">
                    <strong>City</strong>
                    <div>{{ $participant->city }}</div>
                </div>
                <div class="col-md-6">
                    <strong>Nick Name</strong>
                    <div>{{ $participant->nick_name }}</div>
                </div>
                <div class="col-md-6">
                    <strong>Nationality</strong>
                    <div>{{ $participant->nationality }}</div>
                </div>
                <div class="col-md-6">
                    <strong>Category</strong>
                    <div>{{ $participant->category?->name ?: 'Not assigned yet' }}</div>
                </div>
                <div class="col-md-6">
                    <strong>Selected By Team</strong>
                    <div>{{ $participant->team?->name ?: 'Not drafted yet' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
