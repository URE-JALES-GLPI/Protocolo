<?php
/**
 * ajax/can.php - Pre-checagem de permissão (usado antes de abrir a janela
 * Registrar Entrada). Retorna a avaliação EFETIVA da sessão atual, para que
 * o modal nunca abra "cego" e o envio morra com 403 depois.
 */
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;

Session::checkLoginUser();

header('Content-Type: application/json; charset=utf-8');

$pid = (int)($_SESSION['glpiactive_profile']['id'] ?? 0);
$pname = '';
try {
    global $DB;
    if ($pid > 0 && isset($DB) && $DB->tableExists('glpi_profiles')) {
        $it = $DB->request(['SELECT' => ['name'], 'FROM' => 'glpi_profiles', 'WHERE' => ['id' => $pid], 'LIMIT' => 1]);
        foreach ($it as $r) {
            $pname = (string)($r['name'] ?? '');
            break;
        }
    }
} catch (\Throwable $e) {
}

echo json_encode([
    'canCreate' => Pasta::canCreate(),
    'canView'   => Pasta::canView(),
    'uid'       => (int)Session::getLoginUserID(),
    'pid'       => $pid,
    'pname'     => $pname,
], JSON_UNESCAPED_UNICODE);
