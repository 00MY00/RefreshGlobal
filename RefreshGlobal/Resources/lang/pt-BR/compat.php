<?php

return [
    'env' => [
        'check'  => 'Versão do FreeScout dentro da faixa testada',
        'label'  => 'Versão do FreeScout fora da faixa testada.',
        'effect' => 'A página funciona, mas não foi testada com esta versão do FreeScout.',
        'action' => 'Consulte o COMPATIBILITY.md e a versão mais recente do RefreshGlobal e depois execute php artisan refreshglobal:check.',
    ],
    'ref_present' => [
        'check'  => 'Módulo Refresh presente e ativo',
        'label'  => 'O módulo Refresh não está instalado ou não está ativo.',
        'effect' => 'A página “Todas as caixas” é exibida com a aparência padrão do FreeScout.',
        'action' => 'Instale e ative o Refresh (Gerenciar › Módulos) ou mantenha a aparência padrão. Depois execute php artisan refreshglobal:check.',
    ],
    'ref_version' => [
        'check'  => 'Versão do Refresh dentro da faixa testada',
        'label'  => 'Versão do Refresh fora da faixa testada.',
        'effect' => 'A página funciona; sua aparência pode diferir um pouco das páginas do Refresh.',
        'action' => 'Consulte o COMPATIBILITY.md e a versão mais recente do RefreshGlobal e depois execute php artisan refreshglobal:check.',
    ],
    'css' => [
        'check'  => 'Folhas de estilo do Refresh presentes',
        'label'  => 'A folha de estilo do Refresh não foi encontrada.',
        'effect' => 'A página “Todas as caixas” é exibida com a aparência padrão do FreeScout.',
        'action' => 'Verifique a versão instalada do Refresh e depois execute php artisan refreshglobal:check.',
    ],
    'js' => [
        'check'  => 'Scripts do Refresh presentes',
        'label'  => 'Os scripts do Refresh não foram encontrados.',
        'effect' => 'A página “Todas as caixas” é exibida com a aparência padrão do FreeScout.',
        'action' => 'Verifique a versão instalada do Refresh e depois execute php artisan refreshglobal:check.',
    ],
    'view' => [
        'check'           => 'A view :item existe',
        'label'           => 'Uma view usada pela página não foi encontrada: :item.',
        'effect_blocking' => 'A lista de tickets não pode ser exibida.',
        'effect'          => 'A página é exibida com a aparência padrão do FreeScout.',
        'action'          => 'Atualize o RefreshGlobal para uma versão feita para esta versão do FreeScout / Refresh (COMPATIBILITY.md) e depois execute php artisan refreshglobal:check.',
    ],
    'hook' => [
        'check'  => 'O hook “:item” é disparado por :file',
        'label'  => 'O hook “:item” não é mais disparado por :file.',
        'effect' => 'O elemento adicionado por este hook (item de menu, coluna “Caixa” ou ícone da barra lateral) está ausente. A lista funciona.',
        'action' => 'Atualize o RefreshGlobal para uma versão feita para esta versão do FreeScout / Refresh e depois execute php artisan refreshglobal:check.',
    ],
    'core' => [
        'check'  => 'Código do FreeScout presente: :item',
        'label'  => 'Uma classe, método ou constante do FreeScout usada pelo módulo está ausente: :item.',
        'effect' => 'A lista de tickets não é exibida, para não mostrar nenhum ticket não autorizado.',
        'action' => 'Atualize o RefreshGlobal para uma versão feita para esta versão do FreeScout (COMPATIBILITY.md) e depois execute php artisan refreshglobal:check.',
    ],
    'route' => [
        'check'  => 'A rota :item existe',
        'label'  => 'Uma rota usada pelo módulo não foi encontrada: :item.',
        'effect' => 'A lista de tickets não é exibida: seus links não levariam a lugar nenhum.',
        'action' => 'Limpe o cache (php artisan freescout:clear-cache) e depois execute php artisan refreshglobal:check. Se persistir, atualize o RefreshGlobal.',
    ],
    'db' => [
        'check'  => 'Tabela e colunas presentes: :item',
        'label'  => 'Uma tabela ou coluna do FreeScout usada pelo módulo está ausente: :item.',
        'effect' => 'A lista de tickets não é exibida.',
        'action' => 'Execute php artisan migrate (ou Gerenciar › Sistema › Ferramentas › Migrar BD) e depois php artisan refreshglobal:check.',
    ],
    'db_own' => [
        'check'  => 'Tabela de visualizações salvas presente: :item',
        'label'  => 'A tabela do módulo para visualizações salvas está ausente: :item.',
        'effect' => 'A lista funciona; as visualizações salvas estão desativadas.',
        'action' => 'Execute php artisan migrate (ou Gerenciar › Sistema › Ferramentas › Migrar BD) e depois php artisan refreshglobal:check.',
    ],
    'acl' => [
        'check'  => 'Regras de acesso às caixas disponíveis (:item)',
        'label'  => 'A lista do FreeScout das caixas visíveis para um usuário (User::mailboxesCanView) não está disponível.',
        'effect' => 'A lista de tickets não é exibida, para não mostrar nenhum ticket não autorizado.',
        'action' => 'Atualize o RefreshGlobal para uma versão feita para esta versão do FreeScout e depois execute php artisan refreshglobal:check.',
    ],
    'acl_assigned' => [
        'check'  => 'Regra “somente conversas atribuídas” disponível (:item)',
        'label'  => 'A permissão do FreeScout “somente conversas atribuídas” (User::canSeeOnlyAssignedConversations) não está disponível.',
        'effect' => 'A lista de tickets não é exibida, para não mostrar nenhum ticket não autorizado.',
        'action' => 'Atualize o RefreshGlobal para uma versão feita para esta versão do FreeScout e depois execute php artisan refreshglobal:check.',
    ],
    'check_error' => [
        'check'  => 'A verificação :item é executada sem erro',
        'label'  => 'Uma verificação de compatibilidade falhou de forma inesperada: :item.',
        'effect' => 'Por precaução, a lista de tickets não é exibida.',
        'action' => 'Leia storage/logs/laravel.log e depois execute php artisan refreshglobal:check.',
    ],
    'error' => [
        'check'  => 'Página gerada sem erro',
        'label'  => 'Erro inesperado ao gerar a página.',
        'effect' => 'A lista de tickets não é exibida.',
        'action' => 'Leia storage/logs/laravel.log (código RG-ERR-01) e depois execute php artisan refreshglobal:check.',
    ],
];
