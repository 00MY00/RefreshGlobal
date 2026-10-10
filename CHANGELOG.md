# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) — versions : [SemVer](https://semver.org/lang/fr/).

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
