<?php
/**
 * Privacy API implementation for Media Optimiser plugin.
 *
 * This plugin stores analysis data keyed by file content hash only — no user data is stored.
 *
 * @package    local_mediaoptimiser
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mediaoptimiser\privacy;

use core_privacy\local\metadata\collection;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\null_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_plugintype_link(
            'local_mediaoptimiser_cache',
            [],
            'privacy:metadata'
        );
        return $collection;
    }

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
