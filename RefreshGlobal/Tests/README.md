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
| `Feature/MailServerTrashTest.php` | e-mails des tickets supprimés définitivement mis en file puis déplacés dans la corbeille du serveur (faux serveur IMAP) ; jamais pour la mise à la corbeille ni la suppression d'une boîte entière ; corbeille introuvable → nouvel essai ; POP3 ignoré |
| `Feature/AutoRefreshTest.php` | actualisation automatique : empreinte de la liste inscrite dans la page et identique à `/refresh-global/state`, change pour un ticket des boîtes de l'utilisateur (pas pour les autres), filtres de la page appliqués, réglage borné, invités refusés |
| `Feature/SyncNowTest.php` | bouton « Relever les e-mails » : absent sans le module SyncNow (contrôles RG-ROUTE-05/06 ignorés), seulement les boîtes IMAP de la liste et de l'utilisateur, pas sans la permission de SyncNow |
| `Feature/AutoFoldersTest.php` | dossiers automatiques de Workflow Packs : rien sans le module (contrôles ignorés), arbre et nombres du panneau (sous-dossiers, spam et e-mails nettoyés exclus), filtre `wf` combinable (sous-dossiers, caractères `%`/`_` littéraux), badge, droits (boîtes, « seulement assignés »), export / actualisation / vue enregistrée, page « Vue d'ensemble », dossier du serveur copié à la suppression définitive. La table de Workflow Packs est créée pour les tests si le module manque (même schéma que sa migration) |
| `Feature/LayoutTest.php` | affichage selon la largeur, partie serveur : barre « Filtres actifs » (une pastille par filtre, chaque × n'enlève que ce filtre), bouton de fermeture du panneau, adresse de la boîte hors du texte du badge (pastilles du téléphone de Refresh) ; le panneau qui glisse est vérifié dans un navigateur |
| `Feature/TranslationsTest.php` | chaque langue a toutes les clés et les mêmes paramètres que l'anglais |

`Support/Fixtures.php` construit les données avec les modèles de FreeScout (adresses en `.test`).
