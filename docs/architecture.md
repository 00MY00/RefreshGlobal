# Architecture

```mermaid
flowchart LR
    subgraph FreeScout
        L[layouts/app.blade.php<br/>menu.append]
        T[conversations/conversations_table<br/>hooks conversations_table.*]
        U[User::mailboxesCanView<br/>canSeeOnlyAssignedConversations]
        C[(conversations, mailboxes,<br/>customers, mailbox_user)]
        V[/conversation/{id}<br/>page native du ticket/]
    end
    subgraph Refresh["Refresh (lu, jamais modifié)"]
        RC[refresh.css / mobile.js<br/>chargés par Refresh]
        RR[refresh.rail_items]
    end
    subgraph RG["Modules/RefreshGlobal"]
        A[MailboxAccess<br/>seul point des droits]
        Q[GlobalTicketQuery<br/>droits + filtres + tri]
        G[GlobalTicketsController<br/>liste + compteurs]
        E[ExportController<br/>CSV]
        S[SavedViewsController<br/>refreshglobal_saved_views]
        K[CompatibilityChecker<br/>Config/integration.php]
        P[Providers/RefreshGlobalServiceProvider]
    end
    U --> A --> Q
    Q --> C
    G --> Q
    E --> Q
    S --> A
    G -->|inclut| T
    G -->|liens| V
    P -->|menu.append| L
    P -->|colonne Boîte| T
    P -->|icône| RR
    RC -.->|classes rf-* reprises| G
    K -->|vérifie| L & T & U & C & RC & RR
```

- **Une seule requête** (`GlobalTicketQuery`) pour la liste, les compteurs et l'export : les droits ne peuvent pas
  diverger d'un écran à l'autre.
- **Une seule liste des points d'intégration** (`Config/integration.php`) : elle est lue par le contrôle de
  compatibilité, décrite dans `INTEGRATION_MAP.md`.
- États de la page : `ok` / `warning` → page normale ; `degraded` → style FreeScout standard + bandeau ;
  `blocking` → pas de liste, message et liens vers les boîtes natives.
