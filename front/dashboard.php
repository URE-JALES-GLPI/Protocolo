<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;
use GlpiPlugin\Protocolo\Escola;
use GlpiPlugin\Protocolo\Config;

Html::header(__('Protocolo - Dashboard', 'protocolo'), $_SERVER['PHP_SELF'], 'tools', Pasta::class);

if (!Pasta::canView()) {
    Html::displayRightError();
}

global $DB;

// Config
$cfg = Config::getAll();
$prazoAlerta = Config::getPrazoAlertaDias();
$alertaAtivo = Config::isAlertaAtivo();
$graficosAtivo = Config::isGraficosAtivo();

// Entidades ativas para filtro (segurança + performance - evita full scan de outras entidades)
$activeEntities = $_SESSION['glpiactiveentities'] ?? ($_SESSION['glpiactive_entity'] ? [$_SESSION['glpiactive_entity']] : []);
if (!is_array($activeEntities)) $activeEntities = [$activeEntities];
$activeEntities = array_map('intval', $activeEntities);
$hasAllEntities = false;
try { $hasAllEntities = method_exists(Session::class, 'haveAccessToAllEntities') && Session::haveAccessToAllEntities(); } catch (Throwable $e) { $hasAllEntities = false; }
$entityFilter = [];
if (!$hasAllEntities && !empty($activeEntities) && !in_array(0, $activeEntities)) {
    $entityFilter = ['entities_id' => $activeEntities];
}
$entityWhereSql = '';
if (!empty($entityFilter)) {
    $entityWhereSql = " AND p.entities_id IN (" . implode(',', $activeEntities) . ")";
}
$entityWhereSqlPasta = str_replace('p.entities_id', 'entities_id', $entityWhereSql);
if ($entityWhereSqlPasta === $entityWhereSql) {
    // fallback for simple queries without alias p
    $entityWhereSqlPasta = '';
    if (!empty($entityFilter)) {
        $entityWhereSqlPasta = " AND entities_id IN (" . implode(',', $activeEntities) . ")";
    }
}

// Stats - com filtro de entidade
$totalAguardando = countElementsInTable(Pasta::getTable(), array_merge(['status' => 'aguardando', 'is_deleted' => 0], $entityFilter));
$totalRetiradas  = countElementsInTable(Pasta::getTable(), array_merge(['status' => 'retirada', 'is_deleted' => 0], $entityFilter));
$totalCanceladas = countElementsInTable(Pasta::getTable(), array_merge(['status' => 'cancelada', 'is_deleted' => 0], $entityFilter));
$totalMes        = 0;
try {
    $whereMes = array_merge(['is_deleted' => 0, new \QueryExpression("MONTH(data_recebimento) = MONTH(NOW()) AND YEAR(data_recebimento) = YEAR(NOW())")], $entityFilter);
    $iterator = $DB->request([
        'COUNT' => 'cpt',
        'FROM'  => Pasta::getTable(),
        'WHERE' => $whereMes
    ]);
    foreach ($iterator as $row) $totalMes = $row['cpt'];
} catch (Exception $e) { $totalMes = 0; }

// Atrasadas (> prazo)
$totalAtrasadas = 0;
if ($alertaAtivo) {
    try {
        $sql = "SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas p WHERE p.status='aguardando' AND p.is_deleted=0 $entityWhereSql AND DATEDIFF(NOW(), p.data_recebimento) >= " . (int)$prazoAlerta;
        $res = $DB->doQuery($sql);
        if ($res && $row = $DB->fetchAssoc($res)) $totalAtrasadas = (int)$row['cpt'];
    } catch (Throwable $e) { $totalAtrasadas = 0; }
}

$totalEscolas = 0;
try {
    // Conta apenas escolas visíveis na entidade ativa (usa entities se possível)
    if (!$hasAllEntities && !empty($activeEntities)) {
        $totalEscolas = countElementsInTable('glpi_entities', ['id' => $activeEntities]);
    } else {
        $totalEscolas = countElementsInTable('glpi_entities', ['id' => ['>', 0]]);
    }
} catch (Throwable $e) {
    $totalEscolas = countElementsInTable(Escola::getTable(), array_merge(['is_active' => 1], $entityFilter));
}

// Pendências de upload: ESCOLA = ENTIDADE - com filtro de entidade para performance
try {
    $pendQuery = "SELECT p.*, COALESCE(e.completename, oe.name) AS escola_nome,
        (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='recebimento' ORDER BY id DESC LIMIT 1) AS rec_assinado,
        (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' ORDER BY id DESC LIMIT 1) AS ret_assinado,
        (SELECT id FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' LIMIT 1) AS ret_existe
        FROM glpi_plugin_protocolo_pastas p
        LEFT JOIN glpi_entities e ON e.id=p.plugin_protocolo_escolas_id
        LEFT JOIN glpi_plugin_protocolo_escolas oe ON oe.id=p.plugin_protocolo_escolas_id
        WHERE p.is_deleted=0 $entityWhereSql
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
    $res = $DB->doQuery("SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas p WHERE p.is_deleted=0 $entityWhereSql AND (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='recebimento' ORDER BY id DESC LIMIT 1) IS NULL");
    if ($res && $row = $DB->fetchAssoc($res)) $totalPendRec = $row['cpt'];
    $res = $DB->doQuery("SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas p WHERE p.is_deleted=0 AND p.status='retirada' $entityWhereSql AND ((SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' ORDER BY id DESC LIMIT 1) IS NULL)");
    if ($res && $row = $DB->fetchAssoc($res)) $totalPendRet = $row['cpt'];
} catch (Exception $e) { $totalPendRec = 0; $totalPendRet = 0; }

// Últimas aguardando — ESCOLA = ENTIDADE - com JOIN agregado para evitar N+1 + dias
$lastSql = "SELECT p.*, COALESCE(e.completename, oe.name) AS escola_nome, COALESCE(ic.cpt,0) AS itens_qtd, DATEDIFF(NOW(), p.data_recebimento) AS dias_parada FROM glpi_plugin_protocolo_pastas p LEFT JOIN glpi_entities e ON e.id=p.plugin_protocolo_escolas_id LEFT JOIN glpi_plugin_protocolo_escolas oe ON oe.id=p.plugin_protocolo_escolas_id LEFT JOIN (SELECT plugin_protocolo_pastas_id, COUNT(*) AS cpt FROM glpi_plugin_protocolo_itens GROUP BY plugin_protocolo_pastas_id) ic ON ic.plugin_protocolo_pastas_id=p.id WHERE p.status='aguardando' AND p.is_deleted=0 $entityWhereSql ORDER BY p.data_recebimento ASC LIMIT 12";
$lastRows = [];
$res = $DB->doQuery($lastSql);
if ($res) while ($row = $DB->fetchAssoc($res)) $lastRows[] = $row;

// Atrasadas detalhadas (para tabela separada)
$atrasadasRows = [];
if ($alertaAtivo && $totalAtrasadas > 0) {
    try {
        $sqlAtras = "SELECT p.*, COALESCE(e.completename, oe.name) AS escola_nome, DATEDIFF(NOW(), p.data_recebimento) AS dias_parada FROM glpi_plugin_protocolo_pastas p LEFT JOIN glpi_entities e ON e.id=p.plugin_protocolo_escolas_id LEFT JOIN glpi_plugin_protocolo_escolas oe ON oe.id=p.plugin_protocolo_escolas_id WHERE p.status='aguardando' AND p.is_deleted=0 $entityWhereSql AND DATEDIFF(NOW(), p.data_recebimento) >= " . (int)$prazoAlerta . " ORDER BY p.data_recebimento ASC LIMIT 10";
        $res = $DB->doQuery($sqlAtras);
        if ($res) while ($row = $DB->fetchAssoc($res)) $atrasadasRows[] = $row;
    } catch (Throwable $e) {}
}

// Dados gráficos
$chartEntradas = ['labels' => [], 'values' => []];
$chartStatus = ['labels' => [__('Aguardando'), __('Retirada'), __('Cancelada')], 'values' => [$totalAguardando, $totalRetiradas, $totalCanceladas]];
$chartTempoMedio = ['labels' => [], 'values' => []];
$tempoMedioGeral = 0;

if ($graficosAtivo) {
    // Entradas últimos 6 meses
    try {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts = strtotime("-$i months");
            $ym = date('Y-m', $ts);
            $label = date('m/y', $ts);
            $months[$ym] = ['label' => $label, 'cpt' => 0];
        }
        $sql = "SELECT DATE_FORMAT(data_recebimento, '%Y-%m') as ym, COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas WHERE is_deleted=0 $entityWhereSqlPasta AND data_recebimento >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym";
        $res = $DB->doQuery($sql);
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $ym = $row['ym'];
                if (isset($months[$ym])) $months[$ym]['cpt'] = (int)$row['cpt'];
            }
        }
        foreach ($months as $m) {
            $chartEntradas['labels'][] = $m['label'];
            $chartEntradas['values'][] = $m['cpt'];
        }
    } catch (Throwable $e) {
        $chartEntradas = ['labels' => [], 'values' => []];
    }

    // Tempo médio geral e por mês (últimos 6 meses por data_retirada)
    try {
        $sql = "SELECT AVG(DATEDIFF(data_retirada, data_recebimento)) as media FROM glpi_plugin_protocolo_pastas WHERE status='retirada' AND is_deleted=0 AND data_retirada IS NOT NULL $entityWhereSqlPasta";
        $res = $DB->doQuery($sql);
        if ($res && $row = $DB->fetchAssoc($res)) $tempoMedioGeral = round((float)$row['media'], 1);
    } catch (Throwable $e) { $tempoMedioGeral = 0; }

    try {
        $months2 = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts = strtotime("-$i months");
            $ym = date('Y-m', $ts);
            $label = date('m/y', $ts);
            $months2[$ym] = ['label' => $label, 'media' => 0];
        }
        $sql = "SELECT DATE_FORMAT(data_retirada, '%Y-%m') as ym, AVG(DATEDIFF(data_retirada, data_recebimento)) as media FROM glpi_plugin_protocolo_pastas WHERE status='retirada' AND is_deleted=0 AND data_retirada IS NOT NULL $entityWhereSqlPasta AND data_retirada >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym";
        $res = $DB->doQuery($sql);
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $ym = $row['ym'];
                if (isset($months2[$ym])) $months2[$ym]['media'] = round((float)$row['media'], 1);
            }
        }
        foreach ($months2 as $m) {
            $chartTempoMedio['labels'][] = $m['label'];
            $chartTempoMedio['values'][] = $m['media'];
        }
    } catch (Throwable $e) { $chartTempoMedio = ['labels' => [], 'values' => []]; }
}

echo "<div class='container-fluid'>";
echo "<div class='d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2'>";
echo "<h4 class='mb-0'><i class='ti ti-dashboard'></i> Dashboard - Protocolo</h4>";
echo "<div class='d-flex gap-2'>";
if ($alertaAtivo && $totalAtrasadas > 0) {
    echo "<a href='#atrasadas' class='btn btn-danger'><i class='ti ti-alert-triangle'></i> " . __('Atrasadas', 'protocolo') . " ($totalAtrasadas)</a>";
}
if (Pasta::canCreate()) {
    echo "<a href='" . Pasta::getFormURL() . "' class='btn btn-primary'><i class='ti ti-folder-plus'></i> " . __('Registrar Entrada', 'protocolo') . "</a>";
}
if (Config::canEdit()) {
    echo "<a href='" . Plugin::getWebDir('protocolo') . "/front/config.php' class='btn btn-outline-secondary'><i class='ti ti-settings'></i> Config</a>";
}
echo "</div>";
echo "</div>";

if ($alertaAtivo && $totalAtrasadas > 0) {
    echo "<div class='alert alert-danger d-flex justify-content-between align-items-center'><div><i class='ti ti-alert-triangle'></i> <strong>$totalAtrasadas " . __('pasta(s) aguardando há mais de', 'protocolo') . " $prazoAlerta " . __('dias', 'protocolo') . "</strong> — " . __('regularize a retirada ou contate a escola.', 'protocolo') . "</div><a href='#atrasadas' class='btn btn-sm btn-light'>" . __('Ver atrasadas', 'protocolo') . "</a></div>";
}

echo "<div class='row g-3 mb-4 justify-content-center'>";
echo "<div class='col-md-2 col-sm-6 d-flex'><div class='card card-stat shadow-sm border-start border-4 border-warning h-100 w-100'><div class='card-body d-flex flex-column'><div class='text-muted small'>" . __('Aguardando retirada', 'protocolo') . "</div><div class='h3 mb-0'>$totalAguardando</div><a href='" . Pasta::getSearchURL() . "?criteria[0][field]=2&criteria[0][searchtype]=equals&criteria[0][value]=aguardando' class='small mt-auto'>Ver lista &rarr;</a></div></div></div>";
if ($alertaAtivo) {
    $cls = $totalAtrasadas > 0 ? 'border-danger bg-danger bg-opacity-10' : 'border-secondary';
    $txtCls = $totalAtrasadas > 0 ? 'text-danger' : 'text-muted';
    echo "<div class='col-md-2 col-sm-6 d-flex'><div class='card card-stat shadow-sm border-start border-4 $cls h-100 w-100'><div class='card-body d-flex flex-column'><div class='small $txtCls'><i class='ti ti-alarm'></i> " . __('Atrasadas', 'protocolo') . " (&gt;{$prazoAlerta}d)</div><div class='h3 mb-0 " . ($totalAtrasadas>0?'text-danger':'') . "'>$totalAtrasadas</div><a href='#atrasadas' class='small mt-auto'>" . __('Ver atrasadas', 'protocolo') . " &rarr;</a></div></div></div>";
}
echo "<div class='col-md-2 col-sm-6 d-flex'><div class='card card-stat shadow-sm border-start border-4 border-success h-100 w-100'><div class='card-body d-flex flex-column'><div class='text-muted small'>" . __('Retiradas', 'protocolo') . "</div><div class='h3 mb-0'>$totalRetiradas</div><a href='" . Pasta::getSearchURL() . "?criteria[0][field]=2&criteria[0][searchtype]=equals&criteria[0][value]=retirada' class='small mt-auto'>Ver lista &rarr;</a></div></div></div>";
echo "<div class='col-md-2 col-sm-6 d-flex'><div class='card card-stat shadow-sm border-start border-4 border-primary h-100 w-100'><div class='card-body d-flex flex-column'><div class='text-muted small'>" . __('Entradas no mês', 'protocolo') . "</div><div class='h3 mb-0'>$totalMes</div><a href='" . Pasta::getSearchURL() . "' class='small mt-auto invisible'>Ver lista &rarr;</a></div></div></div>";
echo "<div class='col-md-2 col-sm-6 d-flex'><div class='card card-stat shadow-sm border-start border-4 border-warning h-100 w-100'><div class='card-body d-flex flex-column'><div class='text-muted small'><i class='ti ti-circle-filled text-warning'></i> Pend. Termo Entrega</div><div class='h3 mb-0'>$totalPendRec</div><a href='#pendencias' class='small mt-auto'>Ver abaixo &rarr;</a></div></div></div>";
echo "<div class='col-md-2 col-sm-6 d-flex'><div class='card card-stat shadow-sm border-start border-4 border-danger h-100 w-100'><div class='card-body d-flex flex-column'><div class='text-muted small'><i class='ti ti-circle-filled text-danger'></i> Pend. Termo Retirada</div><div class='h3 mb-0'>$totalPendRet</div><a href='#pendencias' class='small mt-auto'>Ver abaixo &rarr;</a></div></div></div>";
echo "</div>";

// Gráficos
if ($graficosAtivo) {
    echo "<div class='row g-3 mb-4'>";
    echo "<div class='col-lg-5'><div class='card shadow-sm h-100'><div class='card-header bg-white'><strong><i class='ti ti-chart-bar'></i> " . __('Entradas por mês (últimos 6 meses)', 'protocolo') . "</strong></div><div class='card-body'><canvas id='chartEntradas' height='200'></canvas></div></div></div>";
    echo "<div class='col-lg-3'><div class='card shadow-sm h-100'><div class='card-header bg-white'><strong><i class='ti ti-chart-pie'></i> " . __('Por status', 'protocolo') . "</strong></div><div class='card-body d-flex align-items-center justify-content-center'><canvas id='chartStatus' height='200'></canvas></div></div></div>";
    echo "<div class='col-lg-4'><div class='card shadow-sm h-100'><div class='card-header bg-white d-flex justify-content-between align-items-center'><strong><i class='ti ti-clock'></i> " . __('Tempo médio de guarda (dias)', 'protocolo') . "</strong><span class='badge bg-primary'>Média geral: " . ($tempoMedioGeral ?: '—') . "d</span></div><div class='card-body'><canvas id='chartTempo' height='200'></canvas><small class='text-muted d-block mt-2'>" . __('Média entre recebimento e retirada por mês de retirada.', 'protocolo') . "</small></div></div></div>";
    echo "</div>";
}

// Tabela aguardando
echo "<div class='card shadow-sm'><div class='card-header bg-white d-flex justify-content-between align-items-center'><strong><i class='ti ti-clock'></i> " . __('Pastas aguardando retirada (recentes)', 'protocolo') . "</strong><a href='" . Pasta::getSearchURL() . "' class='btn btn-sm btn-outline-primary'>" . __('Ver todas') . "</a></div><div class='table-responsive'><table class='table table-hover align-middle mb-0'><thead><tr><th>" . __('Código') . "</th><th>" . __('Escola') . "</th><th>" . __('Recebido de') . "</th><th>" . __('Data') . "</th><th>" . __('Dias parada', 'protocolo') . "</th><th>" . __('Itens') . "</th><th>" . __('Status') . "</th><th></th></tr></thead><tbody>";
if ($lastRows) {
    foreach ($lastRows as $r) {
        $itens = (int)($r['itens_qtd'] ?? 0);
        $dias = (int)($r['dias_parada'] ?? 0);
        $isAtrasada = $alertaAtivo && $dias >= $prazoAlerta;
        $isAtencao = $alertaAtivo && !$isAtrasada && $dias >= max(1, $prazoAlerta - 5);
        $rowCls = $isAtrasada ? "table-danger" : ($isAtencao ? "table-warning" : "");
        $badgeDias = $isAtrasada ? "<span class='badge bg-danger'><i class='ti ti-alert-triangle'></i> $dias d</span>" : ($isAtencao ? "<span class='badge bg-warning text-dark'>$dias d</span>" : "<span class='badge bg-light text-dark border'>$dias d</span>");
        echo "<tr class='$rowCls'><td class='fw-bold'>" . htmlspecialchars($r['codigo']) . "</td><td>" . htmlspecialchars($r['escola_nome']) . "</td><td>" . htmlspecialchars($r['recebido_de']) . "</td><td>" . Html::convDateTime($r['data_recebimento']) . "</td><td>$badgeDias</td><td><span class='badge bg-light text-dark border'>$itens item(s)</span></td><td>" . Pasta::getStatusBadge($r['status']) . "</td><td><a href='" . Pasta::getFormURLWithID($r['id']) . "' class='btn btn-sm " . ($isAtrasada ? "btn-danger" : "btn-outline-primary") . "'><i class='ti ti-eye'></i> Ver</a></td></tr>";
    }
} else {
    echo "<tr><td colspan='8' class='text-center text-muted py-4'>" . __('Nenhuma pasta aguardando no momento.', 'protocolo') . "</td></tr>";
}
echo "</tbody></table></div></div>";

// Tabela atrasadas
if ($alertaAtivo && $totalAtrasadas > 0) {
    echo "<div id='atrasadas' class='card shadow-sm mt-4 border-danger'><div class='card-header bg-danger text-white d-flex justify-content-between align-items-center'><strong><i class='ti ti-alarm'></i> " . __('Pastas atrasadas', 'protocolo') . " — " . __('aguardando há mais de', 'protocolo') . " $prazoAlerta " . __('dias', 'protocolo') . " ($totalAtrasadas)</strong><a href='" . Pasta::getSearchURL() . "?criteria[0][field]=2&criteria[0][searchtype]=equals&criteria[0][value]=aguardando' class='btn btn-sm btn-light'>" . __('Ver todas aguardando', 'protocolo') . "</a></div><div class='table-responsive'><table class='table table-hover align-middle mb-0'><thead><tr><th>" . __('Código') . "</th><th>" . __('Escola') . "</th><th>" . __('Recebido de') . "</th><th>" . __('Data') . "</th><th>" . __('Dias', 'protocolo') . "</th><th></th></tr></thead><tbody>";
    foreach ($atrasadasRows as $r) {
        $dias = (int)($r['dias_parada'] ?? 0);
        echo "<tr class='table-danger'><td class='fw-bold'>" . htmlspecialchars($r['codigo']) . "</td><td>" . htmlspecialchars($r['escola_nome']) . "</td><td>" . htmlspecialchars($r['recebido_de']) . "</td><td>" . Html::convDateTime($r['data_recebimento']) . "</td><td><span class='badge bg-danger'>$dias d</span></td><td><a href='" . Pasta::getFormURLWithID($r['id']) . "' class='btn btn-sm btn-danger'><i class='ti ti-alert-triangle'></i> Regularizar</a></td></tr>";
    }
    echo "</tbody></table></div></div>";
    echo "<div class='form-text mt-1 text-muted small'><i class='ti ti-settings'></i> " . __('Ajuste o prazo em', 'protocolo') . " <a href='" . Plugin::getWebDir('protocolo') . "/front/config.php'>Configuração → Prazo alerta</a>.</div>";
}

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

// Charts JS
if ($graficosAtivo) {
    $jsonEntradasLabels = json_encode($chartEntradas['labels'], JSON_UNESCAPED_UNICODE);
    $jsonEntradasValues = json_encode($chartEntradas['values']);
    $jsonStatusLabels = json_encode($chartStatus['labels'], JSON_UNESCAPED_UNICODE);
    $jsonStatusValues = json_encode($chartStatus['values']);
    $jsonTempoLabels = json_encode($chartTempoMedio['labels'], JSON_UNESCAPED_UNICODE);
    $jsonTempoValues = json_encode($chartTempoMedio['values']);

    // Tenta usar Chart.js do GLPI se existir, senão CDN
    $chartJsCdn = "https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js";
    $chartJsLocal = $CFG_GLPI['root_doc'] . "/public/lib/chart.js/dist/chart.umd.js";
    echo <<<HTML
<script src="$chartJsCdn" onerror="this.onerror=null;this.src='$chartJsLocal'"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  if (typeof Chart === 'undefined') return;
  Chart.defaults.font.family = "Inter, system-ui, sans-serif";
  Chart.defaults.color = "#6c757d";

  const c1 = document.getElementById('chartEntradas');
  if (c1) {
    new Chart(c1, {
      type: 'bar',
      data: { labels: $jsonEntradasLabels, datasets: [{ label: 'Entradas', data: $jsonEntradasValues, backgroundColor: '#0d6efd', borderRadius: 4 }] },
      options: { responsive: true, plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label: ctx => ctx.parsed.y + ' pasta(s)' } } }, scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } } }
    });
  }
  const c2 = document.getElementById('chartStatus');
  if (c2) {
    new Chart(c2, {
      type: 'doughnut',
      data: { labels: $jsonStatusLabels, datasets: [{ data: $jsonStatusValues, backgroundColor:['#ffc107','#198754','#6c757d'], borderWidth:0 }] },
      options: { responsive:true, plugins:{ legend:{ position:'bottom' } }, cutout:'58%' }
    });
  }
  const c3 = document.getElementById('chartTempo');
  if (c3) {
    new Chart(c3, {
      type: 'line',
      data: { labels: $jsonTempoLabels, datasets: [{ label:'Dias médios', data: $jsonTempoValues, borderColor:'#198754', backgroundColor:'rgba(25,135,84,0.12)', tension:0.35, fill:true, pointRadius:3 }] },
      options: { responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, title:{ display:true, text:'dias' } } } }
    });
  }
});
</script>
HTML;
}

Html::footer();
