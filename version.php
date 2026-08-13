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
 * Media Optimiser - Site-wide file analysis and optimisation for Moodle.
 *
 * @package    local_mediaoptimiser
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_mediaoptimiser';
$plugin->version   = 2026071500;
$plugin->requires  = 2022112800;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.5'; // FIX-XMLDB-DEFAULT (v1.0.3): Removed empty-string DEFAULT from NOTNULL CHAR fields in install.xml (contenthash, mimetype). Fixes XMLDB debugging warnings on Moodle sites running local_adminer.
$plugin->supported = [400, 500];
