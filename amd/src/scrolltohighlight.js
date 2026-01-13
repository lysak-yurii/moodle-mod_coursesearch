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

/* eslint-disable max-depth */
/* eslint-disable complexity */
/* eslint-disable promise/catch-or-return */
/* eslint-disable promise/always-return */

define(['jquery'], function($) {
    'use strict';

    // Flag to prevent multiple executions.
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

            // Find all collapsed sections (Bootstrap 4 uses .collapse:not(.show)).
            const collapsedSections = document.querySelectorAll('.collapse:not(.show)');

            let foundInCollapsed = null;
            let triggerButton = null;

            // Check each collapsed section for the search text.
            for (let i = 0; i < collapsedSections.length; i++) {
                const section = collapsedSections[i];
                const textContent = section.textContent.toLowerCase();

                if (textContent.indexOf(searchLower) !== -1) {
                    foundInCollapsed = section;

                    // Find the trigger button for this collapse.
                    // It could be a button with data-target="#id" or data-bs-target="#id".
                    const sectionId = section.id;
                    if (sectionId) {
                        triggerButton = document.querySelector('[data-target="#' + sectionId + '"]') ||
                                       document.querySelector('[data-bs-target="#' + sectionId + '"]') ||
                                       document.querySelector('[href="#' + sectionId + '"]');
                    }

                    // Also check aria-controls.
                    if (!triggerButton && sectionId) {
                        triggerButton = document.querySelector('[aria-controls="' + sectionId + '"]');
                    }

                    break;
                }
            }

            if (foundInCollapsed && triggerButton) {
                // Listen for the collapse to finish expanding.
                $(foundInCollapsed).one('shown.bs.collapse', function() {
                    // Small delay to ensure DOM is updated.
                    setTimeout(resolve, 100);
                });

                // Click the trigger to expand.
                triggerButton.click();

                // Fallback timeout in case the event doesn't fire.
                setTimeout(function() {
                    resolve();
                }, 1000);
            } else if (foundInCollapsed) {
                // No trigger found, try to expand using Bootstrap's collapse API directly.
                $(foundInCollapsed).collapse('show');

                $(foundInCollapsed).one('shown.bs.collapse', function() {
                    setTimeout(resolve, 100);
                });

                // Fallback timeout.
                setTimeout(function() {
                    resolve();
                }, 1000);
            } else {
                // Text not in collapsed section, resolve immediately.
                resolve();
            }
        });
    }

    /**
     * Check if element is hidden
     * @param {HTMLElement} el Element to check
     * @param {HTMLElement} boundary Boundary element
     * @return {boolean} True if hidden
     */
    function isElementHidden(el, boundary) {
        let parent = el;
        while (parent && parent !== boundary) {
            const classList = parent.classList;
            const style = window.getComputedStyle(parent);
            // Skip screen-reader only elements and hidden elements.
            if (classList.contains('sr-only') ||
                classList.contains('visually-hidden') ||
                classList.contains('hidden') ||
                style.display === 'none' ||
                style.visibility === 'hidden' ||
                (style.position === 'absolute' && style.clip === 'rect(0px, 0px, 0px, 0px)')) {
                return true;
            }
            parent = parent.parentElement;
        }
        return false;
    }

    /**
     * Get all visible text nodes within element
     * @param {HTMLElement} element Element to search
     * @return {Array} Array of text nodes
     */
    function getVisibleTextNodes(element) {
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    // Skip empty text nodes.
                    if (!node.textContent.trim()) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Skip nodes inside hidden elements.
                    if (isElementHidden(node.parentElement, element)) {
                        return NodeFilter.FILTER_REJECT;
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
        return textNodes;
    }

    /**
     * Apply temporary highlight to element
     * @param {HTMLElement} el Element to highlight
     */
    function applyHighlight(el) {
        const originalBg = el.style.backgroundColor;
        el.style.setProperty('background-color', '#ffff99', 'important');
        setTimeout(function() {
            if (originalBg) {
                el.style.setProperty('background-color', originalBg);
            } else {
                el.style.removeProperty('background-color');
            }
        }, 3000);
    }

    /**
     * Find suitable parent element for highlighting
     * @param {Node} node Starting node
     * @param {HTMLElement} boundary Boundary element
     * @param {Array} validTags Valid tag names
     * @return {HTMLElement|null} Parent element or null
     */
    function findHighlightParent(node, boundary, validTags) {
        let parent = node.parentElement;
        while (parent && parent !== boundary && parent !== document.body) {
            const tagName = parent.tagName.toUpperCase();
            if (validTags.includes(tagName)) {
                break;
            }
            parent = parent.parentElement;
        }
        if (parent && parent !== boundary && parent !== document.body && parent !== document.documentElement) {
            return parent;
        }
        return null;
    }

    /**
     * Find text within an element and scroll to it
     * @param {HTMLElement} element The element to search within
     * @param {string} searchText The text to search for
     * @return {boolean} True if text was found and scrolled to
     */
    function scrollToText(element, searchText) {
        if (!element || !searchText) {
            return false;
        }

        const textNodes = getVisibleTextNodes(element);
        const searchLower = searchText.toLowerCase();
        const normalizedSearch = searchLower.replace(/[\u00A0\s]+/g, ' ');

        // Build combined text from all nodes.
        let combinedText = '';
        for (let i = 0; i < textNodes.length; i++) {
            combinedText += textNodes[i].textContent;
        }
        const normalizedCombined = combinedText.replace(/[\u00A0\s]+/g, ' ').toLowerCase();
        const combinedIndex = normalizedCombined.indexOf(normalizedSearch);

        if (combinedIndex !== -1) {
            let foundNodeIndex = -1;

            // First, try to find exact match in a single text node.
            for (let i = 0; i < textNodes.length; i++) {
                const nodeText = textNodes[i].textContent;
                const nodeTextLower = nodeText.replace(/[\u00A0\s]+/g, ' ').toLowerCase();
                if (nodeTextLower.indexOf(normalizedSearch) !== -1) {
                    foundNodeIndex = i;
                    break;
                }
            }

            // If no exact match in single node, find the node that contains the START of the match.
            if (foundNodeIndex === -1) {
                const firstWord = normalizedSearch.split(' ')[0];
                let charCount = 0;
                const nodePositions = [];
                for (let i = 0; i < textNodes.length; i++) {
                    const nodeText = textNodes[i].textContent;
                    const normalizedNode = nodeText.replace(/[\u00A0\s]+/g, ' ').toLowerCase();
                    nodePositions.push({
                        nodeIndex: i,
                        start: charCount,
                        end: charCount + normalizedNode.length,
                        text: normalizedNode
                    });
                    charCount += normalizedNode.length;
                }

                // Try to find a node containing the first word at the right position.
                for (let i = 0; i < nodePositions.length; i++) {
                    const np = nodePositions[i];
                    if (np.end > combinedIndex && np.start <= combinedIndex + normalizedSearch.length) {
                        if (np.text.indexOf(firstWord) !== -1) {
                            foundNodeIndex = i;
                            break;
                        }
                    }
                }

                // Fallback: find which node contains position combinedIndex.
                if (foundNodeIndex === -1) {
                    for (let i = 0; i < nodePositions.length; i++) {
                        if (combinedIndex >= nodePositions[i].start && combinedIndex < nodePositions[i].end) {
                            foundNodeIndex = i;
                            break;
                        }
                    }
                }
            }

            if (foundNodeIndex !== -1) {
                const blockTags = ['P', 'DIV', 'LI', 'TD', 'TH', 'BLOCKQUOTE', 'ARTICLE', 'SECTION'];
                const parent = findHighlightParent(textNodes[foundNodeIndex], element, blockTags);
                if (parent) {
                    const rect = parent.getBoundingClientRect();
                    const scrollY = window.scrollY + rect.top - 100;
                    window.scrollTo({top: scrollY, behavior: 'smooth'});
                    applyHighlight(parent);
                    return true;
                }
            }
        }

        // Original single-node search.
        for (let i = 0; i < textNodes.length; i++) {
            const text = textNodes[i].textContent.toLowerCase();
            const index = text.indexOf(searchLower);
            if (index !== -1) {
                const range = document.createRange();
                const textNode = textNodes[i];
                const originalText = textNode.textContent;
                const originalIndex = originalText.toLowerCase().indexOf(searchLower);

                if (originalIndex !== -1) {
                    try {
                        range.setStart(textNode, originalIndex);
                        range.setEnd(textNode, originalIndex + searchText.length);
                        const rect = range.getBoundingClientRect();
                        const scrollY = window.scrollY + rect.top - 100;
                        window.scrollTo({top: scrollY, behavior: 'smooth'});

                        // Highlight the text temporarily.
                        const span = document.createElement('span');
                        span.style.setProperty('background-color', '#ffff99', 'important');
                        span.style.setProperty('padding', '2px', 'important');
                        span.style.setProperty('border-radius', '2px', 'important');
                        span.style.setProperty('color', 'inherit', 'important');
                        span.style.setProperty('display', 'inline', 'important');
                        span.className = 'coursesearch-highlight-temp';

                        let highlighted = false;
                        try {
                            const canSurround = range.startContainer === range.endContainer ||
                                (range.startContainer.nodeType === Node.TEXT_NODE &&
                                 range.endContainer.nodeType === Node.TEXT_NODE);

                            if (canSurround) {
                                range.surroundContents(span);
                                highlighted = true;
                                setTimeout(function() {
                                    if (span.parentNode) {
                                        const spanParent = span.parentNode;
                                        spanParent.replaceChild(document.createTextNode(span.textContent), span);
                                        spanParent.normalize();
                                    }
                                }, 3000);
                            }
                        } catch (e) {
                            highlighted = false;
                        }

                        // Fallback: highlight the parent element if direct highlighting failed.
                        if (!highlighted) {
                            const inlineTags = ['P', 'DIV', 'SPAN', 'A', 'LI', 'TD', 'TH', 'LABEL',
                                     'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'STRONG', 'EM', 'B', 'I', 'U'];
                            const parent = findHighlightParent(textNode, element, inlineTags);
                            if (parent) {
                                applyHighlight(parent);
                            }
                        }

                        return true;
                    } catch (e) {
                        // If range operations fail, just scroll to the element.
                        const elementTop = element.getBoundingClientRect().top + window.scrollY;
                        window.scrollTo({top: elementTop - 100, behavior: 'smooth'});
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Find all occurrences of search text and return array of text nodes with their positions
     * @param {HTMLElement} element The element to search within
     * @param {string} searchText The text to search for
     * @return {Array} Array of objects with textNode, startIndex, and parent element
     */
    function findAllOccurrences(element, searchText) {
        if (!element || !searchText) {
            return [];
        }

        const textNodes = getVisibleTextNodes(element);
        const searchLower = searchText.toLowerCase();
        const normalizedSearch = searchLower.replace(/[\u00A0\s]+/g, ' ');
        const occurrences = [];

        for (let i = 0; i < textNodes.length; i++) {
            const nodeText = textNodes[i].textContent;
            const nodeTextLower = nodeText.replace(/[\u00A0\s]+/g, ' ').toLowerCase();
            let startPos = 0;

            // Find all occurrences within this text node.
            let index = nodeTextLower.indexOf(normalizedSearch, startPos);
            while (index !== -1) {
                // Find the actual index in the original text (accounting for space normalization).
                const originalIndex = findOriginalIndex(nodeText, nodeTextLower, index);

                occurrences.push({
                    textNode: textNodes[i],
                    startIndex: originalIndex,
                    length: searchText.length,
                    nodeIndex: i
                });

                startPos = index + normalizedSearch.length;
                index = nodeTextLower.indexOf(normalizedSearch, startPos);
            }
        }

        return occurrences;
    }

    /**
     * Find the original index in the text accounting for space normalization
     * @param {string} original The original text
     * @param {string} normalized The normalized text (spaces collapsed)
     * @param {number} normalizedIndex Index in the normalized text
     * @return {number} Index in the original text
     */
    function findOriginalIndex(original, normalized, normalizedIndex) {
        // Simple case: if no difference in length, return same index.
        if (original.length === normalized.length) {
            return normalizedIndex;
        }

        // Map from normalized position to original position.
        let normalizedPos = 0;
        let originalPos = 0;
        const originalLower = original.toLowerCase();

        while (normalizedPos < normalizedIndex && originalPos < original.length) {
            // Check if we're at whitespace.
            if (/[\u00A0\s]/.test(originalLower[originalPos])) {
                // Skip consecutive whitespace in original.
                while (originalPos < original.length - 1 && /[\u00A0\s]/.test(originalLower[originalPos + 1])) {
                    originalPos++;
                }
            }
            originalPos++;
            normalizedPos++;
        }

        return originalPos;
    }

    /**
     * Remove all highlight spans from page
     * @param {Array} highlightedElements Array of highlight span elements
     */
    function removeAllHighlights(highlightedElements) {
        highlightedElements.forEach(function(span) {
            if (span && span.parentNode) {
                const parent = span.parentNode;
                parent.replaceChild(document.createTextNode(span.textContent), span);
                parent.normalize();
            }
        });
    }

    /**
     * Highlight all occurrences of search text
     * Highlights persist until user clicks somewhere on the page
     * @param {HTMLElement} element The element to search within
     * @param {string} searchText The text to highlight
     * @return {boolean} True if any occurrences were highlighted
     */
    function highlightAllOccurrences(element, searchText) {
        const occurrences = findAllOccurrences(element, searchText);

        if (occurrences.length === 0) {
            return false;
        }

        // Highlight each occurrence (process in reverse to avoid index shifting).
        const highlightedElements = [];
        for (let i = occurrences.length - 1; i >= 0; i--) {
            const occ = occurrences[i];
            const highlighted = highlightOccurrence(occ.textNode, occ.startIndex, searchText.length);
            if (highlighted) {
                highlightedElements.unshift(highlighted);
            }
        }

        // Scroll to the first highlighted element.
        if (highlightedElements.length > 0) {
            const firstHighlight = highlightedElements[0];
            const rect = firstHighlight.getBoundingClientRect();
            const scrollY = window.scrollY + rect.top - 100;
            window.scrollTo({top: scrollY, behavior: 'smooth'});

            // Remove highlights when user clicks anywhere on the page.
            const clickHandler = function() {
                removeAllHighlights(highlightedElements);
                document.removeEventListener('click', clickHandler);
            };

            // Add click listener with a small delay to avoid immediate trigger.
            setTimeout(function() {
                document.addEventListener('click', clickHandler);
            }, 100);
        }

        return highlightedElements.length > 0;
    }

    /**
     * Highlight a specific occurrence (by index) of search text
     * @param {HTMLElement} element The element to search within
     * @param {string} searchText The text to highlight
     * @param {number} occurrenceIndex Which occurrence to highlight (0-indexed)
     * @return {boolean} True if the occurrence was highlighted
     */
    function highlightNthOccurrence(element, searchText, occurrenceIndex) {
        const occurrences = findAllOccurrences(element, searchText);

        if (occurrences.length === 0) {
            return false;
        }

        // Clamp occurrence index to valid range (don't exceed available occurrences).
        if (occurrenceIndex < 0 || occurrenceIndex >= occurrences.length) {
            occurrenceIndex = Math.min(occurrenceIndex, occurrences.length - 1);
            if (occurrenceIndex < 0) {
                occurrenceIndex = 0;
            }
        }

        const occ = occurrences[occurrenceIndex];
        const highlighted = highlightOccurrence(occ.textNode, occ.startIndex, searchText.length);

        if (highlighted) {
            const rect = highlighted.getBoundingClientRect();
            const scrollY = window.scrollY + rect.top - 100;
            window.scrollTo({top: scrollY, behavior: 'smooth'});

            // Remove highlight after delay.
            setTimeout(function() {
                if (highlighted.parentNode) {
                    const parent = highlighted.parentNode;
                    parent.replaceChild(document.createTextNode(highlighted.textContent), highlighted);
                    parent.normalize();
                }
            }, 3000);

            return true;
        }

        // Even if highlighting failed, return true if we found occurrences
        // to prevent the fallback from running and causing double-highlight.
        // The text exists, we just couldn't visually highlight it.
        return occurrences.length > 0;
    }

    /**
     * Create a highlight span around text in a text node
     * @param {Node} textNode The text node containing the text
     * @param {number} startIndex Start index within the text node
     * @param {number} length Length of text to highlight
     * @return {HTMLElement|null} The highlight span element or null if failed
     */
    function highlightOccurrence(textNode, startIndex, length) {
        try {
            const text = textNode.textContent;

            // Validate indices.
            if (startIndex < 0 || startIndex >= text.length) {
                return null;
            }

            const endIndex = Math.min(startIndex + length, text.length);

            const range = document.createRange();
            range.setStart(textNode, startIndex);
            range.setEnd(textNode, endIndex);

            const span = document.createElement('span');
            span.style.setProperty('background-color', '#ffff99', 'important');
            span.style.setProperty('padding', '2px', 'important');
            span.style.setProperty('border-radius', '2px', 'important');
            span.style.setProperty('color', 'inherit', 'important');
            span.style.setProperty('display', 'inline', 'important');
            span.className = 'coursesearch-highlight-temp';

            range.surroundContents(span);
            return span;
        } catch (e) {
            // Range operations can fail in some edge cases.
            return null;
        }
    }

    /**
     * Initialize scrolling to highlighted text
     */
    function init() {
        // Prevent multiple executions.
        if (hasHighlighted) {
            return;
        }

        // Check for cs_highlight parameter in URL.
        // Use cs_highlight (not highlight) to avoid conflict with Moodle core's built-in highlighting.
        const urlParams = new URLSearchParams(window.location.search);
        let highlightText = urlParams.get('cs_highlight');
        let highlightAll = urlParams.get('cs_highlight_all') === '1';
        let occurrenceIndex = parseInt(urlParams.get('cs_occurrence') || '0', 10);

        // If not in URL, check sessionStorage (set by coursesearch module).
        let storedModuleId = null;
        if (!highlightText && typeof sessionStorage !== 'undefined') {
            const storedHighlight = sessionStorage.getItem('coursesearch_highlight');
            storedModuleId = sessionStorage.getItem('coursesearch_moduleId');
            const timestamp = sessionStorage.getItem('coursesearch_timestamp');
            const storedHighlightAll = sessionStorage.getItem('coursesearch_highlight_all');
            const storedOccurrence = sessionStorage.getItem('coursesearch_occurrence');

            // Check if timestamp is recent (within 10 seconds).
            if (timestamp && Date.now() - parseInt(timestamp, 10) > 10000) {
                // Data is too old, clear it.
                sessionStorage.removeItem('coursesearch_highlight');
                sessionStorage.removeItem('coursesearch_moduleId');
                sessionStorage.removeItem('coursesearch_timestamp');
                sessionStorage.removeItem('coursesearch_shouldScroll');
                sessionStorage.removeItem('coursesearch_highlight_all');
                sessionStorage.removeItem('coursesearch_occurrence');
                return;
            }

            if (storedHighlight) {
                // The value was stored using JSON.stringify, so parse it back safely.
                try {
                    highlightText = JSON.parse(storedHighlight);
                    if (typeof highlightText !== 'string') {
                        highlightText = null;
                    }
                } catch (e) {
                    // If JSON parsing fails, try using it directly (backwards compatibility).
                    highlightText = storedHighlight;
                }

                // Get highlight_all and occurrence from sessionStorage.
                highlightAll = storedHighlightAll === 'true';
                if (storedOccurrence && /^\d+$/.test(storedOccurrence)) {
                    occurrenceIndex = parseInt(storedOccurrence, 10);
                }

                // Clear it after use.
                sessionStorage.removeItem('coursesearch_highlight');
                sessionStorage.removeItem('coursesearch_moduleId');
                sessionStorage.removeItem('coursesearch_timestamp');
                sessionStorage.removeItem('coursesearch_shouldScroll');
                sessionStorage.removeItem('coursesearch_highlight_all');
                sessionStorage.removeItem('coursesearch_occurrence');
            }
        }

        if (!highlightText) {
            return;
        }

        // Mark as highlighted to prevent re-runs.
        hasHighlighted = true;

        // Decode the text.
        const searchText = decodeURIComponent(highlightText).trim();
        if (!searchText) {
            return;
        }

        // Wait for page to be fully loaded.
        $(document).ready(function() {
            // Small delay to ensure all content is rendered.
            setTimeout(function() {
                // First, expand any accordion that contains the search text.
                expandAccordionIfNeeded(searchText).then(function() {
                    // Check if we have a moduleId from sessionStorage first, then URL hash.
                    let moduleId = storedModuleId;
                    const hash = window.location.hash;

                    if (!moduleId && hash) {
                        // Extract module ID from hash (format: #module-123).
                        const match = hash.match(/^#module-(\d+)$/);
                        if (match) {
                            moduleId = match[1];
                        }
                    }

                    // Validate moduleId is numeric only.
                    if (moduleId && !/^\d+$/.test(moduleId)) {
                        moduleId = null;
                    }

                    // Determine the search context element.
                    let searchElement = document.body;
                    if (moduleId) {
                        const moduleElement = document.getElementById('module-' + moduleId);
                        if (moduleElement) {
                            searchElement = moduleElement;
                        }
                    }

                    // Apply the appropriate highlighting mode.
                    let success = false;
                    if (highlightAll) {
                        // Highlight all occurrences.
                        success = highlightAllOccurrences(searchElement, searchText);
                        // If not found in module, try whole page.
                        if (!success && searchElement !== document.body) {
                            success = highlightAllOccurrences(document.body, searchText);
                        }
                    } else {
                        // Highlight specific occurrence.
                        success = highlightNthOccurrence(searchElement, searchText, occurrenceIndex);
                        // If not found in module, try whole page.
                        if (!success && searchElement !== document.body) {
                            success = highlightNthOccurrence(document.body, searchText, occurrenceIndex);
                        }
                    }

                    // Fallback to original scrollToText if new methods fail.
                    if (!success) {
                        if (moduleId) {
                            const moduleElement = document.getElementById('module-' + moduleId);
                            if (moduleElement) {
                                if (!scrollToText(moduleElement, searchText)) {
                                    if (!scrollToText(document.body, searchText)) {
                                        const elementTop = moduleElement.getBoundingClientRect().top + window.scrollY;
                                        window.scrollTo({top: elementTop - 100, behavior: 'smooth'});
                                    }
                                }
                            } else {
                                scrollToText(document.body, searchText);
                            }
                        } else {
                            scrollToText(document.body, searchText);
                        }
                    }
                });
            }, 500);
        });
    }

    /**
     * Auto-initialize on course view pages and module pages
     * Check if we're on a supported page and if highlight parameter exists
     */
    function autoInit() {
        // Run on course view pages and specific module pages.
        const pathname = window.location.pathname;
        // Note: H5P (hvp, h5pactivity) is NOT supported - content is rendered in iframe.
        const supportedPaths = [
            '/course/view.php',
            '/mod/page/view.php',
            '/mod/book/view.php',
            '/mod/lesson/view.php',
            '/mod/wiki/view.php',
            '/mod/forum/discuss.php',
            '/mod/glossary/showentry.php',
            '/mod/data/view.php'
        ];
        const isSupported = supportedPaths.some(function(path) {
            return pathname.indexOf(path) !== -1;
        });
        if (!isSupported) {
            return;
        }

        // Check for cs_highlight in URL or sessionStorage.
        const urlParams = new URLSearchParams(window.location.search);
        let highlightText = urlParams.get('cs_highlight');

        if (!highlightText && typeof sessionStorage !== 'undefined') {
            const storedHighlight = sessionStorage.getItem('coursesearch_highlight');
            if (storedHighlight) {
                // The value may be JSON-encoded.
                try {
                    highlightText = JSON.parse(storedHighlight);
                    if (typeof highlightText !== 'string') {
                        highlightText = null;
                    }
                } catch (e) {
                    // If JSON parsing fails, try using it directly.
                    highlightText = storedHighlight;
                }
            }
        }

        if (highlightText) {
            init();
        }
    }

    // Auto-initialize if highlight parameter is present.
    // This allows the script to run even if not explicitly called.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(autoInit, 500);
        });
    } else {
        // Page already loaded, run immediately.
        setTimeout(autoInit, 500);
    }

    return {
        init: init
    };
});
