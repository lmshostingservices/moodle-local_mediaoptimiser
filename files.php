<?php
/**
 * Media Optimiser - File browser page.
 *
 * Browse all site files with filtering by category, sorting by size/impact.
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

$cat     = optional_param('cat',    '',       PARAM_ALPHA);
$sort    = optional_param('sort',   'size',   PARAM_ALPHA);
$page    = optional_param('page',   0,        PARAM_INT);
$perpage = 50;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/mediaoptimiser/files.php', [
    'cat' => $cat, 'sort' => $sort, 'page' => $page,
]));
$PAGE->set_title(get_string('pluginfiles', 'local_mediaoptimiser'));
$PAGE->set_heading(get_string('pluginname', 'local_mediaoptimiser') . ' — File Browser');
$PAGE->set_pagelayout('admin');

$excludedrafts = (bool)get_config('local_mediaoptimiser', 'excludedrafts');
$excludesystem = (bool)get_config('local_mediaoptimiser', 'excludesystem');
[$where, $params] = local_mediaoptimiser_base_where($excludedrafts, $excludesystem);

// Category filter.
$catFilter = '';
if ($cat) {
    $mimePatterns = [
        'image'  => ["mimetype LIKE 'image/%'"],
        'video'  => ["mimetype LIKE 'video/%'"],
        'audio'  => ["mimetype LIKE 'audio/%'"],
        'pdf'    => ["mimetype = 'application/pdf'"],
        'backup' => ["mimetype = 'application/vnd.moodle.backup'"],
        'office' => [
            "mimetype LIKE 'application/vnd.ms-powerpoint%'",
            "mimetype LIKE 'application/vnd.openxmlformats-officedocument.presentationml%'",
            "mimetype LIKE 'application/vnd.ms-excel%'",
            "mimetype LIKE 'application/vnd.openxmlformats-officedocument.spreadsheetml%'",
            "mimetype LIKE 'application/msword%'",
            "mimetype LIKE 'application/vnd.openxmlformats-officedocument.wordprocessingml%'",
        ],
        'zip'    => [
            "mimetype = 'application/zip'",
            "mimetype = 'application/x-zip-compressed'",
        ],
    ];
    if (isset($mimePatterns[$cat])) {
        $catFilter = ' AND (' . implode(' OR ', $mimePatterns[$cat]) . ')';
    }
}

$sortSql = match($sort) {
    'impact' => 'MAX(filesize) DESC', // fallback if no cache
    'name'   => 'MAX(filename) ASC',
    default  => 'MAX(filesize) DESC',
};

// For impact sort, join to cache table.
if ($sort === 'impact') {
    $filesql = "SELECT f.contenthash,
                       MAX(f.filename)  AS filename,
                       MAX(f.mimetype)  AS mimetype,
                       MAX(f.filesize)  AS filesize,
                       MAX(f.component) AS component,
                       COUNT(f.id)      AS usagecount,
                       MAX(c.impactscore) AS impactscore,
                       MAX(c.recommendations) AS recommendations,
                       MAX(c.imagewidth)  AS imagewidth,
                       MAX(c.imageheight) AS imageheight
                  FROM {files} f
             LEFT JOIN {local_mediaoptimiser_cache} c ON c.contenthash = f.contenthash
                 WHERE $where $catFilter
              GROUP BY f.contenthash
              ORDER BY MAX(COALESCE(c.impactscore, 0)) DESC, MAX(f.filesize) DESC";
} else {
    $filesql = "SELECT contenthash,
                       MAX(filename)  AS filename,
                       MAX(mimetype)  AS mimetype,
                       MAX(filesize)  AS filesize,
                       MAX(component) AS component,
                       COUNT(*)       AS usagecount,
                       NULL AS impactscore,
                       NULL AS recommendations,
                       NULL AS imagewidth,
                       NULL AS imageheight
                  FROM {files}
                 WHERE $where $catFilter
              GROUP BY contenthash
              ORDER BY $sortSql";
}

$totalcount = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT contenthash) FROM {files} WHERE $where $catFilter",
    $params
);

$files = $DB->get_records_sql($filesql, $params, $page * $perpage, $perpage);

// ── Output ─────────────────────────────────────────────────────────────────
echo $OUTPUT->header();

$dashurl = new moodle_url('/local/mediaoptimiser/index.php');

$categoryLinks = [
    ''       => 'All',
    'image'  => 'Images',
    'video'  => 'Videos',
    'audio'  => 'Audio',
    'pdf'    => 'PDFs',
    'office' => 'Office',
    'zip'    => 'ZIP / SCORM',
    'backup' => 'Backups',
];
?>
<div class="local-mediaoptimiser">

<style>
.mo-section-heading{font-size:1.1rem;font-weight:600;margin:0 0 14px;padding-bottom:6px;border-bottom:2px solid #e9ecef;}
.mo-badge-score{display:inline-block;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:700;color:#fff;}
.mo-badge-danger{background:#dc3545;}
.mo-badge-warning{background:#fd7e14;}
.mo-badge-info{background:#0dcaf0;color:#000;}
.mo-badge-success{background:#198754;}
.mo-rec{font-size:.78rem;padding:2px 0;color:#495057;}
.mo-rec::before{content:"✓ ";color:#198754;font-weight:700;}
.mo-filter-bar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px;}
</style>

<div style="margin-bottom:16px;">
    <a href="<?php echo $dashurl; ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa fa-arrow-left mr-1"></i>Dashboard
    </a>
</div>

<h5 class="mo-section-heading">
    <i class="fa fa-folder-open mr-2 text-primary"></i>
    File Browser
    <small class="text-muted ml-2" style="font-size:.8rem;font-weight:400;">
        <?php echo number_format($totalcount); ?> unique files
        <?php if ($cat): ?> in <?php echo htmlspecialchars(local_mediaoptimiser_category_label($cat)); ?><?php endif; ?>
    </small>
</h5>

<!-- Category filter -->
<div class="mo-filter-bar">
    <strong class="align-self-center" style="font-size:.85rem;">Filter:</strong>
    <?php foreach ($categoryLinks as $key => $label): ?>
        <?php $active = ($cat === $key) ? 'btn-primary' : 'btn-outline-secondary'; ?>
        <a href="<?php echo new moodle_url('/local/mediaoptimiser/files.php', ['cat' => $key, 'sort' => $sort]); ?>"
           class="btn btn-sm <?php echo $active; ?>">
            <?php echo htmlspecialchars($label); ?>
        </a>
    <?php endforeach; ?>

    <div style="flex:1;"></div>
    <strong class="align-self-center" style="font-size:.85rem;">Sort:</strong>
    <?php
    foreach (['size' => 'Largest First', 'impact' => 'Highest Impact', 'name' => 'Name A–Z'] as $key => $label):
        $active = ($sort === $key) ? 'btn-info' : 'btn-outline-secondary';
    ?>
        <a href="<?php echo new moodle_url('/local/mediaoptimiser/files.php', ['cat' => $cat, 'sort' => $key]); ?>"
           class="btn btn-sm <?php echo $active; ?>">
            <?php echo htmlspecialchars($label); ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- File table -->
<?php if ($files): ?>
<div class="table-responsive">
<table class="table table-sm table-hover generaltable">
    <thead class="thead-light">
        <tr>
            <th>Filename</th>
            <th>MIME Type</th>
            <th class="text-right">Size</th>
            <th class="text-right">Uses</th>
            <th>Component</th>
            <th class="text-center">Impact</th>
            <th>Recommendations</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($files as $f): ?>
        <?php
        $score  = isset($f->impactscore) && $f->impactscore !== null
            ? (int)$f->impactscore
            : null;
        $recs   = isset($f->recommendations) && $f->recommendations !== null
            ? (json_decode($f->recommendations, true) ?: [])
            : local_mediaoptimiser_recommendations($f->mimetype, (int)$f->filesize, 0, 0, (int)$f->usagecount);
        $col    = $score !== null ? local_mediaoptimiser_score_colour($score) : 'secondary';
        ?>
        <tr>
            <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                title="<?php echo s($f->filename); ?>">
                <i class="fa <?php echo local_mediaoptimiser_category_icon(local_mediaoptimiser_get_category($f->mimetype)); ?> mr-1 text-muted"></i>
                <?php echo s($f->filename); ?>
            </td>
            <td><small class="text-muted"><?php echo s($f->mimetype); ?></small></td>
            <td class="text-right font-weight-bold">
                <?php echo local_mediaoptimiser_format_bytes((int)$f->filesize); ?>
            </td>
            <td class="text-right"><?php echo (int)$f->usagecount; ?></td>
            <td><small class="text-muted"><?php echo s($f->component); ?></small></td>
            <td class="text-center">
                <?php if ($score !== null): ?>
                    <span class="mo-badge-score mo-badge-<?php echo $col; ?>"><?php echo $score; ?></span>
                <?php else: ?>
                    <span class="text-muted" title="Run analysis to get score">—</span>
                <?php endif; ?>
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

<!-- Pagination -->
<?php
$pager = new paging_bar($totalcount, $page, $perpage,
    new moodle_url('/local/mediaoptimiser/files.php', ['cat' => $cat, 'sort' => $sort]));
echo $OUTPUT->render($pager);
?>

<?php else: ?>
    <div class="alert alert-info">No files found for the selected filter.</div>
<?php endif; ?>

</div>
<?php
echo $OUTPUT->footer();
