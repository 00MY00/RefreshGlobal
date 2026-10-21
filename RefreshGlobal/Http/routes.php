<?php

/*
 * Routes of the "All mailboxes" page. Same pattern as the Refresh module (Modules/Refresh/Http/routes.php:3):
 * "web" group, FreeScout's subdirectory prefix (app/Misc/Helper.php:1398), "auth" + FreeScout's "roles" middleware
 * (app/Http/Kernel.php:78).
 */
Route::group([
    'middleware' => 'web',
    'prefix'     => \Helper::getSubdirectory().'/refresh-global',
    'namespace'  => 'Modules\RefreshGlobal\Http\Controllers',
], function () {
    $users = ['middleware' => ['auth', 'roles'], 'roles' => ['admin', 'user']];
    $admins = ['middleware' => ['auth', 'roles'], 'roles' => ['admin']];

    Route::get('/', $users + ['uses' => 'GlobalTicketsController@home'])->name('refreshglobal.home');
    Route::get('/tickets', $users + ['uses' => 'GlobalTicketsController@index'])->name('refreshglobal.tickets');
    Route::get('/export', $users + ['uses' => 'ExportController@export'])->name('refreshglobal.export');
    Route::get('/state', $users + ['uses' => 'GlobalTicketsController@state'])->name('refreshglobal.state');
    // search provider of the FreeScout Interop contract (JSON, read-only; rights applied by Services/Search/GlobalSearch.php)
    Route::get('/search', $users + ['uses' => 'SearchController@search'])->name('refreshglobal.search');
    // automatic folders of Workflow Packs (when installed): overview
    Route::get('/folders', $users + ['uses' => 'AutoFoldersController@index'])->name('refreshglobal.folders');
    // details and raw text of a message (permission checked by the controller: the user can view the ticket)
    Route::get('/thread/{id}/details', $users + ['uses' => 'ThreadDetailsController@show'])->where('id', '[0-9]+')->name('refreshglobal.thread_details');
    Route::post('/language', $users + ['uses' => 'LanguageController@update'])->name('refreshglobal.language');
    // permission checked by the controller (admins, or users allowed to delete conversations)
    Route::post('/trash/empty', $users + ['uses' => 'TrashController@empty'])->name('refreshglobal.trash.empty');

    // e-mails of a ticket moved to a folder of the mail server (rights checked per ticket by the controller)
    Route::get('/move/folders', $users + ['uses' => 'MailMoveController@folders'])->name('refreshglobal.move.folders');
    Route::post('/move', $users + ['uses' => 'MailMoveController@move'])->name('refreshglobal.move');
    Route::post('/move/undo', $users + ['uses' => 'MailMoveController@undo'])->name('refreshglobal.move.undo');

    // saved views: who may see / edit / share each one is checked by Services/SavedViews.php (404 / 403)
    Route::get('/views', $users + ['uses' => 'SavedViewsController@index'])->name('refreshglobal.views');
    Route::post('/views', $users + ['uses' => 'SavedViewsController@store'])->name('refreshglobal.views.store');
    Route::post('/views/import', $users + ['uses' => 'SavedViewsController@import'])->name('refreshglobal.views.import');
    Route::post('/views/{id}/update', $users + ['uses' => 'SavedViewsController@update'])->where('id', '[0-9]+')->name('refreshglobal.views.update');
    Route::post('/views/{id}/rename', $users + ['uses' => 'SavedViewsController@rename'])->where('id', '[0-9]+')->name('refreshglobal.views.rename');
    Route::post('/views/{id}/share', $users + ['uses' => 'SavedViewsController@share'])->where('id', '[0-9]+')->name('refreshglobal.views.share');
    Route::post('/views/{id}/default', $users + ['uses' => 'SavedViewsController@setDefault'])->where('id', '[0-9]+')->name('refreshglobal.views.default');
    Route::post('/views/{id}/pin', $users + ['uses' => 'SavedViewsController@pin'])->where('id', '[0-9]+')->name('refreshglobal.views.pin');
    Route::post('/views/{id}/duplicate', $users + ['uses' => 'SavedViewsController@duplicate'])->where('id', '[0-9]+')->name('refreshglobal.views.duplicate');
    Route::post('/views/{id}/move', $users + ['uses' => 'SavedViewsController@move'])->where('id', '[0-9]+')->name('refreshglobal.views.move');
    Route::get('/views/{id}/export', $users + ['uses' => 'SavedViewsController@export'])->where('id', '[0-9]+')->name('refreshglobal.views.export');
    Route::delete('/views/{id}', $users + ['uses' => 'SavedViewsController@destroy'])->where('id', '[0-9]+')->name('refreshglobal.views.destroy');

    Route::get('/diagnostic', $admins + ['uses' => 'DiagnosticController@index'])->name('refreshglobal.diagnostic');
    Route::post('/mail-tags/recalc', $admins + ['uses' => 'MailTagsController@recalc'])->name('refreshglobal.mail_tags.recalc');
    Route::post('/navigation', $admins + ['uses' => 'DiagnosticController@navigation'])->name('refreshglobal.navigation');
});
