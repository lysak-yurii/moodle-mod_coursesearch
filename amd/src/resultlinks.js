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
 * Handle click interception on search result links
 * Stores highlight data in sessionStorage before navigation
 *
 * @module     mod_coursesearch/resultlinks
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    /**
     * Attach click handlers to search result links
     */
    function attachClickHandlers() {
        // Get all result links (course pages, page activities, forums, etc.).
        const resultLinks = document.querySelectorAll('.coursesearch-results a[href]');

        resultLinks.forEach(function(link) {
            // Skip if already has handler (check for data attribute).
            if (link.dataset.coursesearchHandler) {
                return;
            }
            link.dataset.coursesearchHandler = 'true';

            link.addEventListener('click', function() {
                const href = this.getAttribute('href');
                try {
                    const url = new URL(href, window.location.origin);
                    const highlight = url.searchParams.get('highlight');
                    const occurrence = url.searchParams.get('occurrence');
                    const highlightAll = url.searchParams.get('highlight_all');
                    const hash = url.hash;
                    let moduleId = null;

                    if (hash) {
                        const match = hash.match(/^#module-(\d+)$/);
                        if (match) {
                            moduleId = match[1];
                        }
                    }

                    if (highlight && typeof sessionStorage !== 'undefined') {
                        // Safely escape the highlight value using JSON.stringify before storing.
                        // This prevents XSS by properly escaping all special characters.
                        try {
                            const safeHighlight = JSON.stringify(highlight);
                            sessionStorage.setItem('coursesearch_highlight', safeHighlight);
                            // Validate moduleId is numeric only to prevent XSS.
                            if (moduleId && /^\d+$/.test(moduleId)) {
                                sessionStorage.setItem('coursesearch_moduleId', moduleId);
                            }
                            sessionStorage.setItem('coursesearch_timestamp', Date.now().toString());
                            sessionStorage.setItem('coursesearch_shouldScroll', 'true');

                            // Store highlight_all flag for grouped results (accordion header clicks).
                            if (highlightAll === '1') {
                                sessionStorage.setItem('coursesearch_highlight_all', 'true');
                            } else {
                                sessionStorage.removeItem('coursesearch_highlight_all');
                            }

                            // Store occurrence index for specific match highlighting.
                            // Only store if not highlight_all mode.
                            if (occurrence !== null && highlightAll !== '1' && /^\d+$/.test(occurrence)) {
                                sessionStorage.setItem('coursesearch_occurrence', occurrence);
                            } else {
                                sessionStorage.removeItem('coursesearch_occurrence');
                            }
                        } catch (err) {
                            // Error escaping highlight data, continue without storing.
                        }
                    }
                } catch (err) {
                    // Error storing highlight data, continue without storing.
                }
            });
        });
    }

    return {
        /**
         * Initialize click handlers for search result links
         */
        init: function() {
            // Attach immediately if DOM is ready, otherwise wait.
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', attachClickHandlers);
            } else {
                attachClickHandlers();
            }
        }
    };
});
