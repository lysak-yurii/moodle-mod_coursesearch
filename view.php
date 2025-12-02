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
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/coursesearch/lib.php');
require_once($CFG->dirroot.'/mod/coursesearch/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$cs = optional_param('cs', 0, PARAM_INT);  // CourseSearch instance ID
$query = optional_param('query', '', PARAM_TEXT); // Search query
$filter = optional_param('filter', 'all', PARAM_ALPHA); // Content filter (title, content, description)
$searchscope = optional_param('searchscope', 'all', PARAM_ALPHA); // Search scope (all, sections, activities, resources, forums)

// Trim whitespace from the search query
$query = trim($query);

// Get the course module.
if ($id) {
    $cm = get_coursemodule_from_id('coursesearch', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $coursesearch = $DB->get_record('coursesearch', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($cs) {
    $coursesearch = $DB->get_record('coursesearch', array('id' => $cs), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $coursesearch->course), '*', MUST_EXIST);
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
$PAGE->set_url('/mod/coursesearch/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($coursesearch->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Get the placeholder text.
$placeholder = !empty($coursesearch->placeholder) ? $coursesearch->placeholder : get_string('defaultplaceholder', 'coursesearch');

// Output starts here.
echo $OUTPUT->header();

// Display intro if set.
if (!empty($coursesearch->intro)) {
    echo $OUTPUT->box(format_module_intro('coursesearch', $coursesearch, $cm->id), 'generalbox', 'intro');
}

// Display the search form.
echo html_writer::start_div('coursesearch-container');
echo html_writer::start_tag('form', array('action' => new moodle_url('/mod/coursesearch/view.php', array('id' => $cm->id)), 'method' => 'get', 'class' => 'coursesearch-form'));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cm->id));

// Add filter options above the search bar
echo html_writer::start_div('coursesearch-filters mb-3');

// Create filter options
$filter_options = array(
    'all' => get_string('searchscope_all', 'coursesearch'),
    'forums' => get_string('searchscope_forums', 'coursesearch')
);

// Add filter label
echo html_writer::tag('label', get_string('searchscope', 'coursesearch') . ':', array('class' => 'mr-2'));

// Create radio buttons for each filter option
foreach ($filter_options as $value => $label) {
    $attributes = array(
        'type' => 'radio',
        'name' => 'filter',
        'id' => 'filter_' . $value,
        'value' => $value,
        'class' => 'mr-1'
    );
    
    // Check the current filter option if it matches
    if ($filter === $value) {
        $attributes['checked'] = 'checked';
    }
    
    echo html_writer::start_span('mr-3');
    echo html_writer::empty_tag('input', $attributes);
    echo html_writer::tag('label', $label, array('for' => 'filter_' . $value, 'class' => 'mr-2'));
    echo html_writer::end_span();
}

echo html_writer::end_div(); // coursesearch-filters

echo html_writer::start_div('input-group');
echo html_writer::empty_tag('input', array('type' => 'text', 'name' => 'query', 'value' => s($query), 'class' => 'form-control', 'placeholder' => $placeholder));
echo html_writer::start_div('input-group-append');
echo html_writer::tag('button', get_string('search'), array('type' => 'submit', 'class' => 'btn btn-primary'));
echo html_writer::end_div(); // input-group-append
echo html_writer::end_div(); // input-group
echo html_writer::end_tag('form');
echo html_writer::end_div(); // coursesearch-container

// Display search results if a query was submitted
if (!empty($query)) {
    echo $OUTPUT->heading(get_string('searchresultsfor', 'coursesearch', s($query)));
    
    // Trigger the search event
    $params = array(
        'context' => $context,
        'objectid' => $coursesearch->id,
        'other' => array(
            'query' => $query
        )
    );
    $event = \mod_coursesearch\event\course_searched::create($params);
    $event->trigger();
    
    // Include the filter parameter in the search
    $results = coursesearch_perform_search($query, $course, $filter);
    

    

    
    if (empty($results)) {
        echo html_writer::div(get_string('noresults', 'coursesearch', s($query)), 'coursesearch-no-results');
    } else {
        // Display the count of search results
        $count = count($results);
        echo html_writer::div($count . ' ' . get_string('searchresults', 'coursesearch', s($query)), 'coursesearch-results-count');
        
        echo html_writer::start_div('coursesearch-results');
        
        foreach ($results as $result) {
            echo html_writer::start_div('coursesearch-result');
            
            // Process multilanguage tags in the result name
            $result_name = isset($result['name']) ? coursesearch_process_multilang($result['name']) : '';
            
            // Display the module icon and name
            // For sections, use a custom icon with appropriate alt text
            if ($result['modname'] === 'section') {
                // Use a better icon for sections
                $icon_url = new moodle_url('/pix/i/folder.png'); // Using folder icon for sections
                $icon = html_writer::img($icon_url, get_string('section'), array('class' => 'icon'));
                
                // Remove 'Section: ' prefix from the displayed name to avoid duplication
                $section_prefix = get_string('section') . ': ';
                if (strpos($result_name, $section_prefix) === 0) {
                    $result_name = substr($result_name, strlen($section_prefix));
                }
            } else {
                // For other module types, use the standard icon
                $icon = html_writer::img($result['icon'], $result['modname'], array('class' => 'icon'));
            }
            
            // Ensure all results have a valid URL
            // Check if URL exists and is valid - also check if it has an anchor (which we want to preserve)
            $url_exists = isset($result['url']) && !empty($result['url']);
            $url_has_anchor = false;
            if ($url_exists && $result['url'] instanceof moodle_url) {
                // Check if the URL has an anchor by checking the anchor property
                // moodle_url stores anchor separately, so we need to check the full URL output
                $url_string = $result['url']->out(true); // true = include anchor
                $url_has_anchor = (strpos($url_string, '#') !== false);
            }
            
            // Only fix URL if it doesn't exist, or if it exists but doesn't have an anchor and needs one
            // Preserve URLs that already have anchors (they were set correctly in locallib.php)
            // For labels and html modules, they MUST have anchors, so if they don't, we need to add one
            $needs_anchor = isset($result['modname']) && ($result['modname'] === 'label' || $result['modname'] === 'html');
            if (!$url_exists || ($needs_anchor && !$url_has_anchor)) {
                // For results without a URL, try to find the appropriate URL based on the result type and match
                if (isset($result['modname'])) {
                    // If we have a stored cmid and it's a label/html, use it directly
                    if (isset($result['cmid']) && ($result['modname'] === 'label' || $result['modname'] === 'html')) {
                        // Try to get section number from modinfo if available
                        $modinfo = get_fast_modinfo($course);
                        $cm = $modinfo->get_cm($result['cmid']);
                        $sectionnum = isset($cm->sectionnum) ? $cm->sectionnum : (isset($cm->section) ? $cm->section : null);
                        $urlparams = array('id' => $course->id);
                        if ($sectionnum !== null) {
                            $urlparams['section'] = $sectionnum;
                        }
                        // Add highlight parameter if we have a query
                        if (!empty($query)) {
                            $urlparams['highlight'] = urlencode($query);
                        }
                        $moduleurl = new moodle_url('/course/view.php', $urlparams);
                        $moduleurl->set_anchor('module-' . $result['cmid']);
                        $result['url'] = $moduleurl;
                    } else {
                        $modinfo = get_fast_modinfo($course);
                        
                        // If it's a section, try to find the section
                        if ($result['modname'] === 'section') {
                            // Extract section number from the name if possible
                            $section_number = null;
                            if (preg_match('/Section\s+(\d+)/i', $result['name'], $matches)) {
                                $section_number = $matches[1];
                            }
                            
                            if ($section_number !== null) {
                                $result['url'] = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $section_number));
                            } else {
                                // Try to find the section by name
                                $sections = $DB->get_records('course_sections', array('course' => $course->id), 'section', 'id, section, name');
                                foreach ($sections as $section) {
                                    // Clean up the section name for comparison
                                    $clean_section_name = strip_tags(coursesearch_process_multilang($section->name));
                                    $clean_result_name = strip_tags($result_name);
                                    
                                    // Remove "Section: " prefix for comparison
                                    $clean_result_name = str_replace(get_string('section') . ': ', '', $clean_result_name);
                                    
                                    if ($clean_section_name === $clean_result_name || 
                                        stripos($clean_section_name, $clean_result_name) !== false) {
                                        $result['url'] = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $section->section));
                                        break;
                                    }
                                }
                            }
                        } else {
                            // For other module types, try to find the module by name
                            foreach ($modinfo->get_cms() as $cm) {
                                // Clean up names for comparison
                                $clean_cm_name = strip_tags(coursesearch_process_multilang($cm->name));
                                $clean_result_name = strip_tags($result_name);
                                
                                if ($clean_cm_name === $clean_result_name || 
                                    stripos($clean_cm_name, $clean_result_name) !== false) {
                                    // For labels and html modules, ensure we create a URL with anchor
                                    if ($result['modname'] === 'label' || $result['modname'] === 'html') {
                                        // Include section parameter to ensure the correct section is displayed
                                        $sectionnum = isset($cm->sectionnum) ? $cm->sectionnum : (isset($cm->section) ? $cm->section : null);
                                        $urlparams = array('id' => $course->id);
                                        if ($sectionnum !== null) {
                                            $urlparams['section'] = $sectionnum;
                                        }
                                        // Add highlight parameter if we have a query
                                        if (!empty($query)) {
                                            $urlparams['highlight'] = urlencode($query);
                                        }
                                        $moduleurl = new moodle_url('/course/view.php', $urlparams);
                                        $moduleurl->set_anchor('module-' . $cm->id);
                                        $result['url'] = $moduleurl;
                                    } else {
                                        // For other module types, use the module's URL
                                        $result['url'] = $cm->url;
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // If we still don't have a URL, default to the course page
                if (!isset($result['url']) || empty($result['url'])) {
                    $result['url'] = new moodle_url('/course/view.php', array('id' => $course->id));
                }
            }
            
            // Ensure highlight parameter is added if we have a query for ALL result URLs
            // This includes course/view.php, mod/page/view.php, mod/forum/discuss.php, etc.
            if (isset($result['url']) && $result['url'] instanceof moodle_url && !empty($query)) {
                $urlpath = $result['url']->get_path();
                // Add highlight to course pages, page activities, and other module pages
                if (strpos($urlpath, '/course/view.php') !== false || 
                    strpos($urlpath, '/mod/page/view.php') !== false ||
                    strpos($urlpath, '/mod/') !== false) {
                    // Check if highlight parameter is already present
                    $params = $result['url']->params();
                    if (!isset($params['highlight'])) {
                        $result['url']->param('highlight', urlencode($query));
                    }
                }
            }
            
            $name = html_writer::link($result['url'], $result_name);
            echo html_writer::div($icon . ' ' . $name, 'coursesearch-result-title');
            
            // Display forum information if available
            if ($result['modname'] === 'forum' && isset($result['forum_name'])) {
                $forum_name = coursesearch_process_multilang($result['forum_name']);
                echo html_writer::div(get_string('inforum', 'coursesearch', $forum_name), 'coursesearch-result-forum');
            }
            
            // Display the snippet with highlighted search term
            if (isset($result['snippet']) && !empty($result['snippet'])) {
                // The snippet may contain HTML from highlighting, so we use format_text with appropriate options
                // This ensures any dangerous HTML is properly sanitized while preserving safe highlighting
                $snippet = format_text($result['snippet'], FORMAT_HTML, array('noclean' => false, 'para' => false));
                echo html_writer::div($snippet, 'coursesearch-result-snippet');
            }
            
            // Display what was matched (title, content, etc.)
            $match_type = isset($result['match']) ? get_string('matchedin', 'coursesearch', $result['match']) : '';
            echo html_writer::div($match_type, 'coursesearch-result-match');
            
            echo html_writer::end_div(); // coursesearch-result
        }
        
        echo html_writer::end_div(); // coursesearch-results
    }
}

// Add client-side JavaScript to handle scrolling to highlighted text
// This intercepts link clicks and stores highlight data, then injects script for course pages
if (!empty($query)) {
    // Create a global function that will be available on course pages
    $js = "
    <script>
    (function() {
        // Shared scroll function - optimized to search while walking instead of collecting all nodes first
        function scrollToText(element, searchText) {
            if (!element || !searchText) return false;
            
            var searchLower = searchText.toLowerCase();
            var walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null, false);
            var node;
            
            // Search while walking - stop on first match for better performance
            while (node = walker.nextNode()) {
                var text = node.textContent;
                var textLower = text.toLowerCase();
                var index = textLower.indexOf(searchLower);
                
                if (index !== -1) {
                    try {
                        var range = document.createRange();
                        range.setStart(node, index);
                        range.setEnd(node, index + searchText.length);
                        var rect = range.getBoundingClientRect();
                        window.scrollTo({top: window.scrollY + rect.top - 100, behavior: 'smooth'});
                        
                        // Highlight the matched text
                        var span = document.createElement('span');
                        span.style.backgroundColor = '#ffff99';
                        span.style.padding = '2px';
                        try {
                            range.surroundContents(span);
                            // Remove highlight after 3 seconds
                            setTimeout(function() {
                                try {
                                    if (span && span.parentNode) {
                                        var parentNode = span.parentNode;
                                        var textContent = span.textContent;
                                        parentNode.replaceChild(document.createTextNode(textContent), span);
                                        // Normalize to merge adjacent text nodes
                                        if (parentNode && (parentNode.parentNode || document.body.contains(parentNode))) {
                                            parentNode.normalize();
                                        }
                                    }
                                } catch(e) {
                                    console.error('Error removing highlight:', e);
                                }
                            }, 3000);
                        } catch(e) {
                            console.error('Error highlighting text:', e);
                        }
                        return true;
                    } catch(e) {
                        // Fallback: scroll to element if range manipulation fails
                        console.error('Error creating range for text:', e);
                        try {
                            window.scrollTo({top: element.getBoundingClientRect().top + window.scrollY - 100, behavior: 'smooth'});
                        } catch(scrollErr) {
                            console.error('Error scrolling to element:', scrollErr);
                        }
                        return true;
                    }
                }
            }
            return false;
        }
        
        // Make scrollToText available globally for course pages
        window.coursesearchScrollToText = scrollToText;
        
        // Use event delegation for better performance - single handler for all links
        function handleResultClick(e) {
            var link = e.target.closest('.coursesearch-results a[href]');
            if (!link) return;
            
            var href = link.getAttribute('href');
            if (!href) return;
            
            try {
                var url = new URL(href, window.location.origin);
                var highlight = url.searchParams.get('highlight');
                var hash = url.hash;
                var moduleId = null;
                
                if (hash) {
                    var match = hash.match(/^#module-(\\d+)$/);
                    if (match) moduleId = match[1];
                }
                
                if (highlight && typeof sessionStorage !== 'undefined') {
                    // Safely escape the highlight value using JSON.stringify before storing
                    // This prevents XSS by properly escaping all special characters
                    try {
                        var safeHighlight = JSON.stringify(highlight);
                        sessionStorage.setItem('coursesearch_highlight', safeHighlight);
                        
                        if (moduleId) {
                            // Validate moduleId is numeric only to prevent XSS
                            if (/^\\d+$/.test(moduleId)) {
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
        }
        
        // Attach event delegation handler
        function attachClickHandler() {
            var container = document.querySelector('.coursesearch-results');
            if (container) {
                container.addEventListener('click', handleResultClick);
            }
        }
        
        // Attach immediately if DOM is ready, otherwise wait
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', attachClickHandler);
        } else {
            attachClickHandler();
        }
        
        // Create a function that will execute on course pages
        // This function checks sessionStorage and scrolls to highlighted text
        window.coursesearchInitScroll = function() {
            if (typeof sessionStorage === 'undefined') return;
            
            var highlight = sessionStorage.getItem('coursesearch_highlight');
            var moduleId = sessionStorage.getItem('coursesearch_moduleId');
            var timestamp = sessionStorage.getItem('coursesearch_timestamp');
            
            if (!highlight) return;
            
            // Check if timestamp is recent (within 10 seconds)
            if (timestamp && Date.now() - parseInt(timestamp) > 10000) {
                sessionStorage.removeItem('coursesearch_highlight');
                sessionStorage.removeItem('coursesearch_moduleId');
                sessionStorage.removeItem('coursesearch_timestamp');
                return;
            }
            
            // Only run on course view pages
            if (window.location.pathname.indexOf('/course/view.php') === -1) return;
            
            function initScroll() {
                // Safely decode the highlight value that was stored using JSON.stringify
                var searchText = '';
                try {
                    // The value was stored using JSON.stringify, so parse it back safely
                    searchText = JSON.parse(highlight);
                    if (typeof searchText !== 'string') {
                        searchText = '';
                    } else {
                        searchText = searchText.trim();
                    }
                } catch(e) {
                    // If JSON parsing fails, the value might be corrupted or malicious
                    console.error('Error parsing highlight data:', e);
                    sessionStorage.removeItem('coursesearch_highlight');
                    sessionStorage.removeItem('coursesearch_moduleId');
                    sessionStorage.removeItem('coursesearch_timestamp');
                    return;
                }
                
                if (!searchText) {
                    sessionStorage.removeItem('coursesearch_highlight');
                    sessionStorage.removeItem('coursesearch_moduleId');
                    sessionStorage.removeItem('coursesearch_timestamp');
                    return;
                }
                
                // Clean up sessionStorage helper
                function cleanup() {
                    sessionStorage.removeItem('coursesearch_highlight');
                    sessionStorage.removeItem('coursesearch_moduleId');
                    sessionStorage.removeItem('coursesearch_timestamp');
                }
                
                var hash = window.location.hash;
                var targetModuleId = moduleId || (hash ? hash.match(/^#module-(\\d+)$/) : null);
                if (targetModuleId && !moduleId) targetModuleId = targetModuleId[1];
                
                // Validate moduleId is numeric only
                if (targetModuleId && !/^\\d+$/.test(targetModuleId)) {
                    targetModuleId = null;
                }
                
                if (targetModuleId) {
                    var el = document.getElementById('module-' + targetModuleId);
                    if (el) {
                        if (scrollToText(el, searchText)) {
                            cleanup();
                            return;
                        }
                        // Fallback: scroll to module if text not found
                        try {
                            window.scrollTo({top: el.getBoundingClientRect().top + window.scrollY - 100, behavior: 'smooth'});
                        } catch(e) {
                            console.error('Error scrolling to module:', e);
                        }
                        cleanup();
                    } else {
                        cleanup();
                    }
                } else {
                    scrollToText(document.body, searchText);
                    cleanup();
                }
            }
            
            // Use requestAnimationFrame for smoother initialization
            function init() {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(initScroll, 300);
                    });
                } else {
                    setTimeout(initScroll, 300);
                }
            }
            init();
        };
        
        // Also try to inject it into the next page by storing it
        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.setItem('coursesearch_shouldScroll', 'true');
        }
        
        // For immediate execution on this page, call the function directly
        if (window.location.pathname.indexOf('/course/view.php') !== -1) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(window.coursesearchInitScroll, 300);
                });
            } else {
                setTimeout(window.coursesearchInitScroll, 300);
            }
        }
    })();
    </script>
    ";
    echo $js;
}

// Finish the page.
echo $OUTPUT->footer();
