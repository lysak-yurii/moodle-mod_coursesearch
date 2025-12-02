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

    /**
     * Find text within an element and scroll to it
     */
    function scrollToText(element, searchText) {
        if (!element || !searchText) {
            return false;
        }

        // Get all text nodes within the element
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );

        let node;
        const textNodes = [];
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }

        // Search for the text (case-insensitive)
        const searchLower = searchText.toLowerCase();
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
                        span.style.backgroundColor = '#ffff99';
                        span.style.padding = '2px';
                        span.className = 'coursesearch-highlight-temp';
                        try {
                            range.surroundContents(span);
                            // Remove highlight after 3 seconds
                            setTimeout(function() {
                                if (span.parentNode) {
                                    const parent = span.parentNode;
                                    parent.replaceChild(document.createTextNode(span.textContent), span);
                                    parent.normalize();
                                }
                            }, 3000);
                        } catch (e) {
                            // If surroundContents fails, just scroll
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
        // Check for highlight parameter in URL
        const urlParams = new URLSearchParams(window.location.search);
        let highlightText = urlParams.get('highlight');

        // If not in URL, check sessionStorage (set by coursesearch module)
        if (!highlightText && typeof sessionStorage !== 'undefined') {
            highlightText = sessionStorage.getItem('coursesearch_highlight');
            if (highlightText) {
                // Clear it after use
                sessionStorage.removeItem('coursesearch_highlight');
            }
        }

        if (!highlightText) {
            return;
        }

        // Decode the text
        const searchText = decodeURIComponent(highlightText).trim();
        if (!searchText) {
            return;
        }

        // Wait for page to be fully loaded
        $(document).ready(function() {
            // Small delay to ensure all content is rendered
            setTimeout(function() {
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
            }, 500); // 500ms delay to ensure content is loaded
        });
    }

    // Auto-initialize on course pages
    // Check if we're on a course view page and if highlight parameter exists
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

