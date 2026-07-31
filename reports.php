<?php
/**
 * Media Optimiser - Reports page.
 *
 * Shows: top bandwidth users by component/course, duplicate groups,
 * files by age, and growth trends.
 *
 * @package    local_mediaoptimiser
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

\core\session\manager::write_close();

$report = optional_param('report', 'overview', PARAM_ALPHA);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/mediaoptimiser/reports.php', ['report' => $report]));
$PAGE->set_title(get_string('pluginreports', 'local_mediaoptimiser'));
$PAGE->set_heading(get_string('pluginname', 'local_mediaoptimiser') . ' — Reports');
$PAGE->set_pagelayout('admin');

$excludedrafts = (bool)get_config('local_mediaoptimiser', 'excludedrafts');
$excludesystem = (bool)get_config('local_mediaoptimiser', 'excludesystem');
[$where, $params] = local_mediaoptimiser_base_where($excludedrafts, $excludesystem);

echo $OUTPUT->header();

$dashurl    = new moodle_url('/local/mediaoptimiser/index.php');
$reportsurl = new moodle_url('/local/mediaoptimiser/reports.php');
?>
<div class="local-mediaoptimiser">

<style>
.mo-section-heading{font-size:1.1rem;font-weight:600;margin:0 0 14px;padding-bottom:6px;border-bottom:2px solid #e9ecef;}
.mo-report-nav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;}
.mo-report-nav a.btn{font-size:.85rem;}
</style>

<div style="margin-bottom:16px;">
    <a href="<?php echo $dashurl; ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa fa-arrow-left mr-1"></i>Dashboard
    </a>
</div>

<!-- Report selector -->
<div class="mo-report-nav">
    <?php
    $reports = [
        'overview'   => 'Overview',
        'duplicates' => 'Duplicate Files',
        'bycomponent'=> 'Storage by Component',
        'largest'    => 'Largest Files',
        'oldest'     => 'Oldest Large Files',
        'images'     => 'Image Analysis',
        'videos'     => 'Video Analysis',
    ];
    foreach ($reports as $key => $label):
        $active = ($report === $key) ? 'btn-primary' : 'btn-outline-secondary';
    ?>
        <a href="<?php echo new moodle_url('/local/mediaoptimiser/reports.php', ['report' => $key]); ?>"
           class="btn btn-sm <?php echo $active; ?>">
            <?php echo htmlspecialchars($label); ?>
        </a>
    <?php endforeach; ?>
</div>

<?php

// ─────────────────────────────────────────────────────────────────────────────
if ($report === 'overview') {
    echo '<h5 class="mo-section-heading"><i class="fa fa-bar-chart mr-2"></i>Storage Overview by Type</h5>';

    $sql = "SELECT mimetype,
                   COUNT(DISTINCT contenthash) AS uniquefiles,
                   COUNT(*) AS totalinstances
              FROM {files}
             WHERE $where
          GROUP BY mimetype
          ORDER BY uniquefiles DESC
             LIMIT 60";
    $rows = $DB->get_records_sql($sql, $params);

    $bycat = [];
    foreach ($rows as $r) {
        $cat = local_mediaoptimiser_get_category($r->mimetype);
        if (!isset($bycat[$cat])) {
            $bycat[$cat] = ['unique' => 0, 'instances' => 0];
        }
        $bycat[$cat]['unique']     += (int)$r->uniquefiles;
        $bycat[$cat]['instances']  += (int)$r->totalinstances;
    }
    arsort($bycat);

    echo '<div class="table-responsive"><table class="table table-sm generaltable"><thead class="thead-light"><tr>';
    echo '<th>Category</th><th class="text-right">Unique Files</th><th class="text-right">Total Instances</th><th class="text-right">Instances / Unique</th>';
    echo '</tr></thead><tbody>';
    foreach ($bycat as $cat => $d) {
        $ratio = $d['unique'] > 0 ? round($d['instances'] / $d['unique'], 1) : 1;
        printf('<tr><td><i class="fa %s mr-2 text-muted"></i>%s</td><td class="text-right">%s</td><td class="text-right">%s</td><td class="text-right">%s×</td></tr>',
            local_mediaoptimiser_category_icon($cat),
            htmlspecialchars(local_mediaoptimiser_category_label($cat)),
            number_format($d['unique']),
            number_format($d['instances']),
            $ratio
        );
    }
    echo '</tbody></table></div>';
}

// ─────────────────────────────────────────────────────────────────────────────
if ($report === 'duplicates') {
    echo '<h5 class="mo-section-heading"><i class="fa fa-copy mr-2 text-warning"></i>Files Used in Multiple Locations</h5>';
    echo '<p class="text-muted" style="font-size:.875rem;">These are unique physical files (same content) referenced from more than one location. Moodle already deduplicates the physical storage — these are simply informational. If you delete one reference the file is still safe in other locations.</p>';

    $sql = "SELECT contenthash,
                   MAX(filename)  AS filename,
                   MAX(mimetype)  AS mimetype,
                   MAX(filesize)  AS filesize,
                   MAX(component) AS component,
                   COUNT(*)       AS usagecount
              FROM {files}
             WHERE $where
          GROUP BY contenthash
            HAVING COUNT(*) > 1
          ORDER BY MAX(filesize) DESC
             LIMIT 100";
    $rows = $DB->get_records_sql($sql, $params);

    if ($rows) {
        echo '<div class="table-responsive"><table class="table table-sm generaltable"><thead class="thead-light"><tr>';
        echo '<th>Filename</th><th>MIME Type</th><th class="text-right">Size</th><th class="text-right">Locations</th><th>Primary Component</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $r) {
            printf('<tr><td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="%s">%s</td><td><small class="text-muted">%s</small></td><td class="text-right font-weight-bold">%s</td><td class="text-right"><span class="badge badge-warning">%s</span></td><td><small>%s</small></td></tr>',
                s($r->filename), s($r->filename),
                s($r->mimetype),
                local_mediaoptimiser_format_bytes((int)$r->filesize),
                number_format((int)$r->usagecount),
                s($r->component)
            );
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="alert alert-success">No duplicates found.</div>';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
if ($report === 'bycomponent') {
    echo '<h5 class="mo-section-heading"><i class="fa fa-sitemap mr-2 text-info"></i>Storage by Moodle Component</h5>';

    $sql = "SELECT component,
                   COUNT(DISTINCT contenthash) AS uniquefiles,
                   COUNT(*) AS totalinstances,
                   SUM(filesize) AS logicalsize
              FROM {files}
             WHERE $where
          GROUP BY component
          ORDER BY logicalsize DESC
             LIMIT 40";
    $rows = $DB->get_records_sql($sql, $params);

    echo '<div class="table-responsive"><table class="table table-sm generaltable"><thead class="thead-light"><tr>';
    echo '<th>Component</th><th class="text-right">Unique Files</th><th class="text-right">Instances</th><th class="text-right">Logical Size</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) {
        printf('<tr><td><code>%s</code></td><td class="text-right">%s</td><td class="text-right">%s</td><td class="text-right">%s</td></tr>',
            s($r->component),
            number_format((int)$r->uniquefiles),
            number_format((int)$r->totalinstances),
            local_mediaoptimiser_format_bytes((int)$r->logicalsize)
        );
    }
    echo '</tbody></table></div>';
}

// ─────────────────────────────────────────────────────────────────────────────
if ($report === 'largest') {
    echo '<h5 class="mo-section-heading"><i class="fa fa-sort-amount-desc mr-2 text-primary"></i>Top 100 Largest Files</h5>';

    $sql = "SELECT contenthash,
                   MAX(filename)  AS filename,
                   MAX(mimetype)  AS mimetype,
                   MAX(filesize)  AS filesize,
                   MAX(component) AS component,
                   COUNT(*)       AS usagecount
              FROM {files}
             WHERE $where
          GROUP BY contenthash
          ORDER BY MAX(filesize) DESC
             LIMIT 100";
    $rows = $DB->get_records_sql($sql, $params);

    echo '<div class="table-responsive"><table class="table table-sm generaltable"><thead class="thead-light"><tr>';
    echo '<th>#</th><th>Filename</th><th>Type</th><th class="text-right">Size</th><th class="text-right">Uses</th><th>Saving Estimate</th>';
    echo '</tr></thead><tbody>';
    $i = 1;
    foreach ($rows as $r) {
        printf('<tr><td class="text-muted">%d</td><td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="%s">%s</td><td><small class="text-muted">%s</small></td><td class="text-right font-weight-bold">%s</td><td class="text-right">%s</td><td><small>%s</small></td></tr>',
            $i++,
            s($r->filename), s($r->filename),
            s($r->mimetype),
            local_mediaoptimiser_format_bytes((int)$r->filesize),
            number_format((int)$r->usagecount),
            local_mediaoptimiser_savings_estimate($r->mimetype, (int)$r->filesize)
        );
    }
    echo '</tbody></table></div>';
}

// ─────────────────────────────────────────────────────────────────────────────
if ($report === 'oldest') {
    echo '<h5 class="mo-section-heading"><i class="fa fa-clock-o mr-2 text-muted"></i>Oldest Large Files (> 1 MB)</h5>';
    echo '<p class="text-muted" style="font-size:.875rem;">Old large files may be forgotten resources that are no longer used. Review whether they can be archived or deleted.</p>';

    $sql = "SELECT contenthash,
                   MAX(filename)      AS filename,
                   MAX(mimetype)      AS mimetype,
                   MAX(filesize)      AS filesize,
                   MAX(component)     AS component,
                   COUNT(*)           AS usagecount,
                   MIN(timecreated)   AS earliest
              FROM {files}
             WHERE $where AND filesize > 1048576
          GROUP BY contenthash
          ORDER BY MIN(timecreated) ASC
             LIMIT 50";
    $rows = $DB->get_records_sql($sql, $params);

    echo '<div class="table-responsive"><table class="table table-sm generaltable"><thead class="thead-light"><tr>';
    echo '<th>Filename</th><th>Type</th><th class="text-right">Size</th><th>First Uploaded</th><th>Component</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) {
        printf('<tr><td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="%s">%s</td><td><small class="text-muted">%s</small></td><td class="text-right font-weight-bold">%s</td><td>%s</td><td><small>%s</small></td></tr>',
            s($r->filename), s($r->filename),
            s($r->mimetype),
            local_mediaoptimiser_format_bytes((int)$r->filesize),
            userdate((int)$r->earliest, get_string('strftimedate', 'langconfig')),
            s($r->component)
        );
    }
    echo '</tbody></table></div>';
}

// ─────────────────────────────────────────────────────────────────────────────
if ($report === 'images') {
    echo '<h5 class="mo-section-heading"><i class="fa fa-image mr-2 text-success"></i>Image File Analysis</h5>';

    $sql = "SELECT mimetype,
                   COUNT(DISTINCT contenthash) AS uniquefiles,
                   COUNT(*) AS instances
              FROM {files}
             WHERE $where AND mimetype LIKE 'image/%'
          GROUP BY mimetype
          ORDER BY uniquefiles DESC";
    $rows = $DB->get_records_sql($sql, $params);

    if ($rows) {
        echo '<div class="table-responsive"><table class="table table-sm generaltable"><thead class="thead-light"><tr>';
        echo '<th>Image Format</th><th class="text-right">Unique Files</th><th class="text-right">Instances</th><th>Optimisation Potential</th>';
        echo '</tr></thead><tbody>';

        $formatPotential = [
            'image/bmp'          => ['🔴 Very High — convert to WebP', 'danger'],
            'image/tiff'         => ['🔴 Very High — convert to WebP', 'danger'],
            'image/png'          => ['🟠 High — convert to WebP (60–80% saving)', 'warning'],
            'image/jpeg'         => ['🟡 Medium — re-compress or convert to WebP', 'warning'],
            'image/gif'          => ['🟡 Medium — consider WebP for non-animated GIFs', 'info'],
            'image/webp'         => ['✅ Already optimised format', 'success'],
            'image/avif'         => ['✅ Modern format — no action needed', 'success'],
            'image/svg+xml'      => ['✅ Vector — no optimisation needed', 'success'],
        ];

        foreach ($rows as $r) {
            [$potential, $cls] = $formatPotential[$r->mimetype] ?? ['Review', 'secondary'];
            printf('<tr><td><code>%s</code></td><td class="text-right">%s</td><td class="text-right">%s</td><td><span class="badge badge-%s">%s</span></td></tr>',
                s($r->mimetype),
                number_format((int)$r->uniquefiles),
                number_format((int)$r->instances),
                $cls,
                htmlspecialchars($potential)
            );
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="alert alert-info">No image files found.</div>';
    }

    // Largest images — two-step to avoid ambiguous column refs when LEFT JOINing cache.
    echo '<h5 class="mo-section-heading mt-4"><i class="fa fa-sort-amount-desc mr-2"></i>Top 20 Largest Images</h5>';
    $sql = "SELECT contenthash, MAX(filename) AS filename, MAX(mimetype) AS mimetype,
                   MAX(filesize) AS filesize, COUNT(*) AS usagecount
              FROM {files}
             WHERE $where AND mimetype LIKE 'image/%'
          GROUP BY contenthash
          ORDER BY MAX(filesize) DESC";
    $imgrows = $DB->get_records_sql($sql, $params, 0, 20);
    // Enrich with cached image dimensions where available.
    if ($imgrows) {
        $hashes = array_keys($imgrows);
        list($inhash, $inparams) = $DB->get_in_or_equal($hashes, SQL_PARAMS_NAMED);
        try {
            $cacherows = $DB->get_records_select(
                'local_mediaoptimiser_cache',
                "contenthash $inhash",
                $inparams,
                '',
                'contenthash, imagewidth, imageheight'
            );
        } catch (\dml_exception $e) {
            $cacherows = [];
        }
        foreach ($imgrows as $hash => $r) {
            $r->imagewidth  = isset($cacherows[$hash]) ? $cacherows[$hash]->imagewidth  : null;
            $r->imageheight = isset($cacherows[$hash]) ? $cacherows[$hash]->imageheight : null;
        }
    }
    echo '<div class="table-responsive"><table class="table table-sm generaltable"><thead class="thead-light"><tr>';
    echo '<th>Filename</th><th>Format</th><th class="text-right">Size</th><th class="text-right">Dimensions</th><th>Recommendations</th>';
    echo '</tr></thead><tbody>';
    foreach ($imgrows as $r) {
        $dims = ($r->imagewidth && $r->imageheight)
            ? $r->imagewidth . ' × ' . $r->imageheight . ' px'
            : '(run analysis)';
        $recs = local_mediaoptimiser_recommendations($r->mimetype, (int)$r->filesize, (int)($r->imagewidth ?? 0), (int)($r->imageheight ?? 0));
        printf('<tr><td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="%s">%s</td><td><small>%s</small></td><td class="text-right font-weight-bold">%s</td><td class="text-right">%s</td><td><small>%s</small></td></tr>',
            s($r->filename), s($r->filename),
            s($r->mimetype),
            local_mediaoptimiser_format_bytes((int)$r->filesize),
            htmlspecialchars($dims),
            htmlspecialchars(implode('; ', array_slice($recs, 0, 2)))
        );
    }
    echo '</tbody></table></div>';
}

// ─────────────────────────────────────────────────────────────────────────────
if ($report === 'videos') {
    echo '<h5 class="mo-section-heading"><i class="fa fa-film mr-2 text-danger"></i>Video File Analysis</h5>';
    echo '<div class="alert alert-warning"><i class="fa fa-lightbulb-o mr-2"></i><strong>Recommendation:</strong> Videos hosted inside Moodle consume significant storage and bandwidth. Consider hosting on YouTube, Vimeo, or another CDN and embedding the URL in Moodle instead.</div>';

    $sql = "SELECT contenthash, MAX(filename) AS filename, MAX(mimetype) AS mimetype,
                   MAX(filesize) AS filesize, MAX(component) AS component,
                   COUNT(*) AS usagecount
              FROM {files}
             WHERE $where AND mimetype LIKE 'video/%'
          GROUP BY contenthash
          ORDER BY MAX(filesize) DESC
             LIMIT 100";
    $rows = $DB->get_records_sql($sql, $params);

    if ($rows) {
        $totalVideoSize = array_sum(array_map(fn($r) => (int)$r->filesize, $rows));
        echo '<div class="alert alert-info"><i class="fa fa-info-circle mr-2"></i>Top 100 videos shown. Total size (top 100): <strong>' . local_mediaoptimiser_format_bytes($totalVideoSize) . '</strong></div>';
        echo '<div class="table-responsive"><table class="table table-sm generaltable"><thead class="thead-light"><tr>';
        echo '<th>#</th><th>Filename</th><th>Format</th><th class="text-right">Size</th><th class="text-right">Uses</th><th>Component</th><th>Recommendation</th>';
        echo '</tr></thead><tbody>';
        $i = 1;
        foreach ($rows as $r) {
            $mb  = (int)$r->filesize / (1024 * 1024);
            $rec = $mb > 500 ? 'Host on CDN — very large'
                : ($mb > 100 ? 'Host on CDN or transcode to H.264 720p'
                : 'Transcode to H.264 MP4 to reduce size');
            printf('<tr><td class="text-muted">%d</td><td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="%s">%s</td><td><small>%s</small></td><td class="text-right font-weight-bold">%s</td><td class="text-right">%d</td><td><small>%s</small></td><td><small class="text-warning">%s</small></td></tr>',
                $i++,
                s($r->filename), s($r->filename),
                s($r->mimetype),
                local_mediaoptimiser_format_bytes((int)$r->filesize),
                (int)$r->usagecount,
                s($r->component),
                htmlspecialchars($rec)
            );
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="alert alert-success">No video files found in Moodle storage.</div>';
    }
}
?>

</div>
<?php
echo $OUTPUT->footer();
