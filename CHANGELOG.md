# Changelog

## 1.0.1 - 2026-07-19

- Fixed HTTP 412 responses on authenticated read-only API requests in Nextcloud 34.
- Marked all read-only GET routes as `NoCSRFRequired`; authentication and per-user authorization remain enforced.
- Kept CSRF protection enabled for POST, PUT and DELETE operations.

## 1.0.0 - 2026-07-19

Initial complete project package.

- Structured multilingual recipe library with ingredients, steps, tools, tags, categories, nutrition and revision history.
- Search, filtering, favorites, statistics, duplicate detection and guided merge.
- URL/text/HTML/Markdown/JSON/file import with Schema.org extraction and deterministic multilingual parsing.
- Optional OCR adapters and AI providers for Nextcloud Task Processing, OpenAI-compatible APIs, Anthropic and Gemini.
- Meal planner, aggregated shopping lists, Files attachments, sharing and public links.
- Nextcloud unified search, notifications, queued imports and cleanup jobs.
- JSON-LD, Markdown and printable HTML exports.
- English and Italian localization.
- Vue/Vite development interface plus a precompiled dependency-free production interface.
- Security hardening for URL imports, public-link passwords, provider secrets and active-content attachments.
- Parser and build smoke tests, CI configuration and install/source ZIP packaging.
