# Team Task Manager

Laravel 11 JSON API + Vue 3 / TypeScript / Vuetify 3 SPA with role-based task access.

- `backend/` — Laravel 11 (PHP 8.3, MySQL 8)
- `frontend/` — Vue 3 + TypeScript + Vuetify 3 (Vite)
- `docker-compose.yml` — mysql, backend, frontend

## Requirements

Docker with Docker Compose v2. Nothing else is needed on the host.

## Run

```bash
docker compose up --build
```

The first start installs Composer and npm dependencies, runs migrations and seeds the database, which takes a few minutes. On first start, wait until `docker compose logs -f backend` shows "Server running" before logging in. Then open:

- SPA: http://localhost:5173
- API (direct): http://localhost:8000/api

The containers run as UID/GID 1000 by default. If your host user differs, run `HOST_UID=$(id -u) HOST_GID=$(id -g) docker compose up --build` so generated files are owned by you.

Reset the database: `docker compose exec backend php artisan migrate:fresh --seed`

## Log in

| Role  | Email               | Password   |
|-------|---------------------|------------|
| admin | admin@example.com   | `password` |
| user  | user@example.com    | `password` |
| user  | jane@example.com    | `password` |

Each account starts with 10 seeded tasks.

## Tests

```bash
docker compose exec backend php artisan test                   # backend feature tests (SQLite in-memory)
docker compose run --rm --no-deps frontend npm run type-check  # frontend type check
```

## API

All routes are under `/api`, return JSON, and (except `csrf` / `login`) require an authenticated session.

| Method | Path | Notes |
|---|---|---|
| GET | `/api/csrf` | 204; sets the `XSRF-TOKEN` cookie |
| POST | `/api/login` | `{email, password}` → user; 422 on bad credentials; throttled 10/min |
| POST | `/api/logout` | 204 |
| GET | `/api/me` | current user `{id, name, email, role}` |
| GET | `/api/users` | `[{id, name}]` for the assignee select |
| GET | `/api/tasks` | paginated; query: `status`, `assigned_to`, `search` (title), `page`, `per_page` (≤100) |
| POST | `/api/tasks` | 201 |
| GET | `/api/tasks/{id}` | used by the edit form |
| PUT | `/api/tasks/{id}` | partial updates allowed |
| DELETE | `/api/tasks/{id}` | 204 |

Status codes: 200, 201, 204, 401 (no session), 419 (CSRF token missing/expired), 403 (not your task), 404, 422 (validation).

### Calling the API directly (curl)

State-changing requests (POST/PUT/DELETE, including login) need CSRF: call `GET /api/csrf` with a cookie jar, then send the URL-decoded `XSRF-TOKEN` cookie as the `X-XSRF-TOKEN` header. Without it you get 419.

```bash
curl -s -c jar -H 'Accept: application/json' http://localhost:8000/api/csrf
XSRF=$(awk '$6=="XSRF-TOKEN"{print $7}' jar | python3 -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')
curl -s -b jar -c jar -H 'Accept: application/json' -H "X-XSRF-TOKEN: $XSRF" \
  -H 'Content-Type: application/json' -d '{"email":"admin@example.com","password":"password"}' \
  http://localhost:8000/api/login
curl -s -b jar -H 'Accept: application/json' http://localhost:8000/api/me
```

## Decisions

| Topic | Choice | Why |
|---|---|---|
| Auth | **Laravel session auth** (`web` guard), no Sanctum | Framework-native; the session cookie is HttpOnly so no token is exposed to JS; CSRF protection stays on. API routes are registered with the `web` middleware group under `/api` (`bootstrap/app.php`). The Vite dev server proxies `/api` to Laravel so the SPA and API share one origin, which avoids CORS and third-party cookie issues. |
| Delete | **Hard delete** | No restore requirement. |
| Task form | **Dialog** on `/tasks` (`TaskForm.vue`, reused for create and edit) | Keeps the list context; matches the two-route spec. |
| Pagination | **Server-side** (`paginate()` + `v-data-table-server`) | Scales with data size; filters and search run in SQL. |
| `user` create ownership | **Forced to self** | For non-admins the server ignores any submitted `assigned_to` and uses their own id, on create and update. The UI disables the assignee select for them. |

Authorization is `TaskPolicy` (admins pass via `before()`; users only own tasks) and the list query scope in `TaskController@index`. Validation uses Form Requests (`StoreTaskRequest`, `UpdateTaskRequest`, `IndexTaskRequest`, `LoginRequest`). `role` and `status` are PHP enums, enforced by DB enum columns and `Rule::enum`.

## Assumptions / simplifications

- Users can't reassign tasks; only admins choose an assignee.
- `GET /api/users` is available to every authenticated user (id + name only).
- No registration, password reset or user management UI; accounts come from the seeder.
- Title search is a simple `LIKE %term%` substring match.
- Deleting a user cascades to their tasks (FK `cascadeOnDelete`); there is no UI for it.
- The Docker setup is for local development (`php artisan serve`, Vite dev server), not production.
- Laravel 11 is pinned per the requirements; Composer currently reports security advisories against the 11.x line. The committed composer.lock installs fine; running composer update/require needs COMPOSER_NO_SECURITY_BLOCKING=1. Upgrading to Laravel 12 is the real fix.
- The task form disables Save if loading an existing task fails, so a failed load can never overwrite a task with blank defaults.

## What I'd do next

- Sortable columns (server-side `sort` param) and an assignee filter in the UI for admins.
- Frontend unit tests (Vitest + Vue Test Utils) for `TaskForm` and the auth store.
- A production build: Nginx serving `frontend/dist` and proxying `/api` to PHP-FPM, with secure cookies over HTTPS.
- Handle session expiry and CSRF mismatch (419) with an automatic re-prime and retry.
- Answers to the written questions: see [ANSWERS.md](ANSWERS.md).
