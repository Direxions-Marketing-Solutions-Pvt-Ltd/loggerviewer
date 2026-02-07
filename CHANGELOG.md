# Changelog

All notable changes to this project will be documented in this file.

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
