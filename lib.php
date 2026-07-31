<?php
/**
 * Core library functions for Media Optimiser plugin.
 *
 * @package    local_mediaoptimiser
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add Media Optimiser links to Moodle's site admin navigation.
 * This makes the plugin accessible via the quick-links sidebar
 * in addition to the standard Site Administration tree.
 *
 * @param global_navigation $nav
 */
function local_mediaoptimiser_extend_navigation(\global_navigation $nav): void {
    // No page-level nav items needed; admin pages are registered via
    // admin_externalpage in settings.php and appear in Site Administration.
}

/**
 * Extend the settings navigation with Media Optimiser admin links.
 * Called on every page load for site admins.
 *
 * @param settings_navigation $nav
 * @param context             $context
 */
function local_mediaoptimiser_extend_settings_navigation(\settings_navigation $nav, \context $context): void {
    // Plugin pages are already accessible via Site Administration → Local plugins → Media Optimiser.
    // No additional settings_navigation nodes are required.
}

/**
 * Map a MIME type to a display category.
 *
 * @param string $mimetype
 * @return string  Category key: image|video|audio|pdf|office|zip|backup|scorm|other
 */
function local_mediaoptimiser_get_category(string $mimetype): string {
    if (strpos($mimetype, 'image/') === 0) {
        return 'image';
    }
    if (strpos($mimetype, 'video/') === 0) {
        return 'video';
    }
    if (strpos($mimetype, 'audio/') === 0) {
        return 'audio';
    }
    if ($mimetype === 'application/pdf') {
        return 'pdf';
    }
    if ($mimetype === 'application/vnd.moodle.backup') {
        return 'backup';
    }
    $office = [
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml',
        'application/vnd.oasis.opendocument',
    ];
    foreach ($office as $prefix) {
        if (strpos($mimetype, $prefix) === 0) {
            return 'office';
        }
    }
    if (in_array($mimetype, [
        'application/zip',
        'application/x-zip',
        'application/x-zip-compressed',
        'application/x-gzip',
        'application/x-tar',
    ])) {
        // SCORM packages are ZIPs with a specific component; we differentiate via component in callers.
        return 'zip';
    }
    return 'other';
}

/**
 * Return a human-readable label for a category.
 *
 * @param string $category
 * @return string
 */
function local_mediaoptimiser_category_label(string $category): string {
    $map = [
        'image'  => 'Images',
        'video'  => 'Videos',
        'audio'  => 'Audio',
        'pdf'    => 'PDFs',
        'office' => 'Office Documents',
        'zip'    => 'ZIP / SCORM',
        'backup' => 'Moodle Backups',
        'other'  => 'Other',
    ];
    return $map[$category] ?? ucfirst($category);
}

/**
 * Format bytes to human-readable string.
 *
 * @param int $bytes
 * @param int $precision
 * @return string  e.g. "1.24 GB"
 */
function local_mediaoptimiser_format_bytes(int $bytes, int $precision = 2): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $pow = min((int)floor(log($bytes, 1024)), count($units) - 1);
    $val = $bytes / pow(1024, $pow);
    return round($val, $precision) . ' ' . $units[$pow];
}

/**
 * Calculate impact score (0–100) for a file given its properties.
 *
 * Factors:
 *   - File size (up to 40 pts)
 *   - Usage count / duplicates (up to 20 pts)
 *   - MIME-type format penalty (up to 40 pts)
 *
 * @param int    $filesize     Physical file size in bytes.
 * @param int    $usagecount   Number of {files} rows sharing this contenthash.
 * @param string $mimetype     MIME type.
 * @param int    $imagewidth   Image width in pixels (0 if unknown or not an image).
 * @return int   Score 0–100.
 */
function local_mediaoptimiser_impact_score(
    int    $filesize,
    int    $usagecount,
    string $mimetype,
    int    $imagewidth = 0
): int {
    $score = 0;

    // Size component (0–40).
    $mb = $filesize / (1024 * 1024);
    if ($mb >= 100) {
        $score += 40;
    } elseif ($mb >= 50) {
        $score += 35;
    } elseif ($mb >= 20) {
        $score += 28;
    } elseif ($mb >= 10) {
        $score += 22;
    } elseif ($mb >= 5) {
        $score += 16;
    } elseif ($mb >= 1) {
        $score += 10;
    } elseif ($mb >= 0.5) {
        $score += 5;
    }

    // Usage / duplicate component (0–20).
    if ($usagecount >= 20) {
        $score += 20;
    } elseif ($usagecount >= 10) {
        $score += 15;
    } elseif ($usagecount >= 5) {
        $score += 10;
    } elseif ($usagecount >= 2) {
        $score += 5;
    }

    // Format penalty component (0–40).
    $category = local_mediaoptimiser_get_category($mimetype);
    if ($category === 'image') {
        // BMP/TIFF are worst; PNG large files; JPEG can compress; WebP/AVIF are fine.
        if (in_array($mimetype, ['image/bmp', 'image/tiff', 'image/x-bmp'])) {
            $score += 40; // worst — no lossy compression at all
        } elseif ($mimetype === 'image/png' && $mb > 0.5) {
            $score += 25; // large PNG — could be WebP
        } elseif ($mimetype === 'image/jpeg' && $mb > 0.3) {
            $score += 12; // JPEG, probably can compress more
        } elseif ($imagewidth > 3000) {
            $score += 15; // oversized resolution
        }
    } elseif ($category === 'audio') {
        if (in_array($mimetype, ['audio/wav', 'audio/x-wav', 'audio/flac', 'audio/x-flac'])) {
            $score += 35; // uncompressed
        } elseif ($mimetype === 'audio/mpeg' && $mb > 5) {
            $score += 10; // large MP3
        }
    } elseif ($category === 'video') {
        if ($mb > 500) {
            $score += 40; // huge video
        } elseif ($mb > 100) {
            $score += 30;
        } elseif ($mb > 50) {
            $score += 20;
        } elseif ($mb > 10) {
            $score += 10;
        }
        // Suggest CDN for any video.
        $score = min($score + 5, 100);
    } elseif ($category === 'pdf') {
        if ($mb > 10) {
            $score += 25;
        } elseif ($mb > 2) {
            $score += 10;
        }
    } elseif ($category === 'backup') {
        $score += 20; // backups always worth reviewing
    }

    return min($score, 100);
}

/**
 * Generate a list of optimisation recommendations for a file.
 *
 * @param string $mimetype
 * @param int    $filesize
 * @param int    $imagewidth
 * @param int    $imageheight
 * @param int    $usagecount
 * @return string[]  Array of human-readable recommendation strings.
 */
function local_mediaoptimiser_recommendations(
    string $mimetype,
    int    $filesize,
    int    $imagewidth = 0,
    int    $imageheight = 0,
    int    $usagecount = 1
): array {
    $recs = [];
    $mb   = $filesize / (1024 * 1024);
    $cat  = local_mediaoptimiser_get_category($mimetype);

    if ($cat === 'image') {
        if (in_array($mimetype, ['image/bmp', 'image/tiff', 'image/x-bmp'])) {
            $recs[] = 'Convert BMP/TIFF → WebP (estimated saving: 85–95%)';
        }
        if ($mimetype === 'image/png') {
            $recs[] = 'Convert PNG → WebP (estimated saving: 60–80%)';
        }
        if ($mimetype === 'image/jpeg' && $mb > 0.2) {
            $recs[] = 'Re-compress JPEG (estimated saving: 20–40%)';
            $recs[] = 'Convert JPEG → WebP (estimated saving: 25–50%)';
        }
        if ($imagewidth > 3000 || $imagewidth === 0 && $mb > 1) {
            $recs[] = 'Resize to max 1920 px wide — oversized for web display';
        }
        if ($imagewidth > 0 && $imagewidth > 1920) {
            $recs[] = sprintf('Resize: %d × %d px → 1920 × %d px', $imagewidth, $imageheight,
                (int)round(1920 * $imageheight / max($imagewidth, 1)));
        }
        if ($usagecount > 1) {
            $recs[] = sprintf('Used in %d locations — a single optimised copy would benefit all of them', $usagecount);
        }
    } elseif ($cat === 'audio') {
        if (in_array($mimetype, ['audio/wav', 'audio/x-wav'])) {
            $recs[] = 'Convert WAV → MP3 128 kbps (estimated saving: 85–92%)';
        }
        if (in_array($mimetype, ['audio/flac', 'audio/x-flac'])) {
            $recs[] = 'Convert FLAC → AAC 128 kbps (estimated saving: 60–75%)';
        }
        if ($mb > 10) {
            $recs[] = 'Large audio file — consider reducing bitrate if voice-only content';
        }
    } elseif ($cat === 'video') {
        $recs[] = 'Consider hosting on YouTube / Vimeo and embedding — saves Moodle storage and bandwidth';
        if ($mb > 100) {
            $recs[] = 'Transcode to H.264 MP4 at 720p (estimated saving: 40–70%)';
        }
        if ($mb > 500) {
            $recs[] = 'Very large video — strongly recommend external CDN hosting';
        }
    } elseif ($cat === 'pdf') {
        if ($mb > 2) {
            $recs[] = 'Compress PDF (estimated saving: 30–80% depending on embedded images)';
        }
        if ($mb > 10) {
            $recs[] = 'Very large PDF — may contain high-resolution scans; OCR and re-save can reduce significantly';
        }
    } elseif ($cat === 'backup') {
        $recs[] = 'Review if this backup is still needed — Moodle backups can be very large';
        $recs[] = 'Download to external storage and delete from Moodle if not in active use';
    } elseif ($cat === 'zip') {
        if ($mb > 50) {
            $recs[] = 'Large ZIP/SCORM — check for embedded high-resolution media inside the package';
        }
    }

    if (empty($recs)) {
        $recs[] = 'No immediate optimisation identified';
    }

    return $recs;
}

/**
 * Return an Impact Score badge colour class.
 *
 * @param int $score  0–100
 * @return string  Bootstrap text-* colour class.
 */
function local_mediaoptimiser_score_colour(int $score): string {
    if ($score >= 75) {
        return 'danger';
    }
    if ($score >= 50) {
        return 'warning';
    }
    if ($score >= 25) {
        return 'info';
    }
    return 'success';
}

/**
 * Return potential savings estimate as a rough percentage string for a MIME type.
 *
 * @param string $mimetype
 * @param int    $filesize
 * @return string  e.g. "60–80%"
 */
function local_mediaoptimiser_savings_estimate(string $mimetype, int $filesize): string {
    $cat = local_mediaoptimiser_get_category($mimetype);
    $mb  = $filesize / (1024 * 1024);

    if ($cat === 'image') {
        if (in_array($mimetype, ['image/bmp', 'image/tiff'])) {
            return '85–95%';
        }
        if ($mimetype === 'image/png') {
            return '60–80%';
        }
        if ($mimetype === 'image/jpeg') {
            return '20–50%';
        }
        return '20–60%';
    }
    if ($cat === 'audio') {
        if (in_array($mimetype, ['audio/wav', 'audio/x-wav', 'audio/flac', 'audio/x-flac'])) {
            return '80–92%';
        }
        return '10–30%';
    }
    if ($cat === 'video') {
        return $mb > 100 ? '40–70%' : '20–40%';
    }
    if ($cat === 'pdf' && $mb > 2) {
        return '30–80%';
    }
    return '0–20%';
}

/**
 * Get the icon class for a file category.
 *
 * @param string $category
 * @return string  Font Awesome class (Moodle 4.x ships with FA 6).
 */
function local_mediaoptimiser_category_icon(string $category): string {
    $icons = [
        'image'  => 'fa-image',
        'video'  => 'fa-film',
        'audio'  => 'fa-music',
        'pdf'    => 'fa-file-pdf',
        'office' => 'fa-file-word',
        'zip'    => 'fa-file-archive',
        'backup' => 'fa-archive',
        'other'  => 'fa-file',
    ];
    return $icons[$category] ?? 'fa-file';
}

/**
 * Run the full file analysis and populate the cache table.
 * Shared by both the scheduled task and the on-demand adhoc task.
 */
function local_mediaoptimiser_run_analysis(): void {
    global $DB, $CFG;

    $excludedrafts = (bool)get_config('local_mediaoptimiser', 'excludedrafts');
    $excludesystem = (bool)get_config('local_mediaoptimiser', 'excludesystem');

    [$where, $params] = local_mediaoptimiser_base_where($excludedrafts, $excludesystem);

    $sql = "SELECT contenthash,
                   MAX(mimetype)   AS mimetype,
                   MAX(filesize)   AS filesize,
                   COUNT(*)        AS usagecount
              FROM {files}
             WHERE $where
          GROUP BY contenthash
          ORDER BY filesize DESC";

    $rs   = $DB->get_recordset_sql($sql, $params);
    $now  = time();
    $done = 0;

    foreach ($rs as $row) {
        $score = local_mediaoptimiser_impact_score(
            (int)$row->filesize,
            (int)$row->usagecount,
            (string)$row->mimetype
        );

        $recs = local_mediaoptimiser_recommendations(
            (string)$row->mimetype,
            (int)$row->filesize,
            0, 0,
            (int)$row->usagecount
        );

        $imagewidth  = null;
        $imageheight = null;

        if (strpos($row->mimetype, 'image/') === 0
            && function_exists('getimagesize')
            && $row->filesize < 20 * 1024 * 1024
        ) {
            $hash    = $row->contenthash;
            $subdir  = substr($hash, 0, 2);
            $subdir2 = substr($hash, 2, 2);
            $path    = $CFG->dataroot . '/filedir/' . $subdir . '/' . $subdir2 . '/' . $hash;
            if (file_exists($path)) {
                $size = @getimagesize($path);
                if ($size && $size[0] > 0) {
                    $imagewidth  = (int)$size[0];
                    $imageheight = (int)$size[1];
                    $score = local_mediaoptimiser_impact_score(
                        (int)$row->filesize,
                        (int)$row->usagecount,
                        (string)$row->mimetype,
                        $imagewidth
                    );
                    $recs = local_mediaoptimiser_recommendations(
                        (string)$row->mimetype,
                        (int)$row->filesize,
                        $imagewidth,
                        $imageheight,
                        (int)$row->usagecount
                    );
                }
            }
        }

        $data = (object)[
            'contenthash'     => $row->contenthash,
            'mimetype'        => $row->mimetype,
            'filesize'        => (int)$row->filesize,
            'imagewidth'      => $imagewidth,
            'imageheight'     => $imageheight,
            'impactscore'     => $score,
            'recommendations' => json_encode($recs),
            'analysed'        => $now,
        ];

        $existing = $DB->get_record(
            'local_mediaoptimiser_cache',
            ['contenthash' => $row->contenthash],
            'id',
            IGNORE_MISSING
        );

        if ($existing) {
            $data->id = $existing->id;
            $DB->update_record('local_mediaoptimiser_cache', $data);
        } else {
            $DB->insert_record('local_mediaoptimiser_cache', $data);
        }

        $done++;
        if ($done % 500 === 0) {
            mtrace("  local_mediaoptimiser: analysed $done files...");
        }
    }

    $rs->close();
    mtrace("  local_mediaoptimiser: analysis complete. $done unique files processed.");
}

/**
 * Build the WHERE clause fragments shared across dashboard queries.
 * Respects plugin settings for excluding drafts and system files.
 *
 * @param bool $excludeDrafts  Exclude draft filearea files.
 * @param bool $excludeSystem  Exclude core system component files.
 * @return array  [sql_fragment, params_array]
 */
function local_mediaoptimiser_base_where(bool $excludeDrafts = true, bool $excludeSystem = true): array {
    $where  = "filename != :dot AND filesize > 0";
    $params = ['dot' => '.'];
    if ($excludeDrafts) {
        $where .= " AND filearea != :draft";
        $params['draft'] = 'draft';
    }
    if ($excludeSystem) {
        $where .= " AND component NOT IN (:core1, :core2, :core3)";
        $params['core1'] = 'core';
        $params['core2'] = 'core_h5p';
        $params['core3'] = 'theme';
    }
    return [$where, $params];
}
