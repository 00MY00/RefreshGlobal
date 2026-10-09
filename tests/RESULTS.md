# Résultats des tests — RefreshGlobal 1.4.4

Date : 2026-10-09. Environnements jetables Docker : FreeScout 1.8.245 (dépôt officiel), PHP 8.2.34, Apache,
MariaDB 10.11 ; Refresh 1.4.3 (dépôt officiel). Données de test uniquement (adresses `.test`).

## 1. Analyse statique

| Vérification | Résultat |
|---|---|
| `shellcheck install.sh` (règles par défaut) | **0 avertissement** |
| `php -l` sur les 51 fichiers PHP du module, PHP **7.1.33** (minimum de FreeScout) | **0 erreur** |
| `php -l` sur les 51 fichiers PHP du module, PHP **8.4.26** | **0 erreur** |

## 2. Tests PHPUnit du module (`RefreshGlobal/Tests`, PHPUnit 11.5.57)

**49 tests, 578 assertions, 0 échec** (1.0.0 : 36 tests ; 1.0.1 : 37 ; 1.1.0 : 42 ; 1.2.0 : 45) (FreeScout 1.8.245 + Refresh 1.4.3 actif).

| Fichier | Tests | Contenu |
|---|---|---|
| `AccessTest` | 12 | liste limitée aux boîtes autorisées ; `mb[]` forcé vers une boîte interdite ignoré ; compteurs ; boîte archivée invisible à un non-admin ; permission « assignées uniquement » (y compris en forçant `assignee=none`) ; brouillons, supprimés, spam ; lien vers la page native du ticket ; recherche nom + numéro ; invité redirigé ; diagnostic réservé aux admins (403) ; lien vers la page dans la barre latérale des boîtes / le tiroir mobile de Refresh |
| `ExportTest` | 5 | BOM UTF-8 ; aucune ligne d'une boîte interdite, même demandée ; filtres ; plafond (2 lignes) ; formule `=HYPERLINK` neutralisée ; utilisateur « assignées uniquement » |
| `CompatibilityTest` | 13 | Refresh absent → dégradé + style standard + message admin seulement ; CSS absent → RG-CSS-01 avec message au format exigé (EN et FR) ; vue Refresh absente → dégradé ; vue du cœur absente → liste bloquée ; méthode absente → bloquant (liste, export) + liens de secours ; route absente → bloquant ; colonne absente → bloquant ; hook absent → dégradé ; version hors plage → avertissement ; clé de cache liée aux versions ; codes de sortie de `refreshglobal:check` ; échecs écrits dans le journal Laravel |
| `SavedViewsTest` | 5 | créer, charger, renommer, défaut (ouverte sur l'adresse nue), `?reset=1`, supprimer ; boîtes interdites non enregistrées ; boîte perdue ignorée avec mention ; vues personnelles (404 pour un autre utilisateur) ; validation |
| `UpdateSettingsTest` | 5 | mise à jour automatique : activer / désactiver (commande et page de diagnostic, réservé aux admins), tâche quotidienne dans le planificateur, image du module, pas de `latestVersionUrl` |
| `NavigationTest` | 3 | `shell.js` dans le lot de scripts ; réglages `<meta name="refreshglobal">` sur les pages Refresh (page active, option) ; option « remplacer Tickets » réservée aux admins |
| `SettingsSectionTest` | 4 | section Gérer › Paramètres › RefreshGlobal affichée et listée ; enregistrement par FreeScout (cases cochées / décochées) ; accès refusé aux non-admins ; « Mettre à jour maintenant » : demande enregistrée, tâche `--requested` planifiée, sans effet sans demande |
| `TranslationsTest` | 2 | 7 langues : mêmes clés et mêmes paramètres que l'anglais |

## 3. Vérifications visuelles (Chromium headless, captures dans `docs/screenshots/`)

| Cas | Résultat |
|---|---|
| Liste avec Refresh, vue cartes | identique à la liste de Refresh ; badge de boîte à côté des badges SLA ; icône dans la barre de gauche active |
| Vue tableau (cookie `rf_layout=table` de Refresh) | colonne « Boîte » ; sujet étroit quand le panneau de filtres est ouvert (écart documenté) |
| Filtres boîtes + statuts | 16 tickets ; compteurs par boîte et par statut cohérents |
| Téléphone (390 px) | cartes mobiles de Refresh avec pastille de la boîte ; 1.0.1 : entrée « Toutes les boîtes » en tête du tiroir des vues de Refresh ; tiroir de la page avec titres de section, « Exporter (CSV) » et vues enregistrées ; écran Filtres avec le choix multi-boîtes |
| Refresh désactivé | style FreeScout standard, bandeau `[RG-REF-01]` détaillé (admin) |
| Route simulée manquante | liste masquée, message `[RG-ROUTE-98]` au format exigé, liens vers les boîtes |
| Diagnostic | tableau des 35 contrôles (33 en 1.0.0), état OK |
| Export CSV | séparateur `;`, BOM, dates dans le fuseau de l'utilisateur, adresse du ticket |

## 4. Installeur `install.sh` — scénarios (`tests/installer/run_tests.sh`)

FreeScout existant dans des conteneurs jetables (Debian 12, PHP 8.2, MariaDB 10.11), archive du module donnée par
`--source` (l'adresse de publication n'existe pas encore). Exécuté deux fois, la seconde sur la version finale du
script : **48 vérifications réussies, 0 en échec**.

| Scénario | Vérification | Résultat | Détail |
|---|---|---|---|
| Ajout sur FreeScout existant avec Refresh | code de sortie 0 | PASS |  |
| Ajout sur FreeScout existant avec Refresh | étapes numérotées jusqu'à [7/7] | PASS |  |
| Ajout sur FreeScout existant avec Refresh | refreshglobal:check : OK | PASS |  |
| Ajout sur FreeScout existant avec Refresh | page /refresh-global/tickets = 200 | PASS |  |
| Ajout sur FreeScout existant avec Refresh | style Refresh utilisé | PASS |  |
| Ajout sur FreeScout existant avec Refresh | lien public créé | PASS |  |
| Ajout sur FreeScout existant avec Refresh | propriétaire www-data | PASS |  |
| Ajout sur FreeScout existant avec Refresh | sauvegarde de la base créée | PASS |  |
| Ajout sur FreeScout existant avec Refresh | aucun mot de passe dans le journal | PASS |  |
| Ajout sur FreeScout existant sans Refresh | code de sortie 0 | PASS |  |
| Ajout sur FreeScout existant sans Refresh | avertissement « Refresh absent » affiché | PASS |  |
| Ajout sur FreeScout existant sans Refresh | rapport : RG-REF-01 en échec | PASS |  |
| Ajout sur FreeScout existant sans Refresh | état dégradé annoncé dans le résumé | PASS |  |
| Ajout sur FreeScout existant sans Refresh | page = 200 | PASS |  |
| Ajout sur FreeScout existant sans Refresh | style FreeScout standard | PASS |  |
| Relance du script (idempotence) | code de sortie 0 | PASS |  |
| Relance du script (idempotence) | détecte la version déjà installée | PASS |  |
| Relance du script (idempotence) | aucune sauvegarde ni copie en double | PASS |  |
| Relance du script (idempotence) | une seule ligne dans la table modules | PASS |  |
| Relance du script (idempotence) | migration enregistrée une seule fois | PASS |  |
| --update (1.0.0 -> 1.0.1) | code de sortie 0 | PASS |  |
| --update (1.0.0 -> 1.0.1) | version 1.0.1 installée | PASS |  |
| --update (1.0.0 -> 1.0.1) | vues enregistrées conservées | PASS |  |
| --update (1.0.0 -> 1.0.1) | page = 200 | PASS |  |
| --rollback (retour à 1.0.0) | code de sortie 0 | PASS |  |
| --rollback (retour à 1.0.0) | version 1.0.0 restaurée | PASS |  |
| --rollback (retour à 1.0.0) | table du module restaurée | PASS |  |
| --rollback (retour à 1.0.0) | page = 200 | PASS |  |
| --dry-run | code de sortie 0 | PASS |  |
| --dry-run | actions affichées | PASS |  |
| --dry-run | rien n'est modifié (Modules, sauvegardes) | PASS |  |
| --dry-run | module non activé | PASS |  |
| --dry-run | aucun journal écrit | PASS |  |
| --uninstall | --yes : code de sortie 0 | PASS |  |
| --uninstall | module désactivé | PASS |  |
| --uninstall | --yes sans --drop-tables : table conservée | PASS |  |
| --uninstall | page retirée (404) | PASS |  |
| --uninstall | FreeScout fonctionne toujours (tableau de bord 200) | PASS |  |
| --uninstall | --drop-tables --remove-files : code 0 | PASS |  |
| --uninstall | table supprimée (migration réversible) | PASS |  |
| --uninstall | fichiers supprimés | PASS |  |
| --uninstall | lien public supprimé | PASS |  |
| Échec volontaire : PHP absent | arrêt avec code 1 | PASS |  |
| Échec volontaire : PHP absent | message : étape + cause | PASS |  |
| Échec volontaire : PHP absent | message : action proposée | PASS |  |
| Échec volontaire : mauvais chemin | arrêt avec code 1 | PASS |  |
| Échec volontaire : mauvais chemin | message explicite | PASS |  |
| Échec volontaire : mauvais chemin | action proposée | PASS |  |

**48 vérifications réussies, 0 en échec.**
## 5. Installation complète (`--full`) sur Ubuntu 24.04 vierge (`tests/installer/full_install_test.sh`)

Conteneur `ubuntu:24.04` neuf (+ `sudo`, et `expect` qui répond aux questions du script officiel d'après leur
texte). Premier passage : `install.sh --full` lance le script officiel de FreeScout, puis s'arrête proprement en
demandant de terminer l'installation web ; celle-ci est émulée (`.env`, migrations, administrateur) ; second
passage : même commande, qui installe Refresh depuis `--refresh-zip` puis RefreshGlobal.

| Vérification | Résultat | Détail |
|---|---|---|
| 1er passage : code de sortie 0 | PASS | |
| FreeScout installé par le script officiel | PASS | |
| arrêt propre en attente de l'installation web (`--yes`) | PASS | |
| mot de passe affiché par le script officiel absent de notre journal | PASS | |
| base « freescout » créée par le script officiel | **FAIL** | limite du conteneur : sa politique `policy-rc.d` interdit le démarrage des services pendant `apt`, donc MySQL ne tournait pas pendant le script officiel ; la base a été créée par le test. Sur un vrai serveur (systemd), le script officiel la crée. |
| installation web émulée | PASS | |
| 2e passage : code de sortie 0 | PASS | |
| étapes [1/10] à [10/10] | PASS | |
| Refresh installé depuis `--refresh-zip` | PASS | Refresh 1.4.3 |
| `refreshglobal:check` : OK | PASS | |
| page « Toutes les boîtes » servie par nginx + PHP-FPM 8.3 (200, style Refresh) | PASS | |

**10 vérifications réussies, 1 échec dû à l'environnement de test.**

Adaptations du banc au conteneur (comportement d'un vrai serveur reproduit) : `--init` (récupération des processus
terminés, attendue par le paquet `mysql-server`), `/run/mysqld` en `0755` (créé ainsi par systemd,
`RuntimeDirectory=mysqld`), démarrage manuel de nginx et PHP-FPM.

Constats intégrés au script et au README pendant ces essais :
- sur un serveur neuf, nginx place sa page par défaut dans `/var/www/html` : le script officiel demande alors de
  vider ce dossier ; il faut répondre `Y` (message ajouté avant le lancement et dans l'aide en cas d'échec) ;
- le script officiel demande trois confirmations `apt` : elles passent par le terminal.

## 6. Non testé

- Base PostgreSQL (le code évite les fonctions propres à MySQL, mais aucun essai n'a été fait).
- Installation complète sur Debian (seul Ubuntu 24.04 a été essayé) et sur une vraie machine virtuelle avec systemd.
- Téléchargement depuis l'adresse de publication (le dépôt n'est pas encore publié) : remplacé par `--source`.
## 7. Mise à jour automatique avec retour arrière (1.1.0, `run_tests.sh auto-update`)

FreeScout + Refresh jetables ; cinq fausses publications servies en local (`REFRESHGLOBAL_UPDATE_URL=file:///…`) :
bonne version 9.0.1, version bloquante 9.0.2, version qui fait planter PHP 9.0.3, version qui ajoute une table puis
échoue 9.0.4, archive à l'empreinte fausse 9.0.5. Exécuté deux fois (la seconde après la correction de la lecture du
réglage) : **16 vérifications réussies, 0 en échec**.

| Scénario | Vérification | Résultat | Détail |
|---|---|---|---|
| Mise à jour automatique avec retour arrière | install.sh --auto-update=on | PASS |  |
| Mise à jour automatique avec retour arrière | --check : nouvelle version détectée | PASS |  |
| Mise à jour automatique avec retour arrière | bonne version : installée (9.0.1) | PASS |  |
| Mise à jour automatique avec retour arrière | page = 200 après mise à jour | PASS |  |
| Mise à jour automatique avec retour arrière | vues enregistrées conservées | PASS |  |
| Mise à jour automatique avec retour arrière | version bloquante : retour automatique à 9.0.1 (code 3) | PASS |  |
| Mise à jour automatique avec retour arrière | page de nouveau affichée après le retour arrière | PASS |  |
| Mise à jour automatique avec retour arrière | bandeau d'avertissement pour l'administrateur | PASS |  |
| Mise à jour automatique avec retour arrière | version annulée non retentée automatiquement | PASS |  |
| Mise à jour automatique avec retour arrière | version qui plante PHP : retour automatique | PASS |  |
| Mise à jour automatique avec retour arrière | FreeScout fonctionne (tableau de bord 200) | PASS |  |
| Mise à jour automatique avec retour arrière | nouvelle table annulée (migration défaite) | PASS |  |
| Mise à jour automatique avec retour arrière | empreinte SHA-256 fausse : refusée, rien modifié | PASS |  |
| Mise à jour automatique avec retour arrière | désactivée : la tâche quotidienne vérifie sans installer | PASS |  |
| Mise à jour automatique avec retour arrière | tâche quotidienne enregistrée dans le planificateur de FreeScout | PASS |  |
| Mise à jour automatique avec retour arrière | install.sh --update bloquant : retour automatique | PASS |  |

**16 vérifications réussies, 0 en échec.**

## 8. Onglet téléphone et option « remplacer Tickets » (1.2.0, captures 390 px et 1440 px)

| Cas | Résultat |
|---|---|
| Liste Refresh sur téléphone, option désactivée | onglet « Toutes les boîtes » ajouté après « Tickets » (6 onglets) |
| Option activée, téléphone | onglet « Tickets » de Refresh masqué, « Toutes les boîtes » en 1ʳᵉ position (5 onglets) |
| Option activée, ordinateur | barre de gauche : Tableau de bord, Toutes les boîtes, Contacts (« Tickets » masqué) |

## 9. Correctif 1.2.1 : auto-test avec un APP_URL de production

Avec `APP_URL=https://helpdesk.example.test` : la 1.2.0 obtenait HTTP 403 (`TrustHosts` de FreeScout), la 1.2.1 HTTP 200. Scénarios rejoués : ajout avec Refresh (dont la nouvelle vérification « selftest avec un APP_URL de production ») et mise à jour automatique : **26 vérifications réussies, 0 en échec**.

## 10. Paramètres et « Mettre à jour maintenant » (1.3.0)

Scénario `auto-update` rejoué avec trois vérifications de plus (rien sans demande ; mise à jour faite par la tâche planifiée ; demande effacée) : **19 vérifications réussies, 0 en échec**. Capture : `docs/screenshots/parametres.png`.

## 11. Tableau de bord, suppression, réglages (1.4.0)

FreeScout 1.8.245 + Refresh 1.4.3 neufs, `refreshglobal:check` : état OK (RG-HOOK-18…22, RG-CORE-09…12, RG-ROUTE-04).

- PHPUnit : **62 tests, 816 assertions, 0 échec** (nouveaux : `DashboardTest` 4, `DeleteTest` 8, `SettingsSectionTest` +1).
- Syntaxe PHP 7.1 (`php:7.1-cli`) et 8.4 : aucune erreur ; shellcheck : 0 remarque.
- Navigateur (Chromium headless, 1440 px et 390 px, interface en français) : un seul tableau de bord (celui de
  Refresh remplacé), tuiles sur les 4 boîtes, « Créé il y a 2h » au lieu de « Créé 2h il y a » sur téléphone, nom et
  adresse de la boîte au-dessus de chaque ticket, puce « Vue Refresh ». Captures : `docs/screenshots/tableau-de-bord*.png`.
- Suppression réelle depuis la page d'un ticket (boutons natifs de FreeScout) après avoir ouvert
  `Toutes les boîtes ?mb[]=Support` : retour sur cette même liste, ticket dans la corbeille (`state = 3`).
- Défaut trouvé et corrigé pendant les essais : le tableau de bord mis en cache une minute gardait la langue de la
  visite précédente (clé de cache sans la langue) ; test de non-régression ajouté.

## 12. Bouton « Rechercher une mise à jour » (1.4.1)

- PHPUnit : **63 tests, 874 assertions, 0 échec** (nouveau `testCheckForUpdates` : version disponible, à jour,
  FreeScout trop ancien, publication injoignable, réservé aux administrateurs ; rien n'est installé).
- Navigateur, avec une publication locale en 1.5.0 : clic dans Gérer › Paramètres › RefreshGlobal → message « La
  version 1.5.0 est disponible (installée : 1.4.0)… » ; la page de diagnostic affiche « mise à jour disponible ».

## 13. Sélecteur de langue (1.4.2)

- PHPUnit : **67 tests, 921 assertions, 0 échec** (nouveau `LanguageTest` : sélecteur affiché, langue du profil et
  session modifiées, langue inconnue refusée, retour vers un autre site ignoré). `refreshglobal:check` : RG-VIEW-06 OK.
- Navigateur : interface en anglais, choix « Français » dans le sélecteur → la page, la barre de Refresh et le
  tableau de bord passent en français ; `users.locale = fr` en base. Sélecteur visible en bas du panneau des vues
  (ordinateur) et du tiroir (téléphone).

## 14. Sans release publiée : branche `main` (1.4.3)

- PHPUnit : **67 tests, 932 assertions, 0 échec** (`testCheckForUpdates` : release absente → `module.json` de la
  branche lu, source « branche main » indiquée ; ni release ni branche → message clair avec l'adresse).
- Scénario `auto-update` (`run_tests.sh`) : **22 vérifications réussies, 0 en échec**, dont trois nouvelles : sans
  release, `refreshglobal:update` installe la version de la branche (archive au format GitHub
  `RefreshGlobal-main/RefreshGlobal/`), page 200 ensuite ; `install.sh --update` sans release installe la branche.
- shellcheck : 0 remarque. Adresses réelles vérifiées : `raw.githubusercontent.com/…/main/RefreshGlobal/module.json`
  et `github.com/…/archive/refs/heads/main.zip` répondent 200.
