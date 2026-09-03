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
 * Displays the course search interface
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/coursesearch/lib.php');
require_once($CFG->dirroot . '/mod/coursesearch/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

use mod_coursesearch\local\defaults;
use mod_coursesearch\output\search_form;
use mod_coursesearch\output\search_results;
use mod_coursesearch\output\search_result;

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$cs = optional_param('cs', 0, PARAM_INT);  // CourseSearch instance ID.
$query = optional_param('query', '', PARAM_TEXT); // Search query.
$filter = optional_param('filter', 'all', PARAM_ALPHA); // Content filter (title, content, description).
$modtypes = optional_param_array('modtypes', [], PARAM_ALPHANUMEXT); // Selected module types.
$page = optional_param('page', 0, PARAM_INT); // Pagination page number (0-indexed).

// Validate filter parameter against whitelist to prevent injection.
$allowedfilters = ['all', 'title', 'content', 'description', 'sections', 'activities', 'resources', 'forums'];
if (!in_array($filter, $allowedfilters)) {
    $filter = 'all'; // Default to 'all' if invalid filter provided.
}

// Trim whitespace from the search query.
$query = trim($query);

// Get the course module.
if ($id) {
    $cm = get_coursemodule_from_id('coursesearch', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $coursesearch = $DB->get_record('coursesearch', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($cs) {
    $coursesearch = $DB->get_record('coursesearch', ['id' => $cs], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $coursesearch->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('coursesearch', $coursesearch->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingidandcmid', 'coursesearch');
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/coursesearch:view', $context);

// Completion and trigger events.
coursesearch_view($coursesearch, $course, $cm, $context);

// Set up the page.
$PAGE->set_url('/mod/coursesearch/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($coursesearch->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Get the placeholder text: the instance value wins, otherwise the site default.
$placeholder = !empty($coursesearch->placeholder) ? $coursesearch->placeholder : get_string('defaultplaceholder', 'coursesearch');

// Prepare filter options (none for now; scope selector removed from UI).
$filteroptions = [];

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
    $label = $mod->modfullname ?? $mod->modname;
    $modtypeoptions[$mod->modname] = $label;
}
if (!empty($modtypeoptions)) {
    asort($modtypeoptions, SORT_NATURAL | SORT_FLAG_CASE);
}

// Keep only valid selected module types.
$selectedmodtypes = [];
if (!empty($modtypes)) {
    $selectedmodtypes = array_values(array_intersect($modtypes, array_keys($modtypeoptions)));
}

// Create the form URL.
$formurl = new moodle_url('/mod/coursesearch/view.php', ['id' => $cm->id]);

// Prepare intro content.
$intro = '';
if (!empty($coursesearch->intro)) {
    $intro = format_module_intro('coursesearch', $coursesearch, $cm->id);
}

// Create the search form renderable.
$searchform = new search_form(
    $formurl,
    $cm->id,
    $placeholder,
    $query,
    $filter,
    false, // Not embedded.
    $filteroptions,
    $modtypeoptions,
    $selectedmodtypes,
    $intro
);

// Output starts here.
echo $OUTPUT->header();

// Display the search form (includes intro if set).
echo $OUTPUT->render($searchform);

// Display search results if a query was submitted.
if (!empty($query)) {
    // Trigger the search event.
    $params = [
        'context' => $context,
        'objectid' => $coursesearch->id,
        'other' => [
            'query' => $query,
        ],
    ];
    $event = \mod_coursesearch\event\course_searched::create($params);
    $event->trigger();

    // Include the filter parameter in the search.
    $rawresults = coursesearch_perform_search($query, $course, $filter, $selectedmodtypes);

    // Build renderable result objects (shared with the course-level search page).
    $highlightenabled = coursesearch_is_highlight_enabled();
    $resultobjects = coursesearch_build_result_objects($rawresults, $course, $query);

    // Create base URL for pagination (includes current query and filter).
    $baseurl = new moodle_url('/mod/coursesearch/view.php', [
        'id' => $cm->id,
        'query' => $query,
        'filter' => $filter,
    ]);
    foreach ($selectedmodtypes as $modtype) {
        $baseurl->param('modtypes[]', $modtype);
    }

    // Get grouping setting (default to 1 if not set for backward compatibility).
    $grouped = isset($coursesearch->grouped) ? (bool)$coursesearch->grouped : defaults::grouped();

    // Create and render search results with pagination.
    $perpage = get_config('mod_coursesearch', 'resultsperpage') ?: 10;
    $searchresults = new search_results($query, $resultobjects, $page, $perpage, $baseurl, $grouped);
    echo $OUTPUT->render($searchresults);

    // Load the resultlinks AMD module to handle click interception if there are results.
    if (!empty($resultobjects)) {
        // Only needed when highlight/scroll feature is enabled (it persists highlight data across navigation).
        if ($highlightenabled) {
            $PAGE->requires->js_call_amd('mod_coursesearch/resultlinks', 'init');
        }
        // Load the groups module to handle expand/collapse of grouped results. It is lightweight,
        // so we always load it when there are results rather than scanning for grouped ones.
        $PAGE->requires->js_call_amd('mod_coursesearch/resultgroups', 'init');
    }
}

// Finish the page.
echo $OUTPUT->footer();


