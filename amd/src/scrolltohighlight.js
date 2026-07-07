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

    // Page-chrome regions that must never be searched or highlighted. The server computes
    // occurrences over the stored content field only, so matches in breadcrumbs, navigation,
    // drawers etc. would shift the occurrence index and highlight the wrong text.
    // Note: .activity-header is deliberately NOT excluded - it contains the activity
    // description, which is a legitimate search target (module_description results).
    const EXCLUDED_SELECTORS = [
        '.breadcrumb', // Breadcrumb trail.
        '#page-header', // Page header: page/course title, context header.
        '.secondary-navigation', // Course/activity tabs.
        '.activity-navigation', // Prev/next activity links (inside region-main in Boost).
        'nav.navbar', // Top navbar.
        '.drawer', // Boost drawers: course index + blocks drawer.
        '.drawercontent',
        '#nav-drawer', // Older Boost nav drawer.
        '#page-footer',
        '.courseindex'
    ].join(',');

    /**
     * Determine the root element to search within.
     * Priority: the course-module element (course pages) > the main content region > body.
     * @param {string|null} moduleId Course module id from the URL hash or sessionStorage
     * @return {HTMLElement} The element to search within
     */
    function getSearchRoot(moduleId) {
        if (moduleId) {
            const moduleElement = document.getElementById('module-' + moduleId);
            if (moduleElement) {
                return moduleElement;
            }
        }
        return document.querySelector('#region-main') ||
               document.querySelector('[role="main"]') ||
               document.body;
    }

    /**
     * Expand Bootstrap accordion/collapse if text is found inside a collapsed section
     * Returns a promise that resolves when accordion is expanded (or immediately if not needed)
     * @param {string} searchText The search text to look for
     * @param {HTMLElement} root Element whose collapsed sections should be considered
     * @return {Promise} Promise that resolves when accordion is expanded
     */
    function expandAccordionIfNeeded(searchText, root) {
        return new Promise(function(resolve) {
            const searchLower = searchText.toLowerCase();

            // Find all collapsed sections (Bootstrap 4 uses .collapse:not(.show)).
            const collapsedSections = (root || document).querySelectorAll('.collapse:not(.show)');

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
                    // Skip nodes inside page chrome (breadcrumb, nav, header, drawers...).
                    if (node.parentElement && node.parentElement.closest(EXCLUDED_SELECTORS)) {
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
        const originalColor = el.style.color;
        const originalShadow = el.style.textShadow;
        // Force background and text color as a pair: inheriting the color would
        // render light theme text unreadably on the yellow background.
        el.style.setProperty('background-color', '#ffff99', 'important');
        el.style.setProperty('color', '#212529', 'important');
        el.style.setProperty('text-shadow', 'none', 'important');
        setTimeout(function() {
            if (originalBg) {
                el.style.setProperty('background-color', originalBg);
            } else {
                el.style.removeProperty('background-color');
            }
            if (originalColor) {
                el.style.setProperty('color', originalColor);
            } else {
                el.style.removeProperty('color');
            }
            if (originalShadow) {
                el.style.setProperty('text-shadow', originalShadow);
            } else {
                el.style.removeProperty('text-shadow');
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
                        const span = createHighlightSpan();

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
     * Normalize text the same way the server-side plain text is normalized:
     * whitespace/NBSP runs collapsed to a single space, lowercased.
     * @param {string} text The text to normalize
     * @return {string} Normalized text
     */
    function normalizeText(text) {
        return text.replace(/[\u00A0\s]+/g, ' ').toLowerCase();
    }

    /**
     * Build a searchable index of the visible text under root: one combined normalized
     * string plus a per-character map back to the source (text node, offset).
     *
     * The normalization mirrors the server side (coursesearch_html_to_text collapses
     * whitespace; comparison is lowercased), so occurrences found here line up with the
     * occurrences the server counted - including matches that span multiple inline
     * elements, which a per-text-node search would miss.
     *
     * @param {HTMLElement} root The element to index
     * @return {{text: string, map: Array}} Normalized text and char->source map
     */
    function buildTextIndex(root) {
        const textNodes = getVisibleTextNodes(root);
        const chars = [];
        const map = [];
        let pendingSpace = null;
        let emitted = false;

        for (let n = 0; n < textNodes.length; n++) {
            const node = textNodes[n];
            const text = node.textContent;
            for (let i = 0; i < text.length; i++) {
                if (/[\u00A0\s]/.test(text[i])) {
                    // Remember where the whitespace run started; emit at most one space.
                    if (pendingSpace === null) {
                        pendingSpace = {node: node, offset: i};
                    }
                    continue;
                }
                if (pendingSpace !== null) {
                    // Collapse the run to a single space (skip leading whitespace entirely).
                    if (emitted) {
                        chars.push(' ');
                        map.push(pendingSpace);
                    }
                    pendingSpace = null;
                }
                // Iterate code points: lowercasing can expand a character (e.g. '\u0130').
                const lower = text[i].toLowerCase();
                for (const lch of lower) {
                    chars.push(lch);
                    map.push({node: node, offset: i});
                }
                emitted = true;
            }
        }

        return {text: chars.join(''), map: map};
    }

    /**
     * Find all non-overlapping matches of the search text in the index.
     * Mirrors the server-side mb_strpos walk (advance by needle length).
     * @param {{text: string, map: Array}} indexObj Result of buildTextIndex()
     * @param {string} searchText The text to search for
     * @return {Array} Array of {start, end} offsets into indexObj.text
     */
    function findMatches(indexObj, searchText) {
        const matches = [];
        const needle = normalizeText(searchText).trim();
        if (!needle) {
            return matches;
        }
        let from = 0;
        let pos;
        while ((pos = indexObj.text.indexOf(needle, from)) !== -1) {
            matches.push({start: pos, end: pos + needle.length});
            from = pos + needle.length;
        }
        return matches;
    }

    /**
     * Length of the common suffix of two strings (used for partial prefix-context scores).
     * @param {string} a First string
     * @param {string} b Second string
     * @return {number} Number of equal trailing characters
     */
    function commonSuffixLen(a, b) {
        let len = 0;
        while (len < a.length && len < b.length && a[a.length - 1 - len] === b[b.length - 1 - len]) {
            len++;
        }
        return len;
    }

    /**
     * Length of the common prefix of two strings (used for partial suffix-context scores).
     * @param {string} a First string
     * @param {string} b Second string
     * @return {number} Number of equal leading characters
     */
    function commonPrefixLen(a, b) {
        let len = 0;
        while (len < a.length && len < b.length && a[len] === b[len]) {
            len++;
        }
        return len;
    }

    /**
     * Pick which match to highlight.
     *
     * When the result link carries the surrounding context of the occurrence
     * (cs_prefix/cs_suffix, same disambiguation model as W3C Text Fragments),
     * each match is scored by how well the text around it agrees with that
     * context; exact containment scores double so it always beats partial
     * overlaps. The occurrence index is only a tiebreaker. Without context,
     * fall back to the plain 0-based index, clamped to the first occurrence.
     *
     * @param {{text: string, map: Array}} indexObj Result of buildTextIndex()
     * @param {Array} matches Result of findMatches()
     * @param {string} contextPrefix Expected text immediately before the match
     * @param {string} contextSuffix Expected text immediately after the match
     * @param {number} occurrenceIndex 0-based occurrence index from the result link
     * @return {number} Index into matches of the best candidate
     */
    function selectCandidate(indexObj, matches, contextPrefix, contextSuffix, occurrenceIndex) {
        const prefix = normalizeText(contextPrefix || '').trim();
        const suffix = normalizeText(contextSuffix || '').trim();

        if (!prefix && !suffix) {
            // Index-only mode: out-of-range indices fall back to the first occurrence
            // in the content region rather than an arbitrary last one.
            return (occurrenceIndex >= 0 && occurrenceIndex < matches.length) ? occurrenceIndex : 0;
        }

        let best = 0;
        let bestScore = -1;
        for (let i = 0; i < matches.length; i++) {
            const match = matches[i];
            const before = indexObj.text.slice(Math.max(0, match.start - prefix.length - 1), match.start).trim();
            const after = indexObj.text.slice(match.end, match.end + suffix.length + 1).trim();
            let score = 0;
            if (prefix) {
                score += before.endsWith(prefix) ? prefix.length * 2 : commonSuffixLen(before, prefix);
            }
            if (suffix) {
                score += after.startsWith(suffix) ? suffix.length * 2 : commonPrefixLen(after, suffix);
            }
            if (score > bestScore || (score === bestScore && i === occurrenceIndex)) {
                bestScore = score;
                best = i;
            }
        }
        return best;
    }

    /**
     * Convert a match into per-text-node [start, end) intervals.
     * A match may span several text nodes (e.g. across inline elements).
     * @param {{text: string, map: Array}} indexObj Result of buildTextIndex()
     * @param {{start: number, end: number}} match One match from findMatches()
     * @return {Array} Array of {node, start, end} intervals in document order
     */
    function matchToIntervals(indexObj, match) {
        const intervals = [];
        let current = null;
        for (let i = match.start; i < match.end; i++) {
            const entry = indexObj.map[i];
            if (current && current.node === entry.node) {
                current.start = Math.min(current.start, entry.offset);
                current.end = Math.max(current.end, entry.offset + 1);
            } else {
                current = {node: entry.node, start: entry.offset, end: entry.offset + 1};
                intervals.push(current);
            }
        }
        return intervals;
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
     * Create the styled temporary highlight span element.
     *
     * All typography is explicitly inherited with inline !important declarations so
     * the highlighted text always renders exactly like the surrounding text - theme
     * stylesheets (which may match generic spans) cannot shrink or restyle it.
     * @return {HTMLElement} The span element
     */
    function createHighlightSpan() {
        const span = document.createElement('span');
        // Background and text color are forced as a pair (like the browser's own
        // find-in-page marker): inheriting the color would render light theme text
        // (e.g. white on a dark card) unreadably on the yellow background.
        span.style.setProperty('background-color', '#ffff99', 'important');
        span.style.setProperty('color', '#212529', 'important');
        span.style.setProperty('text-shadow', 'none', 'important');
        span.style.setProperty('padding', '2px', 'important');
        span.style.setProperty('border-radius', '2px', 'important');
        span.style.setProperty('display', 'inline', 'important');
        span.style.setProperty('font', 'inherit', 'important');
        span.style.setProperty('font-size', 'inherit', 'important');
        span.style.setProperty('font-family', 'inherit', 'important');
        span.style.setProperty('font-weight', 'inherit', 'important');
        span.style.setProperty('font-style', 'inherit', 'important');
        span.style.setProperty('line-height', 'inherit', 'important');
        span.style.setProperty('letter-spacing', 'inherit', 'important');
        span.style.setProperty('text-transform', 'inherit', 'important');
        span.style.setProperty('vertical-align', 'baseline', 'important');
        span.className = 'coursesearch-highlight-temp';
        return span;
    }

    /**
     * Wrap one match in highlight spans. A match spanning several text nodes gets
     * one span per node. Intervals are processed in reverse document order so
     * wrapping (which splits the text node) cannot invalidate earlier offsets.
     * @param {{text: string, map: Array}} indexObj Result of buildTextIndex()
     * @param {{start: number, end: number}} match The match to highlight
     * @return {Array} The highlight span elements in document order (may be empty)
     */
    function highlightMatch(indexObj, match) {
        const intervals = matchToIntervals(indexObj, match);
        const spans = [];
        for (let i = intervals.length - 1; i >= 0; i--) {
            const interval = intervals[i];
            try {
                const range = document.createRange();
                range.setStart(interval.node, interval.start);
                range.setEnd(interval.node, Math.min(interval.end, interval.node.textContent.length));
                const span = createHighlightSpan();
                range.surroundContents(span);
                spans.unshift(span);
            } catch (e) {
                // Range operations can fail in some edge cases; keep the other sub-spans.
            }
        }
        return spans;
    }

    /**
     * Highlight all occurrences of search text
     * Highlights persist until user clicks somewhere on the page
     * @param {HTMLElement} element The element to search within
     * @param {string} searchText The text to highlight
     * @return {boolean} True if any occurrences were highlighted
     */
    function highlightAllOccurrences(element, searchText) {
        const indexObj = buildTextIndex(element);
        const matches = findMatches(indexObj, searchText);

        if (matches.length === 0) {
            return false;
        }

        // Highlight each match (process in reverse document order so wrapping one
        // match cannot shift the offsets of matches before it in the same node).
        const highlightedElements = [];
        for (let i = matches.length - 1; i >= 0; i--) {
            const spans = highlightMatch(indexObj, matches[i]);
            for (let j = spans.length - 1; j >= 0; j--) {
                highlightedElements.unshift(spans[j]);
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
     * Highlight a specific occurrence of the search text, located by its
     * surrounding context (preferred) or by its 0-based occurrence index.
     * @param {HTMLElement} element The element to search within
     * @param {string} searchText The text to highlight
     * @param {number} occurrenceIndex Which occurrence to highlight (0-indexed)
     * @param {string} contextPrefix Plain-text context before the occurrence ('' if unknown)
     * @param {string} contextSuffix Plain-text context after the occurrence ('' if unknown)
     * @return {boolean} True if the occurrence was found
     */
    function highlightNthOccurrence(element, searchText, occurrenceIndex, contextPrefix, contextSuffix) {
        const indexObj = buildTextIndex(element);
        const matches = findMatches(indexObj, searchText);

        if (matches.length === 0) {
            return false;
        }

        const selected = selectCandidate(indexObj, matches, contextPrefix, contextSuffix, occurrenceIndex);
        const spans = highlightMatch(indexObj, matches[selected]);

        if (spans.length > 0) {
            const rect = spans[0].getBoundingClientRect();
            const scrollY = window.scrollY + rect.top - 100;
            window.scrollTo({top: scrollY, behavior: 'smooth'});

            // Remove highlight after delay.
            setTimeout(function() {
                removeAllHighlights(spans);
            }, 3000);

            return true;
        }

        // Wrapping failed entirely: highlight the closest block parent instead so the
        // user still sees where the match is, then report success to prevent the
        // legacy fallback from double-highlighting.
        const startEntry = indexObj.map[matches[selected].start];
        const blockTags = ['P', 'DIV', 'LI', 'TD', 'TH', 'BLOCKQUOTE', 'ARTICLE', 'SECTION'];
        const parent = findHighlightParent(startEntry.node, element, blockTags);
        if (parent) {
            const rect = parent.getBoundingClientRect();
            const scrollY = window.scrollY + rect.top - 100;
            window.scrollTo({top: scrollY, behavior: 'smooth'});
            applyHighlight(parent);
        }
        return true;
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
        let contextPrefix = urlParams.get('cs_prefix') || '';
        let contextSuffix = urlParams.get('cs_suffix') || '';

        /**
         * Parse a JSON.stringify'd sessionStorage string value back to a string.
         * @param {string|null} stored The raw sessionStorage value
         * @return {string|null} The parsed string, or null
         */
        const parseStoredString = function(stored) {
            if (!stored) {
                return null;
            }
            try {
                const parsed = JSON.parse(stored);
                return (typeof parsed === 'string') ? parsed : null;
            } catch (e) {
                // If JSON parsing fails, use it directly (backwards compatibility).
                return stored;
            }
        };

        const storageKeys = [
            'coursesearch_highlight',
            'coursesearch_moduleId',
            'coursesearch_timestamp',
            'coursesearch_shouldScroll',
            'coursesearch_highlight_all',
            'coursesearch_occurrence',
            'coursesearch_prefix',
            'coursesearch_suffix'
        ];
        const clearStorage = function() {
            storageKeys.forEach(function(key) {
                sessionStorage.removeItem(key);
            });
        };

        // If not in URL, check sessionStorage (set by coursesearch module).
        let storedModuleId = null;
        if (!highlightText && typeof sessionStorage !== 'undefined') {
            const storedHighlight = sessionStorage.getItem('coursesearch_highlight');
            storedModuleId = sessionStorage.getItem('coursesearch_moduleId');
            const timestamp = sessionStorage.getItem('coursesearch_timestamp');
            const storedHighlightAll = sessionStorage.getItem('coursesearch_highlight_all');
            const storedOccurrence = sessionStorage.getItem('coursesearch_occurrence');
            const storedPrefix = sessionStorage.getItem('coursesearch_prefix');
            const storedSuffix = sessionStorage.getItem('coursesearch_suffix');

            // Check if timestamp is recent (within 10 seconds).
            if (timestamp && Date.now() - parseInt(timestamp, 10) > 10000) {
                // Data is too old, clear it.
                clearStorage();
                return;
            }

            if (storedHighlight) {
                // The values were stored using JSON.stringify, so parse them back safely.
                highlightText = parseStoredString(storedHighlight);

                // Get highlight_all, occurrence and context from sessionStorage.
                highlightAll = storedHighlightAll === 'true';
                if (storedOccurrence && /^\d+$/.test(storedOccurrence)) {
                    occurrenceIndex = parseInt(storedOccurrence, 10);
                }
                contextPrefix = parseStoredString(storedPrefix) || '';
                contextSuffix = parseStoredString(storedSuffix) || '';

                // Clear it after use.
                clearStorage();
            }
        }

        if (!highlightText) {
            return;
        }

        // Mark as highlighted to prevent re-runs.
        hasHighlighted = true;

        // The value is already decoded (URLSearchParams decodes URL params, and the sessionStorage
        // path stores an already-decoded value), so do NOT decode again: decodeURIComponent() would
        // double-decode and throw a URIError on any literal '%' in the query (e.g. "100% cotton").
        const searchText = highlightText.trim();
        if (!searchText) {
            return;
        }

        // Wait for page to be fully loaded.
        $(document).ready(function() {
            // Small delay to ensure all content is rendered.
            setTimeout(function() {
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

                // Determine the search context element: module element, main content
                // region, or body - never page chrome (see EXCLUDED_SELECTORS).
                const searchElement = getSearchRoot(moduleId);

                // First, expand any accordion within the search root that contains the text.
                expandAccordionIfNeeded(searchText, searchElement).then(function() {
                    // Apply the appropriate highlighting mode.
                    let success = false;
                    if (highlightAll) {
                        // Highlight all occurrences.
                        success = highlightAllOccurrences(searchElement, searchText);
                        // If not found in the search root, try the whole page.
                        if (!success && searchElement !== document.body) {
                            success = highlightAllOccurrences(document.body, searchText);
                        }
                    } else {
                        // Highlight the specific occurrence (context-anchored).
                        success = highlightNthOccurrence(
                            searchElement, searchText, occurrenceIndex, contextPrefix, contextSuffix);
                        // If not found in the search root, try the whole page.
                        if (!success && searchElement !== document.body) {
                            success = highlightNthOccurrence(
                                document.body, searchText, occurrenceIndex, contextPrefix, contextSuffix);
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
