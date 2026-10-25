# AI Project Guide

## Project Summary

This is a Laravel 12 participant management and live draft system. It supports public player registration, admin review, league/team setup, timed draft rounds, real-time draft updates, and role-based dashboards.

Use this document as the first context file for AI-assisted development. For product intent, also read `DEVELOPER_PRODUCT_BRIEF.md` and `USER_PRODUCT_BRIEF.md`.

## Stack

- PHP 8.2
- Laravel 12
- Blade views with custom CSS in `public/assets/css`
- Livewire 4 for draft dashboards
- Laravel Reverb, Echo, and Pusher protocol for real-time updates
- Sanctum and Spatie Laravel Permission for auth/roles
- PHPUnit 11 for tests
- Laravel Pint for PHP formatting
- Vite and Tailwind CSS 4 are installed, but many screens use dedicated CSS files

## Main User Roles

- Public participant: submits the registration form and can check submission status.
- Player: gets a login after admin approval and can view their profile.
- Team user: accesses the team dashboard, sees eligible players, and drafts when it is their turn.
- Admin: manages participants, categories, teams, leagues, users, league setup, draft rounds, files, and exports.

## Important Routes

Primary route definitions live in `routes/web.php`.

- `/` shows the public participant form.
- `/submit-form` accepts public registration submissions.
- `/status/{id}` returns participant submission status.
- `/login` and `/logout` handle auth.
- `/team/dashboard` is the team user dashboard.
- `/activities` shows draft activity, scoped for team users.
- `/admin/dashboard` is the admin home.
- `/admin/participants/*` manages participant moderation and files.
- `/admin/teams` is the central team, category, league setup, and draft page.
- `/admin/users` manages user accounts and roles.

API participant routes exist in `routes/api.php` through `App\Http\Controllers\Api\ParticipantController`.

## Core Domain Files

- `app/Models/Participant.php`: registration data, status, category, team assignment, uploaded file paths, draft timestamp.
- `app/Models/Team.php`: team identity, league, color/logo, max roster size, participants relation.
- `app/Models/League.php`: active league definitions and slugs.
- `app/Models/Category.php`: player category grouping.
- `app/Models/LeagueRoundConfig.php`: saved pick order per league round.
- `app/Models/DraftRound.php`: active/completed round state, category rules, pick order, current team, timer.
- `app/Models/DraftPick.php`: immutable pick history for activity and audit.
- `app/Services/DraftService.php`: central draft engine. Keep turn advancement, timed expiry, eligibility, concurrency, and broadcasting here.

## Main Controllers

- `ParticipantFormController`: public form display, validation, file upload, participant creation, status lookup, confirmation email.
- `AuthController`: login/logout.
- `AdminController`: admin dashboard, participant moderation, user management, categories, file preview/download, CSV export.
- `TeamController`: team dashboard, team CRUD, league setup, draft round start/close/tick, manual drafting, active round picking, draft activities.
- `PlayerController`: player profile.

## Livewire Components

- `app/Livewire/DraftBoard.php`: admin/public-style live draft board for one league. It listens for `draft.turn.changed`, polls/ticks the active round, and renders teams, categories, round progress, and pool data.
- `app/Livewire/TeamDraftDashboard.php`: team dashboard component. It validates the authenticated team context, lists eligible players, calls `DraftService::pick()`, and listens for draft broadcasts.

Livewire Blade views are in `resources/views/livewire`.

## Draft Flow

1. Admin creates leagues, categories, teams, and approved participants.
2. Admin saves league setup on `/admin/teams`, which creates `LeagueRoundConfig` rows.
3. Admin starts a draft round for a league/category.
4. `DraftRound` stores the active pick order, current team, current pick number, timer, and category eligibility.
5. Team users pick from eligible approved participants during their turn.
6. `DraftService::pick()` locks the round and participant, validates all rules, creates `DraftPick`, assigns the participant to the team, advances the turn, or completes the round.
7. `DraftService::tick()` handles expired turns.
8. `DraftTurnChanged` broadcasts changes so Livewire dashboards refresh.

Do not duplicate draft state transition logic in controllers or Blade. Add new draft rules to `DraftService` and cover them with focused tests.

## Data Rules To Preserve

- Only approved participants can be drafted.
- A participant can belong to only one team at a time.
- Team and participant league types must match.
- Active draft rounds are scoped by league.
- Eligible draft categories are the selected round category plus optional higher categories.
- Team users may only act for their linked team.
- Admins can manage and override broader draft operations.
- Draft picks should remain an audit trail; be cautious when deleting draft-related records.
- League setup reset currently clears draft progress for that league and releases drafted participants.

## Auth And Roles

Roles are seeded through `DatabaseSeeder`: `admin`, `team`, `player`, and `user`.

- Admin access uses the `admin` middleware.
- Team access is partly role-based and partly linked by matching `users.email` to `teams.email`.
- Approving a participant may create or link a `player` user.
- Creating/updating a team may create or link a `team` user.
- Admin email accounts must not be reused as team/player accounts.

## Database Areas

Read migrations in `database/migrations` before changing schemas. Key tables:

- `participants`
- `teams`
- `categories`
- `leagues`
- `league_round_configs`
- `draft_rounds`
- `draft_picks`
- `users`
- Spatie permission tables

When altering existing columns, preserve all previous column attributes in the migration.

## Frontend Organization

Most pages are Blade templates in `resources/views`.

Important view groups:

- `resources/views/participant_form.blade.php`
- `resources/views/admin/*`
- `resources/views/team/dashboard.blade.php`
- `resources/views/player/profile.blade.php`
- `resources/views/activities/index.blade.php`
- `resources/views/livewire/*`
- `resources/views/partials/portal-navbar.blade.php`

Dedicated CSS files are in `public/assets/css`, including:

- `participant-form.css`
- `admin-dashboard.css`
- `admin-participants.css`
- `admin-teams.css`
- `admin-users.css`
- `auth-login.css`
- `navbar.css`

Before adding UI, check existing Blade partials and CSS patterns. Keep operational admin/team screens dense, clear, and task-focused.

## Development Conventions

- Follow Laravel 12 structure. Middleware registration is in `bootstrap/app.php`, not `app/Http/Kernel.php`.
- Use named routes and `route()` for generated links.
- Use Eloquent relationships and eager loading for list/detail screens.
- Validate request input in controllers or form requests.
- Keep business rules out of Blade.
- Use descriptive method and variable names.
- Use PHP return types and parameter types when editing PHP.
- Use factories for test data.
- Do not add dependencies without approval.
- Do not create new documentation unless the user asks for it.

## Verification

After PHP changes:

1. Run the most focused relevant PHPUnit test first, for example:
   `php artisan test --compact --filter=MultiLeagueBroadcastingTest`
2. Run the specific test file when appropriate:
   `php artisan test --compact tests/Feature/MultiLeagueBroadcastingTest.php`
3. Run Pint on changed PHP:
   `vendor/bin/pint --dirty --format agent`
4. Ask before running the entire suite unless the user already requested it.

Existing tests include:

- `tests/Feature/MultiLeagueBroadcastingTest.php`
- `tests/Feature/AdminUserPasswordResetTest.php`
- `tests/Feature/ExampleTest.php`
- `tests/Unit/ExampleTest.php`

## Safe AI Workflow

When making changes:

1. Read `AGENTS.md`, this file, and the relevant controller/model/view first.
2. If changing Laravel behavior, consult version-specific Laravel docs through available Boost/search-docs tools when they are exposed.
3. Inspect nearby files for local style before editing.
4. Keep changes narrow and aligned with the existing structure.
5. Prefer `php artisan make:* --no-interaction` for new Laravel classes.
6. Add or update focused tests for behavior changes.
7. Run targeted tests and Pint.
8. Summarize changed files, tests run, and any risks.

## Common Risk Areas

- Draft concurrency: preserve transactions and `lockForUpdate()` in pick/tick operations.
- League scoping: always filter teams, participants, rounds, and configs by league where relevant.
- Role boundaries: team users must not access other teams' activity or picks.
- File handling: participant and team uploads use the `public` disk.
- Realtime behavior: draft changes should broadcast `DraftTurnChanged`.
- Reset operations: league setup reset is destructive to draft progress for that league.

