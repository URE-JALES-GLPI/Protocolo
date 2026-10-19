<?php
/**
 * DEBUG TEMPORARIO - inspeciona colunas entregador_* e valores das ultimas pastas.
 * Acesso: /plugins/protocolo/front/debug_retirada.php           (10 ultimas retiradas)
 *         /plugins/protocolo/front/debug_retirada.php?id=<ID>  (detalha uma pasta)
 * Remover apos o diagnostico.
 */
include('../../../inc/includes.php');
Session::checkLoginUser();
if (!\GlpiPlugin\Protocolo\Pasta::canView()) { Html::displayRightError(); }

global $DB;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$id = (int)($_GET['id'] ?? 0);
$table = 'glpi_plugin_protocolo_pastas';

$cols = ['entregador_nome','entregador_documento','entregador_documento_tipo',
         'entregador_assinatura_image','entregador_assinatura_data',
         'retirada_assinatura_image','retirada_assinatura_data','retirado_por','retirado_documento',
         'status','data_retirada'];

// Sem cache: o cache de schema do GLPI pode estar velho após ALTER TABLE.
$exists = [];
foreach ($cols as $c) {
    try { $exists[$c] = (bool)$DB->fieldExists($table, $c, false); } catch (\Throwable $e) { $exists[$c] = 'err:' . $e->getMessage(); }
}

function pt_dbg_row(array $r, array $cols): array
{
    $out = [];
    foreach ($cols as $c) {
        $v = $r[$c] ?? null;
        if (is_string($v) && strpos($v, 'data:image/') === 0) {
            $v = substr($v, 0, 40) . '...(' . strlen($v) . ' bytes)';
        }
        $out[$c] = $v;
    }
    return $out;
}

$rows = [];
try {
    $it = $DB->request([
        'FROM'  => $table,
        'WHERE' => $id > 0 ? ['id' => $id] : ['status' => 'retirada'],
        'ORDER' => ['id DESC'],
        'LIMIT' => $id > 0 ? 1 : 10,
    ]);
    foreach ($it as $r) {
        $rows[] = pt_dbg_row($r, $cols);
    }
} catch (\Throwable $e) {
    $rows = 'err:' . $e->getMessage();
}

$migrated = null;
try { $migrated = \Config::getConfigurationValue('plugin:protocolo', 'migrated_version'); } catch (\Throwable $e) {}

echo json_encode([
    'ok' => true,
    'plugin_version' => defined('PLUGIN_PROTOCOLO_VERSION') ? PLUGIN_PROTOCOLO_VERSION : null,
    'migrated_version' => $migrated,
    'table' => $table,
    'columns_exist' => $exists,
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
