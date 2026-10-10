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
    Route::post('/language', $users + ['uses' => 'LanguageController@update'])->name('refreshglobal.language');
    // permission checked by the controller (admins, or users allowed to delete conversations)
    Route::post('/trash/empty', $users + ['uses' => 'TrashController@empty'])->name('refreshglobal.trash.empty');

    Route::post('/views', $users + ['uses' => 'SavedViewsController@store'])->name('refreshglobal.views.store');
    Route::post('/views/{id}/rename', $users + ['uses' => 'SavedViewsController@rename'])->name('refreshglobal.views.rename');
    Route::post('/views/{id}/default', $users + ['uses' => 'SavedViewsController@setDefault'])->name('refreshglobal.views.default');
    Route::delete('/views/{id}', $users + ['uses' => 'SavedViewsController@destroy'])->name('refreshglobal.views.destroy');

    Route::get('/diagnostic', $admins + ['uses' => 'DiagnosticController@index'])->name('refreshglobal.diagnostic');
    Route::post('/auto-update', $admins + ['uses' => 'DiagnosticController@autoUpdate'])->name('refreshglobal.auto_update');
    Route::post('/update-now', $admins + ['uses' => 'DiagnosticController@updateNow'])->name('refreshglobal.update_now');
    Route::post('/check-update', $admins + ['uses' => 'DiagnosticController@checkUpdate'])->name('refreshglobal.check_update');
    Route::post('/navigation', $admins + ['uses' => 'DiagnosticController@navigation'])->name('refreshglobal.navigation');
});
