# Compatibilité

## Versions testées

| Composant | Version testée | Plage acceptée par `refreshglobal:check` | Hors plage |
|---|---|---|---|
| FreeScout | **1.8.245** (commit `f92cb04`, 2026-10-03) | `>= 1.8.0` et `< 1.9.0` | avertissement RG-ENV-01 |
| Refresh | **1.4.3** (tag `v1.4.3`) | `>= 1.4.0` et `< 1.5.0` | avertissement RG-REF-02 |
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
| `refresh.rail_items` | point d'extension documenté de Refresh | icône remplacée par le lien générique du menu (RG-HOOK-07) |

## Mise à jour automatique

- Passe par le planificateur de FreeScout (filtre `schedule`, `app/Console/Kernel.php:190`) : il faut le cron
  `* * * * * php /var/www/html/artisan schedule:run` (installé par le script officiel de FreeScout). Sans lui,
  utiliser `php artisan refreshglobal:update` à la main.
- Nécessite l'extension PHP zip, Guzzle et Symfony Process (fournis avec FreeScout) : contrôle RG-CORE-08 ; sinon
  la liste fonctionne, seule la mise à jour automatique est indisponible.
- Le module n'annonce pas ses versions au bouton « Mettre à jour » de FreeScout (pas de `latestVersionUrl`), car ce
  bouton ne sait pas revenir en arrière.
- Le retour arrière restaure les fichiers du module, ses vues enregistrées et annule ses nouvelles migrations ; il
  ne touche à aucune autre table. Sauvegardes : `storage/app/refreshglobal/backups/` (les 3 dernières).

## Écarts connus par rapport au style de Refresh

- La **recherche de la barre du haut** de Refresh cherche toujours dans « Tous les tickets » de la première boîte ;
  utiliser la recherche du panneau de filtres de la page.
- Après l'ouverture d'un ticket depuis la page globale, le **fil d'Ariane** et le **« ticket suivant »** de Refresh
  ramènent à la dernière vue Refresh, pas à la page globale (cookie `rf_last_view` de Refresh).
- Dans les cartes, le menu **« Agent »** propose les agents de la première boîte (liste construite par Refresh) ;
  FreeScout refuse côté serveur une assignation impossible.
- En **vue tableau** avec le panneau de filtres ouvert sur un écran étroit, la colonne du sujet est étroite (comme
  dans Refresh, plus la colonne « Boîte »).
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
