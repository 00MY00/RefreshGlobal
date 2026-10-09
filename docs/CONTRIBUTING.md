# Contribuer

## Structure du dépôt

```
install.sh                 installeur (une commande)
RefreshGlobal/             le module, copié tel quel dans Modules/ de FreeScout
  Config/integration.php   liste des points d'intégration contrôlés (source unique)
  INTEGRATION_MAP.md       audit : chaque point avec sa référence fichier:ligne
  Services/                MailboxAccess (droits), GlobalTicketQuery (requête), Compatibility/ (contrôles)
  Tests/                   tests PHPUnit (exécutés dans un FreeScout)
docs/                      captures, schéma, ce guide
tests/installer/           banc de tests de install.sh (Docker)
tests/demo/seed_demo.php   données de démonstration pour un FreeScout jetable
tools/build-release.sh     archive de publication + SHA256SUMS
```

## Règles

1. Ne jamais modifier, copier ou remplacer un fichier de FreeScout ou de Refresh.
2. Ne jamais utiliser un hook, une classe, une méthode, une route, une vue ou une classe CSS sans l'avoir trouvé dans
   le code : l'ajouter à `INTEGRATION_MAP.md` (avec `fichier:ligne`) **et** à `Config/integration.php`.
3. Les droits passent uniquement par `Services/MailboxAccess.php` et `Services/GlobalTicketQuery.php`.
4. Code PHP compatible PHP 7.1 (pas de `fn`, `match`, propriétés typées, `??=` …).
5. Nouveaux textes : dans **toutes** les langues de `Resources/lang/` (le test `TranslationsTest` le vérifie).

## Tests

Tests du module (dans un FreeScout de test, jamais en production : ils écrivent dans la base, dans une transaction
annulée) — voir `RefreshGlobal/Tests/README.md` :

```bash
cd /var/www/html
sudo -u www-data php artisan config:clear
sudo -u www-data php phpunit.phar -c Modules/RefreshGlobal/Tests/phpunit.xml
sudo -u www-data php artisan freescout:clear-cache
```

Installeur (machine Linux avec Docker) :

```bash
bash tests/installer/run_tests.sh               # scénarios d'ajout, mise à jour, retour arrière…
bash tests/installer/run_tests.sh full          # installation complète sur Ubuntu vierge (long)
shellcheck install.sh
```

## Publier une version

1. Mettre à jour `RefreshGlobal/module.json` (`version`), `install.sh` (`SCRIPT_VERSION`), `CHANGELOG.md`.
2. `bash tools/build-release.sh` → `dist/RefreshGlobal.zip` et `dist/SHA256SUMS`.
3. Créer le tag `vX.Y.Z` ; la CI (`.github/workflows/ci.yml`) joint l'archive et les empreintes à la publication.
