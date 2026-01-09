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
 * Floating widget module for course search.
 *
 * @module     mod_coursesearch/floatingwidget
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    /**
     * Initialize the floating widget.
     */
    function init() {
        const widget = document.getElementById('coursesearch-floating-widget');
        if (!widget) {
            return;
        }

        const toggle = widget.querySelector('.coursesearch-floating-widget-toggle');
        const close = widget.querySelector('.coursesearch-floating-widget-close');
        const input = widget.querySelector('.coursesearch-floating-widget-input');

        // Toggle widget on click.
        if (toggle) {
            toggle.addEventListener('click', function() {
                toggleWidget(widget);
            });

            // Support keyboard navigation.
            toggle.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleWidget(widget);
                }
            });
        }

        // Close widget on close button click.
        if (close) {
            close.addEventListener('click', function() {
                collapseWidget(widget);
            });
        }

        // Focus input when widget is expanded.
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    if (!widget.classList.contains('coursesearch-floating-widget-collapsed')) {
                        // Widget is expanded, focus the input.
                        if (input) {
                            setTimeout(function() {
                                input.focus();
                            }, 100);
                        }
                    }
                }
            });
        });

        observer.observe(widget, {
            attributes: true,
            attributeFilter: ['class']
        });

        // Close widget when clicking outside.
        document.addEventListener('click', function(e) {
            if (!widget.contains(e.target) && !widget.classList.contains('coursesearch-floating-widget-collapsed')) {
                collapseWidget(widget);
            }
        });
    }

    /**
     * Toggle widget expanded/collapsed state.
     *
     * @param {HTMLElement} widget The widget element.
     */
    function toggleWidget(widget) {
        if (widget.classList.contains('coursesearch-floating-widget-collapsed')) {
            expandWidget(widget);
        } else {
            collapseWidget(widget);
        }
    }

    /**
     * Expand the widget.
     *
     * @param {HTMLElement} widget The widget element.
     */
    function expandWidget(widget) {
        widget.classList.remove('coursesearch-floating-widget-collapsed');
        const toggle = widget.querySelector('.coursesearch-floating-widget-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
    }

    /**
     * Collapse the widget.
     *
     * @param {HTMLElement} widget The widget element.
     */
    function collapseWidget(widget) {
        widget.classList.add('coursesearch-floating-widget-collapsed');
        const toggle = widget.querySelector('.coursesearch-floating-widget-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    return {
        init: init
    };
});
