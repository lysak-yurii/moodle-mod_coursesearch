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
        return [];
    }

    $results = [];

    // Search course sections - only if not specifically looking for other content types
    if ($filter == 'all' || $filter == 'sections') {
        $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section', 'id, section, name, summary');
        foreach ($sections as $section) {
            // Search in section name - use case-insensitive comparison
            if (!empty($section->name) && (
                stripos($section->name, $query) !== false ||
                stripos(get_string('section') . ' ' . $section->section, $query) !== false
            )) {
                // Create a direct URL to this section with explicit section parameter
                $sectionurl = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $section->section]);

                $results[] = [
                    'type' => 'section_name',
                    'name' => get_string('section') . ': ' . $section->name,
                    'url' => $sectionurl,
                    'modname' => 'section',
                    'icon' => new moodle_url('/pix/i/section.png'),
                    'match' => 'title',
                    'snippet' => coursesearch_extract_snippet($section->name, $query),
                    'section_number' => $section->section, // Store the section number for reference
                ];
            }

            // Search in section summary - use case-insensitive comparison with relevance check
            if (!empty($section->summary) && coursesearch_is_relevant($section->summary, $query)) {
                $sectionurl = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $section->section]);
                $results[] = [
                    'type' => 'section_summary',
                    'name' => get_string('section') . ': ' . ($section->name ? $section->name : get_string('section') . ' ' . $section->section),
                    'url' => $sectionurl,
                    'modname' => 'section',
                    'icon' => new moodle_url('/pix/i/section.png'),
                    'match' => 'description or content',
                    'snippet' => coursesearch_extract_snippet($section->summary, $query),
                ];
            }

            // If section has no name but number matches the query (e.g., searching for "1" finds "Section 1")
            if (empty($section->name) && (stripos(get_string('section') . ' ' . $section->section, $query) !== false)) {
                $sectionurl = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $section->section]);
                $results[] = [
                    'type' => 'section_number',
                    'name' => get_string('section') . ' ' . $section->section,
                    'url' => $sectionurl,
                    'modname' => 'section',
                    'icon' => new moodle_url('/pix/i/section.png'),
                    'match' => 'title',
                    'snippet' => get_string('section') . ' ' . $section->section,
                ];
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
                $urlparams = ['id' => $course->id];
                if ($sectionnum !== null) {
                    $urlparams['section'] = $sectionnum;
                }
                $moduleurl = new moodle_url('/course/view.php', $urlparams);
                $moduleurl->set_anchor('module-' . $mod->id);
            } else {
                // For other module types, use the module's URL
                $moduleurl = $mod->url;
            }

            $results[] = [
                'type' => 'module',
                'name' => $mod->name,
                'url' => $moduleurl,  // URL with proper anchor for inline content
                'modname' => $mod->modname,
                'icon' => $mod->get_icon_url(),
                'match' => 'title',
                'snippet' => $mod->name,
                'cmid' => $mod->id,  // Store the course module ID for reference
            ];
            continue; // Skip further checks for this module if we already found a match.
        }

        // Search in the module description/intro if available (only if filter is 'all' or 'description')
        if ($filter == 'all' || $filter == 'description') {
            // Get the module description from the appropriate table based on module type
            $description = '';
            $modulerecord = null;

            // Try to get the module record with intro/description
            if (!empty($mod->modname)) {
                // Validate module name to prevent SQL injection - must be alphanumeric with underscores only
                $modname = clean_param($mod->modname, PARAM_PLUGIN);
                if (empty($modname) || $modname !== $mod->modname) {
                    // Invalid module name, skip this module
                    continue;
                }
                $modulerecord = $DB->get_record($modname, ['id' => $mod->instance], '*', IGNORE_MISSING);
            }

            // Most modules use 'intro' field for description
            if ($modulerecord && isset($modulerecord->intro)) {
                $description = $modulerecord->intro;
            }
            // Some modules might use 'description' or other fields
            else if ($modulerecord && isset($modulerecord->description)) {
                $description = $modulerecord->description;
            }
            // For custom modules with different field names
            else if ($modulerecord && isset($modulerecord->content)) {
                $description = $modulerecord->content;
            }
            // For modules with summary field
            else if ($modulerecord && isset($modulerecord->summary)) {
                $description = $modulerecord->summary;
            }
        }

        // Search in the description if we found one
        if (!empty($description)) {
            if (coursesearch_is_relevant($description, $query)) {
                // For labels and similar inline content, we need to create a URL with an anchor
                if ($mod->modname === 'label' || $mod->modname === 'html') {
                    // Include section parameter to ensure the correct section is displayed
                    $sectionnum = isset($mod->sectionnum) ? $mod->sectionnum : (isset($mod->section) ? $mod->section : null);
                    $urlparams = ['id' => $course->id];
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
                $results[] = [
                    'type' => 'module_description',
                    'name' => $mod->name,
                    'url' => $moduleurl,
                    'modname' => $mod->modname,
                    'icon' => $mod->get_icon_url(),
                    'match' => 'description or content',
                    'snippet' => $snippet,
                    'cmid' => $mod->id,
                ];
                continue; // Skip further checks for this module if we already found a match.
            }
        }

        // Search in module content based on the module type (only if filter is 'all' or 'content')
        // For forums, we want to search content regardless of the filter when 'forums' filter is selected
        if ($filter == 'all' || $filter == 'content' || ($filter == 'forums' && $mod->modname == 'forum')) {
            switch ($mod->modname) {
            case 'page':
                // Search in page content
                $page = $DB->get_record('page', ['id' => $mod->instance], 'id, name, content');
                if ($page && coursesearch_is_relevant($page->content, $query)) {
                    $snippet = coursesearch_extract_snippet($page->content, $query);
                    $results[] = [
                        'type' => 'page_content',
                        'name' => $mod->name,
                        'url' => $mod->url,
                        'modname' => $mod->modname,
                        'icon' => $mod->get_icon_url(),
                        'match' => 'content',
                        'snippet' => $snippet,
                    ];
                }
                break;

            case 'book':
                // Search in book content and titles
                $bookchapters = $DB->get_records('book_chapters', ['bookid' => $mod->instance]);
                foreach ($bookchapters as $chapter) {
                    // First check if the chapter title matches the query
                    if (($filter == 'all' || $filter == 'title') && coursesearch_mb_stripos($chapter->title, $query) !== false) {
                        $chapterurl = new moodle_url('/mod/book/view.php', ['id' => $mod->id, 'chapterid' => $chapter->id]);
                        $results[] = [
                            'type' => 'book_title',
                            'name' => $mod->name . ': ' . $chapter->title,
                            'url' => $chapterurl,
                            'modname' => $mod->modname,
                            'icon' => $mod->get_icon_url(),
                            'match' => 'title',
                        'snippet' => $chapter->title,
                        'cmid' => $mod->id,
                        ];
                        // Skip content check for this chapter since we already found a match
                        continue;
                    }

                    // Then check if the chapter content matches the query
                    if (($filter == 'all' || $filter == 'content') && coursesearch_is_relevant($chapter->content, $query)) {
                        $snippet = coursesearch_extract_snippet($chapter->content, $query);
                        $chapterurl = new moodle_url('/mod/book/view.php', ['id' => $mod->id, 'chapterid' => $chapter->id]);
                        $results[] = [
                            'type' => 'book_content',
                            'name' => $mod->name . ': ' . $chapter->title,
                            'url' => $chapterurl,
                            'modname' => $mod->modname,
                            'icon' => $mod->get_icon_url(),
                            'match' => 'content',
                            'snippet' => $snippet,
                            'cmid' => $mod->id
                        ];
                    }
                }
                break;

            case 'label':
                // Search in label content
                $label = $DB->get_record('label', ['id' => $mod->instance], 'id, name, intro');
                if ($label && coursesearch_is_relevant($label->intro, $query)) {
                    $snippet = coursesearch_extract_snippet($label->intro, $query);
                    // For labels, create a URL with anchor directly using the cmid we already have
                    // Include section parameter to ensure the correct section is displayed
                    // Also include search query as parameter so JavaScript can scroll to the matched text
                    $sectionnum = isset($mod->sectionnum) ? $mod->sectionnum : (isset($mod->section) ? $mod->section : null);
                    $urlparams = ['id' => $course->id];
                    if ($sectionnum !== null) {
                        $urlparams['section'] = $sectionnum;
                    }
                    // Add search query parameter for JavaScript scrolling
                    if (!empty($query)) {
                        $urlparams['highlight'] = urlencode($query);
                    }
                    $moduleurl = new moodle_url('/course/view.php', $urlparams);
                    $moduleurl->set_anchor('module-' . $mod->id);
                    $results[] = [
                        'type' => 'label_content',
                        'name' => $mod->name,
                        'url' => $moduleurl,
                        'modname' => $mod->modname,
                        'icon' => $mod->get_icon_url(),
                        'match' => 'content',
                        'snippet' => $snippet,
                        'cmid' => $mod->id  // Store the course module ID for reference
                    ];
                }
                break;

            case 'lesson':
                // Search in lesson pages
                $lessonpages = $DB->get_records('lesson_pages', ['lessonid' => $mod->instance]);
                foreach ($lessonpages as $page) {
                    if (coursesearch_is_relevant($page->contents, $query)) {
                        $snippet = coursesearch_extract_snippet($page->contents, $query);
                        $pageurl = new moodle_url('/mod/lesson/view.php', ['id' => $mod->id, 'pageid' => $page->id]);
                        $results[] = [
                            'type' => 'lesson_content',
                            'name' => $mod->name . ': ' . $page->title,
                            'url' => $pageurl,
                            'modname' => $mod->modname,
                            'icon' => $mod->get_icon_url(),
                            'match' => 'content',
                            'snippet' => $snippet
                        ];
                    }
                }
                break;

            case 'forum':
                // Determine what to search in forums based on the filter
                $search_forum_titles = ($filter == 'all' || $filter == 'title' || $filter == 'forums');
                $search_forum_content = ($filter == 'all' || $filter == 'content' || $filter == 'forums');
                $search_forum_subjects = ($filter == 'all' || $filter == 'title' || $filter == 'forums');

                // Search in forum discussions and posts
                $discussions = $DB->get_records('forum_discussions', ['forum' => $mod->instance]);

                // Keep track of processed posts to avoid duplicates
                $processedPosts = [];

                foreach ($discussions as $discussion) {
                    // First check if the discussion subject/topic name matches the query
                    if ($search_forum_titles && coursesearch_mb_stripos($discussion->name, $query) !== false) {
                        // Get the first post of the discussion to create a proper URL
                        $firstpost = $DB->get_record('forum_posts', ['discussion' => $discussion->id, 'parent' => 0]);
                        if ($firstpost) {
                            $discussionurl = new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->id]);

                            // Get forum name
                            $forum = $DB->get_record('forum', ['id' => $mod->instance], 'name');
                            $forumname = $forum ? $forum->name : $mod->name;

                            $results[] = [
                                'type' => 'forum_discussion',
                                'name' => $discussion->name,
                                'url' => $discussionurl,
                                'modname' => $mod->modname,
                                'icon' => $mod->get_icon_url(),
                                'match' => 'title',
                                'snippet' => $discussion->name,
                                'forum_name' => $forumname,
                            ];

                            // Mark the first post as processed to avoid duplicate results
                            $processedPosts[$firstpost->id] = true;
                        }
                    }

                    // Only continue with post checks if we're searching forum content
                    if (!$search_forum_content && !$search_forum_subjects) {
                        continue;
                    }

                    // Check posts in this discussion
                    $posts = $DB->get_records('forum_posts', ['discussion' => $discussion->id]);
                    foreach ($posts as $post) {
                        // Skip if we've already processed this post
                        if (isset($processedPosts[$post->id])) {
                            continue;
                        }

                        // Mark this post as processed
                        $processedPosts[$post->id] = true;

                        // Check post subject (for replies)
                        if ($search_forum_subjects && coursesearch_mb_stripos($post->subject, $query) !== false) {
                            $posturl = new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->id, 'p' => $post->id]);
                            $posturl->set_anchor('p' . $post->id);

                            // Get forum name
                            $forum = $DB->get_record('forum', ['id' => $mod->instance], 'name');
                            $forumname = $forum ? $forum->name : $mod->name;

                            $results[] = [
                                'type' => 'forum_post',
                                'name' => $post->subject,
                                'url' => $posturl,
                                'modname' => $mod->modname,
                                'icon' => $mod->get_icon_url(),
                                'match' => 'title',
                                'snippet' => $post->subject,
                                'forum_name' => $forumname,
                            ];

                            // Skip content check for this post since we already found a match
                            continue;
                        }

                        // Check post content
                        if ($search_forum_content && coursesearch_is_relevant($post->message, $query)) {
                            $snippet = coursesearch_extract_snippet($post->message, $query);
                            $posturl = new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->id, 'p' => $post->id]);
                            $posturl->set_anchor('p' . $post->id);

                            // Get forum name
                            $forum = $DB->get_record('forum', ['id' => $mod->instance], 'name');
                            $forumname = $forum ? $forum->name : $mod->name;

                            $results[] = [
                                'type' => 'forum_post',
                                'name' => $post->subject,
                                'url' => $posturl,
                                'modname' => $mod->modname,
                                'icon' => $mod->get_icon_url(),
                                'match' => 'content',
                                'snippet' => $snippet,
                                'forum_name' => $forumname,
                            ];
                        }
                    }
                }
                break;

            case 'folder':
                // Search in folder files
                $fs = get_file_storage();
                $context = context_module::instance($mod->id);
                $files = $fs->get_area_files($context->id, 'mod_folder', 'content', 0, 'filepath, filename', false);

                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    // Search in filename
                    if (coursesearch_mb_stripos($filename, $query) !== false) {
                        // Create URL to the folder with the file
                        $fileurl = moodle_url::make_pluginfile_url(
                            $context->id,
                            'mod_folder',
                            'content',
                            0,
                            $file->get_filepath(),
                            $filename
                        ];

                        $results[] = [
                            'type' => 'folder_file',
                            'name' => $mod->name . ': ' . $filename,
                            'url' => $fileurl,
                            'modname' => 'folder',
                            'icon' => $mod->get_icon_url(),
                            'match' => 'title',
                            'snippet' => $filename,
                            'cmid' => $mod->id
                        ];
                    }
                }
                break;

            case 'wiki':
                // Search in wiki pages
                $wikipages = $DB->get_records('wiki_pages', ['subwikiid' => $mod->instance], 'id, title, cachedcontent');
                // Note: wiki_pages uses subwikiid, but we need to get the subwikiid from the wiki instance
                // First, get the wiki record to find subwikis
                $wiki = $DB->get_record('wiki', ['id' => $mod->instance], 'id, name, firstpagetitle');
                if ($wiki) {
                    // Get all subwikis for this wiki
                    $subwikis = $DB->get_records('wiki_subwikis', ['wikiid' => $wiki->id], 'id');
                    foreach ($subwikis as $subwiki) {
                        // Get all pages in this subwiki
                        $wikipages = $DB->get_records('wiki_pages', ['subwikiid' => $subwiki->id], 'id, title, cachedcontent');
                        foreach ($wikipages as $wikipage) {
                            // First check if the page title matches
                            if (($filter == 'all' || $filter == 'title') && coursesearch_mb_stripos($wikipage->title, $query) !== false) {
                                $pageurl = new moodle_url('/mod/wiki/view.php', ['id' => $mod->id, 'pageid' => $wikipage->id]);
                                $results[] = [
                                    'type' => 'wiki_page_title',
                                    'name' => $mod->name . ': ' . $wikipage->title,
                                    'url' => $pageurl,
                                    'modname' => $mod->modname,
                                    'icon' => $mod->get_icon_url(),
                                    'match' => 'title',
                                'snippet' => $wikipage->title,
                                'cmid' => $mod->id,
                                ];
                                // Skip content check for this page since we already found a match
                                continue;
                            }

                            // Then check if the page content matches
                            if (($filter == 'all' || $filter == 'content') && !empty($wikipage->cachedcontent) && coursesearch_is_relevant($wikipage->cachedcontent, $query)) {
                                $snippet = coursesearch_extract_snippet($wikipage->cachedcontent, $query);
                                $pageurl = new moodle_url('/mod/wiki/view.php', ['id' => $mod->id, 'pageid' => $wikipage->id]);
                                $results[] = [
                                    'type' => 'wiki_page_content',
                                    'name' => $mod->name . ': ' . $wikipage->title,
                                    'url' => $pageurl,
                                    'modname' => $mod->modname,
                                    'icon' => $mod->get_icon_url(),
                                    'match' => 'content',
                                    'snippet' => $snippet,
                                    'cmid' => $mod->id
                                ];
                            }
                        }
                    }
                }
                break;

            case 'glossary':
                // Search in glossary entries
                $glossary = $DB->get_record('glossary', ['id' => $mod->instance], 'id, name');
                if ($glossary) {
                    // Get all entries in this glossary
                    $entries = $DB->get_records('glossary_entries', ['glossaryid' => $glossary->id], '', 'id, concept, definition');
                    foreach ($entries as $entry) {
                        // First check if the entry concept (term) matches
                        if (($filter == 'all' || $filter == 'title') && coursesearch_mb_stripos($entry->concept, $query) !== false) {
                            $entryurl = new moodle_url('/mod/glossary/showentry.php', ['eid' => $entry->id, 'displayformat' => 'dictionary']);
                            $results[] = [
                                'type' => 'glossary_entry_title',
                                'name' => $mod->name . ': ' . $entry->concept,
                                'url' => $entryurl,
                                'modname' => $mod->modname,
                                'icon' => $mod->get_icon_url(),
                                'match' => 'title',
                                'snippet' => $entry->concept,
                                'cmid' => $mod->id,
                            ];
                            // Skip definition check for this entry since we already found a match
                            continue;
                        }

                        // Then check if the entry definition matches
                        if (($filter == 'all' || $filter == 'content') && !empty($entry->definition) && coursesearch_is_relevant($entry->definition, $query)) {
                            $snippet = coursesearch_extract_snippet($entry->definition, $query);
                            $entryurl = new moodle_url('/mod/glossary/showentry.php', ['eid' => $entry->id, 'displayformat' => 'dictionary']);
                            $results[] = [
                                'type' => 'glossary_entry_content',
                                'name' => $mod->name . ': ' . $entry->concept,
                                'url' => $entryurl,
                                'modname' => $mod->modname,
                                'icon' => $mod->get_icon_url(),
                                'match' => 'content',
                                'snippet' => $snippet,
                                'cmid' => $mod->id,
                            ];
                        }
                    }
                }
                break;

            case 'data':
                // Search in database entries
                $database = $DB->get_record('data', ['id' => $mod->instance], 'id, name');
                if ($database) {
                    // Get all fields for this database
                    $fields = $DB->get_records('data_fields', ['dataid' => $database->id], '', 'id, name, type');

                    // Get all records in this database
                    $records = $DB->get_records('data_records', ['dataid' => $database->id], '', 'id');

                    foreach ($records as $record) {
                        // Get all content for this record
                        $contents = $DB->get_records('data_content', ['recordid' => $record->id], '', 'id, fieldid, content, content1, content2, content3, content4');

                        $record_matched = false;
                        $matched_content = '';
                        $matched_field_name = '';

                        foreach ($contents as $content) {
                            // Skip if already matched this record
                            if ($record_matched) {
                                break;
                            }

                            // Get field info
                            $field = isset($fields[$content->fieldid]) ? $fields[$content->fieldid] : null;
                            $field_name = $field ? $field->name : '';

                            // Search in main content
                            if (!empty($content->content) && coursesearch_is_relevant($content->content, $query)) {
                                $record_matched = true;
                                $matched_content = $content->content;
                                $matched_field_name = $field_name;
                            }
                            // Also check content1-4 fields (used by some field types)
                            else if (!empty($content->content1) && coursesearch_is_relevant($content->content1, $query)) {
                                $record_matched = true;
                                $matched_content = $content->content1;
                                $matched_field_name = $field_name;
                            }
                        }

                        if ($record_matched) {
                            $snippet = coursesearch_extract_snippet($matched_content, $query);
                            $recordurl = new moodle_url('/mod/data/view.php', ['d' => $database->id, 'rid' => $record->id]);

                            // Try to get a meaningful name for the record
                            $record_name = $mod->name;
                            if (!empty($matched_field_name)) {
                                $record_name .= ' (' . $matched_field_name . ')';
                            }

                            $results[] = [
                                'type' => 'data_entry',
                                'name' => $record_name,
                                'url' => $recordurl,
                                'modname' => $mod->modname,
                                'icon' => $mod->get_icon_url(),
                                'match' => 'content',
                                'snippet' => $snippet,
                                'cmid' => $mod->id,
                            ];
                        }
                    }
                }
                break;

            case 'hvp':
                // Search in H5P interactive content (mod_hvp plugin)
                $hvp = $DB->get_record('hvp', ['id' => $mod->instance], 'id, name, json_content');
                if ($hvp && !empty($hvp->json_content)) {
                    // Extract all text content from the H5P JSON
                    $h5p_text = coursesearch_extract_h5p_text($hvp->json_content);

                    if (!empty($h5p_text) && coursesearch_is_relevant($h5p_text, $query)) {
                        $snippet = coursesearch_extract_snippet($h5p_text, $query);
                        $hvpurl = new moodle_url('/mod/hvp/view.php', ['id' => $mod->id]);

                        $results[] = [
                            'type' => 'hvp_content',
                            'name' => $mod->name,
                            'url' => $hvpurl,
                            'modname' => $mod->modname,
                            'icon' => $mod->get_icon_url(),
                            'match' => 'content',
                            'snippet' => $snippet,
                            'cmid' => $mod->id
                        ];
                    }
                }
                break;

            case 'h5pactivity':
                // Search in H5P activity (Moodle core h5pactivity)
                // The content is stored in mdl_h5p table, linked via file system
                $context = context_module::instance($mod->id);
                $h5p_content = $DB->get_record_sql(
                    "SELECT h.id, h.jsoncontent
                     FROM {h5p} h
                     JOIN {files} f ON f.contenthash = h.contenthash
                     WHERE f.contextid = ? AND f.component = 'mod_h5pactivity' AND f.filearea = 'package'
                     LIMIT 1",
                    [$context->id)
                ];

                if ($h5p_content && !empty($h5p_content->jsoncontent)) {
                    $h5p_text = coursesearch_extract_h5p_text($h5p_content->jsoncontent);

                    if (!empty($h5p_text) && coursesearch_is_relevant($h5p_text, $query)) {
                        $snippet = coursesearch_extract_snippet($h5p_text, $query);
                        $h5purl = new moodle_url('/mod/h5pactivity/view.php', ['id' => $mod->id]);

                        $results[] = [
                            'type' => 'h5pactivity_content',
                            'name' => $mod->name,
                            'url' => $h5purl,
                            'modname' => $mod->modname,
                            'icon' => $mod->get_icon_url(),
                            'match' => 'content',
                            'snippet' => $snippet,
                            'cmid' => $mod->id
                        ];
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
        $filtered_results = [];

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
                $activity_mods = ['assign', 'quiz', 'choice', 'feedback', 'lesson', 'workshop', 'data', 'glossary', 'wiki', 'forum');
                if (in_array($result['modname'], $activity_mods)) {
                    $filtered_results[] = $result;
                }
            } else if ($filter == 'resources') {
                $resource_mods = ['book', 'file', 'folder', 'imscp', 'label', 'page', 'resource', 'url');
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
            $result['url'] = new moodle_url('/course/view.php', ['id' => $course->id]);
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
    $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section', 'id, section, name, summary');

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
                $results[] = [
                    'type' => 'course_index_title',
                    'name' => $cm->name,
                    'url' => $cm->url,
                    'modname' => $cm->modname,
                    'icon' => $cm->get_icon_url(),
                    'match' => 'title in course index',
                    'snippet' => $cm->name
                ];
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
                $sectionurl = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $section->section]);
                $results[] = [
                    'type' => 'course_index_section',
                    'name' => get_string('section') . ': ' . $section->name,
                    'url' => $sectionurl,
                    'modname' => 'section',
                    'icon' => new moodle_url('/pix/i/section.png'),
                    'match' => 'title in course index',
                    'snippet' => $section->name,
                ];
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
        $url = new moodle_url('/course/view.php', ['id' => $courseid));

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

/**
 * Extract searchable text content from H5P JSON
 *
 * H5P stores content in JSON format with text in various fields like 'text', 'label',
 * 'title', 'description', 'question', 'answer', etc. This function recursively
 * extracts all text content from the JSON structure.
 *
 * @param string $json_content The JSON content from H5P
 * @return string Combined plain text from all text fields
 */
function coursesearch_extract_h5p_text($json_content) {
    if (empty($json_content)) {
        return '';
    }

    // Decode JSON
    $data = json_decode($json_content, true);
    if ($data === null) {
        return '';
    }

    // Text fields commonly used in H5P content types
    $text_fields = [
        'text', 'label', 'title', 'description', 'question', 'answer',
        'tip', 'feedback', 'correct', 'incorrect', 'header', 'summary',
        'introduction', 'explanation', 'hint', 'placeholder', 'alt',
        'caption', 'credit', 'copyright', 'definition', 'term',
        'statement', 'quote', 'author', 'source', 'taskDescription',
        'endScreenTitle', 'endScreenSubtitle', 'retryButtonLabel',
        'showSolutionsButtonLabel', 'checkAnswerButtonLabel',
        'submitButtonLabel', 'continueButtonLabel', 'proceedButtonLabel',
        // Accordion specific
        'panels', 'content',
        // Course Presentation specific
        'slides', 'elements',
        // Interactive Video specific
        'interactiveVideo', 'video', 'interactions',
        // Question Set specific
        'questions', 'introPage', 'resultPage'
    );

    $extracted_text = [];
    coursesearch_extract_h5p_text_recursive($data, $text_fields, $extracted_text);

    // Join all extracted text with spaces
    $combined_text = implode(' ', $extracted_text);

    // Clean up the text
    $combined_text = preg_replace('/\s+/', ' ', $combined_text);
    $combined_text = trim($combined_text);

    return $combined_text;
}

/**
 * Recursively extract text from H5P data structure
 *
 * @param mixed $data The data to process (array or value)
 * @param array $text_fields List of field names that contain text
 * @param array &$extracted_text Reference to array collecting extracted text
 */
function coursesearch_extract_h5p_text_recursive($data, $text_fields, &$extracted_text) {
    if (is_string($data)) {
        // Check if string contains HTML
        if (preg_match('/<[^>]+>/', $data)) {
            // Extract text from HTML
            $text = coursesearch_html_to_text($data);
            if (!empty($text) && mb_strlen($text, 'UTF-8') > 2) {
                $extracted_text[] = $text;
            }
        } else if (mb_strlen($data, 'UTF-8') > 2) {
            // Plain text - add if it's meaningful (more than 2 chars)
            $extracted_text[] = $data;
        }
    } else if (is_array($data)) {
        foreach ($data as $key => $value) {
            // If key is a known text field or is numeric (array index), process the value
            if (is_numeric($key) || in_array($key, $text_fields)) {
                coursesearch_extract_h5p_text_recursive($value, $text_fields, $extracted_text);
            } else if (is_array($value)) {
                // For other keys that contain arrays, still recurse
                coursesearch_extract_h5p_text_recursive($value, $text_fields, $extracted_text);
            } else if (is_string($value) && in_array($key, $text_fields)) {
                // Direct text field
                coursesearch_extract_h5p_text_recursive($value, $text_fields, $extracted_text);
            }
        }
    }
}

