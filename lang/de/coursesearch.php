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

$string['collapsematches'] = 'Treffer einklappen';
$string['content'] = 'Inhalt';
$string['coursesearch:addinstance'] = 'Eine neue Kurssuche hinzufügen';
$string['coursesearch:view'] = 'Kurssuche anzeigen';
$string['coursesearchsettings'] = 'Kurssuche-Einstellungen';
$string['defaultplaceholder'] = 'Diesen Kurs durchsuchen...';
$string['description'] = 'Beschreibung';
$string['displayoptions'] = 'Anzeigeoptionen';
$string['embedded'] = 'In Kursseite einbetten';
$string['embedded_help'] = 'Wenn aktiviert, wird die Suchleiste direkt in der Kursseite eingebettet, anstatt dass Benutzer auf eine separate Seite klicken müssen.';
$string['embeddedinfo'] = 'Suchleiste direkt auf der Kursseite anzeigen';
$string['enablefloatingwidget'] = 'Schwebendes Schnellzugriffs-Widget aktivieren';
$string['enablefloatingwidget_desc'] = 'Wenn aktiviert, erscheint ein schwebendes Such-Widget auf Kursseiten, das schnellen Zugriff auf die Kurssuche ermöglicht, ohne zur Suchaktivitätsseite zu navigieren.';
$string['enablehighlight'] = 'Scrollen und Hervorheben aktivieren';
$string['enablehighlight_desc'] = 'Wenn aktiviert, wird beim Klicken auf Suchergebnisse automatisch zum gefundenen Text gescrollt und dieser auf der Kursseite hervorgehoben.';
$string['eventcoursesearched'] = 'Kurs durchsucht';
$string['excludedplaceholders'] = 'Ausgeschlossene Platzhalter-Muster';
$string['excludedplaceholders_desc'] = 'Reguläre Ausdrucksmuster (eine pro Zeile) für interne Platzhalter, die von der Suche ausgeschlossen werden sollen. Dies sind interne Markierungen, die für Benutzer nicht sichtbar sind und nicht durchsuchbar sein sollten.

<strong>Regex-Symbol-Leitfaden:</strong>
<ul>
<li><code>@@</code> - Entspricht wörtlichen doppelten @-Zeichen</li>
<li><code>[A-Z_]</code> - Entspricht jedem Großbuchstaben oder Unterstrich</li>
<li><code>+</code> - Entspricht einem oder mehreren des vorhergehenden Zeichens/Gruppe</li>
<li><code>[^\s]</code> - Entspricht jedem Zeichen außer Leerzeichen</li>
<li><code>*</code> - Entspricht null oder mehreren des vorhergehenden Zeichens/Gruppe</li>
<li><code>\s</code> - Entspricht jedem Leerzeichen (Leerzeichen, Tabulator, Zeilenumbruch)</li>
<li><code>^</code> - Innerhalb von Klammern [^...] bedeutet "nicht" (Negation)</li>
</ul>

<strong>Beispiele:</strong>
<ul>
<li><code>@@[A-Z_]+@@[^\s]*</code> - Schließt jedes @@PLATZHALTER@@-Muster aus (allgemeines Muster, empfohlen)</li>
<li><code>\{\{[^}]+\}\}</code> - Schließt Template-Variablen wie {{variablen_name}} aus (geschweifte Klammern müssen mit Backslash maskiert werden)</li>
</ul>

<strong>Hinweis:</strong> Muster sind nicht zwischen Groß- und Kleinschreibung unterscheidend. Ungültige Muster werden mit einer Debug-Nachricht übersprungen. Wenn Sie alle Muster entfernen, wird keine Platzhalter-Filterung angewendet.';
$string['expandmatches'] = 'Treffer erweitern';
$string['floatingwidgetverticaloffset'] = 'Vertikaler Versatz des schwebenden Widgets';
$string['floatingwidgetverticaloffset_desc'] = 'Vertikaler Positionsversatz in Pixeln vom unteren Seitenrand. Erhöhen Sie diesen Wert, um das Widget höher zu positionieren und Überlappungen mit anderen Seitenelementen (z. B. Moodles Infobutton) zu vermeiden.';
$string['floatingwidgetverticaloffset_invalid'] = 'Vertikaler Versatz muss 0 oder größer sein.';
$string['generalsection'] = 'Allgemein';
$string['grouped'] = 'Ergebnisse nach Abschnitten gruppieren';
$string['grouped_help'] = 'Wenn aktiviert, werden Suchergebnisse nach Kursabschnitten organisiert. Wenn deaktiviert, werden Ergebnisse als flache Liste angezeigt.';
$string['groupedinfo'] = 'Suchergebnisse nach Kursabschnitten organisieren';
$string['inforum'] = 'Im Forum: {$a}';
$string['intro'] = 'Einleitung';
$string['matchcount'] = '{$a} Treffer';
$string['matchdescriptionorcontent'] = 'Beschreibung oder Inhalt';
$string['matchedin'] = 'Gefunden in {$a}';
$string['matchof'] = 'Treffer {$a->index} von {$a->total}';
$string['maxoccurrences'] = 'Maximale Vorkommen pro Inhaltselement';
$string['maxoccurrences_desc'] = 'Maximale Anzahl von Vorkommen, die pro Inhaltselement gefunden werden, wenn ein Suchbegriff mehrfach vorkommt. Auf 0 setzen, um das Limit zu deaktivieren und alle Vorkommen zu finden (nicht empfohlen für große Kurse, da dies die Leistung beeinträchtigen und überwältigende Ergebnislisten erstellen kann).';
$string['maxoccurrences_invalid'] = 'Maximale Vorkommen müssen 0 oder größer sein.';
$string['maxoccurrences_warning'] = 'Warnung: Wenn Sie dies auf 0 setzen, werden alle Vorkommen gefunden, was in großen Kursen zu Leistungsproblemen und überwältigenden Ergebnislisten führen kann.';
$string['missingidandcmid'] = 'Fehlende Kursmodul-ID oder Kurssuche-ID';
$string['modulename'] = 'Kurssuche';
$string['modulename_help'] = 'Das Kurssuche-Modul ermöglicht es Lehrenden, eine Suchfunktion in ihren Kurs einzubinden, damit Lernende leicht Inhalte im Kurs finden können.';
$string['modulenameplural'] = 'Kurssuchen';
$string['next'] = 'Weiter';
$string['nocourseinstances'] = 'Es gibt keine Kurssuche-Instanzen in diesem Kurs';
$string['noresults'] = 'Keine Ergebnisse gefunden für "{$a}"';
$string['pagination'] = 'Suchergebnis-Paginierung';
$string['placeholder'] = 'Platzhaltertext';
$string['placeholder_help'] = 'Der Text, der in der Suchbox erscheint, bevor ein Benutzer eine Anfrage eingibt.';
$string['pluginadministration'] = 'Kurssuche-Administration';
$string['pluginname'] = 'Kurssuche';
$string['previous'] = 'Zurück';
$string['privacy:metadata'] = 'Das Kurssuche-Modul speichert keine persönlichen Benutzerdaten. Es speichert nur Aktivitätsinstanz-Konfigurationen wie Name, Beschreibung, Suchbereich und Anzeigeoptionen.';
$string['quicksearch'] = 'Schnellsuche';
$string['resultsperpage'] = 'Ergebnisse pro Seite';
$string['resultsperpage_desc'] = 'Die Anzahl der Suchergebnisse, die pro Seite angezeigt werden.';
$string['search'] = 'Suchen';
$string['searchresults'] = 'Suchergebnisse für "{$a}"';
$string['searchresultscount'] = '{$a->count} Ergebnisse gefunden für "{$a->query}"';
$string['searchresultsfor'] = 'Suchergebnisse für "{$a}"';
$string['searchresultsrange'] = 'Zeige Abschnitte {$a->start}-{$a->end} von {$a->total}';
$string['searchresultsrange_ungrouped'] = 'Zeige Ergebnisse {$a->start}-{$a->end} von {$a->total}';
$string['searchscope'] = 'Suchbereich';
$string['searchscope_activities'] = 'Nur Aktivitäten';
$string['searchscope_all'] = 'Alle Kursinhalte';
$string['searchscope_course'] = 'Nur Kursinhalte';
$string['searchscope_forums'] = 'Nur Foren';
$string['searchscope_help'] = 'Definieren Sie, welche Inhalte in den Suchergebnissen enthalten sein sollen.';
$string['searchscope_resources'] = 'Nur Materialien';
$string['sectionmatch'] = 'Abschnitts-Treffer';
$string['subsectionmatch'] = 'Unterabschnitts-Treffer';
$string['title'] = 'Titel';
