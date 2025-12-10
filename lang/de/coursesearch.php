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

/**
 * German language strings for coursesearch
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Kurssuche';
$string['modulenameplural'] = 'Kurssuchen';
$string['modulename_help'] = 'Das Kurssuche-Modul ermöglicht es Lehrenden, eine Suchfunktion in ihren Kurs einzubinden, damit Lernende leicht Inhalte im Kurs finden können.';
$string['coursesearch:addinstance'] = 'Eine neue Kurssuche hinzufügen';
$string['coursesearch:view'] = 'Kurssuche anzeigen';
$string['pluginadministration'] = 'Kurssuche-Administration';
$string['pluginname'] = 'Kurssuche';

// Form strings
$string['coursesearchsettings'] = 'Kurssuche-Einstellungen';
$string['searchscope'] = 'Suchbereich';
$string['searchscope_help'] = 'Definieren Sie, welche Inhalte in den Suchergebnissen enthalten sein sollen.';
$string['searchscope_course'] = 'Nur Kursinhalte';
$string['searchscope_activities'] = 'Nur Aktivitäten';
$string['searchscope_resources'] = 'Nur Materialien';
$string['searchscope_forums'] = 'Nur Foren';
$string['searchscope_all'] = 'Alle Kursinhalte';
$string['placeholder'] = 'Platzhaltertext';
$string['placeholder_help'] = 'Der Text, der in der Suchbox erscheint, bevor ein Benutzer eine Anfrage eingibt.';
$string['defaultplaceholder'] = 'Diesen Kurs durchsuchen...';

// Display options
$string['displayoptions'] = 'Anzeigeoptionen';
$string['embedded'] = 'In Kursseite einbetten';
$string['embedded_help'] = 'Wenn aktiviert, wird die Suchleiste direkt in der Kursseite eingebettet, anstatt dass Benutzer auf eine separate Seite klicken müssen.';
$string['embeddedinfo'] = 'Suchleiste direkt auf der Kursseite anzeigen';

// View page strings
$string['search'] = 'Suchen';
$string['searchresultsfor'] = 'Suchergebnisse für "{$a}"';
$string['searchresults'] = 'Suchergebnisse für "{$a}"';
$string['searchresultscount'] = '{$a->count} Ergebnisse gefunden für "{$a->query}"';
$string['noresults'] = 'Keine Ergebnisse gefunden für "{$a}"';
$string['inforum'] = 'Im Forum: {$a}';
$string['matchedin'] = 'Gefunden in {$a}';
$string['title'] = 'Titel';
$string['content'] = 'Inhalt';
$string['description'] = 'Beschreibung';
$string['intro'] = 'Einleitung';
$string['eventcoursesearched'] = 'Kurs durchsucht';

// Capability strings
$string['coursesearch:addinstance'] = 'Eine neue Kurssuche hinzufügen';
$string['coursesearch:view'] = 'Kurssuche anzeigen';

// Error strings
$string['missingidandcmid'] = 'Fehlende Kursmodul-ID oder Kurssuche-ID';
$string['nocourseinstances'] = 'Es gibt keine Kurssuche-Instanzen in diesem Kurs';

// Admin settings
$string['enablehighlight'] = 'Scrollen und Hervorheben aktivieren';
$string['enablehighlight_desc'] = 'Wenn aktiviert, wird beim Klicken auf Suchergebnisse automatisch zum gefundenen Text gescrollt und dieser auf der Kursseite hervorgehoben.';
