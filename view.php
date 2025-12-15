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

// Output starts here.
echo $OUTPUT->header();

// Display intro if set.
if (!empty($coursesearch->intro)) {
    echo $OUTPUT->box(format_module_intro('coursesearch', $coursesearch, $cm->id), 'generalbox', 'intro');
}

// Display the search form.
echo html_writer::start_div('coursesearch-container');
$formurl = new moodle_url('/mod/coursesearch/view.php', ['id' => $cm->id]);
$formattrs = ['action' => $formurl, 'method' => 'get', 'class' => 'coursesearch-form'];
echo html_writer::start_tag('form', $formattrs);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);

// Add filter options above the search bar.
echo html_writer::start_div('coursesearch-filters mb-3');

// Create filter options.
$filteroptions = [
    'all' => get_string('searchscope_all', 'coursesearch'),
    'forums' => get_string('searchscope_forums', 'coursesearch'),
];

// Add filter label.
echo html_writer::tag('label', get_string('searchscope', 'coursesearch') . ':', ['class' => 'mr-2']);

// Create radio buttons for each filter option.
foreach ($filteroptions as $value => $label) {
    $attributes = [
        'type' => 'radio',
        'name' => 'filter',
        'id' => 'filter_' . $value,
        'value' => $value,
        'class' => 'mr-1',
    ];

    // Check the current filter option if it matches.
    if ($filter === $value) {
        $attributes['checked'] = 'checked';
    }

    echo html_writer::start_span('mr-3');
    echo html_writer::empty_tag('input', $attributes);
    echo html_writer::tag('label', $label, ['for' => 'filter_' . $value, 'class' => 'mr-2']);
    echo html_writer::end_span();
}

echo html_writer::end_div();

echo html_writer::start_div('input-group');
$inputattrs = [
    'type' => 'text',
    'name' => 'query',
    'value' => $query,
    'class' => 'form-control',
    'placeholder' => $placeholder,
];
echo html_writer::empty_tag('input', $inputattrs);
echo html_writer::start_div('input-group-append');
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::end_div();

// Display search results if a query was submitted.
if (!empty($query)) {
    echo $OUTPUT->heading(get_string('searchresultsfor', 'coursesearch', s($query)));

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
    $results = coursesearch_perform_search($query, $course, $filter);

    if (empty($results)) {
        echo html_writer::div(get_string('noresults', 'coursesearch', s($query)), 'coursesearch-no-results');
    } else {
        // Display the count of search results.
        $count = count($results);
        $resultcountobj = new stdClass();
        $resultcountobj->count = $count;
        $resultcountobj->query = s($query);
        echo html_writer::div(get_string('searchresultscount', 'coursesearch', $resultcountobj), 'coursesearch-results-count');

        echo html_writer::start_div('coursesearch-results');

        foreach ($results as $result) {
            echo html_writer::start_div('coursesearch-result');

            // Process multilanguage tags in the result name.
            $resultname = isset($result['name']) ? coursesearch_process_multilang($result['name']) : '';
            // Strip any HTML tags from the result name for safety (names should be plain text).
            $resultname = strip_tags($resultname);

            // Display the module icon and name.
            // For sections, use a custom icon with appropriate alt text.
            if ($result['modname'] === 'section') {
                // Use a better icon for sections.
                $iconurl = new moodle_url('/pix/i/folder.png');
                $icon = html_writer::img($iconurl, get_string('section'), ['class' => 'icon']);

                // Remove 'Section: ' prefix from the displayed name to avoid duplication.
                $sectionprefix = get_string('section') . ': ';
                if (strpos($resultname, $sectionprefix) === 0) {
                    $resultname = substr($resultname, strlen($sectionprefix));
                }
            } else {
                // For other module types, use the standard icon.
                $icon = html_writer::img($result['icon'], $result['modname'], ['class' => 'icon']);
            }

            // Ensure all results have a valid URL.
            // Check if URL exists and is valid - also check if it has an anchor (which we want to preserve).
            $urlexists = isset($result['url']) && !empty($result['url']);
            $urlhasanchor = false;
            if ($urlexists && $result['url'] instanceof moodle_url) {
                // Check if the URL has an anchor by checking the full URL output.
                $urlstring = $result['url']->out(true);
                $urlhasanchor = (strpos($urlstring, '#') !== false);
            }

            // Only fix URL if it doesn't exist, or if it exists but doesn't have an anchor and needs one.
            // Preserve URLs that already have anchors (they were set correctly in locallib.php).
            // For labels and html modules, they MUST have anchors, so if they don't, we need to add one.
            $needsanchor = isset($result['modname']) && ($result['modname'] === 'label' || $result['modname'] === 'html');
            if (!$urlexists || ($needsanchor && !$urlhasanchor)) {
                // For results without a URL, try to find the appropriate URL based on the result type and match.
                if (isset($result['modname'])) {
                    // If we have a stored cmid and it's a label/html, use it directly.
                    if (isset($result['cmid']) && ($result['modname'] === 'label' || $result['modname'] === 'html')) {
                        // Try to get section number from modinfo if available.
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
                        // Add highlight parameter if we have a query.
                        if (!empty($query)) {
                            // Clean the query parameter to prevent XSS - urlencode will encode it for URL.
                            $cleanquery = clean_param($query, PARAM_TEXT);
                            $urlparams['highlight'] = urlencode($cleanquery);
                        }
                        $moduleurl = new moodle_url('/course/view.php', $urlparams);
                        $moduleurl->set_anchor('module-' . $result['cmid']);
                        $result['url'] = $moduleurl;
                    } else {
                        $modinfo = get_fast_modinfo($course);

                        // If it's a section, try to find the section.
                        if ($result['modname'] === 'section') {
                            // Extract section number from the name if possible.
                            $sectionnumber = null;
                            if (preg_match('/Section\s+(\d+)/i', $result['name'], $matches)) {
                                $sectionnumber = $matches[1];
                            }

                            if ($sectionnumber !== null) {
                                $urlparams = ['id' => $course->id, 'section' => $sectionnumber];
                                $result['url'] = new moodle_url('/course/view.php', $urlparams);
                            } else {
                                // Try to find the section by name.
                                $sectionwhere = ['course' => $course->id];
                                $sections = $DB->get_records('course_sections', $sectionwhere, 'section', 'id, section, name');
                                foreach ($sections as $section) {
                                    // Clean up the section name for comparison.
                                    $cleansectionname = strip_tags(coursesearch_process_multilang($section->name));
                                    $cleanresultname = strip_tags($resultname);

                                    // Remove "Section: " prefix for comparison.
                                    $cleanresultname = str_replace(get_string('section') . ': ', '', $cleanresultname);

                                    $namesmatch = $cleansectionname === $cleanresultname;
                                    $namecontains = stripos($cleansectionname, $cleanresultname) !== false;
                                    if ($namesmatch || $namecontains) {
                                        $urlparams = ['id' => $course->id, 'section' => $section->section];
                                        $result['url'] = new moodle_url('/course/view.php', $urlparams);
                                        break;
                                    }
                                }
                            }
                        } else {
                            // For other module types, try to find the module by name.
                            foreach ($modinfo->get_cms() as $cmobj) {
                                // Clean up names for comparison.
                                $cleancmname = strip_tags(coursesearch_process_multilang($cmobj->name));
                                $cleanresultname = strip_tags($resultname);

                                $namesmatch = $cleancmname === $cleanresultname;
                                $namecontains = stripos($cleancmname, $cleanresultname) !== false;
                                if ($namesmatch || $namecontains) {
                                    // For labels and html modules, ensure we create a URL with anchor.
                                    if ($result['modname'] === 'label' || $result['modname'] === 'html') {
                                        // Include section parameter to ensure the correct section is displayed.
                                        $sectionnum = isset($cmobj->sectionnum) ? $cmobj->sectionnum : null;
                                        if ($sectionnum === null && isset($cmobj->section)) {
                                            $sectionnum = $cmobj->section;
                                        }
                                        $urlparams = ['id' => $course->id];
                                        if ($sectionnum !== null) {
                                            $urlparams['section'] = $sectionnum;
                                        }
                                        // Add highlight parameter if we have a query.
                                        if (!empty($query)) {
                                            // Clean the query parameter to prevent XSS.
                                            $cleanquery = clean_param($query, PARAM_TEXT);
                                            $urlparams['highlight'] = urlencode($cleanquery);
                                        }
                                        $moduleurl = new moodle_url('/course/view.php', $urlparams);
                                        $moduleurl->set_anchor('module-' . $cmobj->id);
                                        $result['url'] = $moduleurl;
                                    } else {
                                        // For other module types, use the module's URL.
                                        $result['url'] = $cmobj->url;
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }

                // If we still don't have a URL, default to the course page.
                if (!isset($result['url']) || empty($result['url'])) {
                    $result['url'] = new moodle_url('/course/view.php', ['id' => $course->id]);
                }
            }

            // Ensure highlight parameter is added if we have a query for ALL result URLs.
            // This includes course/view.php, mod/page/view.php, mod/forum/discuss.php, etc.
            if (isset($result['url']) && $result['url'] instanceof moodle_url && !empty($query)) {
                $urlpath = $result['url']->get_path();
                // Add highlight to course pages, page activities, and other module pages.
                $iscourseview = strpos($urlpath, '/course/view.php') !== false;
                $ispageview = strpos($urlpath, '/mod/page/view.php') !== false;
                $ismodview = strpos($urlpath, '/mod/') !== false;
                if ($iscourseview || $ispageview || $ismodview) {
                    // Check if highlight parameter is already present.
                    $params = $result['url']->params();
                    if (!isset($params['highlight'])) {
                        // Clean the query parameter to prevent XSS.
                        $cleanquery = clean_param($query, PARAM_TEXT);
                        $result['url']->param('highlight', $cleanquery);
                    }
                }
            }

            $name = html_writer::link($result['url'], $resultname);
            echo html_writer::div($icon . ' ' . $name, 'coursesearch-result-title');

            // Display forum information if available.
            if ($result['modname'] === 'forum' && isset($result['forum_name'])) {
                $forumname = coursesearch_process_multilang($result['forum_name']);
                echo html_writer::div(get_string('inforum', 'coursesearch', s($forumname)), 'coursesearch-result-forum');
            }

            // Display the snippet with highlighted search term.
            if (isset($result['snippet']) && !empty($result['snippet'])) {
                // The snippet may contain HTML from highlighting, so we use format_text with appropriate options.
                // This ensures any dangerous HTML is properly sanitized while preserving safe highlighting.
                $snippet = format_text($result['snippet'], FORMAT_HTML, ['noclean' => false, 'para' => false]);
                echo html_writer::div($snippet, 'coursesearch-result-snippet');
            }

            // Display what was matched (title, content, etc.).
            $matchtype = isset($result['match']) ? get_string('matchedin', 'coursesearch', s($result['match'])) : '';
            echo html_writer::div($matchtype, 'coursesearch-result-match');

            echo html_writer::end_div();
        }

        echo html_writer::end_div();
    }
}

// Finish the page.
echo $OUTPUT->footer();
