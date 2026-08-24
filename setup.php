<?php
/**
 * Plugin Protocolo - Sistema de Protocolo de Pastas para GLPI (URE)
 * Converte o sistema standalone pasta (PROT-YYYY-XXXX) para plugin GLPI 11.x
 * Author: Leonardo Poiatti Fação
 * License: GPLv2+
 */

define('PLUGIN_PROTOCOLO_VERSION', '1.0.1');
define('PLUGIN_PROTOCOLO_MIN_GLPI', '11.0.0');
define('PLUGIN_PROTOCOLO_MAX_GLPI', '12.0.0');
define('PLUGIN_PROTOCOLO_NAMESPACE', 'GlpiPlugin\\Protocolo');

/**
 * @return array
 */
function plugin_version_protocolo(): array
{
    return [
        'name'           => 'Protocolo de Pastas - URE',
        'version'        => PLUGIN_PROTOCOLO_VERSION,
        'author'         => 'Leonardo Poiatti Fação',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://github.com/anomalyco/protocolo',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_PROTOCOLO_MIN_GLPI,
                'max' => PLUGIN_PROTOCOLO_MAX_GLPI,
            ],
            'php' => [
                'min' => '8.1',
                'exts' => ['mysqli', 'gd', 'curl']
            ]
        ]
    ];
}

function plugin_protocolo_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_PROTOCOLO_MIN_GLPI, '<')) {
        echo "Este plugin requer GLPI >= " . PLUGIN_PROTOCOLO_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_protocolo_check_config(bool $verbose = false): bool
{
    return true;
}

/**
 * Inicialização do plugin (PSR-4)
 */
function plugin_init_protocolo(): void
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $plugin = new Plugin();
    if (!$plugin->isActivated('protocolo')) {
        return;
    }

    // Autoload via composer se existir
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    // Registra classes do plugin
    // GLPI 11: src/ com namespace GlpiPlugin\Protocolo
    Plugin::registerClass(\GlpiPlugin\Protocolo\Pasta::class, [
        'addtabon' => ['Central']
    ]);
    Plugin::registerClass(\GlpiPlugin\Protocolo\Escola::class);
    Plugin::registerClass(\GlpiPlugin\Protocolo\TipoArquivo::class);
    Plugin::registerClass(\GlpiPlugin\Protocolo\Termo::class);

    // Perfil / direitos - aba Protocolo dentro de Administração > Perfis
    Plugin::registerClass(\GlpiPlugin\Protocolo\Profile::class, ['addtabon' => Profile::class]);
    $PLUGIN_HOOKS['change_profile']['protocolo'] = [\GlpiPlugin\Protocolo\Profile::class, 'changeProfile'];

    // CSRF compliance
    $PLUGIN_HOOKS['csrf_compliant']['protocolo'] = true;

    // Menu GLPI 10/11 - sempre registra (GLPI filtra por canView)
    $PLUGIN_HOOKS['menu_toadd']['protocolo'] = [
        'tools'   => \GlpiPlugin\Protocolo\Pasta::class,
        'plugins' => \GlpiPlugin\Protocolo\Pasta::class,
    ];
    $PLUGIN_HOOKS['menu_entry']['protocolo'] = 'front/dashboard.php';
    $PLUGIN_HOOKS['submenu_entry']['protocolo']['dashboard'] = [
        'title' => 'Dashboard',
        'page'  => '/plugins/protocolo/front/dashboard.php',
        'links' => [
            'search' => '/plugins/protocolo/front/pasta.php',
            'add'    => '/plugins/protocolo/front/pasta.form.php',
        ]
    ];
    $PLUGIN_HOOKS['submenu_entry']['protocolo']['pastas'] = [
        'title' => 'Pastas',
        'page'  => '/plugins/protocolo/front/pasta.php',
        'links' => [
            'search' => '/plugins/protocolo/front/pasta.php',
            'add'    => '/plugins/protocolo/front/pasta.form.php',
        ]
    ];
    $PLUGIN_HOOKS['submenu_entry']['protocolo']['escolas'] = [
        'title' => 'Escolas',
        'page'  => '/plugins/protocolo/front/escola.php',
        'links' => [
            'search' => '/plugins/protocolo/front/escola.php',
            'add'    => '/plugins/protocolo/front/escola.form.php',
        ]
    ];
    $PLUGIN_HOOKS['submenu_entry']['protocolo']['tipos'] = [
        'title' => 'Tipos de Arquivo',
        'page'  => '/plugins/protocolo/front/tipo.php',
        'links' => [
            'search' => '/plugins/protocolo/front/tipo.php',
            'add'    => '/plugins/protocolo/front/tipo.form.php',
        ]
    ];
    $PLUGIN_HOOKS['config_page']['protocolo'] = 'front/config.php';

    // JS/CSS
    $PLUGIN_HOOKS['add_javascript']['protocolo'] = [
        'js/app.js'
    ];
    $PLUGIN_HOOKS['add_css']['protocolo'] = [
        'css/style.css'
    ];

    // Migração automática ENTIDADES para Escola (sem precisar reinstalar)
    // Garante que escolas antigas (entities_id=0, is_recursive=0) fiquem visíveis nas sub-entidades
    try {
        if (class_exists(\GlpiPlugin\Protocolo\Install::class)) {
            // Só roda se plugin já está com tabela criada (evita erro em install limpo)
            global $DB;
            if (isset($DB) && $DB->tableExists('glpi_plugin_protocolo_escolas')) {
                \GlpiPlugin\Protocolo\Install::migrateEntities($DB);
            }
        }
    } catch (\Throwable $e) {
        error_log("[protocolo] migrateEntities auto falhou: " . $e->getMessage());
    }
}
