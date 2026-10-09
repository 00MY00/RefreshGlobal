<?php

return [
    'env' => [
        'check'  => 'Versión de FreeScout dentro del rango probado',
        'label'  => 'Versión de FreeScout fuera del rango probado.',
        'effect' => 'La página funciona pero no se ha probado con esta versión de FreeScout.',
        'action' => 'Consulte COMPATIBILITY.md y la última versión de RefreshGlobal, luego ejecute php artisan refreshglobal:check.',
    ],
    'ref_present' => [
        'check'  => 'Módulo Refresh presente y activo',
        'label'  => 'El módulo Refresh no está instalado o no está activo.',
        'effect' => 'La página «Todos los buzones» se muestra con el aspecto estándar de FreeScout.',
        'action' => 'Instale y active Refresh (Administrar › Módulos) o conserve el aspecto estándar. Luego ejecute php artisan refreshglobal:check.',
    ],
    'ref_version' => [
        'check'  => 'Versión de Refresh dentro del rango probado',
        'label'  => 'Versión de Refresh fuera del rango probado.',
        'effect' => 'La página funciona; su aspecto puede diferir ligeramente de las páginas de Refresh.',
        'action' => 'Consulte COMPATIBILITY.md y la última versión de RefreshGlobal, luego ejecute php artisan refreshglobal:check.',
    ],
    'css' => [
        'check'  => 'Hojas de estilo de Refresh presentes',
        'label'  => 'No se encuentra la hoja de estilo de Refresh.',
        'effect' => 'La página «Todos los buzones» se muestra con el aspecto estándar de FreeScout.',
        'action' => 'Compruebe la versión de Refresh instalada, luego ejecute php artisan refreshglobal:check.',
    ],
    'js' => [
        'check'  => 'Scripts de Refresh presentes',
        'label'  => 'No se encuentran los scripts de Refresh.',
        'effect' => 'La página «Todos los buzones» se muestra con el aspecto estándar de FreeScout.',
        'action' => 'Compruebe la versión de Refresh instalada, luego ejecute php artisan refreshglobal:check.',
    ],
    'view' => [
        'check'           => 'La vista :item existe',
        'label'           => 'No se encuentra una vista usada por la página: :item.',
        'effect_blocking' => 'La lista de tickets no se puede mostrar.',
        'effect'          => 'La página se muestra con el aspecto estándar de FreeScout.',
        'action'          => 'Actualice RefreshGlobal a una versión prevista para esta versión de FreeScout / Refresh (COMPATIBILITY.md), luego ejecute php artisan refreshglobal:check.',
    ],
    'hook' => [
        'check'  => 'El hook «:item» lo dispara :file',
        'label'  => 'El hook «:item» ya no lo dispara :file.',
        'effect' => 'Falta el elemento añadido por este hook (entrada del menú, columna «Buzón» o icono de la barra lateral). La lista funciona.',
        'action' => 'Actualice RefreshGlobal a una versión prevista para esta versión de FreeScout / Refresh, luego ejecute php artisan refreshglobal:check.',
    ],
    'core' => [
        'check'  => 'Código de FreeScout presente: :item',
        'label'  => 'Falta una clase, método o constante de FreeScout usada por el módulo: :item.',
        'effect' => 'La lista de tickets no se muestra, para no enseñar ningún ticket no autorizado.',
        'action' => 'Actualice RefreshGlobal a una versión prevista para esta versión de FreeScout (COMPATIBILITY.md), luego ejecute php artisan refreshglobal:check.',
    ],
    'route' => [
        'check'  => 'La ruta :item existe',
        'label'  => 'No se encuentra una ruta usada por el módulo: :item.',
        'effect' => 'La lista de tickets no se muestra: sus enlaces no llevarían a ninguna parte.',
        'action' => 'Vacíe la caché (php artisan freescout:clear-cache), luego ejecute php artisan refreshglobal:check. Si persiste, actualice RefreshGlobal.',
    ],
    'db' => [
        'check'  => 'Tabla y columnas presentes: :item',
        'label'  => 'Falta una tabla o columna de FreeScout usada por el módulo: :item.',
        'effect' => 'La lista de tickets no se muestra.',
        'action' => 'Ejecute php artisan migrate (o Administrar › Sistema › Herramientas › Migrar BD), luego php artisan refreshglobal:check.',
    ],
    'db_own' => [
        'check'  => 'Tabla de vistas guardadas presente: :item',
        'label'  => 'Falta la tabla del módulo para las vistas guardadas: :item.',
        'effect' => 'La lista funciona; las vistas guardadas están desactivadas.',
        'action' => 'Ejecute php artisan migrate (o Administrar › Sistema › Herramientas › Migrar BD), luego php artisan refreshglobal:check.',
    ],
    'acl' => [
        'check'  => 'Reglas de acceso a buzones disponibles (:item)',
        'label'  => 'La lista de FreeScout de los buzones visibles para un usuario (User::mailboxesCanView) no está disponible.',
        'effect' => 'La lista de tickets no se muestra, para no enseñar ningún ticket no autorizado.',
        'action' => 'Actualice RefreshGlobal a una versión prevista para esta versión de FreeScout, luego ejecute php artisan refreshglobal:check.',
    ],
    'acl_assigned' => [
        'check'  => 'Regla «solo conversaciones asignadas» disponible (:item)',
        'label'  => 'El permiso de FreeScout «solo conversaciones asignadas» (User::canSeeOnlyAssignedConversations) no está disponible.',
        'effect' => 'La lista de tickets no se muestra, para no enseñar ningún ticket no autorizado.',
        'action' => 'Actualice RefreshGlobal a una versión prevista para esta versión de FreeScout, luego ejecute php artisan refreshglobal:check.',
    ],
    'check_error' => [
        'check'  => 'La comprobación :item se ejecuta sin error',
        'label'  => 'Una comprobación de compatibilidad ha fallado de forma inesperada: :item.',
        'effect' => 'Por precaución, la lista de tickets no se muestra.',
        'action' => 'Lea storage/logs/laravel.log, luego ejecute php artisan refreshglobal:check.',
    ],
    'error' => [
        'check'  => 'Página construida sin error',
        'label'  => 'Error inesperado al construir la página.',
        'effect' => 'La lista de tickets no se muestra.',
        'action' => 'Lea storage/logs/laravel.log (código RG-ERR-01), luego ejecute php artisan refreshglobal:check.',
    ],
];
