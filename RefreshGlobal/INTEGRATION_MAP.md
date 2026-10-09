# INTEGRATION_MAP — audit d'intégration de RefreshGlobal

Rapport de la phase 0 : tout élément de FreeScout ou de Refresh utilisé par le module, avec sa référence
`fichier:ligne` dans le code réel. Rien n'a été supposé : chaque élément a été lu dans les sources ci-dessous.

| Code audité | Version | Référence |
|---|---|---|
| FreeScout | **1.8.245** (`config/app.php:21`) | dépôt `freescout-helpdesk/freescout`, commit `f92cb04` du 2026-10-03 |
| Laravel | **5.5.40** + surcharges FreeScout (`composer.json:28`, dossier `overrides/`) | |
| PHP minimal de FreeScout | **7.1.0** (`composer.json:23`) | le module est vérifié avec `php -l` sous PHP 7.1 et 8.4 |
| Refresh | **1.4.3** (`Modules/Refresh/module.json`) | dépôt `altmenorg/freescout-refresh`, tag `v1.4.3`, commit `a35082b` |

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
native et Refresh, `Modules/Refresh/Services/Views.php:173-175`). C'est plus strict que la policy (qui accepte
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
un dossier factice de type 991 (même technique que Refresh, `RefreshServiceProvider.php:32-33, 228-238`), une
pagination côté serveur et le tri transformé en liens (`Public/js/refreshglobal.js`). Attention : le partial lit
`$params`, `$statuses`, `$user`, `$types`, `$mailbox` ; le module ne transmet donc aucune variable de ces noms.

---

### 1.7 Mise à jour des modules et tâches planifiées (RefreshGlobal 1.1.0)

| Élément | Fichier:ligne | Constat / utilisation |
|---|---|---|
| filtre **`schedule`** | `app/Console/Kernel.php:190` | un module peut ajouter une tâche au planificateur (cron `schedule:run` de FreeScout) → **utilisé** : `refreshglobal:update --scheduled` chaque jour |
| `freescout:module-update` | `app/Console/Commands/ModuleUpdate.php:17, 107` | met à jour les modules non officiels dont `module.json` déclare `latestVersionUrl` |
| `App\Module::updateModule()` | `app/Module.php` (bouton « Mettre à jour » de Gérer › Modules) | télécharge `latestVersionZipUrl`, remplace les fichiers, lance `freescout:module-install` ; en cas d'échec, désactive le module — **aucun retour à l'ancienne version** |
| → choix | `module.json` | **pas de `latestVersionUrl` / `latestVersionZipUrl`** : seul l'outil du module (avec retour arrière) met RefreshGlobal à jour |
| `App\Option::get/set` | `app/Option.php:75, 35` | réglage « mise à jour automatique » (`refreshglobal.auto_update`) |
| `Helper::setGuzzleDefaultOptions()` | `app/Misc/Helper.php:3018` | délais et proxy de FreeScout pour les téléchargements |
| filtres **`settings.sections`**, **`settings.section_settings`**, **`settings.view`** | `app/Http/Controllers/SettingsController.php:267, 250` ; `resources/views/settings/view.blade.php:27` | section **Gérer › Paramètres › RefreshGlobal** (même méthode que Refresh, `RefreshServiceProvider.php:91-116`) ; l'enregistrement est fait par FreeScout (`SettingsController::processSave`, `:288-379`, options), RG-HOOK-15…17 |
| image de la carte du module | `resources/views/modules/partials/module_card.blade.php:2-3` (champ `img` de `module.json`) | `../modules/refreshglobal/img/module.svg`, même forme que Refresh |

## 2. Refresh

### 2.1 Manifeste et fournisseur

* `module.json` : alias `refresh`, version `1.4.3`, `requiredAppVersion` `1.8.0`, licence AGPL-3.0, fournisseur
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
| `Public/css/icons.css`, `refresh.css`, `mobile.css` | filtre `stylesheets` (`RefreshServiceProvider.php:123-129`), sur **toutes** les pages | `/modules/refresh/css/…` (lot minifié `/css/builds/<hash>.css`) |
| `Public/js/editor.js`, `new.js`, `mobile.js` | filtre `javascripts` (`RefreshServiceProvider.php:131-138`), sur toutes les pages | `/modules/refresh/js/…` |
| script de « coquille » (barre latérale, barre du haut, panneaux) | action `javascript` (`RefreshServiceProvider.php:689-2364`), en ligne | — |

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
| barre de vue (déplacée dans la barre du haut par le script, `RefreshServiceProvider.php:936-938`) | `.rf-viewbar`, `.rf-sqbtn.rf-toggle-views`, `h1.rf-viewbar-title`, `.rf-pill` | `tickets.blade.php:32-36` |
| conteneur | `.rf-list-layout` (+ `.rf-filters-closed`, `.rf-layout-table` lus dans les cookies `rf_filters_closed`, `rf_layout`) | `tickets.blade.php:38` |
| barre d'outils | `.rf-list-main`, `.rf-toolbar`, `.rf-cb-all`/`.rf-toggle-all`, `.dropdown.rf-sort`, `.rf-sort-label`, `.rf-sort-current`, `.rf-toolbar-right`, `.rf-layout-dd`, `.rf-set-layout`, `.rf-btn`, `.rf-range`, `.rf-pager`, `.rf-pager-btn`, `.rf-toggle-filters` | `tickets.blade.php:39-71` |
| liste | `.rf-list-scroll` + table native | `tickets.blade.php:73-75` |
| panneau de filtres | `form.rf-filters`, `.rf-filters-head`, `.rf-filters-title`, `.rf-filters-reset`, `.rf-filters-body`, `.rf-f`, `.rf-search`, `.rf-f-q`, `select.rf-multi`, `.rf-select`, `.rf-input`, `.rf-filters-foot`, `.rf-btn-primary` | `tickets.blade.php:79-172` |
| panneau des vues | `.rf-views`, `.rf-views-search`, `.rf-views-filter`, `.rf-views-sec[data-sec]`, `.rf-views-sec-head`, `.rf-views-list`, `a.rf-v`, `.rf-v-label`, `.rf-v-count` | `sidebar_menu_view.blade.php:29-65` |
| icônes | `.rf-i`, `.rf-i-<nom>`, `.rf-i-sm` | `Public/css/icons.css` |
| badges SLA | `.rf-badge`, `.rf-badge-new/-first/-late/-customer/-pending` | `RefreshServiceProvider.php:293-306` |
| variables CSS | `--fd-text`, `--fd-text-2`, `--fd-muted`, `--fd-border` | `Public/css/refresh.css:13` (`:root`) |

Comportements du script de Refresh obtenus « gratuitement » grâce à ces classes : panneau des vues repliable et
filtrable (`RefreshServiceProvider.php:1038-1058`), ouverture/fermeture des filtres (1061-1065), select2 sur
`.rf-multi` (1066-1073), mise en page cartes/tableau (1094-1101), barre d'actions groupées dans `.rf-toolbar`
(1118-1208), menus priorité/agent/statut des cartes (1216-1267), avatars (1324-1345), version mobile
(`Public/js/mobile.js:72` : `isList = $('.rf-list-layout').length`, cartes construites à partir des `.rf-badge`
ligne 592).

**Classes de Refresh volontairement évitées** : `.rf-save-view` et `.rf-v-del` déclenchent l'enregistrement /
la suppression de **vues partagées de Refresh** (`RefreshServiceProvider.php:1075-1092`) ; le changement de vue en
ajax ne s'active que sur la route `refresh.tickets` (`RefreshServiceProvider.php:755, 1273`), donc pas sur la page
du module.

### 2.4 bis Barre d'onglets du téléphone et entrée « Tickets » (RefreshGlobal 1.2.0)

| Élément | Fichier:ligne | Utilisation |
|---|---|---|
| barre d'onglets `nav.rf-m-tabs`, onglets `a.rf-m-tab`, onglet `.rf-m-tab-tickets`, actif `.active` | `Modules/Refresh/Public/js/mobile.js:127-140` (liste fixe, aucun point d'extension) | `Public/js/shell.js` ajoute un onglet `a.rf-m-tab.rg-m-tab` après `.rf-m-tab-tickets` (RG-HOOK-13) |
| icône par variable CSS `--rf-i` sur `i.rf-i` | `RefreshServiceProvider.php:884-890` (icônes des modules dans la barre) | même technique pour l'icône de l'onglet |
| lien « Tickets » de la barre de gauche (`.rf-rail-link` contenant `.rf-i-fd-all-tickets`) | `RefreshServiceProvider.php:698` | option « remplacer » : masqué (pas supprimé : `mobile.js:113-115` lit encore son adresse), notre entrée placée à sa suite (RG-HOOK-14) |
| chargement des scripts de module dans le lot de la page | filtre `javascripts`, `resources/views/layouts/app.blade.php:284` | `shell.js` chargé sur toutes les pages (RG-HOOK-11) |
| réglages lus par le script | action `layout.head`, `layouts/app.blade.php:21` (même méthode que Refresh pour ses traductions, `RefreshServiceProvider.php:77-86`) | `<meta name="refreshglobal">` écrit seulement quand l'interface de Refresh est présente (RG-HOOK-12) |

### 2.4 ter Tableau de bord, suppression d'un ticket, textes (RefreshGlobal 1.4.0)

| Élément | Fichier:ligne | Utilisation |
|---|---|---|
| filtre **`dashboard.before`** de FreeScout | `resources/views/secure/dashboard.blade.php:8` (`@filter('dashboard.before', '')`) | point d'insertion du tableau de bord (RG-HOOK-18) |
| ordre des écouteurs Eventy | `overrides/tormjens/eventy/src/Event.php:34-35` (priorité croissante) ; priorité par défaut 20 : `vendor/tormjens/eventy/src/Events.php:58, 93` | à 19 le module note le HTML reçu ; à 21 il remplace **seulement** la partie ajoutée par Refresh, et seulement si elle contient `class="rf-dash"` |
| tableau de bord de Refresh | `RefreshServiceProvider.php:491-523` (`$user->mailboxesCanView()->first()` : **première boîte seulement**, aucun paramètre) ; vue `Resources/views/dashboard.blade.php:13` (`<div class="rf-dash">`) | remplacé par la même présentation pour toutes les boîtes (RG-HOOK-19, RG-HOOK-20) ; Refresh lui-même n'est pas modifié |
| `Views::query($mailbox_id, $view, $user)`, `Views::labels()` | `Modules/Refresh/Services/Views.php:99, 89` | tuiles et filtre `rv` avec la définition de Refresh, boîte par boîte (RG-CORE-09) |
| `Dashboard::chart()`, `delta()`, `duration()` ; formules de `Dashboard::stats()` | `Modules/Refresh/Services/Dashboard.php:147, 121, 130` ; `:16-117` | graphique et formats de Refresh ; chiffres recalculés sur plusieurs boîtes avec les mêmes formules (RG-CORE-10) |
| `Settings::resolutionHours()` | `Modules/Refresh/Services/Settings.php:26` | SLA de « Résolution dans le cadre du SLA » (RG-CORE-11) |
| action **`delete_conversation`**, `delete_conversation_forever`, `bulk_delete_conversation` de `conversations.ajax` | `app/Http/Controllers/ConversationsController.php:1944, 1966, 2164` ; route `routes/web.php:64` ; redirection d'origine `getRedirectUrlAfterDelete()` (même fichier) | middleware `Http/Middleware/AfterDelete.php` ajouté au groupe `web` (même méthode que Refresh, `RefreshServiceProvider.php:55-57`) : change `redirect_url` d'une réponse réussie ; « définitif » = action `delete_conversation_forever` de FreeScout (même contrôle de droits) ; suppression groupée : `Conversation::deleteForever()` (`app/Conversation.php:2156`) sur les seuls tickets que FreeScout vient de mettre à la corbeille (RG-HOOK-21, RG-ROUTE-04, RG-CORE-12) |
| langue de l'utilisateur | `app/Http/Middleware/Localize.php:22` (session `user_locale`) ; enregistrée par le profil : `app/Http/Controllers/UsersController.php:243-245` (`users.locale` + session) ; Refresh n'a pas de choix propre, il lit `app()->getLocale()` (`RefreshServiceProvider.php:41, 78`) | sélecteur de langue du module (`Http/Controllers/LanguageController.php`) : même enregistrement que le profil, langues de `config('app.locales')` + `Helper::getCustomLocales()`, options de la vue `partials/locale_options` (`resources/views/partials/locale_options.blade.php`, utilisée par `users/profile.blade.php:156`) (RG-VIEW-06) |
| dictionnaire des scripts de Refresh | `RefreshServiceProvider.php:77-86` (`<meta name="refresh-l10n">` lu tel quel dans `Resources/lang/<langue>.json`), lu par `rfT()` (`Public/js/mobile.js:15-21`), textes `Created :time ago` / `Closed :time ago` (`mobile.js:612-614`, `fr.json:49, 65`) | aucun point d'extension : action `layout.head` à la priorité 30 (après Refresh) qui corrige le contenu de la balise avant tout script, seulement pour les valeurs fautives connues (`Resources/lang/refresh-fixes.php`) (RG-HOOK-22) |
| traductions de FreeScout surchargées par Refresh | `RefreshServiceProvider.php:170-198`, `Resources/lang/overrides/fr.php` (`*.Mailbox` → « Tous les tickets ») | le module utilise ses propres textes pour « Boîte » |

### 2.5 Points d'extension exposés par Refresh

| Point | Fichier:ligne | Utilisation |
|---|---|---|
| filtre **`refresh.rail_items`** (entrée de la barre latérale avec icône) | `RefreshServiceProvider.php:709-719` ; documenté dans le README de Refresh (« For module developers ») | **utilisé** : icône « Toutes les boîtes » |
| filtre `modernui.rail_items` (ancien nom) | `RefreshServiceProvider.php:711` | non utilisé |
| liens ajoutés au menu natif → recopiés dans la barre avec une icône générique | `RefreshServiceProvider.php:904-910` | dédoublonné par adresse (ligne 892-896) |
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
| fichiers CSS/JS de Refresh | `RefreshServiceProvider.php:123-138` | fragile (noms de fichiers) | style identique | style FreeScout standard (RG-CSS-01, RG-JS-01) |
| structure / classes `rf-*` de la liste | `Modules/Refresh/Resources/views/tickets.blade.php:32-172` | **fragile** (non documentées) | rendu identique | style standard si `refresh::tickets` disparaît (RG-VIEW-05) ; sinon rendu à vérifier visuellement |
| `refresh.rail_items` | `RefreshServiceProvider.php:711` | stable (documenté) | icône dans la barre | lien recopié depuis le menu natif (RG-HOOK-07) |
| `.rf-badge` → pastille mobile | `Modules/Refresh/Public/js/mobile.js:592` | fragile | boîte visible sur téléphone | la colonne reste visible sur ordinateur |
| version FreeScout / Refresh | `config/app.php:21`, `module.json` | — | plages testées | avertissement (RG-ENV-01, RG-REF-02) |
| `dashboard.before` + bloc `rf-dash` de Refresh | `secure/dashboard.blade.php:8`, `RefreshServiceProvider.php:491`, `dashboard.blade.php:13` | fragile (non documenté) | tableau de bord de toutes les boîtes | tableau de bord de Refresh tel quel (RG-HOOK-18…20, RG-CORE-09…11, RG-ERR-03) |
| actions de suppression de `conversations.ajax` | `ConversationsController.php:1944-1990, 2164-2200` | stable | retour à la liste, définitif | comportement de FreeScout (RG-HOOK-21, RG-ROUTE-04, RG-CORE-12, RG-ERR-04) |
| `<meta name="refresh-l10n">` | `RefreshServiceProvider.php:77-86` | fragile | correction de deux textes français | textes de Refresh d'origine (RG-HOOK-22) |

### 3.2 Intégrable proprement

* entrée de menu (`menu.append`) et icône dans la barre de Refresh (`refresh.rail_items`) ;
* liste native avec colonne et badge « Boîte » par les hooks `conversations_table.*` (sur la page du module seulement) ;
* style identique à Refresh **sans copier ni recharger** ses fichiers : même structure HTML et mêmes classes ;
* page du ticket = page native (`Conversation::url()`), la réponse part donc de l'adresse de la bonne boîte.

### 3.3 Non intégrable sans modifier Refresh (documenté, non forcé)

* **Recherche de la barre du haut** de Refresh : elle cible toujours la vue « Tous les tickets » de la première boîte
  (`RefreshServiceProvider.php:876, 979`). La page globale a sa propre recherche.
* **Fil d'Ariane et « ticket suivant » après fermeture** sur la page d'un ticket : ils reviennent à la dernière vue
  Refresh (cookie `rf_last_view`, `RefreshServiceProvider.php:745-747`, `Http/Middleware/ViewNextRedirect.php:51-56`),
  pas à la page globale. (La **suppression** d'un ticket, elle, revient à la page globale depuis 1.4.0.)
* **Tableau de bord de Refresh** : aucun paramètre pour lui donner d'autres boîtes ; le module affiche à la place le
  même tableau de bord calculé sur toutes les boîtes (2.4 ter).
* **Menus « Agent » des cartes** : la liste des agents vient de la boîte courante ou de la première boîte
  (`RefreshServiceProvider.php:728-733`). Sur la page globale, un ticket d'une autre boîte peut proposer des agents
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
