# Tests du module

Les tests s'exécutent **dans une installation FreeScout de test** (jamais en production) : ils créent des boîtes,
des utilisateurs et des tickets dans la base, à l'intérieur d'une transaction annulée à la fin de chaque test.

FreeScout n'embarque pas PHPUnit : utiliser l'archive PHAR (PHPUnit 11 pour PHP 8.2+).

```bash
cd /var/www/html                                   # FreeScout, avec Modules/RefreshGlobal (et Refresh si possible)
curl -sSL -o /tmp/phpunit.phar https://phar.phpunit.de/phpunit-11.phar
sudo -u www-data php artisan config:clear          # FreeScout met sa configuration en cache : APP_ENV=testing doit être lu
sudo -u www-data php /tmp/phpunit.phar -c Modules/RefreshGlobal/Tests/phpunit.xml
sudo -u www-data php artisan freescout:clear-cache # remet le cache de configuration
```

| Fichier | Ce qui est vérifié |
|---|---|
| `Feature/AccessTest.php` | un utilisateur n'obtient jamais un ticket, un compteur ou un lien d'une boîte non autorisée, même en forçant `mb[]` ; boîtes archivées ; permission « assignées uniquement » ; brouillons, supprimés, spam ; recherche ; pages réservées |
| `Feature/ExportTest.php` | BOM UTF-8, lignes autorisées seulement, filtres, plafond, formules neutralisées |
| `Feature/CompatibilityTest.php` | absence simulée de Refresh, d'un CSS, d'une vue, d'une méthode, d'une route, d'une colonne, d'un hook ; état, format du message (EN/FR), page bloquée, code de sortie de la commande, journalisation |
| `Feature/SavedViewsTest.php` | créer, charger, renommer, défaut, supprimer ; boîtes interdites non enregistrées ; boîte perdue ignorée avec mention ; vues personnelles ; validation |
| `Feature/DashboardTest.php` | tableau de bord de toutes les boîtes à la place de celui de Refresh (un seul bloc, droits respectés, option désactivée), filtre `rv`, correction des textes français de Refresh |
| `Feature/DeleteTest.php` | après suppression : retour à la dernière liste « Toutes les boîtes » ou ticket suivant ; corbeille / définitif (simple et groupé) ; autres actions inchangées ; boîte au-dessus de chaque ticket |
| `Feature/LanguageTest.php` | sélecteur de langue : affiché, change la langue du profil FreeScout (et de la session), langue inconnue refusée, pas de retour vers un autre site |
| `Feature/TrashTest.php` | vider la corbeille : droits de FreeScout (admin, permission, boîtes, « seulement assignés »), seuls les tickets de la corbeille supprimés, vidage automatique après N jours (0 = jamais), tâche planifiée, réglage borné |
| `Feature/TranslationsTest.php` | chaque langue a toutes les clés et les mêmes paramètres que l'anglais |

`Support/Fixtures.php` construit les données avec les modèles de FreeScout (adresses en `.test`).
