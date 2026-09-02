<?php
/**
 * front/verify.php - Verificação pública de autenticidade do Termo (QR Code)
 * Acesso sem login necessário para verificação externa
 */
define('GLPI_ROOT', dirname(__DIR__, 3) . '/..');
include(GLPI_ROOT . "/inc/includes.php");

use GlpiPlugin\Protocolo\Pasta;
use GlpiPlugin\Protocolo\Escola;

global $DB, $CFG_GLPI;

$codigo = trim($_GET['codigo'] ?? $_GET['c'] ?? '');
$hash = trim($_GET['hash'] ?? $_GET['h'] ?? '');

$found = null;
$pasta = null;
$escola = null;
$termo = null;

if ($codigo !== '') {
    try {
        $where = ['codigo' => $codigo];
        if ($hash !== '') $where['hash_verificacao'] = $hash;
        $it = $DB->request(['FROM' => 'glpi_plugin_protocolo_termos', 'WHERE' => $where, 'LIMIT' => 1]);
        foreach ($it as $row) $termo = $row;
        if ($termo) {
            $p = new Pasta();
            if ($p->getFromDB($termo['plugin_protocolo_pastas_id'])) {
                $pasta = $p;
                $e = new Escola();
                if ($e->getFromDB($pasta->fields['plugin_protocolo_escolas_id'])) $escola = $e;
            }
            $found = true;
        } else {
            $found = false;
        }
    } catch (Throwable $e) {
        $found = false;
    }
}

// Se logado, usa header GLPI, senão html simples
$isLogged = Session::getLoginUserID();
if ($isLogged) {
    Html::header(__('Verificação de Termo', 'protocolo'), $_SERVER['PHP_SELF'], 'tools', Pasta::class);
} else {
    echo "<!doctype html><html lang='pt-br'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>Verificação - Protocolo</title>";
    echo "<link href='" . $CFG_GLPI['root_doc'] . "/public/lib/bootstrap/css/bootstrap.min.css' rel='stylesheet'>";
    echo "<style>body{background:#eef2f7;}</style></head><body class='p-4'>";
    echo "<div class='container' style='max-width:700px'>";
}

if ($codigo === '') {
    echo "<div class='card shadow-sm'><div class='card-body text-center py-5'>";
    echo "<h4><i class='ti ti-qrcode'></i> Verificação de Termo</h4>";
    echo "<p class='text-muted'>Informe o código e hash para verificar a autenticidade.</p>";
    echo "<form method='get' class='row g-2 justify-content-center'><div class='col-md-4'><input name='codigo' class='form-control' placeholder='Código (TR-...)' required></div><div class='col-md-4'><input name='hash' class='form-control' placeholder='Hash'></div><div class='col-md-2'><button class='btn btn-primary w-100'>Verificar</button></div></form>";
    echo "</div></div>";
} elseif ($found) {
    echo "<div class='card shadow-sm border-success'><div class='card-header bg-success text-white text-center'><h4 class='mb-0'><i class='ti ti-shield-check'></i> Termo Autêntico</h4></div><div class='card-body'>";
    echo "<p class='text-center'><span class='badge bg-success fs-6'><i class='ti ti-check'></i> Válido</span> <code class='ms-2'>" . htmlspecialchars($termo['codigo']) . "</code></p>";
    echo "<table class='table table-bordered'>";
    echo "<tr><th>Pasta</th><td><a href='" . Pasta::getFormURLWithID($pasta->fields['id']) . "'>" . htmlspecialchars($pasta->fields['codigo']) . "</a> " . Pasta::getStatusBadge($pasta->fields['status']) . "</td></tr>";
    echo "<tr><th>Escola</th><td>" . htmlspecialchars($escola ? $escola->fields['name'] : '-') . "</td></tr>";
    echo "<tr><th>Tipo</th><td><span class='badge " . ($termo['tipo']==='recebimento'?'bg-primary':'bg-success') . "'>" . htmlspecialchars(strtoupper($termo['tipo'])) . "</span></td></tr>";
    echo "<tr><th>Hash</th><td><code>" . htmlspecialchars($termo['hash_verificacao']) . "</code></td></tr>";
    echo "<tr><th>Gerado em</th><td>" . Html::convDateTime($termo['date_creation']) . " por " . htmlspecialchars(getUserName($termo['users_id'] ?? 0)) . "</td></tr>";
    if (!empty($termo['arquivo_assinado'])) {
        $dl = Plugin::getWebDir('protocolo') . "/front/download.php?id=" . (int)$termo['id'];
        echo "<tr><th>Assinado</th><td><a href='$dl' class='btn btn-sm btn-success'><i class='ti ti-file'></i> Baixar assinado</a> <small class='text-muted'>" . htmlspecialchars($termo['arquivo_assinado']) . "</small></td></tr>";
    } else {
        echo "<tr><th>Assinado</th><td><span class='badge bg-warning text-dark'>Sem arquivo assinado</span></td></tr>";
    }
    echo "</table>";
    echo "<div class='alert alert-info small mb-0'><i class='ti ti-info-circle'></i> Este termo foi gerado pelo Sistema de Protocolo e consta na base do GLPI. Em caso de dúvida, contate o setor de protocolo.</div>";
    echo "</div></div>";
} else {
    echo "<div class='card shadow-sm border-danger'><div class='card-header bg-danger text-white text-center'><h4 class='mb-0'><i class='ti ti-shield-x'></i> Termo NÃO encontrado</h4></div><div class='card-body text-center py-4'>";
    echo "<p><code>" . htmlspecialchars($codigo) . "</code> <small class='text-muted'>hash " . htmlspecialchars(substr($hash,0,12)) . "...</small></p>";
    echo "<p class='text-muted'>Código ou hash não confere. Verifique se digitou corretamente ou se o termo foi gerado em outro sistema.</p>";
    echo "<a href='verify.php' class='btn btn-outline-secondary'>Tentar novamente</a>";
    echo "</div></div>";
}

if ($isLogged) {
    Html::footer();
} else {
    echo "</div></body></html>";
}
