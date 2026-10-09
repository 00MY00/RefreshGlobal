# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) — versions : [SemVer](https://semver.org/lang/fr/).

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
