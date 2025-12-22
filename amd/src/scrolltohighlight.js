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
     * Initialize scrolling to highlighted text
     */
    function init() {
        // Prevent multiple executions.
        if (hasHighlighted) {
            return;
        }

        // Check for highlight parameter in URL.
        const urlParams = new URLSearchParams(window.location.search);
        let highlightText = urlParams.get('highlight');

        // If not in URL, check sessionStorage (set by coursesearch module).
        let storedModuleId = null;
        if (!highlightText && typeof sessionStorage !== 'undefined') {
            const storedHighlight = sessionStorage.getItem('coursesearch_highlight');
            storedModuleId = sessionStorage.getItem('coursesearch_moduleId');
            const timestamp = sessionStorage.getItem('coursesearch_timestamp');

            // Check if timestamp is recent (within 10 seconds).
            if (timestamp && Date.now() - parseInt(timestamp, 10) > 10000) {
                // Data is too old, clear it.
                sessionStorage.removeItem('coursesearch_highlight');
                sessionStorage.removeItem('coursesearch_moduleId');
                sessionStorage.removeItem('coursesearch_timestamp');
                sessionStorage.removeItem('coursesearch_shouldScroll');
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
                // Clear it after use.
                sessionStorage.removeItem('coursesearch_highlight');
                sessionStorage.removeItem('coursesearch_moduleId');
                sessionStorage.removeItem('coursesearch_timestamp');
                sessionStorage.removeItem('coursesearch_shouldScroll');
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

                    if (moduleId) {
                        const moduleElement = document.getElementById('module-' + moduleId);

                        if (moduleElement) {
                            // Try to find and scroll to the text within this module.
                            if (!scrollToText(moduleElement, searchText)) {
                                // If text not found in module, try searching in whole page.
                                if (!scrollToText(document.body, searchText)) {
                                    // If still not found, just scroll to the module.
                                    const elementTop = moduleElement.getBoundingClientRect().top + window.scrollY;
                                    window.scrollTo({top: elementTop - 100, behavior: 'smooth'});
                                }
                            }
                        } else {
                            // Module element not found, search in whole page.
                            scrollToText(document.body, searchText);
                        }
                    } else {
                        // No moduleId, search in the whole page.
                        scrollToText(document.body, searchText);
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

        // Check for highlight in URL or sessionStorage.
        const urlParams = new URLSearchParams(window.location.search);
        let highlightText = urlParams.get('highlight');

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
