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

        // Process URL - ensure all results have a valid URL.
        $resulturl = coursesearch_process_result_url($result, $course, $query);

        // Ensure highlight parameter is added for all result URLs.
        if ($resulturl instanceof moodle_url && !empty($query)) {
            $urlpath = $resulturl->get_path();
            $iscourseview = strpos($urlpath, '/course/view.php') !== false;
            $ispageview = strpos($urlpath, '/mod/page/view.php') !== false;
            $ismodview = strpos($urlpath, '/mod/') !== false;
            if ($iscourseview || $ispageview || $ismodview) {
                $params = $resulturl->params();
                if (!isset($params['highlight'])) {
                    $cleanquery = clean_param($query, PARAM_TEXT);
                    $resulturl->param('highlight', $cleanquery);
                }
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

        // Create search_result object.
        $resultobjects[] = new search_result(
            $resultname,
            $resulturl,
            $result['modname'],
            $iconurl,
            $snippet,
            $matchtype,
            $forumname
        );
    }

    // Create and render search results.
    $searchresults = new search_results($query, $resultobjects);
    echo $OUTPUT->render($searchresults);

    // Load the resultlinks AMD module to handle click interception if there are results.
    if (!empty($resultobjects)) {
        $PAGE->requires->js_call_amd('mod_coursesearch/resultlinks', 'init');
    }
}

// Finish the page.
echo $OUTPUT->footer();

/**
 * Process and fix result URL
 *
 * @param array $result The search result array
 * @param object $course The course object
 * @param string $query The search query
 * @return moodle_url The processed URL
 */
function coursesearch_process_result_url($result, $course, $query) {
    global $DB;

    // Check if URL exists and is valid.
    $urlexists = isset($result['url']) && !empty($result['url']);
    $urlhasanchor = false;
    if ($urlexists && $result['url'] instanceof moodle_url) {
        $urlstring = $result['url']->out(true);
        $urlhasanchor = (strpos($urlstring, '#') !== false);
    }

    // Determine if URL needs an anchor.
    $needsanchor = isset($result['modname']) && ($result['modname'] === 'label' || $result['modname'] === 'html');
    if (!$urlexists || ($needsanchor && !$urlhasanchor)) {
        if (isset($result['modname'])) {
            // If we have a stored cmid and it's a label/html, use it directly.
            if (isset($result['cmid']) && ($result['modname'] === 'label' || $result['modname'] === 'html')) {
                $modinfo = get_fast_modinfo($course);
                $cmobj = $modinfo->get_cm($result['cmid']);
                $sectionnum = isset($cmobj->sectionnum) ? $cmobj->sectionnum : null;
                if ($sectionnum === null && isset($cmobj->section)) {
                    $sectionnum = $cmobj->section;
                }
                $urlparams = ['id' => $course->id];
                if ($sectionnum !== null) {
                    $urlparams['section'] = $sectionnum;
                }
                if (!empty($query)) {
                    $cleanquery = clean_param($query, PARAM_TEXT);
                    $urlparams['highlight'] = urlencode($cleanquery);
                }
                $moduleurl = new moodle_url('/course/view.php', $urlparams);
                $moduleurl->set_anchor('module-' . $result['cmid']);
                return $moduleurl;
            } else {
                $modinfo = get_fast_modinfo($course);

                // If it's a section, try to find the section.
                if ($result['modname'] === 'section') {
                    $sectionnumber = null;
                    if (preg_match('/Section\s+(\d+)/i', $result['name'], $matches)) {
                        $sectionnumber = $matches[1];
                    }

                    if ($sectionnumber !== null) {
                        $urlparams = ['id' => $course->id, 'section' => $sectionnumber];
                        return new moodle_url('/course/view.php', $urlparams);
                    } else {
                        $sectionwhere = ['course' => $course->id];
                        $sections = $DB->get_records('course_sections', $sectionwhere, 'section', 'id, section, name');
                        $resultname = strip_tags(isset($result['name']) ? coursesearch_process_multilang($result['name']) : '');
                        $cleanresultname = str_replace(get_string('section') . ': ', '', $resultname);
                        foreach ($sections as $section) {
                            $cleansectionname = strip_tags(coursesearch_process_multilang($section->name));
                            $namesmatch = $cleansectionname === $cleanresultname;
                            $namecontains = stripos($cleansectionname, $cleanresultname) !== false;
                            if ($namesmatch || $namecontains) {
                                $urlparams = ['id' => $course->id, 'section' => $section->section];
                                return new moodle_url('/course/view.php', $urlparams);
                            }
                        }
                    }
                } else {
                    // For other module types, try to find the module by name.
                    $resultname = strip_tags(isset($result['name']) ? coursesearch_process_multilang($result['name']) : '');
                    foreach ($modinfo->get_cms() as $cmobj) {
                        $cleancmname = strip_tags(coursesearch_process_multilang($cmobj->name));
                        $namesmatch = $cleancmname === $resultname;
                        $namecontains = stripos($cleancmname, $resultname) !== false;
                        if ($namesmatch || $namecontains) {
                            if ($result['modname'] === 'label' || $result['modname'] === 'html') {
                                $sectionnum = isset($cmobj->sectionnum) ? $cmobj->sectionnum : null;
                                if ($sectionnum === null && isset($cmobj->section)) {
                                    $sectionnum = $cmobj->section;
                                }
                                $urlparams = ['id' => $course->id];
                                if ($sectionnum !== null) {
                                    $urlparams['section'] = $sectionnum;
                                }
                                if (!empty($query)) {
                                    $cleanquery = clean_param($query, PARAM_TEXT);
                                    $urlparams['highlight'] = urlencode($cleanquery);
                                }
                                $moduleurl = new moodle_url('/course/view.php', $urlparams);
                                $moduleurl->set_anchor('module-' . $cmobj->id);
                                return $moduleurl;
                            } else {
                                return $cmobj->url;
                            }
                        }
                    }
                }
            }
        }

        // If we still don't have a URL, default to the course page.
        return new moodle_url('/course/view.php', ['id' => $course->id]);
    }

    return $result['url'];
}
