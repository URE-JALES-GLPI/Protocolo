<?php
/**
 * Endpoint do Assignat-ure (integração "Genérica").
 *
 * GET  /plugins/protocolo/front/assignature.php?list=pending&limit=500&offset=0
 * GET  /plugins/protocolo/front/assignature.php?id=entrada:123  (ou retirada:123)
 * POST /plugins/protocolo/front/assignature.php  {"action":"signature_completed",...}
 *
 * Autenticação: cabeçalho X-Assignature-Token (token em Configuração do Protocolo).
 */
include('../../../inc/includes.php');
require_once __DIR__ . '/../src/compat.php';

use GlpiPlugin\Protocolo\Assignature;

function pt_assignature_json(array $payload, int $code = 200): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    pt_assignature_json(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

$provided = (string) ($_SERVER['HTTP_X_ASSIGNATURE_TOKEN'] ?? '');
if (!Assignature::checkToken($provided)) {
    pt_assignature_json(['ok' => false, 'error' => 'Token inválido.'], 401);
}

try {
    if ($method === 'POST') {
        $body = file_get_contents('php://input', false, null, 0, 16_385);
        $data = json_decode((string) $body, true);
        if (!is_array($data) || (string) ($data['action'] ?? '') !== 'signature_completed') {
            pt_assignature_json(['ok' => false, 'error' => 'Dados inválidos.'], 400);
        }
        try {
            Assignature::complete(
                (string) ($data['id'] ?? ''),
                (string) ($data['signer_name'] ?? ''),
                (string) ($data['signed_at'] ?? ''),
                (string) ($data['signature_sha256'] ?? '')
            );
        } catch (RuntimeException $e) {
            pt_assignature_json(['ok' => false, 'error' => $e->getMessage()], 409);
        }
        pt_assignature_json(['ok' => true, 'data' => ['completed' => true]]);
    }

    if (strtolower(trim((string) ($_GET['list'] ?? ''))) === 'pending') {
        $limit = max(1, min(500, (int) ($_GET['limit'] ?? 500)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        $requests = Assignature::listPending($limit, $offset);
        pt_assignature_json(['ok' => true, 'data' => ['requests' => $requests, 'count' => count($requests)]]);
    }

    [$kind, $pastaId] = Assignature::parseRef((string) ($_GET['id'] ?? ''));
    $row = Assignature::get($pastaId);
    if ($row === null) {
        pt_assignature_json(['ok' => false, 'error' => 'Pasta não encontrada.'], 404);
    }
    pt_assignature_json(['ok' => true, 'data' => Assignature::payload($row, $kind)]);
} catch (Throwable $e) {
    error_log('[protocolo] endpoint Assignat-ure: ' . $e->getMessage());
    pt_assignature_json(['ok' => false, 'error' => 'Erro interno no Protocolo.'], 500);
}
