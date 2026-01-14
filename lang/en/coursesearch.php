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

$string['collapsematches'] = 'Collapse matches';
$string['content'] = 'content';
$string['coursesearch:addinstance'] = 'Add a new course search';
$string['coursesearch:view'] = 'View course search';
$string['coursesearchsettings'] = 'Course Search settings';
$string['defaultplaceholder'] = 'Search this course...';
$string['description'] = 'description';
$string['displayoptions'] = 'Display options';
$string['embedded'] = 'Embed in course page';
$string['embedded_help'] = 'When enabled, the search bar will be embedded directly in the course page instead of requiring users to click through to a separate page.';
$string['embeddedinfo'] = 'Display the search bar directly on the course page';
$string['enablefloatingwidget'] = 'Enable floating quick-access widget';
$string['enablefloatingwidget_desc'] = 'When enabled, a floating search widget will appear on course pages, allowing quick access to course search without navigating to the search activity page.';
$string['enablehighlight'] = 'Enable scrolling and highlighting';
$string['enablehighlight_desc'] = 'When enabled, clicking on search results will automatically scroll to and highlight the matched text on the course page.';
$string['eventcoursesearched'] = 'Course searched';
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
$string['expandmatches'] = 'Expand matches';
$string['floatingwidgetverticaloffset'] = 'Floating widget vertical offset';
$string['floatingwidgetverticaloffset_desc'] = 'Vertical position offset in pixels from the bottom of the page. Increase this value to move the widget higher and avoid overlap with other page elements (e.g., Moodle\'s infobutton).';
$string['floatingwidgetverticaloffset_invalid'] = 'Vertical offset must be 0 or greater.';
$string['generalsection'] = 'General';
$string['grouped'] = 'Group results by section';
$string['grouped_help'] = 'When enabled, search results will be organized by course sections. When disabled, results will be displayed as a flat list.';
$string['groupedinfo'] = 'Organize search results by course sections';
$string['inforum'] = 'In forum: {$a}';
$string['intro'] = 'introduction';
$string['matchcount'] = '{$a} matches';
$string['matchdescriptionorcontent'] = 'description or content';
$string['matchedin'] = 'Matched in {$a}';
$string['matchof'] = 'Match {$a->index} of {$a->total}';
$string['maxoccurrences'] = 'Maximum occurrences per content item';
$string['maxoccurrences_desc'] = 'Maximum number of occurrences to find per content item when a search term appears multiple times. Set to 0 to disable the limit and find all occurrences (not recommended for large courses as it may impact performance and create overwhelming result lists).';
$string['maxoccurrences_invalid'] = 'Maximum occurrences must be 0 or greater.';
$string['maxoccurrences_warning'] = 'Warning: Setting this to 0 will find all occurrences, which may cause performance issues and overwhelming result lists in large courses.';
$string['missingidandcmid'] = 'Missing course module ID or course search ID';
$string['modulename'] = 'Course Search';
$string['modulename_help'] = 'The course search module enables a teacher to add a search bar to a course that allows students to search through course content.';
$string['modulenameplural'] = 'Course Searches';
$string['next'] = 'Next';
$string['nocourseinstances'] = 'There are no course search instances in this course';
$string['noresults'] = 'No results found for "{$a}"';
$string['pagination'] = 'Search results pagination';
$string['placeholder'] = 'Placeholder text';
$string['placeholder_help'] = 'The text that appears in the search box before a user enters a query.';
$string['pluginadministration'] = 'Course Search administration';
$string['pluginname'] = 'Course Search';
$string['previous'] = 'Previous';
$string['privacy:metadata'] = 'The Course Search module does not store any personal user data. It only stores activity instance configuration such as name, description, search scope, and display options.';
$string['quicksearch'] = 'Quick search';
$string['resultsperpage'] = 'Results per page';
$string['resultsperpage_desc'] = 'The number of search results to display per page.';
$string['search'] = 'Search';
$string['searchresults'] = 'Search results for "{$a}"';
$string['searchresultscount'] = '{$a->count} results found for "{$a->query}"';
$string['searchresultsfor'] = 'Search results for "{$a}"';
$string['searchresultsrange'] = 'Showing sections {$a->start}-{$a->end} of {$a->total}';
$string['searchresultsrange_ungrouped'] = 'Showing results {$a->start}-{$a->end} of {$a->total}';
$string['searchscope'] = 'Search scope';
$string['searchscope_activities'] = 'Activities only';
$string['searchscope_all'] = 'All course content';
$string['searchscope_course'] = 'Course content only';
$string['searchscope_forums'] = 'Forums only';
$string['searchscope_help'] = 'Define what content should be included in the search results.';
$string['searchscope_resources'] = 'Resources only';
$string['sectionmatch'] = 'Section match';
$string['subsectionmatch'] = 'Subsection match';
$string['title'] = 'title';
