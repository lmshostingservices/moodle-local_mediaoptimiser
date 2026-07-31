# Media Optimiser — Changelog

## v1.0.0 — 28 Jun 2026

Initial release.

- **Dashboard** — Site health overview showing total unique files, physical storage, deduplication savings, estimated optimisation potential, duplicate file groups, and high-impact file count.
- **File Browser** — Browse all site files with filters by category (Images, Videos, Audio, PDFs, Office, ZIP/SCORM, Backups) and sorting by size, impact score, or name. Paginated 50 per page.
- **Reports** — Seven built-in reports: Overview by type, Duplicate Files, Storage by Component, Top 100 Largest Files, Oldest Large Files, Image Analysis (format breakdown + top 20 largest images), Video Analysis (with CDN hosting recommendation).
- **Impact Scoring (0–100)** — Each file receives a score based on size, usage count, and format penalty (BMP/TIFF score highest; WebP/AVIF score lowest). Scores are stored in the cache table after analysis.
- **Per-file Recommendations** — Specific optimisation actions per file: resize oversized images, convert to WebP/AAC/H.264, compress JPEGs/PDFs, host videos on CDN, review old backups.
- **Background Scanner** — Nightly scheduled task (2:00 AM) walks all site files, reads image dimensions via PHP GD where available, computes impact scores and recommendations, and writes results to the cache table (skips re-analysis of unchanged files).
- **Run Analysis Now** — Button to immediately queue the analysis task as a Moodle ad-hoc task (runs on next cron cycle).
- **Settings** — Toggle to exclude draft files and exclude core/system component files from analysis.
- **Privacy API** — Implemented as a null provider; only file content hashes are stored, no user data.
- **Capability-controlled** — `local/mediaoptimiser:viewdashboard` and `local/mediaoptimiser:manage` capabilities; access restricted to site administrators by default.
