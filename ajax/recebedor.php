<?php
if (isset($_SERVER['HTTP_X_GLPI_CSRF_TOKEN']) && !isset($_POST['_glpi_csrf_token']) && !isset($_GET['_glpi_csrf_token'])) {
    $_POST['_glpi_csrf_token'] = $_SERVER['HTTP_X_GLPI_CSRF_TOKEN'];
    $_REQUEST['_glpi_csrf_token'] = $_SERVER['HTTP_X_GLPI_CSRF_TOKEN'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $raw_pre = file_get_contents('php://input');
    if ($raw_pre) {
        $tmp_pre = json_decode($raw_pre, true);
        if (is_array($tmp_pre) && isset($tmp_pre['_glpi_csrf_token']) && !isset($_POST['_glpi_csrf_token'])) {
            $_POST['_glpi_csrf_token'] = $tmp_pre['_glpi_csrf_token'];
            $_REQUEST['_glpi_csrf_token'] = $tmp_pre['_glpi_csrf_token'];
        }
        $GLOBALS['_pt_rec_raw'] = $raw_pre;
    }
}
ini_set('display_errors', '0');
error_reporting(E_ALL);

include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;

Session::checkLoginUser();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$action = $_GET['action'] ?? '';
if ($action === '') {
    $raw0 = $GLOBALS['_pt_rec_raw'] ?? file_get_contents('php://input');
    $tmp0 = json_decode($raw0 ?: '{}', true);
    if (is_array($tmp0) && isset($tmp0['action'])) {
        $action = $tmp0['action'];
    }
}

global $DB;

if ($action === 'list') {
    if (!Pasta::canView()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sem permissão.']);
        exit;
    }
    $items = [];
    try {
        if ($DB->tableExists('glpi_plugin_protocolo_recebedores')) {
            $it = $DB->request(['FROM' => 'glpi_plugin_protocolo_recebedores', 'WHERE' => ['is_active' => 1], 'ORDER' => 'name']);
            foreach ($it as $r) {
                $items[] = [
                    'id' => (int)$r['id'],
                    'name' => $r['name'],
                    'doc_tipo' => $r['document_type'] ?? 'cpf',
                    'doc' => $r['document'] ?? '',
                    'image' => $r['assinatura_image'] ?? '',
                ];
            }
        }
    } catch (\Throwable $e) {
        error_log('[protocolo] recebedor list falhou: ' . $e->getMessage());
    }
    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save') {
    if (!Pasta::canCreate()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sem permissão para cadastrar.']);
        exit;
    }
    $raw = $GLOBALS['_pt_rec_raw'] ?? file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) {
        $data = $_POST;
    }
    $csrf = $data['_glpi_csrf_token'] ?? '';
    if ($csrf === '' || !Session::validateCSRF(['_glpi_csrf_token' => $csrf])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página (F5).']);
        exit;
    }
    $nome = trim($data['nome'] ?? '');
    if ($nome === '') {
        echo json_encode(['ok' => false, 'error' => 'Informe o nome.']);
        exit;
    }
    $docTipo = strtolower(trim($data['doc_tipo'] ?? 'cpf'));
    if (!in_array($docTipo, ['cpf', 'rg'], true)) {
        $docTipo = 'cpf';
    }
    $doc = trim($data['doc'] ?? '');
    if ($doc !== '') {
        $docOk = ($docTipo === 'cpf') ? Pasta::validarCPF($doc) : Pasta::validarRG($doc);
        if (!$docOk) {
            echo json_encode(['ok' => false, 'error' => $docTipo === 'cpf' ? 'CPF inválido (11 dígitos válidos).' : 'RG inválido (7 a 9 dígitos).']);
            exit;
        }
    }
    $image = trim($data['image'] ?? '');
    if ($image === '' || strpos($image, 'data:image/') !== 0 || strlen($image) > 1500000) {
        echo json_encode(['ok' => false, 'error' => 'Faça a assinatura no quadro.']);
        exit;
    }
    try {
        if (!$DB->tableExists('glpi_plugin_protocolo_recebedores')) {
            echo json_encode(['ok' => false, 'error' => 'Tabela ainda não criada. Atualize o plugin.']);
            exit;
        }
        $newId = $DB->insert('glpi_plugin_protocolo_recebedores', [
            'name' => mb_substr($nome, 0, 255),
            'document_type' => $docTipo,
            'document' => $doc !== '' ? $doc : null,
            'assinatura_image' => $image,
            'assinatura_data' => date('Y-m-d H:i:s'),
            'users_id' => Session::getLoginUserID(),
            'is_active' => 1,
        ]);
        echo json_encode(['ok' => true, 'id' => (int)$newId], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        error_log('[protocolo] recebedor save falhou: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Falha ao salvar.']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Ação inválida.']);
