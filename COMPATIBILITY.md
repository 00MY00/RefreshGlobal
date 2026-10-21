# Compatibilité

## Versions testées

| Composant | Version testée | Plage acceptée par `refreshglobal:check` | Hors plage |
|---|---|---|---|
| FreeScout | **1.8.245** (commit `f92cb04`, 2026-10-03) | `>= 1.8.0` et `< 1.9.0` | avertissement RG-ENV-01 |
| Refresh | **1.6.0** (tag `v1.6.0`) ; 1.4.3 testé avec les versions précédentes | `>= 1.4.0` et `< 1.7.0` | avertissement RG-REF-02 |
| Workflow Packs (facultatif) | **1.14.0** (release `v1.14.0`) | table `workflowpacks_ticket_folders` présente | sans elle : dossiers automatiques non affichés (RG-DB-09) |
| Laravel (fourni par FreeScout) | 5.5.40 + surcharges FreeScout | — | — |
| PHP | **8.2.34** (tests), syntaxe vérifiée sous **7.1.33** et **8.4.26** | `>= 7.1.0` (minimum de FreeScout) | l'installeur s'arrête |
| Base de données | **MariaDB 10.11** | MySQL / MariaDB ; PostgreSQL pris en charge par le code (opérateur `ilike`, pas de fonction JSON) mais **non testé** | — |
| Navigateurs | Chromium (captures et vérifications) | ceux de FreeScout / Refresh | — |
| Installeur | Debian 12 (conteneur PHP officiel), Ubuntu 24.04 (installation complète, voir `tests/RESULTS.md`) | Linux + bash ≥ 4 ; `--full` : Ubuntu / Debian (comme le script officiel de FreeScout) | message explicite |

Les plages sont définies dans `RefreshGlobal/Config/integration.php` (clé `versions`).
Windows n'est pas pris en charge par FreeScout lui-même (wiki officiel) : pas d'installeur PowerShell.

## Points d'intégration fragiles

Détail complet et références `fichier:ligne` : [RefreshGlobal/INTEGRATION_MAP.md](RefreshGlobal/INTEGRATION_MAP.md).
Chacun est contrôlé par `php artisan refreshglobal:check`.

| Point | Pourquoi il est fragile | Si ça casse |
|---|---|---|
| Structure HTML et classes `rf-*` de la liste de Refresh (`tickets.blade.php`) | non documentées par Refresh : peuvent changer à chaque version | rendu différent ; si la vue `refresh::tickets` disparaît : style standard (RG-VIEW-05) |
| Noms des fichiers CSS/JS de Refresh | internes à Refresh | style standard (RG-CSS-01 / RG-JS-01) |
| Pastille « boîte » sur téléphone (`.rf-badge` lu par `mobile.js`) | comportement interne de Refresh | la boîte n'apparaît plus sur téléphone ; la liste fonctionne |
| Partial natif `conversations/conversations_table` et ses hooks | balisage et variables internes de FreeScout | colonne « Boîte » absente (RG-HOOK-02…05) ou liste bloquée (RG-VIEW-02) |
| Pagination / tri natifs en ajax (`main.js`) | désactivés sur la page par `refreshglobal.js` | si FreeScout renomme ses classes, un clic sur un en-tête pourrait recharger un dossier : la pagination serveur reste correcte |
| Entrée dans le tiroir des vues de Refresh (`mailbox.after_sidebar_buttons` dans sa vue surchargée) | la vue surchargée par Refresh peut perdre ce hook | sur téléphone, plus d'accès depuis le tiroir ; l'adresse `/refresh-global/tickets` reste valable (RG-HOOK-09) |
| Onglet du téléphone et option « remplacer Tickets » (`.rf-m-tab-tickets`, `fd-all-tickets`) | éléments internes des scripts de Refresh | l'onglet n'apparaît plus ou l'entrée de Refresh n'est plus masquée ; la page fonctionne (RG-HOOK-13, RG-HOOK-14) |
| `refresh.rail_items` | point d'extension documenté de Refresh | icône remplacée par le lien générique du menu (RG-HOOK-07) |
| Tableau de bord de toutes les boîtes (remplace le bloc `rf-dash` de Refresh) | balisage et classes internes de Refresh pour l’apparence ; vues, chiffres et graphique calculés par le module depuis 1.17.0 ; objectifs SLA de Refresh (`Settings::firstResponseHours / resolutionHours`) | le tableau de bord de Refresh s’affiche tel quel (première boîte) ; sans Refresh, le tableau de bord du module s’affiche au-dessus de celui de FreeScout (RG-HOOK-18…20, RG-CORE-11) |
| Suppression d'un ticket (`conversations.ajax`, actions `delete_conversation*`) | noms d'actions internes de FreeScout | comportement de FreeScout (retour à la boîte, corbeille) (RG-HOOK-21, RG-ROUTE-04, RG-CORE-12) |
| Dossiers automatiques (tables `workflowpacks_ticket_folders`, `workflowpacks_retention` de Workflow Packs) | schéma d'un module tiers, documenté par lui (`docs/api-dossiers.md`) | dossiers automatiques non affichés, le reste fonctionne (RG-DB-09/10, avertissement) |
| Fil d'Ariane de la page d'un ticket (`.rf-crumb`), flèche retour du téléphone qui le lit, recherche du panneau des vues | éléments internes des scripts et vues de Refresh | retour de Refresh (sa dernière vue) au lieu de « Toutes les boîtes » ; le reste fonctionne (RG-HOOK-25…27, avertissement) |
| Adresse d'envoi (`from_alias` de FreeScout, champs `.rf-nf` de la page « Nouveau » de Refresh, ligne « De » du téléphone `.rf-m-nfrom`) | code et structure internes de FreeScout et de Refresh | champ d'adresse absent : la boîte du formulaire comme dans FreeScout ; le reste fonctionne (RG-HOOK-28/29, RG-CORE-17, RG-ROUTE-11, avertissement) |
| Relève des e-mails (`fetch_emails.should_save_thread`, `fetch_emails.mailbox_to_save_message`, `conversation.created_by_customer` de `FetchEmails.php`) | hooks internes de la relève de FreeScout | e-mails importés sans tags, spams importés comme avant ; le reste fonctionne (RG-HOOK-30…32, avertissement) |
| Détails d'un message (`thread.menu.append`, outils `.rf-th-tools` de Refresh) | menu et script internes | bouton « Détails » absent ; le reste fonctionne (RG-HOOK-33/34) |
| Feuille de style sur toutes les pages (filtre `stylesheets`, `layouts/app.blade.php:33`) | hook de FreeScout | tags, fenêtre de détails et repère sans mise en forme hors des pages du module (RG-HOOK-35) |
| Aperçus au survol de Refresh 1.6.0 (`Settings::hoverCards()`, `.rf-hover-toggle` de `hovercards.js`) | fonctions de Refresh ≥ 1.6.0 | entrée « Aperçus au survol » absente du menu Affichage de « Toutes les boîtes » ; contrôles ignorés avec un Refresh plus ancien (RG-CORE-19, RG-HOOK-37) |
| Vues partagées : utilisateurs proposés (`User::whichUsersCanView()`, `User::nonDeleted()`, `TYPE_ROBOT`) | méthodes de FreeScout | partage avec des utilisateurs choisis indisponible ; vues privées et pour tous fonctionnent (RG-CORE-20) |
| Images distantes (`thread.body_output`, `thread.blade.php:297`) | filtre de FreeScout | images distantes chargées comme avant (RG-HOOK-38) |
| Déplacer vers un dossier (`bulk_actions.before_delete`, `.conv-actions`, `.rf-tb-close` de Refresh, `ConversationPolicy::update`, `Mailbox::getInImapFolders`, accès IMAP RG-CORE-14/15) | crochets et vues de FreeScout / Refresh | bouton absent à l'endroit concerné ; le reste fonctionne (RG-HOOK-39…41, RG-CORE-21/22) |
| Contrat FreeScout Interop (`interop.json`, `window.FreeScoutInterop`) | Refresh qui déclare le protocole `>=1.0.0 <2.0.0` | sans contrat : mode *legacy* (replis DOM testés, comme avant) ; sans Refresh : *autonome* ; version en conflit déclarée : intégration coupée (INCOMPATIBLE) — voir Diagnostic › Interopérabilité |
| Correction des textes français de Refresh (`<meta name="refresh-l10n">`) | balise interne de Refresh | textes d'origine de Refresh (RG-HOOK-22, avertissement) |

## Mises à jour

- Depuis 1.9.0, pas de mise à jour propre au module : `module.json` déclare `latestVersionUrl` (`version.txt` de la
  dernière release) et `latestVersionZipUrl` (`RefreshGlobal.zip`, `SHA256SUMS` à côté).
- Sans UpdateGuard : bouton « Mettre à jour » de Gérer › Modules (`app/Http/Controllers/ModulesController.php:150-195`,
  `app/Module.php:415-519`) — remplace les fichiers et lance `freescout:module-install`, **sans sauvegarde ni retour
  arrière** ; FreeScout désactive le module si l'activation échoue.
- Avec [UpdateGuard](https://github.com/00MY00/UpdateGuard) 1.0.0 : niveau « Full Safe Update » (SHA-256 + contrôle
  de santé natif `refreshglobal:health`) ; `"updateGuard": {"migrations": "backward-compatible"}` déclaré (migrations
  additives : tables et colonnes facultatives).
- Les installations en 1.8.x reçoivent encore les nouvelles versions par leur ancienne mise à jour automatique (même
  release, même `SHA256SUMS`) ; elle lance `refreshglobal:check` et `refreshglobal:selftest`, toujours présents.

## Écarts connus par rapport au style de Refresh

- La **recherche de la barre du haut** de Refresh cherche toujours dans « Tous les tickets » de la première boîte ;
  utiliser la recherche du panneau de filtres de la page.
- Après l'ouverture d'un ticket depuis la page globale, le **fil d'Ariane** (et la flèche retour du téléphone) ramène
  à « Toutes les boîtes » (1.8.2), aussi après la fermeture du ticket (ticket suivant, ou retour à la liste
  « Toutes les boîtes » quand la boîte n'a plus de ticket actif, 1.13.0) ; le **« ticket suivant »** de Refresh reste celui de sa dernière vue (cookie
  `rf_last_view` de Refresh). Le panneau des vues de la page du ticket est celui de Refresh pour la boîte du ticket
  (nombres de cette boîte seulement), avec un repère « ← Toutes les boîtes ».
- Dans les cartes, le menu **« Agent »** propose les agents de la première boîte (liste construite par Refresh) ;
  FreeScout refuse côté serveur une assignation impossible.
- Vue tableau et cartes : colonnes rétrécies selon la largeur de la liste, panneau des filtres par-dessus la liste
  quand la place manque (1.8.1, requêtes de conteneur CSS : Chrome / Edge 105+, Firefox 110+, Safari 16+ ; un
  navigateur plus ancien garde la présentation de Refresh).
- Le panneau de gauche reprend la structure du panneau des vues de Refresh, mais avec « Mes vues », « Boîtes » et
  « Statuts » : les vues partagées de Refresh (par boîte) n'y figurent pas.
- Les **vues enregistrées** de RefreshGlobal sont personnelles (celles de Refresh sont partagées par boîte).

## Performances et index

La requête de la page (`GlobalTicketQuery`) est de la forme
`WHERE mailbox_id IN (…) AND state = 2 AND status <> 4 [AND user_id = …] ORDER BY last_reply_at DESC, id DESC`.

- Index existants de FreeScout utilisés : `(mailbox_id, state, status)`
  (`database/migrations/2025_09_06_010101_add_index_to_conversations_table.php`) et
  `(user_id, mailbox_id, state, status)` (`2021_09_21_010101_add_indexes_to_conversations_table.php`).
- Le tri par `last_reply_at` / `created_at` se fait sans index dédié (tri en mémoire sur les lignes filtrées) : sans
  effet sensible jusqu'à quelques dizaines de milliers de tickets ouverts. Sur une très grosse base, un index
  `(state, last_reply_at)` sur `conversations` accélérerait la page ; **le module ne l'ajoute pas** (règle : aucune
  modification des tables du cœur) — c'est une décision d'administrateur.
- La recherche texte utilise `LIKE '%…%'` (comme FreeScout et Refresh) et des sous-requêtes sur `customers` ; pas
  de jointure, donc le total de la pagination reste exact.
- Compteurs : deux requêtes `GROUP BY` (par boîte, par statut), aucune requête par ligne ; la liste précharge
  `mailbox`, `customer` et `user` (eager loading). Le partial natif interroge les favoris une fois par boîte affichée.
- Export : lecture par pages de 500 lignes, écriture en flux, plafond `REFRESHGLOBAL_EXPORT_MAX_ROWS` (5000).

## Après une mise à jour de FreeScout ou de Refresh

1. `cd /var/www/html && sudo -u www-data php artisan refreshglobal:check`
2. lire le rapport (code, attendu, effet, action) ;
3. corriger les points en échec — en général : installer la version de RefreshGlobal prévue pour la nouvelle
   version, ou, pour un développeur, mettre à jour `Config/integration.php`, `INTEGRATION_MAP.md` et ce fichier
   après avoir relu le code concerné.
