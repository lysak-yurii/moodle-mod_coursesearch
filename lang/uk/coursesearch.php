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
 * Ukrainian language strings for coursesearch
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activity'] = 'Активність';
$string['closebuttontitle'] = 'Закрити';
$string['collapsematches'] = 'Згорнути збіги';
$string['content'] = 'вмісті';
$string['coursesearch:addinstance'] = 'Додати новий пошук по курсу';
$string['coursesearch:view'] = 'Переглянути пошук по курсу';
$string['coursesearchsettings'] = 'Налаштування пошуку по курсу';
$string['defaultgrouped'] = 'Групувати результати за розділами за замовчуванням';
$string['defaultgrouped_desc'] = 'Вигляд результатів, коли віджет шукає в курсі без діяльності «Пошук по курсу». Діяльності завжди використовують власне налаштування «Групувати результати за розділами», а нові діяльності беруть це значення як початкове.';
$string['defaultplaceholder'] = 'Пошук по курсу...';
$string['description'] = 'описі';
$string['disabledmodules'] = 'Приховувати віджет у цих діяльностях';
$string['disabledmodules_desc'] = 'Типи діяльностей, на сторінках яких віджет швидкого доступу не показується. Наприклад, виберіть «Тест», щоб віджет не з\'являвся під час спроби тесту, де пошук по всьому курсу може бути небажаним. Це стосується лише сторінок діяльностей — на самій сторінці курсу віджет показується як і раніше.';
$string['displayoptions'] = 'Параметри відображення';
$string['embedded'] = 'Вбудувати в сторінку курсу';
$string['embedded_help'] = 'Якщо увімкнено, панель пошуку буде вбудована безпосередньо на сторінці курсу, замість переходу на окрему сторінку.';
$string['embeddedinfo'] = 'Відображати панель пошуку безпосередньо на сторінці курсу';
$string['enablefloatingwidget'] = 'Увімкнути плаваючий віджет швидкого доступу';
$string['enablefloatingwidget_desc'] = 'Якщо увімкнено, на сторінках курсу з\'явиться плаваючий віджет пошуку, який дозволяє швидко отримати доступ до пошуку по курсу без переходу на сторінку активності пошуку.';
$string['enablehighlight'] = 'Увімкнути прокрутку та підсвічування';
$string['enablehighlight_desc'] = 'Якщо увімкнено, при натисканні на результати пошуку сторінка автоматично прокрутиться до знайденого тексту та підсвітить його на сторінці курсу.';
$string['eventcoursemoduleviewed'] = 'Переглянуто активність пошуку по курсу';
$string['eventcoursesearched'] = 'Пошук по курсу виконано';
$string['excludedplaceholders'] = 'Виключені шаблони плейсхолдерів';
$string['excludedplaceholders_desc'] = 'Шаблони регулярних виразів (по одному на рядок) для внутрішніх плейсхолдерів, які слід виключити з пошуку. Це внутрішні маркери, невидимі для користувачів, і вони не повинні бути доступні для пошуку.

<strong>Довідник символів регулярних виразів:</strong>
<ul>
<li><code>@@</code> - Відповідає буквальним подвійним знакам @</li>
<li><code>[A-Z_]</code> - Відповідає будь-якій великій літері або підкресленню</li>
<li><code>+</code> - Відповідає одному або більше попереднього символу/групи</li>
<li><code>[^\s]</code> - Відповідає будь-якому символу, крім пробілу</li>
<li><code>*</code> - Відповідає нулю або більше попереднього символу/групи</li>
<li><code>\s</code> - Відповідає будь-якому символу пробілу (пробіл, табуляція, новий рядок)</li>
<li><code>^</code> - Всередині квадратних дужок [^...] означає "не" (заперечення)</li>
</ul>

<strong>Приклади:</strong>
<ul>
<li><code>@@[A-Z_]+@@[^\s]*</code> - Виключає будь-який шаблон @@PLACEHOLDER@@ (загальний шаблон, рекомендовано)</li>
<li><code>\{\{[^}]+\}\}</code> - Виключає змінні шаблонів, такі як {{назва_змінної}} (фігурні дужки потрібно екранувати зворотною косою рискою)</li>
</ul>

<strong>Примітка:</strong> Шаблони не чутливі до регістру. Недійсні шаблони будуть пропущені з повідомленням налагодження. Якщо ви видалите всі шаблони, фільтрація плейсхолдерів не буде застосована.';
$string['expandmatches'] = 'Розгорнути збіги';
$string['floatingwidgetscope'] = 'Показувати віджет у';
$string['floatingwidgetscope:allcourses'] = 'Усіх курсах';
$string['floatingwidgetscope:withactivity'] = 'Курсах із діяльністю «Пошук по курсу»';
$string['floatingwidgetscope_desc'] = 'Визначає, де показується віджет швидкого доступу. За варіанта «Курсах із діяльністю Пошук по курсу» він показується лише в курсах, де викладач додав цю діяльність, і використовує її налаштування. За варіанта «Усіх курсах» він також показується в курсах без цієї діяльності та дозволяє пошук по цьому курсу.';
$string['floatingwidgetverticaloffset'] = 'Вертикальне зміщення плаваючого віджета';
$string['floatingwidgetverticaloffset_desc'] = 'Вертикальне зміщення позиції в пікселях від нижнього краю сторінки. Збільште це значення, щоб перемістити віджет вище та уникнути перекриття з іншими елементами сторінки (наприклад, інфокнопкою Moodle).';
$string['floatingwidgetverticaloffset_invalid'] = 'Вертикальне зміщення повинно бути 0 або більше.';
$string['generalsection'] = 'Загальне';
$string['grouped'] = 'Групувати результати за розділами';
$string['grouped_help'] = 'Якщо увімкнено, результати пошуку будуть організовані за розділами курсу. Якщо вимкнено, результати будуть відображатися як плоский список.';
$string['groupedinfo'] = 'Організувати результати пошуку за розділами курсу';
$string['inforum'] = 'У форумі: {$a}';
$string['intro'] = 'вступному розділі';
$string['matchcount'] = '{$a} збігів';
$string['matchdescriptionorcontent'] = 'описі або вмісті';
$string['matchedin'] = 'Знайдено у {$a}';
$string['matchof'] = 'Збіг {$a->index} з {$a->total}';
$string['maxoccurrences'] = 'Максимальна кількість входжень на елемент контенту';
$string['maxoccurrences_desc'] = 'Максимальна кількість входжень, які будуть знайдені на елемент контенту, коли пошуковий термін з\'являється кілька разів. Встановіть 0, щоб вимкнути обмеження та знайти всі входження (не рекомендується для великих курсів, оскільки це може вплинути на продуктивність та створити перевантажені списки результатів).';
$string['maxoccurrences_invalid'] = 'Максимальна кількість входжень повинна бути 0 або більше.';
$string['maxoccurrences_warning'] = 'Попередження: Встановлення цього значення на 0 призведе до пошуку всіх входжень, що може спричинити проблеми з продуктивністю та перевантажені списки результатів у великих курсах.';
$string['missingidandcmid'] = 'Відсутній ID модуля курсу або ID пошуку по курсу';
$string['modulename'] = 'Пошук по курсу';
$string['modulename_help'] = 'Модуль пошуку по курсу дозволяє викладачам додати панель пошуку до курсу, щоб студенти могли шукати вміст курсу.<br><br><a href="https://moodle.org/plugins/mod_coursesearch"><i class="icon fa fa-info-circle" aria-hidden="true"></i> Докладніше на Moodle.org</a>';
$string['modulenameplural'] = 'Пошуки по курсах';
$string['next'] = 'Наступна';
$string['nocourseinstances'] = 'У цьому курсі немає екземплярів пошуку по курсу';
$string['noresults'] = 'Не знайдено результатів для "{$a}"';
$string['pagination'] = 'Сторінки результатів пошуку';
$string['placeholder'] = 'Текст підказки';
$string['placeholder_help'] = 'Текст, який з\'являється в полі пошуку до введення запиту користувачем.';
$string['pluginadministration'] = 'Адміністрування пошуку по курсу';
$string['pluginname'] = 'Пошук по курсу';
$string['previous'] = 'Попередня';
$string['privacy:metadata'] = 'Модуль пошуку по курсу не зберігає жодних особистих даних користувачів. Він зберігає лише конфігурацію екземпляра активності, таку як назва, опис, область пошуку та параметри відображення.';
$string['quicksearch'] = 'Швидкий пошук';
$string['resultsperpage'] = 'Результатів на сторінці';
$string['resultsperpage_desc'] = 'Кількість результатів пошуку, що відображаються на одній сторінці.';
$string['search'] = 'Пошук';
$string['searchcourse'] = 'Пошук по курсу';
$string['searchmodtypes'] = 'Фільтр';
$string['searchmodtypes_help'] = 'Фільтрувати результати пошуку за конкретними типами активностей або ресурсів.';
$string['searchresults'] = 'Результати пошуку для "{$a}"';
$string['searchresultscount'] = 'Знайдено {$a->count} результатів для "{$a->query}"';
$string['searchresultsfor'] = 'Результати пошуку для "{$a}"';
$string['searchresultsrange'] = 'Показано розділи {$a->start}-{$a->end} з {$a->total}';
$string['searchresultsrange_ungrouped'] = 'Показано результати {$a->start}-{$a->end} з {$a->total}';
$string['searchscope'] = 'Область пошуку';
$string['sectionmatch'] = 'Збіг у розділі';
$string['settingsdisplay'] = 'Відображення результатів';
$string['settingshighlighting'] = 'Підсвічування';
$string['settingswidget'] = 'Віджет швидкого доступу';
$string['sitewidesearchdisabled'] = 'Пошук в усіх курсах не увімкнено на цьому сайті.';
$string['subsectionmatch'] = 'Збіг у підрозділі';
$string['title'] = 'назві';
