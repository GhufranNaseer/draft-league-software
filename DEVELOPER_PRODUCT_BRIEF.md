# Developer Product Brief

## Product
Participant Management and Live Draft System

## Objective
Deliver a role-based platform for participant intake, admin moderation, and real-time league drafting with strong operational control.

## Core Domains
- Participant lifecycle management
- Team and league configuration
- Draft round orchestration
- Real-time draft state distribution
- Role-based access and governance

## Primary Roles and Boundaries
- Public user: submit form and track status
- Authenticated team user: team dashboard and drafting actions
- Admin user: full management of participants, categories, teams, rounds, league config, and users

## Current Technology Stack
- Backend: PHP 8.2, Laravel 12
- Interactive UI: Livewire 4, Blade
- Styling and build: Tailwind CSS 4, Vite 7
- Realtime: Laravel Reverb, Laravel Echo, Pusher protocol
- Auth and permissions: Sanctum, Spatie Laravel Permission
- Testing and quality: PHPUnit 11, Pint

## Key Application Areas
- Routes and role segmentation in routes/web.php
- Draft orchestration in app/Services/DraftService.php
- Team draft live component in app/Livewire/TeamDraftDashboard.php
- Draft board projection in app/Livewire/DraftBoard.php
- Domain entities in app/Models

## Functional Flow
1. Public submission enters participant pipeline.
2. Admin validates and transitions status.
3. League rounds and constraints are configured.
4. Active round drives pick order and timed turns.
5. DraftService handles pick and tick state changes.
6. Broadcast events refresh livewire components.
7. Round closes and next configuration is applied.

## Operational Expectations for Developers
- Preserve role and middleware boundaries.
- Keep draft rules centralized in service layer.
- Avoid business logic drift into Blade templates.
- Use Eloquent relations and eager loading to prevent N+1 issues.
- Keep league type scoping explicit across queries.

## Data Model Focus
- Participant: lifecycle, category, team assignment
- Team: league scoping and participant ownership
- DraftRound: status, pick order, timing, current turn
- DraftPick: immutable pick history and activity feed
- LeagueRoundConfig: per-round constraints and sequence

## Quality and Delivery Checklist
- Add or update focused feature tests for changed behavior.
- Run targeted tests before broad suite execution.
- Format changed PHP files with Pint.
- Verify realtime events for turn changes in local environment.
- Confirm dashboard behavior across admin and team roles.

## Non-Functional Priorities
- Correctness of draft transitions
- Concurrency safety on pick operations
- Clear auditability of actions
- Predictable performance at round peak activity

## Next Technical Improvements
- Add stronger domain-level validation around round transitions.
- Expand integration tests for concurrent picks and timeout ticks.
- Introduce explicit observability for draft timing and event latency.
