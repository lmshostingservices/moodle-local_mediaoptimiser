<?php
/**
 * Scheduled task: analyse all site files and populate the cache table.
 *
 * @package    local_mediaoptimiser
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mediaoptimiser\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/mediaoptimiser/lib.php');

class analyse_files extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_analyse_files', 'local_mediaoptimiser');
    }

    public function execute(): void {
        local_mediaoptimiser_run_analysis();
    }
}
