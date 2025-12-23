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

use mod_coursesearch\output\search_form;
use mod_coursesearch\output\search_results;
use mod_coursesearch\output\search_result;

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$cs = optional_param('cs', 0, PARAM_INT);  // CourseSearch instance ID.
$query = optional_param('query', '', PARAM_TEXT); // Search query.
$filter = optional_param('filter', 'all', PARAM_ALPHA); // Content filter (title, content, description).
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

// Get the placeholder text from language string.
$placeholder = get_string('defaultplaceholder', 'coursesearch');

// Prepare filter options.
$filteroptions = [
    'all' => get_string('searchscope_all', 'coursesearch'),
    'forums' => get_string('searchscope_forums', 'coursesearch'),
];

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
    $rawresults = coursesearch_perform_search($query, $course, $filter);

    // Process results and create search_result objects.
    $resultobjects = [];

    foreach ($rawresults as $result) {
        // Process multilanguage tags in the result name.
        $resultname = isset($result['name']) ? coursesearch_process_multilang($result['name']) : '';
        // Strip any HTML tags from the result name for safety (names should be plain text).
        $resultname = strip_tags($resultname);

        // For sections, remove 'Section: ' prefix from the displayed name to avoid duplication.
        if ($result['modname'] === 'section') {
            $sectionprefix = get_string('section') . ': ';
            if (strpos($resultname, $sectionprefix) === 0) {
                $resultname = substr($resultname, strlen($sectionprefix));
            }
        }

        // Get the URL - use the original URL from search if valid, only fall back if missing.
        $resulturl = null;
        if (isset($result['url']) && $result['url'] instanceof moodle_url) {
            // Use the original URL from the search - it already has correct section parameter.
            $resulturl = $result['url'];
        } else if (isset($result['url']) && !empty($result['url'])) {
            // URL exists but isn't a moodle_url object.
            $resulturl = new moodle_url($result['url']);
        } else {
            // No URL - fall back to processing.
            $resulturl = coursesearch_process_result_url($result, $course);
        }

        // Only add highlight parameter for content/description matches, NOT for title matches.
        // Highlighting titles on the page makes no sense - the title is already visible.
        $matchtype = isset($result['match']) ? $result['match'] : '';
        $istitlematch = (stripos($matchtype, 'title') !== false);

        if (!$istitlematch && $resulturl instanceof moodle_url && !empty($query)) {
            $params = $resulturl->params();
            if (!isset($params['highlight'])) {
                $cleanquery = clean_param($query, PARAM_TEXT);
                $resulturl->param('highlight', $cleanquery);
            }
        }

        // Process snippet.
        $snippet = '';
        if (isset($result['snippet']) && !empty($result['snippet'])) {
            $snippet = format_text($result['snippet'], FORMAT_HTML, ['noclean' => false, 'para' => false]);
        }

        // Get match type string.
        $matchtype = isset($result['match']) ? s($result['match']) : '';

        // Get forum name if applicable.
        $forumname = null;
        if ($result['modname'] === 'forum' && isset($result['forum_name'])) {
            $forumname = s(coursesearch_process_multilang($result['forum_name']));
        }

        // Get icon URL.
        $iconurl = $result['icon'] ?? '';

        // Get section info.
        $sectionnumber = $result['section_number'] ?? 0;
        $sectionname = $result['section_name'] ?? '';

        // Get subsection info.
        $issubsection = $result['is_subsection'] ?? false;
        $parentsectionnumber = $result['parent_section_number'] ?? null;
        $parentsectionname = $result['parent_section_name'] ?? null;

        // Create search_result object.
        $resultobjects[] = new search_result(
            $resultname,
            $resulturl,
            $result['modname'],
            $iconurl,
            $snippet,
            $matchtype,
            $forumname,
            $sectionnumber,
            $sectionname,
            $issubsection,
            $parentsectionnumber,
            $parentsectionname
        );
    }

    // Create base URL for pagination (includes current query and filter).
    $baseurl = new moodle_url('/mod/coursesearch/view.php', [
        'id' => $cm->id,
        'query' => $query,
        'filter' => $filter,
    ]);

    // Create and render search results with pagination.
    $perpage = get_config('mod_coursesearch', 'resultsperpage') ?: 10;
    $searchresults = new search_results($query, $resultobjects, $page, $perpage, $baseurl);
    echo $OUTPUT->render($searchresults);

    // Load the resultlinks AMD module to handle click interception if there are results.
    if (!empty($resultobjects)) {
        $PAGE->requires->js_call_amd('mod_coursesearch/resultlinks', 'init');
    }
}

// Finish the page.
echo $OUTPUT->footer();

/**
 * Process and fix result URL (fallback only - called when result has no valid URL)
 *
 * Note: Highlight parameter is NOT added here - it's handled conditionally in the main loop
 * based on match type (only for content/description matches, not title matches).
 *
 * @param array $result The search result array
 * @param object $course The course object
 * @return moodle_url The processed URL
 */
function coursesearch_process_result_url($result, $course) {
    global $DB;

    $modname = $result['modname'] ?? '';

    // For sections, use the section_number if available.
    if ($modname === 'section') {
        $sectionnumber = $result['section_number'] ?? null;
        if ($sectionnumber !== null) {
            return new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $sectionnumber]);
        }
        // Fallback: try to parse from name.
        if (preg_match('/(\d+)/', $result['name'] ?? '', $matches)) {
            return new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $matches[1]]);
        }
        return new moodle_url('/course/view.php', ['id' => $course->id]);
    }

    // For labels/html, create URL with section and anchor.
    if (($modname === 'label' || $modname === 'html') && isset($result['cmid'])) {
        $sectionnum = $result['section_number'] ?? 0;
        $urlparams = ['id' => $course->id, 'section' => $sectionnum];
        $moduleurl = new moodle_url('/course/view.php', $urlparams);
        $moduleurl->set_anchor('module-' . $result['cmid']);
        return $moduleurl;
    }

    // For other modules with cmid, try to get their URL.
    if (isset($result['cmid'])) {
        try {
            $modinfo = get_fast_modinfo($course);
            $cmobj = $modinfo->get_cm($result['cmid']);
            if ($cmobj && $cmobj->url) {
                return $cmobj->url;
            }
        } catch (Exception $e) {
            // CM not found, fall through to default.
            debugging('CM not found: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // Default to course page.
    return new moodle_url('/course/view.php', ['id' => $course->id]);
}
