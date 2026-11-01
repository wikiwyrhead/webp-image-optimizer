WebP Image Optimizer 2.0.1 fixes file cleanup, bulk conversion, upload tracking, and alt text handling.

- Preserve unrelated images when deleting an attachment. Cleanup now uses explicitly tracked source files instead of filename guesses.
- Preserve existing source files and thumbnail URLs during bulk conversion, including when retention is disabled for new uploads.
- Restore the attachment path, MIME type, and metadata when a bulk attachment update fails.
- Record savings for new uploads and track retained source files.
- Honor an empty selection of allowed image formats.
- Preserve GIF animation on GD-only servers; handle corrupt images without uncaught conversion errors.
- Apply image orientation before stripping metadata; detect actual engine format support.
- Allow removing a saved API key and prevent reusing a saved key with a different provider.
- Run upload AI alt text independently of filename fallback. Never publish model reasoning as alt text.
- Handle Unicode alt text without requiring mbstring.
- Fix bulk retry queues, stopped progress, and the Media Library alt field selector.
- Correct the minimum WordPress version to 5.8, which introduced core WebP support.

Verification: isolated regression tests with real image encoding and WordPress API doubles, plus PHP and JavaScript syntax checks. An additional 11 real WordPress media lifecycle checks passed locally on WordPress 7.1.4 with SQLite; CI also runs them with MySQL. Live provider calls and a WordPress browser walkthrough were not performed. See docs/audit-2.0.1.md for remaining improvements and limitations.

Install the attached plugin ZIP through Plugins > Add New > Upload Plugin. Existing source URLs remain available after bulk conversion; this release does not rewrite URLs already embedded in content. Older untracked retained files are deliberately left untouched.
