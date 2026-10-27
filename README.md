<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

# KTPL Drafting Software — CI/CD & Deployment Guide

## 1. Architecture Overview

The KTPL Drafting Software uses a hybrid Docker container architecture on the production VPS (`184.168.125.171`):

- **Host Nginx:** Terminates public HTTPS traffic on port `8443` with Let's Encrypt certificates, proxying requests to `127.0.0.1:8007`.
- **Containerized Nginx (`drafting_v2-nginx-1`):** Serves static files, routes `/app/` WebSocket traffic to Reverb on `http://reverb:8080`, and proxies PHP traffic to `app:9000`.
- **Laravel PHP-FPM (`drafting_v2-app-1`):** Executes Laravel application code mounted from `/var/www/drafting_v2`.
- **Background Queue (`drafting_v2-queue-1`):** Processes broadcasting and asynchronous jobs using the database queue driver.
- **Laravel Reverb (`drafting_v2-reverb-1`):** Authoritative WebSocket server handling real-time draft events and timer synchronization.
- **MariaDB 11.4 (`drafting_v2-database-1`):** Relational database with persistent named volume `drafting_v2_database-data`.

---

## 2. CI/CD Workflow Architecture

```
Developer Local Workspace (Source of Truth)
   │
   ├─► Feature Branch: feature/xyz
   │     ├─► pint (PSR-12 code style)
   │     ├─► php artisan test (420+ assertions)
   │     └─► npm run build (Vite assets)
   │
   ├─► Push & Pull Request to GitHub (`main`)
   │     └─► GitHub Actions CI (.github/workflows/ci.yml)
   │           ├─ PHP 8.2 setup & Composer install
   │           ├─ Pint format validation
   │           ├─ Feature & Unit test suite
   │           └─ Node 22 build & manifest verification
   │
   └─► PR Review & Merge to `main`
         └─► GitHub Actions Deploy (.github/workflows/deploy-production.yml)
               ├─ Environment Protection & Approval Gate
               ├─ Asset compilation & rsync to VPS
               ├─ Secure SSH trigger of /var/www/drafting_v2/scripts/deploy-production.sh
               ├─ Pre-deployment MariaDB backup dump
               ├─ Git commit checkout
               ├─ Laravel cache rebuild (optimize, config, route, view)
               ├─ Container reload (queue, reverb, app, nginx)
               └─ Post-deployment health verification (HTTP 200, Reverb, Containers)
```

---

## 3. Required GitHub Secrets

Configure the following secrets in the GitHub repository or the `production` environment:

| Secret Name | Description | Example / Format |
|---|---|---|
| `VPS_HOST` | Production server IP address or domain | `184.168.125.171` |
| `VPS_PORT` | SSH daemon listening port | `22` |
| `VPS_USER` | Dedicated deployment user | `kamran` |
| `VPS_SSH_KEY` | Ed25519 deployment private key | `-----BEGIN OPENSSH PRIVATE KEY----- ...` |
| `VPS_KNOWN_HOSTS` | VPS host public key fingerprint | `184.168.125.171 ssh-ed25519 AAAAC3...` |

---

## 4. SSH Key Setup & Verification

A dedicated Ed25519 key pair is installed on the VPS at `/home/kamran/.ssh/github_actions_prod`:

1. The public key is registered in `/home/kamran/.ssh/authorized_keys` with permissions `0600`.
2. The user `kamran` belongs to the `docker` group (`gid=998`) allowing container management without sudo passwords.
3. Strict host key checking is enforced in CI using the verified host key fingerprint stored in `VPS_KNOWN_HOSTS`.

---

## 5. Deployment & Rollback Scripts

Deployment operations are encapsulated in version-controlled scripts on the VPS:

### Manual Deployment
```bash
/var/www/drafting_v2/scripts/deploy-production.sh [COMMIT_SHA]
```
This script:
1. Validates working tree and tools.
2. Creates an uncompressed MariaDB backup in `/var/backups/drafting_v2/` with mode `0600`.
3. Checks out the exact target Git commit.
4. Validates `public/build/manifest.json`.
5. Rebuilds Laravel caches (`config:cache`, `route:cache`, `view:cache`).
6. Restarts worker and WebSocket containers.
7. Verifies health checks across local Nginx, host HTTPS, and Reverb.

### Manual Rollback
```bash
/var/www/drafting_v2/scripts/rollback-production.sh [COMMIT_SHA_OR_REF]
```
Reverts the working tree to the previous known-good commit, recompiles caches, and reloads services.
