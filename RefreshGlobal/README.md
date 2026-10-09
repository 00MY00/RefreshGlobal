# RefreshGlobal (module FreeScout)

Page « Toutes les boîtes » : les tickets de toutes les boîtes accessibles, dans une seule liste, avec filtre par
boîte, compteurs, export CSV et vues enregistrées. Apparence de Refresh quand il est installé, style FreeScout
standard sinon.

- Installation, mise à jour, désinstallation : README du dépôt (installeur `install.sh`).
- Installation manuelle : copier ce dossier dans `Modules/RefreshGlobal` de FreeScout, puis
  **Gérer › Modules › RefreshGlobal › Activer**.
- Vérification : `php artisan refreshglobal:check` ou `/refresh-global/diagnostic` (administrateurs).
- Points d'intégration avec FreeScout et Refresh : [INTEGRATION_MAP.md](INTEGRATION_MAP.md).
- Licence : AGPL-3.0 (comme FreeScout).

---

"All mailboxes" page for FreeScout: tickets of every mailbox you can access in one list, with mailbox filter,
counters, CSV export and saved views. Looks like the Refresh module when it is installed. Manual install: copy this
folder to `Modules/RefreshGlobal`, then **Manage › Modules › RefreshGlobal › Activate**.
