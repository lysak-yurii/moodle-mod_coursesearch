# Changelog

## Changes in version 1.4.12 (Build: 2026070710)

- **Fixed**: Highlighted text is now always readable on dark backgrounds - the highlight forces a dark text color together with the yellow background instead of inheriting the theme's (possibly light) text color

## Changes in version 1.4.11 (Build: 2026070708)

- **Fixed**: Highlighted text on target pages now always inherits the surrounding typography (font size, family, weight, line height); themes with generic span rules could previously shrink or restyle the highlighted text
- **Fixed**: Grouped results are now titled with the activity name (e.g. the folder name) instead of the first match's display name (e.g. "Folder: first-file.pdf"); applies to folder, book, lesson, wiki, glossary and database groups
- **Fixed**: Clicking a grouped result header now opens the activity itself (e.g. the folder page) instead of following the first match's link (e.g. downloading the first file)
- **Fixed**: Result snippets now highlight only the specific occurrence the result row links to, instead of every query match in the snippet
- **Fixed**: Result links now highlight the correct occurrence on the target page. Each link carries the surrounding text context of its occurrence (`cs_prefix`/`cs_suffix`, following the W3C Text Fragments disambiguation model), so the highlight lands on the exact match the snippet came from
- **Fixed**: Highlighting no longer matches page chrome - breadcrumbs, page header, navigation, drawers, course index and footer are excluded from the client-side search, and the search is scoped to the main content region instead of the whole page
- **Fixed**: Occurrences spanning multiple inline elements (e.g. text split by `<strong>` or links) are now found and highlighted across element boundaries
- **Fixed**: Inconsistent occurrence numbering (0-based vs 1-based) between search functions and result rendering; `cs_occurrence` is now 0-based everywhere and out-of-range indices fall back to the first content occurrence instead of the last one
- **Fixed**: Search results now respect per-user visibility of activity content (hidden book chapters, unapproved glossary/database entries, other users' or groups' wiki subwikis are no longer exposed)
- **Fixed**: Highlight query with special characters (e.g. a literal `%`) is no longer double-decoded on target pages
- **Fixed**: Section names now follow the course format (e.g. weekly dates) instead of showing a generic "Section 0"
- **Fixed**: Prevented an error when cloning result URLs for modules without a view page

## Changes in version 1.4.10 (Build: 2026051201)

- **Updated**: Removed legacy logdata from `course_module_viewed` event

## Changes in version 1.4.9 (Build: 2026051200)

- **Fixed**: Floating quick-access widget close label string

## Changes in version 1.4.8 (Build: 2026012200)

- **Fixed**: Floating quick-access widget now shifts to the left when Moodle's block drawer is opened, preventing it from being hidden behind the drawer

## Changes in version 1.4.7 (Build: 2026011901)

- **New Feature**: Added module type filter with chip-based UI - users can now filter search results by specific activity or resource types (e.g., only Assignments, Quizzes, Pages, etc.)
- **Updated**: Removed old "Search scope" filter (All/Forums only) in favor of more flexible module type filtering
- **Updated**: Removed unused language strings (searchscope_* variants)

## Changes in version 1.4.6 (Build: 2026011900)

- **Updated**: Added Moodle.org info link to the activity description help

## Changes in version 1.4.6 (Build: 2026011603)

- **Updated**: Theme-aware styling for activity links and badges
- **Updated**: Section/subsection match highlights now follow theme color shades
- **Updated**: Added hover tooltips with result type labels

## Changes in version 1.4.5 (Build: 2026011602)

- **Fixed**: Removed highlighting from title-only matches to avoid highlighting when only content is visible

## Changes in version 1.4.4 (Build: 2026011601)

- **Fixed**: Correct multilang fallback order (`userlang -> other -> en -> first available`) to prevent empty titles
- **Fixed**: Apply multilang processing to grouped activity accordion titles

## Changes in version 1.4.3 (Build: 2026011600)

- **Fixed**: Removed deprecated dynamic properties on `cached_cm_info` by using `customdata` flags (PHP 8.2+ compatibility)

## Changes in version 1.4.3 (Build: 2026011401)

- **Fixed**: Critical bug causing "The theme has already been set up for this page" error for non-admin users (teachers, students) when viewing course pages or using search functionality
- **Fixed**: Removed unnecessary `get_fast_modinfo()` call in floating widget hook that triggered theme re-initialization during footer generation
- **Fixed**: Replaced `$PAGE->get_renderer()` call in `cm_info_dynamic` with direct HTML generation, as the renderer requires page context which is not available during course module info building

## Changes in version 1.4.2 (Build: 2026011300)

- **Fixed**: Changed URL parameters from 'highlight' to 'cs_highlight' (and 'highlight_all' to 'cs_highlight_all', 'occurrence' to 'cs_occurrence') to avoid conflict with Moodle core's built-in highlighting mechanism. This fixes the issue where the first occurrence was incorrectly highlighted when opening specific occurrence results.

## Changes in version 1.4.1

- **New Feature**: Multi-occurrence highlighting - opening grouped activity result (e.g., "Activity Name - 3 matches") now highlights ALL occurrences of the search term, and highlights persist until the user clicks anywhere on the page
- **New Feature**: Specific occurrence highlighting - opening individual match items from expanded accordions now highlights the exact occurrence that was clicked

## Changes in version 1.4.0

- **New Feature**: Added floating quick-access search widget that appears on all course pages (course view, module pages, etc.) providing instant access to course search without navigating to the search activity page
- **New Feature**: Added admin settings for enabling/disabling the floating widget and configuring its vertical offset position

## Changes in version 1.3.1

- **Fixed**: Highlight parameter URL encoding in grouped results
- **Fixed**: Proper disabling of all highlight features when its off in admin setting
- **Fixed**: Multilang processing for section names and language selection

## Changes in version 1.3.0

- **New Feature**: Added collapsible grouping of search results by activity - when multiple matches are found in the same activity (e.g., multiple forum posts, book chapters, or page content), they are now grouped together with a collapsible interface showing the match count
- **New Feature**: Multiple occurrences support - now finds multiple occurrences of search terms in content (configurable limit per content item, default: 5) instead of just the first match. The limit is configurable in admin settings and can be disabled (set to 0) to find all occurrences, though this is not recommended for large courses as it may impact performance
- **Improved**: Enhanced search logic to find both title and content matches for all activity types (books, pages, wiki, etc.) - previously, title matches would skip content search
- **Fixed**: Books now properly search both description/intro and chapter content, allowing multiple matches to be grouped
- **Fixed**: Pages now properly search both title and content, allowing multiple matches to be grouped
- **Fixed**: Wiki now properly searches both page titles and content, allowing multiple matches to be grouped
- **Fixed**: Labels now only show "Matched in description or content" results (eliminated duplicate results)
- **Fixed**: Wiki URL format corrected to use only 'pageid' parameter instead of both 'id' and 'pageid'

## Changes in version 1.2.3

- **Fixed**: Critical bug in forum search where only the last matching post/reply was shown instead of all matching posts - Fixed SQL query to properly retrieve all forum posts by using post ID as the primary key instead of discussion ID

## Changes in version 1.2.2

- **Fixed**: Bug where search terms matched HTML tag names (e.g., searching "group" matched `<colgroup>` tags)
- **Fixed**: Bug where internal Moodle placeholders (like `@@PLUGINFILE@@`) caused false positive search matches
- **Added**: Configurable placeholder filtering in admin settings - administrators can now define regex patterns to exclude internal placeholders from search
- **Improved**: Search accuracy by filtering out non-visible HTML markup and internal system strings

## Changes in version 1.2.1

- **Added**: Optional grouping toggle in activity settings - teachers can now choose between grouped (by sections) or ungrouped (flat list) result display
- **Improved**: Pagination now works for both grouped and ungrouped views

## Changes in version 1.2.0

- **Added**: Search results pagination for large result sets
- **Added**: Automatic grouping of results by course sections
- **Improved**: Result organization and navigation

## Changes in version 1.1.0

- **Extended**: Highlighting support to Pages, Books, Lessons, Wiki, Forums, Glossary, and Database activities

## Changes in version 1.0.0

- Initial release
- Core search functionality for course content
- Basic highlighting support
- Embedded mode support
