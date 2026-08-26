<?php
include('../../../inc/includes.php');
header('Content-Type: text/plain; charset=utf-8');
echo "setup.php PLUGIN_PROTOCOLO_VERSION = " . (defined('PLUGIN_PROTOCOLO_VERSION') ? PLUGIN_PROTOCOLO_VERSION : 'not defined') . "\n";
$ver = plugin_version_protocolo();
echo "plugin_version_protocolo() = " . json_encode($ver, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n";
echo "protocolo.xml = " . file_get_contents(__DIR__ . '/../protocolo.xml') . "\n";
global $DB;
$it = $DB->request(['FROM'=>'glpi_plugins','WHERE'=>['directory'=>'protocolo']]);
foreach ($it as $row) {
    echo "DB glpi_plugins = " . json_encode($row, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n";
}
echo "opcache_invalidate setup.php: " . (opcache_invalidate(__DIR__.'/../setup.php', true) ? 'ok' : 'fail') . "\n";
echo "opcache_invalidate protocolo.xml: " . (opcache_invalidate(__DIR__.'/../protocolo.xml', true) ? 'ok' : 'fail') . "\n";
