# Prominenz
Das Plugin erweitert das Forum um eine interaktive Liste berühmter Charaktere und Gruppen. Mitglieder können selbst Einträge erstellen. Neu Einträge müssen zunächst durch das Team im ModCP überprüft werden.
Zu jedem Eintrag werden der Name, mindestens eine Tätigkeit - etwa Sänger:in, Schauspieler:in, Sportler:in, Band, usw. - sowie eine oder mehrere Branchen angegeben. Zusätzlich kann ein Beschreibungstext hinzugefügt werden, der wie ein kurzer Wikipedia-Artikel über den Charakter oder die Gruppe wirkt.<br>
<br>
In den Einstellungen kann festgelegt werden, welche Branchen existieren und ob Berühmtheiten mehreren Branchen gleichzeitig zugeordnet werden dürfen.
Auch bei der Darstellung bietet das Plugin verschiedene Möglichkeiten. Die Liste kann als einfache Übersicht mit einer Multipage angezeigt werden oder mit Filtern, über die gezielt nach Einzelpersonen/Gruppen oder bestimmten Branchen gesucht werden kann. Alternativ steht eine Tab-Ansicht zur Verfügung, in der jede Branche als eigener Reiter dargestellt wird.

# Vorrausetzung
- Das ACP Modul <a href="https://github.com/little-evil-genius/rpgstuff_modul" target="_blank">RPG Stuff</a> <b>muss</b> vorhanden sein.
- Der <a href="https://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2" target="_blank">Accountswitcher</a> von doylecc sollte im besten Falle installiert sein. (kein Muss)
- <a href="https://github.com/MyBBStuff/MyAlerts\" target="_blank">MyAlerts</a> von EuanT können installiert sein. (kein Muss)

# Datenbank-Änderungen
hinzugefügte Tabelle:
- PRÄFIX_celebritylist

# Einstellungen
- Branchen
- mehrere Branchen
- Sortierung
- Einträge pro Seite
- Einträge löschen
- Listen PHP
- Listen Menü
- Listen Menü Template

# Neues Templatess (nicht global!) 
- celebritylist
- celebritylist_add
- celebritylist_banner
- celebritylist_bit
- celebritylist_bit_tabs
- celebritylist_bit_tabs_content
- celebritylist_bit_tabs_menu
- celebritylist_edit
- celebritylist_filter
- celebritylist_filter_bit
- celebritylist_modcp
- celebritylist_modcp_bit
- celebritylist_modcp_nav
- celebritylist_options
- celebritylist_user

# Neue Variable
- header: {$celebritylist_newentry}
- modcp_nav_users: {$nav_celebritylist}

# Neues CSS - celebritylist.css
Es wird automatisch in jedes bestehende und neue Design hinzugefügt. Man sollte es einfach einmal abspeichern - auch im Default. Sonst kann es passieren, dass es bei einem Update von MyBB entfernt wird.<br>
Nach einem MyBB Upgrade fehlt der Stylesheets im Masterstyle? Im ACP Modul "RPG Erweiterungen" befindet sich der Menüpunkt "Stylesheets überprüfen" und kann von hinterlegten Plugins den Stylesheet wieder hinzufügen.
<blockquote>
  
     .celebritylist_info {
        padding: 20px 40px;
        text-align: justify;
        line-height: 180%;
        }

        #celebritylist_add {
        width: 80%;
        margin: auto;
        margin-bottom: 20px;
        }

        .celebritylist_user {
        margin-bottom: 20px;
        box-sizing: border-box;
        width: 100%;
        }

        .celebritylist_bit {
        padding: 20px;
        }

        .celebritylist_desc {
        padding: 5px;
        text-align: justify;
        }

        .celebritylist_tab {
        overflow: hidden;
        border: 1px solid #ccc;
        background-color: #f1f1f1;
        }

        .celebritylist_tab button {
        background-color: inherit;
        float: left;
        border: none;
        outline: none;
        cursor: pointer;
        padding: 14px 16px;
        transition: 0.3s;
        }

        .celebritylist_tab button:hover {
        background-color: #ddd;
        }

        .celebritylist_tab button.celebritylist_active {
        background-color: #ccc;
        }

        .celebritylist_tabcontent {
        display: none;
        padding: 6px 12px;
        border: 1px solid #ccc;
        border-top: none;
        }

        .celebritylist-filter {
        background: #f5f5f5;
        margin-bottom: 20px;
        }

        .celebritylist-filter-headline {
        background: #0f0f0f url(../../../images/tcat.png) repeat-x;
        color: #fff;
        border-top: 1px solid #444;
        border-bottom: 1px solid #000;
        padding: 6px;
        font-size: 12px;
        }

        .celebritylist-filteroptions {
        display: flex;
        justify-content: space-around;
        width: 90%;
        margin: 10px auto;
        gap: 5px;
        }

        .celebritylist_filter_bit {
        width: 100%;
        text-align: center;
        }

        .celebritylist_filter_bit-headline {
        padding: 6px;
        background: #ddd;
        color: #666;
        }

        .celebritylist_filter_bit-dropbox {
        margin: 5px;
        }
</blockquote>

# Demo
<img src="https://stormborn.at/plugins/celebrity_add.png">
<img src="https://stormborn.at/plugins/celebrity_filter.png">
<img src="https://stormborn.at/plugins/celebrity_tabs.png">
