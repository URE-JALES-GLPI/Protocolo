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

// Sanitiza - whitelist estrita: apenas basename, sem path traversal
$rel = trim($rel);
$rel = str_replace('\\', '/', $rel);
// Permite apenas "termos/<arquivo>" ou "<arquivo>" - extrai basename
$basename = basename($rel);
// Valida basename contra whitelist: TR/TE/DOC + extensão permitida
if (!preg_match('/^[A-Za-z0-9._-]+\.(pdf|jpg|jpeg|png)$/i', $basename)) {
    // Se vier no formato termos/TR-...pdf, valida mesmo assim
    if (!preg_match('/^(termos\/)?[A-Z0-9._-]+\.(pdf|jpg|jpeg|png)$/i', $rel)) {
        Html::displayErrorAndDie(__('Nome de arquivo inválido', 'protocolo'));
    }
}
$relBasename = $basename;

$baseDir = rtrim(GLPI_PLUGIN_DOC_DIR, '/') . '/protocolo/termos';
$candidates = [
    $baseDir . '/' . $relBasename,
    GLPI_PLUGIN_DOC_DIR . '/protocolo/' . $relBasename,
    GLPI_ROOT . '/plugins/protocolo/files/termos/' . $relBasename,
];

$found = null;
$realBase = realpath($baseDir);
foreach ($candidates as $c) {
    if (file_exists($c)) {
        $real = realpath($c);
        // Se base existir, garante que o arquivo está dentro dela (previne symlink traversal)
        if ($real && $realBase && strpos($real, $realBase) === 0) {
            $found = $real;
            break;
        } elseif ($real) {
            // Para candidatos fora do baseDir, valida que é dentro de plugins/protocolo
            $realPlugins = realpath(GLPI_ROOT . '/plugins/protocolo');
            if ($realPlugins && strpos($real, $realPlugins) === 0) {
                $found = $real;
                break;
            }
        } else {
            $found = $c;
            break;
        }
    }
}

if (!$found || !file_exists($found)) {
    Html::displayErrorAndDie(__('Arquivo físico não encontrado: ', 'protocolo') . htmlspecialchars($relBasename));
}
// Verificação adicional de permissão: pasta da qual o termo pertence deve ser visível
if ($termo) {
    $pastaCheck = new Pasta();
    if (!$pastaCheck->getFromDB($termo['plugin_protocolo_pastas_id']) || !$pastaCheck->canViewItem()) {
        Html::displayRightError();
    }
}

// Envia
$filename = basename($found);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $found) : mime_content_type($found);
if ($finfo) finfo_close($finfo);
$mime = $mime ?: 'application/octet-stream';
// Força mime correto por extensão
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeMap = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
if (isset($mimeMap[$ext])) $mime = $mimeMap[$ext];

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($found));
readfile($found);
exit;
