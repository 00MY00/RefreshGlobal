# Journal des modifications (français)

Version anglaise (référence pour les notes de publication GitHub) : [CHANGELOG.md](CHANGELOG.md). Les deux fichiers
sont tenus à jour ensemble à chaque version.

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) — versions : [SemVer](https://semver.org/lang/fr/).

## [1.17.0] — non publiée (en attente de la validation du créateur de Refresh)

### Ajouté
- **RefreshGlobal fonctionne entièrement sans Refresh** (contrat §2.1) : le tableau de bord de toutes les boîtes, ses
  vues rapides (tuiles, filtre `rv` de « Toutes les boîtes »), ses chiffres, son graphique et ses objectifs SLA sont
  désormais calculés par RefreshGlobal lui-même (`Services/Dashboard/` : `TicketViews`, `Statistics`, `Chart`, `Sla`)
  au lieu d’appeler les classes de Refresh.
  - Avec Refresh : le tableau de bord remplace toujours « Mon tableau de bord » de Refresh (première boîte seulement),
    même apparence, mêmes chiffres.
  - Sans Refresh : le tableau de bord s’affiche au-dessus du tableau de bord de FreeScout, avec sa propre feuille de
    style (`Public/css/refreshglobal-dashboard.css`, ordinateur et téléphone) ; le réglage n’est plus grisé.
  - Objectifs SLA : ceux des réglages de Refresh quand Refresh est actif, sinon les nouveaux réglages **Objectif de
    première réponse** / **Objectif de résolution** (Gérer › Paramètres › RefreshGlobal, 24 h / 72 h par défaut, comme
    Refresh).
  - Nouvelles vues rapides `new` et `undelivered` ; la carte « E-mails non remis » mène à « Toutes les boîtes » au lieu
    de la vue de Refresh boîte par boîte.
- **API de recherche** (contrat §20, protocole 1.1) : fournisseur de recherche `refreshglobal.global-search` pour le
  contexte `global-mailboxes` — point d’accès `GET /refresh-global/search?q=&limit=&mb[]=` (JSON, lecture seule, droits
  de l’utilisateur, schéma `search-results` 1.0), registre du navigateur `FreeScoutInterop.search` (`registerProvider`,
  `forContext`, `run`), filtres serveur `fsinterop.search.providers` et `fsinterop.search.results`.
- **Contextes serveur** (contrat, phase 4) : filtre `fsinterop.navigation.context` — quel module affiche quelle
  surface sur la page en cours (propriété selon le contexte, §11 ; RefreshGlobal en déduit ses surfaces dans le
  navigateur au lieu de les deviner d’après le HTML) ; filtre `fsinterop.conversation.context` — ce que chaque module
  sait d’un ticket pour un utilisateur qui peut le voir (RefreshGlobal : lien « Toutes les boîtes », tags d’origine,
  actions utilisables).
- **Politique d’obsolescence** (§42) : une capacité, un événement ou un crochet serveur peut être déclaré
  `"status": "deprecated"` avec `replacement` et `remove_in_protocol` (une version majeure suivante) ; vérifié par le
  validateur et par `Manifest::deprecations()`.
- Les routes publiques sont déclarées dans `interop.json` (`provides.routes`) et vérifiées dans `Http/routes.php`.
- Contrôles d’intégration RG-CORE-23 (statuts d’envoi), RG-CORE-24 (`ConversationPolicy::view`), RG-DB-15 (colonnes
  de `threads`), avec leurs textes en 7 langues.
- **Contrat d’interopérabilité FreeScout Interop, protocole 1.1.0** ([docs/INTEROPERABILITY.md](docs/INTEROPERABILITY.md)) :
  Refresh et RefreshGlobal restent deux modules indépendants, au même niveau, et peuvent coopérer par un contrat
  petit, versionné et déclaré, au lieu que RefreshGlobal modifie la page de Refresh.
  - `RefreshGlobal/interop.json` : la référence unique de ce que RefreshGlobal fournit (capacités, événements,
    surfaces, intentions de navigation / barre du ticket / glissement, origine d’un ticket, schémas, crochets serveur,
    commandes) et utilise d’un Refresh facultatif, ses plages de compatibilité et ses informations pour UpdateGuard ;
    schémas JSON de chaque échange dans `RefreshGlobal/docs/interop/schemas/`.
  - `Public/js/fs-interop.js` : le pont neutre du navigateur `window.FreeScoutInterop` (enregistrement, découverte
    des capacités par plages de versions, bus d’événements, contexte d’un ticket, surfaces, registres) — chargeable
    plusieurs fois, dans n’importe quel ordre, sans dépendance ; Refresh peut livrer le même fichier.
  - `Public/js/interop-adapter.js` : RefreshGlobal s’enregistre, déclare ses intentions (entrée « Toutes les boîtes »,
    actions « Déplacer vers un dossier » et « Supprimer définitivement » de la barre du ticket, glissement à gauche
    « Déplacer ») et transmet l’origine des tickets ouverts depuis sa liste.
  - Crochets serveur `fsinterop.modules`, `fsinterop.dashboard.context` (un autre module peut seulement réduire les
    boîtes du tableau de bord) et `fsinterop.dashboard.data` (chiffres de la portée `global-mailboxes`, schéma 1.1).
  - Page de diagnostic : section **Interopérabilité** (version de Refresh, protocole, mode, compatibilité, état de
    chaque capacité, replis legacy).
  - Guide pour le créateur de Refresh : [docs/interop/REFRESH_MAINTAINER_GUIDE.md](docs/interop/REFRESH_MAINTAINER_GUIDE.md),
    manifeste proposé pour Refresh `docs/interop/examples/refresh.interop.json`.

### Modifié
- **Modes de coopération avec Refresh** : *natif* quand Refresh déclare un protocole compatible (RefreshGlobal ne
  modifie plus la page de Refresh pour les capacités qu’il fournit), *legacy* avec le Refresh actuel (les replis DOM
  des versions précédentes, inchangés pour l’utilisateur), *autonome* sans Refresh. Compatibilité indiquée en
  COMPATIBLE / DEGRADED / UNKNOWN / INCOMPATIBLE.
- `refreshglobal:health --json` suit le schéma de santé 1.0 du contrat : `"schema": "1.0"`, état `healthy` /
  `unhealthy` / `error`, contrôles `pass` / `warn` / `fail`, nouveau contrôle `interop_manifest`, résumé `interop` ;
  code de sortie 2 si le contrôle lui-même échoue. UpdateGuard ne lit que le code de sortie (0 = sain) : rien ne
  change pour lui.
- Les releases publient `interop.json` à côté de `RefreshGlobal.zip`, listé dans `SHA256SUMS`.
- La pastille d’une vue rapide sur « Toutes les boîtes » s’appelle « Vue rapide » (au lieu de « Vue Refresh »), dans
  les 7 langues.
- Contrôles d’intégration : RG-CORE-09 (`Views::query`, `Views::labels`) et RG-CORE-10 (`Dashboard::chart / delta /
  duration`) retirés (plus utilisés) ; RG-CORE-11 couvre maintenant `Settings::firstResponseHours / resolutionHours`,
  en gravité *avertissement* (sans eux, les objectifs SLA du module s’appliquent).

### Compatibilité
- Protocole **1.1.0** = protocole 1.0 + fournisseurs de recherche, ajoutés sans rien casser : un module qui parle 1.0
  reste compatible (`>=1.0.0 <2.0.0`) ; si une copie plus ancienne du pont est chargée en premier, celle de
  RefreshGlobal ajoute seulement le registre `search`, sur le bus d’événements existant.
- Avec le Refresh actuel (1.4.x – 1.6.x, sans contrat) : mode **legacy**, même comportement qu’en 1.16.2.
- Nouveau job CI **interop-contract** : `tools/validate-interop.php` (manifeste valide et cohérent avec le code,
  `module.json` et `Config/integration.php`) et `tests/interop/fs-interop.test.js`.

## [1.16.2] — 2026-10-20

### Corrigé
- **Textes non traduits du tableau de bord et de la liste.** Le tableau de bord de toutes les boîtes (indicateurs,
  graphique, carte « Tickets non résolus » avec ses colonnes Ouverts / En attente, « E-mails non livrés », tuiles) et
  l’entrée « Aperçus au survol » reprenaient les traductions de Refresh ou de FreeScout, qui n’existent qu’en
  français et en allemand : en espagnol, italien, néerlandais et portugais (Brésil), ils s’affichaient en anglais
  (« Open » n’était même traduit qu’en français). Ils utilisent maintenant les textes du module, dans ses 7 langues
  (mêmes mots que Refresh en français et en allemand), et ne dépendent plus des fichiers de langue de Refresh.

### Documentation
- Le journal anglais (`CHANGELOG.md`) couvre maintenant toutes les versions (1.0.0 à 1.9.2 traduites aussi).

## [1.16.1] — 2026-10-20

### Documentation
- **Journal des modifications en anglais** : `CHANGELOG.md` est désormais en anglais (versions 1.10.0 à 1.16.1
  traduites), ce fichier `CHANGELOG.fr.md` garde la version française complète. Les notes des releases GitHub sont
  en anglais (section de `CHANGELOG.md`, avec un lien vers la version française).
- `AGENTS.md` : consignes pour les agents de programmation (mettre à jour les deux journaux à chaque version, règles
  du projet).
- L’archive de la release contient les deux journaux ; `tools/build-release.sh` refuse une version absente de l’un
  des deux.
- Aucun changement de code ni de traduction de l’interface.

## [1.16.0] — 2026-10-19

### Ajouté
- **Déplacer un ticket vers un dossier du serveur mail.** Les e-mails du ticket vont dans un dossier existant de leur
  boîte sur le serveur (IMAP) ; le ticket lui-même ne change pas (statut, boîte, agent) et ses tags d'origine suivent
  le dossier (`inbox` → `folder:factures`…). Un message propose **« Annuler »** pendant 8 secondes.
  - bouton **« Déplacer »** sur la page d'un ticket (à côté de « Fermer » avec Refresh, dans la barre de FreeScout sans
    Refresh), entrée « Déplacer vers un dossier » du menu « Actions du ticket » sur téléphone ;
  - bouton dossier dans la **barre de sélection** des listes (plusieurs tickets à la fois) ;
  - **glisser un ticket vers la gauche** sur écran tactile (téléphone, tablette) ouvre le choix du dossier (le
    glissement vers la droite reste celui de Refresh : Supprimer / Prendre / Spam) ;
  - fenêtre de choix : dossiers de la boîte (sous-dossiers d'INBOX sans « INBOX »), recherche, dossier actuel indiqué ;
  - droits : ceux de FreeScout (« modifier le ticket ») ; seulement les boîtes en IMAP ; le dossier doit exister dans
    la boîte ; l'annulation ne remet un e-mail que dans un dossier de sa boîte ;
  - réglage « Déplacer vers un dossier » (activé par défaut) ; pour les autres modules, action
    `refreshglobal.mail_moved` (`docs/api-tags.md`).
- **Images distantes des e-mails** (pixels de suivi) : réglage par défaut **Toujours afficher**, **Restrictif**
  (non chargées, bouton « Afficher les images » par message, par défaut) ou **Bloqué** (jamais chargées, adresses
  retirées de la page) ; **règles par tag d'origine** « tag = mode » (préfixes avec `*`, ex. `folder:clients-* = show`),
  la plus restrictive l'emporte (par défaut : `classified_spam = block`, `spam = block`). Les images jointes à l'e-mail
  et celles de ce FreeScout s'affichent toujours. Filtre `thread.body_output` de FreeScout.
- Contrôles RG-HOOK-38…41, RG-CORE-21, RG-CORE-22.

## [1.15.1] — 2026-10-19

### Corrigé
- **Téléphone : titre d'un ticket écrit une lettre par ligne.** Sur la page d'un ticket classé dans un dossier
  automatique (badge « [WP] … » de Workflow Packs) ou portant des tags d'e-mail, Refresh met le sujet et les badges
  sur une ligne qui ne passe jamais à la ligne ; un badge long réduisait le sujet à 0 px. Le sujet garde maintenant
  la largeur, les badges passent dessous (souvent vu après « Fermer », qui ouvre le ticket suivant).
- **Styles du module sur téléphone perdus hors de ses pages** (ticket, tableau de bord) : FreeScout regroupe les
  feuilles de style et son minificateur ne comprend pas une règle `@container` dans un `@media` ; toute la suite de
  la feuille du module se retrouvait dans `@media (min-width: 768px)` (par ex. les tags d'e-mail sans style sur
  téléphone). Les règles `@container` de la liste sont dans `Public/css/refreshglobal-layout.css`, chargée par la page
  « Toutes les boîtes » seulement ; un test empêche d'en remettre dans la feuille regroupée.
- **Avertissement du navigateur « Incorrect use of <label for=FORM_ELEMENT> »** : il venait des formulaires de
  FreeScout lui-même (« After Sending » vers un champ caché, « To » vers un champ pas toujours présent :
  `editor_bottom_toolbar.blade.php:34,63`, `conversations/view.blade.php:267`, `create.blade.php:153`) ; le script du
  module retire ce `for` invalide (le texte reste), sans toucher aux fichiers de FreeScout.

### Information
- La requête vers un domaine externe vue dans le navigateur à l'ouverture d'un ticket (« …/wf/open?upn=… ») ne vient
  ni de Refresh ni du module : c'est un **pixel de suivi** contenu dans l'e-mail reçu, chargé avec ses images.

## [1.15.0] — 2026-10-19

### Ajouté
- **Vues enregistrées partagées.** Une vue (filtres + tri de « Toutes les boîtes ») peut être :
  - **privée** (son auteur seulement), **partagée avec des utilisateurs choisis**, ou **pour tous** (tous ceux qui ont
    accès à « Toutes les boîtes ») ; tous les agents peuvent publier pour tous, ou seulement les administrateurs
    (réglage « Seuls les administrateurs publient des vues pour tous », désactivé par défaut) ;
  - dans le panneau des vues : sections **Vues épinglées**, **Mes vues**, **Partagées avec moi**, **Pour tous** ;
  - **ouverte puis modifiée** : la vue reste ouverte, marquée « * Modifications non enregistrées », avec
    **Mettre à jour la vue** (seulement avec le droit de modifier, confirmation pour une vue partagée),
    **Enregistrer comme nouvelle** et **Rétablir** ; une vue partagée n'est jamais modifiée sans cette action ;
  - **Copier dans mes vues** (copie privée « Nom (copie) »), sauf si l'auteur l'interdit ;
  - **épingler** et **ma vue par défaut** : préférences personnelles, la vue elle-même ne change pas ;
  - un administrateur peut la rendre **vue par défaut** pour tous (vue pour tous) ou pour les utilisateurs avec qui
    elle est partagée ; le choix personnel de chacun passe avant ; il peut aussi laisser des utilisateurs choisis la
    **modifier** (filtres et nom, pas le partage) ;
  - **ordre** des vues (↑ ↓) : chacun pour les siennes, les administrateurs pour les vues partagées ;
  - **page « Vues enregistrées »** (`/refresh-global/views`, lien « Gérer les vues ») : nom, propriétaire, visibilité,
    partagée avec, dates, actions (ouvrir, modifier / partager, copier, épingler, par défaut, ordre, exporter,
    supprimer) ; **import / export JSON** (boîtes par adresse e-mail, aucun identifiant d'utilisateur exporté) ;
    journal des changements des vues partagées pour les administrateurs ; tableau sur ordinateur, cartes sur téléphone.
- **« Moi » dynamique** : « Assigné à : moi » désigne toujours la personne qui regarde la liste, jamais l'auteur de
  la vue (une vue « Mes tickets ouverts » pour tous montre à chacun les siens).
- **Une vue ne donne jamais accès à une boîte** : chaque personne ne voit que les tickets de ses propres boîtes (et
  seulement les siens avec le droit « ne voir que les tickets assignés »), aussi dans l'export et l'actualisation
  automatique ; une vue qui désigne une boîte supprimée, une boîte qui n'est pas la vôtre, un agent supprimé, un
  dossier automatique ou un tag d'e-mail qui n'existe plus l'indique au-dessus de la liste et l'ignore ; une
  définition illisible affiche la liste complète avec un message.
- Format des vues versionné (`schema_version` 2 : `filters`, `sorting`, `display`) ; les vues des versions
  précédentes sont lues et converties automatiquement ; tables `refreshglobal_view_users` (partage),
  `refreshglobal_view_prefs` (épinglée, vue par défaut de chacun, reprise des vues par défaut existantes),
  `refreshglobal_view_audit` (création, modification, partage, suppression d'une vue partagée). Liste des vues
  visibles gardée 1 minute par utilisateur, vidée à chaque changement ; 3 requêtes quel que soit le nombre de vues.
- Contrôles RG-DB-12…14 (tables), RG-DB-05 (nouvelles colonnes), RG-CORE-20 (méthodes `App\User` utilisées).

### Non inclus
- **Partage avec une équipe** : FreeScout n'a pas d'équipe ; les équipes du module payant « Teams » sont des
  utilisateurs de type « robot » dont RefreshGlobal ne peut pas lire les membres sans le code de ce module. Le modèle
  de données le prévoit (`SavedView::SHARED_TEAM`) ; à ajouter si le code du module Teams est fourni.

## [1.14.0] — 2026-10-19

### Compatibilité
- **Refresh 1.6.0** (version actuelle) : testé avec Refresh 1.6.0 ; plage acceptée par `refreshglobal:check`
  `>= 1.4.0` et `< 1.7.0` (avant : `< 1.5.0`, d'où l'avertissement RG-REF-02 avec un Refresh récent). Tous les
  éléments de Refresh utilisés par le module ont été revérifiés dans le code de Refresh 1.6.0 et leurs numéros de
  ligne mis à jour (`INTEGRATION_MAP.md`). Testé : 124 tests PHPUnit, affichage à 5 largeurs d'écran, retour à la
  liste, sélection par appui long, adresse d'envoi (ordinateur et téléphone), tableau de bord.

### Ajouté
- **Tableau de bord global : tickets non résolus par agent en deux colonnes, « Ouverts » et « En attente »**, comme
  la carte de Refresh depuis Refresh 1.5.0 ; agents triés par le total (ouverts + en attente), puis par nom ; la ligne
  « Non assigné » est découpée de la même façon ; chaque nombre mène à « Toutes les boîtes » filtrée sur l'agent et
  le statut. Une seule requête groupée (agent × statut), avec les mêmes droits qu'avant (boîtes permises, droit
  « seulement ses tickets », tickets publiés, sans spam). Sur téléphone, chaque nombre a son libellé. Les données du
  tableau de bord gardées en cache par une version précédente sont ignorées (nouvelle clé), sans action à faire.
- **Aperçus au survol de Refresh 1.6.0** : l'entrée « Aperçus au survol » (activer / désactiver, par agent) est aussi
  dans le menu Affichage de « Toutes les boîtes » ; les aperçus (premier message du ticket, fiche du contact)
  fonctionnent sur cette liste. Contrôles RG-CORE-19 et RG-HOOK-37, ignorés avec un Refresh plus ancien (nouvelle
  option `refresh_min` des contrôles).

## [1.13.0] — 2026-10-15

### Corrigé
- **Le retour à « Toutes les boîtes » ne disparaît plus après le traitement d'un ticket.** Un ticket ouvert depuis
  « Toutes les boîtes » puis fermé (bouton « Fermer » de Refresh, statut « Fermé ») : FreeScout ouvre le ticket actif
  suivant de la boîte, et le fil d'Ariane / la flèche retour du téléphone y ramenaient bien à la liste ; mais quand il
  n'y avait plus de ticket actif dans la boîte, FreeScout affichait la liste de cette boîte (vue Refresh) et le retour
  à « Toutes les boîtes » était perdu pour les tickets suivants. Désormais, quand FreeScout quitte le ticket pour la
  liste d'une boîte après une action (fermeture du dernier ticket actif, réglage « Après l'envoi : dossier »,
  assignation à un autre agent, déplacement…), on revient à la liste « Toutes les boîtes » d'où le ticket a été ouvert
  (mêmes filtres, à la place du ticket). Le passage au ticket suivant est inchangé.
- Contrôle RG-HOOK-36 (réponse `redirect_url` des actions de FreeScout sur un ticket).

## [1.12.0] — 2026-10-14

Nouvelle publication sous un **nouveau numéro de version** : des changements avaient été publiés sans que la release
soit renommée. Le système de mise à jour (Gérer › Modules de FreeScout, UpdateGuard) ne propose une mise à jour que
si le numéro de version change ; une installation déjà en 1.11.0 n'aurait donc pas reçu ces changements. Désormais,
**chaque changement publié reçoit un nouveau numéro de version**.

### Changements apportés (depuis 1.10.0)
- **Spams laissés sur le serveur mail** (réglage « Ne pas importer les spams », activé par défaut) ; désactivé, les
  spams sont importés avec le tag `classified_spam`.
- **Tags de provenance des e-mails** en anglais (`inbox`, `spam`, `trash`, `sent`, `drafts`, `archive`,
  `folder:<nom>`, noms de l'administrateur) : badges sur les tickets, section « Tags d'e-mail » du panneau, filtre de
  la liste, réglage d'affichage, bouton « Recalculer les tags » (commande `refreshglobal:mail-tags`), API pour les
  autres modules (`docs/api-tags.md`) et copie dans le module Tags s'il est installé.
- **Détails / texte brut** de chaque message : bouton « i » (outils de Refresh) ou menu du message ; en-têtes tels que
  reçus, message en texte brut, bouton Copier, lien vers la source originale de FreeScout.
- **Repère « ← Toutes les boîtes (N) »** en haut du panneau sur toutes les vues Refresh d'une boîte et sur chaque
  ticket (aussi dans le tiroir du téléphone) : on ne reste plus « enfermé » dans une boîte.
- Feuille de style du module chargée sur toutes les pages (tickets, vues Refresh).
- Page de la release GitHub : elle reprend désormais la section du CHANGELOG de la version.

Détail complet : section 1.11.0 ci-dessous.

## [1.11.0] — 2026-10-14

### Ajouté
- **Spams laissés sur le serveur mail** (réglage « Ne pas importer les spams », **activé par défaut**) : à la relève,
  un e-mail que le serveur mail a marqué comme spam (`X-Spam-Flag: YES`, `X-Spam-Status: Yes`, `X-Spam: yes`, SCL
  Exchange ≥ 5) ou relevé dans un dossier de spams (Spam, Junk, Courrier indésirable…) ne devient pas un ticket ; il
  reste dans la boîte sur le serveur (marqué lu par FreeScout). Désactivé : il est importé avec le tag
  `classified_spam`.
- **Tags de provenance des e-mails** (réglage, activé par défaut) : chaque e-mail relevé reçoit le tag du dossier du
  serveur d'où il vient, **en anglais** quelle que soit la langue du serveur (`inbox`, `spam`, `trash`, `sent`,
  `drafts`, `archive`, `folder:<nom>` pour les autres ; noms supplémentaires « Nom du dossier = tag » dans les
  réglages). Un tag par e-mail, regroupés sur le ticket :
  - affichés (réglage « Afficher les tags ») sur les tickets de « Toutes les boîtes », sous l'objet du ticket, et dans
    une section **« Tags d'e-mail »** du panneau des vues avec le nombre de tickets ; **filtre** de la liste (`mt`,
    combinable, enregistrable dans une vue) ;
  - **« Recalculer les tags »** (réglages) : les e-mails déjà dans FreeScout sont tagués en lisant chaque dossier des
    boîtes IMAP (en arrière-plan, commande `refreshglobal:mail-tags`) ;
  - **pour les autres modules** (Workflow Packs…) : filtres `refreshglobal.mail_tags` / `refreshglobal.thread_mail_tags`,
    action `refreshglobal.mail_tagged`, table `refreshglobal_mail_tags` (`docs/api-tags.md`) ; copiés aussi dans le
    module officiel **Tags** quand il est installé (règles Workflows, recherche de FreeScout).
- **Détails / texte brut d'un message** : bouton « i » sur chaque message du ticket (outils de Refresh ; menu du
  message sans Refresh) : expéditeur, destinataires, date, Message-ID, boîte, dossier du serveur et tags ; onglet
  **« Texte brut »** (en-têtes de l'e-mail tels que reçus + message en texte brut, bouton Copier) ; lien vers la
  source originale de FreeScout (relue sur le serveur mail). Refresh masquait la fenêtre « Original » de FreeScout.
- **Repère « ← Toutes les boîtes (N) »** en haut du panneau des vues sur **toutes** les vues Refresh d'une boîte
  (`/mailbox/N/tickets/…`) et sur chaque ticket, aussi dans le tiroir du téléphone, avec « Boîte : nom » : on ne reste
  plus « enfermé » dans une boîte. Il ramène à la dernière liste « Toutes les boîtes » (sinon à la liste complète).
- La feuille de style du module est chargée sur toutes les pages (filtre `stylesheets` de FreeScout) : tags, fenêtre de
  détails et repère s'affichent aussi sur la page du ticket et les vues Refresh.
- Contrôles RG-HOOK-30…35, RG-CORE-18 (module Tags, ignoré s'il est absent), RG-DB-11.

## [1.10.0] — 2026-10-14

### Ajouté
- **Choix de l'adresse d'envoi** sur « Envoyer un e-mail » et « Nouveau ticket » (formulaire de création de FreeScout,
  refait par Refresh ; aussi sans Refresh et sur téléphone). FreeScout lie ce formulaire à une seule boîte et
  n'affiche un champ « De » que si cette boîte a des alias avec « réponse depuis les alias » activé. Désormais :
  - champ **« De »** (e-mail) avec **toutes les adresses autorisées** : l'adresse de chaque boîte de l'utilisateur
    (sauf archivées) et les **alias** de ces boîtes, groupés par boîte ; champ **« Boîte »** pour un ticket (adresses
    des boîtes) ;
  - un alias de la même boîte s'applique tout de suite (envoyé comme `from_alias`, que FreeScout utilise même si
    « réponse depuis les alias » est désactivé) ; l'adresse d'une **autre boîte** ouvre le formulaire de cette boîte
    **en gardant ce qui est déjà saisi** (destinataires, objet, message, contact, type, priorité) ;
  - **adresse proposée** : la boîte sur laquelle « Toutes les boîtes » est filtrée, sinon la **dernière adresse
    utilisée** (ce navigateur), sinon la boîte du formulaire ; ouvert depuis une boîte ou un ticket, cette boîte est
    gardée ;
  - les réponses dans un ticket restent liées à la boîte du ticket (et à ses alias, fonction de FreeScout).
- **Sécurité** : FreeScout appliquait n'importe quel `from_alias` envoyé avec un message sans vérifier qu'il appartient à
  la boîte (une requête forgée pouvait envoyer depuis n'importe quelle adresse). Un alias qui n'est ni l'adresse ni un
  alias de la boîte du ticket est maintenant retiré avant l'envoi (`Http/Middleware/CheckFromAlias.php`).
- Contrôles RG-HOOK-28/29, RG-CORE-17, RG-ROUTE-11 (avertissement).

## [1.9.2] — 2026-10-14

### Corrigé
- **Téléphone : sélection par appui long, protection renforcée.** La 1.9.1 ignorait seulement le clic du relâchement.
  Un vrai téléphone peut aussi décocher le ticket autrement : l'appui long natif de FreeScout (`taphold`,
  `public/js/main.js` `conversationsTableInit()`) relancé par les évènements souris que certains téléphones envoient
  après un toucher (`mousedown` / `mouseup` / `click`). Désormais, dès qu'un appui long a coché un ticket, **tout autre
  changement de sa case** pendant le même appui, ou dans les 1,5 s qui suivent le relâchement (avant le toucher
  suivant), est annulé, quelle qu'en soit la source ; la carte garde sa couleur de sélection.

### Ajouté
- **Journal tactile** pour le dépannage : `…/refresh-global/tickets?rg_debug=touch` affiche en haut de la page ce que
  le téléphone envoie (touchers, menu contextuel, évènements souris, clics, changements de case, annulations) ;
  `?rg_debug=off` l'arrête (mémorisé pour l'onglet seulement).

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
