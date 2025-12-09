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

// Validate filter parameter against whitelist to prevent injection
$allowed_filters = array('all', 'title', 'content', 'description', 'sections', 'activities', 'resources', 'forums');
if (!in_array($filter, $allowed_filters)) {
    $filter = 'all'; // Default to 'all' if invalid filter provided
}

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
        $resultcountobj = new stdClass();
        $resultcountobj->count = $count;
        $resultcountobj->query = s($query);
        echo html_writer::div(get_string('searchresultscount', 'coursesearch', $resultcountobj), 'coursesearch-results-count');
        
        echo html_writer::start_div('coursesearch-results');
        
        foreach ($results as $result) {
            echo html_writer::start_div('coursesearch-result');
            
            // Process multilanguage tags in the result name
            $result_name = isset($result['name']) ? coursesearch_process_multilang($result['name']) : '';
            // Strip any HTML tags from the result name for safety (names should be plain text)
            $result_name = strip_tags($result_name);
            
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
                            // Clean the query parameter to prevent XSS - urlencode will encode it for URL
                            $cleanquery = clean_param($query, PARAM_TEXT);
                            $urlparams['highlight'] = urlencode($cleanquery);
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
                                            // Clean the query parameter to prevent XSS - urlencode will encode it for URL
                                            $cleanquery = clean_param($query, PARAM_TEXT);
                                            $urlparams['highlight'] = urlencode($cleanquery);
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
                        // Clean the query parameter to prevent XSS - moodle_url->param() will URL-encode it
                        $cleanquery = clean_param($query, PARAM_TEXT);
                        $result['url']->param('highlight', $cleanquery);
                    }
                }
            }
            
            $name = html_writer::link($result['url'], $result_name);
            echo html_writer::div($icon . ' ' . $name, 'coursesearch-result-title');
            
            // Display forum information if available
            if ($result['modname'] === 'forum' && isset($result['forum_name'])) {
                $forum_name = coursesearch_process_multilang($result['forum_name']);
                echo html_writer::div(get_string('inforum', 'coursesearch', s($forum_name)), 'coursesearch-result-forum');
            }
            
            // Display the snippet with highlighted search term
            if (isset($result['snippet']) && !empty($result['snippet'])) {
                // The snippet may contain HTML from highlighting, so we use format_text with appropriate options
                // This ensures any dangerous HTML is properly sanitized while preserving safe highlighting
                $snippet = format_text($result['snippet'], FORMAT_HTML, array('noclean' => false, 'para' => false));
                echo html_writer::div($snippet, 'coursesearch-result-snippet');
            }
            
            // Display what was matched (title, content, etc.)
            $match_type = isset($result['match']) ? get_string('matchedin', 'coursesearch', s($result['match'])) : '';
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
        // Define scroll function that will be used on course pages
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
                            span.style.backgroundColor = '#ffff99';
                            span.style.padding = '2px';
                            span.style.borderRadius = '2px';
                            try {
                                range.surroundContents(span);
                                setTimeout(function() {
                                    try {
                                        if (span && span.parentNode) {
                                            var parentNode = span.parentNode;
                                            var textContent = span.textContent;
                                            parentNode.replaceChild(document.createTextNode(textContent), span);
                                            // Normalize to merge adjacent text nodes
                                            // Check if parentNode still exists and is connected to document
                                            if (parentNode && (parentNode.parentNode || document.body.contains(parentNode))) {
                                                parentNode.normalize();
                                            }
                                        }
                                    } catch(e) {
                                        // Ignore errors if nodes were already removed or modified
                                    }
                                }, 3000);
                            } catch(e) {
                                // If surroundContents fails (e.g., text is in a link), highlight the parent element
                                var parent = textNode.parentElement;
                                if (parent && parent !== element && parent.tagName !== 'BODY' && parent.tagName !== 'HTML') {
                                    var originalBg = parent.style.backgroundColor;
                                    var originalTransition = parent.style.transition;
                                    parent.style.backgroundColor = '#ffff99';
                                    parent.style.transition = 'background-color 0.3s';
                                    setTimeout(function() {
                                        parent.style.backgroundColor = originalBg;
                                        setTimeout(function() {
                                            parent.style.transition = originalTransition;
                                        }, 300);
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
        
        // Intercept clicks on ALL result links - attach immediately and also on DOMContentLoaded
        function attachClickHandlers() {
            // Get all result links (course pages, page activities, forums, etc.)
            var resultLinks = document.querySelectorAll('.coursesearch-results a[href]');
            resultLinks.forEach(function(link) {
                // Skip if already has handler (check for data attribute)
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
                });
            });
        }
        
        // Attach immediately if DOM is ready, otherwise wait
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', attachClickHandlers);
        } else {
            attachClickHandlers();
        }
        
        // Create a function that will execute on course pages
        // This function checks sessionStorage and scrolls to highlighted text
        // We define it as a proper function instead of using eval()
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
            
            // Define scroll function (needed on course page)
            // Improved version that handles text inside links and across node boundaries
            function scrollToText(element, searchText) {
                if (!element || !searchText) return false;
                
                // First, try to find text using a more robust method that handles links
                var searchLower = searchText.toLowerCase().trim();
                
                // Get all text content and search for matches
                var walker = document.createTreeWalker(
                    element,
                    NodeFilter.SHOW_TEXT,
                    {
                        acceptNode: function(node) {
                            // Skip script and style nodes
                            var parent = node.parentNode;
                            if (parent && (parent.tagName === 'SCRIPT' || parent.tagName === 'STYLE')) {
                                return NodeFilter.FILTER_REJECT;
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
                
                // Search through text nodes, handling text that might span multiple nodes
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
                            
                            // Check if range is valid and doesn't span invalid boundaries
                            try {
                                var testRange = range.cloneRange();
                                testRange.collapse(true);
                            } catch(e) {
                                // Range is invalid, try next node
                                continue;
                            }
                            
                            var rect = range.getBoundingClientRect();
                            if (rect.width === 0 && rect.height === 0) {
                                // Range is collapsed or invalid, try next
                                continue;
                            }
                            
                            // Scroll to the found text
                            window.scrollTo({top: window.scrollY + rect.top - 100, behavior: 'smooth'});
                            
                            // Try to highlight, but handle cases where it might fail (e.g., text inside links)
                            try {
                                var span = document.createElement('span');
                                span.style.backgroundColor = '#ffff99';
                                span.style.padding = '2px';
                                span.style.borderRadius = '2px';
                                
                                // Try to surround the range with a span
                                try {
                                    range.surroundContents(span);
                                    
                                    // Remove highlight after 3 seconds
                                    setTimeout(function() {
                                        if (span && span.parentNode) {
                                            var parentNode = span.parentNode;
                                            var textContent = span.textContent;
                                            parentNode.replaceChild(document.createTextNode(textContent), span);
                                            if (parentNode.parentNode) {
                                                parentNode.normalize();
                                            }
                                        }
                                    }, 3000);
                                } catch(surroundError) {
                                    // Can't surround (e.g., text spans link boundaries), highlight the parent element instead
                                    var parent = textNode.parentNode;
                                    if (parent && parent !== element && parent.tagName !== 'BODY' && parent.tagName !== 'HTML') {
                                        var originalBg = parent.style.backgroundColor;
                                        var originalTransition = parent.style.transition;
                                        parent.style.backgroundColor = '#ffff99';
                                        parent.style.transition = 'background-color 0.3s';
                                        parent.style.borderRadius = '2px';
                                        setTimeout(function() {
                                            if (parent && parent.style) {
                                                parent.style.backgroundColor = originalBg;
                                                setTimeout(function() {
                                                    parent.style.transition = originalTransition;
                                                }, 300);
                                            }
                                        }, 3000);
                                    }
                                }
                            } catch(e) {
                                // If highlighting fails, at least we scrolled to the location
                                console.log('Could not highlight text:', e);
                            }
                            
                            return true;
                        } catch(e) {
                            // If range creation fails, try to scroll to the text node's parent
                            try {
                                var parent = textNode.parentNode;
                                if (parent) {
                                    var parentRect = parent.getBoundingClientRect();
                                    window.scrollTo({top: window.scrollY + parentRect.top - 100, behavior: 'smooth'});
                                    return true;
                                }
                            } catch(e2) {
                                // Fallback: scroll to element
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
                var maxRetries = 15; // Increased retries for slow-loading content
                
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
                    if (observer) observer.disconnect();
                    return;
                }
                if (!searchText) {
                    if (observer) observer.disconnect();
                    return;
                }
                
                var hash = window.location.hash;
                var targetModuleId = moduleId || (hash ? hash.match(/^#module-(\\d+)$/) : null);
                if (targetModuleId && !moduleId) targetModuleId = targetModuleId[1];
                // Validate moduleId is numeric only
                if (targetModuleId && !/^\\d+$/.test(targetModuleId)) {
                    targetModuleId = null;
                }
                
                var targetElement = null;
                if (targetModuleId) {
                    targetElement = document.getElementById('module-' + targetModuleId);
                }
                
                // If target element doesn't exist yet and we haven't exceeded retries, wait and retry
                if (targetModuleId && !targetElement && retryCount < maxRetries) {
                    setTimeout(function() {
                        initScroll(retryCount + 1);
                    }, 500); // Increased delay
                    return;
                }
                
                // Try to find and scroll to the text
                var found = false;
                if (targetElement) {
                    found = scrollToText(targetElement, searchText);
                    if (!found) {
                        // Text not found in target element, scroll to element anyway
                        try {
                            window.scrollTo({top: targetElement.getBoundingClientRect().top + window.scrollY - 100, behavior: 'smooth'});
                        } catch(e) {
                            // Element might not be ready yet
                        }
                    }
                } else {
                    // No specific target, search in whole page
                    found = scrollToText(document.body, searchText);
                }
                
                // If text not found and we haven't exceeded retries, try again (content might still be loading)
                if (!found && retryCount < maxRetries) {
                    setTimeout(function() {
                        initScroll(retryCount + 1);
                    }, 500); // Increased delay
                    return;
                }
                
                // Clean up sessionStorage after successful attempt
                if (found) {
                    sessionStorage.removeItem('coursesearch_highlight');
                    sessionStorage.removeItem('coursesearch_moduleId');
                    sessionStorage.removeItem('coursesearch_timestamp');
                    if (observer) observer.disconnect();
                    highlightAttempted = true;
                } else if (retryCount >= maxRetries) {
                    // Failed after all retries, but keep sessionStorage for page reload
                    // Don't clean up - let user reload to try again
                    if (observer) observer.disconnect();
                    highlightAttempted = true;
                }
            }
            
            // Wait for both DOM and all resources (images, etc.) to be loaded
            function startInitScroll() {
                // Use MutationObserver to watch for dynamically added content
                if (typeof MutationObserver !== 'undefined' && !observer) {
                    observer = new MutationObserver(function(mutations) {
                        if (!highlightAttempted) {
                            // Content was added, try highlighting again
                            setTimeout(function() {
                                initScroll(0);
                            }, 200);
                        }
                    });
                    
                    // Start observing when DOM is ready
                    if (document.body) {
                        observer.observe(document.body, {
                            childList: true,
                            subtree: true,
                            characterData: true
                        });
                    }
                }
                
                // Initial attempt after page load
                function attemptHighlight() {
                    setTimeout(function() {
                        if (!highlightAttempted) {
                            initScroll(0);
                        }
                    }, 800); // Longer initial delay to ensure content is ready
                }
                
                if (document.readyState === 'complete') {
                    // Page fully loaded, wait a bit more for dynamic content
                    attemptHighlight();
                } else if (document.readyState === 'loading') {
                    // Wait for DOMContentLoaded first, then window.load
                    document.addEventListener('DOMContentLoaded', function() {
                        window.addEventListener('load', function() {
                            attemptHighlight();
                        });
                        // Also try after DOMContentLoaded in case load event is slow
                        attemptHighlight();
                    });
                } else {
                    // DOM is ready but page might still be loading
                    window.addEventListener('load', function() {
                        attemptHighlight();
                    });
                    // Also try immediately in case load already fired
                    attemptHighlight();
                }
            }
            
            startInitScroll();
        };
        
        // Also try to inject it into the next page by storing it
        // The course page will need to check for this and execute it
        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.setItem('coursesearch_shouldScroll', 'true');
        }
        
        // For immediate execution on this page, call the function directly
        // This replaces the dangerous eval() approach
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
    ";
    echo $js;
}

// Finish the page.
echo $OUTPUT->footer();
