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
 * Private coursesearch module utility functions
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Get parent section info for a subsection.
 *
 * In Moodle 4.x, subsections are stored as regular sections with component='mod_subsection'.
 * The parent relationship is:
 * - Subsection (course_sections) has itemid pointing to mdl_subsection.id
 * - Subsection module (course_modules) has instance = mdl_subsection.id
 * - Subsection module's section field points to parent section's row ID
 *
 * @param object $section The section record (must have component, itemid fields)
 * @param int $courseid The course ID
 * @param array $allsections All sections in the course, indexed by section row ID
 * @return array|null Array with parent section info or null if not a subsection
 */
function coursesearch_get_parent_section_info($section, $courseid, $allsections) {
    global $DB;

    // Check if this is a subsection (component = 'mod_subsection').
    if (empty($section->component) || $section->component !== 'mod_subsection') {
        return null; // Not a subsection.
    }

    // Find the subsection module that links to this section.
    // The subsection module's instance = section's itemid.
    $subsectionmodule = $DB->get_record_sql(
        "SELECT cm.id, cm.section as parent_section_id
         FROM {course_modules} cm
         JOIN {modules} m ON m.id = cm.module
         WHERE cm.course = :courseid
           AND m.name = 'subsection'
           AND cm.instance = :itemid",
        ['courseid' => $courseid, 'itemid' => $section->itemid]
    );

    if (!$subsectionmodule) {
        return null; // Could not find parent relationship.
    }

    // Find the parent section by its row ID.
    $parentsectionid = $subsectionmodule->parent_section_id;
    foreach ($allsections as $s) {
        if ($s->id == $parentsectionid) {
            return [
                'parent_section_number' => $s->section,
                'parent_section_name' => !empty($s->name) ? $s->name : get_string('section') . ' ' . $s->section,
                'parent_section_id' => $s->id,
            ];
        }
    }

    return null;
}

/**
 * Get section info (number and name) for a course module
 *
 * @param cm_info $mod The course module
 * @param array $sections Pre-fetched sections indexed by section number
 * @return array Array with 'section_number' and 'section_name' keys
 */
function coursesearch_get_section_info($mod, $sections) {
    $sectionnum = isset($mod->sectionnum) ? $mod->sectionnum : (isset($mod->section) ? $mod->section : 0);

    // Find section name from pre-fetched sections.
    $sectionname = '';
    foreach ($sections as $section) {
        if ($section->section == $sectionnum) {
            $sectionname = !empty($section->name) ? $section->name : get_string('section') . ' ' . $section->section;
            break;
        }
    }

    if (empty($sectionname)) {
        $sectionname = get_string('section') . ' ' . $sectionnum;
    }

    return [
        'section_number' => $sectionnum,
        'section_name' => $sectionname,
    ];
}

/**
 * Perform a course search based on the given query and filter
 *
 * @param string $query The search query
 * @param object $course The course object
 * @param string $filter The filter to apply (all, title, content, description, sections, activities, resources, forums)
 * @return array The search results
 */
function coursesearch_perform_search($query, $course, $filter = 'all') {
    global $DB;

    // Trim the query to remove leading and trailing whitespace.
    $query = trim($query);

    // Limit query length to prevent performance issues and potential abuse.
    $query = mb_substr($query, 0, 500);

    if (empty($query)) {
        return [];
    }

    $results = [];

    // Fetch sections ONCE - will be reused in coursesearch_search_course_index() to avoid duplicate query.
    // Include component and itemid to detect subsections and find their parent sections.
    $sections = $DB->get_records(
        'course_sections',
        ['course' => $course->id],
        'section',
        'id, section, name, summary, component, itemid'
    );

    // Search course sections - only if not specifically looking for other content types.
    if ($filter == 'all' || $filter == 'sections') {
        foreach ($sections as $section) {
            // Check if this section is a subsection and get its parent info.
            $parentinfo = coursesearch_get_parent_section_info($section, $course->id, $sections);
            $issubsection = ($parentinfo !== null);

            // Search in section name - use case-insensitive comparison.
            $namematches = !empty($section->name) && stripos($section->name, $query) !== false;
            $nummatches = stripos(get_string('section') . ' ' . $section->section, $query) !== false;
            if (!empty($section->name) && ($namematches || $nummatches)) {
                // Create a direct URL to this section with explicit section parameter.
                $sectionurl = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $section->section]);

                $result = [
                    'type' => 'section_name',
                    'name' => ($issubsection ? '' : get_string('section') . ': ') . $section->name,
                    'url' => $sectionurl,
                    'modname' => $issubsection ? 'subsection' : 'section',
                    'icon' => new moodle_url('/pix/i/section.png'),
                    'match' => get_string('title', 'mod_coursesearch'),
                    'snippet' => coursesearch_extract_snippet($section->name, $query),
                    'section_number' => $section->section,
                    'section_name' => $section->name,
                    'is_subsection' => $issubsection,
                ];

                // Add parent section info for subsections.
                if ($issubsection) {
                    $result['parent_section_number'] = $parentinfo['parent_section_number'];
                    $result['parent_section_name'] = $parentinfo['parent_section_name'];
                }

                $results[] = $result;
            }

            // Search in section summary - use case-insensitive comparison with relevance check.
            if (!empty($section->summary) && coursesearch_is_relevant($section->summary, $query)) {
                $sectionurl = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $section->section]);
                $sectionname = $section->name ? $section->name : get_string('section') . ' ' . $section->section;

                $result = [
                    'type' => 'section_summary',
                    'name' => ($issubsection ? '' : get_string('section') . ': ') . $sectionname,
                    'url' => $sectionurl,
                    'modname' => $issubsection ? 'subsection' : 'section',
                    'icon' => new moodle_url('/pix/i/section.png'),
                    'match' => get_string('matchdescriptionorcontent', 'mod_coursesearch'),
                    'snippet' => coursesearch_extract_snippet($section->summary, $query),
                    'section_number' => $section->section,
                    'section_name' => $sectionname,
                    'is_subsection' => $issubsection,
                ];

                // Add parent section info for subsections.
                if ($issubsection) {
                    $result['parent_section_number'] = $parentinfo['parent_section_number'];
                    $result['parent_section_name'] = $parentinfo['parent_section_name'];
                }

                $results[] = $result;
            }

            // If section has no name but number matches the query.
            if (empty($section->name) && (stripos(get_string('section') . ' ' . $section->section, $query) !== false)) {
                $sectionurl = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $section->section]);
                $sectionname = get_string('section') . ' ' . $section->section;

                $result = [
                    'type' => 'section_number',
                    'name' => $sectionname,
                    'url' => $sectionurl,
                    'modname' => $issubsection ? 'subsection' : 'section',
                    'icon' => new moodle_url('/pix/i/section.png'),
                    'match' => get_string('title', 'mod_coursesearch'),
                    'snippet' => $sectionname,
                    'section_number' => $section->section,
                    'section_name' => $sectionname,
                    'is_subsection' => $issubsection,
                ];

                // Add parent section info for subsections.
                if ($issubsection) {
                    $result['parent_section_number'] = $parentinfo['parent_section_number'];
                    $result['parent_section_name'] = $parentinfo['parent_section_name'];
                }

                $results[] = $result;
            }
        }
    }

    // Build a map of subsection numbers to their parent section info.
    // This allows us to properly group module results that are inside subsections.
    $subsectionparents = [];
    foreach ($sections as $section) {
        if (!empty($section->component) && $section->component === 'mod_subsection') {
            $parentinfo = coursesearch_get_parent_section_info($section, $course->id, $sections);
            if ($parentinfo) {
                $subsectionparents[$section->section] = $parentinfo;
            }
        }
    }

    // Search course modules based on the scope.
    $modinfo = get_fast_modinfo($course);

    // Bulk fetch all module records to avoid N+1 queries when searching descriptions.
    $moduledata = coursesearch_bulk_fetch_module_data($modinfo);

    foreach ($modinfo->get_cms() as $mod) {
        // Skip if the module is not visible or the user can't access it.
        if (!$mod->uservisible) {
            continue;
        }

        // Skip subsection modules - they are nested sections in Moodle 4.x and are
        // already found by the section search. Including them here causes duplicates
        // with wrong URLs (they get grouped under parent section instead of their own).
        if ($mod->modname === 'subsection') {
            continue;
        }

        // Filter by filter type.
        if ($filter != 'all') {
            if ($filter == 'activities' && $mod->modname == 'resource') {
                continue;
            } else if ($filter == 'resources' && $mod->modname != 'resource') {
                continue;
            } else if ($filter == 'forums' && $mod->modname != 'forum') {
                continue;
            }
        }

        // Check if the module name contains the search query (only if filter is 'all' or 'title').
        if (($filter == 'all' || $filter == 'title') && coursesearch_mb_stripos($mod->name, $query) !== false) {
            // For labels and similar inline content, we need to create a URL with an anchor.
            if ($mod->modname === 'label' || $mod->modname === 'html') {
                // Include section parameter to ensure the correct section is displayed.
                $sectionnum = isset($mod->sectionnum) ? $mod->sectionnum : (isset($mod->section) ? $mod->section : null);
                $urlparams = ['id' => $course->id];
                if ($sectionnum !== null) {
                    $urlparams['section'] = $sectionnum;
                }
                $moduleurl = new moodle_url('/course/view.php', $urlparams);
                $moduleurl->set_anchor('module-' . $mod->id);
            } else {
                // For other module types, use the module's URL.
                $moduleurl = $mod->url;
            }

            $sectioninfo = coursesearch_get_section_info($mod, $sections);
            $result = [
                'type' => 'module',
                'name' => $mod->name,
                'url' => $moduleurl,
                'modname' => $mod->modname,
                'icon' => $mod->get_icon_url(),
                'match' => get_string('title', 'mod_coursesearch'),
                'snippet' => $mod->name,
                'cmid' => $mod->id,
                'section_number' => $sectioninfo['section_number'],
                'section_name' => $sectioninfo['section_name'],
                'is_subsection' => false,
            ];

            // Check if module is inside a subsection.
            if (isset($subsectionparents[$sectioninfo['section_number']])) {
                $parentinfo = $subsectionparents[$sectioninfo['section_number']];
                $result['is_subsection'] = true;
                $result['parent_section_number'] = $parentinfo['parent_section_number'];
                $result['parent_section_name'] = $parentinfo['parent_section_name'];
            }

            $results[] = $result;
            // Don't skip content search - modules can have both title and content matches.
            // This allows grouping of title matches with content matches for the same activity.
            // Fall through to description and content search below.
        }

        // Search in the module description/intro if available (only if filter is 'all' or 'description').
        // Skip description search for labels and html - their intro IS the content, so it's handled by content search.
        $description = '';
        if (($filter == 'all' || $filter == 'description') && $mod->modname !== 'label' && $mod->modname !== 'html') {
            // Get the module description from pre-fetched data (bulk loaded to avoid N+1 queries).
            $modulerecord = $moduledata[$mod->modname][$mod->instance] ?? null;

            // Most modules use 'intro' field for description.
            if ($modulerecord && isset($modulerecord->intro)) {
                $description = $modulerecord->intro;
            } else if ($modulerecord && isset($modulerecord->description)) {
                // Some modules might use 'description' or other fields.
                $description = $modulerecord->description;
            } else if ($modulerecord && isset($modulerecord->content)) {
                // For custom modules with different field names.
                $description = $modulerecord->content;
            } else if ($modulerecord && isset($modulerecord->summary)) {
                // For modules with summary field.
                $description = $modulerecord->summary;
            }
        }

        // Search in the description if we found one.
        if (!empty($description)) {
            if (coursesearch_is_relevant($description, $query)) {
                // For labels and similar inline content, we need to create a URL with an anchor.
                if ($mod->modname === 'label' || $mod->modname === 'html') {
                    // Include section parameter to ensure the correct section is displayed.
                    $sectionnum = isset($mod->sectionnum) ? $mod->sectionnum : (isset($mod->section) ? $mod->section : null);
                    $urlparams = ['id' => $course->id];
                    if ($sectionnum !== null) {
                        $urlparams['section'] = $sectionnum;
                    }
                    $moduleurl = new moodle_url('/course/view.php', $urlparams);
                    $moduleurl->set_anchor('module-' . $mod->id);
                } else {
                    // For other module types, use the module's URL.
                    $moduleurl = $mod->url;
                }

                // Extract multiple snippets for all occurrences (limit from admin settings).
                $maxoccurrences = get_config('mod_coursesearch', 'maxoccurrences');
                $maxoccurrences = ($maxoccurrences === false || $maxoccurrences === null) ? 5 : (int)$maxoccurrences;
                $snippets = coursesearch_extract_multiple_snippets($description, $query, 150, $maxoccurrences);
                $sectioninfo = coursesearch_get_section_info($mod, $sections);

                // Create one result per snippet occurrence.
                foreach ($snippets as $index => $snippet) {
                    // For multiple occurrences, add occurrence parameter to URL.
                    $resulturl = clone $moduleurl;
                    if ($index > 0) {
                        $resulturl->param('occurrence', $index + 1);
                    }

                    $result = [
                        'type' => 'module_description',
                        'name' => $mod->name,
                        'url' => $resulturl,
                        'modname' => $mod->modname,
                        'icon' => $mod->get_icon_url(),
                        'match' => get_string('matchdescriptionorcontent', 'mod_coursesearch'),
                        'snippet' => $snippet,
                        'cmid' => $mod->id,
                        'section_number' => $sectioninfo['section_number'],
                        'section_name' => $sectioninfo['section_name'],
                        'is_subsection' => false,
                    ];

                    // Check if module is inside a subsection.
                    if (isset($subsectionparents[$sectioninfo['section_number']])) {
                        $parentinfo = $subsectionparents[$sectioninfo['section_number']];
                        $result['is_subsection'] = true;
                        $result['parent_section_number'] = $parentinfo['parent_section_number'];
                        $result['parent_section_name'] = $parentinfo['parent_section_name'];
                    }

                    $results[] = $result;
                }
                // Don't use continue here - we want to also search content for all modules
                // that can have multiple matches. This allows grouping of description matches with content matches.
                // Fall through to content search below.
            }
        }

        // Search in module content based on the module type (only if filter is 'all' or 'content').
        // For forums, we want to search content regardless of the filter when 'forums' filter is selected.
        // We always want to search content even if title/description matched, to allow grouping of multiple matches.
        if ($filter == 'all' || $filter == 'content' || ($filter == 'forums' && $mod->modname == 'forum')) {
            $contentresults = coursesearch_search_module_content($mod, $query, $course, $filter, $sections);
            $results = array_merge($results, $contentresults);
        }
    }

    // Additional fallback: Direct database search for all labels in the course.
    // This ensures we don't miss any labels that might not be in modinfo or are being filtered out.
    // Only search if filter allows content search.
    if ($filter == 'all' || $filter == 'content') {
        $labelresults = coursesearch_search_all_labels_direct($query, $course, $sections, $modinfo);
        // Merge results, avoiding duplicates by cmid.
        $existingcmids = [];
        foreach ($results as $result) {
            if (isset($result['cmid'])) {
                $existingcmids[$result['cmid']] = true;
            }
        }
        foreach ($labelresults as $labelresult) {
            if (!isset($labelresult['cmid']) || !isset($existingcmids[$labelresult['cmid']])) {
                $results[] = $labelresult;
                if (isset($labelresult['cmid'])) {
                    $existingcmids[$labelresult['cmid']] = true;
                }
            }
        }
    }

    // Search for titles in the course index (unless we're filtering by content or description only).
    // Pass pre-fetched sections and modinfo to avoid duplicate queries.
    if ($filter != 'content' && $filter != 'description') {
        $results = coursesearch_search_course_index($query, $course, $results, $sections, $modinfo);
    }

    // Post-process all results to add subsection parent info for modules inside subsections.
    // This catches results from coursesearch_search_module_content and coursesearch_search_course_index.
    // We need to process ALL results, even if is_subsection is already set, because results from
    // different search functions might not have been processed yet.
    foreach ($results as &$result) {
        // Skip section/subsection results themselves - they already have parent info set.
        $modname = $result['modname'] ?? '';
        if ($modname === 'section' || $modname === 'subsection') {
            continue;
        }

        // Check if this result's section is a subsection.
        $sectionnumber = isset($result['section_number']) ? (int)$result['section_number'] : 0;
        if ($sectionnumber > 0 && isset($subsectionparents[$sectionnumber])) {
            $parentinfo = $subsectionparents[$sectionnumber];
            $result['is_subsection'] = true;
            $result['parent_section_number'] = $parentinfo['parent_section_number'];
            $result['parent_section_name'] = $parentinfo['parent_section_name'];
        } else {
            // Ensure is_subsection is set to false if not in a subsection.
            $result['is_subsection'] = false;
        }
    }
    unset($result); // Break reference.

    // Apply additional filtering based on the selected filter.
    if ($filter != 'all') {
        $filteredresults = [];

        foreach ($results as $result) {
            // Filter by match type.
            if ($filter == 'title' && $result['match'] == get_string('title', 'mod_coursesearch')) {
                $filteredresults[] = $result;
            } else if ($filter == 'content' && $result['match'] == get_string('content', 'mod_coursesearch')) {
                $filteredresults[] = $result;
            } else if (
                    $filter == 'description' &&
                    $result['match'] == get_string('matchdescriptionorcontent', 'mod_coursesearch')
            ) {
                $filteredresults[] = $result;
            } else if ($filter == 'sections' && $result['modname'] == 'section') {
                $filteredresults[] = $result;
            } else if ($filter == 'activities') {
                $activitymods = [
                    'assign', 'quiz', 'choice', 'feedback', 'lesson',
                    'workshop', 'data', 'glossary', 'wiki', 'forum',
                ];
                if (in_array($result['modname'], $activitymods)) {
                    $filteredresults[] = $result;
                }
            } else if ($filter == 'resources') {
                $resourcemods = ['book', 'file', 'folder', 'imscp', 'label', 'page', 'resource', 'url'];
                if (in_array($result['modname'], $resourcemods)) {
                    $filteredresults[] = $result;
                }
            } else if ($filter == 'forums' && $result['modname'] == 'forum') {
                $filteredresults[] = $result;
            }
        }

        $results = $filteredresults;
    }

    // Fix the issue with search results that have 'match' set to 'title' by ensuring they all have valid URLs.
    foreach ($results as &$result) {
        if ($result['match'] == get_string('title', 'mod_coursesearch') && empty($result['url'])) {
            $result['url'] = new moodle_url('/course/view.php', ['id' => $course->id]);
        }
    }

    return $results;
}

/**
 * Bulk fetch module records grouped by module type for efficient lookup
 *
 * This function collects all module instances by type and fetches them in bulk
 * to avoid N+1 query problems when searching module descriptions.
 *
 * @param course_modinfo $modinfo The course module info object
 * @return array Associative array indexed by modname then instance id
 */
function coursesearch_bulk_fetch_module_data($modinfo) {
    global $DB;

    $moduledata = [];
    $modulesbytype = [];

    // Group module instances by type.
    foreach ($modinfo->get_cms() as $mod) {
        if (!$mod->uservisible) {
            continue;
        }
        // Validate module name to prevent SQL injection.
        $modname = clean_param($mod->modname, PARAM_PLUGIN);
        if (empty($modname) || $modname !== $mod->modname) {
            continue;
        }
        $modulesbytype[$modname][] = $mod->instance;
    }

    // Bulk fetch each module type.
    foreach ($modulesbytype as $modname => $instances) {
        // Check if table exists to avoid errors with missing plugins.
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists($modname)) {
            continue;
        }
        // Fetch all records for this module type in one query.
        $moduledata[$modname] = $DB->get_records_list($modname, 'id', $instances);
    }

    return $moduledata;
}

/**
 * Search module content based on module type
 *
 * @param cm_info $mod The course module
 * @param string $query The search query
 * @param object $course The course object
 * @param string $filter The filter type
 * @param array $sections Pre-fetched sections for section info lookup
 * @return array The search results for this module
 */
function coursesearch_search_module_content($mod, $query, $course, $filter, $sections = []) {
    global $DB;

    $results = [];

    switch ($mod->modname) {
        case 'page':
            $results = coursesearch_search_page($mod, $query);
            break;

        case 'book':
            $results = coursesearch_search_book($mod, $query, $filter);
            break;

        case 'label':
            $results = coursesearch_search_label($mod, $query, $course);
            break;

        case 'lesson':
            $results = coursesearch_search_lesson($mod, $query);
            break;

        case 'forum':
            $results = coursesearch_search_forum($mod, $query, $filter);
            break;

        case 'folder':
            $results = coursesearch_search_folder($mod, $query);
            break;

        case 'wiki':
            $results = coursesearch_search_wiki($mod, $query, $filter);
            break;

        case 'glossary':
            $results = coursesearch_search_glossary($mod, $query, $filter);
            break;

        case 'data':
            $results = coursesearch_search_database($mod, $query);
            break;

        case 'hvp':
            $results = coursesearch_search_hvp($mod, $query);
            break;

        case 'h5pactivity':
            $results = coursesearch_search_h5pactivity($mod, $query);
            break;
    }

    // Add section info to all results from content search.
    if (!empty($results) && !empty($sections)) {
        $sectioninfo = coursesearch_get_section_info($mod, $sections);
        foreach ($results as &$result) {
            if (!isset($result['section_number'])) {
                $result['section_number'] = $sectioninfo['section_number'];
                $result['section_name'] = $sectioninfo['section_name'];
            }
        }
    }

    return $results;
}

/**
 * Search in page content
 *
 * @param cm_info $mod The course module
 * @param string $query The search query
 * @return array The search results
 */
function coursesearch_search_page($mod, $query) {
    global $DB;

    $results = [];
    $page = $DB->get_record('page', ['id' => $mod->instance], 'id, name, content');

    if ($page && coursesearch_is_relevant($page->content, $query)) {
        // Extract multiple snippets for all occurrences (limit from admin settings).
        $maxoccurrences = get_config('mod_coursesearch', 'maxoccurrences');
        $maxoccurrences = ($maxoccurrences === false || $maxoccurrences === null) ? 5 : (int)$maxoccurrences;
        $snippets = coursesearch_extract_multiple_snippets($page->content, $query, 150, $maxoccurrences);

        // Create one result per snippet occurrence.
        foreach ($snippets as $index => $snippet) {
            $pageurl = new moodle_url('/mod/page/view.php', ['id' => $mod->id]);
            if (!empty($query)) {
                $pageurl->param('highlight', $query);
            }
            // Add occurrence index to URL to distinguish multiple matches (optional, for future use).
            if ($index > 0) {
                $pageurl->param('occurrence', $index + 1);
            }

            $results[] = [
                'type' => 'page_content',
                'name' => $mod->name,
                'url' => $pageurl,
                'modname' => $mod->modname,
                'icon' => $mod->get_icon_url(),
                'match' => get_string('content', 'mod_coursesearch'),
                'snippet' => $snippet,
                'cmid' => $mod->id,
            ];
        }
    }

    return $results;
}

/**
 * Search in book content and titles
 *
 * @param cm_info $mod The course module
 * @param string $query The search query
 * @param string $filter The filter type
 * @return array The search results
 */
function coursesearch_search_book($mod, $query, $filter) {
    global $DB;

    $results = [];
    $bookchapters = $DB->get_records('book_chapters', ['bookid' => $mod->instance]);

    foreach ($bookchapters as $chapter) {
        // First check if the chapter title matches the query.
        if (($filter == 'all' || $filter == 'title') && coursesearch_mb_stripos($chapter->title, $query) !== false) {
            $chapterurl = new moodle_url('/mod/book/view.php', ['id' => $mod->id, 'chapterid' => $chapter->id]);
            if (!empty($query)) {
                $chapterurl->param('highlight', $query);
            }
            $results[] = [
                'type' => 'book_title',
                'name' => $mod->name . ': ' . $chapter->title,
                'url' => $chapterurl,
                'modname' => $mod->modname,
                'icon' => $mod->get_icon_url(),
                'match' => get_string('title', 'mod_coursesearch'),
                'snippet' => $chapter->title,
                'cmid' => $mod->id,
            ];
            continue;
        }

        // Then check if the chapter content matches the query.
        if (($filter == 'all' || $filter == 'content') && coursesearch_is_relevant($chapter->content, $query)) {
            $snippet = coursesearch_extract_snippet($chapter->content, $query);
            $chapterurl = new moodle_url('/mod/book/view.php', ['id' => $mod->id, 'chapterid' => $chapter->id]);
            if (!empty($query)) {
                $chapterurl->param('highlight', $query);
            }
            $results[] = [
                'type' => 'book_content',
                'name' => $mod->name . ': ' . $chapter->title,
                'url' => $chapterurl,
                'modname' => $mod->modname,
                'icon' => $mod->get_icon_url(),
                'match' => get_string('content', 'mod_coursesearch'),
                'snippet' => $snippet,
                'cmid' => $mod->id,
            ];
        }
    }

    return $results;
}

/**
 * Search in label content
 *
 * @param cm_info $mod The course module
 * @param string $query The search query
 * @param object $course The course object
 * @return array The search results
 */
function coursesearch_search_label($mod, $query, $course) {
    global $DB;

    $results = [];
    $label = $DB->get_record('label', ['id' => $mod->instance], 'id, name, intro');

    if ($label) {
        // Use the standard relevance check which handles HTML conversion and multilang processing.
        $isrelevant = coursesearch_is_relevant($label->intro, $query);

        if ($isrelevant) {
            // Extract multiple snippets for all occurrences (limit from admin settings).
            $maxoccurrences = get_config('mod_coursesearch', 'maxoccurrences');
            $maxoccurrences = ($maxoccurrences === false || $maxoccurrences === null) ? 5 : (int)$maxoccurrences;
            $snippets = coursesearch_extract_multiple_snippets($label->intro, $query, 150, $maxoccurrences);

            // Create one result per snippet occurrence.
            foreach ($snippets as $index => $snippet) {
                $sectionnum = isset($mod->sectionnum) ? $mod->sectionnum : (isset($mod->section) ? $mod->section : null);
                $urlparams = ['id' => $course->id];
                if ($sectionnum !== null) {
                    $urlparams['section'] = $sectionnum;
                }
                if (!empty($query)) {
                    $urlparams['highlight'] = $query;
                }
                // Add occurrence index to distinguish multiple matches.
                if ($index > 0) {
                    $urlparams['occurrence'] = $index + 1;
                }
                $moduleurl = new moodle_url('/course/view.php', $urlparams);
                $moduleurl->set_anchor('module-' . $mod->id);
                $results[] = [
                    'type' => 'label_content',
                    'name' => $mod->name,
                    'url' => $moduleurl,
                    'modname' => $mod->modname,
                    'icon' => $mod->get_icon_url(),
                    // For labels, intro IS the content, so use "description or content" to match user expectations.
                    'match' => get_string('matchdescriptionorcontent', 'mod_coursesearch'),
                    'snippet' => $snippet,
                    'cmid' => $mod->id,
                ];
            }
        }
    }

    return $results;
}

/**
 * Direct database search for all labels in a course
 * This is a fallback to ensure we don't miss any labels that might not be in modinfo
 *
 * @param string $query The search query
 * @param object $course The course object
 * @param array $sections Pre-fetched sections
 * @param course_modinfo $modinfo The course modinfo
 * @return array Search results for matching labels
 */
function coursesearch_search_all_labels_direct($query, $course, $sections, $modinfo) {
    global $DB;

    $results = [];

    // Get all course modules for labels in this course.
    $sql = "SELECT cm.id as cmid, cm.instance, cm.section, l.name, l.intro
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module AND m.name = 'label'
            JOIN {label} l ON l.id = cm.instance
            WHERE cm.course = :courseid
              AND cm.visible = 1
              AND cm.deletioninprogress = 0";

    $labelmodules = $DB->get_records_sql($sql, ['courseid' => $course->id]);

    foreach ($labelmodules as $labelmod) {
        // Use the standard relevance check which handles HTML conversion and multilang processing.
        $isrelevant = false;
        if (!empty($labelmod->intro)) {
            $isrelevant = coursesearch_is_relevant($labelmod->intro, $query);
        }

        if ($isrelevant) {
            // Get section info.
            $sectioninfo = ['section_number' => 0, 'section_name' => ''];
            foreach ($sections as $section) {
                if ($section->id == $labelmod->section) {
                    $sectioninfo['section_number'] = $section->section;
                    $sectionname = !empty($section->name) ? $section->name : get_string('section') . ' ' . $section->section;
                    $sectioninfo['section_name'] = $sectionname;
                    break;
                }
            }

            // Create URL.
            $urlparams = ['id' => $course->id];
            if ($sectioninfo['section_number'] > 0) {
                $urlparams['section'] = $sectioninfo['section_number'];
            }
            if (!empty($query)) {
                $urlparams['highlight'] = $query;
            }
            $moduleurl = new moodle_url('/course/view.php', $urlparams);
            $moduleurl->set_anchor('module-' . $labelmod->cmid);

            $snippet = coursesearch_extract_snippet($labelmod->intro, $query);

            // Get icon URL from modinfo if available.
            $iconurl = '';
            try {
                $cm = $modinfo->get_cm($labelmod->cmid);
                $iconurl = $cm->get_icon_url();
            } catch (Exception $e) {
                // If cm not found in modinfo, use default label icon.
                $iconurl = new moodle_url('/mod/label/icon.png');
            }

            $results[] = [
                'type' => 'label_content',
                'name' => $labelmod->name,
                'url' => $moduleurl,
                'modname' => 'label',
                'icon' => $iconurl,
                'match' => get_string('content', 'mod_coursesearch'),
                'snippet' => $snippet,
                'cmid' => $labelmod->cmid,
                'section_number' => $sectioninfo['section_number'],
                'section_name' => $sectioninfo['section_name'],
                'is_subsection' => false,
            ];
        }
    }

    return $results;
}

/**
 * Search in lesson pages
 *
 * @param cm_info $mod The course module
 * @param string $query The search query
 * @return array The search results
 */
function coursesearch_search_lesson($mod, $query) {
    global $DB;

    $results = [];
    $lessonpages = $DB->get_records('lesson_pages', ['lessonid' => $mod->instance]);

    foreach ($lessonpages as $page) {
        if (coursesearch_is_relevant($page->contents, $query)) {
            $snippet = coursesearch_extract_snippet($page->contents, $query);
            $pageurl = new moodle_url('/mod/lesson/view.php', ['id' => $mod->id, 'pageid' => $page->id]);
            if (!empty($query)) {
                $pageurl->param('highlight', $query);
            }
            $results[] = [
                'type' => 'lesson_content',
                'name' => $mod->name . ': ' . $page->title,
                'url' => $pageurl,
                'modname' => $mod->modname,
                'icon' => $mod->get_icon_url(),
                'match' => get_string('content', 'mod_coursesearch'),
                'snippet' => $snippet,
                'cmid' => $mod->id,
            ];
        }
    }

    return $results;
}

/**
 * Search in forum discussions and posts
 *
 * Optimized to use a single JOIN query instead of N+1 queries.
 * Forum name is cached once instead of being fetched per post.
 *
 * @param cm_info $mod The course module
 * @param string $query The search query
 * @param string $filter The filter type
 * @return array The search results
 */
function coursesearch_search_forum($mod, $query, $filter) {
    global $DB;

    $results = [];

    // Determine what to search in forums based on the filter.
    $searchforumtitles = ($filter == 'all' || $filter == 'title' || $filter == 'forums');
    $searchforumcontent = ($filter == 'all' || $filter == 'content' || $filter == 'forums');
    $searchforumsubjects = ($filter == 'all' || $filter == 'title' || $filter == 'forums');

    // Cache forum name ONCE (was previously queried multiple times per post).
    $forum = $DB->get_record('forum', ['id' => $mod->instance], 'id, name');
    $forumname = $forum ? $forum->name : $mod->name;

    // Single JOIN query to fetch all discussions and posts at once.
    // This replaces multiple queries: one for discussions + N queries for posts.
    // IMPORTANT: Use post ID as the first column so get_records_sql uses it as the key,
    // preventing posts from the same discussion from overwriting each other.
    $sql = "SELECT p.id as postid, d.id as discussionid, d.name as discussionname,
                   p.parent, p.subject, p.message
            FROM {forum_discussions} d
            JOIN {forum_posts} p ON p.discussion = d.id
            WHERE d.forum = :forumid
            ORDER BY d.id, p.parent, p.id";
    $rows = $DB->get_records_sql($sql, ['forumid' => $mod->instance]);

    // Organize data into structures for efficient processing.
    $discussions = [];
    $postsbydiscussion = [];
    $firstposts = [];

    foreach ($rows as $postid => $row) {
        // Build discussion object if not seen yet.
        if (!isset($discussions[$row->discussionid])) {
            $discussions[$row->discussionid] = (object)[
                'id' => $row->discussionid,
                'name' => $row->discussionname,
            ];
        }

        // Build post object.
        // Note: $postid is now the array key from get_records_sql (the post ID).
        $post = (object)[
            'id' => $row->postid,
            'parent' => $row->parent,
            'subject' => $row->subject,
            'message' => $row->message,
            'discussion' => $row->discussionid,
        ];
        $postsbydiscussion[$row->discussionid][$row->postid] = $post;

        // Track first posts (parent = 0) for each discussion.
        if ($row->parent == 0) {
            $firstposts[$row->discussionid] = $post;
        }
    }

    // Keep track of processed posts to avoid duplicates.
    $processedposts = [];

    // Process discussions and posts with O(1) lookups.
    foreach ($discussions as $discussionid => $discussion) {
        // First check if the discussion subject/topic name matches the query.
        if ($searchforumtitles && coursesearch_mb_stripos($discussion->name, $query) !== false) {
            // Use pre-fetched first post.
            if (isset($firstposts[$discussionid])) {
                $discussionurl = new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->id]);
                if (!empty($query)) {
                    $discussionurl->param('highlight', $query);
                }

                $results[] = [
                    'type' => 'forum_discussion',
                    'name' => $discussion->name,
                    'url' => $discussionurl,
                    'modname' => $mod->modname,
                    'icon' => $mod->get_icon_url(),
                    'match' => get_string('title', 'mod_coursesearch'),
                    'snippet' => $discussion->name,
                    'forum_name' => $forumname,
                    'cmid' => $mod->id,
                ];

                // Mark the first post as processed to avoid duplicate results.
                $processedposts[$firstposts[$discussionid]->id] = true;
            }
        }

        // Only continue with post checks if we're searching forum content.
        if (!$searchforumcontent && !$searchforumsubjects) {
            continue;
        }

        // Check posts in this discussion (using pre-fetched data).
        $posts = $postsbydiscussion[$discussionid] ?? [];
        foreach ($posts as $post) {
            // Skip if we've already processed this post.
            if (isset($processedposts[$post->id])) {
                continue;
            }

            // Mark this post as processed.
            $processedposts[$post->id] = true;

            // Check post subject (for replies).
            if ($searchforumsubjects && coursesearch_mb_stripos($post->subject, $query) !== false) {
                $posturl = new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->id, 'p' => $post->id]);
                if (!empty($query)) {
                    $posturl->param('highlight', $query);
                }
                $posturl->set_anchor('p' . $post->id);

                $results[] = [
                    'type' => 'forum_post',
                    'name' => $post->subject,
                    'url' => $posturl,
                    'modname' => $mod->modname,
                    'icon' => $mod->get_icon_url(),
                    'match' => get_string('title', 'mod_coursesearch'),
                    'snippet' => $post->subject,
                    'forum_name' => $forumname,
                    'cmid' => $mod->id,
                ];
                continue;
            }

            // Check post content.
            if ($searchforumcontent && coursesearch_is_relevant($post->message, $query)) {
                $snippet = coursesearch_extract_snippet($post->message, $query);
                $posturl = new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->id, 'p' => $post->id]);
                if (!empty($query)) {
                    $posturl->param('highlight', $query);
                }
                $posturl->set_anchor('p' . $post->id);

                $results[] = [
                    'type' => 'forum_post',
                    'name' => $post->subject,
                    'url' => $posturl,
                    'modname' => $mod->modname,
                    'icon' => $mod->get_icon_url(),
                    'match' => get_string('content', 'mod_coursesearch'),
                    'snippet' => $snippet,
                    'forum_name' => $forumname,
                    'cmid' => $mod->id,
                ];
            }
        }
    }

    return $results;
}

/**
 * Search in folder files
 *
 * @param cm_info $mod The course module
 * @param string $query The search query
 * @return array The search results
 */
function coursesearch_search_folder($mod, $query) {
    $results = [];
    $fs = get_file_storage();
    $context = context_module::instance($mod->id);
    $files = $fs->get_area_files($context->id, 'mod_folder', 'content', 0, 'filepath, filename', false);

    foreach ($files as $file) {
        $filename = $file->get_filename();
        // Search in filename.
        if (coursesearch_mb_stripos($filename, $query) !== false) {
            // Create URL to the folder with the file.
            $fileurl = moodle_url::make_pluginfile_url(
                $context->id,
                'mod_folder',
                'content',
                0,
                $file->get_filepath(),
                $filename
            );

            $results[] = [
                'type' => 'folder_file',
                'name' => $mod->name . ': ' . $filename,
                'url' => $fileurl,
                'modname' => 'folder',
                'icon' => $mod->get_icon_url(),
                'match' => get_string('title', 'mod_coursesearch'),
                'snippet' => $filename,
                'cmid' => $mod->id,
            ];
        }
    }

    return $results;
}

/**
 * Search in wiki pages
 *
 * Optimized to use a single JOIN query instead of nested loops with separate queries.
 *
 * @param cm_info $mod The course module
 * @param string $query The search query
 * @param string $filter The filter type
 * @return array The search results
 */
function coursesearch_search_wiki($mod, $query, $filter) {
    global $DB;

    $results = [];

    // Single JOIN query replaces 2 nested loops (subwikis -> pages).
    // This fetches all wiki pages across all subwikis in one query.
    $sql = "SELECT wp.id, wp.title, wp.cachedcontent
            FROM {wiki_subwikis} ws
            JOIN {wiki_pages} wp ON wp.subwikiid = ws.id
            WHERE ws.wikiid = :wikiid";
    $wikipages = $DB->get_records_sql($sql, ['wikiid' => $mod->instance]);

    foreach ($wikipages as $wikipage) {
        // First check if the page title matches.
        if (($filter == 'all' || $filter == 'title') && coursesearch_mb_stripos($wikipage->title, $query) !== false) {
            // Wiki URLs should use pageid only, not both id and pageid.
            $pageurl = new moodle_url('/mod/wiki/view.php', ['pageid' => $wikipage->id]);
            if (!empty($query)) {
                $pageurl->param('highlight', $query);
            }
            $results[] = [
                'type' => 'wiki_page_title',
                'name' => $mod->name . ': ' . $wikipage->title,
                'url' => $pageurl,
                'modname' => $mod->modname,
                'icon' => $mod->get_icon_url(),
                'match' => get_string('title', 'mod_coursesearch'),
                'snippet' => $wikipage->title,
                'cmid' => $mod->id,
            ];
            // Don't use continue - wiki pages can have both title and content matches.
            // Fall through to content search below.
        }

        // Then check if the page content matches.
        $filterok = ($filter == 'all' || $filter == 'content');
        $hascon = !empty($wikipage->cachedcontent);
        $relevant = $hascon && coursesearch_is_relevant($wikipage->cachedcontent, $query);
        if ($filterok && $relevant) {
            $snippet = coursesearch_extract_snippet($wikipage->cachedcontent, $query);

            // Wiki URLs should use pageid only, not both id and pageid.
            $pageurl = new moodle_url('/mod/wiki/view.php', ['pageid' => $wikipage->id]);
            if (!empty($query)) {
                $pageurl->param('highlight', $query);
            }
            $results[] = [
                'type' => 'wiki_page_content',
                'name' => $mod->name . ': ' . $wikipage->title,
                'url' => $pageurl,
                'modname' => $mod->modname,
                'icon' => $mod->get_icon_url(),
                'match' => get_string('content', 'mod_coursesearch'),
                'snippet' => $snippet,
                'cmid' => $mod->id,
            ];
        }
    }

    return $results;
}

/**
 * Search in glossary entries
 *
 * @param cm_info $mod The course module
 * @param string $query The search query
 * @param string $filter The filter type
 * @return array The search results
 */
function coursesearch_search_glossary($mod, $query, $filter) {
    global $DB;

    $results = [];
    $glossary = $DB->get_record('glossary', ['id' => $mod->instance], 'id, name');

    if ($glossary) {
        // Get all entries in this glossary.
        $entries = $DB->get_records('glossary_entries', ['glossaryid' => $glossary->id], '', 'id, concept, definition');
        foreach ($entries as $entry) {
            // First check if the entry concept (term) matches.
            if (($filter == 'all' || $filter == 'title') && coursesearch_mb_stripos($entry->concept, $query) !== false) {
                $entryurl = new moodle_url('/mod/glossary/showentry.php', ['eid' => $entry->id, 'displayformat' => 'dictionary']);
                if (!empty($query)) {
                    $entryurl->param('highlight', $query);
                }
                $results[] = [
                    'type' => 'glossary_entry_title',
                    'name' => $mod->name . ': ' . $entry->concept,
                    'url' => $entryurl,
                    'modname' => $mod->modname,
                    'icon' => $mod->get_icon_url(),
                    'match' => get_string('title', 'mod_coursesearch'),
                    'snippet' => $entry->concept,
                    'cmid' => $mod->id,
                ];
                continue;
            }

            // Then check if the entry definition matches.
            $filterok = ($filter == 'all' || $filter == 'content');
            $hasdef = !empty($entry->definition);
            $relevant = $hasdef && coursesearch_is_relevant($entry->definition, $query);
            if ($filterok && $relevant) {
                $snippet = coursesearch_extract_snippet($entry->definition, $query);
                $entryurl = new moodle_url('/mod/glossary/showentry.php', ['eid' => $entry->id, 'displayformat' => 'dictionary']);
                if (!empty($query)) {
                    $entryurl->param('highlight', $query);
                }
                $results[] = [
                    'type' => 'glossary_entry_content',
                    'name' => $mod->name . ': ' . $entry->concept,
                    'url' => $entryurl,
                    'modname' => $mod->modname,
                    'icon' => $mod->get_icon_url(),
                    'match' => get_string('content', 'mod_coursesearch'),
                    'snippet' => $snippet,
                    'cmid' => $mod->id,
                ];
            }
        }
    }

    return $results;
}

/**
 * Search in database entries
 *
 * Optimized to use a single JOIN query instead of N+1 queries (one per record).
 * Content is fetched in bulk and grouped by record ID for efficient processing.
 *
 * @param cm_info $mod The course module
 * @param string $query The search query
 * @return array The search results
 */
function coursesearch_search_database($mod, $query) {
    global $DB;

    $results = [];

    // Get all fields for this database (for field name lookups).
    $fields = $DB->get_records('data_fields', ['dataid' => $mod->instance], '', 'id, name, type');

    // Single JOIN query to fetch all records with their content at once.
    // This replaces N+1 queries (one per record for content).
    $sql = "SELECT r.id as recordid,
                   c.id as contentid, c.fieldid, c.content, c.content1, c.content2, c.content3, c.content4
            FROM {data_records} r
            JOIN {data_content} c ON c.recordid = r.id
            WHERE r.dataid = :dataid
            ORDER BY r.id, c.id";
    $rows = $DB->get_records_sql($sql, ['dataid' => $mod->instance]);

    // Group content by record ID for efficient processing.
    $contentbyrecord = [];
    foreach ($rows as $row) {
        $contentbyrecord[$row->recordid][] = $row;
    }

    // Process each record with O(1) lookups.
    foreach ($contentbyrecord as $recordid => $contents) {
        $recordmatched = false;
        $matchedcontent = '';
        $matchedfieldname = '';

        foreach ($contents as $content) {
            // Skip if already matched this record.
            if ($recordmatched) {
                break;
            }

            // Get field info from pre-fetched fields.
            $field = isset($fields[$content->fieldid]) ? $fields[$content->fieldid] : null;
            $fieldname = $field ? $field->name : '';

            // Search in main content.
            if (!empty($content->content) && coursesearch_is_relevant($content->content, $query)) {
                $recordmatched = true;
                $matchedcontent = $content->content;
                $matchedfieldname = $fieldname;
            } else if (!empty($content->content1) && coursesearch_is_relevant($content->content1, $query)) {
                // Also check content1-4 fields (used by some field types).
                $recordmatched = true;
                $matchedcontent = $content->content1;
                $matchedfieldname = $fieldname;
            }
        }

        if ($recordmatched) {
            $snippet = coursesearch_extract_snippet($matchedcontent, $query);
            $recordurl = new moodle_url('/mod/data/view.php', ['d' => $mod->instance, 'rid' => $recordid]);
            if (!empty($query)) {
                $recordurl->param('highlight', $query);
            }

            // Try to get a meaningful name for the record.
            $recordname = $mod->name;
            if (!empty($matchedfieldname)) {
                $recordname .= ' (' . $matchedfieldname . ')';
            }

            $results[] = [
                'type' => 'data_entry',
                'name' => $recordname,
                'url' => $recordurl,
                'modname' => $mod->modname,
                'icon' => $mod->get_icon_url(),
                'match' => get_string('content', 'mod_coursesearch'),
                'snippet' => $snippet,
                'cmid' => $mod->id,
            ];
        }
    }

    return $results;
}

/**
 * Search in H5P interactive content (mod_hvp plugin)
 *
 * @param cm_info $mod The course module
 * @param string $query The search query
 * @return array The search results
 */
function coursesearch_search_hvp($mod, $query) {
    global $DB;

    $results = [];
    $hvp = $DB->get_record('hvp', ['id' => $mod->instance], 'id, name, json_content');

    if ($hvp && !empty($hvp->json_content)) {
        // Extract all text content from the H5P JSON.
        $h5ptext = coursesearch_extract_h5p_text($hvp->json_content);

        if (!empty($h5ptext) && coursesearch_is_relevant($h5ptext, $query)) {
            $snippet = coursesearch_extract_snippet($h5ptext, $query);
            // Note: H5P content is rendered in iframe, highlighting not possible.
            $hvpurl = new moodle_url('/mod/hvp/view.php', ['id' => $mod->id]);

            $results[] = [
                'type' => 'hvp_content',
                'name' => $mod->name,
                'url' => $hvpurl,
                'modname' => $mod->modname,
                'icon' => $mod->get_icon_url(),
                'match' => get_string('content', 'mod_coursesearch'),
                'snippet' => $snippet,
                'cmid' => $mod->id,
            ];
        }
    }

    return $results;
}

/**
 * Search in H5P activity (Moodle core h5pactivity)
 *
 * @param cm_info $mod The course module
 * @param string $query The search query
 * @return array The search results
 */
function coursesearch_search_h5pactivity($mod, $query) {
    global $DB;

    $results = [];
    $context = context_module::instance($mod->id);
    $h5pcontent = $DB->get_record_sql(
        "SELECT h.id, h.jsoncontent
         FROM {h5p} h
         JOIN {files} f ON f.contenthash = h.contenthash
         WHERE f.contextid = ? AND f.component = 'mod_h5pactivity' AND f.filearea = 'package'
         LIMIT 1",
        [$context->id]
    );

    if ($h5pcontent && !empty($h5pcontent->jsoncontent)) {
        $h5ptext = coursesearch_extract_h5p_text($h5pcontent->jsoncontent);

        if (!empty($h5ptext) && coursesearch_is_relevant($h5ptext, $query)) {
            $snippet = coursesearch_extract_snippet($h5ptext, $query);
            // Note: H5P content is rendered in iframe, highlighting not possible.
            $h5purl = new moodle_url('/mod/h5pactivity/view.php', ['id' => $mod->id]);

            $results[] = [
                'type' => 'h5pactivity_content',
                'name' => $mod->name,
                'url' => $h5purl,
                'modname' => $mod->modname,
                'icon' => $mod->get_icon_url(),
                'match' => get_string('content', 'mod_coursesearch'),
                'snippet' => $snippet,
                'cmid' => $mod->id,
            ];
        }
    }

    return $results;
}

/**
 * Search for titles in the course index and add them to the results
 *
 * Optimized to accept pre-fetched sections and modinfo to avoid duplicate queries.
 *
 * @param string $query The search query
 * @param object $course The course object
 * @param array $results The current search results array
 * @param array|null $sections Pre-fetched sections (optional, will query if not provided)
 * @param course_modinfo|null $modinfo Pre-fetched modinfo (optional, will query if not provided)
 * @return array The updated search results
 */
function coursesearch_search_course_index($query, $course, $results, $sections = null, $modinfo = null) {
    global $DB;

    // Use pre-fetched modinfo or fetch if not provided.
    if ($modinfo === null) {
        $modinfo = get_fast_modinfo($course);
    }
    $cms = $modinfo->get_cms();

    // Use pre-fetched sections or fetch if not provided (fallback for backwards compatibility).
    if ($sections === null) {
        $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section', 'id, section, name, summary');
    }

    // Search in the course index (course structure) - modules only.
    // Note: Sections are already searched in coursesearch_perform_search, so we skip them here.
    foreach ($cms as $cm) {
        // Skip if not visible.
        if (!$cm->uservisible) {
            continue;
        }

        // Skip subsection modules - they are nested sections found by section search.
        if ($cm->modname === 'subsection') {
            continue;
        }

        // Check if the module name matches the query but is not already in results.
        if (stripos($cm->name, $query) !== false) {
            // Check if this result is already in the results array.
            $duplicate = false;
            foreach ($results as $result) {
                $ismoduletype = isset($result['type']) && $result['type'] === 'module';
                $namematches = isset($result['name']) && $result['name'] === $cm->name;
                if ($ismoduletype && $namematches) {
                    $duplicate = true;
                    break;
                }
            }

            // If not a duplicate, add it to results.
            if (!$duplicate) {
                $sectioninfo = coursesearch_get_section_info($cm, $sections);
                $results[] = [
                    'type' => 'course_index_title',
                    'name' => $cm->name,
                    'url' => $cm->url,
                    'modname' => $cm->modname,
                    'icon' => $cm->get_icon_url(),
                    'match' => get_string('title', 'mod_coursesearch'),
                    'snippet' => $cm->name,
                    'cmid' => $cm->id,
                    'section_number' => $sectioninfo['section_number'],
                    'section_name' => $sectioninfo['section_name'],
                ];
            }
        }
    }

    return $results;
}

/**
 * Process multilanguage tags in content to display only the appropriate language
 *
 * @param string $content The content with multilanguage tags
 * @return string The processed content with only the appropriate language
 */
function coursesearch_process_multilang($content) {
    // Get the user's current language.
    $userlang = current_language();

    // Process multilanguage tags.
    $pattern = '/\{mlang\s+([\w\-_]+)\}(.*?)\{mlang\}/s';

    // First pass: try to find content for the user's language.
    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $lang = $match[1];
            $text = $match[2];

            // If this is the user's language or a fallback language (other/en).
            if ($lang === $userlang || $lang === 'other' || $lang === 'en') {
                // Replace the entire multilang block with just this language's content.
                $content = str_replace($match[0], $text, $content);
            }
        }
    }

    // Second pass: remove any remaining multilang tags.
    $content = preg_replace($pattern, '', $content);

    return $content;
}

/**
 * Performs a case-insensitive search that works for all alphabets including Cyrillic
 *
 * @param string $haystack The string to search in
 * @param string $needle The string to search for
 * @return bool|int The position of the first occurrence or false if not found
 */
function coursesearch_mb_stripos($haystack, $needle) {
    // Replace newlines with spaces first.
    $haystack = str_replace("\n", " ", $haystack);
    $needle = str_replace("\n", " ", $needle);

    // Normalize whitespace in both strings (collapse multiple spaces to single space).
    $haystack = preg_replace('/\s+/u', ' ', $haystack);
    $needle = preg_replace('/\s+/u', ' ', $needle);

    // Convert both strings to lowercase using multibyte functions.
    $haystacklower = mb_strtolower($haystack, 'UTF-8');
    $needlelower = mb_strtolower($needle, 'UTF-8');

    // Use multibyte strpos for proper UTF-8 handling.
    return mb_strpos($haystacklower, $needlelower, 0, 'UTF-8');
}

/**
 * Count occurrences of a substring in a string, case-insensitive and multibyte-safe
 *
 * @param string $haystack The string to search in
 * @param string $needle The string to search for
 * @return int Number of occurrences
 */
function coursesearch_mb_substr_count($haystack, $needle) {
    $haystacklower = mb_strtolower($haystack, 'UTF-8');
    $needlelower = mb_strtolower($needle, 'UTF-8');
    return mb_substr_count($haystacklower, $needlelower, 'UTF-8');
}

/**
 * Check if content is relevant to the search query
 *
 * @param string $content The content to check
 * @param string $query The search query
 * @return bool Whether the content is relevant
 */
function coursesearch_is_relevant($content, $query) {
    // Process multilanguage tags.
    $content = coursesearch_process_multilang($content);

    // Extract text from HTML more effectively.
    $plaincontent = coursesearch_html_to_text($content);

    // Normalize the query - trim and collapse multiple spaces.
    $query = trim($query);
    $query = preg_replace('/\s+/u', ' ', $query);

    // Additional check for exact phrase match.
    $plaincontentnormalized = preg_replace('/\s+/u', ' ', $plaincontent);
    if (mb_stripos($plaincontentnormalized, $query) !== false) {
        return true;
    }

    // Standard check if query is found in content using multibyte-safe function.
    $pos = coursesearch_mb_stripos($plaincontent, $query);
    if ($pos === false) {
        return false;
    }

    // For short queries (3 chars or less), ensure it's a whole word match or part of a word.
    if (mb_strlen($query, 'UTF-8') <= 3) {
        // For Cyrillic and other non-Latin alphabets, we need a different approach.
        $words = preg_split('/\s+/u', $plaincontent);
        $foundmatch = false;

        foreach ($words as $word) {
            if (coursesearch_mb_stripos($word, $query) !== false) {
                $foundmatch = true;
                break;
            }
        }

        if (!$foundmatch) {
            return false;
        }
    }

    // For longer content, check if the query appears at least once per 1000 characters.
    if (mb_strlen($plaincontent, 'UTF-8') > 500) {
        $occurrences = coursesearch_mb_substr_count($plaincontent, $query);
        $expectedmin = max(1, floor(mb_strlen($plaincontent, 'UTF-8') / 1000));
        if ($occurrences < $expectedmin) {
            return false;
        }
    }

    return true;
}

/**
 * Extract a snippet of text around a search term
 *
 * @param string $content The full content to extract from
 * @param string $query The search term to find
 * @param int $length The approximate length of the snippet
 * @return string The extracted snippet with the search term highlighted
 */
function coursesearch_extract_snippet($content, $query, $length = 150) {
    // Process multilanguage tags before extracting snippet.
    $content = coursesearch_process_multilang($content);

    // Improved HTML handling.
    $plaincontent = coursesearch_html_to_text($content);

    // Find the position of the search term (case-insensitive) with multibyte support for Cyrillic.
    $pos = coursesearch_mb_stripos($plaincontent, $query);

    if ($pos === false) {
        // If the term isn't found, return the beginning of the content.
        return mb_substr($plaincontent, 0, $length, 'UTF-8') . '...';
    }

    // Calculate the start position of the snippet.
    $start = max(0, $pos - floor($length / 2));

    // If we're not starting from the beginning, add ellipsis.
    $prefix = ($start > 0) ? '...' : '';

    // Extract the snippet.
    $snippet = $prefix . mb_substr($plaincontent, $start, $length, 'UTF-8') . '...';

    // Highlight the search term in the snippet.
    // The snippet is plain text (from coursesearch_html_to_text), so it's safe to add HTML.
    // Use preg_quote to safely escape the query for regex to prevent injection.
    $pattern = '/(' . preg_quote($query, '/') . ')/iu';
    $replacement = '<span class="highlight">$1</span>';
    $snippet = preg_replace($pattern, $replacement, $snippet);

    return $snippet;
}

/**
 * Extract multiple snippets for all occurrences of a search term
 *
 * @param string $content The full content to extract from
 * @param string $query The search term to find
 * @param int $length The approximate length of each snippet
 * @param int $maxresults Maximum number of snippets to return
 * @return array Array of snippets, one for each occurrence
 */
function coursesearch_extract_multiple_snippets($content, $query, $length = 150, $maxresults = 5) {
    // Process multilanguage tags before extracting snippets.
    $content = coursesearch_process_multilang($content);

    // Improved HTML handling.
    $plaincontent = coursesearch_html_to_text($content);

    // Find all positions of the search term (case-insensitive) with multibyte support.
    $snippets = [];
    $contentlower = mb_strtolower($plaincontent, 'UTF-8');
    $querylower = mb_strtolower($query, 'UTF-8');
    $querylen = mb_strlen($querylower, 'UTF-8');
    $contentlen = mb_strlen($plaincontent, 'UTF-8');

    $pos = 0;
    $occurrence = 0;

    // If maxresults is 0, set to a very high number to effectively disable the limit.
    // This allows admins to disable the limit, but we still cap it at a reasonable maximum
    // to prevent infinite loops or memory issues.
    if ($maxresults <= 0) {
        $maxresults = 1000; // Effectively unlimited but with a safety cap.
    }

    while ($pos < $contentlen && $occurrence < $maxresults) {
        // Find next occurrence.
        $foundpos = mb_strpos($contentlower, $querylower, $pos, 'UTF-8');

        if ($foundpos === false) {
            break;
        }

        // Calculate the start position of the snippet.
        $start = max(0, $foundpos - floor($length / 2));

        // If we're not starting from the beginning, add ellipsis.
        $prefix = ($start > 0) ? '...' : '';

        // Extract the snippet.
        $snippet = $prefix . mb_substr($plaincontent, $start, $length, 'UTF-8') . '...';

        // Highlight the search term in the snippet.
        $pattern = '/(' . preg_quote($query, '/') . ')/iu';
        $replacement = '<span class="highlight">$1</span>';
        $snippet = preg_replace($pattern, $replacement, $snippet);

        $snippets[] = $snippet;

        // Move position past this occurrence.
        $pos = $foundpos + $querylen;
        $occurrence++;
    }

    // If no snippets found, return at least one from the beginning.
    if (empty($snippets)) {
        $snippets[] = mb_substr($plaincontent, 0, $length, 'UTF-8') . '...';
    }

    return $snippets;
}

/**
 * Enhanced HTML to text conversion that better handles complex HTML structures
 *
 * @param string $html The HTML content to convert
 * @return string Plain text version of the content
 */
function coursesearch_html_to_text($html) {
    // First try the DOM method which works best for complex HTML.
    if (class_exists('DOMDocument')) {
        try {
            // Create a new DOM document.
            $dom = new DOMDocument();

            // Suppress errors from malformed HTML.
            libxml_use_internal_errors(true);

            // Add XML encoding declaration to handle special characters.
            if (!preg_match('/<\?xml\s+encoding=/', $html)) {
                $html = '<?xml encoding="UTF-8">' . $html;
            }

            // Load the HTML.
            $dom->loadHTML($html);

            // Get the text content.
            $domtext = $dom->textContent;

            // Clear any errors.
            libxml_clear_errors();

            // If we got text content, use it.
            if (!empty($domtext)) {
                // Normalize whitespace.
                $domtext = preg_replace('/\s+/', ' ', $domtext);
                $domtext = trim($domtext);

                // Remove configured placeholder patterns.
                $domtext = coursesearch_filter_placeholders($domtext);

                // Normalize whitespace again after removing placeholders.
                $domtext = preg_replace('/\s+/', ' ', $domtext);
                $domtext = trim($domtext);

                return $domtext;
            }
        } catch (Exception $e) {
            // If there's an error, continue with the fallback method.
            debugging('DOM parsing error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // Fallback method if DOM parsing fails.

    // First normalize spaces in HTML attributes and remove excessive whitespace.
    $html = preg_replace('/\s+/', ' ', $html);

    // Remove script and style elements.
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

    // Special handling for MS Office-generated HTML.
    // Remove MS Office specific comments and conditional tags.
    $html = preg_replace('/<!--\[if.*?<!\[endif\]-->/s', '', $html);
    $html = preg_replace('/<!--\[if.*?\]>/s', '', $html);
    $html = preg_replace('/<!\[endif\]-->/s', '', $html);

    // Handle MS Office XML data.
    $html = preg_replace('/<xml>.*?<\/xml>/s', '', $html);
    $html = preg_replace('/<w:.*?<\/w:.*?>/s', '', $html);

    // Remove MS Office specific attributes that might interfere with text extraction.
    $html = preg_replace('/\s+mso-[^=]*="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+o:[^=]*="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+v:[^=]*="[^"]*"/i', '', $html);

    // Replace common block elements with newlines before and after.
    $html = preg_replace('/<\/(div|p|h[1-6]|table|tr|ul|ol|li|blockquote|section|article)>/i', "$0\n", $html);
    $html = preg_replace('/<(div|p|h[1-6]|table|tr|ul|ol|li|blockquote|section|article)[^>]*>/i', "\n$0", $html);

    // Replace <br> tags with newlines.
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

    // Replace table cells with spaces.
    $html = preg_replace('/<\/td>\s*<td[^>]*>/i', ' ', $html);

    // Convert HTML entities.
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Strip all remaining HTML tags.
    $text = strip_tags($html);

    // Normalize whitespace.
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    // Remove configured placeholder patterns.
    $text = coursesearch_filter_placeholders($text);

    // Normalize whitespace again after removing placeholders.
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    return $text;
}

/**
 * Filter out configured placeholder patterns from text
 *
 * This function removes internal Moodle placeholders (like @@PLUGINFILE@@) and
 * custom patterns configured by administrators from searchable text.
 * These placeholders are internal markers not visible to users and should
 * not be searchable to prevent false positive matches.
 *
 * @param string $text The text to filter
 * @return string The filtered text with placeholders removed
 */
function coursesearch_filter_placeholders($text) {
    // Get configured patterns from admin settings.
    $configuredpatterns = get_config('mod_coursesearch', 'excludedplaceholders');

    // If no patterns configured, return text as-is.
    if (empty($configuredpatterns)) {
        return $text;
    }

    // Split by newlines and filter out empty lines.
    $patterns = array_filter(
        array_map('trim', explode("\n", $configuredpatterns)),
        function ($line) {
            return !empty($line);
        }
    );

    // Apply each pattern to remove placeholders.
    foreach ($patterns as $pattern) {
        // Validate pattern is not empty and doesn't contain dangerous constructs.
        // Basic safety check: ensure it's a reasonable regex pattern.
        if (!empty($pattern) && strlen($pattern) < 500) {
            try {
                // Apply pattern with case-insensitive flag.
                $text = preg_replace('/' . $pattern . '/i', '', $text);
            } catch (Exception $e) {
                // If pattern is invalid, log and skip it.
                debugging('Invalid placeholder pattern in coursesearch settings: ' . $pattern, DEBUG_DEVELOPER);
            } catch (Error $e) {
                // Catch PHP 7+ Error exceptions as well.
                $errormsg = 'Invalid placeholder pattern in coursesearch settings: ' . $pattern . ' - ' . $e->getMessage();
                debugging($errormsg, DEBUG_DEVELOPER);
            }
        }
    }

    return $text;
}

/**
 * Extract searchable text content from H5P JSON
 *
 * H5P stores content in JSON format with text in various fields like 'text', 'label',
 * 'title', 'description', 'question', 'answer', etc. This function recursively
 * extracts all text content from the JSON structure.
 *
 * @param string $jsoncontent The JSON content from H5P
 * @return string Combined plain text from all text fields
 */
function coursesearch_extract_h5p_text($jsoncontent) {
    if (empty($jsoncontent)) {
        return '';
    }

    // Decode JSON.
    $data = json_decode($jsoncontent, true);
    if ($data === null) {
        return '';
    }

    // Text fields commonly used in H5P content types.
    $textfields = [
        'text', 'label', 'title', 'description', 'question', 'answer',
        'tip', 'feedback', 'correct', 'incorrect', 'header', 'summary',
        'introduction', 'explanation', 'hint', 'placeholder', 'alt',
        'caption', 'credit', 'copyright', 'definition', 'term',
        'statement', 'quote', 'author', 'source', 'taskDescription',
        'endScreenTitle', 'endScreenSubtitle', 'retryButtonLabel',
        'showSolutionsButtonLabel', 'checkAnswerButtonLabel',
        'submitButtonLabel', 'continueButtonLabel', 'proceedButtonLabel',
        'panels', 'content',
        'slides', 'elements',
        'interactiveVideo', 'video', 'interactions',
        'questions', 'introPage', 'resultPage',
    ];

    $extractedtext = [];
    coursesearch_extract_h5p_text_recursive($data, $textfields, $extractedtext);

    // Join all extracted text with spaces.
    $combinedtext = implode(' ', $extractedtext);

    // Clean up the text.
    $combinedtext = preg_replace('/\s+/', ' ', $combinedtext);
    $combinedtext = trim($combinedtext);

    return $combinedtext;
}

/**
 * Recursively extract text from H5P data structure
 *
 * @param mixed $data The data to process (array or value)
 * @param array $textfields List of field names that contain text
 * @param array $extractedtext Reference to array collecting extracted text
 */
function coursesearch_extract_h5p_text_recursive($data, $textfields, &$extractedtext) {
    if (is_string($data)) {
        // Check if string contains HTML.
        if (preg_match('/<[^>]+>/', $data)) {
            // Extract text from HTML.
            $text = coursesearch_html_to_text($data);
            if (!empty($text) && mb_strlen($text, 'UTF-8') > 2) {
                $extractedtext[] = $text;
            }
        } else if (mb_strlen($data, 'UTF-8') > 2) {
            // Plain text - add if it's meaningful (more than 2 chars).
            $extractedtext[] = $data;
        }
    } else if (is_array($data)) {
        foreach ($data as $key => $value) {
            // If key is a known text field or is numeric (array index), process the value.
            if (is_numeric($key) || in_array($key, $textfields)) {
                coursesearch_extract_h5p_text_recursive($value, $textfields, $extractedtext);
            } else if (is_array($value)) {
                // For other keys that contain arrays, still recurse.
                coursesearch_extract_h5p_text_recursive($value, $textfields, $extractedtext);
            } else if (is_string($value) && in_array($key, $textfields)) {
                // Direct text field.
                coursesearch_extract_h5p_text_recursive($value, $textfields, $extractedtext);
            }
        }
    }
}
