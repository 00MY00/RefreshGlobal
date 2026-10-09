<?php

/*
 * Corrections of Refresh's phone strings (dictionary of its scripts, Modules/Refresh/Resources/lang/<locale>.json,
 * written in <head> as meta "refresh-l10n" by RefreshServiceProvider.php:77-86 and read by rfT() in
 * Public/js/mobile.js:15-21). Refresh's file is not modified: the module corrects the dictionary in the page, and
 * only while a string still has the wrong value below ("from"), so a later fix in Refresh always wins.
 *
 * locale => [key => [from, to]]
 */
return [
    'fr' => [
        // mobile.js:612-614: "Créé 7h il y a" -> "Créé il y a 7h"
        'Created :time ago' => ['Créé :time il y a', 'Créé il y a :time'],
        'Closed :time ago'  => ['Fermé :time il y a', 'Fermé il y a :time'],
    ],
];
