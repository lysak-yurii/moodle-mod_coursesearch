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
 * English strings for coursesearch
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Course Search';
$string['modulenameplural'] = 'Course Searches';
$string['modulename_help'] = 'The course search module enables a teacher to add a search bar to a course that allows students to search through course content.';
$string['pluginname'] = 'Course Search';
$string['pluginadministration'] = 'Course Search administration';

// Form strings.
$string['coursesearchsettings'] = 'Course Search settings';
$string['searchscope'] = 'Search scope';
$string['searchscope_help'] = 'Define what content should be included in the search results.';
$string['searchscope_course'] = 'Course content only';
$string['searchscope_activities'] = 'Activities only';
$string['searchscope_resources'] = 'Resources only';
$string['searchscope_forums'] = 'Forums only';
$string['searchscope_all'] = 'All course content';
$string['placeholder'] = 'Placeholder text';
$string['placeholder_help'] = 'The text that appears in the search box before a user enters a query.';
$string['defaultplaceholder'] = 'Search this course...';

// Display options.
$string['displayoptions'] = 'Display options';
$string['embedded'] = 'Embed in course page';
$string['embedded_help'] = 'When enabled, the search bar will be embedded directly in the course page instead of requiring users to click through to a separate page.';
$string['embeddedinfo'] = 'Display the search bar directly on the course page';

// View page strings.
$string['search'] = 'Search';
$string['searchresultsfor'] = 'Search results for "{$a}"';
$string['searchresults'] = 'Search results for "{$a}"';
$string['searchresultscount'] = '{$a->count} results found for "{$a->query}"';
$string['noresults'] = 'No results found for "{$a}"';
$string['inforum'] = 'In forum: {$a}';
$string['matchedin'] = 'Matched in {$a}';
$string['title'] = 'title';
$string['content'] = 'content';
$string['description'] = 'description';
$string['matchdescriptionorcontent'] = 'description or content';
$string['intro'] = 'introduction';
$string['eventcoursesearched'] = 'Course searched';

// Capability strings.
$string['coursesearch:addinstance'] = 'Add a new course search';
$string['coursesearch:view'] = 'View course search';

// Error strings.
$string['missingidandcmid'] = 'Missing course module ID or course search ID';
$string['nocourseinstances'] = 'There are no course search instances in this course';

// Admin settings.
$string['enablehighlight'] = 'Enable scrolling and highlighting';
$string['enablehighlight_desc'] = 'When enabled, clicking on search results will automatically scroll to and highlight the matched text on the course page.';
$string['resultsperpage'] = 'Results per page';
$string['resultsperpage_desc'] = 'The number of search results to display per page.';

// Pagination strings.
$string['pagination'] = 'Search results pagination';
$string['previous'] = 'Previous';
$string['next'] = 'Next';
$string['searchresultsrange'] = 'Showing sections {$a->start}-{$a->end} of {$a->total}';

// Section grouping strings.
$string['sectionmatch'] = 'Section match';
$string['subsectionmatch'] = 'Subsection match';
$string['generalsection'] = 'General';

// Privacy.
$string['privacy:metadata'] = 'The Course Search module does not store any personal user data. It only stores activity instance configuration such as name, description, search scope, and display options.';
