<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-users.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/navbar.css') }}">
</head>

<body data-should-reopen-reset-modal="{{ $errors->any() && old('_form') === 'reset_password' && old('user_id') ? '1' : '0' }}" data-old-reset-user-id="{{ old('user_id', '') }}">
    @include('partials.portal-navbar')

    <!-- Main Content -->
    <div class="container-fluid main-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="bi bi-person-gear"></i> Users Management</h1>
                <p>Create and manage admin user accounts</p>
            </div>
            <button class="add-user-btn" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <i class="bi bi-person-plus"></i> Add New User
            </button>
        </div>

        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Action failed.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif
        <div class="card">
            <ul class="nav nav-tabs" id="nav-tab" role="tablist">
                @foreach($roles as $key=>$role)
                <li class="nav-item" role="presentation">
                    <button class="nav-link @if($key === 0) active @endif" id="nav-{{ $role }}-tab" data-bs-toggle="tab" data-bs-target="#nav-{{ $role }}" type="button" role="tab" aria-controls="nav-{{ $role }}" aria-selected="{{ $key === 0 ? 'true' : 'false' }}">{{ ucfirst($role) }}</button>
                </li>
                @endforeach
            </ul>
            <div class="tab-content" id="nav-tabContent">
                @foreach($roles as $key=>$role)
                <div class="tab-pane fade @if($key === 0) show active @endif" id="nav-{{ $role }}" role="tabpanel" aria-labelledby="nav-{{ $role }}-tab">
                    <!-- {{ ucfirst($role) }} List -->
                    @if($users[$role]->count() > 0)
                    <div class="row">
                        @foreach($users[$role] as $user)
                        <div class="col-12">
                            <div class="user-card">
                                <div class="user-header">
                                    <div class="user-avatar">{{ substr($user->name, 0, 1) }}</div>
                                    <div class="user-header-content">
                                        <h5>{{ $user->name }}</h5>
                                        <p>{{ $user->email }}</p>
                                        <div>
                                            @forelse($user->getRoleNames() as $roleName)
                                            <span class="badge text-bg-secondary me-1">{{ ucfirst($roleName) }}</span>
                                            @empty
                                            <span class="badge text-bg-light">No role</span>
                                            @endforelse
                                        </div>
                                    </div>
                                    @if($user->is_admin)
                                    <div class="admin-badge">
                                        <i class="bi bi-shield-check"></i> Administrator
                                    </div>
                                    @endif
                                </div>

                                <div class="user-info">
                                    <div class="info-item">
                                        <span class="info-label">Created</span>
                                        <span class="info-value">{{ $user->created_at->format('M d, Y') }}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Status</span>
                                        <span class="info-value">
                                            <span style="color: var(--success-color); font-weight: 600;">
                                                <i class="bi bi-check-circle"></i> Active
                                            </span>
                                        </span>
                                    </div>
                                </div>

                                <div class="user-actions">
                                    <button
                                        class="action-btn-small edit"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editUserModal"
                                        data-id="{{ $user->id }}"
                                        data-name="{{ $user->name }}"
                                        data-email="{{ $user->email }}"
                                        data-roles='@json($user->getRoleNames()->values())'
                                        onclick="editUser(this)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button
                                        class="action-btn-small"
                                        data-bs-toggle="modal"
                                        data-bs-target="#resetPasswordModal"
                                        data-id="{{ $user->id }}"
                                        data-name="{{ $user->name }}"
                                        onclick="openResetPasswordModal(this)">
                                        <i class="bi bi-key"></i> Reset Password
                                    </button>
                                    @if(auth()->user()->id !== $user->id)
                                    <form method="POST" action="/admin/users/{{ $user->id }}" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-btn-small delete" onclick="return confirm('Delete this user?')">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="empty-state">
                        <i class="bi bi-people"></i>
                        <h3>No users found</h3>
                        <p>Create your first admin user to get started</p>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        <!-- Create User Modal -->
        <div class="modal fade" id="createUserModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-plus"></i> Create New User</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="/admin/users">
                        @csrf
                        <input type="hidden" name="_form" value="create_user">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="password" onclick="togglePassword(this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="password_confirmation" onclick="togglePassword(this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Assign Roles</label>
                                @foreach($roles as $role)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="create_role_{{ $role }}" name="roles[]" value="{{ $role }}">
                                    <label class="form-check-label" for="create_role_{{ $role }}">
                                        {{ ucfirst($role) }}
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div class="modal fade" id="editUserModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit User</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" id="editForm">
                        @csrf
                        @method('PATCH')
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Assign Roles</label>
                                @foreach($roles as $role)
                                <div class="form-check">
                                    <input class="form-check-input edit-role-checkbox" type="checkbox" id="edit_role_{{ $role }}" name="roles[]" value="{{ $role }}">
                                    <label class="form-check-label" for="edit_role_{{ $role }}">
                                        {{ ucfirst($role) }}
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reset Password Modal -->
        <div class="modal fade" id="resetPasswordModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-key"></i> Reset User Password</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" id="resetPasswordForm">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="_form" value="reset_password">
                        <input type="hidden" name="user_id" id="reset_user_id">
                        <div class="modal-body">
                            <p class="mb-3">Set a new password for <strong id="resetPasswordUserName"></strong>.</p>
                            <div class="mb-3">
                                <label for="reset_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="reset_password" name="password" required minlength="8">
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="reset_password" onclick="togglePassword(this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="reset_password_confirmation" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="reset_password_confirmation" name="password_confirmation" required minlength="8">
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="reset_password_confirmation" onclick="togglePassword(this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Reset Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function editUser(button) {
                const id = button.dataset.id;
                const name = button.dataset.name;
                const email = button.dataset.email;
                const roles = JSON.parse(button.dataset.roles || '[]');

                document.getElementById('edit_name').value = name;
                document.getElementById('edit_email').value = email;
                document.getElementById('editForm').action = '/admin/users/' + id;

                document.querySelectorAll('.edit-role-checkbox').forEach((checkbox) => {
                    checkbox.checked = roles.includes(checkbox.value);
                });
            }

            function openResetPasswordModal(button) {
                const id = button.dataset.id;
                const name = button.dataset.name;

                document.getElementById('resetPasswordForm').action = '/admin/users/' + id + '/password';
                document.getElementById('reset_user_id').value = id;
                document.getElementById('resetPasswordUserName').innerText = name;
                document.getElementById('reset_password').value = '';
                document.getElementById('reset_password_confirmation').value = '';
            }

            function togglePassword(button) {
                const targetInput = document.getElementById(button.dataset.target);

                if (!targetInput) {
                    return;
                }

                const icon = button.querySelector('i');
                const isHidden = targetInput.type === 'password';
                targetInput.type = isHidden ? 'text' : 'password';

                if (icon) {
                    icon.classList.toggle('bi-eye', !isHidden);
                    icon.classList.toggle('bi-eye-slash', isHidden);
                }
            }

            const shouldReopenResetModal = document.body.dataset.shouldReopenResetModal === '1';
            const oldResetUserId = document.body.dataset.oldResetUserId;

            if (shouldReopenResetModal && oldResetUserId) {
                document.addEventListener('DOMContentLoaded', function() {
                    const form = document.getElementById('resetPasswordForm');
                    form.action = '/admin/users/' + oldResetUserId + '/password';
                    document.getElementById('reset_user_id').value = oldResetUserId;

                    const resetModalElement = document.getElementById('resetPasswordModal');
                    const resetModal = new bootstrap.Modal(resetModalElement);
                    resetModal.show();
                });
            }
        </script>
</body>

</html>