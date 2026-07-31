<?php
/**
 * Media Optimiser - Site-wide file analysis and optimisation for Moodle.
 *
 * @package    local_mediaoptimiser
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_mediaoptimiser';
$plugin->version   = 2026071500005;
$plugin->requires  = 2022112800;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.4'; // FIX-XMLDB-DEFAULT (v1.0.3): Removed empty-string DEFAULT from NOTNULL CHAR fields in install.xml (contenthash, mimetype). Fixes XMLDB debugging warnings on Moodle sites running local_adminer.
$plugin->supported = [400, 500];
