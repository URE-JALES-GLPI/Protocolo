<?php
include('../../../inc/includes.php');

Session::checkLoginUser();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$uid = (int)Session::getLoginUserID();
if ($uid <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sessão expirada.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}
$view = strtolower(trim($data['view'] ?? ''));
if (!in_array($view, ['grid', 'list'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Visão inválida.']);
    exit;
}

try {
    global $DB;
    if (!$DB->tableExists('glpi_plugin_protocolo_view_prefs')) {
        echo json_encode(['ok' => false, 'error' => 'Tabela de preferências ainda não criada. Atualize o plugin.']);
        exit;
    }
    $exists = false;
    $it = $DB->request(['SELECT' => ['users_id'], 'FROM' => 'glpi_plugin_protocolo_view_prefs', 'WHERE' => ['users_id' => $uid], 'LIMIT' => 1]);
    foreach ($it as $row) {
        $exists = true;
        break;
    }
    if ($exists) {
        $DB->update('glpi_plugin_protocolo_view_prefs', ['view' => $view], ['users_id' => $uid]);
    } else {
        $DB->insert('glpi_plugin_protocolo_view_prefs', ['users_id' => $uid, 'view' => $view]);
    }
    echo json_encode(['ok' => true, 'view' => $view]);
} catch (\Throwable $e) {
    error_log('[protocolo] view pref falhou: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Falha ao salvar.']);
}
