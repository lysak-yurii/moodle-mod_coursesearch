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

namespace mod_coursesearch\output;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

/**
 * Search results container renderable class
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_results implements renderable, templatable {
    /** @var string The search query */
    protected $query;

    /** @var array Array of search_result objects */
    protected $results;

    /** @var int Total count of results */
    protected $count;

    /** @var int Current page number (0-indexed) */
    protected $page;

    /** @var int Results per page */
    protected $perpage;

    /** @var moodle_url Base URL for pagination */
    protected $baseurl;

    /** @var bool Whether results should be grouped by section */
    protected $grouped;

    /** @var int Default results per page */
    const DEFAULT_PERPAGE = 10;

    /**
     * Constructor
     *
     * @param string $query The search query
     * @param array $results Array of search_result objects
     * @param int $page Current page number (0-indexed)
     * @param int $perpage Results per page
     * @param moodle_url|null $baseurl Base URL for pagination links
     * @param bool $grouped Whether to group results by section (default true)
     */
    public function __construct(
        string $query,
        array $results = [],
        int $page = 0,
        int $perpage = self::DEFAULT_PERPAGE,
        ?moodle_url $baseurl = null,
        bool $grouped = true
    ) {
        $this->query = $query;
        $this->results = $results;
        $this->count = count($results);
        $this->page = max(0, $page);
        $this->perpage = max(1, $perpage);
        $this->baseurl = $baseurl;
        $this->grouped = $grouped;
    }

    /**
     * Deduplicate results by URL
     *
     * When the same URL appears multiple times (e.g., same content matched by title and description),
     * keep only the most informative match. Priority: content > description > title.
     *
     * @param array $results Array of exported result data
     * @return array Deduplicated results
     */
    protected function deduplicate_results(array $results): array {
        $seen = [];
        $deduplicated = [];

        // Priority map: higher = more informative, should be kept.
        $matchpriority = [
            'content' => 3,
            'description' => 2,
            'title' => 1,
        ];

        foreach ($results as $result) {
            // Create a unique key based on URL (without query params that might vary).
            $url = $result['url'] ?? '';
            if (empty($url)) {
                // No URL - always include.
                $deduplicated[] = $result;
                continue;
            }

            // Normalize URL for comparison (remove highlight param, but preserve anchor/fragment).
            // The anchor (fragment) is important for labels - each label has a unique anchor like #module-123.
            // Parse URL to separate base URL from anchor.
            $urlparts = parse_url($url);
            $baseurl = '';
            if (isset($urlparts['scheme'])) {
                $baseurl .= $urlparts['scheme'] . '://';
            }
            if (isset($urlparts['host'])) {
                $baseurl .= $urlparts['host'];
            }
            if (isset($urlparts['port'])) {
                $baseurl .= ':' . $urlparts['port'];
            }
            if (isset($urlparts['path'])) {
                $baseurl .= $urlparts['path'];
            }
            // Remove highlight from query string but keep other params.
            $query = $urlparts['query'] ?? '';
            if (!empty($query)) {
                parse_str($query, $params);
                unset($params['highlight']);
                if (!empty($params)) {
                    $baseurl .= '?' . http_build_query($params);
                }
            }
            // Include anchor/fragment in the key - this ensures labels with different anchors are not deduplicated.
            $anchor = $urlparts['fragment'] ?? '';
            $urlkey = $baseurl . ($anchor ? '#' . $anchor : '');

            // Determine priority of this match.
            $matchtype = strtolower($result['matchtype'] ?? 'title');
            $priority = 0;
            foreach ($matchpriority as $type => $prio) {
                if (stripos($matchtype, $type) !== false) {
                    $priority = max($priority, $prio);
                }
            }

            // Check if we've seen this URL before.
            if (isset($seen[$urlkey])) {
                // Compare priorities - keep the higher priority match.
                if ($priority > $seen[$urlkey]['priority']) {
                    // Replace the existing entry with this better one.
                    $seen[$urlkey] = ['index' => count($deduplicated), 'priority' => $priority];
                    // Find and replace in deduplicated array.
                    foreach ($deduplicated as $i => $existing) {
                        // Normalize existing URL the same way.
                        $existingurlstr = $existing['url'] ?? '';
                        $existingparts = parse_url($existingurlstr);
                        $existingbase = '';
                        if (isset($existingparts['scheme'])) {
                            $existingbase .= $existingparts['scheme'] . '://';
                        }
                        if (isset($existingparts['host'])) {
                            $existingbase .= $existingparts['host'];
                        }
                        if (isset($existingparts['port'])) {
                            $existingbase .= ':' . $existingparts['port'];
                        }
                        if (isset($existingparts['path'])) {
                            $existingbase .= $existingparts['path'];
                        }
                        $existingquery = $existingparts['query'] ?? '';
                        if (!empty($existingquery)) {
                            parse_str($existingquery, $existingparams);
                            unset($existingparams['highlight']);
                            if (!empty($existingparams)) {
                                $existingbase .= '?' . http_build_query($existingparams);
                            }
                        }
                        $existinganchor = $existingparts['fragment'] ?? '';
                        $existingurl = $existingbase . ($existinganchor ? '#' . $existinganchor : '');
                        if ($existingurl === $urlkey) {
                            $deduplicated[$i] = $result;
                            break;
                        }
                    }
                }
                // Otherwise skip this duplicate.
            } else {
                // First time seeing this URL.
                $seen[$urlkey] = ['index' => count($deduplicated), 'priority' => $priority];
                $deduplicated[] = $result;
            }
        }

        return $deduplicated;
    }

    /**
     * Group results by section number with hierarchical subsection support
     *
     * Results are organized hierarchically:
     * - Parent sections contain their own results AND subsections
     * - Subsections are nested under their parent sections
     * - Section-type results enhance the header, not shown as separate items
     *
     * @param array $results Array of exported result data
     * @return array Grouped results with section headers and nested subsections
     */
    protected function group_results_by_section(array $results): array {
        $groups = [];
        $subsectiongroups = []; // Temporary storage for subsections.

        foreach ($results as $result) {
            $sectionnumber = $result['section_number'] ?? 0;
            $sectionname = $result['section_name'] ?? get_string('section') . ' ' . $sectionnumber;
            $issubsection = $result['issubsection'] ?? false;

            // For subsections, group under parent section.
            if ($issubsection && isset($result['parent_section_number'])) {
                $parentsectionnumber = $result['parent_section_number'];
                $parentsectionname = $result['parent_section_name'] ?? get_string('section') . ' ' . $parentsectionnumber;

                // Ensure parent group exists.
                if (!isset($groups[$parentsectionnumber])) {
                    $groups[$parentsectionnumber] = [
                        'section_number' => $parentsectionnumber,
                        'section_name' => $parentsectionname,
                        'section_matched' => false,
                        'section_snippet' => '',
                        'section_url' => '',
                        'module_results' => [],
                        'subsections' => [],
                    ];
                }

                // Ensure subsection exists within parent.
                if (!isset($groups[$parentsectionnumber]['subsections'][$sectionnumber])) {
                    $groups[$parentsectionnumber]['subsections'][$sectionnumber] = [
                        'section_number' => $sectionnumber,
                        'section_name' => $sectionname,
                        'section_matched' => false,
                        'section_snippet' => '',
                        'section_url' => '',
                        'module_results' => [],
                    ];
                }

                // Handle subsection matches.
                if ($result['issection'] ?? false) {
                    $groups[$parentsectionnumber]['subsections'][$sectionnumber]['section_matched'] = true;
                    $groups[$parentsectionnumber]['subsections'][$sectionnumber]['section_url'] = $result['url'] ?? '';

                    // Only show snippet if it's from description match (not title match).
                    $matchtype = $result['matchtype'] ?? '';
                    $istitlematch = (stripos($matchtype, 'title') !== false);
                    if (!$istitlematch && !empty($result['snippet'])) {
                        $groups[$parentsectionnumber]['subsections'][$sectionnumber]['section_snippet'] = $result['snippet'];
                    }
                } else {
                    $groups[$parentsectionnumber]['subsections'][$sectionnumber]['module_results'][] = $result;
                }
            } else {
                // Regular section or module result.
                if (!isset($groups[$sectionnumber])) {
                    $groups[$sectionnumber] = [
                        'section_number' => $sectionnumber,
                        'section_name' => $sectionname,
                        'section_matched' => false,
                        'section_snippet' => '',
                        'section_url' => '',
                        'module_results' => [],
                        'subsections' => [],
                    ];
                }

                // Section-type results enhance the group header, not shown as separate items.
                if ($result['issection'] ?? false) {
                    $groups[$sectionnumber]['section_matched'] = true;
                    $groups[$sectionnumber]['section_url'] = $result['url'] ?? '';

                    // Only show snippet if it's from description match (not title match).
                    $matchtype = $result['matchtype'] ?? '';
                    $istitlematch = (stripos($matchtype, 'title') !== false);
                    if (!$istitlematch && !empty($result['snippet'])) {
                        $groups[$sectionnumber]['section_snippet'] = $result['snippet'];
                    }
                } else {
                    $groups[$sectionnumber]['module_results'][] = $result;
                }
            }
        }

        // Sort groups by section number.
        ksort($groups, SORT_NUMERIC);

        // Prepare final grouped array.
        $groupedresults = [];
        foreach ($groups as $sectionnumber => $group) {
            // Process subsections.
            $subsections = [];
            if (!empty($group['subsections'])) {
                ksort($group['subsections'], SORT_NUMERIC);
                foreach ($group['subsections'] as $subsectionnumber => $subsection) {
                    // Only include subsections that have results or a match.
                    if (empty($subsection['module_results']) && !$subsection['section_matched']) {
                        continue;
                    }
                    $subsections[] = [
                        'section_number' => $subsection['section_number'],
                        'section_name' => $subsection['section_name'],
                        'section_matched' => $subsection['section_matched'],
                        'section_snippet' => $subsection['section_snippet'],
                        'section_url' => $subsection['section_url'],
                        'hassectionsnippet' => !empty($subsection['section_snippet']),
                        'results' => $subsection['module_results'],
                        'resultcount' => count($subsection['module_results']),
                        'hasresults' => !empty($subsection['module_results']),
                    ];
                }
            }

            // Only include groups that have module results, subsections, OR a section match.
            if (empty($group['module_results']) && empty($subsections) && !$group['section_matched']) {
                continue;
            }

            $groupedresults[] = [
                'section_number' => $group['section_number'],
                'section_name' => $group['section_name'],
                'section_matched' => $group['section_matched'],
                'section_snippet' => $group['section_snippet'],
                'section_url' => $group['section_url'],
                'hassectionsnippet' => !empty($group['section_snippet']),
                'results' => $group['module_results'],
                'resultcount' => count($group['module_results']),
                'hasresults' => !empty($group['module_results']),
                'subsections' => $subsections,
                'hassubsections' => !empty($subsections),
            ];
        }

        return $groupedresults;
    }

    /**
     * Generate pagination data
     *
     * @param int $totalcount Total number of results
     * @return array Pagination data for template
     */
    protected function get_pagination_data(int $totalcount): array {
        $totalpages = ceil($totalcount / $this->perpage);

        if ($totalpages <= 1) {
            return [
                'haspagination' => false,
            ];
        }

        $pages = [];
        $currentpage = $this->page + 1; // Convert to 1-indexed for display.

        // Determine page range to show (show max 7 pages with ellipsis).
        $startpage = max(1, $currentpage - 3);
        $endpage = min($totalpages, $currentpage + 3);

        // Adjust if near start or end.
        if ($currentpage <= 4) {
            $endpage = min($totalpages, 7);
        }
        if ($currentpage >= $totalpages - 3) {
            $startpage = max(1, $totalpages - 6);
        }

        // Add first page and ellipsis if needed.
        if ($startpage > 1) {
            $pages[] = $this->create_page_item(1, $currentpage);
            if ($startpage > 2) {
                $pages[] = ['isellipsis' => true];
            }
        }

        // Add page numbers.
        for ($i = $startpage; $i <= $endpage; $i++) {
            $pages[] = $this->create_page_item($i, $currentpage);
        }

        // Add ellipsis and last page if needed.
        if ($endpage < $totalpages) {
            if ($endpage < $totalpages - 1) {
                $pages[] = ['isellipsis' => true];
            }
            $pages[] = $this->create_page_item($totalpages, $currentpage);
        }

        // Previous and next links.
        $hasprevious = $this->page > 0;
        $hasnext = $this->page < $totalpages - 1;

        $previousurl = '';
        $nexturl = '';

        if ($this->baseurl) {
            if ($hasprevious) {
                $prevurl = clone $this->baseurl;
                $prevurl->param('page', $this->page - 1);
                $previousurl = $prevurl->out(false);
            }
            if ($hasnext) {
                $nxturl = clone $this->baseurl;
                $nxturl->param('page', $this->page + 1);
                $nexturl = $nxturl->out(false);
            }
        }

        return [
            'haspagination' => true,
            'pages' => $pages,
            'hasprevious' => $hasprevious,
            'hasnext' => $hasnext,
            'previousurl' => $previousurl,
            'nexturl' => $nexturl,
            'currentpage' => $currentpage,
            'totalpages' => $totalpages,
        ];
    }

    /**
     * Create a page item for pagination
     *
     * @param int $pagenumber Page number (1-indexed)
     * @param int $currentpage Current page (1-indexed)
     * @return array Page item data
     */
    protected function create_page_item(int $pagenumber, int $currentpage): array {
        $iscurrent = ($pagenumber === $currentpage);

        $url = '';
        if ($this->baseurl && !$iscurrent) {
            $pageurl = clone $this->baseurl;
            $pageurl->param('page', $pagenumber - 1); // Convert back to 0-indexed.
            $url = $pageurl->out(false);
        }

        return [
            'page' => $pagenumber,
            'url' => $url,
            'iscurrent' => $iscurrent,
            'isellipsis' => false,
        ];
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // Export each result.
        $exportedresults = [];
        foreach ($this->results as $result) {
            if ($result instanceof search_result) {
                $exportedresults[] = $result->export_for_template($output);
            }
        }

        // Deduplicate results.
        $exportedresults = $this->deduplicate_results($exportedresults);

        // Count total individual results (for user display).
        $totalresultcount = count($exportedresults);

        // Prepare count object for language string.
        $countobj = new \stdClass();
        $countobj->count = $totalresultcount;
        $countobj->query = s($this->query);

        if ($this->grouped) {
            // Grouped mode: group by section and paginate groups.
            // Group ALL results first (before pagination).
            // This ensures subsection matches and their module results stay together.
            $allgroupedresults = $this->group_results_by_section($exportedresults);

            // Count total groups for pagination.
            $totalgroupcount = count($allgroupedresults);

            // Paginate at group level (not individual results).
            $offset = $this->page * $this->perpage;
            $pagedgroups = array_slice($allgroupedresults, $offset, $this->perpage);

            // Get pagination data (use group count for pagination).
            $pagination = $this->get_pagination_data($totalgroupcount);

            // Calculate displayed range (now refers to groups/sections).
            $startgroup = min($offset + 1, $totalgroupcount);
            $endgroup = min($offset + $this->perpage, $totalgroupcount);

            // Prepare range object for language string (sections/groups range).
            $rangeobj = new \stdClass();
            $rangeobj->start = $startgroup;
            $rangeobj->end = $endgroup;
            $rangeobj->total = $totalgroupcount;

            return [
                'query' => s($this->query),
                'hasresults' => ($totalresultcount > 0),
                'noresults' => ($totalresultcount === 0),
                'grouped' => true,
                'groups' => $pagedgroups,
                'hasgroups' => !empty($pagedgroups),
                'count' => $totalresultcount,
                'groupcount' => $totalgroupcount,
                'resultscount' => get_string('searchresultscount', 'coursesearch', $countobj),
                'resultsrange' => get_string('searchresultsrange', 'coursesearch', $rangeobj),
                'noresultsmessage' => get_string('noresults', 'coursesearch', s($this->query)),
                'heading' => get_string('searchresultsfor', 'coursesearch', s($this->query)),
                'pagination' => $pagination,
                'haspagination' => $pagination['haspagination'] ?? false,
                'showingrange' => ($totalgroupcount > $this->perpage),
            ];
        } else {
            // Ungrouped mode: paginate individual results.
            $offset = $this->page * $this->perpage;
            $pagedresults = array_slice($exportedresults, $offset, $this->perpage);

            // Get pagination data (use result count for pagination).
            $pagination = $this->get_pagination_data($totalresultcount);

            // Calculate displayed range (refers to individual results).
            $startresult = min($offset + 1, $totalresultcount);
            $endresult = min($offset + $this->perpage, $totalresultcount);

            // Prepare range object for language string (results range).
            $rangeobj = new \stdClass();
            $rangeobj->start = $startresult;
            $rangeobj->end = $endresult;
            $rangeobj->total = $totalresultcount;

            return [
                'query' => s($this->query),
                'hasresults' => ($totalresultcount > 0),
                'noresults' => ($totalresultcount === 0),
                'grouped' => false,
                'results' => $pagedresults,
                'count' => $totalresultcount,
                'resultscount' => get_string('searchresultscount', 'coursesearch', $countobj),
                'resultsrange' => get_string('searchresultsrange_ungrouped', 'coursesearch', $rangeobj),
                'noresultsmessage' => get_string('noresults', 'coursesearch', s($this->query)),
                'heading' => get_string('searchresultsfor', 'coursesearch', s($this->query)),
                'pagination' => $pagination,
                'haspagination' => $pagination['haspagination'] ?? false,
                'showingrange' => ($totalresultcount > $this->perpage),
            ];
        }
    }
}
