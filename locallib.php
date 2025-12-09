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

defined('MOODLE_INTERNAL') || die;

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
    
    // Trim the query to remove leading and trailing whitespace
    $query = trim($query);
    
    // Limit query length to prevent performance issues and potential abuse
    $query = mb_substr($query, 0, 500);
    
    if (empty($query)) {
        return array();
    }
    
    $results = array();
    
    // Search course sections - only if not specifically looking for other content types
    if ($filter == 'all' || $filter == 'sections') {
        $sections = $DB->get_records('course_sections', array('course' => $course->id), 'section', 'id, section, name, summary');
        foreach ($sections as $section) {
            // Search in section name - use case-insensitive comparison
            if (!empty($section->name) && (stripos($section->name, $query) !== false || 
                                          stripos(get_string('section') . ' ' . $section->section, $query) !== false)) {
                // Create a direct URL to this section with explicit section parameter
                $sectionurl = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $section->section));
                
                $results[] = array(
                    'type' => 'section_name',
                    'name' => get_string('section') . ': ' . $section->name,
                    'url' => $sectionurl,
                    'modname' => 'section',
                    'icon' => new moodle_url('/pix/i/section.png'),
                    'match' => 'title',
                    'snippet' => coursesearch_extract_snippet($section->name, $query),
                    'section_number' => $section->section // Store the section number for reference
                );
            }
            
            // Search in section summary - use case-insensitive comparison with relevance check
            if (!empty($section->summary) && coursesearch_is_relevant($section->summary, $query)) {
                $sectionurl = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $section->section));
                $results[] = array(
                    'type' => 'section_summary',
                    'name' => get_string('section') . ': ' . ($section->name ? $section->name : get_string('section') . ' ' . $section->section),
                    'url' => $sectionurl,
                    'modname' => 'section',
                    'icon' => new moodle_url('/pix/i/section.png'),
                    'match' => 'description or content',
                    'snippet' => coursesearch_extract_snippet($section->summary, $query)
                );
            }
            
            // If section has no name but number matches the query (e.g., searching for "1" finds "Section 1")
            if (empty($section->name) && (stripos(get_string('section') . ' ' . $section->section, $query) !== false)) {
                $sectionurl = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $section->section));
                $results[] = array(
                    'type' => 'section_number',
                    'name' => get_string('section') . ' ' . $section->section,
                    'url' => $sectionurl,
                    'modname' => 'section',
                    'icon' => new moodle_url('/pix/i/section.png'),
                    'match' => 'title',
                    'snippet' => get_string('section') . ' ' . $section->section
                );
            }
        }
    }
    
    // Search course modules based on the scope.
    $modinfo = get_fast_modinfo($course);
    
    foreach ($modinfo->get_cms() as $mod) {
        // Skip if the module is not visible or the user can't access it.
        if (!$mod->uservisible) {
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
        
        // Check if the module name contains the search query (only if filter is 'all' or 'title')
        if (($filter == 'all' || $filter == 'title') && coursesearch_mb_stripos($mod->name, $query) !== false) {
            // For labels and similar inline content, we need to create a URL with an anchor
            if ($mod->modname === 'label' || $mod->modname === 'html') {
                // Include section parameter to ensure the correct section is displayed
                $sectionnum = isset($mod->sectionnum) ? $mod->sectionnum : (isset($mod->section) ? $mod->section : null);
                $urlparams = array('id' => $course->id);
                if ($sectionnum !== null) {
                    $urlparams['section'] = $sectionnum;
                }
                $moduleurl = new moodle_url('/course/view.php', $urlparams);
                $moduleurl->set_anchor('module-' . $mod->id);
            } else {
                // For other module types, use the module's URL
                $moduleurl = $mod->url;
            }
            
            $results[] = array(
                'type' => 'module',
                'name' => $mod->name,
                'url' => $moduleurl,  // URL with proper anchor for inline content
                'modname' => $mod->modname,
                'icon' => $mod->get_icon_url(),
                'match' => 'title',
                'snippet' => $mod->name,
                'cmid' => $mod->id  // Store the course module ID for reference
            );
            continue; // Skip further checks for this module if we already found a match.
        }
        
        // Search in the module description/intro if available (only if filter is 'all' or 'description')
        if ($filter == 'all' || $filter == 'description') {
            // Get the module description from the appropriate table based on module type
            $description = '';
            $module_record = null;
            
            // Try to get the module record with intro/description
            if (!empty($mod->modname)) {
                // Validate module name to prevent SQL injection - must be alphanumeric with underscores only
                $modname = clean_param($mod->modname, PARAM_PLUGIN);
                if (empty($modname) || $modname !== $mod->modname) {
                    // Invalid module name, skip this module
                    continue;
                }
                $module_record = $DB->get_record($modname, array('id' => $mod->instance), '*', IGNORE_MISSING);
            }
            
            // Most modules use 'intro' field for description
            if ($module_record && isset($module_record->intro)) {
                $description = $module_record->intro;
            } 
            // Some modules might use 'description' or other fields
            else if ($module_record && isset($module_record->description)) {
                $description = $module_record->description;
            }
            // For custom modules with different field names
            else if ($module_record && isset($module_record->content)) {
                $description = $module_record->content;
            }
            // For modules with summary field
            else if ($module_record && isset($module_record->summary)) {
                $description = $module_record->summary;
            }
        }
        
        // Search in the description if we found one
        if (!empty($description)) {
            if (coursesearch_is_relevant($description, $query)) {
                // For labels and similar inline content, we need to create a URL with an anchor
                if ($mod->modname === 'label' || $mod->modname === 'html') {
                    // Include section parameter to ensure the correct section is displayed
                    $sectionnum = isset($mod->sectionnum) ? $mod->sectionnum : (isset($mod->section) ? $mod->section : null);
                    $urlparams = array('id' => $course->id);
                    if ($sectionnum !== null) {
                        $urlparams['section'] = $sectionnum;
                    }
                    $moduleurl = new moodle_url('/course/view.php', $urlparams);
                    $moduleurl->set_anchor('module-' . $mod->id);
                } else {
                    // For other module types, use the module's URL
                    $moduleurl = $mod->url;
                }
                
                $snippet = coursesearch_extract_snippet($description, $query);
                $results[] = array(
                    'type' => 'module_description',
                    'name' => $mod->name,
                    'url' => $moduleurl,
                    'modname' => $mod->modname,
                    'icon' => $mod->get_icon_url(),
                    'match' => 'description or content',
                    'snippet' => $snippet,
                    'cmid' => $mod->id
                );
                continue; // Skip further checks for this module if we already found a match.
            }
        }
        
        // Search in module content based on the module type (only if filter is 'all' or 'content')
        // For forums, we want to search content regardless of the filter when 'forums' filter is selected
        if ($filter == 'all' || $filter == 'content' || ($filter == 'forums' && $mod->modname == 'forum')) {
            switch ($mod->modname) {
            case 'page':
                // Search in page content
                $page = $DB->get_record('page', array('id' => $mod->instance), 'id, name, content');
                if ($page && coursesearch_is_relevant($page->content, $query)) {
                    $snippet = coursesearch_extract_snippet($page->content, $query);
                    $results[] = array(
                        'type' => 'page_content',
                        'name' => $mod->name,
                        'url' => $mod->url,
                        'modname' => $mod->modname,
                        'icon' => $mod->get_icon_url(),
                        'match' => 'content',
                        'snippet' => $snippet
                    );
                }
                break;
                
            case 'book':
                // Search in book content and titles
                $bookchapters = $DB->get_records('book_chapters', array('bookid' => $mod->instance));
                foreach ($bookchapters as $chapter) {
                    // First check if the chapter title matches the query
                    if (($filter == 'all' || $filter == 'title') && coursesearch_mb_stripos($chapter->title, $query) !== false) {
                        $chapterurl = new moodle_url('/mod/book/view.php', array('id' => $mod->id, 'chapterid' => $chapter->id));
                        $results[] = array(
                            'type' => 'book_title',
                            'name' => $mod->name . ': ' . $chapter->title,
                            'url' => $chapterurl,
                            'modname' => $mod->modname,
                            'icon' => $mod->get_icon_url(),
                            'match' => 'title',
                            'snippet' => $chapter->title
                        );
                        // Skip content check for this chapter since we already found a match
                        continue;
                    }
                    
                    // Then check if the chapter content matches the query
                    if (($filter == 'all' || $filter == 'content') && coursesearch_is_relevant($chapter->content, $query)) {
                        $snippet = coursesearch_extract_snippet($chapter->content, $query);
                        $chapterurl = new moodle_url('/mod/book/view.php', array('id' => $mod->id, 'chapterid' => $chapter->id));
                        $results[] = array(
                            'type' => 'book_content',
                            'name' => $mod->name . ': ' . $chapter->title,
                            'url' => $chapterurl,
                            'modname' => $mod->modname,
                            'icon' => $mod->get_icon_url(),
                            'match' => 'content',
                            'snippet' => $snippet
                        );
                    }
                }
                break;
                
            case 'label':
                // Search in label content
                $label = $DB->get_record('label', array('id' => $mod->instance), 'id, name, intro');
                if ($label && coursesearch_is_relevant($label->intro, $query)) {
                    $snippet = coursesearch_extract_snippet($label->intro, $query);
                    // For labels, create a URL with anchor directly using the cmid we already have
                    // Include section parameter to ensure the correct section is displayed
                    // Also include search query as parameter so JavaScript can scroll to the matched text
                    $sectionnum = isset($mod->sectionnum) ? $mod->sectionnum : (isset($mod->section) ? $mod->section : null);
                    $urlparams = array('id' => $course->id);
                    if ($sectionnum !== null) {
                        $urlparams['section'] = $sectionnum;
                    }
                    // Add search query parameter for JavaScript scrolling
                    if (!empty($query)) {
                        $urlparams['highlight'] = urlencode($query);
                    }
                    $moduleurl = new moodle_url('/course/view.php', $urlparams);
                    $moduleurl->set_anchor('module-' . $mod->id);
                    $results[] = array(
                        'type' => 'label_content',
                        'name' => $mod->name,
                        'url' => $moduleurl,
                        'modname' => $mod->modname,
                        'icon' => $mod->get_icon_url(),
                        'match' => 'content',
                        'snippet' => $snippet,
                        'cmid' => $mod->id  // Store the course module ID for reference
                    );
                }
                break;
                
            case 'lesson':
                // Search in lesson pages
                $lessonpages = $DB->get_records('lesson_pages', array('lessonid' => $mod->instance));
                foreach ($lessonpages as $page) {
                    if (coursesearch_is_relevant($page->contents, $query)) {
                        $snippet = coursesearch_extract_snippet($page->contents, $query);
                        $pageurl = new moodle_url('/mod/lesson/view.php', array('id' => $mod->id, 'pageid' => $page->id));
                        $results[] = array(
                            'type' => 'lesson_content',
                            'name' => $mod->name . ': ' . $page->title,
                            'url' => $pageurl,
                            'modname' => $mod->modname,
                            'icon' => $mod->get_icon_url(),
                            'match' => 'content',
                            'snippet' => $snippet
                        );
                    }
                }
                break;
                
            case 'forum':
                // Determine what to search in forums based on the filter
                $search_forum_titles = ($filter == 'all' || $filter == 'title' || $filter == 'forums');
                $search_forum_content = ($filter == 'all' || $filter == 'content' || $filter == 'forums');
                $search_forum_subjects = ($filter == 'all' || $filter == 'title' || $filter == 'forums');
                
                // Search in forum discussions and posts
                $discussions = $DB->get_records('forum_discussions', array('forum' => $mod->instance));
                
                // Keep track of processed posts to avoid duplicates
                $processedPosts = array();
                
                foreach ($discussions as $discussion) {
                    // First check if the discussion subject/topic name matches the query
                    if ($search_forum_titles && coursesearch_mb_stripos($discussion->name, $query) !== false) {
                        // Get the first post of the discussion to create a proper URL
                        $firstpost = $DB->get_record('forum_posts', array('discussion' => $discussion->id, 'parent' => 0));
                        if ($firstpost) {
                            $discussionurl = new moodle_url('/mod/forum/discuss.php', array('d' => $discussion->id));
                            
                            // Get forum name
                            $forum = $DB->get_record('forum', array('id' => $mod->instance), 'name');
                            $forumname = $forum ? $forum->name : $mod->name;
                            
                            $results[] = array(
                                'type' => 'forum_discussion',
                                'name' => $discussion->name,
                                'url' => $discussionurl,
                                'modname' => $mod->modname,
                                'icon' => $mod->get_icon_url(),
                                'match' => 'title',
                                'snippet' => $discussion->name,
                                'forum_name' => $forumname
                            );
                            
                            // Mark the first post as processed to avoid duplicate results
                            $processedPosts[$firstpost->id] = true;
                        }
                    }
                    
                    // Only continue with post checks if we're searching forum content
                    if (!$search_forum_content && !$search_forum_subjects) {
                        continue;
                    }
                    
                    // Check posts in this discussion
                    $posts = $DB->get_records('forum_posts', array('discussion' => $discussion->id));
                    foreach ($posts as $post) {
                        // Skip if we've already processed this post
                        if (isset($processedPosts[$post->id])) {
                            continue;
                        }
                        
                        // Mark this post as processed
                        $processedPosts[$post->id] = true;
                        
                        // Check post subject (for replies)
                        if ($search_forum_subjects && coursesearch_mb_stripos($post->subject, $query) !== false) {
                            $posturl = new moodle_url('/mod/forum/discuss.php', array('d' => $discussion->id, 'p' => $post->id));
                            $posturl->set_anchor('p' . $post->id);
                            
                            // Get forum name
                            $forum = $DB->get_record('forum', array('id' => $mod->instance), 'name');
                            $forumname = $forum ? $forum->name : $mod->name;
                            
                            $results[] = array(
                                'type' => 'forum_post',
                                'name' => $post->subject,
                                'url' => $posturl,
                                'modname' => $mod->modname,
                                'icon' => $mod->get_icon_url(),
                                'match' => 'title',
                                'snippet' => $post->subject,
                                'forum_name' => $forumname
                            );
                            
                            // Skip content check for this post since we already found a match
                            continue;
                        }
                        
                        // Check post content
                        if ($search_forum_content && coursesearch_is_relevant($post->message, $query)) {
                            $snippet = coursesearch_extract_snippet($post->message, $query);
                            $posturl = new moodle_url('/mod/forum/discuss.php', array('d' => $discussion->id, 'p' => $post->id));
                            $posturl->set_anchor('p' . $post->id);
                            
                            // Get forum name
                            $forum = $DB->get_record('forum', array('id' => $mod->instance), 'name');
                            $forumname = $forum ? $forum->name : $mod->name;
                            
                            $results[] = array(
                                'type' => 'forum_post',
                                'name' => $post->subject,
                                'url' => $posturl,
                                'modname' => $mod->modname,
                                'icon' => $mod->get_icon_url(),
                                'match' => 'content',
                                'snippet' => $snippet,
                                'forum_name' => $forumname
                            );
                        }
                    }
                }
                break;
                
            case 'wiki':
                // Search in wiki pages
                $wikipages = $DB->get_records('wiki_pages', array('subwikiid' => $mod->instance), 'id, title, cachedcontent');
                // Note: wiki_pages uses subwikiid, but we need to get the subwikiid from the wiki instance
                // First, get the wiki record to find subwikis
                $wiki = $DB->get_record('wiki', array('id' => $mod->instance), 'id, name, firstpagetitle');
                if ($wiki) {
                    // Get all subwikis for this wiki
                    $subwikis = $DB->get_records('wiki_subwikis', array('wikiid' => $wiki->id), 'id');
                    foreach ($subwikis as $subwiki) {
                        // Get all pages in this subwiki
                        $wikipages = $DB->get_records('wiki_pages', array('subwikiid' => $subwiki->id), 'id, title, cachedcontent');
                        foreach ($wikipages as $wikipage) {
                            // First check if the page title matches
                            if (($filter == 'all' || $filter == 'title') && coursesearch_mb_stripos($wikipage->title, $query) !== false) {
                                $pageurl = new moodle_url('/mod/wiki/view.php', array('id' => $mod->id, 'pageid' => $wikipage->id));
                                $results[] = array(
                                    'type' => 'wiki_page_title',
                                    'name' => $mod->name . ': ' . $wikipage->title,
                                    'url' => $pageurl,
                                    'modname' => $mod->modname,
                                    'icon' => $mod->get_icon_url(),
                                    'match' => 'title',
                                    'snippet' => $wikipage->title,
                                    'cmid' => $mod->id
                                );
                                // Skip content check for this page since we already found a match
                                continue;
                            }
                            
                            // Then check if the page content matches
                            if (($filter == 'all' || $filter == 'content') && !empty($wikipage->cachedcontent) && coursesearch_is_relevant($wikipage->cachedcontent, $query)) {
                                $snippet = coursesearch_extract_snippet($wikipage->cachedcontent, $query);
                                $pageurl = new moodle_url('/mod/wiki/view.php', array('id' => $mod->id, 'pageid' => $wikipage->id));
                                $results[] = array(
                                    'type' => 'wiki_page_content',
                                    'name' => $mod->name . ': ' . $wikipage->title,
                                    'url' => $pageurl,
                                    'modname' => $mod->modname,
                                    'icon' => $mod->get_icon_url(),
                                    'match' => 'content',
                                    'snippet' => $snippet,
                                    'cmid' => $mod->id
                                );
                            }
                        }
                    }
                }
                break;
            }
        }
    }
    
    // Search for titles in the course index (unless we're filtering by content or description only)
    if ($filter != 'content' && $filter != 'description') {
        $results = coursesearch_search_course_index($query, $course, $results);
    }
    
    // Apply additional filtering based on the selected filter
    if ($filter != 'all') {
        $filtered_results = array();
        
        foreach ($results as $result) {
            // Filter by match type
            if ($filter == 'title' && $result['match'] == 'title') {
                $filtered_results[] = $result;
            } else if ($filter == 'content' && $result['match'] == 'content') {
                $filtered_results[] = $result;
            } else if ($filter == 'description' && $result['match'] == 'description or content') {
                $filtered_results[] = $result;
            } else if ($filter == 'sections' && $result['modname'] == 'section') {
                $filtered_results[] = $result;
            } else if ($filter == 'activities') {
                $activity_mods = array('assign', 'quiz', 'choice', 'feedback', 'lesson', 'workshop', 'data', 'glossary', 'wiki', 'forum');
                if (in_array($result['modname'], $activity_mods)) {
                    $filtered_results[] = $result;
                }
            } else if ($filter == 'resources') {
                $resource_mods = array('book', 'file', 'folder', 'imscp', 'label', 'page', 'resource', 'url');
                if (in_array($result['modname'], $resource_mods)) {
                    $filtered_results[] = $result;
                }
            } else if ($filter == 'forums' && $result['modname'] == 'forum') {
                $filtered_results[] = $result;
            }
        }
        
        $results = $filtered_results;
    }
    
    // Fix the issue with search results that have 'match' set to 'title' by ensuring they all have valid URLs
    foreach ($results as &$result) {
        if ($result['match'] == 'title' && empty($result['url'])) {
            $result['url'] = new moodle_url('/course/view.php', array('id' => $course->id));
        }
    }
    
    return $results;
}

/**
 * Search for titles in the course index and add them to the results
 *
 * @param string $query The search query
 * @param object $course The course object
 * @param array $results The current search results array
 * @return array The updated search results
 */
function coursesearch_search_course_index($query, $course, $results) {
    global $DB;
    
    // Get all course modules
    $modinfo = get_fast_modinfo($course);
    $cms = $modinfo->get_cms();
    
    // Get all sections
    $sections = $DB->get_records('course_sections', array('course' => $course->id), 'section', 'id, section, name, summary');
    
    // Search in the course index (course structure)
    foreach ($cms as $cm) {
        // Skip if not visible
        if (!$cm->uservisible) {
            continue;
        }
        
        // Check if the module name matches the query but is not already in results
        // This catches modules that appear in the course index but might not have been found by other searches
        if (stripos($cm->name, $query) !== false) {
            // Check if this result is already in the results array
            $duplicate = false;
            foreach ($results as $result) {
                if (isset($result['type']) && $result['type'] === 'module' && 
                    isset($result['name']) && $result['name'] === $cm->name) {
                    $duplicate = true;
                    break;
                }
            }
            
            // If not a duplicate, add it to results
            if (!$duplicate) {
                $results[] = array(
                    'type' => 'course_index_title',
                    'name' => $cm->name,
                    'url' => $cm->url,
                    'modname' => $cm->modname,
                    'icon' => $cm->get_icon_url(),
                    'match' => 'title in course index',
                    'snippet' => $cm->name
                );
            }
        }
    }
    
    // Also search section titles that appear in the course index
    foreach ($sections as $section) {
        if (!empty($section->name) && stripos($section->name, $query) !== false) {
            // Check if this section is already in results
            $duplicate = false;
            foreach ($results as $result) {
                if (isset($result['type']) && $result['type'] === 'section_name' && 
                    isset($result['name']) && $result['name'] === (get_string('section') . ': ' . $section->name)) {
                    $duplicate = true;
                    break;
                }
            }
            
            // If not a duplicate, add it
            if (!$duplicate) {
                $sectionurl = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $section->section));
                $results[] = array(
                    'type' => 'course_index_section',
                    'name' => get_string('section') . ': ' . $section->name,
                    'url' => $sectionurl,
                    'modname' => 'section',
                    'icon' => new moodle_url('/pix/i/section.png'),
                    'match' => 'title in course index',
                    'snippet' => $section->name
                );
            }
        }
    }
    
    return $results;
}

/**
 * Generate a URL with an anchor to the specific content on a page
 *
 * @param moodle_url $baseurl The base URL of the page
 * @param string $modname The module name
 * @param string $contenttype The type of content
 * @param int $instanceid The instance ID
 * @param int $courseid The course ID
 * @return moodle_url The URL with anchor
 */
function coursesearch_generate_anchored_url($baseurl, $modname, $contenttype, $instanceid, $courseid) {
    global $CFG;
    
    // For label and other inline content, we need to link to the course page with an anchor
    if ($modname === 'label' || $contenttype === 'text_field') {
        // Create a URL to the course page with an anchor to the specific module
        $url = new moodle_url('/course/view.php', array('id' => $courseid));
        
        // Use the correct anchor format based on Moodle's DOM structure
        // The format is 'module-{cmid}' where cmid is the course module ID
        $cmid = coursesearch_get_cmid_from_instance($modname, $instanceid, $courseid);
        if ($cmid) {
            $url->set_anchor('module-' . $cmid);
        } else {
            // Fallback to the instance ID if we can't find the cmid
            $url->set_anchor('module-' . $instanceid);
        }
        return $url;
    }
    
    // For book chapters, we need to add the chapter ID to the URL
    if ($modname === 'book' && $contenttype === 'book_content') {
        // The chapter ID should be passed as part of the URL
        return $baseurl;
    }
    
    // For other content types, return the original URL
    return $baseurl;
}

/**
 * Get the course module ID from an instance ID
 *
 * @param string $modname The module name
 * @param int $instanceid The instance ID
 * @param int $courseid The course ID
 * @return int|false The course module ID or false if not found
 */
function coursesearch_get_cmid_from_instance($modname, $instanceid, $courseid) {
    global $DB;
    
    try {
        $cm = get_coursemodule_from_instance($modname, $instanceid, $courseid);
        if ($cm) {
            return $cm->id;
        }
    } catch (Exception $e) {
        // If there's an error, return false
        return false;
    }
    
    return false;
}

/**
 * Process multilanguage tags in content to display only the appropriate language
 *
 * @param string $content The content with multilanguage tags
 * @return string The processed content with only the appropriate language
 */
function coursesearch_process_multilang($content) {
    global $USER, $CFG;
    
    // Get the user's current language
    $userlang = current_language();
    
    // Process multilanguage tags
    $pattern = '/\{mlang\s+([\w\-_]+)\}(.*?)\{mlang\}/s';
    
    // First pass: try to find content for the user's language
    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $lang = $match[1];
            $text = $match[2];
            
            // If this is the user's language or a fallback language (other/en)
            if ($lang === $userlang || $lang === 'other' || $lang === 'en') {
                // Replace the entire multilang block with just this language's content
                $content = str_replace($match[0], $text, $content);
            }
        }
    }
    
    // Second pass: remove any remaining multilang tags
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
    // Replace newlines with spaces first
    $haystack = str_replace("\n", " ", $haystack);
    $needle = str_replace("\n", " ", $needle);
    
    // Normalize whitespace in both strings (collapse multiple spaces to single space)
    $haystack = preg_replace('/\s+/u', ' ', $haystack);
    $needle = preg_replace('/\s+/u', ' ', $needle);
    
    // Convert both strings to lowercase using multibyte functions
    $haystack_lower = mb_strtolower($haystack, 'UTF-8');
    $needle_lower = mb_strtolower($needle, 'UTF-8');
    
    // Use multibyte strpos for proper UTF-8 handling
    return mb_strpos($haystack_lower, $needle_lower, 0, 'UTF-8');
}

/**
 * Count occurrences of a substring in a string, case-insensitive and multibyte-safe
 * 
 * @param string $haystack The string to search in
 * @param string $needle The string to search for
 * @return int Number of occurrences
 */
function coursesearch_mb_substr_count($haystack, $needle) {
    $haystack_lower = mb_strtolower($haystack, 'UTF-8');
    $needle_lower = mb_strtolower($needle, 'UTF-8');
    return mb_substr_count($haystack_lower, $needle_lower, 'UTF-8');
}

/**
 * Check if content is relevant to the search query
 *
 * @param string $content The content to check
 * @param string $query The search query
 * @return bool Whether the content is relevant
 */
function coursesearch_is_relevant($content, $query) {
    // Process multilanguage tags
    $content = coursesearch_process_multilang($content);
    
    // Extract text from HTML more effectively
    $plain_content = coursesearch_html_to_text($content);
    
    // Normalize the query - trim and collapse multiple spaces
    $query = trim($query);
    $query = preg_replace('/\s+/u', ' ', $query);
    
    // Additional check for exact phrase match
    $plain_content_normalized = preg_replace('/\s+/u', ' ', $plain_content);
    if (mb_stripos($plain_content_normalized, $query) !== false) {
        return true;
    }
    
    // Standard check if query is found in content using multibyte-safe function
    $pos = coursesearch_mb_stripos($plain_content, $query);
    if ($pos === false) {
        return false;
    }
    
    // For short queries (3 chars or less), ensure it's a whole word match or part of a word
    if (mb_strlen($query, 'UTF-8') <= 3) {
        // For Cyrillic and other non-Latin alphabets, we need a different approach
        // since \b doesn't work well with them in regex
        $words = preg_split('/\s+/u', $plain_content);
        $found_match = false;
        
        foreach ($words as $word) {
            if (coursesearch_mb_stripos($word, $query) !== false) {
                $found_match = true;
                break;
            }
        }
        
        if (!$found_match) {
            return false;
        }
    }
    
    // For longer content, check if the query appears at least once per 1000 characters
    // This helps filter out content where the query appears just once in a very long text
    if (mb_strlen($plain_content, 'UTF-8') > 500) {
        $occurrences = coursesearch_mb_substr_count($plain_content, $query);
        $expected_min = max(1, floor(mb_strlen($plain_content, 'UTF-8') / 1000));
        if ($occurrences < $expected_min) {
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
    // Process multilanguage tags before extracting snippet
    $content = coursesearch_process_multilang($content);
    
    // Improved HTML handling
    $plain_content = coursesearch_html_to_text($content);
    
    // Find the position of the search term (case-insensitive) with multibyte support for Cyrillic
    $pos = coursesearch_mb_stripos($plain_content, $query);
    
    if ($pos === false) {
        // If the term isn't found, return the beginning of the content
        return mb_substr($plain_content, 0, $length, 'UTF-8') . '...';
    }
    
    // Calculate the start position of the snippet
    $start = max(0, $pos - floor($length / 2));
    
    // If we're not starting from the beginning, add ellipsis
    $prefix = ($start > 0) ? '...' : '';
    
    // Extract the snippet
    $snippet = $prefix . mb_substr($plain_content, $start, $length, 'UTF-8') . '...';
    
    // Highlight the search term in the snippet
    // The snippet is plain text (from coursesearch_html_to_text), so it's safe to add HTML
    // Use preg_quote to safely escape the query for regex to prevent injection
    $pattern = '/(' . preg_quote($query, '/') . ')/iu';
    $replacement = '<span class="highlight">$1</span>';
    $snippet = preg_replace($pattern, $replacement, $snippet);
    
    // Escape the entire snippet to prevent XSS, but preserve the highlighting spans
    // We'll use format_text in view.php which will sanitize while preserving safe HTML
    return $snippet;
}

/**
 * Enhanced HTML to text conversion that better handles complex HTML structures
 *
 * @param string $html The HTML content to convert
 * @return string Plain text version of the content
 */
function coursesearch_html_to_text($html) {
    // Use a simpler, more direct approach for complex HTML
    // The DOM method is the most reliable for extracting text from complex HTML
    
    // First try the DOM method which works best for complex HTML
    if (class_exists('DOMDocument')) {
        try {
            // Create a new DOM document
            $dom = new DOMDocument();
            
            // Suppress errors from malformed HTML
            libxml_use_internal_errors(true);
            
            // Add XML encoding declaration to handle special characters
            if (!preg_match('/<\?xml\s+encoding=/', $html)) {
                $html = '<?xml encoding="UTF-8">' . $html;
            }
            
            // Load the HTML
            $dom->loadHTML($html);
            
            // Get the text content
            $dom_text = $dom->textContent;
            
            // Clear any errors
            libxml_clear_errors();
            
            // If we got text content, use it
            if (!empty($dom_text)) {
                // Normalize whitespace
                $dom_text = preg_replace('/\s+/', ' ', $dom_text);
                $dom_text = trim($dom_text);
                
                return $dom_text;
            }
        } catch (Exception $e) {
            // If there's an error, continue with the fallback method
            error_log("DOM parsing error: " . $e->getMessage());
        }
    }
    
    // Fallback method if DOM parsing fails
    
    // First normalize spaces in HTML attributes and remove excessive whitespace
    $html = preg_replace('/\s+/', ' ', $html);
    
    // Remove script and style elements
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
    
    // Special handling for MS Office-generated HTML
    // Remove MS Office specific comments and conditional tags
    $html = preg_replace('/<!--\[if.*?<!\[endif\]-->/s', '', $html);
    $html = preg_replace('/<!--\[if.*?\]>/s', '', $html);
    $html = preg_replace('/<!\[endif\]-->/s', '', $html);
    
    // Handle MS Office XML data
    $html = preg_replace('/<xml>.*?<\/xml>/s', '', $html);
    $html = preg_replace('/<w:.*?<\/w:.*?>/s', '', $html);
    
    // Remove MS Office specific attributes that might interfere with text extraction
    $html = preg_replace('/\s+mso-[^=]*="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+o:[^=]*="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+v:[^=]*="[^"]*"/i', '', $html);
    
    // Replace common block elements with newlines before and after
    $html = preg_replace('/<\/(div|p|h[1-6]|table|tr|ul|ol|li|blockquote|section|article)>/i', "$0\n", $html);
    $html = preg_replace('/<(div|p|h[1-6]|table|tr|ul|ol|li|blockquote|section|article)[^>]*>/i', "\n$0", $html);
    
    // Replace <br> tags with newlines
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
    
    // Replace table cells with spaces
    $html = preg_replace('/<\/td>\s*<td[^>]*>/i', ' ', $html);
    
    // Convert HTML entities
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Strip all remaining HTML tags
    $text = strip_tags($html);
    
    // Normalize whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    return $text;
}


