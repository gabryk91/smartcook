# Security model

## Authentication and authorization

- Private endpoints require a Nextcloud session and use the standard CSRF request token for mutations.
- Recipe authorization is centralized in `RecipeAccessService`.
- Owners can read, update, share and delete their recipes.
- User/group shares grant read or read+update permissions; only the owner can manage shares.
- Public shares use high-entropy tokens, optional expiry and `password_hash` / `password_verify`.
- Public-link passwords are submitted in a POST body rather than a query string.

## Imported URLs and SSRF protection

- Only HTTP and HTTPS URLs are accepted.
- Credentials embedded in URLs are rejected.
- DNS results are resolved and private, loopback, link-local, multicast and reserved addresses are rejected.
- Redirects are followed manually and every destination is validated again.
- Request timeouts, redirect limits and maximum response sizes are enforced.
- Fetching uses the Nextcloud HTTP client so instance-level outbound-network policy still applies.

DNS rebinding cannot be eliminated solely at application level; production deployments should also restrict outbound network access at the host or container boundary.

## Uploaded files and attachments

- File size and MIME constraints are checked before parsing.
- Files are stored through the Nextcloud Files API under the owning user's storage.
- Media downloads require recipe authorization.
- HTML, SVG and unknown active-content types are forced to download as `application/octet-stream`.
- Only allowlisted image, audio, video and PDF types can be displayed inline.
- Responses use `X-Content-Type-Options: nosniff`; inline media also receives a restrictive sandbox content-security policy.
- OCR tools are invoked without shell interpolation.

## AI and external OCR

- AI is disabled by default.
- External processing happens only after explicit user configuration and invocation.
- API secrets are encrypted with Nextcloud's crypto service before storage.
- Secret values are never returned by settings endpoints; only presence flags are exposed.
- Saving a blank secret preserves the existing value unless the user explicitly requests deletion.
- Provider responses are size-limited and must decode into the expected JSON structure.

## Public data minimization

The public recipe response removes owner identifiers, UUIDs, revision data, internal storage paths and private media metadata. Internal `media:<id>` cover references are not exposed through unauthenticated links.

## Input and output handling

- Recipe inputs are validated and length-bounded before persistence.
- Database access uses the Nextcloud query builder and bound parameters.
- JSON is encoded with the PHP JSON API; rendered values are escaped by templates or frontend helpers.
- Import previews are not persisted until the user explicitly saves them.

## Recommended deployment controls

- Keep Nextcloud and PHP security updates current.
- Use HTTPS and a trusted reverse-proxy configuration.
- Run Nextcloud background jobs via system cron.
- Restrict outbound network access to approved AI/OCR/recipe hosts where practical.
- Prefer Nextcloud Assistant or a local provider for sensitive recipe content.
- Back up both the Nextcloud database and user Files storage.
- Test upgrades in staging and review `nextcloud.log` after deployment.
