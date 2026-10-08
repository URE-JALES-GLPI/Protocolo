<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;

Session::checkLoginUser();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function pt_retirada_answer(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Pasta::canView()) {
    pt_retirada_answer(['ok' => false, 'error' => 'Sem permissão.'], 403);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}

$csrf = $data['_glpi_csrf_token'] ?? '';
if ($csrf === '' || !Session::validateCSRF(['_glpi_csrf_token' => $csrf])) {
    pt_retirada_answer(['ok' => false, 'code' => 'SESSION_DEAD', 'error' => 'Sessão expirada. Recarregue a página (F5) e tente de novo.'], 403);
}

$ids = $data['ids'] ?? [];
if (!is_array($ids)) {
    $ids = [$ids];
}
$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
if (empty($ids)) {
    pt_retirada_answer(['ok' => false, 'error' => 'Nenhuma pasta selecionada.']);
}

$nome = trim($data['nome'] ?? '');
if ($nome === '') {
    pt_retirada_answer(['ok' => false, 'error' => 'Informe o nome de quem retira.']);
}
$docTipo = strtolower(trim($data['doc_tipo'] ?? 'cpf'));
if (!in_array($docTipo, ['cpf', 'rg'], true)) {
    $docTipo = 'cpf';
}
$doc = trim($data['doc'] ?? '');
if ($doc !== '') {
    $docOk = ($docTipo === 'cpf') ? Pasta::validarCPF($doc) : Pasta::validarRG($doc);
    if (!$docOk) {
        pt_retirada_answer(['ok' => false, 'error' => $docTipo === 'cpf' ? 'CPF inválido (11 dígitos válidos).' : 'RG inválido (7 a 9 dígitos).']);
    }
}
$image = trim($data['image'] ?? '');
if ($image === '' || strpos($image, 'data:image/') !== 0) {
    pt_retirada_answer(['ok' => false, 'error' => 'Faça a assinatura no quadro.']);
}
if (strlen($image) > 1500000) {
    pt_retirada_answer(['ok' => false, 'error' => 'Assinatura muito grande. Limpe e assine de novo.']);
}

$results = [];
$termos = [];
foreach ($ids as $pid) {
    $pasta = new Pasta();
    if (!$pasta->getFromDB($pid)) {
        $results[] = ['id' => $pid, 'ok' => false, 'error' => 'Pasta não encontrada.'];
        continue;
    }
    if (($pasta->fields['status'] ?? '') !== 'aguardando') {
        $results[] = ['id' => $pid, 'ok' => false, 'error' => 'Pasta ' . ($pasta->fields['codigo'] ?? $pid) . ' não está aguardando.'];
        continue;
    }
    if (!$pasta->can($pid, UPDATE)) {
        $results[] = ['id' => $pid, 'ok' => false, 'error' => 'Sem direito de atualizar a pasta ' . ($pasta->fields['codigo'] ?? $pid) . '.'];
        continue;
    }
    $params = [
        'retirado_por' => $nome,
        'retirado_documento_tipo' => $docTipo,
        'retirado_documento' => $doc,
        'data_retirada' => date('Y-m-d H:i:s'),
        'observacao_retirada' => trim($data['obs'] ?? ''),
        'retirada_assinatura_image' => $image,
    ];
    try {
        $ok = $pasta->doRetirar($params);
    } catch (\Throwable $e) {
        error_log('[protocolo] retirada_save falhou id=' . $pid . ': ' . $e->getMessage());
        $ok = false;
    }
    if ($ok) {
        $results[] = ['id' => $pid, 'ok' => true, 'codigo' => $pasta->fields['codigo'] ?? ''];
        try {
            global $CFG_GLPI;
            $termIt = $GLOBALS['DB']->request([
                'SELECT' => ['id'],
                'FROM' => 'glpi_plugin_protocolo_termos',
                'WHERE' => ['plugin_protocolo_pastas_id' => $pid, 'tipo' => 'retirada'],
                'ORDER' => 'id DESC',
                'LIMIT' => 1,
            ]);
            foreach ($termIt as $tr) {
                $termos[] = ['pasta_id' => $pid, 'url' => Plugin::getWebDir('protocolo') . '/front/termo.php?id=' . $pid . '&tipo=retirada'];
                break;
            }
        } catch (\Throwable $e) {
        }
    } else {
        $results[] = ['id' => $pid, 'ok' => false, 'error' => 'Falha ao registrar retirada da pasta ' . ($pasta->fields['codigo'] ?? $pid) . '.'];
    }
}

$okCount = count(array_filter($results, fn($r) => !empty($r['ok'])));
pt_retirada_answer(['ok' => $okCount > 0, 'done' => $okCount, 'total' => count($ids), 'results' => $results, 'termos' => $termos]);
