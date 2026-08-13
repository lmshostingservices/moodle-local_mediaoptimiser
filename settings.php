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
 * Admin settings for Media Optimiser plugin.
 *
 * @package    local_mediaoptimiser
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

if ($hassiteconfig) {
    // Create a parent category so the nav tree shows a single collapsible entry.
    $category = new admin_category(
        'local_mediaoptimiser_cat',
        get_string('pluginname', 'local_mediaoptimiser')
    );
    $ADMIN->add('localplugins', $category);

    // Dashboard external page.
    $ADMIN->add('local_mediaoptimiser_cat', new admin_externalpage(
        'local_mediaoptimiser_dashboard',
        get_string('plugindashboard', 'local_mediaoptimiser'),
        new moodle_url('/local/mediaoptimiser/index.php'),
        'moodle/site:config'
    ));

    // File browser external page.
    $ADMIN->add('local_mediaoptimiser_cat', new admin_externalpage(
        'local_mediaoptimiser_files',
        get_string('pluginfiles', 'local_mediaoptimiser'),
        new moodle_url('/local/mediaoptimiser/files.php'),
        'moodle/site:config'
    ));

    // Reports external page.
    $ADMIN->add('local_mediaoptimiser_cat', new admin_externalpage(
        'local_mediaoptimiser_reports',
        get_string('pluginreports', 'local_mediaoptimiser'),
        new moodle_url('/local/mediaoptimiser/reports.php'),
        'moodle/site:config'
    ));

    // Settings page.
    $settings = new admin_settingpage(
        'local_mediaoptimiser_settings',
        get_string('settings', 'moodle')
    );
    $ADMIN->add('local_mediaoptimiser_cat', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_mediaoptimiser/excludedrafts',
        get_string('settings_excludedrafts', 'local_mediaoptimiser'),
        get_string('settings_excludedrafts_desc', 'local_mediaoptimiser'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_mediaoptimiser/excludesystem',
        get_string('settings_excludesystem', 'local_mediaoptimiser'),
        get_string('settings_excludesystem_desc', 'local_mediaoptimiser'),
        1
    ));
}
