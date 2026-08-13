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
 * Language strings for Media Optimiser plugin.
 *
 * @package    local_mediaoptimiser
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']                  = 'Media Optimiser';
$string['plugindashboard']             = 'Media Optimiser Dashboard';
$string['pluginreports']               = 'File Reports';
$string['pluginfiles']                 = 'File Browser';

// Dashboard strings.
$string['health_overview']             = 'Site Health Overview';
$string['total_unique_files']          = 'Unique Files';
$string['total_physical_storage']      = 'Physical Storage';
$string['total_logical_storage']       = 'Logical Storage (incl. duplicates)';
$string['duplicate_savings']           = 'Saved by Moodle Deduplication';
$string['potential_savings']           = 'Potential Optimisation Savings';
$string['duplicate_files']             = 'Files with Duplicates';
$string['largest_files']               = 'Largest Files';
$string['highest_impact']              = 'Highest Impact Files';
$string['file_type_breakdown']         = 'Storage by File Type';
$string['recommendations']             = 'Optimisation Recommendations';
$string['impact_score']                = 'Impact Score';
$string['filename']                    = 'Filename';
$string['filetype']                    = 'File Type';
$string['filesize']                    = 'Size';
$string['usage_count']                 = 'Uses';
$string['component']                   = 'Component';
$string['category_image']              = 'Images';
$string['category_video']              = 'Videos';
$string['category_audio']              = 'Audio';
$string['category_pdf']                = 'PDFs';
$string['category_office']             = 'Office Documents';
$string['category_zip']                = 'ZIP Archives';
$string['category_backup']             = 'Moodle Backups';
$string['category_scorm']              = 'SCORM Packages';
$string['category_other']              = 'Other Files';
$string['last_analysed']               = 'Last Analysed';
$string['never_analysed']              = 'Not yet analysed';
$string['run_analysis']                = 'Run Analysis Now';
$string['analysis_queued']             = 'Analysis has been queued and will run during the next cron cycle.';
$string['view_all']                    = 'View All';
$string['no_files']                    = 'No files found.';

// Impact score reasons.
$string['impact_large']                = 'Large file';
$string['impact_highusage']            = 'Used in many locations';
$string['impact_duplicate']            = 'Has duplicates';
$string['impact_unoptimised_image']    = 'Unoptimised image format';
$string['impact_unoptimised_audio']    = 'Unoptimised audio format';
$string['impact_unoptimised_video']    = 'Unoptimised video format';

// Recommendations.
$string['rec_compress_jpeg']           = 'Compress JPEG';
$string['rec_convert_png_webp']        = 'Convert PNG to WebP';
$string['rec_convert_bmp_webp']        = 'Convert BMP/TIFF to WebP';
$string['rec_reduce_audio_bitrate']    = 'Reduce audio bitrate';
$string['rec_transcode_video']         = 'Transcode video to H.264/WebM';
$string['rec_host_video_cdn']          = 'Host video on CDN instead of Moodle';
$string['rec_compress_pdf']            = 'Compress PDF';
$string['rec_review_large_zip']        = 'Review ZIP for large embedded media';
$string['rec_review_backup']           = 'Review or delete old backup';
$string['rec_remove_duplicate']        = 'Remove duplicate instances';

// Capabilities.
$string['mediaoptimiser:viewdashboard'] = 'View Media Optimiser dashboard';
$string['mediaoptimiser:manage']        = 'Run Media Optimiser analysis and optimisations';

// Task strings.
$string['task_analyse_files']          = 'Analyse site files for optimisation opportunities';

// Privacy.
$string['privacy:metadata']            = 'The Media Optimiser plugin stores cached analysis results for files (by content hash only, not user data).';

// Settings.
$string['settings_excludedrafts']      = 'Exclude draft files';
$string['settings_excludedrafts_desc'] = 'Exclude files in the draft filearea from analysis (draft files are temporary and automatically cleaned up by Moodle).';
$string['settings_excludesystem']      = 'Exclude system files';
$string['settings_excludesystem_desc'] = 'Exclude files uploaded by Moodle core system components (themes, core icons, etc.).';
