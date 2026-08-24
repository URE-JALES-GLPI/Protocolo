<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;
use GlpiPlugin\Protocolo\Escola;

Html::header(__('Protocolo - Dashboard', 'protocolo'), $_SERVER['PHP_SELF'], 'tools', Pasta::class);

if (!Pasta::canView()) {
    Html::displayRightError();
}

global $DB;

// Stats
$totalAguardando = countElementsInTable(Pasta::getTable(), ['status' => 'aguardando', 'is_deleted' => 0]);
$totalRetiradas  = countElementsInTable(Pasta::getTable(), ['status' => 'retirada', 'is_deleted' => 0]);
$totalMes        = 0;
try {
    $iterator = $DB->request([
        'COUNT' => 'cpt',
        'FROM'  => Pasta::getTable(),
        'WHERE' => [
            'is_deleted' => 0,
            new \QueryExpression("MONTH(data_recebimento) = MONTH(NOW()) AND YEAR(data_recebimento) = YEAR(NOW())")
        ]
    ]);
    foreach ($iterator as $row) $totalMes = $row['cpt'];
} catch (Exception $e) { $totalMes = 0; }

$totalEscolas = countElementsInTable(Escola::getTable(), ['is_active' => 1]);

// Pendências de upload: semelhante ao standalone mas adaptado para novo schema
try {
    $pendQuery = "SELECT p.*, e.name AS escola_nome,
        (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='recebimento' ORDER BY id DESC LIMIT 1) AS rec_assinado,
        (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' ORDER BY id DESC LIMIT 1) AS ret_assinado,
        (SELECT id FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' LIMIT 1) AS ret_existe
        FROM glpi_plugin_protocolo_pastas p
        JOIN glpi_plugin_protocolo_escolas e ON e.id=p.plugin_protocolo_escolas_id
        WHERE p.is_deleted=0
        HAVING rec_assinado IS NULL OR (ret_existe IS NOT NULL AND ret_assinado IS NULL) OR (p.status='retirada' AND ret_existe IS NULL)
        ORDER BY p.id DESC LIMIT 10";
    $pendentes = [];
    $res = $DB->doQuery($pendQuery);
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) $pendentes[] = $row;
    }
} catch (Exception $e) { $pendentes = []; }

try {
    $totalPendRec = 0;
    $totalPendRet = 0;
    $res = $DB->doQuery("SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas p WHERE p.is_deleted=0 AND (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='recebimento' ORDER BY id DESC LIMIT 1) IS NULL");
    if ($res && $row = $DB->fetchAssoc($res)) $totalPendRec = $row['cpt'];
    $res = $DB->doQuery("SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas p WHERE p.is_deleted=0 AND p.status='retirada' AND ((SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' ORDER BY id DESC LIMIT 1) IS NULL)");
    if ($res && $row = $DB->fetchAssoc($res)) $totalPendRet = $row['cpt'];
} catch (Exception $e) { $totalPendRec = 0; $totalPendRet = 0; }

// Últimas aguardando
$lastSql = "SELECT p.*, e.name AS escola_nome FROM glpi_plugin_protocolo_pastas p JOIN glpi_plugin_protocolo_escolas e ON e.id=p.plugin_protocolo_escolas_id WHERE p.status='aguardando' AND p.is_deleted=0 ORDER BY p.data_recebimento DESC LIMIT 8";
$lastRows = [];
$res = $DB->doQuery($lastSql);
if ($res) while ($row = $DB->fetchAssoc($res)) $lastRows[] = $row;

echo "<div class='container-fluid'>";
echo "<div class='d-flex justify-content-between align-items-center mb-4'>";
echo "<h4 class='mb-0'><i class='ti ti-dashboard'></i> Dashboard - Protocolo</h4>";
if (Pasta::canCreate()) {
    echo "<a href='" . Pasta::getFormURL() . "' class='btn btn-primary'><i class='ti ti-folder-plus'></i> " . __('Registrar Entrada', 'protocolo') . "</a>";
}
echo "</div>";

echo "<div class='row g-3 mb-4'>";
echo "<div class='col-md-2'><div class='card card-stat shadow-sm border-start border-4 border-warning'><div class='card-body'><div class='text-muted small'>" . __('Aguardando retirada', 'protocolo') . "</div><div class='h3 mb-0'>$totalAguardando</div><a href='" . Pasta::getSearchURL() . "?criteria[0][field]=2&criteria[0][searchtype]=equals&criteria[0][value]=aguardando' class='small'>Ver lista &rarr;</a></div></div></div>";
echo "<div class='col-md-2'><div class='card card-stat shadow-sm border-start border-4 border-success'><div class='card-body'><div class='text-muted small'>" . __('Retiradas', 'protocolo') . "</div><div class='h3 mb-0'>$totalRetiradas</div><a href='" . Pasta::getSearchURL() . "?criteria[0][field]=2&criteria[0][searchtype]=equals&criteria[0][value]=retirada' class='small'>Ver lista &rarr;</a></div></div></div>";
echo "<div class='col-md-2'><div class='card card-stat shadow-sm border-start border-4 border-primary'><div class='card-body'><div class='text-muted small'>" . __('Entradas no mês', 'protocolo') . "</div><div class='h3 mb-0'>$totalMes</div></div></div></div>";
echo "<div class='col-md-2'><div class='card card-stat shadow-sm border-start border-4 border-secondary'><div class='card-body'><div class='text-muted small'>" . __('Escolas ativas', 'protocolo') . "</div><div class='h3 mb-0'>$totalEscolas</div><a href='" . Escola::getSearchURL() . "' class='small'>Gerenciar &rarr;</a></div></div></div>";
echo "<div class='col-md-2'><div class='card card-stat shadow-sm border-start border-4 border-warning'><div class='card-body'><div class='text-muted small'><i class='ti ti-circle-filled text-warning'></i> Pend. Termo Entrega</div><div class='h3 mb-0'>$totalPendRec</div><a href='#pendencias' class='small'>Ver abaixo &rarr;</a></div></div></div>";
echo "<div class='col-md-2'><div class='card card-stat shadow-sm border-start border-4 border-danger'><div class='card-body'><div class='text-muted small'><i class='ti ti-circle-filled text-danger'></i> Pend. Termo Retirada</div><div class='h3 mb-0'>$totalPendRet</div><a href='#pendencias' class='small'>Ver abaixo &rarr;</a></div></div></div>";
echo "</div>";

// Tabela aguardando
echo "<div class='card shadow-sm'><div class='card-header bg-white d-flex justify-content-between align-items-center'><strong><i class='ti ti-clock'></i> " . __('Pastas aguardando retirada (recentes)', 'protocolo') . "</strong><a href='" . Pasta::getSearchURL() . "' class='btn btn-sm btn-outline-primary'>" . __('Ver todas') . "</a></div><div class='table-responsive'><table class='table table-hover align-middle mb-0'><thead><tr><th>" . __('Código') . "</th><th>" . __('Escola') . "</th><th>" . __('Recebido de') . "</th><th>" . __('Data') . "</th><th>" . __('Itens') . "</th><th>" . __('Status') . "</th><th></th></tr></thead><tbody>";
if ($lastRows) {
    foreach ($lastRows as $r) {
        $qtdRes = $DB->doQuery("SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_itens WHERE plugin_protocolo_pastas_id=" . (int)$r['id']);
        $itens = 0;
        if ($qtdRes && $row = $DB->fetchAssoc($qtdRes)) $itens = $row['cpt'];
        echo "<tr><td class='fw-bold'>" . htmlspecialchars($r['codigo']) . "</td><td>" . htmlspecialchars($r['escola_nome']) . "</td><td>" . htmlspecialchars($r['recebido_de']) . "</td><td>" . Html::convDateTime($r['data_recebimento']) . "</td><td><span class='badge bg-light text-dark border'>$itens item(s)</span></td><td>" . Pasta::getStatusBadge($r['status']) . "</td><td><a href='" . Pasta::getFormURLWithID($r['id']) . "' class='btn btn-sm btn-outline-primary'><i class='ti ti-eye'></i> Ver</a></td></tr>";
    }
} else {
    echo "<tr><td colspan='7' class='text-center text-muted py-4'>" . __('Nenhuma pasta aguardando no momento.', 'protocolo') . "</td></tr>";
}
echo "</tbody></table></div></div>";

// Pendências
echo "<div id='pendencias' class='card shadow-sm mt-4'><div class='card-header bg-white d-flex justify-content-between align-items-center'><strong><i class='ti ti-alert-triangle'></i> " . __('Pendências de upload de termos', 'protocolo') . "</strong><span class='small text-muted'><i class='ti ti-circle-filled text-warning'></i> Entrega &nbsp; <i class='ti ti-circle-filled text-danger'></i> Retirada</span></div><div class='table-responsive'><table class='table table-hover align-middle mb-0'><thead><tr><th>" . __('Código') . "</th><th>" . __('Escola') . "</th><th>" . __('Status') . "</th><th>" . __('Pendência') . "</th><th></th></tr></thead><tbody>";
if ($pendentes) {
    foreach ($pendentes as $r) {
        $amarelo = empty($r['rec_assinado']);
        $vermelho = (!empty($r['ret_existe']) && empty($r['ret_assinado'])) || ($r['status'] === 'retirada' && empty($r['ret_existe']));
        echo "<tr><td class='fw-bold'>" . htmlspecialchars($r['codigo']) . "</td><td>" . htmlspecialchars($r['escola_nome']) . "</td><td>" . Pasta::getStatusBadge($r['status']) . "</td><td>";
        if ($amarelo) echo "<span class='badge bg-warning text-dark'><i class='ti ti-circle-filled'></i> Entrega</span> ";
        if ($vermelho) echo "<span class='badge bg-danger'><i class='ti ti-circle-filled'></i> Retirada</span> ";
        if (!$amarelo && !$vermelho) echo "<span class='badge bg-success'>OK</span>";
        echo "</td><td><a href='" . Pasta::getFormURLWithID($r['id']) . "' class='btn btn-sm btn-outline-primary'><i class='ti ti-upload'></i> Resolver</a></td></tr>";
    }
} else {
    echo "<tr><td colspan='5' class='text-center text-success py-3'><i class='ti ti-check'></i> " . __('Nenhuma pendência! Todos os termos com upload em dia.', 'protocolo') . "</td></tr>";
}
echo "</tbody></table></div></div>";

echo "<div class='alert alert-info mt-4'><strong>" . __('Fluxo do sistema:', 'protocolo') . "</strong> 1) " . __('Alguém deixa a pasta → Registrar Entrada → imprime Termo de Recebimento → assina e digitaliza (upload).', 'protocolo') . "<br>2) " . __('Escola vem buscar → abrir pasta → Registrar Retirada → imprime Termo de Entrega/Retirada → assina e digitaliza.', 'protocolo') . "</div>";

echo "</div>";

Html::footer();
