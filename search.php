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
 * Course-level search page
 *
 * Serves the quick-access widget in courses that have no Course Search activity, which is
 * only possible when the site administrator has set the widget scope to all courses.
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/coursesearch/lib.php');
require_once($CFG->dirroot . '/mod/coursesearch/locallib.php');

use mod_coursesearch\local\defaults;
use mod_coursesearch\output\search_form;
use mod_coursesearch\output\search_results;

$courseid = required_param('courseid', PARAM_INT);
$query = optional_param('query', '', PARAM_TEXT);
$filter = optional_param('filter', 'all', PARAM_ALPHA);
$modtypes = optional_param_array('modtypes', [], PARAM_ALPHANUMEXT);
$page = optional_param('page', 0, PARAM_INT);

// Validate filter parameter against whitelist to prevent injection.
$allowedfilters = ['all', 'title', 'content', 'description', 'sections', 'activities', 'resources', 'forums'];
if (!in_array($filter, $allowedfilters)) {
    $filter = 'all';
}
$query = trim($query);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
if ($course->id == SITEID) {
    throw new moodle_exception('coursemisconf');
}

require_course_login($course);
$context = context_course::instance($course->id);
require_capability('mod/coursesearch:view', $context);

// This page exists only while the widget is enabled site-wide. Otherwise the course's own
// Course Search activity is the entry point and this endpoint must not be reachable.
$widgetenabled = get_config('mod_coursesearch', 'enablefloatingwidget');
$scope = get_config('mod_coursesearch', 'floatingwidgetscope');
if (($widgetenabled !== null && (int)$widgetenabled === 0) || $scope !== 'allcourses') {
    throw new moodle_exception('sitewidesearchdisabled', 'coursesearch');
}

$pageurl = new moodle_url('/mod/coursesearch/search.php', ['courseid' => $course->id]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('searchcourse', 'coursesearch'));
$PAGE->set_heading(format_string($course->fullname));

// Prepare module type options for filtering.
$modtypeoptions = [];
$modinfo = get_fast_modinfo($course);
foreach ($modinfo->get_cms() as $mod) {
    if (!$mod->uservisible) {
        continue;
    }
    if ($mod->modname === 'subsection' || $mod->modname === 'coursesearch') {
        continue;
    }
    $modtypeoptions[$mod->modname] = $mod->modfullname ?? $mod->modname;
}
if (!empty($modtypeoptions)) {
    asort($modtypeoptions, SORT_NATURAL | SORT_FLAG_CASE);
}

// Keep only valid selected module types.
$selectedmodtypes = [];
if (!empty($modtypes)) {
    $selectedmodtypes = array_values(array_intersect($modtypes, array_keys($modtypeoptions)));
}

$searchform = new search_form(
    $pageurl,
    $course->id,
    get_string('defaultplaceholder', 'coursesearch'),
    $query,
    $filter,
    false, // Not embedded.
    [],
    $modtypeoptions,
    $selectedmodtypes,
    '',
    'courseid'
);

echo $OUTPUT->header();
echo $OUTPUT->render($searchform);

if (!empty($query)) {
    $rawresults = coursesearch_perform_search($query, $course, $filter, $selectedmodtypes);

    // Build renderable result objects (shared with the activity view).
    $highlightenabled = coursesearch_is_highlight_enabled();
    $resultobjects = coursesearch_build_result_objects($rawresults, $course, $query);

    // Create base URL for pagination (includes current query and filter).
    $baseurl = new moodle_url('/mod/coursesearch/search.php', [
        'courseid' => $course->id,
        'query' => $query,
        'filter' => $filter,
    ]);
    foreach ($selectedmodtypes as $modtype) {
        $baseurl->param('modtypes[]', $modtype);
    }

    // No activity instance here, so the site default decides the result layout.
    $perpage = get_config('mod_coursesearch', 'resultsperpage') ?: 10;
    $searchresults = new search_results($query, $resultobjects, $page, $perpage, $baseurl, defaults::grouped());
    echo $OUTPUT->render($searchresults);

    if (!empty($resultobjects)) {
        if ($highlightenabled) {
            $PAGE->requires->js_call_amd('mod_coursesearch/resultlinks', 'init');
        }
        $PAGE->requires->js_call_amd('mod_coursesearch/resultgroups', 'init');
    }
}

echo $OUTPUT->footer();
