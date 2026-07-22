# Package validation

The generated 1.0.0 archives were checked in the build environment with the following steps:

- PHP syntax validation for every PHP file under `lib`, `templates` and `tests`.
- Standalone parser smoke suite covering quantities, ASCII/Unicode fractions, unit normalization, Italian durations, structured Italian recipe text, Schema.org JSON-LD normalization and fenced AI JSON.
- Strict TypeScript compilation of the dependency-free production interface.
- JavaScript syntax validation with Node.js.
- JSON parsing for Composer, npm and translation catalogues.
- XML parsing for `appinfo/info.xml` where an XML parser was available, plus PHP XML well-formedness fallback otherwise.
- YAML parsing for the OpenAPI document and GitHub Actions workflow.
- Verification that database table names respect the Nextcloud cross-database length constraint used by this project.
- ZIP integrity checks and verification that the installable archive has a single `smartcook/` root with `appinfo/info.xml`, compiled JavaScript and compiled CSS.
- SHA-256 checksums for both downloadable archives.

## Environment limitations

A live Nextcloud instance and database server were not available in the build environment, so installation migrations, route dispatch, background jobs and browser interaction were not executed end to end against a running server.

The npm registry was not reachable from the build environment. The Vue/Vite source is included in the source archive, but that specific bundle could not be rebuilt here. The installable archive instead contains the checked, dependency-free TypeScript interface compiled locally with the available TypeScript compiler.

Before production deployment, install the package on a staging Nextcloud instance matching the intended server/database combination and run the principal import, sharing, Files and background-job workflows.
