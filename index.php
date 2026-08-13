<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Media Optimiser - Main admin dashboard.
 *
 * Shows a site-health overview: total storage, potential savings, file-type
 * breakdown, largest files, highest-impact files and duplicate detection.
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

$action = optional_param('action', '', PARAM_ALPHA);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/mediaoptimiser/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_mediaoptimiser') . ' — Dashboard');
$PAGE->set_heading(get_string('pluginname', 'local_mediaoptimiser'));
$PAGE->set_pagelayout('admin');

// ── Handle "queue analysis" action ──────────────────────────────────────────
if ($action === 'analyse') {
    require_sesskey();
    $task = new \local_mediaoptimiser\task\analyse_files_adhoc();
    \core\task\manager::queue_adhoc_task($task);
    redirect(
        new moodle_url('/local/mediaoptimiser/index.php'),
        get_string('analysis_queued', 'local_mediaoptimiser'),
        5,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── Settings ─────────────────────────────────────────────────────────────────
$excludedrafts = (bool)get_config('local_mediaoptimiser', 'excludedrafts');
$excludesystem = (bool)get_config('local_mediaoptimiser', 'excludesystem');
[$where, $params] = local_mediaoptimiser_base_where($excludedrafts, $excludesystem);

// ── Query: physical storage (distinct contenthashes) ─────────────────────────
$physrow = $DB->get_record_sql(
    "SELECT COUNT(*) AS cnt, SUM(filesize) AS totalsize
       FROM (SELECT DISTINCT contenthash, filesize FROM {files} WHERE $where) subq",
    $params
);
$physicalFiles   = (int)($physrow->cnt      ?? 0);
$physicalStorage = (int)($physrow->totalsize ?? 0);

// ── Query: logical size (counting all instances, incl. duplicates) ────────────
$logicalrow = $DB->get_record_sql(
    "SELECT COUNT(*) AS cnt, SUM(filesize) AS logicalsize
       FROM {files}
      WHERE $where",
    $params
);
$logicalFiles   = (int)($logicalrow->cnt         ?? 0);
$logicalStorage = (int)($logicalrow->logicalsize ?? 0);
$dedupSavings   = max(0, $logicalStorage - $physicalStorage);

// ── Query: duplicate groups (contenthash used in > 1 location) ───────────────
$duprow = $DB->get_record_sql(
    "SELECT COUNT(*) AS dupgroups
       FROM (
           SELECT contenthash
             FROM {files}
            WHERE $where
         GROUP BY contenthash
           HAVING COUNT(*) > 1
       ) subq",
    $params
);
$duplicateGroups = (int)($duprow->dupgroups ?? 0);

// ── Query: by MIME type (unique files only) ───────────────────────────────────
$mimesql = "SELECT mimetype, COUNT(*) AS filecount
              FROM (SELECT DISTINCT contenthash, mimetype FROM {files} WHERE $where) subq
          GROUP BY mimetype
          ORDER BY filecount DESC";
$mimerows = $DB->get_records_sql($mimesql, $params);

// Aggregate into categories in PHP.
$categories = [];
foreach ($mimerows as $mrow) {
    $cat = local_mediaoptimiser_get_category($mrow->mimetype);
    if (!isset($categories[$cat])) {
        $categories[$cat] = ['filecount' => 0];
    }
    $categories[$cat]['filecount'] += (int)$mrow->filecount;
}
arsort($categories);

// ── Query: top 20 largest unique files ───────────────────────────────────────
$top20sql = "SELECT contenthash,
                    MAX(filename)  AS filename,
                    MAX(mimetype)  AS mimetype,
                    MAX(filesize)  AS filesize,
                    MAX(component) AS component,
                    COUNT(*)       AS usagecount
               FROM {files}
              WHERE $where
           GROUP BY contenthash
           ORDER BY MAX(filesize) DESC
              LIMIT 20";
$top20 = $DB->get_records_sql($top20sql, $params);

// ── Query: top 20 by cached impact score (if cache populated) ─────────────────
$impactsql = "SELECT c.contenthash, c.mimetype, c.filesize, c.imagewidth, c.imageheight,
                     c.impactscore, c.recommendations, c.analysed,
                     MAX(f.filename)  AS filename,
                     MAX(f.component) AS component,
                     COUNT(f.id)      AS usagecount
                FROM {local_mediaoptimiser_cache} c
                JOIN {files} f ON f.contenthash = c.contenthash
               WHERE c.impactscore >= 25 AND f.filename != '.'
            GROUP BY c.contenthash, c.mimetype, c.filesize, c.imagewidth, c.imageheight,
                     c.impactscore, c.recommendations, c.analysed
            ORDER BY c.impactscore DESC
               LIMIT 20";
$impactfiles = [];
try {
    $impactfiles = $DB->get_records_sql($impactsql);
} catch (\dml_exception $e) {
    // Cache table may be empty on fresh install.
    $impactfiles = [];
}

// ── Last analysis time ────────────────────────────────────────────────────────
$lastanalysed = $DB->get_field_sql(
    "SELECT MAX(analysed) FROM {local_mediaoptimiser_cache}"
);

// ── Potential savings estimate ────────────────────────────────────────────────
// Rough estimate: 50% of images + 70% of audio + 40% of video + 30% of PDFs.
$savingsEstimateBytes = 0;
$savingsSql = "SELECT mimetype, SUM(filesize) AS totalsize
                 FROM (SELECT DISTINCT contenthash, mimetype, filesize FROM {files} WHERE $where) sub
             GROUP BY mimetype";
$savingsRows = $DB->get_records_sql($savingsSql, $params);
foreach ($savingsRows as $sr) {
    $cat = local_mediaoptimiser_get_category($sr->mimetype);
    $sz  = (int)$sr->totalsize;
    if ($cat === 'image') {
        $savingsEstimateBytes += (int)($sz * 0.50);
    } elseif ($cat === 'audio') {
        $savingsEstimateBytes += (int)($sz * 0.65);
    } elseif ($cat === 'video') {
        $savingsEstimateBytes += (int)($sz * 0.40);
    } elseif ($cat === 'pdf') {
        $savingsEstimateBytes += (int)($sz * 0.30);
    }
}

// ── Output ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();

$analyseurl = new moodle_url('/local/mediaoptimiser/index.php', [
    'action'  => 'analyse',
    'sesskey' => sesskey(),
]);
$filesurl   = new moodle_url('/local/mediaoptimiser/files.php');
$reportsurl = new moodle_url('/local/mediaoptimiser/reports.php');

// ── Nav tabs ─────────────────────────────────────────────────────────────────
?>
<div class="local-mediaoptimiser">

<style>
.mo-stat-card{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:24px 20px;text-align:center;margin-bottom:0;}
.dark-mode .mo-stat-card,.theme-dark .mo-stat-card{background:#2a2d30;border-color:#444;}
.mo-stat-value{font-size:2rem;font-weight:700;line-height:1.1;margin:8px 0 4px;}
.mo-stat-label{font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;color:#6c757d;margin-bottom:0;}
.mo-stat-sub{font-size:.75rem;color:#adb5bd;margin-top:2px;}
.mo-section-heading{font-size:1.15rem;font-weight:600;margin:28px 0 14px;padding-bottom:6px;border-bottom:2px solid #e9ecef;}
.mo-badge-score{display:inline-block;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:700;color:#fff;}
.mo-badge-danger{background:#dc3545;}
.mo-badge-warning{background:#fd7e14;color:#fff;}
.mo-badge-info{background:#0dcaf0;color:#000;}
.mo-badge-success{background:#198754;}
.mo-rec{font-size:.8rem;padding:3px 0;color:#495057;}
.mo-rec::before{content:"✓ ";color:#198754;font-weight:700;}
.mo-topnav{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;align-items:center;}
.mo-topnav a.btn{font-size:.85rem;}
</style>

<!-- Top nav -->
<div class="mo-topnav">
    <a href="<?php echo $filesurl; ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-folder-open mr-1"></i>File Browser
    </a>
    <a href="<?php echo $reportsurl; ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-chart-bar mr-1"></i>Reports
    </a>
    <div style="flex:1"></div>
    <?php if ($lastanalysed): ?>
        <small class="text-muted align-self-center">
            Last analysed: <?php echo userdate($lastanalysed); ?>
        </small>
    <?php else: ?>
        <small class="text-muted align-self-center">Analysis cache is empty</small>
    <?php endif; ?>
    <a href="<?php echo $analyseurl; ?>" class="btn btn-primary btn-sm">
        <i class="fa fa-sync mr-1"></i>Run Analysis Now
    </a>
</div>

<!-- ── Health stat cards ──────────────────────────────────────────────── -->
<h5 class="mo-section-heading"><i class="fa fa-heartbeat mr-2 text-danger"></i>Site Storage Health</h5>
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-4">

    <div class="col">
        <div class="mo-stat-card">
            <div class="mo-stat-label"><i class="fa fa-files-o mr-1"></i>Unique Files</div>
            <div class="mo-stat-value text-primary"><?php echo number_format($physicalFiles); ?></div>
            <div class="mo-stat-sub"><?php echo number_format($logicalFiles); ?> total instances</div>
        </div>
    </div>

    <div class="col">
        <div class="mo-stat-card">
            <div class="mo-stat-label"><i class="fa fa-hdd-o mr-1"></i>Physical Storage</div>
            <div class="mo-stat-value text-dark"><?php echo local_mediaoptimiser_format_bytes($physicalStorage); ?></div>
            <div class="mo-stat-sub">Actual disk space used</div>
        </div>
    </div>

    <div class="col">
        <div class="mo-stat-card">
            <div class="mo-stat-label"><i class="fa fa-leaf mr-1"></i>Deduplication Savings</div>
            <div class="mo-stat-value text-success"><?php echo local_mediaoptimiser_format_bytes($dedupSavings); ?></div>
            <div class="mo-stat-sub">Saved by Moodle's built-in dedup</div>
        </div>
    </div>

    <div class="col">
        <div class="mo-stat-card">
            <div class="mo-stat-label"><i class="fa fa-compress mr-1"></i>Optimisation Potential</div>
            <div class="mo-stat-value text-warning"><?php echo local_mediaoptimiser_format_bytes($savingsEstimateBytes); ?></div>
            <div class="mo-stat-sub">Estimated if images/audio/video/PDFs optimised</div>
        </div>
    </div>

</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="mo-stat-card">
            <div class="mo-stat-label"><i class="fa fa-copy mr-1"></i>Duplicate Groups</div>
            <div class="mo-stat-value <?php echo $duplicateGroups > 0 ? 'text-warning' : 'text-success'; ?>">
                <?php echo number_format($duplicateGroups); ?>
            </div>
            <div class="mo-stat-sub">Contenthashes with &gt;1 file record</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="mo-stat-card">
            <div class="mo-stat-label"><i class="fa fa-flag mr-1"></i>High-Impact Files</div>
            <div class="mo-stat-value <?php echo count($impactfiles) > 0 ? 'text-danger' : 'text-success'; ?>">
                <?php echo count($impactfiles) > 0 ? count($impactfiles) . '+' : '0'; ?>
            </div>
            <div class="mo-stat-sub">Impact score ≥ 25 (from last analysis)</div>
        </div>
    </div>
</div>

<!-- ── File Type Breakdown ────────────────────────────────────────────── -->
<h5 class="mo-section-heading"><i class="fa fa-pie-chart mr-2 text-info"></i>Unique Files by Type</h5>
<?php if ($categories): ?>
<div class="table-responsive">
<table class="table table-sm table-hover generaltable">
    <thead class="thead-light">
        <tr>
            <th>Category</th>
            <th class="text-right">Unique Files</th>
            <th>Typical Optimisation Potential</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($categories as $cat => $data): ?>
        <tr>
            <td>
                <i class="fa <?php echo local_mediaoptimiser_category_icon($cat); ?> mr-2 text-secondary"></i>
                <?php echo local_mediaoptimiser_category_label($cat); ?>
            </td>
            <td class="text-right"><?php echo number_format($data['filecount']); ?></td>
            <td>
                <?php
                $potentials = [
                    'image'  => '<span class="badge badge-warning">High — up to 80%</span>',
                    'audio'  => '<span class="badge badge-warning">High — up to 92%</span>',
                    'video'  => '<span class="badge badge-danger">Very High — up to 70%</span>',
                    'pdf'    => '<span class="badge badge-info">Medium — up to 80%</span>',
                    'backup' => '<span class="badge badge-secondary">Review / Delete</span>',
                    'office' => '<span class="badge badge-light">Low — 5–20%</span>',
                    'zip'    => '<span class="badge badge-light">Review contents</span>',
                    'other'  => '<span class="badge badge-light">Varies</span>',
                ];
                echo $potentials[$cat] ?? '<span class="badge badge-light">Varies</span>';
                ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php else: ?>
    <p class="text-muted"><?php echo get_string('no_files', 'local_mediaoptimiser'); ?></p>
<?php endif; ?>

<!-- ── Top 20 Largest Files ───────────────────────────────────────────── -->
<h5 class="mo-section-heading"><i class="fa fa-sort-amount-desc mr-2 text-primary"></i>Top 20 Largest Files</h5>
<?php if ($top20): ?>
<div class="table-responsive">
<table class="table table-sm table-hover generaltable">
    <thead class="thead-light">
        <tr>
            <th>Filename</th>
            <th>Type</th>
            <th class="text-right">Size</th>
            <th class="text-right">Uses</th>
            <th>Component</th>
            <th>Est. Saving</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($top20 as $f): ?>
        <tr>
            <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                title="<?php echo s($f->filename); ?>">
                <?php echo s($f->filename); ?>
            </td>
            <td>
                <small class="text-muted"><?php echo s($f->mimetype); ?></small>
            </td>
            <td class="text-right font-weight-bold">
                <?php echo local_mediaoptimiser_format_bytes((int)$f->filesize); ?>
            </td>
            <td class="text-right"><?php echo (int)$f->usagecount; ?></td>
            <td><small><?php echo s($f->component); ?></small></td>
            <td>
                <small><?php echo local_mediaoptimiser_savings_estimate($f->mimetype, (int)$f->filesize); ?></small>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p><a href="<?php echo $filesurl; ?>" class="btn btn-sm btn-outline-primary">View all files &rarr;</a></p>
<?php else: ?>
    <p class="text-muted"><?php echo get_string('no_files', 'local_mediaoptimiser'); ?></p>
<?php endif; ?>

<!-- ── Highest-Impact Files (from cache) ─────────────────────────────── -->
<?php if ($impactfiles): ?>
<h5 class="mo-section-heading"><i class="fa fa-exclamation-triangle mr-2 text-warning"></i>Highest-Impact Files</h5>
<div class="table-responsive">
<table class="table table-sm table-hover generaltable">
    <thead class="thead-light">
        <tr>
            <th>Filename</th>
            <th>Type</th>
            <th class="text-right">Size</th>
            <th class="text-center">Impact Score</th>
            <th>Top Recommendations</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($impactfiles as $f): ?>
        <?php
        $recs  = json_decode($f->recommendations ?? '[]', true) ?: [];
        $score = (int)$f->impactscore;
        $col   = local_mediaoptimiser_score_colour($score);
        ?>
        <tr>
            <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                title="<?php echo s($f->filename); ?>">
                <?php echo s($f->filename); ?>
            </td>
            <td><small class="text-muted"><?php echo s($f->mimetype); ?></small></td>
            <td class="text-right font-weight-bold">
                <?php echo local_mediaoptimiser_format_bytes((int)$f->filesize); ?>
            </td>
            <td class="text-center">
                <span class="mo-badge-score mo-badge-<?php echo $col; ?>">
                    <?php echo $score; ?> / 100
                </span>
            </td>
            <td>
                <?php foreach (array_slice($recs, 0, 2) as $rec): ?>
                    <div class="mo-rec"><?php echo s($rec); ?></div>
                <?php endforeach; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<!-- ── Call to action if cache empty ─────────────────────────────────── -->
<?php if (empty($impactfiles)): ?>
<div class="alert alert-info mt-3">
    <i class="fa fa-info-circle mr-2"></i>
    <strong>Run the analysis</strong> to populate impact scores and per-file recommendations.
    Click <strong>Run Analysis Now</strong> above to queue the background task, or wait for the
    nightly scheduled run at 2:00 AM.
</div>
<?php endif; ?>

</div><!-- .local-mediaoptimiser -->
<?php
echo $OUTPUT->footer();
