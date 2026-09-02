<?php
/**
 * Plugin Protocolo - Sistema de Protocolo de Pastas para GLPI (URE)
 * Converte o sistema standalone pasta (PROT-YYYY-XXXX) para plugin GLPI 11.x
 * Author: Leonardo Poiatti Fação
 * License: GPLv2+
 */

define('PLUGIN_PROTOCOLO_VERSION', '1.5.1');
define('PLUGIN_PROTOCOLO_MIN_GLPI', '11.0.0');
define('PLUGIN_PROTOCOLO_MAX_GLPI', '12.0.0');
define('PLUGIN_PROTOCOLO_NAMESPACE', 'GlpiPlugin\\Protocolo');

/**
 * @return array
 */
function plugin_version_protocolo(): array
{
    return [
        'name'           => '[URE] Protocolo de Pastas - URE',
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
    Plugin::registerClass(\GlpiPlugin\Protocolo\Notificacao::class);
    Plugin::registerClass(\GlpiPlugin\Protocolo\Config::class);
    Plugin::registerClass(\GlpiPlugin\Protocolo\EntityMail::class);

    // Perfil / direitos - aba Protocolo dentro de Administração > Perfis
    Plugin::registerClass(\GlpiPlugin\Protocolo\Profile::class, ['addtabon' => Profile::class]);
    $PLUGIN_HOOKS['change_profile']['protocolo'] = [\GlpiPlugin\Protocolo\Profile::class, 'changeProfile'];

    // CSRF compliance - deve ser true para proteger POST
    $PLUGIN_HOOKS['csrf_compliant']['protocolo'] = true;

    // Menu GLPI 10/11 - apenas em Ferramentas (evita duplicar em Plug-ins)
    $PLUGIN_HOOKS['menu_toadd']['protocolo'] = [
        'tools' => \GlpiPlugin\Protocolo\Pasta::class,
    ];
    $PLUGIN_HOOKS['menu_entry']['protocolo'] = 'front/dashboard.php';
    // Usa Plugin::getWebDir para suportar plugins/ ou marketplace/ sem duplicar root_doc
    $protocolo_webdir = \Plugin::getWebDir('protocolo');
    // Submenu espera caminho sem root_doc (GLPI prepend root_doc internamente), então remove se já contém
    $protocolo_webdir_noroot = $protocolo_webdir;
    if (isset($CFG_GLPI['root_doc']) && $CFG_GLPI['root_doc'] !== '' && str_starts_with($protocolo_webdir, $CFG_GLPI['root_doc'])) {
        $protocolo_webdir_noroot = substr($protocolo_webdir, strlen($CFG_GLPI['root_doc']));
        if ($protocolo_webdir_noroot === '' || $protocolo_webdir_noroot[0] !== '/') $protocolo_webdir_noroot = '/' . ltrim($protocolo_webdir_noroot, '/');
    }
    $PLUGIN_HOOKS['submenu_entry']['protocolo']['dashboard'] = [
        'title' => 'Dashboard',
        'page'  => $protocolo_webdir_noroot . '/front/dashboard.php',
        'links' => [
            'search' => $protocolo_webdir_noroot . '/front/pasta.php',
            'add'    => $protocolo_webdir_noroot . '/front/pasta.form.php',
        ]
    ];
    $PLUGIN_HOOKS['submenu_entry']['protocolo']['pastas'] = [
        'title' => 'Pastas',
        'page'  => $protocolo_webdir_noroot . '/front/pasta.php',
        'links' => [
            'search' => $protocolo_webdir_noroot . '/front/pasta.php',
            'add'    => $protocolo_webdir_noroot . '/front/pasta.form.php',
        ]
    ];
    $PLUGIN_HOOKS['submenu_entry']['protocolo']['escolas'] = [
        'title' => 'Escolas',
        'page'  => $protocolo_webdir_noroot . '/front/escola.php',
        'links' => [
            'search' => $protocolo_webdir_noroot . '/front/escola.php',
            'add'    => $protocolo_webdir_noroot . '/front/escola.form.php',
        ]
    ];
    $PLUGIN_HOOKS['submenu_entry']['protocolo']['tipos'] = [
        'title' => 'Tipos de Arquivo',
        'page'  => $protocolo_webdir_noroot . '/front/tipo.php',
        'links' => [
            'search' => $protocolo_webdir_noroot . '/front/tipo.php',
            'add'    => $protocolo_webdir_noroot . '/front/tipo.form.php',
        ]
    ];
    $PLUGIN_HOOKS['config_page']['protocolo'] = 'front/config.php';

    // Cron
    $PLUGIN_HOOKS['cron']['protocolo'] = 3600;

    // JS/CSS - usa webdir absoluto para evitar 404 marketplace/plugins e duplicação root_doc
    $PLUGIN_HOOKS['add_javascript']['protocolo'] = [
        $protocolo_webdir . '/js/app.js'
    ];
    $PLUGIN_HOOKS['add_css']['protocolo'] = [
        $protocolo_webdir . '/css/style.css'
    ];

    // Migração ENTIDADES - roda apenas uma vez por versão (cache em glpi_configs)
    try {
        if (class_exists(\GlpiPlugin\Protocolo\Install::class) && isset($DB) && $DB->tableExists('glpi_plugin_protocolo_escolas')) {
            $migratedVersion = null;
            try { $migratedVersion = \Config::getConfigurationValue('plugin:protocolo', 'migrated_version'); } catch (\Throwable $e) {}
            if ($migratedVersion !== PLUGIN_PROTOCOLO_VERSION) {
                \GlpiPlugin\Protocolo\Install::migrateEntities($DB);
                // Garante novos direitos Usar/Admin e migração de legados
                try {
                    $ref = new \ReflectionMethod(\GlpiPlugin\Protocolo\Install::class, 'initRights');
                    $ref->setAccessible(true);
                    $ref->invoke(null);
                } catch (\Throwable $e2) { error_log("[protocolo] initRights auto falhou: " . $e2->getMessage()); }
                try { \GlpiPlugin\Protocolo\Config::initDefaults(); } catch (\Throwable $e2) {}
                try { \GlpiPlugin\Protocolo\Install::registerCron(); } catch (\Throwable $e2) {}
                try { \Config::setConfigurationValues('plugin:protocolo', ['migrated_version' => PLUGIN_PROTOCOLO_VERSION]); } catch (\Throwable $e) {}
            }
        }
    } catch (\Throwable $e) {
        error_log("[protocolo] migrateEntities auto falhou: " . $e->getMessage());
    }
}
