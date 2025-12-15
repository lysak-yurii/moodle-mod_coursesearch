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
 * Scroll to highlighted text in course modules
 *
 * @module     mod_coursesearch/scrolltohighlight
 * @copyright  2025
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    'use strict';

    // console.log('[CourseSearch] AMD module loaded');

    // Flag to prevent multiple executions
    let hasHighlighted = false;

    /**
     * Expand Bootstrap accordion/collapse if text is found inside a collapsed section
     * Returns a promise that resolves when accordion is expanded (or immediately if not needed)
     * @param {string} searchText The search text to look for
     * @return {Promise} Promise that resolves when accordion is expanded
     */
    function expandAccordionIfNeeded(searchText) {
        return new Promise(function(resolve) {
            const searchLower = searchText.toLowerCase();

            // Find all collapsed sections (Bootstrap 4 uses .collapse:not(.show))
            const collapsedSections = document.querySelectorAll('.collapse:not(.show)');
            // console.log('[CourseSearch] Found', collapsedSections.length, 'collapsed sections');

            let foundInCollapsed = null;
            let triggerButton = null;

            // Check each collapsed section for the search text
            for (let i = 0; i < collapsedSections.length; i++) {
                const section = collapsedSections[i];
                const textContent = section.textContent.toLowerCase();

                if (textContent.indexOf(searchLower) !== -1) {
                    // console.log('[CourseSearch] Found text in collapsed section:', section.id);
                    foundInCollapsed = section;

                    // Find the trigger button for this collapse
                    // It could be a button with data-target="#id" or data-bs-target="#id"
                    const sectionId = section.id;
                    if (sectionId) {
                        triggerButton = document.querySelector('[data-target="#' + sectionId + '"]') ||
                                       document.querySelector('[data-bs-target="#' + sectionId + '"]') ||
                                       document.querySelector('[href="#' + sectionId + '"]');
                    }

                    // Also check aria-controls
                    if (!triggerButton && sectionId) {
                        triggerButton = document.querySelector('[aria-controls="' + sectionId + '"]');
                    }

                    break;
                }
            }

            if (foundInCollapsed && triggerButton) {
                // console.log('[CourseSearch] Expanding accordion section');

                // Listen for the collapse to finish expanding
                $(foundInCollapsed).one('shown.bs.collapse', function() {
                    // console.log('[CourseSearch] Accordion expanded, proceeding with highlight');
                    setTimeout(resolve, 100); // Small delay to ensure DOM is updated
                });

                // Click the trigger to expand
                triggerButton.click();

                // Fallback timeout in case the event doesn't fire
                setTimeout(function() {
                    resolve();
                }, 1000);
            } else if (foundInCollapsed) {
                // No trigger found, try to expand using Bootstrap's collapse API directly
                // console.log('[CourseSearch] No trigger found, trying direct collapse expansion');
                $(foundInCollapsed).collapse('show');

                $(foundInCollapsed).one('shown.bs.collapse', function() {
                    setTimeout(resolve, 100);
                });

                // Fallback timeout
                setTimeout(function() {
                    resolve();
                }, 1000);
            } else {
                // Text not in collapsed section, resolve immediately
                resolve();
            }
        });
    }

    /**
     * Find text within an element and scroll to it
     * @param {HTMLElement} element The element to search within
     * @param {string} searchText The text to search for
     * @return {boolean} True if text was found and scrolled to
     */
    function scrollToText(element, searchText) {
        // console.log('[CourseSearch] scrollToText called with:', searchText);
        if (!element || !searchText) {
            return false;
        }

        // Get all text nodes within the element, skipping hidden elements
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    // Skip empty text nodes
                    if (!node.textContent.trim()) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Skip nodes inside hidden elements (sr-only, visually-hidden, etc.)
                    let parent = node.parentElement;
                    while (parent && parent !== element) {
                        const classList = parent.classList;
                        const style = window.getComputedStyle(parent);
                        // Skip screen-reader only elements and hidden elements
                        if (classList.contains('sr-only') ||
                            classList.contains('visually-hidden') ||
                            classList.contains('hidden') ||
                            style.display === 'none' ||
                            style.visibility === 'hidden' ||
                            (style.position === 'absolute' && style.clip === 'rect(0px, 0px, 0px, 0px)')) {
                            // console.log('[CourseSearch] Skipping hidden element:', parent);
                            return NodeFilter.FILTER_REJECT;
                        }
                        parent = parent.parentElement;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            },
            false
        );

        let node;
        const textNodes = [];
        while ((node = walker.nextNode()) !== null) {
            textNodes.push(node);
        }
        // console.log('[CourseSearch] Found', textNodes.length, 'visible text nodes');

        // Debug: show content of each text node
        for (let i = 0; i < textNodes.length; i++) {
            // console.log('[CourseSearch] Node', i, ':', JSON.stringify(textNodes[i].textContent));
        }

        // Search for the text (case-insensitive)
        const searchLower = searchText.toLowerCase();
        // console.log('[CourseSearch] Searching for (lowercase):', searchLower);

        // First, try to find exact match in a single text node
        for (let i = 0; i < textNodes.length; i++) {
            const text = textNodes[i].textContent.toLowerCase();
            const index = text.indexOf(searchLower);
            if (index !== -1) {
                // console.log('[CourseSearch] Found exact match in single text node');
                // Continue with existing logic below
            }
        }

        // If not found in single node, try to find text that spans multiple nodes
        // Build combined text from all nodes to check if text exists across nodes
        let combinedText = '';
        for (let i = 0; i < textNodes.length; i++) {
            combinedText += textNodes[i].textContent;
        }
        // console.log('[CourseSearch] Combined text:', JSON.stringify(combinedText));
        // Normalize whitespace: replace &nbsp; (char 160) and other whitespace with regular space
        const normalizedCombined = combinedText.replace(/[\u00A0\s]+/g, ' ').toLowerCase();
        const normalizedSearch = searchLower.replace(/[\u00A0\s]+/g, ' ');
        // console.log('[CourseSearch] Normalized combined:', normalizedCombined);
        // console.log('[CourseSearch] Normalized search:', normalizedSearch);
        const combinedIndex = normalizedCombined.indexOf(normalizedSearch);
        // console.log('[CourseSearch] Combined index:', combinedIndex);

        if (combinedIndex !== -1) {
            // console.log('[CourseSearch] Text found in combined content at index', combinedIndex);

            // Find which text node contains the start of the match by tracking character positions
            let charCount = 0;
            let foundNodeIndex = -1;

            // First, try to find exact match in a single text node
            for (let i = 0; i < textNodes.length; i++) {
                const nodeText = textNodes[i].textContent;
                const nodeTextLower = nodeText.replace(/[\u00A0\s]+/g, ' ').toLowerCase();
                if (nodeTextLower.indexOf(normalizedSearch) !== -1) {
                    foundNodeIndex = i;
                    // console.log('[CourseSearch] Found exact match in node', i, ':', nodeText.substring(0, 50));
                    break;
                }
            }

            // If no exact match in single node, find the node that contains the START of the match
            // For multi-node spanning text, find the node containing the first word of the search
            if (foundNodeIndex === -1) {
                // Get the first word of the search to help locate the correct starting node
                const firstWord = normalizedSearch.split(' ')[0];
                // console.log('[CourseSearch] First word of search:', firstWord);

                // Search for a node that contains the first word AND is at approximately the right position
                charCount = 0;
                const nodePositions = [];
                for (let i = 0; i < textNodes.length; i++) {
                    const nodeText = textNodes[i].textContent;
                    const normalizedNode = nodeText.replace(/[\u00A0\s]+/g, ' ').toLowerCase();
                    nodePositions.push({
                        nodeIndex: i,
                        start: charCount,
                        end: charCount + normalizedNode.length,
                        text: normalizedNode,
                        original: nodeText
                    });
                    charCount += normalizedNode.length;
                }

                // First, try to find a node containing the first word that's at the right position
                for (let i = 0; i < nodePositions.length; i++) {
                    const np = nodePositions[i];
                    // Check if this node overlaps with the match position
                    if (np.end > combinedIndex && np.start <= combinedIndex + normalizedSearch.length) {
                        // Check if this node contains the first word
                        if (np.text.indexOf(firstWord) !== -1) {
                            foundNodeIndex = i;
                            // console.log('[CourseSearch] Found first word "' + firstWord + '" in node', i);
                            // console.log('[CourseSearch] Node text:', np.original.substring(0, 80));
                            break;
                        }
                    }
                }

                // Fallback: find which node contains position combinedIndex (start of match)
                if (foundNodeIndex === -1) {
                    for (let i = 0; i < nodePositions.length; i++) {
                        if (combinedIndex >= nodePositions[i].start && combinedIndex < nodePositions[i].end) {
                            foundNodeIndex = i;
                            // console.log('[CourseSearch] Fallback: Match starts in node', i);
                            // console.log('[CourseSearch] Node range:', nodePositions[i].start, '-', nodePositions[i].end);
                            // console.log('[CourseSearch] Node text:', textNodes[i].textContent.substring(0, 50));
                            break;
                        }
                    }
                }
            }

            if (foundNodeIndex !== -1) {
                // console.log('[CourseSearch] Found matching word, will highlight parent element');
                // Find a good parent to highlight
                let parent = textNodes[foundNodeIndex].parentElement;
                // console.log('[CourseSearch] Starting parent:', parent ? parent.tagName : 'null');
                while (parent && parent !== element && parent !== document.body) {
                    const tagName = parent.tagName.toUpperCase();
                    // console.log('[CourseSearch] Checking parent:', tagName);
                    if (['P', 'DIV', 'LI', 'TD', 'TH', 'BLOCKQUOTE', 'ARTICLE', 'SECTION']
                        .includes(tagName)) {
                        break;
                    }
                    parent = parent.parentElement;
                }
                // console.log('[CourseSearch] Final parent:', parent ? parent.tagName : 'null',
                //     'element:', element ? element.tagName : 'null');
                // console.log('[CourseSearch] parent !== element:', parent !== element,
                //     'parent !== body:', parent !== document.body);
                if (parent && parent !== element && parent !== document.body) {
                    // Scroll to element
                    const rect = parent.getBoundingClientRect();
                    const scrollY = window.scrollY + rect.top - 100;
                    window.scrollTo({top: scrollY, behavior: 'smooth'});

                    // Highlight the parent
                    // console.log('[CourseSearch] Highlighting container:', parent.tagName, parent);
                    const originalBg = parent.style.backgroundColor;
                    // console.log('[CourseSearch] Original background:', originalBg);
                    parent.style.setProperty('background-color', '#ffff99', 'important');
                    // console.log('[CourseSearch] After setting background:', parent.style.backgroundColor);
                    setTimeout(function() {
                        if (originalBg) { parent.style.setProperty('background-color', originalBg); }
                        else { parent.style.removeProperty('background-color'); }
                    }, 3000);
                    return true;
                } else {
                    // console.log('[CourseSearch] Parent check failed, not highlighting');
                }
            }
        }

        // Original single-node search
        for (let i = 0; i < textNodes.length; i++) {
            const text = textNodes[i].textContent.toLowerCase();
            const index = text.indexOf(searchLower);
            if (index !== -1) {
                // Found the text, now we need to scroll to it
                const range = document.createRange();
                const textNode = textNodes[i];

                // Find the actual position in the original text
                const originalText = textNode.textContent;
                const originalIndex = originalText.toLowerCase().indexOf(searchLower);

                if (originalIndex !== -1) {
                    try {
                        range.setStart(textNode, originalIndex);
                        range.setEnd(textNode, originalIndex + searchText.length);

                        // Get the bounding rectangle
                        const rect = range.getBoundingClientRect();

                        // Scroll to the position
                        const scrollY = window.scrollY + rect.top - 100; // 100px offset from top
                        window.scrollTo({
                            top: scrollY,
                            behavior: 'smooth'
                        });

                        // Highlight the text temporarily
                        const span = document.createElement('span');
                        span.style.setProperty('background-color', '#ffff99', 'important');
                        span.style.setProperty('padding', '2px', 'important');
                        span.style.setProperty('border-radius', '2px', 'important');
                        span.style.setProperty('color', 'inherit', 'important');
                        span.style.setProperty('display', 'inline', 'important');
                        span.className = 'coursesearch-highlight-temp';

                        let highlighted = false;
                        // console.log('[CourseSearch] Found text, attempting to highlight');
                        try {
                            // Check if range is valid for surroundContents
                            // It fails if the range partially selects a non-Text node
                            const canSurround = range.startContainer === range.endContainer ||
                                (range.startContainer.nodeType === Node.TEXT_NODE &&
                                 range.endContainer.nodeType === Node.TEXT_NODE);

                            // console.log('[CourseSearch] canSurround:', canSurround);
                            if (canSurround) {
                                range.surroundContents(span);
                                highlighted = true;
                                // console.log('[CourseSearch] Direct highlight SUCCESS');
                                // Remove highlight after 3 seconds
                                setTimeout(function() {
                                    if (span.parentNode) {
                                        const parent = span.parentNode;
                                        parent.replaceChild(document.createTextNode(span.textContent), span);
                                        parent.normalize();
                                    }
                                }, 3000);
                            }
                        } catch (e) {
                            // surroundContents failed
                            highlighted = false;
                        }

                        // Fallback: highlight the parent element if direct highlighting failed
                        if (!highlighted) {
                            // console.log('[CourseSearch] Using fallback parent highlighting');
                            // Find the best parent element to highlight
                            let parent = textNode.parentElement;

                            // Skip very small containers, find a meaningful parent
                            while (parent && parent !== element && parent !== document.body) {
                                const tagName = parent.tagName.toUpperCase();
                                // Stop at block-level or meaningful inline elements
                                if (['P', 'DIV', 'SPAN', 'A', 'LI', 'TD', 'TH', 'LABEL',
                                     'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
                                     'STRONG', 'EM', 'B', 'I', 'U'].includes(tagName)) {
                                    break;
                                }
                                parent = parent.parentElement;
                            }

                            if (parent && parent !== element && parent !== document.body && parent !== document.documentElement) {
                                // console.log('[CourseSearch] Highlighting parent:', parent.tagName, parent);
                                // Save original styles
                                const originalBg = parent.style.backgroundColor;

                                // Apply highlight with !important to override theme styles
                                parent.style.setProperty('background-color', '#ffff99', 'important');

                                setTimeout(function() {
                                    // Restore original styles
                                    if (originalBg) {
                                        parent.style.setProperty('background-color', originalBg);
                                    } else {
                                        parent.style.removeProperty('background-color');
                                    }
                                }, 3000);
                            }
                        }

                        return true;
                    } catch (e) {
                        // If range operations fail, try alternative method
                        // Just scroll to the element
                        const elementTop = element.getBoundingClientRect().top + window.scrollY;
                        window.scrollTo({
                            top: elementTop - 100,
                            behavior: 'smooth'
                        });
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Initialize scrolling to highlighted text
     */
    function init() {
        // console.log('[CourseSearch] init() called, hasHighlighted:', hasHighlighted);

        // Prevent multiple executions
        if (hasHighlighted) {
            // console.log('[CourseSearch] Already highlighted, skipping');
            return;
        }

        // Check for highlight parameter in URL
        const urlParams = new URLSearchParams(window.location.search);
        let highlightText = urlParams.get('highlight');
        // console.log('[CourseSearch] highlight from URL:', highlightText);

        // If not in URL, check sessionStorage (set by coursesearch module)
        if (!highlightText && typeof sessionStorage !== 'undefined') {
            highlightText = sessionStorage.getItem('coursesearch_highlight');
            // console.log('[CourseSearch] highlight from sessionStorage:', highlightText);
            if (highlightText) {
                // Clear it after use
                sessionStorage.removeItem('coursesearch_highlight');
            }
        }

        if (!highlightText) {
            // console.log('[CourseSearch] No highlight text found, exiting');
            return;
        }

        // Mark as highlighted to prevent re-runs
        hasHighlighted = true;

        // Decode the text
        const searchText = decodeURIComponent(highlightText).trim();
        if (!searchText) {
            return;
        }

        // Wait for page to be fully loaded
        $(document).ready(function() {
            // Small delay to ensure all content is rendered
            setTimeout(function() {
                // First, expand any accordion that contains the search text
                expandAccordionIfNeeded(searchText).then(function() {
                    // Check if we have an anchor in the URL
                    const hash = window.location.hash;
                    if (hash) {
                        // Extract module ID from hash (format: #module-123)
                        const match = hash.match(/^#module-(\d+)$/);
                        if (match) {
                            const moduleId = match[1];
                            const moduleElement = document.getElementById('module-' + moduleId);

                            if (moduleElement) {
                                // Try to find and scroll to the text within this module
                                if (!scrollToText(moduleElement, searchText)) {
                                    // If text not found, just scroll to the module
                                    const elementTop = moduleElement.getBoundingClientRect().top + window.scrollY;
                                    window.scrollTo({
                                        top: elementTop - 100,
                                        behavior: 'smooth'
                                    });
                                }
                            }
                        } else {
                            // No specific module anchor, search in the whole page
                            scrollToText(document.body, searchText);
                        }
                    } else {
                        // No anchor, search in the whole page
                        scrollToText(document.body, searchText);
                    }
                });
            }, 500); // 500ms delay to ensure content is loaded
        });
    }

    /**
     * Auto-initialize on course view pages
     * Check if we're on a course page and if highlight parameter exists
     */
    function autoInit() {
        // Only run on course view pages
        if (window.location.pathname.indexOf('/course/view.php') === -1) {
            return;
        }

        // Check for highlight in URL or sessionStorage
        const urlParams = new URLSearchParams(window.location.search);
        let highlightText = urlParams.get('highlight');

        if (!highlightText && typeof sessionStorage !== 'undefined') {
            highlightText = sessionStorage.getItem('coursesearch_highlight');
        }

        if (highlightText) {
            init();
        }
    }
    
    // Auto-initialize if highlight parameter is present
    // This allows the script to run even if not explicitly called
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(autoInit, 500);
        });
    } else {
        // Page already loaded, run immediately
        setTimeout(autoInit, 500);
    }

    return {
        init: init
    };
});

