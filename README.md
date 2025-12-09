# Course Search Module for Moodle

A comprehensive Moodle activity module that enables teachers to add a search bar to courses, allowing students to search through course content **with automatic highlighting of search terms**.

## Features

### Core Search Functionality
- **Flexible Search Scope**: Configure what content to include in search results:
  - All Course content (sections, activities, resources)
  - Forums only (discussions and posts)

- **Display Options**:
  - Embed search bar directly in course page
  - Standalone search page

- **Customizable**: Set custom placeholder text for the search input

- **Comprehensive Search**: Searches through:
  - Course sections (names and summaries)
  - Activity and resource titles
  - Descriptions and introductions
  - Content (pages, labels, books, forums, wikis, lessons)

### Search Term Highlighting (v1.0.0)
- **Automatic Scrolling**: Automatically scrolls to matched content when clicking search results
- **Visual Highlighting**: Highlights search terms with a yellow background for 3 seconds
- **Smart Fallback**: If direct text highlighting fails (e.g., text inside links), highlights the parent element
- **Seamless Integration**: Works across all content types including labels, pages, forums, and more
- **No Configuration Needed**: Works out of the box after installation

## Requirements

- **Moodle**: 4.4 or higher
- **PHP**: 7.4 or higher
- **Browser**: Modern browser with JavaScript enabled

## Installation

### Method 1: Via Moodle Admin Interface (Recommended)

1. Download `mod_coursesearch.zip`
2. Log in to Moodle as administrator
3. Navigate to: **Site administration → Plugins → Install plugins**
4. Click **"Choose a file"** and upload `mod_coursesearch.zip`
5. Click **"Install plugin from the ZIP file"**
6. Review the validation report and click **"Continue"**
7. Follow the on-screen prompts to complete installation

### Method 2: Manual Installation

1. Extract the `mod_coursesearch.zip` file
2. Upload the `coursesearch` folder to `/path/to/moodle/mod/`
3. Set proper permissions: `chown -R www-data:www-data coursesearch`
4. Visit **Site administration → Notifications**
5. Follow the upgrade prompts

## Usage

### Adding to a Course

1. Navigate to your course
2. Turn editing on
3. Click "Add an activity or resource"
4. Select "Course Search"
5. Configure settings:
   - **Name**: Display name for the search activity
   - **Description**: Optional introduction text
   - **Search scope**: Choose between "All content" or "Forums only"
   - **Embedded mode**: Enable to show search form inline on course page
   - **Placeholder text**: Customize the search box placeholder
6. Save and display

### Searching with Highlighting

1. Open the Course Search activity
2. Enter search terms
3. Optionally select a filter
4. Click **"Search"**
5. **Click on any result** - the page will automatically:
   - Navigate to the content
   - Scroll to the matched text
   - Highlight it with a yellow background for 3 seconds

## Supported Content Types

- Course Sections
- Pages (mod_page)
- Books and Chapters (mod_book)
- Labels (mod_label)
- Forums, Discussions, Posts (mod_forum)
- Wiki Pages (mod_wiki)
- Lesson Pages (mod_lesson)
- All module descriptions

## Performance

- JavaScript only loads on course pages (not site-wide)
- AMD modules are lazy-loaded by Moodle
- No database overhead
- Client-side highlighting only
- Minimal impact: ~3KB minified JavaScript

## Troubleshooting

### Highlighting doesn't work
- Clear browser cache (Ctrl+Shift+Delete)
- Purge Moodle caches: **Site administration → Development → Purge all caches**
- Ensure JavaScript is enabled

### No search results
- Verify content is visible to the user
- Try different search terms or filters

## Version

Current version: **1.0.0** (Stable)

## License

This plugin is licensed under the GNU GPL v3 or later.

## Credits

Original plugin: Yurii Lysak (2025)
Highlighting feature: HNEE (Hochschule für nachhaltige Entwicklung Eberswalde) - December 2025

## Support

For issues, feature requests, or contributions, please contact your Moodle administrator.

