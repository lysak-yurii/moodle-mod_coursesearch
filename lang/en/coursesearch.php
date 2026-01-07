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
$string['modulename_help'] = 'The course search module enables a teacher to add a search bar to a course that allows students to search through course content.';
$string['modulenameplural'] = 'Course Searches';
$string['pluginadministration'] = 'Course Search administration';
$string['pluginname'] = 'Course Search';

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
$string['grouped'] = 'Group results by section';
$string['grouped_help'] = 'When enabled, search results will be organized by course sections. When disabled, results will be displayed as a flat list.';
$string['groupedinfo'] = 'Organize search results by course sections';

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
$string['excludedplaceholders'] = 'Excluded placeholder patterns';
$string['excludedplaceholders_desc'] = 'Regular expression patterns (one per line) for internal placeholders that should be excluded from search. These are internal markers not visible to users and should not be searchable.

<strong>Regex Symbol Guide:</strong>
<ul>
<li><code>@@</code> - Matches literal double at signs</li>
<li><code>[A-Z_]</code> - Matches any uppercase letter or underscore</li>
<li><code>+</code> - Matches one or more of the preceding character/group</li>
<li><code>[^\s]</code> - Matches any character except whitespace</li>
<li><code>*</code> - Matches zero or more of the preceding character/group</li>
<li><code>\s</code> - Matches any whitespace character (space, tab, newline)</li>
<li><code>^</code> - Inside brackets [^...], means "not" (negation)</li>
</ul>

<strong>Examples:</strong>
<ul>
<li><code>@@[A-Z_]+@@[^\s]*</code> - Excludes any @@PLACEHOLDER@@ pattern (general pattern, recommended)</li>
<li><code>\{\{[^}]+\}\}</code> - Excludes template variables like {{variable_name}} (braces must be escaped with backslash)</li>
</ul>

<strong>Note:</strong> Patterns are case-insensitive. Invalid patterns will be skipped with a debug message. If you remove all patterns, no placeholder filtering will be applied.';
$string['pagination'] = 'Search results pagination';
$string['resultsperpage'] = 'Results per page';
$string['resultsperpage_desc'] = 'The number of search results to display per page.';
$string['maxoccurrences'] = 'Maximum occurrences per content item';
$string['maxoccurrences_desc'] = 'Maximum number of occurrences to find per content item when a search term appears multiple times. Set to 0 to disable the limit and find all occurrences (not recommended for large courses as it may impact performance and create overwhelming result lists). Default: 5.';
$string['maxoccurrences_invalid'] = 'Maximum occurrences must be 0 or greater.';
$string['maxoccurrences_warning'] = 'Warning: Setting this to 0 will find all occurrences, which may cause performance issues and overwhelming result lists in large courses.';

// Pagination strings.
$string['previous'] = 'Previous';
$string['next'] = 'Next';
$string['searchresultsrange'] = 'Showing sections {$a->start}-{$a->end} of {$a->total}';
$string['searchresultsrange_ungrouped'] = 'Showing results {$a->start}-{$a->end} of {$a->total}';

// Section grouping strings.
$string['sectionmatch'] = 'Section match';
$string['subsectionmatch'] = 'Subsection match';
$string['generalsection'] = 'General';

// Activity grouping strings.
$string['matchcount'] = '{$a} matches';
$string['expandmatches'] = 'Expand matches';
$string['collapsematches'] = 'Collapse matches';
$string['matchof'] = 'Match {$a->index} of {$a->total}';

// Privacy.
$string['privacy:metadata'] = 'The Course Search module does not store any personal user data. It only stores activity instance configuration such as name, description, search scope, and display options.';
