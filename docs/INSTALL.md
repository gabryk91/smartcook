# Installation and configuration

## Production installation

1. Extract `smartcook-1.0.0-nextcloud.zip` into the Nextcloud custom-apps directory.
2. Confirm the resulting path is exactly `custom_apps/smartcook/appinfo/info.xml`.
3. Give the web-server user read access to the application directory.
4. From the Nextcloud root, enable the app:

```bash
php occ app:enable smartcook
```

5. Open SmartCook once. Nextcloud applies the migration and creates the app-specific tables in the existing Nextcloud database.
6. Configure optional AI and OCR providers in **SmartCook -> Settings**.

The installable archive includes compiled JavaScript and CSS. It does not require Node.js, npm or Composer on the production server.

## Compatibility target

- Nextcloud 31-34.
- PHP 8.1 or newer and the normal Nextcloud PHP extensions, including DOM and mbstring.
- MariaDB/MySQL, PostgreSQL or SQLite.

Always test the app against a staging copy before deploying it to an important instance.

## Files and storage

By default SmartCook stores attachments in a SmartCook directory inside the recipe owner's Nextcloud Files area. The path is configurable per user. Database records contain structured recipe data and file references; uploaded file bodies are not stored as database blobs.

## Local OCR and PDF extraction

Local document extraction is disabled by default. To enable it, install:

- `tesseract` and the required language packs, for example Italian and English;
- `pdftotext`, normally supplied by Poppler.

Configure the executable names/paths and the OCR language expression, such as `ita+eng`. SmartCook invokes commands with an argument array and bypasses shell interpolation.

## External document extractor

The endpoint receives a multipart POST with:

- `file`: uploaded binary;
- `mimeType`: detected/declared MIME type;
- `language`: configured OCR language expression.

Expected response:

```json
{
  "text": "extracted document text",
  "confidence": 0.95
}
```

Use HTTPS for remote extractors. A local endpoint must also be allowed by the Nextcloud HTTP-client policy configured by the instance administrator.

## AI providers

### Disabled

This is the default. All imports use JSON-LD and deterministic parsing only.

### Nextcloud Assistant

Uses the `core:text2text` Task Processing provider already configured for the Nextcloud instance. No duplicate API key is stored by SmartCook.

### OpenAI-compatible

Supports a configurable Chat Completions endpoint for OpenAI, OpenRouter, Ollama, LocalAI, Mistral or another compatible service. Configure the base endpoint, model and API key where required.

### Anthropic

Uses the Messages API. Configure the endpoint, model and API key.

### Gemini

Uses the `generateContent` API. Configure the endpoint/model and API key.

SmartCook does not hard-code external model names because provider catalogues change independently of the app. Recipe data is sent outside the Nextcloud instance only when the user explicitly requests an AI-assisted import or transformation.

## Background jobs

Queued imports and cleanup rely on the normal Nextcloud background-job system. For reliable processing, configure the instance to run `cron.php` using system cron rather than AJAX mode.

## Frontend maintenance

The frontend is maintained directly in `js/smartcook-main.js`. Packaging uses the checked-in JavaScript and does not run npm, Vite or another frontend build step.
