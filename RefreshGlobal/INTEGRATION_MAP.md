# INTEGRATION_MAP — audit d'intégration de RefreshGlobal

Rapport de la phase 0 : tout élément de FreeScout ou de Refresh utilisé par le module, avec sa référence
`fichier:ligne` dans le code réel. Rien n'a été supposé : chaque élément a été lu dans les sources ci-dessous.

| Code audité | Version | Référence |
|---|---|---|
| FreeScout | **1.8.245** (`config/app.php:21`) | dépôt `freescout-helpdesk/freescout`, commit `f92cb04` du 2026-10-03 |
| Laravel | **5.5.40** + surcharges FreeScout (`composer.json:28`, dossier `overrides/`) | |
| PHP minimal de FreeScout | **7.1.0** (`composer.json:23`) | le module est vérifié avec `php -l` sous PHP 7.1 et 8.4 |
| Refresh | **1.6.0** (`Modules/Refresh/module.json`) | dépôt `altmenorg/freescout-refresh`, tag `v1.6.0`, commit `4259031` (numéros de ligne recalculés depuis 1.4.3 / `a35082b` à partir des différences de Git, éléments vérifiés par `refreshglobal:check`) |

Les numéros de ligne correspondent à ces versions. Après une mise à jour, `php artisan refreshglobal:check`
vérifie automatiquement que chaque élément « fragile » est toujours présent (voir la dernière section).

---

## 1. FreeScout

### 1.1 Structure d'un module (référence : Refresh)

| Élément | Où | Remarque |
|---|---|---|
| Manifeste | `module.json` : `name`, `alias`, `version`, `requiredAppVersion`, `providers`, `license` | le champ `active` est ignoré : l'état vient de la table `modules` (`app/Module.php:2-5, 44-77`) |
| Fournisseur de service | `Providers/<Nom>ServiceProvider.php`, méthode `boot()` | Refresh : `Modules/Refresh/Providers/RefreshServiceProvider.php:38-87` |
| Routes | `Http/routes.php`, chargé par `loadRoutesFrom()` | Refresh : `RefreshServiceProvider.php:53` ; groupe `web` + préfixe `\Helper::getSubdirectory()` (`Modules/Refresh/Http/routes.php:3`, `app/Misc/Helper.php:1398`) |
| Contrôle d'accès des routes | middlewares `auth` et `roles` (`app/Http/Kernel.php:72,78`) | Refresh : `'middleware' => ['auth','roles'], 'roles' => ['admin','user']` (`Modules/Refresh/Http/routes.php:4-18`) |
| Vues | `Resources/views`, espace de noms via `loadViewsFrom()` | Refresh : `RefreshServiceProvider.php:43` |
| Traductions | `Resources/lang/<locale>/*.php` via `loadTranslationsFrom()` (+ JSON) | Refresh : `RefreshServiceProvider.php:44-45` |
| Migrations | `Database/Migrations`, exécutées par `module:migrate` (`app/Console/Commands/ModuleMigrate.php:39`) | Refresh n'en a pas (il utilise la table `options`) |
| Fichiers publics | `Public/`, servis sous `/modules/<alias>/` grâce au lien symbolique créé par `freescout:module-install` (`app/Console/Commands/ModuleInstall.php:90-139`) | chemin public : `\Module::getPublicPath($alias)` = `/modules/<alias>` (`overrides/nwidart/laravel-modules/src/Repository.php:852-855`) |
| Activation | Gérer › Modules : `\App\Module::setActive()` puis `freescout:module-install <alias>` (`app/Http/Controllers/ModulesController.php:252-257`) ; en CLI `module:enable <Nom>` appelle le même `setActive()` (`overrides/nwidart/laravel-modules/src/Module.php:435-438`) | |

### 1.2 Hooks Eventy (recherche de `Eventy::action(`, `Eventy::filter(`, `@action`, `@filter`)

Hooks utiles au module, tous dans `resources/views/layouts/app.blade.php` sauf mention :

| Hook | Type | Fichier:ligne | Rôle |
|---|---|---|---|
| `layout.head` | action | `layouts/app.blade.php:21` | injecter dans `<head>` |
| `stylesheets` | filtre | `layouts/app.blade.php:33` | ajouter une feuille de style au lot minifié |
| `body.class` | action | `layouts/app.blade.php:43` | classes du `<body>` |
| `menu.mailboxes` | filtre | `layouts/app.blade.php:74` | liste des boîtes du menu |
| `mailbox.url` | filtre | `layouts/app.blade.php:77, 85` | adresse d'une boîte dans le menu (Refresh la redirige vers sa vue) |
| **`menu.manage.after_mailboxes`** (1.27.0) | action | **`layouts/app.blade.php:110`** (FreeScout 1.8.252) ; Refresh déplace ce menu sous l'engrenage et ajoute une icône `.rf-i` à chaque entrée : `Modules/Refresh/Providers/RefreshServiceProvider.php:1036-1052` | **entrée « RefreshGlobal » (réglages) du menu « Gérer » → utilisé (RG-HOOK-54)** |
| `menu.manage.append` | action | `layouts/app.blade.php:117` | fin du menu « Gérer » |
| **`menu.append`** | action | **`layouts/app.blade.php:121`** | **ajouter une entrée à la barre de navigation → utilisé** |
| `layout.body_bottom` | action | `layouts/app.blade.php:278` | bas de page |
| `javascripts` | filtre | `layouts/app.blade.php:284` | scripts du lot minifié |
| `javascript` | action | `layouts/app.blade.php:305` | script en ligne (avec nonce CSP) |
| `conversations_table.preload_table_data` | filtre | `conversations/conversations_table.blade.php:29` | précharger des données de la liste |
| **`conversations_table.col_before_conv_number`** | action | **`conversations_table.blade.php:83`** | **colonne → utilisé** |
| **`conversations_table.th_before_conv_number`** | action | **`conversations_table.blade.php:118`** | **en-tête de colonne → utilisé** |
| `conversations_table.row_class` | action | `conversations_table.blade.php:137` | classes d'une ligne |
| **`conversations_table.before_subject`** | action | **`conversations_table.blade.php:191`** | **avant le sujet → utilisé (badge de la boîte)** |
| `conversations_table.after_subject` / `preview_prepend` | action | `conversations_table.blade.php:191, 193` | après le sujet / avant l'aperçu |
| **`conversations_table.td_before_conv_number`** | action | **`conversations_table.blade.php:206`** | **cellule → utilisé** |
| `dashboard.before` / `dashboard.after` | filtre | `secure/dashboard.blade.php:8, 68` | tableau de bord |
| **`mailbox.after_sidebar_buttons`** | action | **`mailboxes/sidebar_menu_view.blade.php:35`** ; Refresh : **`Modules/Refresh/Resources/views/core/mailboxes/sidebar_menu_view.blade.php:81`** (dans `.rf-views`) | **entrée « Toutes les boîtes » dans la barre latérale des boîtes → utilisé** ; sur téléphone, Refresh affiche ce panneau comme tiroir des vues alors que sa barre de gauche est masquée (`Public/css/mobile.css:19`) |

Aucun hook ne permet d'insérer un élément **dans** la liste des dossiers de la barre latérale d'une boîte (la vue
`mailboxes/sidebar_menu_view` ne déclenche que `mailbox.view.before_name`, `mailbox.sidebar.buttons` et
`mailbox.after_sidebar_buttons`) : le module utilise ce dernier pour placer son entrée sous la liste.

### 1.3 Boîtes accessibles à un utilisateur

| Élément | Fichier:ligne | Règle |
|---|---|---|
| `User::mailboxesCanView($cache = false)` | `app/User.php:251-270` | admin : `Mailbox::all()` ; utilisateur : ses boîtes (`mailbox_user`) **sans les boîtes archivées** (`Mailbox::excludeArchived`, `app/Mailbox.php:1160`) |
| `User::mailboxesIdsCanView()` | `app/User.php:333-340` | identifiants seulement — **n'exclut pas** les boîtes archivées (non utilisé) |
| `User::hasAccessToMailbox($id)` | `app/User.php:342-346` | s'appuie sur la méthode précédente |
| `User::isAdmin()` | `app/User.php:223-226` | `role == ROLE_ADMIN` (`app/User.php:45-46`) |
| `User::canSeeOnlyAssignedConversations()` | `app/User.php:1353-1356` | permission « ne voir que les conversations assignées » (`PERM_ONLY_ASSIGNED_TICKETS`, `app/User.php:95`, via `hasManageMailboxPermission`, `app/User.php:395`) ; jamais pour un admin |
| `ConversationPolicy::view()` | `app/Policies/ConversationPolicy.php:22-38` | admin : tout ; sinon accès à la boîte, boîte non archivée, et règle « assignées uniquement » (`checkIsOnlyAssigned`, lignes 122-133) |
| `Conversation::search()` | `app/Conversation.php:2541`, filtre ligne 2569 | la recherche native limite aussi à `mailboxesIdsCanView()` |
| `ConversationsController::search()` | `app/Http/Controllers/ConversationsController.php:2989-2991` | ajoute `assigned = utilisateur` si « assignées uniquement » |
| `User::whichUsersCanView($mailboxes)` | `app/User.php:1189-1214` | utilisateurs des boîtes données (liste du filtre « Assigné à ») |

**Choix du module** (`Services/MailboxAccess.php`) : `mailboxesCanView()` (exclut les boîtes archivées comme la
policy) et, si `canSeeOnlyAssignedConversations()`, restriction à `user_id = utilisateur` (comme la recherche
native et Refresh, `Modules/Refresh/Services/Views.php:214-216`). C'est plus strict que la policy (qui accepte
aussi les conversations créées par l'utilisateur) : la liste ne montre jamais un ticket que la policy refuserait.

### 1.4 Modèles et colonnes

| Modèle | Éléments utilisés | Fichier:ligne |
|---|---|---|
| `App\Conversation` | `STATUS_ACTIVE/PENDING/CLOSED/SPAM` = 1/2/3/4 | `app/Conversation.php:78-81` |
| | `STATE_DRAFT/PUBLISHED/DELETED` = 1/2/3 | `app/Conversation.php:130-132` |
| | relations `user()`, `folder()`, `mailbox()`, `customer()` | `app/Conversation.php:270, 278, 294, 312` |
| | `url()` → route `conversations.view` (+ `folder_id`) | `app/Conversation.php:1109-1115` |
| | `getStatusName()`, `statusCodeToName()` | `app/Conversation.php:606, 618` |
| | `getSubject()` | `app/Conversation.php:1749` |
| | `numberFieldName()` (`number` ou `id` selon la numérotation personnalisée) | `app/Conversation.php:2695-2705` |
| | colonnes `id, number, mailbox_id, status, state, user_id, customer_id, customer_email, subject, created_at, last_reply_at, closed_at, created_by_user_id` | `database/migrations/2018_07_11_010333_create_conversations_table.php:19-74` |
| | index `(mailbox_id, state, status)` et `(user_id, mailbox_id, state, status)` | `database/migrations/2025_09_06_010101_add_index_to_conversations_table.php`, `2021_09_21_010101_add_indexes_to_conversations_table.php` |
| `App\Mailbox` | `STATE_ACTIVE/ARCHIVED`, `isArchived()`, `users()`, `usersAssignable()` | `app/Mailbox.php:21-22, 434, 247, 511` |
| `App\Customer` | `first_name`, `last_name`, `getFullName()` | `database/migrations/2018_07_09_053559_create_customers_table.php:19-20`, `app/Customer.php:526` |
| `App\Folder` | `TYPE_*`, `$types` (un type absent de `$types` affiche la colonne « Assigné à ») | `app/Folder.php:12-23` |
| `App\Thread` | non utilisé par le module | `app/Thread.php:35-96` |

### 1.5 Routes nommées

| Route | Fichier:ligne |
|---|---|
| `dashboard` | `routes/web.php:37` |
| **`conversations.view`** (`/conversation/{id}`) — page native d'un ticket | **`routes/web.php:63`** |
| `conversations.ajax` (actions groupées, statut, assignation) | `routes/web.php:64` |
| `conversations.create` | `routes/web.php:66` |
| `conversations.search` | `routes/web.php:70` |
| **`mailboxes.view`** (`/mailbox/{id}`) | **`routes/web.php:82`** |
| `mailboxes.view.folder` | `routes/web.php:83` |

### 1.6 Vue native de liste : `conversations/conversations_table`

`resources/views/conversations/conversations_table.blade.php` :

* variables attendues : `$conversations` (paginateur), `$folder` (facultatif : un dossier factice est créé lignes
  6-10), `$params` (tableau écrit en attributs `data-param_*`, ligne 71 ; clés `user_id`, `no_customer`,
  `no_checkboxes`, `show_mailbox`, `target_blank`), `$conversations_filter`, `$no_checkboxes`, `$no_customer` ;
* inclut `conversations/partials/bulk_actions` (ligne 68 ; utilise `$mailbox` s'il existe, ligne 6) et
  `conversations/partials/badges` ;
* l'option native `show_mailbox` affiche `[Nom de la boîte]` dans l'aperçu via `mailbox_cached` (ligne 193) ;
* pied de tableau : pagination **en ajax par dossier** (`conversations_pagination`, lignes 231-236 →
  `public/js/main.js:3003`, action `conversations_pagination` ligne 3057 avec `mailbox_id` / `folder_id`) ; le tri par
  en-tête recharge aussi le dossier en ajax (`convListSortingInit`, `public/js/main.js:5118-5135`) ;
* initialisation : `conversationsTableInit()` (ligne 248, `public/js/main.js:5183`).

**Conséquence** : le module réutilise ce partial tel quel (même rendu que les pages natives et que Refresh), avec
un dossier factice de type 991 (même technique que Refresh, `RefreshServiceProvider.php:32-33, 242-252`), une
pagination côté serveur et le tri transformé en liens (`Public/js/refreshglobal.js`). Attention : le partial lit
`$params`, `$statuses`, `$user`, `$types`, `$mailbox` ; le module ne transmet donc aucune variable de ces noms.

---

### 1.7 Mise à jour des modules et tâches planifiées (RefreshGlobal 1.1.0 ; mise à jour revue en 1.9.0)

| Élément | Fichier:ligne | Constat / utilisation |
|---|---|---|
| filtre **`schedule`** | `app/Console/Kernel.php:190` | un module peut ajouter une tâche au planificateur (cron `schedule:run` de FreeScout) → **utilisé** : vidage de la corbeille, e-mails vers la corbeille du serveur (plus de mise à jour depuis 1.9.0) |
| `freescout:module-update` | `app/Console/Commands/ModuleUpdate.php:17, 107` | met à jour les modules non officiels dont `module.json` déclare `latestVersionUrl` |
| `App\Module::updateModule()` | `app/Module.php` (bouton « Mettre à jour » de Gérer › Modules) | télécharge `latestVersionZipUrl`, remplace les fichiers, lance `freescout:module-install` ; en cas d'échec, désactive le module — **aucun retour à l'ancienne version** |
| → choix (1.9.0) | `module.json` | **`latestVersionUrl` / `latestVersionZipUrl` déclarés** (`version.txt`, `RefreshGlobal.zip` de la dernière release) : bouton « Mettre à jour » de Gérer › Modules, ou module **UpdateGuard** (00MY00/UpdateGuard 1.0.0 : `healthCommand` = `refreshglobal:health`, `SHA256SUMS` à côté du ZIP, `"updateGuard": {"migrations": "backward-compatible"}` ; README « For module developers ») ; `Services/Updates.php` dit lequel des deux est actif (route `modules` `routes/web.php:111`, route `updateguard.module` `Modules/UpdateGuard/Http/routes.php:19`) (RG-ROUTE-09, 10) |
| `App\Option::get/set` | `app/Option.php:75, 35` | réglages du module (Gérer › Paramètres › RefreshGlobal) |
| filtres **`settings.sections`**, **`settings.section_settings`**, **`settings.view`** | `app/Http/Controllers/SettingsController.php:267, 250` ; `resources/views/settings/view.blade.php:27` | section **Gérer › Paramètres › RefreshGlobal** (même méthode que Refresh, `RefreshServiceProvider.php:91-126`) ; l'enregistrement est fait par FreeScout (`SettingsController::processSave`, `:288-379`, options), RG-HOOK-15…17 |
| image de la carte du module | `resources/views/modules/partials/module_card.blade.php:2-3` (champ `img` de `module.json`) | `../modules/refreshglobal/img/module.svg`, même forme que Refresh |

## 2. Refresh

### 2.1 Manifeste et fournisseur

* `module.json` : alias `refresh`, version `1.6.0`, `requiredAppVersion` `1.8.0`, licence AGPL-3.0, fournisseur
  `Modules\Refresh\Providers\RefreshServiceProvider`. Le dossier **doit** s'appeler `Modules/Refresh` (README de
  Refresh, « Installation »).
* `RefreshServiceProvider::boot()` (`Providers/RefreshServiceProvider.php:38-87`) enregistre :
  vues `refresh::` (43), traductions (44-45), **surcharge des vues natives** par `prependLocation(Resources/views/core)`
  (48, aujourd'hui seulement `mailboxes/sidebar_menu_view`), filtre `mailbox.url` → sa propre vue (50-52), routes
  (53), middlewares `NativeFolderRedirect` et `ViewNextRedirect` ajoutés au groupe `web` (55, 57), traductions
  surchargées (58), styles (59), hooks de liste (60), tableau de bord (61), ticket (62-63), script (64), réglages (65),
  `body.class` (70-74), `layout.head` (77-86).

### 2.2 CSS / JS et chargement

| Fichier | Chargé par | Chemin public |
|---|---|---|
| `Public/css/icons.css`, `refresh.css`, `mobile.css` | filtre `stylesheets` (`RefreshServiceProvider.php:133-139`), sur **toutes** les pages | `/modules/refresh/css/…` (lot minifié `/css/builds/<hash>.css`) |
| `Public/js/editor.js`, `new.js`, `mobile.js` | filtre `javascripts` (`RefreshServiceProvider.php:141-152`), sur toutes les pages | `/modules/refresh/js/…` |
| script de « coquille » (barre latérale, barre du haut, panneaux) | action `javascript` (`RefreshServiceProvider.php:720-2514`), en ligne | — |

Refresh charge déjà tout sur chaque page : **le module ne recharge aucun fichier de Refresh**, il réutilise
seulement sa structure HTML et ses classes.

### 2.3 Vues et partials

| Vue | Réutilisable ? |
|---|---|
| `refresh::tickets` (`Resources/views/tickets.blade.php`) | page complète liée à **une** boîte (`$mailbox`, routes `refresh.tickets*`, enregistrement de vues partagées) → **non incluse**, sa structure est reproduite |
| `core/mailboxes/sidebar_menu_view` | panneau des vues d'**une** boîte (`$mailbox` obligatoire, ligne 9) → non inclus, structure reproduite |
| `refresh::dashboard`, `properties`, `contact_panel`, `contacts`, `settings` | sans rapport avec la liste globale |

Aucun partial de Refresh n'est inclus : `view()->exists('refresh::tickets')` est seulement contrôlé (RG-VIEW-05)
pour détecter un changement de structure de la liste de Refresh.

### 2.4 Structure HTML et classes de la liste Refresh (reproduites à l'identique)

Source : `Modules/Refresh/Resources/views/tickets.blade.php` et `core/mailboxes/sidebar_menu_view.blade.php`.

| Zone | Classes | Lignes |
|---|---|---|
| barre de vue (déplacée dans la barre du haut par le script, `RefreshServiceProvider.php:1011-1013`) | `.rf-viewbar`, `.rf-sqbtn.rf-toggle-views`, `h1.rf-viewbar-title`, `.rf-pill` | `tickets.blade.php:32-36` |
| conteneur | `.rf-list-layout` (+ `.rf-filters-closed`, `.rf-layout-table` lus dans les cookies `rf_filters_closed`, `rf_layout`) | `tickets.blade.php:38` |
| barre d'outils | `.rf-list-main`, `.rf-toolbar`, `.rf-cb-all`/`.rf-toggle-all`, `.dropdown.rf-sort`, `.rf-sort-label`, `.rf-sort-current`, `.rf-toolbar-right`, `.rf-layout-dd`, `.rf-set-layout`, `.rf-btn`, `.rf-range`, `.rf-pager`, `.rf-pager-btn`, `.rf-toggle-filters` | `tickets.blade.php:39-71` |
| liste | `.rf-list-scroll` + table native | `tickets.blade.php:73-75` |
| panneau de filtres | `form.rf-filters`, `.rf-filters-head`, `.rf-filters-title`, `.rf-filters-reset`, `.rf-filters-body`, `.rf-f`, `.rf-search`, `.rf-f-q`, `select.rf-multi`, `.rf-select`, `.rf-input`, `.rf-filters-foot`, `.rf-btn-primary` | `tickets.blade.php:79-172` |
| panneau des vues | `.rf-views`, `.rf-views-search`, `.rf-views-filter`, `.rf-views-sec[data-sec]`, `.rf-views-sec-head`, `.rf-views-list`, `a.rf-v`, `.rf-v-label`, `.rf-v-count` | `sidebar_menu_view.blade.php:29-65` |
| icônes | `.rf-i`, `.rf-i-<nom>`, `.rf-i-sm` | `Public/css/icons.css` |
| badges SLA | `.rf-badge`, `.rf-badge-new/-first/-late/-customer/-pending` | `RefreshServiceProvider.php:307-320` |
| variables CSS | `--fd-text`, `--fd-text-2`, `--fd-muted`, `--fd-border` | `Public/css/refresh.css:13` (`:root`) |

Comportements du script de Refresh obtenus « gratuitement » grâce à ces classes : panneau des vues repliable et
filtrable (`RefreshServiceProvider.php:1113-1133`), ouverture/fermeture des filtres (1061-1065), select2 sur
`.rf-multi` (1066-1073), mise en page cartes/tableau (1094-1101), barre d'actions groupées dans `.rf-toolbar`
(1118-1208), menus priorité/agent/statut des cartes (1216-1267), avatars (1324-1345), version mobile
(`Public/js/mobile.js:72` : `isList = $('.rf-list-layout').length`, cartes construites à partir des `.rf-badge`
ligne 592).

**Classes de Refresh volontairement évitées** : `.rf-save-view` et `.rf-v-del` déclenchent l'enregistrement /
la suppression de **vues partagées de Refresh** (`RefreshServiceProvider.php:1150-1167`) ; le changement de vue en
ajax ne s'active que sur la route `refresh.tickets` (`RefreshServiceProvider.php:787, 1348`), donc pas sur la page
du module.

### 2.4 bis Barre d'onglets du téléphone et entrée « Tickets » (RefreshGlobal 1.2.0)

| Élément | Fichier:ligne | Utilisation |
|---|---|---|
| barre d'onglets `nav.rf-m-tabs`, onglets `a.rf-m-tab`, onglet `.rf-m-tab-tickets`, actif `.active` | `Modules/Refresh/Public/js/mobile.js:127-140` (liste fixe, aucun point d'extension) | `Public/js/shell.js` ajoute un onglet `a.rf-m-tab.rg-m-tab` après `.rf-m-tab-tickets` (RG-HOOK-13) |
| icône par variable CSS `--rf-i` sur `i.rf-i` | `RefreshServiceProvider.php:958-964` (icônes des modules dans la barre) | même technique pour l'icône de l'onglet |
| lien « Tickets » de la barre de gauche (`.rf-rail-link` contenant `.rf-i-fd-all-tickets`) | `RefreshServiceProvider.php:729` | option « remplacer » : masqué (pas supprimé : `mobile.js:113-115` lit encore son adresse), notre entrée placée à sa suite (RG-HOOK-14) |
| chargement des scripts de module dans le lot de la page | filtre `javascripts`, `resources/views/layouts/app.blade.php:284` | `shell.js` chargé sur toutes les pages (RG-HOOK-11) |
| réglages lus par le script | action `layout.head`, `layouts/app.blade.php:21` (même méthode que Refresh pour ses traductions, `RefreshServiceProvider.php:77-86`) | `<meta name="refreshglobal">` écrit seulement quand l'interface de Refresh est présente (RG-HOOK-12) |

### 2.4 ter Tableau de bord, suppression d'un ticket, textes (RefreshGlobal 1.4.0)

| Élément | Fichier:ligne | Utilisation |
|---|---|---|
| filtre **`dashboard.before`** de FreeScout | `resources/views/secure/dashboard.blade.php:8` (`@filter('dashboard.before', '')`) | point d'insertion du tableau de bord (RG-HOOK-18) |
| ordre des écouteurs Eventy | `overrides/tormjens/eventy/src/Event.php:34-35` (priorité croissante) ; priorité par défaut 20 : `vendor/tormjens/eventy/src/Events.php:58, 93` | à 19 le module note le HTML reçu ; à 21 il remplace **seulement** la partie ajoutée par Refresh, et seulement si elle contient `class="rf-dash"` |
| tableau de bord de Refresh | `RefreshServiceProvider.php:505-537` (`$user->mailboxesCanView()->first()` : **première boîte seulement**, aucun paramètre) ; vue `Resources/views/dashboard.blade.php:13` (`<div class="rf-dash">`) | remplacé par la même présentation pour toutes les boîtes (RG-HOOK-19, RG-HOOK-20) ; Refresh lui-même n'est pas modifié |
| vues rapides du tableau de bord (`unresolved`, `overdue`, `due-today`, `open`, `pending`, `unassigned`, `new`, `undelivered` ; `closed-today` / `closed` depuis 1.19.0 : `Conversation::STATUS_CLOSED`, colonne `conversations.closed_at`, propres au module) | même sens que les vues de Refresh du même nom (`Modules/Refresh/Services/Views.php:116-219`, Refresh 1.6.0) ; statuts d’envoi `App\SendLog::STATUS_SEND_ERROR` / `STATUS_DELIVERY_ERROR` (`app/SendLog.php:18,20`), colonne `threads.send_status` (`database/migrations/2018_07_12_003318_create_threads_table.php:69`), `Thread::TYPE_MESSAGE` / `STATE_PUBLISHED` (`app/Thread.php:37,92`) | **définitions propres au module depuis 1.17.0** (`Services/Dashboard/TicketViews.php`), sans appeler Refresh : tuiles et filtre `rv` sur une seule requête toutes boîtes (RG-CORE-23, RG-DB-15)
| graphique, écarts et durées ; chiffres du jour | même présentation que `Dashboard::chart()` / `delta()` / `duration()` de Refresh (`Modules/Refresh/Services/Dashboard.php:127-186`, classes `rf-chart*`) et mêmes formules que `Dashboard::stats()` (`:16-117`) | **propres au module depuis 1.17.0** : `Services/Dashboard/Chart.php`, `Services/Dashboard/Statistics.php` ; sans Refresh, styles `Public/css/refreshglobal-dashboard.css` (portée `.rg-dash-standalone`)
| `Settings::hoverCards()` (Refresh ≥ 1.6.0) | `Modules/Refresh/Services/Settings.php:20` ; aperçus au survol `Public/js/hovercards.js` (chargé sur toutes les pages, `RefreshServiceProvider.php:149-150` ; cible `.table-conversations td.conv-subject a > p`, :149 ; interrupteur `.rf-hover-toggle a` du menu Affichage, :191-197, cookie `rf_hover`), entrée du menu dans `Resources/views/tickets.blade.php:62-66` | entrée « Aperçus au survol » du menu Affichage de « Toutes les boîtes » (`tickets.blade.php` du module) ; la liste du module est le tableau natif, les aperçus de Refresh s'y appliquent sans autre code (RG-CORE-19, RG-HOOK-37 ; contrôles ignorés avec un Refresh plus ancien : `refresh_min`) |
| carte « Tickets non résolus » du tableau de bord | depuis Refresh 1.5.0 : ouverts et en attente par agent, deux colonnes, tri par total (`Services/Dashboard.php:94-111`, `Resources/views/dashboard.blade.php:51-53`) | même découpage calculé par le module sans appeler Refresh : `GlobalDashboard::unresolvedByAgent()` (une requête groupée agent × statut sur la requête de base du tableau de bord, noms en une requête), `partials/unresolved_card.blade.php` |
| `Settings::firstResponseHours()`, `Settings::resolutionHours()` | `Modules/Refresh/Services/Settings.php:26, 32` | **repli legacy** (classe interne de Refresh, à retirer quand Refresh répond au filtre public `fsinterop.sla.targets`, convenu pour Refresh 1.9.0) : objectifs SLA quand aucun module ne répond au filtre et que Refresh est actif (`Services/Dashboard/Sla.php`) ; sinon réglages du module `refreshglobal.sla_first_response` / `refreshglobal.sla_resolution` (24 h / 72 h) (RG-CORE-11, avertissement)
| action **`delete_conversation`**, `delete_conversation_forever`, `bulk_delete_conversation` de `conversations.ajax` | `app/Http/Controllers/ConversationsController.php:1944, 1966, 2164` ; route `routes/web.php:64` ; redirection d'origine `getRedirectUrlAfterDelete()` (même fichier) | middleware `Http/Middleware/AfterDelete.php` ajouté au groupe `web` (même méthode que Refresh, `RefreshServiceProvider.php:55-57`) : change `redirect_url` d'une réponse réussie ; « définitif » = action `delete_conversation_forever` de FreeScout (même contrôle de droits) ; suppression groupée : `Conversation::deleteForever()` (`app/Conversation.php:2156`) sur les seuls tickets que FreeScout vient de mettre à la corbeille (RG-HOOK-21, RG-ROUTE-04, RG-CORE-12) |
| corbeille : « Vider la corbeille » de FreeScout | `app/Http/Controllers/ConversationsController.php:2203-2260` (action `empty_folder` : administrateur ou `User::PERM_DELETE_CONVERSATIONS` (`app/User.php:89`), accès à la boîte, tickets assignés seulement si « ne voit que les assignés ») ; suppression `Conversation::deleteConversationsForever()` (`app/Conversation.php:2161`, fils et pièces jointes compris) | `Services/Trash.php` applique les mêmes règles sur plusieurs boîtes (bouton) et pour le vidage automatique (`refreshglobal:trash --scheduled`, filtre `schedule`) (RG-CORE-13) |
| « Actions du ticket » sur téléphone (Refresh) | `Modules/Refresh/Public/js/mobile.js:814-846` (menu construit par `sheet()`, :82-108, classe `.rf-m-sheet-actions` :86, lignes `.rf-m-opt`) ; « Supprimer » seulement via `.rf-tb-delete` (copie de `.conv-delete`, `RefreshServiceProvider.php:1486-1491`) ; pour un ticket de la corbeille FreeScout donne `.conv-delete-forever` (`app/Misc/ConversationActionButtons.php:40-60`) | `Public/js/shell.js` complète le menu à son ouverture (MutationObserver) avec la même présentation, l'entrée clique le bouton de FreeScout (RG-HOOK-23) |
| rechargement après suppression groupée | `public/js/main.js:5321-5345` (`location.reload()` après `bulk_delete_conversation`) ; glissement de Refresh : même suppression groupée (`mobile.js:368-373`) ; défilement : `.rf-list-scroll` sur ordinateur, fenêtre sur téléphone | `Public/js/refreshglobal.js` (`keepPosition`) note l'ordre des tickets et la place d'un ticket repère en quittant la liste, et la rétablit après `#rg-deleted=ID` (redirection du module) ou un rechargement |
| e-mails sur le serveur mail | FreeScout ne fait que copier les e-mails (relève `app/Console/Commands/FetchEmails.php:221-350`) et n'efface jamais rien sur le serveur ; action **`conversations.before_delete_forever`** avant toute suppression définitive (`app/Conversation.php:2163`), appelée aussi pour la suppression d'une boîte entière (`app/Mailbox.php:1137`) et pour un e-mail non importable (`FetchEmails.php:1339`) | `Services/MailServerTrash.php` : mise en file **seulement** pendant une suppression voulue par un utilisateur (middleware `AfterDelete` pour `delete_conversation_forever`, `bulk_delete_conversation`, `empty_folder` ; `Services/Trash.php`) ; traitement par `refreshglobal:mail-trash` avec `MailHelper::getMailboxClient()` (`app/Misc/Mail.php:808`), `MailHelper::getImapFolder()` (:1307), `Mailbox::getInImapFolders()` (`app/Mailbox.php:906`), `imap_sent_folder` (`app/Jobs/SendReplyToCustomer.php:450-545`), recherche `whereMessageId()` (`overrides/webklex/php-imap/src/Query/WhereQuery.php:459`) et `Message::move()` (`Message.php:1038`, MOVE ou COPY + suppression, `ImapProtocol.php:1039-1051`) ; la bibliothèque ne lit pas l'attribut `\Trash` des dossiers (`Folder.php:225-230`) : corbeille trouvée par son nom ou réglée (RG-HOOK-24, RG-CORE-14, 15, RG-DB-07, 08) |
| module tiers **SyncNow** (rabsym/freescout-syncnow 1.3.0, alias `syncnow`, facultatif) | routes `syncnow.force` (POST `/mailbox/{id}/syncnow/force`, `Http/routes.php:54-57`) → `{status: running, sync_token}` / `cooldown` / `locked` / `skipped` / `error` ; `syncnow.status` (GET `…/status?sync_token&offset`, :64-67) à interroger jusqu'à la fin : enregistre l'historique et libère le verrou (`SyncNowController.php:335-441`) ; droits `canSync()` (:534-544) : admin, ou permission `syncnow.force_sync` (`Entities/SyncNowLog.php`) + accès à la boîte ; IMAP seulement (:164) | bouton « Relever les e-mails » (`Services/SyncNow.php`, `Public/js/refreshglobal.js` `syncNow`) affiché seulement si SyncNow est actif ; mêmes appels que la page de SyncNow, avec le jeton CSRF de FreeScout (`<meta name="csrf-token">`, `layouts/app.blade.php:10`) (RG-ROUTE-05, 06) |
| relève des e-mails (spam, tags de provenance) | `app/Console/Commands/FetchEmails.php` : dossiers lus `Mailbox::getInImapFolders()` (:254), filtre `fetch_emails.mailbox_to_save_message` avec le dossier de chaque message (:380), données `fetch_emails.data_to_save` (:993, `message` = message webklex, en-têtes bruts `getHeader()->raw`), filtre `fetch_emails.should_save_thread` (:1051, :1091 ; `false` = message ignoré et marqué lu, :1053-1057), actions `conversation.created_by_customer` / `conversation.customer_replied` (:1384, :1387) ; dossiers webklex sans attributs spéciaux conservés (`Folder::parseAttributes()`, seuls NoSelect… : reconnaissance par les noms) ; `threads.headers` (en-têtes bruts conservés) ; module Tags : `\Modules\Tags\Entities\Tag::attachByName()` (utilisé ainsi par `Modules/Refresh/Http/Controllers/NewTicketController.php:192`) | `Services/MailTags.php` (dossier → tag anglais, détection du spam, table `refreshglobal_mail_tags`, recopie dans Tags, API), `Console/MailTagsCommand.php` (recalcul planifié), `Http/Controllers/ThreadDetailsController.php` (détails / texte brut, lien vers `conversations.ajax_html` action `show_original`, `ConversationsController.php:2606-2643`), menu `thread.menu.append` (`thread.blade.php:337`), outils `.rf-th-tools` de Refresh (`RefreshServiceProvider.php:1833-1854`) (RG-HOOK-30…34, RG-CORE-18, RG-DB-11) |
| vues enregistrées partagées (1.15.0) | utilisateurs : `App\User::nonDeleted()` (`app/User.php:1164`, filtre `user.non_deleted_condition`), `isDeleted()` (:1181), `whichUsersCanView()` (:1189 : tous pour un admin, sinon les utilisateurs des boîtes de l'utilisateur), `getFullName()` (:233), `TYPE_ROBOT` (:57, « Workflows, teams »), `STATUS_DELETED` (:64) ; aucune notion d'équipe dans FreeScout (seulement des utilisateurs « robot » du module payant Teams, membres non lisibles sans son code) ; droits de boîtes : `MailboxAccess` / `GlobalTicketQuery::normalize()` (inchangés) | `Services/SavedViews.php` (définition versionnée, droits voir / modifier / gérer / copier, liste visible en cache 1 min vidée à chaque changement, défaut, ordre, export / import, journal), `Entities/SavedView.php`, `Http/Controllers/SavedViewsController.php`, `GlobalTicketsController::resolveFilters()` (liste, actualisation, export), migration `2026_10_19_000000_extend_refreshglobal_saved_views.php` (RG-DB-05, RG-DB-12…14, RG-CORE-20) |
| images distantes des e-mails (1.16.0) | filtre `thread.body_output` sur le corps de chaque message (`resources/views/conversations/partials/thread.blade.php:297`, arguments : corps, thread, ticket, boîte), puis `safe_raw_html()` → `Helper::stripDangerousTags()` (`app/Misc/Functions.php:33`, `app/Misc/Helper.php:676` : retire script, iframe, style…, garde les attributs `data-*` des `img`) | `Services/RemoteImages.php` (show / ask / block, règles « tag = mode » sur les tags d'origine, `src` / `srcset` / `background` / `url()` des styles remplacés ; adresses gardées en `data-rg-*` en mode « ask »), bouton « Afficher les images » dans `Public/js/shell.js` (RG-HOOK-38) |
| déplacer vers un dossier du serveur mail (1.16.0) | IMAP de FreeScout : `\MailHelper::getMailboxClient()` (`app/Misc/Mail.php:808`), `getImapFolder()` (:1307, nom UTF-8 → UTF7-IMAP), webklex `Client::getFolders()` (`overrides/webklex/php-imap/src/Client.php:475`), `getFolderByPath()` (:459), `Message::move()` (`Message.php:1038`) ; `Mailbox::getInImapFolders()` (`app/Mailbox.php:906`), `IN_PROTOCOL_IMAP` (:81) ; droit `ConversationPolicy::update()` (`app/Policies/ConversationPolicy.php:73`) ; barre de sélection : action `bulk_actions.before_delete` (`resources/views/conversations/partials/bulk_actions.blade.php:34`) ; barre du ticket : `.conv-actions` (`conversations/view.blade.php:29`) et `.rf-tb-close` de Refresh (`RefreshServiceProvider.php:1476`) ; menu « Actions du ticket » du téléphone `.rf-m-sheet-actions` (`mobile.js:86`) ; glissement vers la droite de Refresh sur les cartes (`mobile.js:353-418`, `translateX` ≥ 0 : le glissement vers la gauche lui est libre) ; nouveau dossier (1.21.0) : webklex `Client::createFolder()` (`overrides/webklex/php-imap/src/Client.php:608`, FreeScout 1.8.250 ; `getFolders()` :494), nom en UTF7-IMAP comme `getImapFolder()` : `\Helper::mbConvertEncoding()` (`app/Misc/Helper.php:1695`) s'il existe, sinon `mb_convert_encoding()` de PHP (versions plus anciennes de FreeScout ; 1.21.1, aucun contrôle requis) | `Services/MailMove.php` (dossiers en cache 10 min, relus à chaque ouverture de la fenêtre et par « Actualiser les dossiers », `createFolder()` : nom validé, parent existant ou premier niveau — sous INBOX sur un serveur « INBOX. », doublon refusé, e-mails retrouvés par Message-ID dans le dossier noté à la relève puis dans les dossiers relevés, tags remplacés, action `refreshglobal.mail_moved`), `Http/Controllers/MailMoveController.php` (dossiers, déplacer, annuler), `Public/js/shell.js` (fenêtre, message « Annuler », glissement à gauche) (RG-HOOK-39…41, RG-CORE-21/22) |
| contrat FreeScout Interop (1.17.0) | aucun élément privé : `window.FreeScoutInterop` (fichier partagé `Public/js/fs-interop.js`), manifeste `interop.json` de chaque module (Refresh : `Modules/Refresh/interop.json` quand il le fournira), crochets `fsinterop.*` ; modules trouvés par `\Module::findByAlias()` / `App\Module::isActive()` comme pour RG-REF-01 | `Services/Interop/` (Manifest, SemVer, PeerStatus, ServerHooks), `Public/js/interop-adapter.js` ; les replis DOM de `Public/js/shell.js` (RG-HOOK-13, 14, 23, 25…27, 40) ne s'exécutent que si `legacy(capability)` (Refresh sans la capacité) ; `docs/INTEROPERABILITY.md` |
| adresse d'envoi d'un nouvel e-mail / ticket | formulaire `conversations.create` (`routes/web.php:66`, `ConversationsController::create()` :477, `resources/views/conversations/create.blade.php:138-150` : champ « De » seulement si `Mailbox::getAliases(true, true)`, c.-à-d. alias + `aliases_reply`) ; `Mailbox::getAliases()` (`app/Mailbox.php:793-824`, alias mis en minuscules) ; à l'envoi `$thread->from = $request->from_alias` **sans contrôle** (`ConversationsController.php:1195-1197`), adresse d'expédition `app/Mail/ReplyToCustomer.php:82` ; Refresh : page refaite par `Public/js/new.js` (champs `.rf-nf`, :218-225 ; « De » :241-248), sur téléphone `Public/js/mobile.js` `initNew()` (ligne « De » `.rf-m-nfrom`, :934-937 ; masquée pour un ticket), « Nouveau ticket » posté à `refresh.new_ticket` avec `mailbox_id` (`Http/Controllers/NewTicketController.php:46-49`) | `Services/SendFrom.php` (adresses : boîtes visibles non archivées + alias), `<meta name="refreshglobal-from">` (action `layout.head` sur `conversations.create`), `Public/js/shell.js` `sendFrom()` (champ « De » / « Boîte », alias appliqué en `from_alias`, autre boîte = formulaire de cette boîte avec la saisie reprise par `sessionStorage`, adresse par défaut) ; `Http/Middleware/CheckFromAlias.php` retire un `from_alias` étranger à la boîte sur `conversations.ajax` et `refresh.new_ticket` (RG-HOOK-28, 29, RG-CORE-17, RG-ROUTE-11) |
| appui long sur une carte (téléphone, Refresh) | `Modules/Refresh/Public/js/mobile.js:260-298` : case cochée après 500 ms (`lastHold`), clic du relâchement ignoré seulement s'il arrive moins de 800 ms après ; en mode sélection (`body.rf-m-sel`) un clic coche / décoche la carte | `Public/js/refreshglobal.js` `longPressGuard()` : si la case de la carte a changé pendant l'appui, le clic qui suit est ignoré (phase de capture), sans dépendre des délais de Refresh ; CSS `-webkit-touch-callout: none` / `user-select: none` sur les cartes du téléphone |
| fil d'Ariane de la page d'un ticket (Refresh) | `RefreshServiceProvider.php:769-780` : premier élément = dernière vue Refresh (`TicketsController::lastView()`, `Http/Controllers/TicketsController.php:115-122`, cookie `rf_last_view` limité aux adresses `/mailbox/N/tickets/…`), sinon « Tous les tickets » de la boîte ; construit en `<div class="rf-crumb">` (:940-944) ; flèche retour du téléphone = premier lien du fil d'Ariane (`Public/js/mobile.js:674-676`) ; panneau des vues de la boîte du ticket avec sa recherche `.rf-views-search` (`Resources/views/core/mailboxes/sidebar_menu_view.blade.php:30`) | `Public/js/shell.js` `backToList()` : pour un ticket ouvert depuis « Toutes les boîtes » (mémoire `sessionStorage` par onglet, écrite par la page à partir de `#rg-state data-back-*`), premier lien du fil d'Ariane et flèche du téléphone → la liste sans dossier (`AfterDelete::backParams()`), marqueur `#rg-back=<ticket>` lu par `keepPosition()` ; repère « ← Toutes les boîtes » + « Boîte : nom » (nom écrit dans `<meta name="refreshglobal">` si l'utilisateur a accès à la boîte) ; après une action qui quitte le ticket pour la liste d'une boîte (réponse `redirect_url` de `conversations.ajax`, `ConversationsController::getRedirectUrl()` :2814-2852 appelé :627, :686 ; fermeture du dernier ticket actif : `Conversation::urlNext()` affiche le dossier, `app/Conversation.php:953-964` ; bouton « Fermer » de Refresh : `after_send` = 2, `RefreshServiceProvider.php:1515-1522`), l'adresse est remplacée par la liste « Toutes les boîtes » dans le convertisseur JSON de jQuery (`$.ajaxPrefilter`) avant que le rappel de FreeScout (`fsAjax()`, `public/js/main.js:1024-1081`) ou de Refresh ne l'ouvre ; un autre ticket (suivant) est ouvert normalement (RG-HOOK-25, 26, 27, 36) |
| module tiers **Workflow Packs** (00MY00/WorkflowPacks **1.14.0**, alias `workflowpacks`, facultatif) | table **`workflowpacks_ticket_folders`** (`Database/Migrations/2026_10_21_000000_create_workflowpacks_ticket_folders_table.php`, documentée dans son `docs/api-dossiers.md`) : une ligne par ticket, `conversation_id` (unique), `mailbox_id`, `folder` (« Factures/Fournisseurs », comme dans les règles), `label` (« [WP] Factures › Fournisseurs », `Services/Imap/FolderPath.php:85-95`, séparateur « › »), `path` (dossier du serveur), `state` `filed` \| `cleaned`, `filed_at` ; écrite par `TicketFolders::record()` / `cleaned()` (`Services/Imap/TicketFolders.php:68-123`) ; ligne effacée par Workflow Packs sur `conversations.before_delete_forever` à la priorité **1000** (:41-43) ; table **`workflowpacks_retention`** (`folder`, `number`, `metric` i\|h\|d, `mode` trash\|delete ; `Services/Imap/Retention.php:45-66`) ; routes `workflowpacks.graph` (`Http/routes.php:33`) et `workflowpacks.history` (:46) ; droit d'accès `WorkflowPacksServiceProvider::userCanUse()` (`Providers/WorkflowPacksServiceProvider.php:120-134`) | `Services/AutoFolders.php`, seule classe qui lit ces tables (lecture seule) : panneau « Dossiers automatiques », filtre `wf` (`GlobalTicketQuery`), badge (`conversations_table.before_subject`), page `/refresh-global/folders` ; `MailServerTrash::queue()` (priorité 20) copie `path` avant l'effacement de la ligne (RG-DB-09, 10, RG-ROUTE-07, 08) |
| dates de la vue d'ensemble | `App\User::dateFormat()` (`app/User.php:536`), `App\User::dateDiffForHumans()` (:627) : fuseau de l'utilisateur | « il y a 3 heures » avec la date complète en info-bulle (RG-CORE-16) |
| politique de sécurité (CSP) des pages | les attributs `style="…"` en ligne peuvent être refusés | retraits des sous-dossiers par classes CSS (`rg-depth-1…5`), pas de style en ligne |
| date d'entrée dans la corbeille | `Conversation::deleteToFolder()` met `user_updated_at` à jour (`app/Conversation.php:2125`) ; colonne : migration `2018_07_11_010333_create_conversations_table.php:67` | « dans la corbeille depuis plus de N jours » ; `updated_at` si `user_updated_at` est vide (RG-DB-06) |
| langue de l'utilisateur | `app/Http/Middleware/Localize.php:22` (session `user_locale`) ; enregistrée par le profil : `app/Http/Controllers/UsersController.php:243-245` (`users.locale` + session) ; Refresh n'a pas de choix propre, il lit `app()->getLocale()` (`RefreshServiceProvider.php:41, 78`) | sélecteur de langue du module (`Http/Controllers/LanguageController.php`) : même enregistrement que le profil, langues de `config('app.locales')` + `Helper::getCustomLocales()`, options de la vue `partials/locale_options` (`resources/views/partials/locale_options.blade.php`, utilisée par `users/profile.blade.php:156`) (RG-VIEW-06) |
| dictionnaire des scripts de Refresh | `RefreshServiceProvider.php:77-86` (`<meta name="refresh-l10n">` lu tel quel dans `Resources/lang/<langue>.json`), lu par `rfT()` (`Public/js/mobile.js:15-21`), textes `Created :time ago` / `Closed :time ago` (`mobile.js:612-614`, `fr.json:49, 65`) | aucun point d'extension : action `layout.head` à la priorité 30 (après Refresh) qui corrige le contenu de la balise avant tout script, seulement pour les valeurs fautives connues (`Resources/lang/refresh-fixes.php`) (RG-HOOK-22) |
| traductions de FreeScout surchargées par Refresh | `RefreshServiceProvider.php:184-212`, `Resources/lang/overrides/fr.php` (`*.Mailbox` → « Tous les tickets ») | le module utilise ses propres textes pour « Boîte » |

### 2.5 Points d'extension exposés par Refresh

| Point | Fichier:ligne | Utilisation |
|---|---|---|
| filtre **`refresh.rail_items`** (entrée de la barre latérale avec icône) | `RefreshServiceProvider.php:740-750` ; documenté dans le README de Refresh (« For module developers ») | **utilisé** : icône « Toutes les boîtes » |
| filtre `modernui.rail_items` (ancien nom) | `RefreshServiceProvider.php:742` | non utilisé |
| liens ajoutés au menu natif → recopiés dans la barre avec une icône générique | `RefreshServiceProvider.php:978-984` | dédoublonné par adresse (ligne 892-896) |
| vues partagées (`options.refresh_saved_views`) | `Services/Views.php:207-222` | non utilisé (par boîte, partagées) |

---

## 3. Conclusion de l'audit

### 3.1 Tableau des points d'intégration

| Élément | Source (`fichier:ligne`) | Stable / fragile | Utilisation prévue | Solution de secours (contrôle) |
|---|---|---|---|---|
| `User::mailboxesCanView()` | `app/User.php:251` | stable (cœur, utilisé partout) | boîtes autorisées | aucune : **bloquant** (RG-ACL-01, RG-CORE-01) |
| `User::canSeeOnlyAssignedConversations()` | `app/User.php:1353` | stable | permission « assignées uniquement » | **bloquant** (RG-ACL-02) |
| `Conversation` constantes, `url()`, `getStatusName()`… | `app/Conversation.php:78-132, 1109, 606` | stable | requête, liens, libellés | **bloquant** (RG-CORE-02/06) |
| colonnes `conversations`, `mailboxes`, `customers`, `mailbox_user` | migrations citées en 1.4 | stable | requête | **bloquant** (RG-DB-01…04) |
| route `conversations.view` | `routes/web.php:63` | stable | ouvrir un ticket | **bloquant** (RG-ROUTE-01) |
| route `mailboxes.view` + filtre `mailbox.url` | `routes/web.php:82`, `layouts/app.blade.php:77` | stable | liens de secours vers les boîtes | **bloquant** (RG-ROUTE-02) / dégradé (RG-HOOK-06) |
| vue `layouts.app` | `resources/views/layouts/app.blade.php` | stable | gabarit de page | page autonome `blocked_plain` (RG-VIEW-01) |
| vue `conversations/conversations_table` | `resources/views/conversations/conversations_table.blade.php` | **fragile** (balisage, variables) | liste | **bloquant** (RG-VIEW-02) |
| hooks `conversations_table.*` | `conversations_table.blade.php:83, 118, 191, 206` | fragile | colonne / badge « Boîte » | liste sans colonne (RG-HOOK-02…05) |
| hook `menu.append` | `layouts/app.blade.php:121` | stable | entrée de menu | adresse directe `/refresh-global/tickets` (RG-HOOK-01) |
| hook `mailbox.after_sidebar_buttons` | `mailboxes/sidebar_menu_view.blade.php:35`, Refresh `core/mailboxes/sidebar_menu_view.blade.php:81` | stable / fragile (vue surchargée par Refresh) | entrée dans la barre latérale et le tiroir mobile | menu natif / barre de gauche (RG-HOOK-08, RG-HOOK-09) |
| module Refresh actif | `modules` (`app/Module.php:44`) | — | style | style FreeScout standard (RG-REF-01) |
| fichiers CSS/JS de Refresh | `RefreshServiceProvider.php:133-152` | fragile (noms de fichiers) | style identique | style FreeScout standard (RG-CSS-01, RG-JS-01) |
| structure / classes `rf-*` de la liste | `Modules/Refresh/Resources/views/tickets.blade.php:32-177` | **fragile** (non documentées) | rendu identique | style standard si `refresh::tickets` disparaît (RG-VIEW-05) ; sinon rendu à vérifier visuellement |
| `refresh.rail_items` | `RefreshServiceProvider.php:742` | stable (documenté) | icône dans la barre | lien recopié depuis le menu natif (RG-HOOK-07) |
| `.rf-badge` → pastille mobile | `Modules/Refresh/Public/js/mobile.js:592` | fragile | boîte visible sur téléphone | la colonne reste visible sur ordinateur |
| version FreeScout / Refresh | `config/app.php:21`, `module.json` | — | plages testées | avertissement (RG-ENV-01, RG-REF-02) |
| `dashboard.before` + bloc `rf-dash` de Refresh | `secure/dashboard.blade.php:8`, `RefreshServiceProvider.php:505`, `dashboard.blade.php:13` | fragile (non documenté) | tableau de bord de toutes les boîtes à la place de celui de Refresh | tableau de bord de Refresh tel quel (RG-HOOK-18…20, RG-ERR-03) ; sans Refresh actif (`App\Module::isActive`), le tableau de bord du module s’ajoute au-dessus des cartes de FreeScout |
| actions de suppression de `conversations.ajax` | `ConversationsController.php:1944-1990, 2164-2200` | stable | retour à la liste, définitif | comportement de FreeScout (RG-HOOK-21, RG-ROUTE-04, RG-CORE-12, RG-ERR-04) |
| `<meta name="refresh-l10n">` | `RefreshServiceProvider.php:77-86` | fragile | correction de deux textes français | textes de Refresh d'origine (RG-HOOK-22) |

### 3.2 Intégrable proprement

* entrée de menu (`menu.append`) et icône dans la barre de Refresh (`refresh.rail_items`) ;
* liste native avec colonne et badge « Boîte » par les hooks `conversations_table.*` (sur la page du module seulement) ;
* style identique à Refresh **sans copier ni recharger** ses fichiers : même structure HTML et mêmes classes ;
* page du ticket = page native (`Conversation::url()`), la réponse part donc de l'adresse de la bonne boîte.

### 3.3 Non intégrable sans modifier Refresh (documenté, non forcé)

* **Recherche de la barre du haut** de Refresh : elle cible toujours la vue « Tous les tickets » de la première boîte
  (`RefreshServiceProvider.php:917, 1054`). La page globale a sa propre recherche.
* **Fil d'Ariane et « ticket suivant » après fermeture** sur la page d'un ticket : ils reviennent à la dernière vue
  Refresh (cookie `rf_last_view`, `RefreshServiceProvider.php:777-779`, `Http/Middleware/ViewNextRedirect.php:51-56`),
  pas à la page globale. (La **suppression** d'un ticket, elle, revient à la page globale depuis 1.4.0.)
* **Tableau de bord de Refresh** : aucun paramètre pour lui donner d'autres boîtes ; le module affiche à la place le
  même tableau de bord calculé sur toutes les boîtes (2.4 ter).
* **Menus « Agent » des cartes** : la liste des agents vient de la boîte courante ou de la première boîte
  (`RefreshServiceProvider.php:759-764`). Sur la page globale, un ticket d'une autre boîte peut proposer des agents
  de la première boîte ; FreeScout contrôle l'assignation côté serveur (`conversations.ajax`).
* **Panneau des vues de Refresh** : par boîte, sans hook → la page globale affiche son propre panneau avec la même
  structure.
* **Barre latérale native des boîtes** : aucun hook pour y ajouter « Toutes les boîtes » (voir 1.2).

---

## 4. Ce que vérifie `php artisan refreshglobal:check`

La liste ci-dessus est codée dans `Config/integration.php` (une seule source) et contrôlée par
`Services/Compatibility/` : versions (RG-ENV-01, RG-REF-02), présence de Refresh (RG-REF-01), fichiers
(RG-CSS-01, RG-JS-01), vues (RG-VIEW-01…05), hooks et éléments de Refresh — en relisant le fichier qui les
contient — (RG-HOOK-01…22), classes / méthodes / constantes de FreeScout et de Refresh (RG-CORE-01…12), routes
(RG-ROUTE-01…04), tables / colonnes (RG-DB-01…05), règles
d'accès (RG-ACL-01, RG-ACL-02). Après une mise à jour de FreeScout ou de Refresh : relancer la commande, puis
corriger les lignes en échec (et les références de ce document).


## FreeScout Interop 1.17.0 — recherche et contextes serveur

| Élément | Fichier:ligne | Utilisation |
|---|---|---|
| noms de routes `dashboard`, `mailboxes.view`, `mailboxes.view.folder`, `conversations.view` ; `refresh.tickets` (Refresh) | `routes/web.php:37, 82, 83, 63` ; `Modules/Refresh/Http/routes.php:5` (`{mailbox_id}`) | contexte de la page pour `fsinterop.navigation.context` (`Services/Interop/NavigationContext.php`) : lecture du nom de route seulement |
| `ConversationPolicy::view()` | `app/Policies/ConversationPolicy.php:22` | `fsinterop.conversation.context` ne répond que pour un utilisateur qui peut voir le ticket (`Services/Interop/ConversationContext.php`) (RG-CORE-24) |
| `Conversation::url()`, `getSubject()`, `number` | `app/Conversation.php:1109, 1749, 2683` | résultats de recherche `search-results` 1.0 (`Services/Search/GlobalSearch.php`) ; même recherche que la liste (`GlobalTicketQuery::applySearch()`) (RG-CORE-02) |
| `App\Option::get($name, $default, $decode, $use_cache)` | `app/Option.php:75` (cache par requête : une valeur absente reste « absente » après `set()`) | objectifs SLA lus sans ce cache (`Sla::own()`) (RG-CORE-07) |


## Flux de Workflow Packs 3.20+ — statistiques et flux temporaires (RefreshGlobal 1.20.0)

Contrat documenté par Workflow Packs (`docs/api-flux.md`, release `v3.20.0`), tout par Eventy : aucune classe ni table de
Workflow Packs n'est lue. Seul point de contact : `Services/WorkflowPacksLink.php` (`available()` : module actif,
`workflowpacks.info` avec `api >= 1`, version `>= 3.20.0` et moteur allumé ; lu une fois par requête, après le
démarrage de tous les modules). Sans cela : routes en 404, aucun lien, aucun écouteur, aucune tâche planifiée.

| Élément | Fichier:ligne | Utilisation |
|---|---|---|
| filtre `workflowpacks.info` | `Modules/WorkflowPacks/Services/Engine/FlowReport.php:50, 80-84` | détection (`WorkflowPacksLink::available()`) (RG-HOOK-42) |
| action `workflowpacks.ticket_changed` ($conversationId, $change) | `FlowReport.php:87-101` (clés : `base()` 104-116, `fact()` 119-155) | enregistrement validé et idempotent dans `refreshglobal_flow_changes` (`Services/FlowChanges.php record()`) (RG-HOOK-43) |
| filtre `workflowpacks.ticket_changes` (1000 tickets, 5000 changements au plus) | `FlowReport.php:53, 159-190` | rattrapage par lots (`FlowChanges::backfill()`, commande `refreshglobal:flow-history`) ; lot coupé en deux s'il atteint 5000 changements (RG-HOOK-44) |
| filtre `workflowpacks.flow_stats` | `FlowReport.php:58, 197` | flux supprimés depuis (`exists: false`) sur la page des statistiques (RG-HOOK-45) |
| filtre `workflowpacks.untreated` | `FlowReport.php:63`, `Services/Engine/TempFlows.php:200-215` | e-mails non traités, toujours demandés pour les boîtes permises de l'utilisateur, puis limités par la permission « seulement assignés » (`Services/FlowTemp.php`) (RG-HOOK-46) |
| filtre `workflowpacks.temp_flow` | `FlowReport.php:68`, `TempFlows.php:221-276` (codes d'erreur `error()` 273, droits `caller()` 266) | flux temporaire sans lancement, liste recalculée côté serveur, `edit_url` suivi seulement s'il pointe vers ce FreeScout (`WorkflowPacksLink::safeUrl()`) (RG-HOOK-47) |
| filtre `workflowpacks.temp_flow_status` | `FlowReport.php:73`, `TempFlows.php:163-178` | état des flux temporaires créés depuis RefreshGlobal (RG-HOOK-48) |
| action `workflowpacks.temp_flow_finished` ($flowId, $summary) | `TempFlows.php:153` | fin notée dans `refreshglobal_temp_flows` (RG-HOOK-49) |
| `App\Module::isActive()`, `App\User::STATUS_ACTIVE` | `app/Module.php:43`, `app/User.php:62` | module actif ; bouton réservé aux administrateurs actifs (RG-CORE-25) |
| action `conversations.before_delete_forever` | `app/Conversation.php:2174` (FreeScout 1.8.250) | lignes d'un ticket supprimé définitivement effacées (`FlowChanges::forget()`) (RG-HOOK-24) |
| tables du module `refreshglobal_flow_changes`, `refreshglobal_temp_flows` | `Database/Migrations/2026_11_19_000000_create_refreshglobal_flow_tables.php` | statistiques et flux temporaires (RG-DB-16/17) |

Les contrôles RG-HOOK-42…49 relisent les fichiers de Workflow Packs et sont **non applicables** sans lui ou avant 3.20
(`module` / `module_min` de `Config/integration.php`, `CompatibilityChecker::optionalModulePath()`).

## Performance (1.23.0)

| Élément | Où | Utilisé par |
|---|---|---|
| événement `Illuminate\Foundation\Http\Events\RequestHandled` | `vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:128` | `Services/Perf/Recorder.php` : une ligne par requête pendant un enregistrement (RG-CORE-28) |
| `LARAVEL_START` | `public/index.php:19` | début de la requête (facultatif) |
| `App\Option::maybeUnserialize()`, événements Eloquent `saved` / `deleted` | `app/Option.php`, `Illuminate/Database/Eloquent/Concerns/HasEvents.php` | `Services/Settings.php` `option()` : tous les réglages en une requête, relus après un changement (RG-CORE-29) |
| table du module `refreshglobal_perf` | `Database/Migrations/2026_11_24_000000_create_refreshglobal_perf_table.php` | mesures (RG-DB-18) |
| `/proc`, `/sys`, `opcache_get_status()`, `sys_getloadavg()`, `information_schema` | système, PHP, base | `Services/Perf/Hardware.php`, `Probe.php` : lecture seule, omis si illisible |

## Réponses (1.24.0)

| Élément | Où (FreeScout 1.8.252) | Utilisé par |
|---|---|---|
| `.conv-reply-block` (classes `hidden`, `conv-note-block`, `conv-forward-block`), `#body` (summernote) | `public/js/main.js:1861-1925` (`prepareReplyForm()`, `showReplyForm()`) | `Public/js/shell.js` : texte placé quand une réponse s'ouvre vide (RG-HOOK-52) |
| `setReplyBody()`, `fs_reply_changed` | `public/js/main.js:5415`, `:13`, `:2060-2176` | écrire le texte sans créer de brouillon (RG-HOOK-50/51) |
| action `send_reply`, `getRedirectUrl()` | `app/Http/Controllers/ConversationsController.php:2953` | `Http/Middleware/AfterReply.php` : seule la `redirect_url` d'une réponse réussie est changée (RG-HOOK-53) |
| `Mailbox::getUserSettings()`, `MailboxUser::AFTER_SEND_NEXT` | `app/Mailbox.php`, `app/MailboxUser.php:16` | choix « après l'envoi » de l'agent (RG-CORE-30) |
| `Conversation::url()`, `isChat()` | `app/Conversation.php` | ticket à garder, pas de texte dans les discussions (RG-CORE-31) |
| onglets et boutons `[data-native]`, `.rf-m-opt` de Refresh | `Modules/Refresh/Providers/RefreshServiceProvider.php:1785-1860`, `Public/js/mobile.js` | facultatif : texte retiré avant le passage en note ou transfert |
| `ViewNextRedirect` de Refresh | `Modules/Refresh/Http/Middleware/ViewNextRedirect.php` | jamais appelé : `AfterReply` agit après lui (premier du groupe « web ») |
| `Thread::TYPE_CUSTOMER`, `body`, `created_by_customer_id` (1.25.0) | `app/Thread.php:31` | `Services/CustomerName.php` : signature des derniers messages du client (RG-CORE-32) |
| `.note-editor` (summernote) | dans `.conv-reply-block` | liste de choix du texte, placée avant ; absente, la liste n'apparaît pas |
| `.dropdown` / `data-toggle="dropdown"` (Bootstrap 3 de FreeScout) (1.26.0) | `public/js/bootstrap.js` | bouton de choix du profil ; absent, le menu ne s'ouvre pas, le reste fonctionne |
