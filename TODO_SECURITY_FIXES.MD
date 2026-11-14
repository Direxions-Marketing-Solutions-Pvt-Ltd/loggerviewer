# Security & Quality Fixes TODO

Source: full repository audit (OWASP-focused). Fix items one by one and mark `[x]` when done.
Reference items by their ID (e.g., "fix SEC-003").

## Must fix

- [x] SEC-001. **Secrets and database downloadable from web root** — `.env`, `data/database.sqlite` exposed with no access control. Add `.htaccess`/server config denying `.env`, `data/`, `src/`, `*.sqlite`; move `data/` outside web root if possible. (`index.php`, repo root)
- [x] SEC-002. **Brute-force lockout is per-session (ineffective) + DoS via usleep** — `src/Auth.php:136-174`. Store attempt counts by username+IP in DB/Redis with 15-min TTL; remove/cap sleeps.
- [x] SEC-003. **Unauthenticated encryption (CBC, no MAC)** — `src/Encryption.php`. Switch to AES-256-GCM with random IV + auth tag; re-encrypt on rotation.
- [x] SEC-004. **Decrypted secrets rendered in settings HTML** — `src/views/settings.php:169,214`. Empty password fields; only update when new value submitted.
- [x] SEC-005. **Content-Disposition filename not escaped** — `index.php:197`. Use `rawurlencode(basename($filePath))`.
- [x] SEC-006. **Newline injection in `.env` writer** — `src/ConfigManager.php:43-69`. Reject/strip `\r`/`\n` in keys and values.

## Should fix

- [x] SEC-007. **No security headers (CSP, X-Frame-Options, HSTS)** — app-wide; emit in `index.php` or `.htaccess`.
- [x] SEC-008. **`Database::query` crashes if connection failed** — `src/Database.php:34-44`. Return false when `$pdo === null`.
- [x] SEC-009. **`Cache::get` unserializes Redis data (object injection)** — `src/Cache.php:35-37`. Use JSON or signed serialization.
- [x] SEC-010. **`Auth::hasAccess` opens a new Database per call** — `src/Auth.php:127`. Reuse existing connection.
- [ ] SEC-011. **`LogReader::getTotalLines` runs shell commands per request** — `src/LogReader.php:644-687`. Cache counts briefly or compute on demand.
- [ ] SEC-012. **`Project::setUserProjects` not transactional** — `src/Project.php:73-80`. Wrap in transaction.
- [x] SEC-013. **Role values not validated server-side** — `src/User.php:34,84`. Whitelist `['user','admin']`.
- [x] SEC-014. **`Auth::logout()` does not clear session data** — `src/Auth.php:96-99`. Unset session + destroy + regenerate id.

## Nice to have

- [x] SEC-015. **`openssl_random_pseudo_bytes` → `random_bytes`** — `src/Encryption.php:13`, `src/scripts/update_secret.php:43`.
- [x] SEC-016. **Remove `usleep` delays in login** — `src/Auth.php:60,65` (after server-side throttling exists).
- [ ] SEC-017. **AI sends raw error text to third party** — `src/AI.php:39`. Add opt-in + redaction.

## Lean / cleanup

- [x] SEC-018. **Remove per-call Database instantiation in `Auth::hasAccess`**.
- [x] SEC-019. **Consolidate encryption helpers** (remove duplicated logic in `update_secret.php`).
- [ ] SEC-020. **Drop `usleep` delays once throttling is server-side.**

## Notes
- No automated tests exist; verify manually after each fix.
- Mark items `[x]` as completed.