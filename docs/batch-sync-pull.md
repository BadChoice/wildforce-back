# Batch sync pull

## Objective

Replace the many flat-resource pull requests made when the app opens with one paginated batch endpoint. Keep the existing individual endpoints, plus the graph endpoints for workout days and nutrition plans, unchanged.

## Scope

The batch endpoint includes flat resources only. `workout-days` and `nutrition-plans` remain separate because each returns and applies a complete graph.

The app must include only the flat resources it currently syncs, including users, settings, preferences, locations, body metric entries, body-progress-photo sessions, exercise and nutrition profiles, workout plans, nutrition-log entries, and nutrition-log items. Photo binaries and media records are explicitly out of scope.

## API

Add `POST /api/sync/pull/batch`, behind the same authentication, access, and throttling middleware as the current sync endpoints.

First request:

```json
{
  "resources": [
    { "resource": "body-metric-entries", "updated_after": "2026-09-30T08:00:00Z" },
    { "resource": "nutrition-log-entries", "updated_after": null }
  ]
}
```

Later requests send only the opaque `cursor` returned by the previous response.

```json
{ "cursor": "..." }
```

Response:

```json
{
  "data": [
    { "resource": "body-metric-entries", "record": { "id": "..." } },
    { "resource": "nutrition-log-entries", "record": { "id": "..." } }
  ],
  "meta": {
    "as_of": "2026-09-30T08:30:00.000000Z",
    "next_cursor": "..."
  }
}
```

Each page has a fixed maximum number of records. The final page returns `next_cursor: null`.

## Ordering and cursor

At the first request, the backend fixes `as_of = now()`. Every page queries only records with `updated_at <= as_of`.

Merge records globally by `(updated_at, resource, id)`. This tuple, not only a timestamp, is the stable position used for pagination. The cursor must be encrypted/signed, bound to the authenticated user, and expire after a short lifetime (15–30 minutes).

The cursor stores `as_of`, the requested resources and their initial watermarks, and the latest emitted tuple for each resource. Do not store server-side sync sessions unless a later scaling need requires it.

When parent and child rows have equal timestamps, emit `nutrition-log-entries` before `nutrition-log-items` so the app can attach an item to its local parent.

## Backend implementation

1. Add `batchPullResources()` to `SyncRegistry`. Exclude `workout-days`, `workout-blocks`, `planned-exercises`, `exercise-results`, `nutrition-plans`, `nutrition-days`, and `nutrition-meals`.
2. Add a form request that validates the initial resource list and an opaque cursor request.
3. Add `BatchSyncPullController` and a dedicated service. Reuse model ownership filtering, soft-deleted tombstones, and `syncPayload()` from the existing sync implementation.
4. For every requested resource, query from its own `(updated_at, id)` checkpoint, fetch enough candidates for one page, merge them in PHP, and advance only checkpoints for emitted records.
5. Add API tests for authentication, invalid resources/cursors, user isolation, tombstones, ordering, `as_of`, multi-page resume, and unchanged individual endpoints.

## iOS implementation

1. Add a batch-pull API method and Codable request/response types.
2. Extend `SyncState` with a pending batch cursor and per-resource tuple watermarks. Persist after each successfully applied page.
3. At app launch, use batch pull for flat resources. Keep `WorkoutDayGraphSyncResource` and `NutritionPlanGraphSyncResource` on their current endpoints.
4. Dispatch every returned record to its existing local apply path. Apply nutrition-log entries before nutrition-log items.
5. If a cursor expires or is rejected, clear the pending cursor and restart from the already persisted per-resource watermarks; do not discard locally applied pages.
6. Add tests for an initial multi-page pull, interruption/resume, mixed-resource ordering, and nutrition-log parent/item application.
