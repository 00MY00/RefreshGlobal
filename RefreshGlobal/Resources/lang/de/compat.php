<?php

return [
    'env' => [
        'check'  => 'FreeScout-Version im getesteten Bereich',
        'label'  => 'FreeScout-Version außerhalb des getesteten Bereichs.',
        'effect' => 'Die Seite funktioniert, wurde aber mit dieser FreeScout-Version nicht getestet.',
        'action' => 'COMPATIBILITY.md und die neueste RefreshGlobal-Version prüfen, dann php artisan refreshglobal:check ausführen.',
    ],
    'ref_present' => [
        'check'  => 'Refresh-Modul vorhanden und aktiv',
        'label'  => 'Das Refresh-Modul ist nicht installiert oder nicht aktiv.',
        'effect' => 'Die Seite „Alle Postfächer“ wird im Standard-Aussehen von FreeScout angezeigt.',
        'action' => 'Refresh installieren und aktivieren (Verwalten › Module) oder das Standard-Aussehen beibehalten. Dann php artisan refreshglobal:check ausführen.',
    ],
    'ref_version' => [
        'check'  => 'Refresh-Version im getesteten Bereich',
        'label'  => 'Refresh-Version außerhalb des getesteten Bereichs.',
        'effect' => 'Die Seite funktioniert; ihr Aussehen kann leicht von den Refresh-Seiten abweichen.',
        'action' => 'COMPATIBILITY.md und die neueste RefreshGlobal-Version prüfen, dann php artisan refreshglobal:check ausführen.',
    ],
    'css' => [
        'check'  => 'Refresh-Stylesheets vorhanden',
        'label'  => 'Das Stylesheet von Refresh wurde nicht gefunden.',
        'effect' => 'Die Seite „Alle Postfächer“ wird im Standard-Aussehen von FreeScout angezeigt.',
        'action' => 'Die installierte Refresh-Version prüfen, dann php artisan refreshglobal:check ausführen.',
    ],
    'js' => [
        'check'  => 'Refresh-Skripte vorhanden',
        'label'  => 'Die Skripte von Refresh wurden nicht gefunden.',
        'effect' => 'Die Seite „Alle Postfächer“ wird im Standard-Aussehen von FreeScout angezeigt.',
        'action' => 'Die installierte Refresh-Version prüfen, dann php artisan refreshglobal:check ausführen.',
    ],
    'view' => [
        'check'           => 'Ansicht :item vorhanden',
        'label'           => 'Eine von der Seite verwendete Ansicht wurde nicht gefunden: :item.',
        'effect_blocking' => 'Die Ticketliste kann nicht angezeigt werden.',
        'effect'          => 'Die Seite wird im Standard-Aussehen von FreeScout angezeigt.',
        'action'          => 'RefreshGlobal auf eine für diese FreeScout-/Refresh-Version vorgesehene Version aktualisieren (COMPATIBILITY.md), dann php artisan refreshglobal:check ausführen.',
    ],
    'hook' => [
        'check'  => 'Hook „:item“ wird von :file ausgelöst',
        'label'  => 'Der Hook „:item“ wird von :file nicht mehr ausgelöst.',
        'effect' => 'Das über diesen Hook hinzugefügte Element (Menüeintrag, Spalte „Postfach“ oder Symbol der Seitenleiste) fehlt. Die Liste funktioniert.',
        'action' => 'RefreshGlobal auf eine für diese FreeScout-/Refresh-Version vorgesehene Version aktualisieren, dann php artisan refreshglobal:check ausführen.',
    ],
    'core' => [
        'check'  => 'FreeScout-Code vorhanden: :item',
        'label'  => 'Eine vom Modul verwendete Klasse, Methode oder Konstante von FreeScout fehlt: :item.',
        'effect' => 'Die Ticketliste wird nicht angezeigt, damit kein unberechtigtes Ticket sichtbar wird.',
        'action' => 'RefreshGlobal auf eine für diese FreeScout-Version vorgesehene Version aktualisieren (COMPATIBILITY.md), dann php artisan refreshglobal:check ausführen.',
    ],
    'route' => [
        'check'  => 'Route :item vorhanden',
        'label'  => 'Eine vom Modul verwendete Route wurde nicht gefunden: :item.',
        'effect' => 'Die Ticketliste wird nicht angezeigt: Ihre Links würden ins Leere führen.',
        'action' => 'Cache leeren (php artisan freescout:clear-cache), dann php artisan refreshglobal:check ausführen. Falls es weiterhin auftritt, RefreshGlobal aktualisieren.',
    ],
    'db' => [
        'check'  => 'Tabelle und Spalten vorhanden: :item',
        'label'  => 'Eine vom Modul verwendete FreeScout-Tabelle oder -Spalte fehlt: :item.',
        'effect' => 'Die Ticketliste wird nicht angezeigt.',
        'action' => 'php artisan migrate ausführen (oder Verwalten › System › Werkzeuge › Datenbank migrieren), dann php artisan refreshglobal:check.',
    ],
    'db_own' => [
        'check'  => 'Tabelle der gespeicherten Ansichten vorhanden: :item',
        'label'  => 'Die Tabelle des Moduls für gespeicherte Ansichten fehlt: :item.',
        'effect' => 'Die Liste funktioniert; gespeicherte Ansichten sind deaktiviert.',
        'action' => 'php artisan migrate ausführen (oder Verwalten › System › Werkzeuge › Datenbank migrieren), dann php artisan refreshglobal:check.',
    ],
    'acl' => [
        'check'  => 'Zugriffsregeln für Postfächer verfügbar (:item)',
        'label'  => 'Die FreeScout-Liste der für einen Benutzer sichtbaren Postfächer (User::mailboxesCanView) ist nicht verfügbar.',
        'effect' => 'Die Ticketliste wird nicht angezeigt, damit kein unberechtigtes Ticket sichtbar wird.',
        'action' => 'RefreshGlobal auf eine für diese FreeScout-Version vorgesehene Version aktualisieren, dann php artisan refreshglobal:check ausführen.',
    ],
    'acl_assigned' => [
        'check'  => 'Regel „nur zugewiesene Konversationen“ verfügbar (:item)',
        'label'  => 'Die FreeScout-Berechtigung „nur zugewiesene Konversationen“ (User::canSeeOnlyAssignedConversations) ist nicht verfügbar.',
        'effect' => 'Die Ticketliste wird nicht angezeigt, damit kein unberechtigtes Ticket sichtbar wird.',
        'action' => 'RefreshGlobal auf eine für diese FreeScout-Version vorgesehene Version aktualisieren, dann php artisan refreshglobal:check ausführen.',
    ],
    'check_error' => [
        'check'  => 'Prüfung :item ohne Fehler ausgeführt',
        'label'  => 'Eine Kompatibilitätsprüfung ist unerwartet fehlgeschlagen: :item.',
        'effect' => 'Die Ticketliste wird vorsorglich nicht angezeigt.',
        'action' => 'storage/logs/laravel.log lesen, dann php artisan refreshglobal:check ausführen.',
    ],
    'error' => [
        'check'  => 'Seite ohne Fehler erstellt',
        'label'  => 'Unerwarteter Fehler beim Aufbau der Seite.',
        'effect' => 'Die Ticketliste wird nicht angezeigt.',
        'action' => 'storage/logs/laravel.log lesen (Code RG-ERR-01), dann php artisan refreshglobal:check ausführen.',
    ],
];
