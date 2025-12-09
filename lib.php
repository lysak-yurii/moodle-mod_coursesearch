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
 * Library of interface functions and constants for module coursesearch
 *
 * @package    mod_coursesearch
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * List of features supported in Course Search module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function coursesearch_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_MOD_PURPOSE:             return MOD_PURPOSE_CONTENT;
        default: return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function coursesearch_reset_userdata($data) {
    return array();
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * @return array
 */
function coursesearch_get_view_actions() {
    return array('view', 'search');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * @return array
 */
function coursesearch_get_post_actions() {
    return array();
}

/**
 * Add coursesearch instance.
 * @param stdClass $data
 * @param mod_coursesearch_mod_form $mform
 * @return int new coursesearch instance id
 */
function coursesearch_add_instance($data, $mform = null) {
    global $DB;
    
    $cmid = $data->coursemodule;
    
    $data->timemodified = time();
    
    // You might want to add more options here
    $data->id = $DB->insert_record('coursesearch', $data);
    
    // We need to use context now, so we need to make sure all needed info is already in db
    $DB->set_field('course_modules', 'instance', $data->id, array('id' => $cmid));
    
    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'coursesearch', $data->id, $completiontimeexpected);
    
    return $data->id;
}

/**
 * Update coursesearch instance.
 * @param object $data
 * @param object $mform
 * @return bool true
 */
function coursesearch_update_instance($data, $mform) {
    global $DB;
    
    $cmid = $data->coursemodule;
    
    $data->timemodified = time();
    $data->id = $data->instance;
    
    $DB->update_record('coursesearch', $data);
    
    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'coursesearch', $data->id, $completiontimeexpected);
    
    return true;
}

/**
 * Delete coursesearch instance.
 * @param int $id
 * @return bool true
 */
function coursesearch_delete_instance($id) {
    global $DB;
    
    if (!$coursesearch = $DB->get_record('coursesearch', array('id' => $id))) {
        return false;
    }
    
    $cm = get_coursemodule_from_instance('coursesearch', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'coursesearch', $id, null);
    
    $DB->delete_records('coursesearch', array('id' => $coursesearch->id));
    
    return true;
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info info
 */
function coursesearch_get_coursemodule_info($coursemodule) {
    global $DB, $PAGE;
    
    if (!$coursesearch = $DB->get_record('coursesearch', array('id' => $coursemodule->instance),
            'id, name, intro, introformat, embedded')) {
        return null;
    }
    
    $info = new cached_cm_info();
    $info->name = $coursesearch->name;
    
    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('coursesearch', $coursesearch, $coursemodule->id, false);
    }
    
    // Add script to course pages that checks sessionStorage for highlight data.
    // Use a broad match so it also runs on custom course formats (e.g., grid),
    // which set pagetype values like 'course-view-grid'.
    if (strpos($PAGE->pagetype, 'course-view') === 0) {
        // Ensure the AMD helper is loaded so highlight works even when inline
        // script injection is blocked or pagetype varies between formats.
        $PAGE->requires->js_call_amd('mod_coursesearch/scrolltohighlight', 'init');
        $scrollScript = "
        <script>
        (function() {
            if (typeof sessionStorage === 'undefined') return;
            var shouldScroll = sessionStorage.getItem('coursesearch_shouldScroll');
            if (!shouldScroll) return;
            
            var highlight = sessionStorage.getItem('coursesearch_highlight');
            var moduleId = sessionStorage.getItem('coursesearch_moduleId');
            var timestamp = sessionStorage.getItem('coursesearch_timestamp');
            if (!highlight) {
                sessionStorage.removeItem('coursesearch_shouldScroll');
                return;
            }
            // Check if timestamp is recent (within 10 seconds)
            if (timestamp && Date.now() - parseInt(timestamp) > 10000) {
                sessionStorage.removeItem('coursesearch_highlight');
                sessionStorage.removeItem('coursesearch_moduleId');
                sessionStorage.removeItem('coursesearch_timestamp');
                sessionStorage.removeItem('coursesearch_shouldScroll');
                return;
            }
            
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
                    sessionStorage.removeItem('coursesearch_shouldScroll');
                    if (observer) observer.disconnect();
                    return;
                }
                if (!searchText) {
                    sessionStorage.removeItem('coursesearch_shouldScroll');
                    if (observer) observer.disconnect();
                    return;
                }
                
                var hash = window.location.hash;
                var targetModuleId = moduleId || (hash ? hash.match(/^#module-(\\d+)$/) : null);
                if (targetModuleId && !moduleId) targetModuleId = targetModuleId[1];
                // Validate moduleId is numeric only to prevent XSS
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
                    sessionStorage.removeItem('coursesearch_shouldScroll');
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
        })();
        </script>
        ";
        // Use Moodle's proper JavaScript API to add the script to the page
        // This ensures the script is properly included and avoids security issues with global variables
        $PAGE->requires->js_init_code($scrollScript, true);
    }
    
    // If the search bar is set to be embedded, tell the course renderer to display it inline
    if (!empty($coursesearch->embedded)) {
        $info->content = $info->content ?? '';
        
        // Set a custom flag to indicate this module should be rendered inline
        $info->customdata = array('embedded' => true);
        
        // This is the key part - tell Moodle to use our custom renderer
        $info->content_items_online = true;
        $info->content_online = true;
        $info->onclick_online = true;
    }
    
    return $info;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $coursesearch     coursesearch object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 */
function coursesearch_view($coursesearch, $course, $cm, $context) {
    
    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $coursesearch->id
    );
    
    $event = \mod_coursesearch\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('coursesearch', $coursesearch);
    $event->trigger();
    
    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Serves the coursesearch course format content.
 *
 * @param cm_info $cm Course module object
 * @param array $displayoptions Display options
 * @return string HTML to display
 */
function coursesearch_cm_info_view(cm_info $cm) {
    global $CFG, $PAGE, $DB;
    
    // Only continue if the module is set to be embedded
    if (empty($cm->customdata['embedded'])) {
        return '';
    }
    
    // Get the coursesearch record
    $coursesearch = $DB->get_record('coursesearch', array('id' => $cm->instance), '*', MUST_EXIST);
    
    // Include renderer file
    require_once($CFG->dirroot . '/mod/coursesearch/renderer.php');
    
    // Get the renderer
    $renderer = $PAGE->get_renderer('mod_coursesearch');
    
    // Render the embedded search form
    return $renderer->render_embedded_search_form($coursesearch, $cm);
}

/**
 * Overwrites the content output for a course module
 *
 * This function is used to display the embedded search form directly in the course page
 *
 * @param cm_info $cm Course module info object
 */
function coursesearch_cm_info_dynamic(cm_info $cm) {
    global $CFG, $DB, $PAGE;
    
    // Note: JavaScript for scrolling is now handled client-side via sessionStorage
    // No need to load AMD modules here
    
    // Check if the module should be embedded
    $coursesearch = $DB->get_record('coursesearch', array('id' => $cm->instance), 'embedded');
    
    if (!$coursesearch || empty($coursesearch->embedded)) {
        return;
    }
    
    // Include the renderer
    require_once($CFG->dirroot . '/mod/coursesearch/renderer.php');
    
    // Get the full coursesearch record
    $fullcoursesearch = $DB->get_record('coursesearch', array('id' => $cm->instance), '*', MUST_EXIST);
    
    // Get the renderer
    $renderer = $PAGE->get_renderer('mod_coursesearch');
    
    // Generate the embedded search form
    $content = $renderer->render_embedded_search_form($fullcoursesearch, $cm);
    
    // Set the content to be displayed in the course page
    $cm->set_content($content);
    
    // Hide the view link since the content is already embedded
    $cm->set_no_view_link();
}

/**
 * Inject highlighting JavaScript on course pages
 * This is called by Moodle before the footer is rendered on every page
 */
function coursesearch_before_footer() {
    global $PAGE;

    // Only run on course view pages where highlighting might be needed
    if (strpos($PAGE->pagetype, 'course-view') !== 0) {
        return;
    }

    // Load the AMD module for highlighting
    // The module itself will check for highlight data and only execute if present
    // This is lightweight - AMD modules are lazy-loaded by Moodle
    $PAGE->requires->js_call_amd('mod_coursesearch/scrolltohighlight', 'init');
}

