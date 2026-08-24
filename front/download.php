<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

use GlpiPlugin\Protocolo\Pasta;

if (!Pasta::canView()) {
    Html::displayRightError();
}

$termoId = (int)($_GET['id'] ?? 0);
$path = $_GET['file'] ?? '';

if (!$termoId && !$path) {
    Html::displayErrorAndDie(__('Arquivo não encontrado', 'protocolo'));
}

// Busca termo se passou id
global $DB;
$termo = null;
if ($termoId) {
    $it = $DB->request(['FROM' => 'glpi_plugin_protocolo_termos', 'WHERE' => ['id' => $termoId], 'LIMIT' => 1]);
    foreach ($it as $row) $termo = $row;
    if (!$termo || empty($termo['arquivo_assinado'])) {
        Html::displayErrorAndDie(__('Arquivo assinado não encontrado', 'protocolo'));
    }
    $rel = $termo['arquivo_assinado']; // ex: termos/TR-...pdf
} else {
    $rel = $path;
}

// Sanitiza
$rel = str_replace(['..', '\\'], '', $rel);
$rel = ltrim($rel, '/');

$candidates = [
    GLPI_PLUGIN_DOC_DIR . '/protocolo/' . $rel,
    GLPI_PLUGIN_DOC_DIR . '/protocolo/termos/' . basename($rel),
    GLPI_ROOT . '/plugins/protocolo/files/termos/' . basename($rel),
    GLPI_ROOT . '/plugins/protocolo/uploads/termos/' . basename($rel),
];

$found = null;
foreach ($candidates as $c) {
    if (file_exists($c)) { $found = $c; break; }
}

if (!$found) {
    Html::displayErrorAndDie(__('Arquivo físico não encontrado: ', 'protocolo') . htmlspecialchars($rel));
}

// Envia
$filename = basename($found);
$mime = mime_content_type($found) ?: 'application/octet-stream';
if (str_ends_with(strtolower($filename), '.pdf')) $mime = 'application/pdf';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($found));
readfile($found);
exit;
