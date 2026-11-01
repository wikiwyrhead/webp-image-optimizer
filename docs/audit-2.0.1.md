# WebP Image Optimizer audit — 2026-11-01

Baseline: main at ae004f5 (published v2.0.0). Reviewed all plugin PHP, JavaScript, CSS, uninstall behavior, settings, upload and bulk paths, AI requests, and existing release assets.

## Confirmed findings addressed

| Severity | Finding | Resolution |
| --- | --- | --- |
| High | Cleanup guesses every extension and thumbnail pattern, deleting unrelated same-basename attachments | Delegate active image cleanup to WordPress; delete only explicitly tracked plugin sources, inside uploads, excluding another attachment's active file |
| High | Bulk conversion deletes the source when retention is disabled, breaking embedded old URLs | Preserve and track old sources and registered thumbnails for bulk conversion |
| High | Bulk metadata/post failures ignored after switching attachment path | Check failures and restore old path, MIME and metadata; preserve source |
| Medium | Upload savings stored only in an upload-array field WordPress does not persist | Capture upload result and save postmeta on metadata generation, including scaled uploads |
| Medium | Retained upload originals not explicitly recorded | Record ownership for future cleanup; no guessing for legacy files |
| Medium | Unchecking every allowed format restores defaults | Save and honor an empty list |
| Medium | GD flattens animated GIFs; bulk Imagick errors escape | Skip GIFs without frame-aware detection, preserve multi-frame input, catch conversion errors |
| Medium | EXIF stripped before orientation applied | Orient first, then encode |
| Medium | Engine detection assumes every installed engine can encode WebP; HEIC display label never matches checkbox | Query supported formats and list HEIC/HEIF separately |
| Medium | Saved key cannot be removed and can be sent to a newly selected provider | Explicit removal checkbox; only reuse saved key for unchanged provider |
| Medium | Reasoning fallback may publish analysis or invented text | Require final response content |
| Medium | Filename-alt toggle unintentionally disables automatic AI alt | Independent AI toggle and optional filename fallback |
| Medium | Unconditional mbstring calls can crash sites without mbstring | Unicode-safe splitting without extension dependency |
| Low | Successful IDs remain in bulk queue; Stop shows 100%; attachment alt selector misses inputs | Remove completed IDs, preserve actual progress, fix field selector |
| Low | No-engine badge checks wrong case; Media Library button visible without handler permission | Correct engine badge and match button to AJAX capability |
| Low | WordPress 5.0 advertised despite WebP dependency | Require WordPress 5.8 |

## Verification

`tests/regression.php` uses real GD/ImageMagick encoders and isolated doubles for WordPress storage and HTTP. It tests success and failure behavior; it is not a complete WordPress integration suite. Run `php tests/regression.php` and `php -n -d extension=gd tests/regression.php` with GD installed; the first also exercises ImageMagick when loaded. PHP/JS syntax checks are required. CI repeats these on PHP 7.0, 8.3, and 8.4 before publishing a version bump merged into main.

No production site was accessed or modified. No paid AI requests were made. Browser UI, actual WordPress database behavior, multisite, offloaded media, and provider authentication still need staging verification. Rollback to the previous plugin release changes code only; converting media is a separate data operation. Back up uploads and database before a large migration.

## Remaining improvements

1. Add a full WordPress integration suite for upload/sideload/REST, thumbnails, metadata failure, multisite, and attachment deletion. Add a browser test for the Media Library and bulk controls.
2. Refresh provider model defaults against each provider's current catalog. Gemini 2.0 Flash and Groq Llama 3.2 vision defaults are retired. Custom model overrides remain available; provider availability is not guaranteed by the connection test. See official https://ai.google.dev/gemini-api/docs/deprecations and https://console.groq.com/docs/deprecations . A follow-up should consolidate duplicated model configuration and test a small image, not a text greeting.
3. Add server-side bulk job locks and paginated/resumable queues for large libraries. Current jobs require the browser tab to stay open and should run in one tab at a time.
4. Clarify that recorded bytes saved compares source and encoded main-image sizes; keeping originals and thumbnails may increase actual disk usage. Historical upload savings cannot be reconstructed reliably.
5. Add a reversible migration manifest/restore interface. Preserve legacy untracked files rather than attempting risky automatic cleanup. Failed metadata generation can leave generated thumbnails; a future cleanup tool should use a verified manifest.
6. Generate a small supported image for AI instead of loading the full source, expose safe error details, add rate-limit backoff, and avoid blocking uploads on a remote request. Some formats (TIFF/HEIC/SVG) are not accepted by every vision provider.
7. Complete modal focus trapping/return, localization of JS strings, and per-site uninstall cleanup for cached stats. Keep retained-file ownership metadata on uninstall so reinstall can still clean up correctly.

## Release process

The workflow runs tests on pull requests and main. Only a version change on main triggers a release, after all jobs pass. It creates a plugin-root ZIP without development files, includes a SHA256 checksum, tags the exact tested commit, and publishes the GitHub release with `docs/release-notes.md`. It does not deploy to WordPress sites or WordPress.org.
