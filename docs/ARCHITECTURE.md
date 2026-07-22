# Architecture

SmartCook is a Nextcloud App Framework application using attribute routes, dependency injection, migrations and the cross-database query builder.

## Runtime layers

- `Controller`: authenticated and public HTTP endpoints. `BaseController` normalizes JSON errors.
- `Db`: repositories and transaction boundaries for Nextcloud's configured database.
- `Service`: access control, recipe validation, planner, shopping, sharing, exports and file storage.
- `Service/Import`: interchangeable importers for URL, free text, HTML, Markdown, JSON and uploaded files.
- `Service/AI`: a common provider contract for Nextcloud Task Processing and optional external APIs.
- `Service/Ocr`: local and HTTP document-text extraction providers.
- `Search`: unified Nextcloud search provider.
- `Notification`: import completion/failure notifications.
- `BackgroundJob`: queued imports and retention cleanup.
- `src`: Vue 3 and TypeScript development interface.
- `src-standalone`: dependency-free TypeScript compatibility interface compiled into the supplied release.

The production templates load only `js/smartcook-main.js` and `css/smartcook-main.css`. No Node.js process runs on the Nextcloud server.

## Import pipeline

1. Validate source type, request size and current user.
2. Fetch/convert the source using guarded adapters.
3. Prefer Schema.org `Recipe` JSON-LD when present.
4. Extract readable text and run multilingual deterministic parsing.
5. Optionally ask the configured AI provider for a strict JSON object.
6. Normalize ingredient names, quantities, units, times, steps, tools, tags and categories.
7. Search for likely duplicates.
8. Return an editable preview.
9. Save only after explicit user confirmation.

The AI layer is deliberately secondary: normal web pages that expose Schema.org metadata do not require model inference.

## Data model and ownership

Every recipe has one owner. Access may additionally be granted to Nextcloud users, groups or a public-link token. Read/update checks are centralized in `RecipeAccessService`.

Structured recipe data lives in app-specific tables in the same database as Nextcloud. Attachments live under a configurable directory in the owner's Nextcloud Files storage; database rows store paths and metadata rather than file blobs.

Recipe updates run in transactions and create immutable revision snapshots. Child collections are synchronized atomically with the parent recipe.

## Extension points

- Implement `ImporterInterface` and register the importer in `ImportManager`.
- Implement `AiProviderInterface` and add it to `AiProviderRegistry`.
- Implement `DocumentTextExtractorInterface` and add it to `DocumentTextExtractorRegistry`.
- Add export serializers to `ExportService` and expose the format in `ExportController`.

## Privacy boundaries

Deterministic parsing, database access and local file operations stay inside Nextcloud. Recipe content leaves the instance only when a user explicitly enables and invokes an external AI/OCR provider or imports a public URL.
