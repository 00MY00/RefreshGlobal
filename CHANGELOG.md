# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) — versions : [SemVer](https://semver.org/lang/fr/).

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
