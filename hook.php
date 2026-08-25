<?php
/**
 * hook.php - install / uninstall / hooks utilitários
 */

use GlpiPlugin\Protocolo\Install;

/**
 * Install hook
 * @return bool
 */
function plugin_protocolo_install(): bool
{
    include_once __DIR__ . '/src/Install.php';
    return Install::install();
}

/**
 * Uninstall hook
 * @return bool
 */
function plugin_protocolo_uninstall(): bool
{
    include_once __DIR__ . '/src/Install.php';
    return Install::uninstall();
}

// Dropdown relations (ex: pasta -> escola) - só tabelas com itemtype conhecido para evitar warning DbUtils
function plugin_protocolo_getDatabaseRelations(): array
{
    return [
        'glpi_plugin_protocolo_escolas' => [
            'glpi_plugin_protocolo_pastas' => 'plugin_protocolo_escolas_id'
        ],
        'glpi_plugin_protocolo_pastas' => [
            'glpi_plugin_protocolo_itens' => 'plugin_protocolo_pastas_id',
            'glpi_plugin_protocolo_termos' => 'plugin_protocolo_pastas_id',
            'glpi_plugin_protocolo_pastatipos' => 'plugin_protocolo_pastas_id',
        ],
        // 'glpi_plugin_protocolo_tipos' removido para evitar "Invalid relations" - limpeza feita via FK sem checagem DbUtils
    ];
}

function plugin_protocolo_getDropdown(): array
{
    return [
        \GlpiPlugin\Protocolo\Escola::class => __('Escolas', 'protocolo'),
        \GlpiPlugin\Protocolo\TipoArquivo::class => __('Tipos de Arquivo', 'protocolo'),
    ];
}

function plugin_protocolo_getAddSearchOptions($itemtype): array
{
    $sopt = [];
    return $sopt;
}

function plugin_protocolo_getAddSearchOptionsNew($itemtype): array
{
    return [];
}

function plugin_protocolo_cronInfo(): array
{
    return [
        'protocolo' => [
            'description' => 'Envio de notificações pendentes do Protocolo',
            'parameter'   => 'Número de notificações a processar por execução'
        ]
    ];
}

function plugin_protocolo_cronProtocolo(\CronTask $task): int
{
    include_once __DIR__ . '/src/Config.php';
    include_once __DIR__ . '/src/Notificacao.php';
    return \GlpiPlugin\Protocolo\Notificacao::cronProtocolo($task);
}

// Alias para compat com tarefas antigas protocolo_send
function plugin_protocolo_cronProtocolo_send(\CronTask $task): int
{
    return plugin_protocolo_cronProtocolo($task);
}
