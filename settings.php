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
 * Administration settings for the coursesearch module.
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Enable/disable scrolling and highlighting feature.
    $settings->add(new admin_setting_configcheckbox(
        'mod_coursesearch/enablehighlight',
        get_string('enablehighlight', 'coursesearch'),
        get_string('enablehighlight_desc', 'coursesearch'),
        1
    ));

    // Results per page setting.
    $settings->add(new admin_setting_configtext(
        'mod_coursesearch/resultsperpage',
        get_string('resultsperpage', 'coursesearch'),
        get_string('resultsperpage_desc', 'coursesearch'),
        10,
        PARAM_INT
    ));
}
