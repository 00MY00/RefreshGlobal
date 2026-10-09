<?php

return [
    'env' => [
        'check'  => 'FreeScout-versie binnen het geteste bereik',
        'label'  => 'FreeScout-versie buiten het geteste bereik.',
        'effect' => 'De pagina werkt, maar is niet getest met deze FreeScout-versie.',
        'action' => 'Raadpleeg COMPATIBILITY.md en de nieuwste RefreshGlobal-versie en voer daarna php artisan refreshglobal:check uit.',
    ],
    'ref_present' => [
        'check'  => 'Refresh-module aanwezig en actief',
        'label'  => 'De Refresh-module is niet geïnstalleerd of niet actief.',
        'effect' => 'De pagina „Alle mailboxen” wordt in de standaardstijl van FreeScout weergegeven.',
        'action' => 'Installeer en activeer Refresh (Beheren › Modules) of behoud de standaardstijl. Voer daarna php artisan refreshglobal:check uit.',
    ],
    'ref_version' => [
        'check'  => 'Refresh-versie binnen het geteste bereik',
        'label'  => 'Refresh-versie buiten het geteste bereik.',
        'effect' => 'De pagina werkt; de weergave kan licht afwijken van de pagina’s van Refresh.',
        'action' => 'Raadpleeg COMPATIBILITY.md en de nieuwste RefreshGlobal-versie en voer daarna php artisan refreshglobal:check uit.',
    ],
    'css' => [
        'check'  => 'Stylesheets van Refresh aanwezig',
        'label'  => 'De stylesheet van Refresh is niet gevonden.',
        'effect' => 'De pagina „Alle mailboxen” wordt in de standaardstijl van FreeScout weergegeven.',
        'action' => 'Controleer de geïnstalleerde versie van Refresh en voer daarna php artisan refreshglobal:check uit.',
    ],
    'js' => [
        'check'  => 'Scripts van Refresh aanwezig',
        'label'  => 'De scripts van Refresh zijn niet gevonden.',
        'effect' => 'De pagina „Alle mailboxen” wordt in de standaardstijl van FreeScout weergegeven.',
        'action' => 'Controleer de geïnstalleerde versie van Refresh en voer daarna php artisan refreshglobal:check uit.',
    ],
    'view' => [
        'check'           => 'Weergave :item bestaat',
        'label'           => 'Een door de pagina gebruikte weergave is niet gevonden: :item.',
        'effect_blocking' => 'De ticketlijst kan niet worden weergegeven.',
        'effect'          => 'De pagina wordt in de standaardstijl van FreeScout weergegeven.',
        'action'          => 'Werk RefreshGlobal bij naar een versie voor deze FreeScout-/Refresh-versie (COMPATIBILITY.md) en voer daarna php artisan refreshglobal:check uit.',
    ],
    'hook' => [
        'check'  => 'Hook „:item” wordt aangeroepen door :file',
        'label'  => 'De hook „:item” wordt niet meer aangeroepen door :file.',
        'effect' => 'Het via deze hook toegevoegde element (menu-item, kolom „Mailbox” of pictogram in de zijbalk) ontbreekt. De lijst werkt.',
        'action' => 'Werk RefreshGlobal bij naar een versie voor deze FreeScout-/Refresh-versie en voer daarna php artisan refreshglobal:check uit.',
    ],
    'core' => [
        'check'  => 'FreeScout-code aanwezig: :item',
        'label'  => 'Een door de module gebruikte klasse, methode of constante van FreeScout ontbreekt: :item.',
        'effect' => 'De ticketlijst wordt niet weergegeven, zodat geen enkel onbevoegd ticket zichtbaar wordt.',
        'action' => 'Werk RefreshGlobal bij naar een versie voor deze FreeScout-versie (COMPATIBILITY.md) en voer daarna php artisan refreshglobal:check uit.',
    ],
    'route' => [
        'check'  => 'Route :item bestaat',
        'label'  => 'Een door de module gebruikte route is niet gevonden: :item.',
        'effect' => 'De ticketlijst wordt niet weergegeven: de links zouden nergens naartoe leiden.',
        'action' => 'Leeg de cache (php artisan freescout:clear-cache) en voer daarna php artisan refreshglobal:check uit. Blijft het probleem bestaan, werk RefreshGlobal dan bij.',
    ],
    'db' => [
        'check'  => 'Tabel en kolommen aanwezig: :item',
        'label'  => 'Een door de module gebruikte FreeScout-tabel of -kolom ontbreekt: :item.',
        'effect' => 'De ticketlijst wordt niet weergegeven.',
        'action' => 'Voer php artisan migrate uit (of Beheren › Systeem › Hulpmiddelen › Database migreren) en daarna php artisan refreshglobal:check.',
    ],
    'db_own' => [
        'check'  => 'Tabel met opgeslagen weergaven aanwezig: :item',
        'label'  => 'De tabel van de module voor opgeslagen weergaven ontbreekt: :item.',
        'effect' => 'De lijst werkt; opgeslagen weergaven zijn uitgeschakeld.',
        'action' => 'Voer php artisan migrate uit (of Beheren › Systeem › Hulpmiddelen › Database migreren) en daarna php artisan refreshglobal:check.',
    ],
    'acl' => [
        'check'  => 'Toegangsregels voor mailboxen beschikbaar (:item)',
        'label'  => 'De FreeScout-lijst van mailboxen die een gebruiker mag zien (User::mailboxesCanView) is niet beschikbaar.',
        'effect' => 'De ticketlijst wordt niet weergegeven, zodat geen enkel onbevoegd ticket zichtbaar wordt.',
        'action' => 'Werk RefreshGlobal bij naar een versie voor deze FreeScout-versie en voer daarna php artisan refreshglobal:check uit.',
    ],
    'acl_assigned' => [
        'check'  => 'Regel „alleen toegewezen gesprekken” beschikbaar (:item)',
        'label'  => 'De FreeScout-machtiging „alleen toegewezen gesprekken” (User::canSeeOnlyAssignedConversations) is niet beschikbaar.',
        'effect' => 'De ticketlijst wordt niet weergegeven, zodat geen enkel onbevoegd ticket zichtbaar wordt.',
        'action' => 'Werk RefreshGlobal bij naar een versie voor deze FreeScout-versie en voer daarna php artisan refreshglobal:check uit.',
    ],
    'check_error' => [
        'check'  => 'Controle :item zonder fouten uitgevoerd',
        'label'  => 'Een compatibiliteitscontrole is onverwacht mislukt: :item.',
        'effect' => 'Uit voorzorg wordt de ticketlijst niet weergegeven.',
        'action' => 'Lees storage/logs/laravel.log en voer daarna php artisan refreshglobal:check uit.',
    ],
    'error' => [
        'check'  => 'Pagina zonder fouten opgebouwd',
        'label'  => 'Onverwachte fout bij het opbouwen van de pagina.',
        'effect' => 'De ticketlijst wordt niet weergegeven.',
        'action' => 'Lees storage/logs/laravel.log (code RG-ERR-01) en voer daarna php artisan refreshglobal:check uit.',
    ],
];
