# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) — versions : [SemVer](https://semver.org/lang/fr/).

## [1.9.1] — 2026-10-14

### Corrigé
- **Téléphone : sélection par appui long** sur « Toutes les boîtes ». Un appui long sélectionnait le ticket puis le
  désélectionnait au relâchement, et le mode sélection se refermait : impossible d'agir sur un ou plusieurs tickets.
  Cause : le script téléphone de Refresh (`Modules/Refresh/Public/js/mobile.js:260-298`) coche le ticket après 500 ms,
  puis n'ignore le « clic » envoyé au relâchement que s'il arrive moins de 800 ms après ce moment ; un appui volontaire
  de plus de ~1,3 s voyait ce clic traité comme un toucher en mode sélection, qui décochait le ticket (même défaut sur
  la liste de Refresh). Désormais, quand la case du ticket a changé pendant que le doigt était posé, le clic qui suit
  est ignoré, quelle que soit la durée de l'appui ; les touchers courts gardent le comportement de Refresh (ouvrir le
  ticket, ou cocher / décocher en mode sélection). Plus de sélection de texte ni de menu du téléphone sur la carte
  pendant l'appui long.

## [1.9.0] — 2026-10-13

### Modifié
- **Mises à jour par FreeScout ou par [UpdateGuard](https://github.com/00MY00/UpdateGuard)** : RefreshGlobal n'a plus de
  système de mise à jour à lui. Son `module.json` déclare les clés standard de FreeScout (`latestVersionUrl` →
  `version.txt` de la dernière release, `latestVersionZipUrl` → `RefreshGlobal.zip`, `SHA256SUMS` à côté) :
  - **sans UpdateGuard** : le bouton « Mettre à jour » de **Gérer › Modules** apparaît quand une nouvelle version est
    publiée (comme pour les autres modules ; sans sauvegarde ni retour arrière) ; les réglages et la page de
    diagnostic **conseillent d'installer UpdateGuard** ;
  - **avec UpdateGuard** actif : il met RefreshGlobal à jour en sécurité (SHA-256, sauvegarde, contrôle de santé,
    retour arrière automatique, mises à jour automatiques si autorisées) ; les réglages et le diagnostic renvoient à
    sa page de RefreshGlobal.
- Retirés : la mise à jour automatique du module (case, « Rechercher une mise à jour », « Mettre à jour maintenant »,
  tâches planifiées, commande `refreshglobal:update`, réglages `REFRESHGLOBAL_UPDATE_URL` / `…_AUTO_UPDATE_TIME`) et
  le contrôle RG-CORE-08. Les deux options qu'elle laissait sont effacées par une migration ; ses anciennes
  sauvegardes (`storage/app/refreshglobal/backups/`) restent en place et peuvent être supprimées à la main.
  `install.sh --auto-update` n'a plus d'effet et conseille UpdateGuard.

### Ajouté
- Commande **`php artisan refreshglobal:health [--json]`** (`healthCommand` de `module.json`), lancée par UpdateGuard
  après une mise à jour : fichiers du module, contrôles de compatibilité groupés (classes, vues, routes, hooks,
  ressources, base de données) et affichage réel de la page pour un administrateur. Code 0 = sain.
- `module.json` : `"updateGuard": {"migrations": "backward-compatible"}` (les migrations du module ajoutent des tables
  ou des colonnes facultatives : la version précédente fonctionne avec) ; la release publie `version.txt`.
- Contrôles RG-ROUTE-09 (page Modules de FreeScout) et RG-ROUTE-10 (UpdateGuard, ignoré s'il est absent).

### Compatibilité
- Une installation en 1.8.x **reçoit encore cette version par son ancienne mise à jour automatique** (même release,
  même `SHA256SUMS`) : `refreshglobal:check` et `refreshglobal:selftest`, qu'elle lance après l'installation, sont
  conservés.

## [1.8.2] — 2026-10-12

### Ajouté
- **Retour à « Toutes les boîtes » depuis un ticket.** Le fil d'Ariane de Refresh (et la flèche retour du téléphone,
  qui le lit) menait à la dernière vue Refresh d'**une** boîte, ou à « Tous les tickets » de la boîte du ticket. Pour
  un ticket ouvert depuis « Toutes les boîtes », son premier élément devient « Toutes les boîtes » (ou le nom de la
  vue enregistrée) et ramène à la liste d'origine, **à l'endroit du ticket ouvert** (aussi après avoir répondu ou être
  passé au ticket suivant). Mémoire par onglet du navigateur ; ouvrir une autre liste (vue Refresh, boîte, recherche,
  tableau de bord) redonne le fil d'Ariane normal de Refresh. Contrôles RG-HOOK-25…27 (avertissement).
- **Repère au-dessus du panneau des vues sur la page du ticket** : « ← Toutes les boîtes (N) » et « Boîte : <nom> ».
  Ce panneau est celui de Refresh pour la boîte du ticket : ses nombres ne concernent que cette boîte (≈ 50 au lieu
  de ≈ 800), ce qui donnait l'impression d'être « enfermé » ailleurs.
- **Bandeau « Dossier automatique … — N ticket(s) »** au-dessus de la liste quand un dossier de Workflow Packs est
  choisi, toujours visible, avec **« Quitter le dossier »** (les autres filtres sont gardés).

### Modifié
- Le retour à la liste (après la suppression d'un ticket, fil d'Ariane du ticket) se fait **sans le dossier
  automatique** : on ne reste plus « bloqué » dans un dossier. Une vue enregistrée qui contient un dossier est
  remplacée par ses filtres sans le dossier. Le réglage « passer au ticket suivant » cherche toujours le suivant dans
  la liste affichée.

## [1.8.1] — 2026-10-12

### Corrigé
- **Bouton « Filtres » caché sous le panneau des filtres** sur les écrans moyens (portable, écran 1080p zoomé à
  125–150 %) : la barre d'outils débordait de la liste, le bouton passait sous le panneau (l'entonnoir sur
  « FILTRES ») et on ne pouvait plus replier le panneau. La barre d'outils ne dépasse plus jamais de la liste.

### Amélioré
- **Affichage selon la place réellement disponible** (requêtes de conteneur CSS : elles suivent aussi le panneau des
  vues replié ou non), page « Toutes les boîtes » seulement, ordinateur et tablette ; le téléphone garde la
  présentation de Refresh :
  - **panneau des filtres qui glisse** : quand la liste et le panneau ne tiennent pas côte à côte (liste sous
    ~760 px), le panneau ne prend plus de place à la liste ; il s'ouvre **par-dessus** depuis la droite (bouton
    « Filtres ») et se ferme avec ×, Échap ou un clic à côté. Sur un grand écran il reste à droite et se **replie
    sur le côté** avec × (choix retenu, comme le bouton « Filtres » de Refresh) ;
  - **barre « Filtres actifs »** au-dessus de la liste quand le panneau est replié ou fermé : une pastille par
    filtre (recherche, boîtes, statuts, assigné, vue Refresh, dossier automatique) avec × pour l'enlever,
    « Modifier » (ouvre le panneau) et « Effacer les filtres » ; le bouton « Filtres » est coloré quand un filtre est
    actif ;
  - **barre d'outils** compacte quand la liste est étroite (libellés « Trier par » masqués, « Exporter » en icône,
    puis compteur « 1 - 30 sur N » masqué) ;
  - **cartes plus lisibles** : la colonne de droite (priorité / agent / statut) se rétrécit avec la liste, le sujet
    garde la place (à 1280 px : ~460 px de sujet au lieu de ~200) ; l'adresse de la boîte se masque (nom seul,
    adresse dans l'info-bulle) quand la place manque ; vue **tableau** : colonnes client et priorité / agent /
    statut rétrécies (colonne « Ticket » de ~90 à ~320 px à 1280 px).
  - Téléphone : la pastille « boîte » de Refresh ne montre plus que le nom (l'adresse débordait de la carte).
  - L'actualisation automatique attend que le panneau ouvert par-dessus la liste soit fermé.

## [1.8.0] — 2026-10-12

### Ajouté
- **Dossiers automatiques de [Workflow Packs](https://github.com/00MY00/WorkflowPacks)** (seulement si Workflow Packs
  **1.14.0 ou plus récent** est installé et actif ; rien ne change sinon). Workflow Packs classe les e-mails dans
  des dossiers du serveur mail (« [WP] Factures › Fournisseurs ») et garde le dossier actuel de chaque ticket ;
  RefreshGlobal le lit (table `workflowpacks_ticket_folders`, lecture seule) pour garder une vue d'ensemble :
  - section **« Dossiers automatiques »** dans le panneau des vues (et le tiroir sur téléphone) : un arbre des
    dossiers de toutes vos boîtes, chacun avec le nombre de tickets de la liste affichée (sous-dossiers compris) ;
    un clic filtre la liste sur ce dossier. Le filtre (`wf` dans l'adresse) se combine avec les autres, s'enregistre
    dans une vue, suit l'export CSV et l'actualisation automatique ;
  - **badge dossier** sur chaque ticket de la liste (à côté de la boîte), aussi sur téléphone ;
  - page **« Vue d'ensemble »** (`/refresh-global/folders`) : par dossier, tickets, tickets ouverts, répartition par
    boîte, dernier classement et nettoyage automatique réglé dans Workflow Packs ; liens vers les règles et
    l'historique de Workflow Packs pour ceux qui y ont accès.
  - Mêmes droits que la liste : chacun ne voit que les dossiers et les tickets de ses boîtes (et seulement ses
    tickets avec « ne voir que les conversations assignées »). Un ticket dont l'e-mail a été retiré par le nettoyage
    automatique de Workflow Packs n'est plus compté dans le dossier.
- Contrôles RG-DB-09/10, RG-ROUTE-07/08 (ignorés sans Workflow Packs) et RG-CORE-16.

### Corrigé
- **Suppression définitive et dossiers de Workflow Packs** : l'e-mail d'un ticket que Workflow Packs avait déplacé
  dans un de ses dossiers (« INBOX.[WP] Factures ») n'était pas trouvé dans les dossiers relevés par FreeScout, donc
  pas déplacé dans la corbeille du serveur. Le dossier est maintenant noté au moment de la suppression (nouvelle
  colonne `imap_path` de la file, migration du module) et l'e-mail y est cherché aussi.

## [1.7.1] — 2026-10-11

### Ajouté
- **Animation de chargement** pendant une action, comme dans le module Workflow Packs : le bouton cliqué affiche
  l'icône tournante de FreeScout (`glyphicon-spin`) et les boutons sont désactivés pendant le traitement (pas de
  double envoi). Pour toutes les actions du module : filtres, vues enregistrées, « Vider la corbeille », langue,
  réglages (Enregistrer, Rechercher une mise à jour, Mettre à jour maintenant), diagnostic (Relancer les contrôles).
  Rien ne tourne si une confirmation est annulée ; tout est remis en état avec le bouton « Retour » du navigateur.

## [1.7.0] — 2026-10-10

### Ajouté
- Bouton **« Relever les e-mails »** (flèche en cercle) sur la page « Toutes les boîtes » (barre d'outils ; tiroir
  sur téléphone), **seulement si le module [SyncNow](https://github.com/rabsym/freescout-syncnow) est installé et
  actif** et que l'utilisateur peut s'en servir (administrateur, ou permission « Force sync » de SyncNow). Il relève
  tout de suite les boîtes IMAP de la liste affichée, une par une, avec les appels de SyncNow (`syncnow.force` puis
  `syncnow.status` jusqu'à la fin : historique et verrou de SyncNow gérés normalement) ; l'icône tourne pendant la
  relève, la liste se recharge s'il y a de nouveaux e-mails, sinon un message (« Aucun nouvel e-mail », délai de
  SyncNow avant une nouvelle relève…). Contrôles RG-ROUTE-05/06 (ignorés sans SyncNow).

## [1.6.0] — 2026-10-10

### Ajouté
- **Actualisation automatique** de la liste « Toutes les boîtes » et du tableau de bord de toutes les boîtes : toutes
  les N secondes (réglage, 30 par défaut, 0 = désactivée), la page demande au module l'« empreinte » de ce qu'elle
  affiche (`GET /refresh-global/state`, mêmes filtres et mêmes droits ; une requête d'agrégat) et se recharge
  d'elle-même quand un ticket a été ajouté, modifié ou supprimé — jamais pendant que l'utilisateur coche des tickets,
  tape du texte, ou a un menu / une fenêtre / un panneau du téléphone ouvert. « Rester à ma place dans la liste »
  remet la liste au même endroit. Le cache d'une minute du tableau de bord tient compte de cette empreinte : les
  chiffres affichés après une actualisation sont toujours à jour.

## [1.5.3] — 2026-10-10

### Corrigé
- CI GitHub : la tâche shellcheck (dans « lint (8.2) ») échouait depuis la première version sur une construction
  `A && B || C` de `install.sh` (SC2015, `--rollback`), réécrite en `if` (même comportement). Comme le job
  `release` dépend de `lint`, **aucune release GitHub n'avait jamais été créée** ; elles le sont maintenant à chaque
  tag (archive, `install.sh`, `module.json`, `SHA256SUMS`).
- CI : `actions/checkout@v5` (Node.js 24) à la place de v4 (avertissement « Node.js 20 is deprecated »).

## [1.5.2] — 2026-10-10

### Corrigé
- **Suppression définitive : les e-mails restaient sur le serveur mail.** FreeScout ne fait que copier les e-mails :
  il ne les efface jamais du serveur. Désormais, quand un ticket est supprimé définitivement par un utilisateur
  (« Supprimer définitivement », suppression groupée de tickets déjà à la corbeille, « Vider la corbeille » de
  FreeScout ou du module, vidage automatique), ses e-mails sont **déplacés dans la corbeille du serveur mail**
  (IMAP), dans la minute : ils disparaissent de la boîte de réception mais restent récupérables depuis le webmail.
  - Les e-mails sont notés au moment de la suppression (action `conversations.before_delete_forever` de
    FreeScout), puis déplacés par la tâche `refreshglobal:mail-trash` (chaque minute) avec la connexion IMAP de
    FreeScout : la suppression elle-même n'attend jamais le serveur mail ; en cas d'erreur, nouvel essai (5 fois).
  - E-mails des clients cherchés dans les dossiers relevés par FreeScout ; réponses des agents dans le dossier
    « Envoyés » de la boîte s'il est configuré.
  - Corbeille du serveur trouvée automatiquement (Trash, Corbeille, Deleted Items, [Gmail]/Corbeille…) ou indiquée
    dans les réglages. Boîtes POP3 : impossible (ignorées).
  - Jamais pour les autres suppressions de FreeScout (suppression d'une boîte entière, e-mail non importable).
  - Réglage « Aussi sur le serveur mail » (activé par défaut) et état de la file dans Gérer › Paramètres ›
    RefreshGlobal › Corbeille. Contrôles RG-HOOK-24, RG-CORE-14, RG-CORE-15, RG-DB-07, RG-DB-08.

## [1.5.1] — 2026-10-10

### Ajouté
- Réglage **« Rester à ma place dans la liste »** (activé par défaut) : après une suppression, la liste « Toutes les
  boîtes » s'ouvre là où l'on était, le ticket suivant à la place de celui supprimé, au lieu de revenir en haut.
  Fonctionne pour la suppression depuis le ticket (ordinateur et téléphone) et pour la suppression groupée ou par
  glissement depuis la liste (rechargement de la page), y compris quand des tickets au-dessus disparaissent : la
  position est recalée sur un ticket repère, pas sur un nombre de pixels.
- Téléphone : **« Supprimer définitivement »** dans les « Actions du ticket » de Refresh pour un ticket de la
  corbeille (Refresh ne proposait aucune suppression dans ce cas) ; l'entrée clique le bouton de FreeScout (sa
  confirmation et ses droits). Contrôle RG-HOOK-23.

## [1.5.0] — 2026-10-09

### Ajouté
- **Vider la corbeille** : bouton « Vider la corbeille (N) » dans Gérer › Paramètres › RefreshGlobal et en bas du
  panneau des vues de « Toutes les boîtes » (et du tiroir sur téléphone), avec confirmation. Les tickets de la
  corbeille sont supprimés définitivement avec leurs e-mails, par la fonction de FreeScout
  (`Conversation::deleteConversationsForever`), avec les mêmes règles que son « Vider la corbeille » par boîte :
  administrateurs ou utilisateurs autorisés à supprimer des conversations, seulement leurs boîtes, seulement leurs
  tickets s'ils ne voient que les tickets assignés.
- **Vidage automatique** : réglage « Vider automatiquement — N jours » (0 = jamais, par défaut). Chaque jour (03:45,
  `REFRESHGLOBAL_TRASH_AUTO_TIME`), les tickets qui sont dans la corbeille depuis plus de N jours sont supprimés
  définitivement, dans toutes les boîtes ; chaque ticket reste donc au moins N jours récupérable. Commande
  `php artisan refreshglobal:trash --older-than=N`.
- Le serveur de messagerie n'est jamais touché.
- Contrôles RG-CORE-13 (suppression définitive de FreeScout) et RG-DB-06 (date d'entrée dans la corbeille).

## [1.4.5] — 2026-10-09

### Modifié
- État de la mise à jour plus clair (réglages et diagnostic, même bloc) :
  - **à jour** (par exemple juste après une mise à jour réussie) : « Version installée : 1.4.5 — À jour », sans
    « dernière : … (branche main …) » qui faisait croire à une nouvelle version ;
  - **nouvelle version** : « nouvelle version : X » (avec la source si c'est la branche `main`) ;
  - **demandée** puis **en cours** (« Mise à jour vers X en cours… ») : boutons désactivés et page rechargée toute
    seule jusqu'à la fin. « En cours » vient du verrou réellement tenu par la mise à jour.
- « Dernière mise à jour : réussie (installée et vérifiée) » au lieu de « Dernière mise à jour automatique :
  installée » (la mise à jour peut venir du bouton).
- Boutons « Rechercher une mise à jour » et « Mettre à jour maintenant » côte à côte.

## [1.4.4] — 2026-10-09

### Modifié
- Gérer › Paramètres › RefreshGlobal : les liens deviennent de vrais boutons (« Ouvrir la page « Toutes les
  boîtes » », « Diagnostic RefreshGlobal », « Changer ma langue », « Langue par défaut (Général) »).
- Page de diagnostic : boutons « Relancer les contrôles », « Ouvrir la page « Toutes les boîtes » » et « Réglages ».
- Panneau des vues de « Toutes les boîtes » : « Diagnostic RefreshGlobal » en bouton.

### Corrigé
- Tests : le test du bouton « Rechercher une mise à jour » remet le fichier d'état des mises à jour comme avant.

## [1.4.3] — 2026-10-09

### Modifié
- **Sans release publiée, la version actuelle de la branche `main` est utilisée** : « Rechercher une mise à jour »,
  la mise à jour automatique / « Mettre à jour maintenant » et `install.sh` (sans `--version`) se rabattent sur
  `main` quand GitHub répond 404 pour la dernière release (dépôt avec des tags seulement). Il n'y a pas de fichier
  SHA256SUMS pour une branche : l'intégrité repose sur HTTPS ; l'empreinte de l'archive est notée dans le journal,
  et la vérification après installation avec retour arrière automatique reste en place. Une release publiée reste
  prioritaire. Adresses réglables (`REFRESHGLOBAL_UPDATE_BRANCH_MANIFEST`, `REFRESHGLOBAL_UPDATE_BRANCH_ZIP`,
  `RG_BRANCH_ZIP_URL` pour `install.sh`) ; valeur vide = pas de repli.

### Corrigé
- « Rechercher une mise à jour » : l'erreur HTTP 404 brute est remplacée par un message clair quand ni release ni
  branche `main` ne sont lisibles.

## [1.4.2] — 2026-10-09

### Ajouté
- **Sélecteur de langue** en bas du panneau des vues de « Toutes les boîtes » (et du tiroir sur téléphone). Il n'y a
  qu'un seul choix de langue, celui du profil FreeScout de l'utilisateur, que FreeScout, Refresh et le module suivent
  tous : le sélecteur l'enregistre comme la page Profil, donc toute l'interface change de langue ensemble. Langues
  proposées : celles de FreeScout ; le module est traduit en 7 langues (anglais pour les autres).
- Gérer › Paramètres › RefreshGlobal : langue actuelle, liens « Changer ma langue » et « Langue par défaut ».
- Contrôle RG-VIEW-06 (liste des langues de FreeScout).

## [1.4.1] — 2026-10-09

### Ajouté
- Bouton **« Rechercher une mise à jour »** (Gérer › Paramètres › RefreshGlobal et page de diagnostic) : lit tout
  de suite le `module.json` de la dernière version publiée (délai 15 s, rien n'est installé) et indique si une
  version plus récente existe, si elle demande un FreeScout plus récent, ou pourquoi la recherche a échoué. Date de
  la dernière recherche affichée.

## [1.4.0] — 2026-10-09

### Ajouté
- **« Mon tableau de bord » de Refresh sur toutes les boîtes** : Refresh calcule son tableau de bord pour la première
  boîte seulement ; le module remplace ce bloc (filtre `dashboard.before`) par le même tableau de bord, avec les mêmes
  classes et les fonctions de Refresh (`Views::query`, `Dashboard::chart/delta/duration`), pour toutes les boîtes de
  l'utilisateur et avec ses droits. Tuiles, agents et e-mails non livrés par boîte, liste des tickets non résolus
  avec la boîte. Réglage « Tableau de bord de toutes les boîtes » (activé par défaut).
- Filtre **vue Refresh** (`rv`) sur « Toutes les boîtes » (non résolus, en retard, échéance aujourd'hui, ouverts, en
  attente, non assignés, nouveaux), avec la définition de Refresh ; puce retirable dans les filtres.
- **Suppression d'un ticket** (middleware sur `conversations.ajax`) : retour à « Toutes les boîtes » avec les
  derniers filtres, ou ticket suivant de cette liste (réglage « Passer au ticket suivant ») ; réglage « Supprimer
  définitivement » (le ticket et ses e-mails sont effacés de FreeScout, aussi en suppression groupée ; le serveur de
  messagerie n'est pas touché). Par défaut : retour à la liste, corbeille.
- Réglage **« Boîte au-dessus de chaque ticket »** (activé par défaut) : nom **et adresse** de la boîte.
- Réglages présentés en interrupteurs, comme ceux de FreeScout.
- Contrôles RG-HOOK-18…22, RG-CORE-09…12, RG-ROUTE-04, avec leurs propres textes d'effet.

### Corrigé
- Français, Refresh sur téléphone : « Créé 7h il y a » / « Fermé 7h il y a » deviennent « Créé il y a 7h » /
  « Fermé il y a 7h » (correction appliquée au dictionnaire de Refresh dans la page, sans modifier Refresh, et
  seulement tant que Refresh garde l'ancienne tournure).

## [1.3.0] — 2026-10-09

### Ajouté
- Section **Gérer › Paramètres › RefreshGlobal** (comme Refresh) : option « remplacer l'entrée Tickets de Refresh », mise à jour automatique, versions installée / disponible, dernier résultat, liens vers la page et le diagnostic.
- Bouton **« Mettre à jour maintenant »** (paramètres et diagnostic) : la mise à jour sécurisée est lancée dans la minute par la tâche planifiée `refreshglobal:update --requested` (jamais pendant une requête web), même si la mise à jour quotidienne est désactivée.
- Contrôles RG-HOOK-15…17 (filtres `settings.sections`, `settings.section_settings`, `settings.view`).

## [1.2.1] — 2026-10-09

### Corrigé
- `refreshglobal:selftest` répondait HTTP 403 sur un serveur dont l'`APP_URL` n'est pas `localhost` (contrôle `TrustHosts` de FreeScout) : la mise à jour par `install.sh --update` (et la mise à jour automatique) était alors annulée à tort par le retour arrière automatique. L'auto-test utilise maintenant l'adresse complète d'`APP_URL`.

## [1.2.0] — 2026-10-09

### Ajouté
- Téléphone : onglet « Toutes les boîtes » dans la barre d'onglets du bas de Refresh (script `Public/js/shell.js`, chargé comme les scripts de Refresh).
- Option (page de diagnostic, administrateurs) : remplacer l'entrée « Tickets » de Refresh par « Toutes les boîtes » dans la barre de gauche et la barre d'onglets ; l'entrée de Refresh est masquée, pas supprimée. Valeur par défaut : `REFRESHGLOBAL_REPLACE_REFRESH_TICKETS`.
- Contrôles RG-HOOK-11…14 (filtres `javascripts` / `layout.head`, éléments `.rf-m-tab-tickets` et `fd-all-tickets` de Refresh).

## [1.1.0] — 2026-10-09

### Ajouté
- Mise à jour automatique quotidienne (désactivée par défaut) avec retour arrière automatique : empreinte SHA-256
  obligatoire, sauvegarde, installation, `refreshglobal:check` + `refreshglobal:selftest`, retour à la version
  précédente (fichiers, vues enregistrées, migrations) si la nouvelle ne fonctionne pas ; version annulée non
  retentée ; bandeau pour les administrateurs. Commande `php artisan refreshglobal:update` (`--check`, `--enable`,
  `--disable`, `--force`), réglage sur la page de diagnostic, option `install.sh --auto-update=on|off`.
- Commande `php artisan refreshglobal:selftest` : affiche la page pour un administrateur et la vérifie.
- `install.sh --update` revient automatiquement à la version précédente si la nouvelle est bloquante
  (`--no-auto-rollback`).
- Image du module pour Gérer › Modules (`Public/img/module.svg`).
- Contrôles RG-HOOK-10 (planificateur), RG-CORE-07 (`App\Option`), RG-CORE-08 (ZipArchive, Guzzle, Symfony Process).

## [1.0.1] — 2026-10-09

### Corrigé
- Téléphone : la version mobile de Refresh masque sa barre de gauche et sa barre d'outils ; la page « Toutes les boîtes » est maintenant accessible depuis le tiroir des vues (hook `mailbox.after_sidebar_buttons`, aussi dans la barre latérale native de FreeScout), et son tiroir affiche les titres de section et le lien « Exporter (CSV) ».
- Installation complète : consigne pour la question « All files … will be removed » du script officiel.

## [1.0.0] — 2026-10-09

Première version.

### Ajouté
- Page « Toutes les boîtes » (`/refresh-global/tickets`) : tickets de toutes les boîtes autorisées, liste native de
  FreeScout avec colonne « Boîte » ; ouverture d'un ticket sur sa page native.
- Filtres par boîte (multi-sélection), statut, assignation ; recherche sujet / client / e-mail / numéro ; tri ;
  filtres dans l'adresse.
- Compteurs par boîte et par statut (requêtes groupées).
- Export CSV en flux (UTF-8 avec BOM, plafond configurable, protection contre les formules).
- Vues enregistrées personnelles (créer, renommer, vue par défaut, supprimer) ; table `refreshglobal_saved_views`.
- Apparence identique à Refresh quand il est installé (structure et classes reprises, aucun fichier copié) ;
  entrée « Toutes les boîtes » dans le menu et dans la barre latérale de Refresh ; version téléphone.
- Contrôle de compatibilité (`php artisan refreshglobal:check`, page `/refresh-global/diagnostic`, contrôle mis en
  cache au chargement des pages) avec états OK / avertissement / dégradé / bloquant et messages `RG-xxx`.
- Traductions : français, anglais, allemand, espagnol, italien, néerlandais, portugais (Brésil).
- Installeur `install.sh` : ajout, installation complète (`--full`), `--update`, `--uninstall`, `--rollback`,
  `--dry-run`, `--yes` ; sauvegardes horodatées, journal sans mot de passe.
- Tests PHPUnit du module (accès, compatibilité, export, vues enregistrées, traductions) et banc de tests de
  l'installeur dans Docker.
