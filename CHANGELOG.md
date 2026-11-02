# Changelog

All notable changes to this project will be documented in this file.

## [1.2.6] - 2026-11-02
### Added
- **Configurable Timezone**: `APP_TIMEZONE` setting (default UTC) applied to stats collection, seeding, and analytics; log parsing remains UTC.

## [1.2.5] - 2026-11-02
### Fixed
- **Analytics Graph**: Reverted to 48h window and made labels date-aware to avoid hour collisions and sparse graphs.

## [1.2.4] - 2026-11-02
### Added
- **Live Tail (per file)**: Toggle in log viewer polls for new lines every 3s using fingerprint change detection and prepends them.

## [1.2.3] - 2026-11-02
### Performance
- **GZ Decompression Cache**: Decompress `.gz` once to a temp file and reuse buffered backward reader (faster, lower memory).
- **Larger Read Chunks**: 64KB/256KB chunks for log reading and stats aggregation.
- **Shell-First Counting**: Prefer native `wc`/`grep`/`zcat` with PHP fallback.

## [1.2.2] - 2026-11-02
### Added
- **Click-to-Copy**: Clicking a log message in the viewer copies the raw backend value to the clipboard with visual feedback.

## [1.2.1] - 2026-11-02
### Fixed
- **Login Back Button**: "Back to login" now clears pending OTP state so the login form is shown.

## [1.2.0] - 2026-11-02
### Security
- **Session Fixation**: Regenerate session ID on login (`session_regenerate_id`).
- **XSS Hardening**: Escape username/role in layout and login error output.
- **Secret Rotation**: `AUTH_SECRET` no longer editable from web UI; CLI-only via `update_secret.php`.
- **Rate Limiting**: Login/OTP attempts throttled with temporary lockout after 5 failures.
- **SQL Identifiers**: Validate table/column names in `Database` to prevent injection.
- **Default Credentials**: Force admin password change on first login.

### Fixed
- **Analytics Window**: Query last 24 hours (was 48) to avoid label collisions.
- **Seeder Idempotency**: Use REPLACE mode for stats seeding.
- **Compressed Logs**: `collect_stats` and `LogReader` now scan `.gz` files.
- **Shell Dependencies**: PHP fallbacks for line counting and gz reading.
- **API Validation**: Whitelist log type and require filename in `api_logs`.
- **Multiline Tail**: Attach trailing unparsed buffer to oldest parsed entry.

## [1.1.0] - 2026-02-07
### Fixed
- **Stats Collection**: Resolved issue where stats collection failed silently on live servers due to missing `top_errors` column.
- **Database Resilience**: Implemented `INSERT OR REPLACE` logic to allow redundant stats collection runs without unique constraint errors.
- **Log Parsing**: Added support for glob patterns in log paths and improved regex for WordPress/PHP generic log formats.
- **Timezone Handling**: Expanded analytics query range and normalized timestamps to UTC for consistent cross-timezone reporting.

### Added
- **Release Notes**: Added a built-in changelog viewer to track application improvements.
- **Diagnostic Tools**: Improved error reporting in the CLI stats collector.

## [1.0.0] - 2026-02-01
- Initial release of Logger View.
