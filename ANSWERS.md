# Written answers

## 10.1 Performance — `GET /api/tasks` with 1,000,000+ tasks

1. **Keyset (cursor) pagination.** `OFFSET` gets slower the deeper you page. Use `cursorPaginate()` ordered by `(created_at, id)` so each page is an index seek.
2. **Avoid `COUNT(*)` on every request.** `paginate()` runs an extra count. Use `simplePaginate()` or `cursorPaginate()`, or cache or approximate the total.
3. **Composite indexes that match the queries.** For example, `(assigned_to, status, created_at)` for the common "my tasks by status, newest first" query and `(status, created_at)` for admins. Check them with `EXPLAIN`.
4. **Real search instead of `LIKE '%term%'`.** A leading wildcard can't use a B-tree index. Use a MySQL `FULLTEXT` index on `title` (`whereFullText`), or Meilisearch/Elasticsearch via Laravel Scout.
5. **Eager loading and selecting only needed columns.** `with('assignee:id,name')` avoids N+1 queries. Select only the columns the list shows, and leave out large `description` text.
6. **Cap and validate the page size.** `per_page` is already limited to 100. Reject unbounded queries and unknown filters.
7. **Caching.** Cache rarely-changing lookups such as the users list, and hot list pages per user and filter with short TTLs or tag-based invalidation on task writes. Use Redis for cache and sessions instead of the database.
8. **Scale the database.** Send list reads to read replicas (Laravel `read`/`write` connections). Archive or partition old `done` tasks, for example by year.
9. **Monitor.** Watch the slow query log, Laravel Telescope/Pulse and APM, and load-test with realistic data before and after changes.
10. **HTTP-level savings.** Use gzip/brotli compression and ETags or conditional requests for unchanged pages.

## 10.1 Security — Laravel API + Vue SPA

- **SQL injection:** Eloquent and the query builder bind all parameters. Never interpolate input into `DB::raw` / `whereRaw`. Validate every filter (`IndexTaskRequest`).
- **XSS:** Vue escapes interpolated `{{ }}` output. Never use `v-html` with user content. Add a strict Content-Security-Policy header.
- **CSRF:** session auth keeps Laravel's CSRF middleware on. The SPA gets an `XSRF-TOKEN` cookie and axios sends it back as `X-XSRF-TOKEN`. Session cookies are `SameSite=Lax`.
- **Session and token handling:** the session cookie is `HttpOnly`, so JS can't read it, and there's no bearer token in `localStorage`. Regenerate the session on login and invalidate it on logout. Set `SESSION_SECURE_COOKIE=true` and short lifetimes in production.
- **HTTPS everywhere:** TLS termination plus HSTS, so cookies and credentials never cross the network in plaintext.
- **Authentication hardening:** throttle login (already 10/min), hash passwords with bcrypt/argon2, and use generic error messages.
- **Authorization on the server:** policies for every task action, and list queries scoped to the user. The UI hiding a button is never the security boundary.
- **Mass assignment and validation:** explicit `$fillable`, Form Requests, and only `validated()` data reaches models. Ownership fields like `assigned_to` are set by the server for non-admins.
- **Configuration:** `APP_DEBUG=false` in production, secrets only in `.env` or a secret manager, and CORS locked to known origins if the SPA is ever served from another domain.
- **Dependencies:** keep them patched with `composer audit` / `npm audit`.

## 10.2 TypeScript / Vue

### Benefits of TypeScript in a Vue 3 SPA

- **API contracts in one place.** `Task`, `User` and `Paginated<T>` in `src/types` describe the backend's JSON. If a field is renamed, the compiler points to every place that uses it.
- **Typed props, emits and templates.** `defineProps<…>()` and `defineEmits<…>()` are checked by `vue-tsc`, including inside templates, so wrong prop types or event payloads fail at build time instead of at runtime.
- **Safer refactoring and better editor support.** Autocomplete, go-to-definition and rename work across components, stores and the API layer.
- **Fewer runtime bugs.** Literal unions such as `'todo' | 'in_progress' | 'done'` and `Record<TaskStatus, …>` make impossible states unrepresentable and ensure every case is handled.
- **Self-documenting code.** Types act as always-up-to-date documentation for new team members.

### Example: `<script setup lang="ts">` with strongly typed props

A self-contained version of `frontend/src/components/StatusChip.vue`, with an extra optional prop to show `withDefaults`:

```vue
<script setup lang="ts">
import { computed } from 'vue'

type TaskStatus = 'todo' | 'in_progress' | 'done'

const props = withDefaults(
  defineProps<{
    status: TaskStatus
    size?: 'x-small' | 'small' | 'default'
  }>(),
  { size: 'small' },
)

const COLORS: Record<TaskStatus, string> = {
  todo: 'grey',
  in_progress: 'blue',
  done: 'green',
}

const LABELS: Record<TaskStatus, string> = {
  todo: 'To do',
  in_progress: 'In progress',
  done: 'Done',
}

const color = computed(() => COLORS[props.status])
const label = computed(() => LABELS[props.status])
</script>

<template>
  <v-chip :color="color" :size="size" variant="flat">{{ label }}</v-chip>
</template>
```

`<StatusChip status="archived" />` is a compile-time error, and adding a new status to the union forces `COLORS` and `LABELS` to be updated.
