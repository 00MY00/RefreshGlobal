# Résultats des tests — RefreshGlobal 1.16.1

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

## 29. Spams, tags de provenance, détails / texte brut, repère sur les vues Refresh (1.11.0)

- `refreshglobal:check` : RG-HOOK-30…35, RG-DB-11 OK, RG-CORE-18 ignoré (module Tags absent), état OK.
- PHPUnit : **117 tests, 0 échec** (1 ignoré : SyncNow) ; nouveau `MailTagsTest`.
- **Vrai serveur IMAP (GreenMail)**, boîte relevant INBOX et Junk :
  - par défaut : e-mail normal → ticket tagué `inbox` ; e-mail `X-Spam-Flag: YES` et e-mail du dossier Junk → **pas
    de ticket** (« should_save_thread returned false ») et **toujours sur le serveur** (INBOX / Junk) ;
  - réglage désactivé : spams importés avec `classified_spam, inbox` (en-tête) et `classified_spam, spam` (Junk) ;
  - « Recalculer les tags » après copie d'un e-mail dans `INBOX.[WP] Factures` sur le serveur : 6 e-mails lus,
    **1 tag ajouté** → ticket `folder:wp-factures, inbox`.
- Navigateur : filtre `classified_spam` (4 tickets), section « Tags d'e-mail » (classified_spam 4, folder:wp-factures 1,
  inbox 3, spam 2), badges ; page du ticket : tags sous l'objet, bouton « i » ; fenêtre **Détails** (type, de, à,
  date, boîte, Message-ID, dossier du serveur, tags), **Texte brut** (en-têtes reçus + texte), source originale de
  FreeScout, Échap ; téléphone : bouton visible ; **vue Refresh d'une boîte** : repère « ← All mailboxes 4 / Mailbox:
  Billing » en haut du panneau, vers la dernière liste ; tiroir du téléphone : repère visible ; aucune erreur
  JavaScript.

## 30. Retour à « Toutes les boîtes » après la fermeture d'un ticket (1.13.0)

- Reproduit sur une instance neuve (FreeScout 1.8.245, Refresh 1.4.3, données de démonstration), script
  `closenext.js` (Chromium headless, bureau 1440 px et téléphone 390 px) : liste « Toutes les boîtes » filtrée sur
  « Actif », ouverture d'un ticket, puis « Fermer » de Refresh trois fois de suite.
- Avant (1.12.0) : tant qu'un ticket actif restait dans la boîte, le ticket suivant gardait le retour ; à la
  fermeture du dernier, FreeScout affichait `/mailbox/1/tickets/unassigned` et le ticket ouvert ensuite revenait à
  « Non assignés » (Refresh) au lieu de « Toutes les boîtes » — **reproduit**.
- Après (1.13.0) : bureau et téléphone, **10 vérifications OK sur 10** : chaque ticket suivant garde le retour
  « Toutes les boîtes » (fil d’Ariane, flèche du téléphone, repère du panneau) ; à la fermeture du dernier ticket
  actif de la boîte, on revient directement à `/refresh-global/tickets?status[]=1` (au lieu de la liste de la boîte) et
  le ticket ouvert ensuite garde le retour ; aucune erreur JavaScript. `refreshglobal:check` : RG-HOOK-36 OK.
- PHPUnit : **117 tests, 1681 assertions, 0 échec** (1 ignoré : SyncNow absent).

## 31. Refresh 1.6.0, tableau de bord ouverts / en attente, aperçus au survol (1.14.0)

- Instance neuve : FreeScout 1.8.245, **Refresh 1.6.0** (tag `v1.6.0`, commit `4259031`), données de démonstration.
  `refreshglobal:check` : état **OK** (avant la mise à jour de la plage : avertissement RG-REF-02 « hors de la plage
  testée ») ; les 13 éléments de Refresh cherchés dans ses fichiers sont présents ; RG-CORE-19 et RG-HOOK-37 OK.
- PHPUnit : **124 tests, 1728 assertions, 0 échec** (1 ignoré : SyncNow absent), dont `DashboardUnresolvedTest`
  (7 tests : ouverts seulement, en attente seulement, les deux, tri par total puis par nom — l’exemple Alice 7 + 5,
  Bob 10 + 0, Charlie 2 + 3 donne Alice, Bob, Charlie —, « Non assigné », boîtes permises et droit « seulement ses
  tickets », tableau de bord vide, lignes d’une version précédente, même nombre de requêtes pour 1 et 7 lignes).
- Navigateur (Chromium headless) avec Refresh 1.6.0 : affichage à 1024, 1280, 1440, 1920 et 820 px (`layout.js`,
  OK), retour à « Toutes les boîtes » après fermeture (`closenext.js`, 10/10), sélection par appui long sur
  téléphone (`select.js`, 9/9), adresse d’envoi ordinateur et téléphone (`sendfrom.js`, `phonesf.js`, OK).
- Tableau de bord (`dash114.js`) : carte « Agent | Open | Pending », lignes triées par total, chaque nombre mène à la
  liste filtrée (`status[]=1&assignee=none`…) ; à 390 px, en-tête masqué et « Open : 7 » sous le nom.
- Aperçus au survol (`hover114.js`, souris simulée : Chromium sans écran se déclare sans souris et Refresh coupe alors
  ses aperçus) : aperçu du premier message sur « Toutes les boîtes » comme sur la liste de Refresh ; l’entrée
  « Aperçus au survol » du menu Affichage de « Toutes les boîtes » les coupe (cookie `rf_hover=off`) ; aucune erreur
  JavaScript.

## 32. Vues enregistrées partagées (1.15.0)

- Instance FreeScout 1.8.245 + Refresh 1.6.0, `refreshglobal:check` : état **OK**, RG-DB-05 (nouvelles colonnes),
  RG-DB-12…14 et RG-CORE-20 OK.
- PHPUnit : **139 tests, 1926 assertions, 0 échec** (1 ignoré : SyncNow absent), dont `SharedViewsTest` (15 tests :
  vue privée, partagée avec des utilisateurs choisis, pour tous et réglage « admins seulement », droits de
  modification, copie, suppression, « moi » = la personne qui regarde, boîtes non permises jamais montrées — liste,
  export, URL forcée, droit « tickets assignés » —, boîte / agent / utilisateur supprimés, définition illisible, ancien
  format converti, cache vidé par les changements, même nombre de requêtes pour 1 et 9 vues, défauts, épinglage,
  ordre, export / import, page de gestion et rendu téléphone).
- Mise à niveau (`upgrade_views.php` sur l’instance) : table des vues remise au format 1.14 avec une vue par défaut,
  migration 1.15 : vue conservée (privée, format 1), vue par défaut reprise dans les préférences et ouverte par
  défaut ; migration relancée sans effet.
- Navigateur (`views115.js`, Chromium headless) : l’admin enregistre « Team open tickets » partagée avec Sam (liste
  des utilisateurs affichée pour « Utilisateurs choisis », « peut modifier » à côté de l’utilisateur coché) ; Sam la
  trouve dans « Partagées avec moi », change le statut : « Team open tickets * », bandeau « Modifications non
  enregistrées » avec « Enregistrer comme nouvelle » et « Rétablir », **sans** « Mettre à jour » ; « Enregistrer comme
  nouvelle » ouvre le formulaire, nom « Team open tickets (copy) » ; page « Vues enregistrées » (tableau, journal
  admin) ; à 390 px : cartes, pas de défilement horizontal (liste et page de gestion) ; aucune erreur JavaScript.
- Non-régression avec Refresh 1.6.0 : `layout.js` (5 largeurs), `closenext.js`, `select.js`, `dash114.js` OK.

## 33. Titre de ticket sur téléphone, feuille regroupée, labels (1.15.1)

- Reproduit (Chromium headless, 412 px) avec Workflow Packs actif et des tickets classés dans « [WP] Achats et
  licences › Livraisons » : après « Fermer », le ticket suivant affichait son sujet en largeur 0 (une lettre par ligne).
- Cause 1 : `.conv-subjwrap` de Refresh en `display: flex` sans retour à la ligne + badge de dossier non rétrécissable.
  Cause 2 : dans la feuille regroupée par FreeScout, les règles du module après le premier `@container` étaient
  imbriquées dans `@media (min-width: 768px)` (vérifié dans le CSSOM : « @media (min-width: 768px) > @media
  (max-width: 767px) »), donc jamais appliquées sur téléphone.
- Après : sujet 249 px puis badge sur la ligne suivante, `flex-wrap: wrap` appliqué ; mise en page de la liste à 1024,
  1280, 1440, 1920 et 820 px inchangée (`layout.js` OK).
- Labels (`labels.js`, 7 pages × ordinateur et téléphone) : avant, 3 `label for` invalides sur la page d'un ticket et
  2 sur « Nouvel e-mail » (formulaires de FreeScout) ; après : aucun.
- PHPUnit : **140 tests, 1924 assertions, 0 échec** (2 ignorés : SyncNow absent ; « sans Workflow Packs », celui-ci
  étant installé), dont `LayoutTest::testBundledStylesheetHasNoContainerQuery`.

## 34. Déplacer vers un dossier du serveur, images distantes (1.16.0)

- Bout en bout avec un vrai serveur IMAP (GreenMail) : boîte IMAP relevant INBOX et Junk, dossiers `INBOX.Factures`
  et `INBOX.Clients` créés sur le serveur, 2 e-mails HTML avec un pixel de suivi et un logo distants, relevés par
  `freescout:fetch-emails` (tags `inbox`).
- Ticket (ordinateur, Refresh 1.6.0) : « 2 remote images not loaded (protects from tracking) », aucune requête vers le
  site distant avant le clic ; « Show images » les charge (2 requêtes vues). Bouton « Move » juste après « Close » ;
  fenêtre : INBOX, Clients, Factures (actuel), Junk ; choix d'un dossier → message « 1 e-mail moved to … » avec Undo.
- Téléphone (412 px, tactile) : glisser la première carte vers la gauche (indicateur « Move », bleu au-delà de 90 px)
  ouvre la fenêtre sans ouvrir le ticket ; choix d'un dossier → repère « → Factures » sur la carte, message avec
  « UNDO » ; Undo → l'e-mail revient dans son dossier précédent.
- Lecture indépendante du serveur après coup (`imap_list.php`) : e-mails dans les dossiers attendus ; tags du ticket
  `folder:clients` / `inbox` conformes ; ticket inchangé (statut Actif).
- Correction trouvée par ce test : le verrou « le relâchement du glissement n'ouvre pas le ticket » bloquait aussi le
  clic dans la fenêtre ; il ne vise plus que les lignes de la liste.
- PHPUnit : **150 tests, 2110 assertions, 0 échec** (2 ignorés : SyncNow absent ; « sans Workflow Packs »), dont
  `MailMoveTest` (5) et `RemoteImagesTest` (5). `refreshglobal:check` : RG-HOOK-38…41, RG-CORE-21/22 OK.

## 28. Adresse d'envoi au choix (1.10.0)

- `refreshglobal:check` : RG-HOOK-28/29, RG-CORE-17, RG-ROUTE-11 OK, état OK.
- Navigateur (boîte Support avec alias `help@` (Help desk) et `info@`, « réponse depuis les alias » **désactivé**) :
  depuis « Toutes les boîtes » filtrée sur Sales, « Nouveau › E-mail » s'ouvre **sur Sales** (champ « De », 5 adresses
  groupées Billing / Sales / Support) ; destinataire, objet et message saisis, choix de `help@` → formulaire de
  **Support**, saisie **reprise**, `from_alias = help@example.test` ; **envoi** → ticket créé dans Support, message
  `from = help@example.test` (base de données) ; « Nouveau ticket » ouvert depuis le tableau de bord → dernière boîte
  utilisée (Support), champ « Boîte » sans alias ; téléphone : « DE » (e-mail) et « BOÎTE » (ticket) visibles,
  changement de boîte en gardant l'objet ; aucune erreur JavaScript.
- PHPUnit : nouveau `SendFromTest` (adresses et droits, alias étranger retiré, middleware dans le groupe `web`,
  données de la page).
## 27. UpdateGuard 1.2.0 et sélection par appui long renforcée (1.9.2)

- **UpdateGuard 1.2.0** (release) avec RefreshGlobal **1.9.0** (release) : détection « 1.9.0 → 1.9.1 », niveau
  **✓ Full Safe Update** ; mise à jour manuelle : archive téléchargée, **SHA-256 vérifié**, zone d'attente, sauvegarde,
  installation, **contrôle de santé natif réussi**, version marquée stable ; **restauration** de 1.9.0 (sauvegarde de
  1.9.1 d'abord, santé OK) ; **mise à jour automatique** par le planificateur (`updateguard:run`) 1.9.0 → 1.9.1, santé
  OK ; module abîmé (fichier retiré) → « × Failed » avec le fichier manquant, puis « ✓ Healthy » une fois remis.
  Page « Toutes les boîtes » rendue après chaque étape.
- Téléphone : appui long puis appui long natif de FreeScout (`taphold`) pendant l'appui et après le relâchement →
  changement **annulé** (« UNDONE … -> true »), ticket toujours sélectionné ; toucher ultérieur → décoché normalement ;
  scénario complet de sélection (appuis 1,8 s / 0,9 s, touchers, compteur, sortie du mode, ouverture d'un ticket) : OK ;
  journal `?rg_debug=touch` affiché (touchstart, évènements souris émulés, clic ignoré, annulation).

## 26. Téléphone : sélection par appui long (1.9.1)

- Reproduction (navigateur en mode téléphone, vrais évènements tactiles, chaque changement de case tracé avec sa pile
  d'appels) : appui 0,9 s → ticket coché ; appui **1,8 s** → coché à 0,5 s puis **décoché par le clic du relâchement**
  (gestionnaire de clic de `mobile.js` de Refresh), mode sélection refermé — sur « Toutes les boîtes » et de la même
  façon sur la liste de Refresh.
- Après correction, sur « Toutes les boîtes » : appui 1,8 s → 1 sélectionné ; toucher d'un 2e → 2 ; appui 1,8 s d'un
  3e → 3 ; toucher du 1er → décoché (2) ; appui 0,9 s → 3 ; touchers → 0, mode sélection quitté ; appui long puis
  toucher rapide d'un autre → 2 ; toucher normal hors sélection → le ticket s'ouvre ; aucune erreur JavaScript.

## 25. Mises à jour par FreeScout ou UpdateGuard (1.9.0)

- Instance neuve **sans UpdateGuard** : `refreshglobal:check` RG-ROUTE-09 OK (page Modules), RG-ROUTE-10 ignoré, état OK ;
  `refreshglobal:health --json` → `{"status":"ok", … "checks": files, environment, assets, views, hooks, classes,
  routes, database, page : ok}`, code 0 ; plus de commande `refreshglobal:update`. Réglages et diagnostic : « se met à
  jour sur la page Modules de FreeScout », lien `/modules/list`, conseil UpdateGuard avec son lien ; plus aucun bouton
  de l'ancienne mise à jour. PHPUnit : **104 tests, 0 échec** (1 ignoré : SyncNow).
- **UpdateGuard 1.0.0** installé depuis sa release (SHA-256 vérifié) : RG-ROUTE-10 OK ; `updateguard:health
  RefreshGlobal` → « ✓ Healthy », **Native health check: PASS [refreshglobal:health]**, validation générique PASS
  (module.json, ServiceProvider, espace de noms, autoload, 18 vues, routes, démarrage de Laravel, 13 routes du module) ;
  `updateguard:check RefreshGlobal` réussi. Réglages et diagnostic : « mis à jour par le module UpdateGuard », lien
  vers sa page de RefreshGlobal. PHPUnit : **104 tests, 0 échec**.
- **Après publication de la release v1.9.0** (CI verte, `version.txt` = 1.9.0), mises à jour réelles depuis GitHub :
  - **1.8.2 → 1.9.0 par l'ancienne mise à jour du module** (`refreshglobal:update` de la 1.8.2) : « RefreshGlobal
    updated 1.8.2 → 1.9.0 (state: ok) », sans retour arrière ; ensuite plus de `refreshglobal:update`,
    `refreshglobal:health` sain, les 2 options de l'ancienne mise à jour effacées par la migration ;
  - **page Modules de FreeScout** (version installée ramenée à 1.8.99) : carte RefreshGlobal « A new version is
    available: 1.9.0 … Update Now » ;
  - **UpdateGuard 1.0.0** (`updateguard:update RefreshGlobal`, 1.8.99 → 1.9.0) : mise à jour détectée, archive
    téléchargée, **SHA-256 vérifié**, extraction et contrôle en zone d'attente, sauvegarde, fichiers installés,
    **contrôle de santé natif réussi** (`refreshglobal:health`), observation de 10 minutes ; version 1.9.0 installée.

## 24. Retour à « Toutes les boîtes » depuis un ticket, bandeau du dossier (1.8.2)

- Navigateur (Refresh + Workflow Packs 1.14.0, tickets classés par son code) : liste du dossier « [WP] Factures » →
  bandeau « Dossier automatique [WP] Factures — 9 ticket(s) » avec « Quitter le dossier » (lien sans `wf`) ; ouverture
  du 3e ticket → fil d'Ariane « Toutes les boîtes › 3 » vers la liste **sans le dossier** (`#rg-back=<ticket>`),
  repère « ← Toutes les boîtes 40 / Boîte : Support » au-dessus du panneau des vues ; clic sur le fil d'Ariane →
  liste sans dossier, ouverte **à la ligne du ticket** ; téléphone : flèche retour vers la même liste ; après une vue
  Refresh, le fil d'Ariane de Refresh revient (`/mailbox/N/tickets/all`), sans repère ; aucune erreur JavaScript.
- `refreshglobal:check` : RG-HOOK-25/26/27 OK, état OK.
- PHPUnit : **107 tests, 0 échec** (2 ignorés : SyncNow ; « sans Workflow Packs » car il est installé) ; nouveaux
  tests : bandeau, liste de retour sans le dossier, suppression → liste sans le dossier, repère de la page du ticket
  (boîte visible seulement si l'utilisateur y a accès).

## 23. Affichage selon la largeur (1.8.1)

- Navigateur, liste filtrée (recherche + boîte + statut), sujets et noms de boîte longs, largeurs 1280 (zoom 150 %,
  comme un écran 1080p à 150 %), 1024, 1440, 1920, 820 px et téléphone :
  - bouton « Filtres » toujours **dans** la liste et visible, à toutes les largeurs ;
  - 1280, 1024, 820 px : panneau **par-dessus** la liste (la liste garde 924 / 668 / 754 px), ouvert par
    « Filtres » et « Modifier », fermé par Échap, ×, clic à côté ; barre « Filtres actifs » avec 3 pastilles ;
  - 1440, 1920 px : panneau à droite ; × le replie sur le côté (liste 784 → 1084 px, 1264 → 1564 px), la barre
    « Filtres actifs » apparaît, « Modifier » le rouvre ;
  - sujet des cartes à 1280 px : **460 px** (≈ 200 avant) ; vue tableau à 1280 px : colonne « Ticket » **322 px**
    (92 avant) ; sans filtre : pas de barre ; téléphone : présentation de Refresh, pastille « boîte » au nom seul ;
    aucune erreur JavaScript.
- PHPUnit : nouveau `LayoutTest` (barre et pastilles, liens qui n'enlèvent qu'un filtre, adresse de la boîte hors
  du texte du badge).

## 22. Dossiers automatiques de Workflow Packs (1.8.0)

- PHPUnit **sans Workflow Packs** : **101 tests, 1488 assertions, 0 échec** (1 ignoré : SyncNow) ;
  `refreshglobal:check` : RG-DB-09/10 et RG-ROUTE-07/08 « ignorés », état OK.
- **Workflow Packs 1.14.0** installé depuis sa release publiée (`WorkflowPacks.zip`, SHA-256 vérifié) :
  `refreshglobal:check` RG-DB-09/10, RG-ROUTE-07/08 OK, état OK ; PHPUnit **101 tests, 0 échec** (2 ignorés :
  SyncNow, et le test « sans Workflow Packs »). Le test de suppression définitive vérifie, avec Workflow Packs actif,
  que RefreshGlobal (priorité 20) lit le dossier avant que Workflow Packs efface sa ligne (priorité 1000).
- Navigateur (14 tickets classés **par le code de Workflow Packs**, `TicketFolders::record`, un retiré par le
  nettoyage, une règle « Newsletters : corbeille après 7 j ») : section « Dossiers automatiques » avec l'arbre
  ([WP] Factures 9 › Clients 3, Fournisseurs 3 ; [WP] Newsletters 2 ; [WP] Support › Urgent 2) ; clic → liste
  filtrée (9 tickets, titre « [WP] Factures · Toutes les boîtes », puce supprimable dans les filtres) ; badge
  dossier sur chaque ticket (cartes, téléphone) ; « Vue d'ensemble » : 5 dossiers, boîtes, « corbeille du serveur
  après 7 j », liens vers les règles et l'historique de Workflow Packs ; tiroir du téléphone ; style standard de
  FreeScout (sans Refresh) ; aucune erreur JavaScript. Icône dossier en SVG (un émoji n'est pas dessiné sans police
  d'émojis) ; retraits par classes CSS (les styles en ligne peuvent être bloqués par la CSP).
- **Bout en bout, vrai serveur IMAP** (GreenMail) : e-mail relevé, déplacé dans `INBOX.[WP] Factures`, ticket
  classé par Workflow Packs, ticket supprimé définitivement → file : `imap_path = INBOX.[WP] Factures` ;
  `refreshglobal:mail-trash` : **1 e-mail déplacé** ; contrôle sur le serveur : `INBOX.[WP] Factures` vide,
  `Trash` contient l'e-mail.

## 21. Animation de chargement (1.7.1)

- PHPUnit : **92 tests, 1334 assertions, 0 échec** (1 ignoré : SyncNow non installé).
- Navigateur, réponses du serveur ralenties exprès (4 s) : « Rechercher une mise à jour » → icône tournante à la place
  de la loupe, les deux boutons désactivés ; « Vider la corbeille » avec confirmation annulée → rien ne tourne, bouton
  actif ; « Appliquer » des filtres → icône tournante, bouton désactivé.

## 20. Bouton « Relever les e-mails » avec SyncNow (1.7.0)

- Module tiers **SyncNow 1.3.0** (rabsym/freescout-syncnow) installé et activé à côté de Refresh et RefreshGlobal ;
  `refreshglobal:check` : RG-ROUTE-05/06 OK (ignorés quand SyncNow n'est pas actif).
- PHPUnit : **92 tests, 1334 assertions** sans SyncNow actif : 0 échec (1 test SyncNow ignoré). Avec SyncNow actif,
  les tests SyncNow de RefreshGlobal passent ; 8 tests d'`AccessTest` tombent sur « Faker\Factory introuvable » :
  SyncNow charge ses « factories » hors production (`SyncNowServiceProvider::registerFactories`, ligne 125) et
  FreeScout n'embarque pas Faker — limité à un environnement de test, sans effet en production.
- **Bout en bout** (vrai serveur mail GreenMail, vrai SyncNow) : un nouvel e-mail est déposé sur le serveur (non
  relevé), la page « Toutes les boîtes » montre le bouton (boîte « Mail test ») ; clic → icône qui tourne, SyncNow
  relève **1 e-mail** (historique SyncNow : success), la liste se recharge seule et **le nouveau ticket s'affiche**.
  Barre d'outils : bouton en icône seule, « Filtres » reste visible ; téléphone : « Relever les e-mails » dans le
  tiroir.

## 19. Actualisation automatique (1.6.0)

- PHPUnit : **89 tests, 1266 assertions, 0 échec** (nouveau `AutoRefreshTest`, 5 tests).
- Navigateur (réglage 10 s, nouveaux tickets créés pendant l'essai) : liste avec un ticket coché → nouveau ticket
  arrivé, **pas de rechargement** ; ticket décoché → **rechargement automatique**, nouveau ticket affiché ; tableau
  de bord → rechargement automatique, tuile « Non résolu » **31 → 32** et nouveau ticket dans la liste.
- CI GitHub de la 1.5.3 : **vert** (lint 7.1/8.2/8.4 avec shellcheck, tests du module) ; release v1.5.3 créée
  automatiquement (`RefreshGlobal.zip`, `install.sh`, `module.json`, `SHA256SUMS`).

## 18. E-mails des tickets supprimés définitivement → corbeille du serveur mail (1.5.2)

- PHPUnit : **84 tests, 1233 assertions, 0 échec** (nouveau `MailServerTrashTest`, 9 tests avec un faux serveur IMAP :
  « Supprimer définitivement » met en file les e-mails du client (dossiers relevés) et les réponses (dossier Envoyés),
  sans `< >` ; mise à la corbeille de FreeScout et suppression d'une boîte entière : rien ; « Vider la corbeille » :
  en file ; réglage désactivé : rien ; traitement : déplacés / introuvable ; corbeille absente → erreur, nouvel essai,
  puis corbeille indiquée dans les réglages ; boîte POP3 ignorée ; tâche planifiée chaque minute).
- **Bout en bout avec un vrai serveur mail** (GreenMail, IMAP + SMTP) : 2 e-mails envoyés, relevés par FreeScout
  (2 tickets) ; le premier ticket mis à la corbeille puis « Vider la corbeille » → `refreshglobal:mail-trash` :
  « 1 e-mail(s) moved to the mail server's trash ». Lecture directe du serveur ensuite : **INBOX : e2e-second**,
  **Trash : e2e-first** ; le second ticket est intact dans FreeScout.

## 17. Rester à sa place dans la liste, suppression sur téléphone (1.5.1)

- PHPUnit : **75 tests, 1094 assertions, 0 échec** (`testKeepPositionSetting`, redirection avec `#rg-deleted=ID`).
- Navigateur (Chromium headless piloté par Puppeteer, 79 tickets de démonstration) :
  - ordinateur, 15ᵉ ticket placé à 300 px, ouvert puis supprimé → retour : le ticket suivant à **300 px**, marqueur
    retiré de l'adresse ;
  - ordinateur, suppression groupée de 2 tickets situés au-dessus du ticket repère (à 4 px) → après rechargement,
    repère toujours à **4 px** (même essai avec 2 tickets en dessous : 4 px) ;
  - téléphone, « Supprimer » dans « Actions du ticket » → retour : le ticket suivant à **300 px** ;
  - téléphone, ticket dans la corbeille : le menu contient « Supprimer pour toujours » (absent avant).

## 16. Vider la corbeille (1.5.0)

- PHPUnit : **74 tests, 1056 assertions, 0 échec** (nouveau `TrashTest`, 6 tests : l'administrateur vide tout sans
  toucher aux tickets publiés, brouillons et indésirables ; sans permission → 403 et pas de bouton ; utilisateur
  autorisé → seulement ses boîtes ; « seulement assignés » → seulement ses tickets ; vidage automatique : rien à
  0 jour, à 30 jours le ticket de 40 jours est supprimé et celui de 2 jours gardé ; tâche quotidienne enregistrée ;
  réglage enregistré par FreeScout et borné à 0…3650). `refreshglobal:check` : RG-CORE-13 et RG-DB-06 OK.
- Navigateur : 2 tickets mis à la corbeille, clic sur « Vider la corbeille (2) » dans les réglages → confirmation
  « Supprimer définitivement les 2 ticket(s)… » → « Corbeille vidée : 2 ticket(s) supprimé(s) définitivement » ;
  en base : 0 ticket dans la corbeille, les 38 autres intacts. Bouton aussi visible dans le panneau des vues.

## 15. État de la mise à jour : à jour / demandée / en cours (1.4.5)

- PHPUnit : **68 tests, 952 assertions, 0 échec** (nouveau `testUpdateStates` : après une mise à jour, « À jour »
  sans « dernière version » ni « branche main » ; nouvelle version ; demandée → boutons désactivés et rechargement
  automatique ; en cours → verrou tenu par un autre processus détecté, « Mise à jour vers X en cours… »).
- Scénario `auto-update` : **22 vérifications réussies, 0 en échec** (la mise à jour note et efface son état
  « en cours »).
- Navigateur : réglages « Version installée : 1.4.5 — À jour », « Dernière mise à jour : réussie (installée et
  vérifiée) » ; clic sur « Mettre à jour maintenant » → « Mise à jour demandée », demande enregistrée en base.

## 14. Sans release publiée : branche `main` (1.4.3)

- PHPUnit : **67 tests, 932 assertions, 0 échec** (`testCheckForUpdates` : release absente → `module.json` de la
  branche lu, source « branche main » indiquée ; ni release ni branche → message clair avec l'adresse).
- Scénario `auto-update` (`run_tests.sh`) : **22 vérifications réussies, 0 en échec**, dont trois nouvelles : sans
  release, `refreshglobal:update` installe la version de la branche (archive au format GitHub
  `RefreshGlobal-main/RefreshGlobal/`), page 200 ensuite ; `install.sh --update` sans release installe la branche.
- shellcheck : 0 remarque. Adresses réelles vérifiées : `raw.githubusercontent.com/…/main/RefreshGlobal/module.json`
  et `github.com/…/archive/refs/heads/main.zip` répondent 200.
