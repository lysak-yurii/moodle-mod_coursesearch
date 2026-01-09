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

    // Group raw results by activity before processing.
    $groupedrawresults = coursesearch_group_raw_results_by_activity($rawresults, $course);

    // Highlight/scroll feature (admin setting). Default to enabled for backward compatibility.
    $enablehighlight = get_config('mod_coursesearch', 'enablehighlight');
    $highlightenabled = ($enablehighlight === null) ? true : ((int)$enablehighlight === 1);

    // Process results and create search_result objects.
    $resultobjects = [];

    foreach ($groupedrawresults as $result) {
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

        if ($highlightenabled && !$istitlematch && $resulturl instanceof moodle_url && !empty($query)) {
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

        // Check if this is a grouped result (has 'matches' key).
        if (isset($result['matches']) && is_array($result['matches']) && count($result['matches']) >= 2) {
            // This is a grouped result - create grouped search_result object.
            $matches = [];
            foreach ($result['matches'] as $matchdata) {
                // Process each match in the group.
                $matchresultname = isset($matchdata['name']) ? coursesearch_process_multilang($matchdata['name']) : '';
                $matchresultname = strip_tags($matchresultname);

                // Get URL for match.
                $matchurl = null;
                if (isset($matchdata['url']) && $matchdata['url'] instanceof moodle_url) {
                    $matchurl = $matchdata['url'];
                } else if (isset($matchdata['url']) && !empty($matchdata['url'])) {
                    $matchurl = new moodle_url($matchdata['url']);
                } else {
                    $matchurl = coursesearch_process_result_url($matchdata, $course);
                }

                // Add highlight if needed.
                $matchmatchtype = isset($matchdata['match']) ? $matchdata['match'] : '';
                $matchistitlematch = (stripos($matchmatchtype, 'title') !== false);
                if ($highlightenabled && !$matchistitlematch && $matchurl instanceof moodle_url && !empty($query)) {
                    $params = $matchurl->params();
                    if (!isset($params['highlight'])) {
                        $cleanquery = clean_param($query, PARAM_TEXT);
                        $matchurl->param('highlight', $cleanquery);
                    }
                }

                // Process snippet.
                $matchsnippet = '';
                if (isset($matchdata['snippet']) && !empty($matchdata['snippet'])) {
                    $matchsnippet = format_text($matchdata['snippet'], FORMAT_HTML, ['noclean' => false, 'para' => false]);
                }

                $matchmatchtype = isset($matchdata['match']) ? s($matchdata['match']) : '';
                $matchforumname = null;
                if (isset($matchdata['modname']) && $matchdata['modname'] === 'forum' && isset($matchdata['forum_name'])) {
                    $matchforumname = s(coursesearch_process_multilang($matchdata['forum_name']));
                }
                $matchiconurl = $matchdata['icon'] ?? '';
                $matchsectionnumber = $matchdata['section_number'] ?? 0;
                $matchsectionname = $matchdata['section_name'] ?? '';
                $matchissubsection = $matchdata['is_subsection'] ?? false;
                $matchparentsectionnumber = $matchdata['parent_section_number'] ?? null;
                $matchparentsectionname = $matchdata['parent_section_name'] ?? null;

                $matches[] = new search_result(
                    $matchresultname,
                    $matchurl,
                    $matchdata['modname'],
                    $matchiconurl,
                    $matchsnippet,
                    $matchmatchtype,
                    $matchforumname,
                    $matchsectionnumber,
                    $matchsectionname,
                    $matchissubsection,
                    $matchparentsectionnumber,
                    $matchparentsectionname
                );
            }

            // Create grouped result.
            // Use section info from the result array (from first match), not from processed variables.
            $activityname = $result['activityname'] ?? $resultname;
            $activityurl = isset($result['activityurl']) && $result['activityurl'] instanceof moodle_url
                ? $result['activityurl']
                : ($resulturl ?: new moodle_url('/course/view.php', ['id' => $course->id]));
            $activityicon = $result['activityicon'] ?? $iconurl;
            $activitymodname = $result['modname'] ?? 'unknown';

            // Get section info from the result array (preserved from grouping function).
            $groupsectionnumber = $result['section_number'] ?? $sectionnumber;
            $groupsectionname = $result['section_name'] ?? $sectionname;
            $groupissubsection = $result['is_subsection'] ?? $issubsection;
            $groupparentsectionnumber = $result['parent_section_number'] ?? $parentsectionnumber;
            $groupparentsectionname = $result['parent_section_name'] ?? $parentsectionname;

            $resultobjects[] = new search_result(
                $activityname,
                $activityurl,
                $activitymodname,
                $activityicon,
                '',
                '',
                $forumname,
                $groupsectionnumber,
                $groupsectionname,
                $groupissubsection,
                $groupparentsectionnumber,
                $groupparentsectionname,
                true, // Is grouped.
                count($matches), // Match count.
                $activityname, // Activity name.
                $activityurl, // Activity URL.
                $activityicon, // Activity icon.
                $matches // Matches.
            );
        } else {
            // Regular individual result.
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
    }

    // Create base URL for pagination (includes current query and filter).
    $baseurl = new moodle_url('/mod/coursesearch/view.php', [
        'id' => $cm->id,
        'query' => $query,
        'filter' => $filter,
    ]);

    // Get grouping setting (default to 1 if not set for backward compatibility).
    $grouped = isset($coursesearch->grouped) ? (bool)$coursesearch->grouped : true;

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
        // Check if any results are grouped and load the groups module.
        $hasgrouped = false;
        foreach ($resultobjects as $result) {
            if ($result instanceof search_result && method_exists($result, 'isgrouped')) {
                // Use reflection to check if grouped (property is protected).
                $reflection = new \ReflectionClass($result);
                if ($reflection->hasProperty('isgrouped')) {
                    $prop = $reflection->getProperty('isgrouped');
                    $prop->setAccessible(true);
                    if ($prop->getValue($result)) {
                        $hasgrouped = true;
                        break;
                    }
                }
            }
        }
        // Simpler check: look for grouped results by checking if we have any groups.
        // Actually, let's just always load it if we have results - it's lightweight.
        $PAGE->requires->js_call_amd('mod_coursesearch/resultgroups', 'init');
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

/**
 * Group raw search results by activity
 *
 * Groups multiple matches from the same activity. Activities with only 1 match
 * are kept as individual results. Returns array with either individual results
 * or grouped results (with 'matches' key containing array of match data).
 *
 * @param array $rawresults Array of raw result arrays from search
 * @param object $course The course object
 * @return array Array of results (individual or grouped with 'matches' key)
 */
function coursesearch_group_raw_results_by_activity(array $rawresults, $course): array {
    if (empty($rawresults)) {
        return [];
    }

    // Group results by activity identifier.
    $groups = [];
    $individual = [];

    foreach ($rawresults as $result) {
        if (!is_array($result)) {
            $individual[] = $result;
            continue;
        }

        $modname = $result['modname'] ?? 'unknown';

        // Skip grouping for sections - they're already handled by section grouping.
        if ($modname === 'section' || $modname === 'subsection') {
            $individual[] = $result;
            continue;
        }

        // Get activity identifier - prefer cmid if available.
        $activitykey = null;
        $cmid = isset($result['cmid']) ? (int)$result['cmid'] : null;

        // Only use cmid if it's a valid positive integer.
        if ($cmid && $cmid > 0) {
            // Use cmid as the grouping key (most reliable).
            $activitykey = 'cmid_' . $cmid;
        } else {
            // Fallback: normalize URL for grouping.
            $url = $result['url'] ?? null;
            if ($url instanceof moodle_url) {
                $urlparts = parse_url($url->out(false));
                $baseurl = '';
                if (isset($urlparts['scheme'])) {
                    $baseurl .= $urlparts['scheme'] . '://';
                }
                if (isset($urlparts['host'])) {
                    $baseurl .= $urlparts['host'];
                }
                if (isset($urlparts['port'])) {
                    $baseurl .= ':' . $urlparts['port'];
                }
                if (isset($urlparts['path'])) {
                    $baseurl .= $urlparts['path'];
                }
                // Remove query params and fragments for grouping.
                $activitykey = 'url_' . $modname . '_' . md5($baseurl);
            } else {
                // Last resort: use modname + name.
                $name = $result['name'] ?? '';
                $activitykey = 'name_' . $modname . '_' . md5($name);
            }
        }

        if ($activitykey) {
            if (!isset($groups[$activitykey])) {
                $groups[$activitykey] = [];
            }
            $groups[$activitykey][] = $result;
        } else {
            $individual[] = $result;
        }
    }

    // Process groups: create grouped structure for activities with 2+ matches.
    $groupedresults = [];
    foreach ($groups as $activitykey => $matches) {
        if (count($matches) >= 2) {
            // Create grouped result structure.
            $firstmatch = $matches[0];

            // Ensure we have valid data for grouping.
            if (empty($firstmatch['modname'])) {
                // Skip if modname is missing - can't group without knowing the activity type.
                $individual = array_merge($individual, $matches);
                continue;
            }

            // Extract common activity info.
            $activityname = $firstmatch['name'] ?? '';
            $activityurl = $firstmatch['url'] ?? null;
            $activityicon = $firstmatch['icon'] ?? '';
            $modname = $firstmatch['modname'] ?? 'unknown';

            // For forums, use forum name as activity name.
            if ($modname === 'forum' && isset($firstmatch['forum_name'])) {
                $activityname = $firstmatch['forum_name'];
            }

            // Try to get main activity URL (without post-specific params).
            if ($activityurl instanceof moodle_url && $modname === 'forum') {
                // For forums, try to get the forum view URL instead of post URL.
                // The cmid should be in the result.
                if (isset($firstmatch['cmid'])) {
                    try {
                        $modinfo = get_fast_modinfo($course);
                        $cm = $modinfo->get_cm($firstmatch['cmid']);
                        if ($cm && $cm->url) {
                            $activityurl = $cm->url;
                        }
                    } catch (Exception $e) {
                        // Keep original URL if we can't get module URL.
                        debugging('CM not found: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    }
                }
            }

            // Create grouped result entry.
            // Preserve all fields from first match, especially section info and modname.
            $groupedresult = $firstmatch;

            // Validate that we have matches to group.
            if (empty($matches) || count($matches) < 2) {
                // Shouldn't happen, but if it does, treat as individual results.
                $individual = array_merge($individual, $matches);
                continue;
            }

            $groupedresult['matches'] = $matches;
            $groupedresult['activityname'] = $activityname;
            $groupedresult['activityurl'] = $activityurl;
            $groupedresult['activityicon'] = $activityicon;

            // Ensure modname is preserved (critical for template rendering).
            if (!isset($groupedresult['modname']) || empty($groupedresult['modname'])) {
                $groupedresult['modname'] = $modname;
            }

            // Ensure we have a valid activity name.
            if (empty($groupedresult['activityname'])) {
                $groupedresult['activityname'] = $firstmatch['name'] ?? get_string('activity', 'mod_coursesearch');
            }

            // Ensure section info is preserved (get from first match if available).
            if (!isset($groupedresult['section_number']) && isset($matches[0]['section_number'])) {
                $groupedresult['section_number'] = $matches[0]['section_number'];
            }
            if (!isset($groupedresult['section_name']) && isset($matches[0]['section_name'])) {
                $groupedresult['section_name'] = $matches[0]['section_name'];
            }
            if (!isset($groupedresult['is_subsection']) && isset($matches[0]['is_subsection'])) {
                $groupedresult['is_subsection'] = $matches[0]['is_subsection'];
            }
            if (!isset($groupedresult['parent_section_number']) && isset($matches[0]['parent_section_number'])) {
                $groupedresult['parent_section_number'] = $matches[0]['parent_section_number'];
            }
            if (!isset($groupedresult['parent_section_name']) && isset($matches[0]['parent_section_name'])) {
                $groupedresult['parent_section_name'] = $matches[0]['parent_section_name'];
            }

            $groupedresults[] = $groupedresult;
        } else {
            // Single match - add to individual results.
            $individual = array_merge($individual, $matches);
        }
    }

    // Combine grouped and individual results.
    return array_merge($groupedresults, $individual);
}

/**
 * Group search results by activity (legacy function - kept for compatibility)
 *
 * @param array $results Array of search_result objects
 * @param object $course The course object
 * @return array Array of search_result objects (individual or grouped)
 */
function coursesearch_group_results_by_activity(array $results, $course): array {
    if (empty($results)) {
        return [];
    }

    // Group results by activity identifier.
    $groups = [];
    $individual = [];

    foreach ($results as $result) {
        if (!($result instanceof \mod_coursesearch\output\search_result)) {
            // Skip non-result objects.
            $individual[] = $result;
            continue;
        }

        // Use reflection to access protected properties.
        $reflection = new \ReflectionClass($result);

        // Get modname.
        $modnameprop = $reflection->getProperty('modname');
        $modnameprop->setAccessible(true);
        $modname = $modnameprop->getValue($result);

        // Skip grouping for sections - they're already handled by section grouping.
        if ($modname === 'section' || $modname === 'subsection') {
            $individual[] = $result;
            continue;
        }

        // Get activity identifier - use cmid if available, otherwise normalize URL.
        $activitykey = null;

        // Get URL.
        $urlprop = $reflection->getProperty('url');
        $urlprop->setAccessible(true);
        $url = $urlprop->getValue($result);

        if ($url instanceof moodle_url) {
            $params = $url->params();
            // Check for 'id' parameter (cmid) in URL - this is the most reliable identifier.
            if (isset($params['id'])) {
                $cmid = (int)$params['id'];
                $activitykey = 'cmid_' . $cmid;
            } else {
                // For URLs without id param, normalize URL for grouping.
                $urlparts = parse_url($url->out(false));
                $baseurl = '';
                if (isset($urlparts['scheme'])) {
                    $baseurl .= $urlparts['scheme'] . '://';
                }
                if (isset($urlparts['host'])) {
                    $baseurl .= $urlparts['host'];
                }
                if (isset($urlparts['port'])) {
                    $baseurl .= ':' . $urlparts['port'];
                }
                if (isset($urlparts['path'])) {
                    $baseurl .= $urlparts['path'];
                }
                // Use modname + base URL as key.
                $activitykey = 'url_' . $modname . '_' . md5($baseurl);
            }
        } else {
            // No URL - use modname + name as fallback.
            $nameprop = $reflection->getProperty('name');
            $nameprop->setAccessible(true);
            $name = $nameprop->getValue($result);
            $activitykey = 'name_' . $modname . '_' . md5($name);
        }

        if ($activitykey) {
            if (!isset($groups[$activitykey])) {
                $groups[$activitykey] = [];
            }
            $groups[$activitykey][] = $result;
        } else {
            $individual[] = $result;
        }
    }

    // Process groups: create grouped results for activities with 2+ matches.
    $groupedresults = [];
    foreach ($groups as $activitykey => $matches) {
        if (count($matches) >= 2) {
            // Create grouped result.
            $firstmatch = $matches[0];
            $reflection = new \ReflectionClass($firstmatch);

            // Get modname first to determine how to extract activity name.
            $modnameprop = $reflection->getProperty('modname');
            $modnameprop->setAccessible(true);
            $modname = $modnameprop->getValue($firstmatch);

            // Get common activity info from first match.
            $nameprop = $reflection->getProperty('name');
            $nameprop->setAccessible(true);
            $activityname = $nameprop->getValue($firstmatch);

            // For forums, try to extract forum name from forumname property.
            if ($modname === 'forum') {
                $forumnameprop = $reflection->getProperty('forumname');
                $forumnameprop->setAccessible(true);
                $forumname = $forumnameprop->getValue($firstmatch);
                if (!empty($forumname)) {
                    // Use forum name as activity name for better grouping.
                    $activityname = $forumname;
                }
            }

            $urlprop = $reflection->getProperty('url');
            $urlprop->setAccessible(true);
            $activityurl = $urlprop->getValue($firstmatch);

            $iconprop = $reflection->getProperty('iconurl');
            $iconprop->setAccessible(true);
            $activityicon = $iconprop->getValue($firstmatch);

            $sectionnumberprop = $reflection->getProperty('sectionnumber');
            $sectionnumberprop->setAccessible(true);
            $sectionnumber = $sectionnumberprop->getValue($firstmatch);

            $sectionnameprop = $reflection->getProperty('sectionname');
            $sectionnameprop->setAccessible(true);
            $sectionname = $sectionnameprop->getValue($firstmatch);

            $issubsectionprop = $reflection->getProperty('issubsection');
            $issubsectionprop->setAccessible(true);
            $issubsection = $issubsectionprop->getValue($firstmatch);

            $parentsectionnumberprop = $reflection->getProperty('parentsectionnumber');
            $parentsectionnumberprop->setAccessible(true);
            $parentsectionnumber = $parentsectionnumberprop->getValue($firstmatch);

            $parentsectionnameprop = $reflection->getProperty('parentsectionname');
            $parentsectionnameprop->setAccessible(true);
            $parentsectionname = $parentsectionnameprop->getValue($firstmatch);

            // For forum results, extract the forum name from the first match.
            $forumname = null;
            if ($modname === 'forum') {
                $forumnameprop = $reflection->getProperty('forumname');
                $forumnameprop->setAccessible(true);
                $forumname = $forumnameprop->getValue($firstmatch);
            }

            // Create grouped result.
            $groupedresult = new \mod_coursesearch\output\search_result(
                $activityname, // Name (not used for grouped).
                $activityurl ?: new moodle_url('/course/view.php', ['id' => $course->id]), // URL (not used for grouped).
                $modname,
                $activityicon,
                '', // Snippet (not used for grouped).
                '', // Match type (not used for grouped).
                $forumname,
                $sectionnumber,
                $sectionname,
                $issubsection,
                $parentsectionnumber,
                $parentsectionname,
                true, // Is grouped.
                count($matches), // Match count.
                $activityname, // Activity name.
                $activityurl, // Activity URL.
                $activityicon, // Activity icon.
                $matches // Matches.
            );
            $groupedresults[] = $groupedresult;
        } else {
            // Single match - add to individual results.
            $individual = array_merge($individual, $matches);
        }
    }

    // Combine grouped and individual results.
    return array_merge($groupedresults, $individual);
}
