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
 * Handle expand/collapse functionality for grouped search results
 *
 * @module     mod_coursesearch/resultgroups
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    /**
     * Toggle a result group's expanded/collapsed state
     *
     * @param {HTMLElement} groupElement The group container element
     * @param {boolean} expand Whether to expand (true) or collapse (false)
     */
    function toggleGroup(groupElement, expand) {
        const header = groupElement.querySelector('.coursesearch-group-header');
        const matches = groupElement.querySelector('.coursesearch-group-matches');
        const toggle = groupElement.querySelector('.coursesearch-group-toggle');
        const icon = toggle ? toggle.querySelector('.icon') : null;

        if (!header || !matches) {
            return;
        }

        const isExpanded = !groupElement.classList.contains('coursesearch-group-collapsed');

        if (expand === undefined) {
            expand = !isExpanded;
        }

        if (expand) {
            // Expand the group.
            groupElement.classList.remove('coursesearch-group-collapsed');
            groupElement.classList.add('coursesearch-group-expanded');
            header.setAttribute('aria-expanded', 'true');
            matches.setAttribute('aria-hidden', 'false');
            if (icon) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
            if (toggle) {
                toggle.setAttribute('aria-label', toggle.dataset.collapseLabel || 'Collapse matches');
            }
        } else {
            // Collapse the group.
            groupElement.classList.remove('coursesearch-group-expanded');
            groupElement.classList.add('coursesearch-group-collapsed');
            header.setAttribute('aria-expanded', 'false');
            matches.setAttribute('aria-hidden', 'true');
            if (icon) {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
            if (toggle) {
                toggle.setAttribute('aria-label', toggle.dataset.expandLabel || 'Expand matches');
            }
        }
    }

    /**
     * Handle click on group header or toggle button
     *
     * @param {Event} event The click event
     */
    function handleGroupToggle(event) {
        const target = event.target;
        const groupElement = target.closest('.coursesearch-result-group');

        if (!groupElement) {
            return;
        }

        // Don't toggle if clicking on a link inside the header.
        if (target.closest('a')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        toggleGroup(groupElement);
    }

    /**
     * Handle keyboard events on group header or activity name span
     *
     * @param {Event} event The keyboard event
     */
    function handleGroupKeydown(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            // Don't toggle if the target is a link.
            if (event.target.tagName === 'A') {
                return;
            }
            event.preventDefault();
            const groupElement = event.target.closest('.coursesearch-result-group');
            if (groupElement) {
                toggleGroup(groupElement);
            }
        }
    }

    /**
     * Attach event handlers to all result groups
     */
    function attachHandlers() {
        // Find all result groups.
        const groups = document.querySelectorAll('.coursesearch-result-group');

        groups.forEach(function(group) {
            // Skip if already has handlers.
            if (group.dataset.coursesearchGroupHandler) {
                return;
            }
            group.dataset.coursesearchGroupHandler = 'true';

            const header = group.querySelector('.coursesearch-group-header');
            const toggle = group.querySelector('.coursesearch-group-toggle');

            if (header) {
                // Click handler for header.
                header.addEventListener('click', handleGroupToggle);

                // Keyboard handler for header.
                header.addEventListener('keydown', handleGroupKeydown);
            }

            // Also handle keyboard events on the activity name span (when no URL).
            const activityNameSpan = group.querySelector('.coursesearch-group-activity-name');
            if (activityNameSpan) {
                activityNameSpan.addEventListener('keydown', handleGroupKeydown);
            }

            if (toggle) {
                // Click handler for toggle button.
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleGroup(group);
                });
            }
        });
    }

    return {
        /**
         * Initialize expand/collapse handlers for search result groups
         */
        init: function() {
            // Attach immediately if DOM is ready, otherwise wait.
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', attachHandlers);
            } else {
                attachHandlers();
            }
        }
    };
});

