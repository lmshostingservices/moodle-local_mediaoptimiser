<?php
/**
 * Adhoc task: on-demand file analysis triggered from the dashboard.
 *
 * @package    local_mediaoptimiser
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mediaoptimiser\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/mediaoptimiser/lib.php');

class analyse_files_adhoc extends \core\task\adhoc_task {

    public function execute(): void {
        local_mediaoptimiser_run_analysis();
    }
}
