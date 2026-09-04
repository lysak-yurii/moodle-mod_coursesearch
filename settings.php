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

// Load custom admin setting classes.
require_once($CFG->dirroot . '/mod/coursesearch/classes/admin_setting/maxoccurrences.php');
require_once($CFG->dirroot . '/mod/coursesearch/classes/admin_setting/floatingwidgetoffset.php');

if ($ADMIN->fulltree) {
    global $DB;

    // Results display.
    $settings->add(new admin_setting_heading(
        'mod_coursesearch/settingsdisplay',
        get_string('settingsdisplay', 'coursesearch'),
        ''
    ));

    // Results per page setting.
    $settings->add(new admin_setting_configtext(
        'mod_coursesearch/resultsperpage',
        get_string('resultsperpage', 'coursesearch'),
        get_string('resultsperpage_desc', 'coursesearch'),
        10,
        PARAM_INT
    ));

    // Maximum occurrences per content item setting.
    $settings->add(new admin_setting_configtext_maxoccurrences(
        'mod_coursesearch/maxoccurrences',
        get_string('maxoccurrences', 'coursesearch'),
        get_string('maxoccurrences_desc', 'coursesearch'),
        5,
        PARAM_INT
    ));

    // Excluded placeholder patterns setting.
    $settings->add(new admin_setting_configtextarea(
        'mod_coursesearch/excludedplaceholders',
        get_string('excludedplaceholders', 'coursesearch'),
        get_string('excludedplaceholders_desc', 'coursesearch'),
        '@@[A-Z_]+@@[^\s]*'
    ));

    // Highlighting.
    $settings->add(new admin_setting_heading(
        'mod_coursesearch/settingshighlighting',
        get_string('settingshighlighting', 'coursesearch'),
        ''
    ));

    // Enable/disable scrolling and highlighting feature.
    $settings->add(new admin_setting_configcheckbox(
        'mod_coursesearch/enablehighlight',
        get_string('enablehighlight', 'coursesearch'),
        get_string('enablehighlight_desc', 'coursesearch'),
        1
    ));

    // Quick-access widget.
    $settings->add(new admin_setting_heading(
        'mod_coursesearch/settingswidget',
        get_string('settingswidget', 'coursesearch'),
        ''
    ));

    // Enable/disable floating quick-access widget.
    $settings->add(new admin_setting_configcheckbox(
        'mod_coursesearch/enablefloatingwidget',
        get_string('enablefloatingwidget', 'coursesearch'),
        get_string('enablefloatingwidget_desc', 'coursesearch'),
        1
    ));

    // Where the widget appears. 'withactivity' preserves the historical behaviour, so an
    // upgraded site sees no change until an admin opts in to 'allcourses'.
    $settings->add(new admin_setting_configselect(
        'mod_coursesearch/floatingwidgetscope',
        get_string('floatingwidgetscope', 'coursesearch'),
        get_string('floatingwidgetscope_desc', 'coursesearch'),
        'withactivity',
        [
            'withactivity' => get_string('floatingwidgetscope:withactivity', 'coursesearch'),
            'allcourses' => get_string('floatingwidgetscope:allcourses', 'coursesearch'),
        ]
    ));

    // Activity types the widget stays out of. Module names are stored rather than
    // {modules} ids, so the value stays readable and survives a config export to another site.
    $modulechoices = [];
    foreach ($DB->get_records('modules', ['visible' => 1], '', 'id, name') as $module) {
        $modulechoices[$module->name] = get_string('pluginname', 'mod_' . $module->name);
    }
    core_collator::asort($modulechoices);

    $settings->add(new admin_setting_configmultiselect(
        'mod_coursesearch/disabledmodules',
        get_string('disabledmodules', 'coursesearch'),
        get_string('disabledmodules_desc', 'coursesearch'),
        [],
        $modulechoices
    ));

    // Result layout for courses reached through the widget with no Course Search activity.
    // Only meaningful in 'allcourses' mode; an activity's own setting always wins over it.
    $settings->add(new admin_setting_configcheckbox(
        'mod_coursesearch/defaultgrouped',
        get_string('defaultgrouped', 'coursesearch'),
        get_string('defaultgrouped_desc', 'coursesearch'),
        1
    ));

    // Floating widget vertical offset setting.
    $settings->add(new admin_setting_configtext_floatingwidgetoffset(
        'mod_coursesearch/floatingwidgetverticaloffset',
        get_string('floatingwidgetverticaloffset', 'coursesearch'),
        get_string('floatingwidgetverticaloffset_desc', 'coursesearch'),
        80,
        PARAM_INT
    ));

    // Widget sub-options only apply when the widget itself is on, and the grouping default
    // only applies in 'all courses' mode.
    $settings->hide_if(
        'mod_coursesearch/floatingwidgetscope',
        'mod_coursesearch/enablefloatingwidget',
        'notchecked'
    );
    $settings->hide_if(
        'mod_coursesearch/disabledmodules',
        'mod_coursesearch/enablefloatingwidget',
        'notchecked'
    );
    $settings->hide_if(
        'mod_coursesearch/floatingwidgetverticaloffset',
        'mod_coursesearch/enablefloatingwidget',
        'notchecked'
    );
    $settings->hide_if(
        'mod_coursesearch/defaultgrouped',
        'mod_coursesearch/enablefloatingwidget',
        'notchecked'
    );
    $settings->hide_if(
        'mod_coursesearch/defaultgrouped',
        'mod_coursesearch/floatingwidgetscope',
        'neq',
        'allcourses'
    );
}
