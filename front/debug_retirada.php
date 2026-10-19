<?php
/**
 * DEBUG TEMPORARIO - inspeciona colunas entregador_* e valores de uma pasta.
 * Acesso: /plugins/protocolo/front/debug_retirada.php?id=<ID>
 * Remover apos o diagnostico.
 */
include('../../../inc/includes.php');
Session::checkLoginUser();
if (!\GlpiPlugin\Protocolo\Pasta::canView()) { Html::displayRightError(); }

global $DB;
header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
$table = 'glpi_plugin_protocolo_pastas';

$cols = ['entregador_nome','entregador_documento','entregador_documento_tipo',
         'entregador_assinatura_image','entregador_assinatura_data',
         'retirada_assinatura_image','retirada_assinatura_data','retirado_por','retirado_documento','status'];

$exists = [];
foreach ($cols as $c) {
    try { $exists[$c] = (bool)$DB->fieldExists($table, $c); } catch (\Throwable $e) { $exists[$c] = 'err:' . $e->getMessage(); }
}

$row = null;
if ($id > 0) {
    try {
        $it = $DB->request(['FROM' => $table, 'WHERE' => ['id' => $id], 'LIMIT' => 1]);
        foreach ($it as $r) {
            $row = [];
            foreach ($cols as $c) {
                $v = $r[$c] ?? null;
                if (is_string($v) && strpos($v, 'data:image/') === 0) {
                    $v = substr($v, 0, 40) . '...(' . strlen($v) . ' bytes)';
                }
                $row[$c] = $v;
            }
            break;
        }
    } catch (\Throwable $e) { $row = 'err:' . $e->getMessage(); }
}

echo json_encode([
    'ok' => true,
    'table' => $table,
    'columns_exist' => $exists,
    'row' => $row,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
