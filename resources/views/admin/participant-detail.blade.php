<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participant Details - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-participant-detail.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/navbar.css') }}">
</head>

<body>
    @include('partials.portal-navbar')

    <!-- Main Content -->
    <div class="container main-container">
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

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <img src="{{ asset('images/logo.webp') }}" alt="Tournament Logo" class="page-logo">
                <h1 class="text-white mb-2">{{ $participant->first_name }} {{ $participant->last_name }}</h1>
                <p class="text-white-50">
                    <a href="/admin/participants" class="text-white-50">← Back to Participants</a>
                </p>
            </div>
            <div class="d-flex gap-2">
                <span class="status-badge {{ $participant->status }}">
                    {{ ucfirst($participant->status) }}
                </span>
                <a href="{{ route('admin.participant.edit', $participant->id) }}" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            </div>
        </div>

        <!-- Personal Information Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="bi bi-person"></i> Personal Information</h2>
            </div>
            <div class="card-body">
                <div class="personal-info-layout">
                    <div class="personal-info-details">
                        <div class="info-row">
                            <div class="info-item">
                                <span class="info-label">Full Name</span>
                                <span class="info-value">{{ $participant->first_name }} {{ $participant->last_name }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Nick Name</span>
                                <span class="info-value">{{ $participant->nick_name }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value">{{ $participant->email }}</span>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-item">
                                <span class="info-label">Phone (Encrypted)</span>
                                <div class="value-action-row">
                                    <span class="info-value" id="mobileValue">••••••••{{ substr($participant->mobile, -4) }}</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="mobileToggle" onclick="toggleMobile()">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Emergency Contact (Encrypted)</span>
                                <div class="value-action-row">
                                    <span class="info-value" id="emergencyValue">••••••••{{ substr($participant->emergency_contact, -4) }}</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="emergencyToggle" onclick="toggleEmergency()">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Date of Birth</span>
                                <span class="info-value">{{ \Carbon\Carbon::parse($participant->dob)->format('M d, Y') }}</span>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-item">
                                <span class="info-label">Nationality</span>
                                <span class="info-value">{{ $participant->nationality }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">League</span>
                                <span class="info-value">{{ ucfirst((string) ($participant->league_type ?? 'male')) }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Performance</span>
                                <span class="info-value">{{ $participant->performance ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="participant-photo-panel">
                        <span class="info-label">Participant Photo</span>
                        @php
                        $participantPhoto = $participant->passport_picture;
                        @endphp
                        @if($participantPhoto)
                        <img
                            src="{{ asset('storage/' . ltrim($participantPhoto, '/')) }}"
                            alt="{{ $participant->full_name }}"
                            class="participant-photo">
                        @else
                        <div class="participant-photo-placeholder">
                            <i class="bi bi-person-badge"></i>
                            <span>No photo uploaded</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- <div class="section-title">Medical & Performance Information</div>
                <div class="info-row">
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="info-label">Medical Information</span>
                        <span class="info-value">{{ $participant->medical_info ?? 'N/A' }}</span>
                    </div>
</div> -->


                <div class="section-title">Address Information</div>
                <div class="info-row">
                    <div class="info-item">
                        <span class="info-label">City</span>
                        <span class="info-value">{{ $participant->city }}</span>
                    </div>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="info-label">Address</span>
                        <span class="info-value">{{ $participant->address }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cricket Information Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="bi bi-trophy"></i> Cricket Information</h2>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-item">
                        <span class="info-label">Shirt Number</span>
                        <span class="info-value">{{ $participant->shirt_number }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Kit Size</span>
                        <span class="info-value">{{ ucfirst($participant->kit_size) }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Identity (Encrypted)</span>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="info-value" id="identityValue">••••{{ substr($participant->identity, -4) }}</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="identityToggle" onclick="toggleIdentity()">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="info-label">Skill Categories</span>
                        <span class="info-value">
                            @if($participant->skill_categories && is_array($participant->skill_categories))
                            @foreach($participant->skill_categories as $skill)
                            <span class="badge bg-primary me-2">{{ $skill }}</span>
                            @endforeach
                            @else
                            N/A
                            @endif
                        </span>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="info-label">Category</span>
                        <span class="info-value">
                            @if($participant->category)
                            <span class="badge bg-success">{{ $participant->category->name }}</span>
                            @else
                            <span class="text-muted">Not assigned</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Travel Information Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="bi bi-airplane"></i> Travel Information</h2>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-item">
                        <span class="info-label">Airline</span>
                        <span class="info-value">{{ $participant->airline ?? 'N/A' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Arrival Date</span>
                        <span class="info-value">{{ $participant->arrival_date ? \Carbon\Carbon::parse($participant->arrival_date)->format('M d, Y') : 'N/A' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Arrival Time</span>
                        <span class="info-value">{{ $participant->arrival_time ?? 'N/A' }}</span>
                    </div>
                </div>

                <div class="section-title">Hotel Information</div>
                <div class="info-row">
                    <div class="info-item">
                        <span class="info-label">Hotel Name</span>
                        <span class="info-value">{{ $participant->hotel_name ?? 'N/A' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Checkin Date</span>
                        <span class="info-value">{{ $participant->checkin ? \Carbon\Carbon::parse($participant->checkin)->format('M d, Y') : 'N/A' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Checkout Date</span>
                        <span class="info-value">{{ $participant->checkout ? \Carbon\Carbon::parse($participant->checkout)->format('M d, Y') : 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="bi bi-file-earmark"></i> Uploaded Documents</h2>
            </div>
            <div class="card-body">
                <div class="file-section">
                    @if($participant->passport_picture)
                    <div class="file-item">
                        <div class="file-info">
                            <div class="file-icon">
                                <i class="bi bi-file-image"></i>
                            </div>
                            <div class="file-details">
                                <h6>Passport Photo</h6>
                                <small>Image document</small>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="file-download-btn" style="background: #007342;" onclick="previewImage('{{ route('admin.participant.preview', ['participantId' => $participant->id, 'fileType' => 'passport']) }}', 'Passport Photo')">
                                <i class="bi bi-eye"></i> Preview
                            </button>
                            <a href="{{ route('admin.participant.download', ['participantId' => $participant->id, 'fileType' => 'passport']) }}" class="file-download-btn">
                                <i class="bi bi-download"></i> Download
                            </a>
                        </div>
                    </div>
                    @endif

                    @if($participant->id_picture)
                    <div class="file-item">
                        <div class="file-info">
                            <div class="file-icon">
                                <i class="bi bi-file-image"></i>
                            </div>
                            <div class="file-details">
                                <h6>ID Picture</h6>
                                <small>Image document</small>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="file-download-btn" style="background: #007342;" onclick="previewImage('{{ route('admin.participant.preview', ['participantId' => $participant->id, 'fileType' => 'id']) }}', 'ID Picture')">
                                <i class="bi bi-eye"></i> Preview
                            </button>
                            <a href="{{ route('admin.participant.download', ['participantId' => $participant->id, 'fileType' => 'id']) }}" class="file-download-btn">
                                <i class="bi bi-download"></i> Download
                            </a>
                        </div>
                    </div>
                    @endif

                    @if($participant->hotel_reservation)
                    <div class="file-item">
                        <div class="file-info">
                            <div class="file-icon">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </div>
                            <div class="file-details">
                                <h6>Hotel Reservation</h6>
                                <small>PDF document</small>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="file-download-btn" style="background: #007342;" onclick="previewPDF('{{ route('admin.participant.preview', ['participantId' => $participant->id, 'fileType' => 'hotel']) }}', 'Hotel Reservation')">
                                <i class="bi bi-eye"></i> Preview
                            </button>
                            <a href="{{ route('admin.participant.download', ['participantId' => $participant->id, 'fileType' => 'hotel']) }}" class="file-download-btn">
                                <i class="bi bi-download"></i> Download
                            </a>
                        </div>
                    </div>
                    @endif

                    @if($participant->flight_reservation)
                    <div class="file-item">
                        <div class="file-info">
                            <div class="file-icon">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </div>
                            <div class="file-details">
                                <h6>Flight Reservation</h6>
                                <small>PDF document</small>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="file-download-btn" style="background: #007342;" onclick="previewPDF('{{ route('admin.participant.preview', ['participantId' => $participant->id, 'fileType' => 'flight']) }}', 'Flight Reservation')">
                                <i class="bi bi-eye"></i> Preview
                            </button>
                            <a href="{{ route('admin.participant.download', ['participantId' => $participant->id, 'fileType' => 'flight']) }}" class="file-download-btn">
                                <i class="bi bi-download"></i> Download
                            </a>
                        </div>
                    </div>
                    @endif

                    @if(!$participant->passport_picture && !$participant->id_picture && !$participant->hotel_reservation && !$participant->flight_reservation)
                    <div style="text-align: center; color: #9ca3af; padding: 20px;">
                        <i class="bi bi-inbox" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                        No documents uploaded
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Submission Information Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="bi bi-clock"></i> Submission Information</h2>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-item">
                        <span class="info-label">Submitted On</span>
                        <span class="info-value">{{ $participant->created_at->format('M d, Y H:i A') }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Updated</span>
                        <span class="info-value">{{ $participant->updated_at->format('M d, Y H:i A') }}</span>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-item">
                        <span class="info-label">Current Status</span>
                        <span class="info-value">
                            <span class="status-badge {{ $participant->status }}">
                                {{ ucfirst($participant->status) }}
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Actions</h5>
                <div class="action-buttons">
                    @if($participant->status === 'pending')
                    <form method="POST" action="/admin/participants/{{ $participant->id }}/approve" style="margin: 0;">
                        @csrf
                        <button type="submit" class="action-btn success" onclick="return confirm('Approve this participant?')">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                    </form>

                    <form method="POST" action="/admin/participants/{{ $participant->id }}/reject" style="margin: 0;">
                        @csrf
                        <button type="submit" class="action-btn danger" onclick="return confirm('Reject this participant?')">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                    </form>
                    @endif

                    <form method="POST" action="/admin/participants/{{ $participant->id }}" style="margin: 0;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="action-btn danger" onclick="return confirm('Delete this participant? This action cannot be undone.')">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </form>

                    <a href="/admin/participants" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div id="imagePreviewModal" class="preview-modal">
        <div class="preview-modal-content">
            <div class="preview-modal-header">
                <h5 id="previewTitle">Preview</h5>
                <button type="button" class="preview-modal-close" onclick="closePreview()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="preview-modal-body">
                <img id="previewImage" src="" alt="Preview" />
            </div>
        </div>
    </div>

    <!-- PDF Preview Modal -->
    <div id="pdfPreviewModal" class="preview-modal">
        <div class="preview-modal-content">
            <div class="preview-modal-header">
                <h5 id="pdfPreviewTitle">Preview</h5>
                <button type="button" class="preview-modal-close" onclick="closePDFPreview()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="preview-modal-body">
                <iframe id="pdfFrame" src=""></iframe>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const mobileValue = '{{ $participant->mobile }}';
        const emergencyValue = '{{ $participant->emergency_contact }}';
        const identityValue = '{{ $participant->identity }}';
        let mobileVisible = false;
        let emergencyVisible = false;
        let identityVisible = false;

        function toggleMobile() {
            mobileVisible = !mobileVisible;
            const display = document.getElementById('mobileValue');
            const btn = document.getElementById('mobileToggle');
            if (mobileVisible) {
                display.textContent = mobileValue;
                btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
            } else {
                display.textContent = '••••••••' + mobileValue.slice(-4);
                btn.innerHTML = '<i class="bi bi-eye"></i>';
            }
        }

        function toggleEmergency() {
            emergencyVisible = !emergencyVisible;
            const display = document.getElementById('emergencyValue');
            const btn = document.getElementById('emergencyToggle');
            if (emergencyVisible) {
                display.textContent = emergencyValue;
                btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
            } else {
                display.textContent = '••••••••' + emergencyValue.slice(-4);
                btn.innerHTML = '<i class="bi bi-eye"></i>';
            }
        }

        function toggleIdentity() {
            identityVisible = !identityVisible;
            const display = document.getElementById('identityValue');
            const btn = document.getElementById('identityToggle');
            if (identityVisible) {
                display.textContent = identityValue;
                btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
            } else {
                display.textContent = '••••' + identityValue.slice(-4);
                btn.innerHTML = '<i class="bi bi-eye"></i>';
            }
        }

        // Preview Functions
        function previewImage(imageSrc, title) {
            document.getElementById('previewTitle').textContent = title;
            document.getElementById('previewImage').src = imageSrc;
            document.getElementById('imagePreviewModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closePreview() {
            document.getElementById('imagePreviewModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        function previewPDF(pdfSrc, title) {
            document.getElementById('pdfPreviewTitle').textContent = title;
            document.getElementById('pdfFrame').src = pdfSrc;
            document.getElementById('pdfPreviewModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closePDFPreview() {
            document.getElementById('pdfPreviewModal').classList.remove('show');
            document.getElementById('pdfFrame').src = '';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside the modal content
        document.getElementById('imagePreviewModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closePreview();
            }
        });

        document.getElementById('pdfPreviewModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closePDFPreview();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePreview();
                closePDFPreview();
            }
        });
    </script>