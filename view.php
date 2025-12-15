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

// Add client-side JavaScript to handle scrolling to highlighted text.
// This intercepts link clicks and stores highlight data, then injects script for course pages.
if (!empty($query)) {
    // Create a global function that will be available on course pages.
    $js = <<<'JAVASCRIPT'
<script>
(function() {
    // Define scroll function that will be used on course pages.
    window.coursesearchScrollToText = function(element, searchText) {
        if (!element || !searchText) return false;
        var walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null, false);
        var textNodes = [];
        var node;
        while (node = walker.nextNode()) textNodes.push(node);
        var searchLower = searchText.toLowerCase();
        for (var i = 0; i < textNodes.length; i++) {
            var text = textNodes[i].textContent.toLowerCase();
            var index = text.indexOf(searchLower);
            if (index !== -1) {
                var range = document.createRange();
                var textNode = textNodes[i];
                var originalIndex = textNode.textContent.toLowerCase().indexOf(searchLower);
                if (originalIndex !== -1) {
                    try {
                        range.setStart(textNode, originalIndex);
                        range.setEnd(textNode, originalIndex + searchText.length);
                        var rect = range.getBoundingClientRect();
                        window.scrollTo({top: window.scrollY + rect.top - 100, behavior: 'smooth'});
                        var span = document.createElement('span');
                        span.style.setProperty('background-color', '#ffff99', 'important');
                        span.style.setProperty('padding', '2px', 'important');
                        span.style.setProperty('border-radius', '2px', 'important');
                        span.style.setProperty('color', 'inherit', 'important');
                        var highlighted = false;
                        try {
                            range.surroundContents(span);
                            highlighted = true;
                            setTimeout(function() {
                                try {
                                    if (span && span.parentNode) {
                                        var parentNode = span.parentNode;
                                        var textContent = span.textContent;
                                        parentNode.replaceChild(document.createTextNode(textContent), span);
                                        if (parentNode && (parentNode.parentNode || document.body.contains(parentNode))) {
                                            parentNode.normalize();
                                        }
                                    }
                                } catch(e) {}
                            }, 3000);
                        } catch(e) {
                            highlighted = false;
                        }
                        // Fallback: highlight parent element.
                        if (!highlighted) {
                            var parent = textNode.parentElement;
                            var validTags = ['P','DIV','SPAN','A','LI','TD','TH','LABEL','H1','H2','H3','H4','H5','H6','STRONG','EM','B','I','U'];
                            while (parent && parent !== element && parent !== document.body) {
                                if (validTags.indexOf(parent.tagName.toUpperCase()) !== -1) break;
                                parent = parent.parentElement;
                            }
                            if (parent && parent !== element && parent !== document.body) {
                                var originalBg = parent.style.backgroundColor;
                                parent.style.setProperty('background-color', '#ffff99', 'important');
                                setTimeout(function() {
                                    if (originalBg) { parent.style.backgroundColor = originalBg; }
                                    else { parent.style.removeProperty('background-color'); }
                                }, 3000);
                            }
                        }
                        return true;
                    } catch(e) {
                        window.scrollTo({top: element.getBoundingClientRect().top + window.scrollY - 100, behavior: 'smooth'});
                        return true;
                    }
                }
            }
        }
        return false;
    };

    // Intercept clicks on ALL result links - attach immediately and also on DOMContentLoaded.
    function attachClickHandlers() {
        // Get all result links (course pages, page activities, forums, etc.).
        var resultLinks = document.querySelectorAll('.coursesearch-results a[href]');
        resultLinks.forEach(function(link) {
            // Skip if already has handler (check for data attribute).
            if (link.dataset.coursesearchHandler) return;
            link.dataset.coursesearchHandler = 'true';

            link.addEventListener('click', function(e) {
                var href = this.getAttribute('href');
                try {
                    var url = new URL(href, window.location.origin);
                    var highlight = url.searchParams.get('highlight');
                    var hash = url.hash;
                    var moduleId = null;
                    if (hash) {
                        var match = hash.match(/^#module-(\d+)$/);
                        if (match) moduleId = match[1];
                    }
                    if (highlight && typeof sessionStorage !== 'undefined') {
                        // Safely escape the highlight value using JSON.stringify before storing.
                        // This prevents XSS by properly escaping all special characters.
                        try {
                            var safeHighlight = JSON.stringify(highlight);
                            sessionStorage.setItem('coursesearch_highlight', safeHighlight);
                            if (moduleId) {
                                // Validate moduleId is numeric only to prevent XSS.
                                if (/^\d+$/.test(moduleId)) {
                                    sessionStorage.setItem('coursesearch_moduleId', moduleId);
                                }
                            }
                            sessionStorage.setItem('coursesearch_timestamp', Date.now().toString());
                            sessionStorage.setItem('coursesearch_shouldScroll', 'true');
                        } catch(err) {
                            console.error('Error escaping highlight data:', err);
                        }
                    }
                } catch(err) {
                    console.error('Error storing highlight data:', err);
                }
            });
        });
    }

    // Attach immediately if DOM is ready, otherwise wait.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachClickHandlers);
    } else {
        attachClickHandlers();
    }

    // Create a function that will execute on course pages.
    // This function checks sessionStorage and scrolls to highlighted text.
    window.coursesearchInitScroll = function() {
        if (typeof sessionStorage === 'undefined') return;
        var highlight = sessionStorage.getItem('coursesearch_highlight');
        var moduleId = sessionStorage.getItem('coursesearch_moduleId');
        var timestamp = sessionStorage.getItem('coursesearch_timestamp');
        if (!highlight) return;
        // Check if timestamp is recent (within 10 seconds).
        if (timestamp && Date.now() - parseInt(timestamp) > 10000) {
            sessionStorage.removeItem('coursesearch_highlight');
            sessionStorage.removeItem('coursesearch_moduleId');
            sessionStorage.removeItem('coursesearch_timestamp');
            return;
        }
        // Only run on course view pages.
        if (window.location.pathname.indexOf('/course/view.php') === -1) return;

        // Define scroll function (needed on course page).
        function scrollToText(element, searchText) {
            if (!element || !searchText) return false;
            var searchLower = searchText.toLowerCase().trim();
            var walker = document.createTreeWalker(
                element,
                NodeFilter.SHOW_TEXT,
                {
                    acceptNode: function(node) {
                        if (!node.textContent.trim()) return NodeFilter.FILTER_REJECT;
                        var parent = node.parentNode;
                        while (parent && parent !== element) {
                            if (parent.tagName === 'SCRIPT' || parent.tagName === 'STYLE') {
                                return NodeFilter.FILTER_REJECT;
                            }
                            var classList = parent.classList;
                            if (classList && (classList.contains('sr-only') || classList.contains('visually-hidden') || classList.contains('hidden'))) {
                                return NodeFilter.FILTER_REJECT;
                            }
                            var style = window.getComputedStyle(parent);
                            if (style.display === 'none' || style.visibility === 'hidden') {
                                return NodeFilter.FILTER_REJECT;
                            }
                            parent = parent.parentElement;
                        }
                        return NodeFilter.FILTER_ACCEPT;
                    }
                },
                false
            );

            var textNodes = [];
            var node;
            while (node = walker.nextNode()) {
                textNodes.push(node);
            }

            for (var i = 0; i < textNodes.length; i++) {
                var textNode = textNodes[i];
                var text = textNode.textContent;
                var textLower = text.toLowerCase();
                var index = textLower.indexOf(searchLower);

                if (index !== -1) {
                    try {
                        var range = document.createRange();
                        range.setStart(textNode, index);
                        range.setEnd(textNode, index + searchText.length);
                        var rect = range.getBoundingClientRect();
                        if (rect.width === 0 && rect.height === 0) {
                            continue;
                        }
                        window.scrollTo({top: window.scrollY + rect.top - 100, behavior: 'smooth'});
                        try {
                            var span = document.createElement('span');
                            span.style.setProperty('background-color', '#ffff99', 'important');
                            span.style.setProperty('padding', '2px', 'important');
                            span.style.setProperty('border-radius', '2px', 'important');
                            span.style.setProperty('color', 'inherit', 'important');
                            try {
                                range.surroundContents(span);
                                setTimeout(function() {
                                    if (span && span.parentNode) {
                                        var parentNode = span.parentNode;
                                        parentNode.replaceChild(document.createTextNode(span.textContent), span);
                                        if (parentNode.parentNode) { parentNode.normalize(); }
                                    }
                                }, 3000);
                            } catch(surroundError) {
                                var parent = textNode.parentNode;
                                var validTags = ['P','DIV','SPAN','A','LI','TD','TH','LABEL','H1','H2','H3','H4','H5','H6','STRONG','EM','B','I','U'];
                                while (parent && parent !== element && parent !== document.body) {
                                    if (validTags.indexOf(parent.tagName.toUpperCase()) !== -1) break;
                                    parent = parent.parentElement;
                                }
                                if (parent && parent !== element && parent !== document.body) {
                                    var originalBg = parent.style.backgroundColor;
                                    parent.style.setProperty('background-color', '#ffff99', 'important');
                                    setTimeout(function() {
                                        if (originalBg) { parent.style.backgroundColor = originalBg; }
                                        else { parent.style.removeProperty('background-color'); }
                                    }, 3000);
                                }
                            }
                        } catch(e) {}
                        return true;
                    } catch(e) {
                        try {
                            var parent = textNode.parentNode;
                            if (parent) {
                                var parentRect = parent.getBoundingClientRect();
                                window.scrollTo({top: window.scrollY + parentRect.top - 100, behavior: 'smooth'});
                                return true;
                            }
                        } catch(e2) {
                            window.scrollTo({top: element.getBoundingClientRect().top + window.scrollY - 100, behavior: 'smooth'});
                            return true;
                        }
                    }
                }
            }
            return false;
        }

        var highlightAttempted = false;
        var observer = null;

        function initScroll(retryCount) {
            retryCount = retryCount || 0;
            var maxRetries = 15;

            var searchText = '';
            try {
                searchText = JSON.parse(highlight);
                if (typeof searchText !== 'string') {
                    searchText = '';
                } else {
                    searchText = searchText.trim();
                }
            } catch(e) {
                console.error('Error parsing highlight data:', e);
                sessionStorage.removeItem('coursesearch_highlight');
                sessionStorage.removeItem('coursesearch_moduleId');
                sessionStorage.removeItem('coursesearch_timestamp');
                if (observer) observer.disconnect();
                return;
            }
            if (!searchText) {
                if (observer) observer.disconnect();
                return;
            }

            var hash = window.location.hash;
            var targetModuleId = moduleId || (hash ? hash.match(/^#module-(\d+)$/) : null);
            if (targetModuleId && !moduleId) targetModuleId = targetModuleId[1];
            if (targetModuleId && !/^\d+$/.test(targetModuleId)) {
                targetModuleId = null;
            }

            var targetElement = null;
            if (targetModuleId) {
                targetElement = document.getElementById('module-' + targetModuleId);
            }

            if (targetModuleId && !targetElement && retryCount < maxRetries) {
                setTimeout(function() {
                    initScroll(retryCount + 1);
                }, 500);
                return;
            }

            var found = false;
            if (targetElement) {
                found = scrollToText(targetElement, searchText);
                if (!found) {
                    try {
                        window.scrollTo({top: targetElement.getBoundingClientRect().top + window.scrollY - 100, behavior: 'smooth'});
                    } catch(e) {}
                }
            } else {
                found = scrollToText(document.body, searchText);
            }

            if (!found && retryCount < maxRetries) {
                setTimeout(function() {
                    initScroll(retryCount + 1);
                }, 500);
                return;
            }

            if (found) {
                sessionStorage.removeItem('coursesearch_highlight');
                sessionStorage.removeItem('coursesearch_moduleId');
                sessionStorage.removeItem('coursesearch_timestamp');
                if (observer) observer.disconnect();
                highlightAttempted = true;
            } else if (retryCount >= maxRetries) {
                if (observer) observer.disconnect();
                highlightAttempted = true;
            }
        }

        function startInitScroll() {
            if (typeof MutationObserver !== 'undefined' && !observer) {
                observer = new MutationObserver(function(mutations) {
                    if (!highlightAttempted) {
                        setTimeout(function() {
                            initScroll(0);
                        }, 200);
                    }
                });

                if (document.body) {
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true,
                        characterData: true
                    });
                }
            }

            function attemptHighlight() {
                setTimeout(function() {
                    if (!highlightAttempted) {
                        initScroll(0);
                    }
                }, 800);
            }

            if (document.readyState === 'complete') {
                attemptHighlight();
            } else if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    window.addEventListener('load', function() {
                        attemptHighlight();
                    });
                    attemptHighlight();
                });
            } else {
                window.addEventListener('load', function() {
                    attemptHighlight();
                });
                attemptHighlight();
            }
        }

        startInitScroll();
    };

    // Also try to inject it into the next page by storing it.
    if (typeof sessionStorage !== 'undefined') {
        sessionStorage.setItem('coursesearch_shouldScroll', 'true');
    }

    // For immediate execution on this page.
    if (window.location.pathname.indexOf('/course/view.php') !== -1) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(window.coursesearchInitScroll, 500);
            });
        } else {
            setTimeout(window.coursesearchInitScroll, 500);
        }
    }
})();
</script>
JAVASCRIPT;
    echo $js;
}

// Finish the page.
echo $OUTPUT->footer();
