<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;
use GlpiPlugin\Protocolo\Escola;
use GlpiPlugin\Protocolo\Config;

if (!Pasta::canView()) {
    error_log("[protocolo] DASHBOARD BLOQUEADO pid=" . ($_SESSION['glpiactive_profile']['id'] ?? 'no_pid') . " uid=" . Session::getLoginUserID() . " rights_db_check FAIL");
    Html::displayRightError();
}
error_log("[protocolo] DASHBOARD LIBERADO pid=" . ($_SESSION['glpiactive_profile']['id'] ?? 'no_pid') . " uid=" . Session::getLoginUserID());

Html::header(__('Protocolo - Dashboard', 'protocolo'), $_SERVER['PHP_SELF'], 'tools', Pasta::class);

global $DB, $CFG_GLPI;

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

// Filtro espécie (substitui categoria pasta/malote)
$especieKeys = array_keys(Pasta::getEspecieOptions());
$categoriaFiltro = $_GET['categoria'] ?? '';
if (!in_array($categoriaFiltro, $especieKeys)) $categoriaFiltro = '';
$categoriaWhereSql = '';
$categoriaWhereSqlPasta = '';
$hasCategoriaCol = false;
try { $hasCategoriaCol = $DB->fieldExists(Pasta::getTable(), 'categoria'); } catch (\Throwable $e) { $hasCategoriaCol = false; }
$entityFilterBase = $entityFilter; // sem categoria para breakdown
if ($categoriaFiltro && $hasCategoriaCol) {
    // categoria já validada via in_array, sem necessidade de escape
    $categoriaWhereSql = " AND p.categoria='" . $categoriaFiltro . "'";
    $categoriaWhereSqlPasta = " AND categoria='" . $categoriaFiltro . "'";
    $entityFilter['categoria'] = $categoriaFiltro;
}
// Stats - com filtro de entidade + categoria
$totalAguardando = countElementsInTable(Pasta::getTable(), array_merge(['status' => 'aguardando', 'is_deleted' => 0], $entityFilter));
$totalRetiradas  = countElementsInTable(Pasta::getTable(), array_merge(['status' => 'retirada', 'is_deleted' => 0], $entityFilter));
$totalCanceladas = countElementsInTable(Pasta::getTable(), array_merge(['status' => 'cancelada', 'is_deleted' => 0], $entityFilter));
// Breakdown por espécie (para cards quando sem filtro)
$especieCounts = [];
if ($hasCategoriaCol && !$categoriaFiltro) {
    foreach ($especieKeys as $esp) {
        try {
            $especieCounts[$esp] = countElementsInTable(Pasta::getTable(), array_merge(['categoria'=>$esp,'is_deleted'=>0], $entityFilterBase));
        } catch (\Throwable $e) { $especieCounts[$esp] = 0; }
    }
}
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
        $sql = "SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas p WHERE p.status='aguardando' AND p.is_deleted=0 $entityWhereSql $categoriaWhereSql AND DATEDIFF(NOW(), p.data_recebimento) >= " . (int)$prazoAlerta;
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
        WHERE p.is_deleted=0 $entityWhereSql $categoriaWhereSql
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
    $res = $DB->doQuery("SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas p WHERE p.is_deleted=0 $entityWhereSql $categoriaWhereSql AND (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='recebimento' ORDER BY id DESC LIMIT 1) IS NULL");
    if ($res && $row = $DB->fetchAssoc($res)) $totalPendRec = $row['cpt'];
    $res = $DB->doQuery("SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas p WHERE p.is_deleted=0 AND p.status='retirada' $entityWhereSql $categoriaWhereSql AND ((SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' ORDER BY id DESC LIMIT 1) IS NULL)");
    if ($res && $row = $DB->fetchAssoc($res)) $totalPendRet = $row['cpt'];
} catch (Exception $e) { $totalPendRec = 0; $totalPendRet = 0; }

// Últimas aguardando — ESCOLA = ENTIDADE - com JOIN agregado para evitar N+1 + dias
$lastSql = "SELECT p.*, COALESCE(e.completename, oe.name) AS escola_nome, COALESCE(ic.cpt,0) AS itens_qtd, DATEDIFF(NOW(), p.data_recebimento) AS dias_parada FROM glpi_plugin_protocolo_pastas p LEFT JOIN glpi_entities e ON e.id=p.plugin_protocolo_escolas_id LEFT JOIN glpi_plugin_protocolo_escolas oe ON oe.id=p.plugin_protocolo_escolas_id LEFT JOIN (SELECT plugin_protocolo_pastas_id, COUNT(*) AS cpt FROM glpi_plugin_protocolo_itens GROUP BY plugin_protocolo_pastas_id) ic ON ic.plugin_protocolo_pastas_id=p.id WHERE p.status='aguardando' AND p.is_deleted=0 $entityWhereSql $categoriaWhereSql ORDER BY p.data_recebimento ASC LIMIT 12";
$lastRows = [];
$res = $DB->doQuery($lastSql);
if ($res) while ($row = $DB->fetchAssoc($res)) $lastRows[] = $row;

// Atrasadas detalhadas (para tabela separada)
$atrasadasRows = [];
if ($alertaAtivo && $totalAtrasadas > 0) {
    try {
        $sqlAtras = "SELECT p.*, COALESCE(e.completename, oe.name) AS escola_nome, DATEDIFF(NOW(), p.data_recebimento) AS dias_parada FROM glpi_plugin_protocolo_pastas p LEFT JOIN glpi_entities e ON e.id=p.plugin_protocolo_escolas_id LEFT JOIN glpi_plugin_protocolo_escolas oe ON oe.id=p.plugin_protocolo_escolas_id WHERE p.status='aguardando' AND p.is_deleted=0 $entityWhereSql $categoriaWhereSql AND DATEDIFF(NOW(), p.data_recebimento) >= " . (int)$prazoAlerta . " ORDER BY p.data_recebimento ASC LIMIT 10";
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
        $sql = "SELECT DATE_FORMAT(data_recebimento, '%Y-%m') as ym, COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas WHERE is_deleted=0 $entityWhereSqlPasta $categoriaWhereSqlPasta AND data_recebimento >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym";
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
        $sql = "SELECT AVG(DATEDIFF(data_retirada, data_recebimento)) as media FROM glpi_plugin_protocolo_pastas WHERE status='retirada' AND is_deleted=0 AND data_retirada IS NOT NULL $entityWhereSqlPasta $categoriaWhereSqlPasta";
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
        $sql = "SELECT DATE_FORMAT(data_retirada, '%Y-%m') as ym, AVG(DATEDIFF(data_retirada, data_recebimento)) as media FROM glpi_plugin_protocolo_pastas WHERE status='retirada' AND is_deleted=0 AND data_retirada IS NOT NULL $entityWhereSqlPasta $categoriaWhereSqlPasta AND data_retirada >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym";
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

echo "<div class='container-fluid pt-page'>";
echo "<div class='pt-page-header'>";
echo "<div class='pt-page-title'><i class='ti ti-dashboard'></i><h2>Dashboard - Protocolo</h2></div>";
echo "<div class='pt-page-actions'>";
if ($alertaAtivo && $totalAtrasadas > 0) {
    echo "<a href='#atrasadas' class='pt-btn pt-btn-danger pt-btn-sm'><i class='ti ti-alert-triangle'></i> " . __('Atrasadas', 'protocolo') . " ($totalAtrasadas)</a>";
}
if (Pasta::canCreate()) {
    echo "<a href='" . Pasta::getFormURL() . "' onclick=\"return ptOpenRegisterModal(event)\" class='pt-btn pt-btn-primary pt-btn-sm'><i class='ti ti-folder-plus'></i> " . __('Registrar Entrada', 'protocolo') . "</a>";
}
if (Config::canEdit()) {
    echo "<a href='" . Plugin::getWebDir('protocolo') . "/front/config.php' class='pt-btn pt-btn-secondary pt-btn-sm'><i class='ti ti-settings'></i> Config</a>";
}
echo "<button id='pt-theme-btn' onclick='ptToggleTheme()' class='pt-btn pt-btn-secondary pt-btn-sm' title='Alternar tema claro/escuro'><i class='ti ti-moon'></i></button>";
echo "</div>";
echo "</div>";

$especieIcons = ['pasta'=>'ti ti-folder','malote'=>'ti ti-mail','envelope'=>'ti ti-envelope','caixa'=>'ti ti-box','outro'=>'ti ti-dots'];

echo "<div class='pt-dash-grid'>";
echo "<div class='pt-dash-card'><div class='pt-dash-card-top'><span class='pt-badge pt-badge-aguardando'>" . __('Aguardando retirada', 'protocolo') . "</span></div><div class='pt-dash-number'>$totalAguardando</div><div class='pt-dash-label'>pastas</div><a href='" . Pasta::getSearchURL() . "?criteria[0][field]=2&criteria[0][searchtype]=equals&criteria[0][value]=aguardando' class='pt-dash-link'>Ver lista &rarr;</a></div>";
echo "<div class='pt-dash-card'><div class='pt-dash-card-top'><span class='pt-badge pt-badge-retirada'>" . __('Retiradas', 'protocolo') . "</span></div><div class='pt-dash-number'>$totalRetiradas</div><div class='pt-dash-label'>pastas</div><a href='" . Pasta::getSearchURL() . "?criteria[0][field]=2&criteria[0][searchtype]=equals&criteria[0][value]=retirada' class='pt-dash-link'>Ver lista &rarr;</a></div>";
if ($hasCategoriaCol && !$categoriaFiltro) {
    foreach (Pasta::getEspecieOptions() as $espVal => $espLabel) {
        $espTotal = $especieCounts[$espVal] ?? 0;
        $espIcon = $especieIcons[$espVal] ?? 'ti ti-tag';
        echo "<div class='pt-dash-card'><div class='pt-dash-card-top'>" . Pasta::getCategoriaBadge($espVal) . "</div><div class='pt-dash-number'>$espTotal</div><div class='pt-dash-label'>" . htmlspecialchars(mb_strtolower($espLabel)) . "</div><a href='?categoria=$espVal' class='pt-dash-link'>Filtrar &rarr;</a></div>";
    }
}
echo "<div class='pt-dash-card'><div class='pt-dash-card-top'><span style='font-size:.8rem;font-weight:700;color:#4f46e5;'><i class='ti ti-calendar-plus'></i> " . __('Entradas no mês', 'protocolo') . "</span></div><div class='pt-dash-number'>$totalMes</div><div class='pt-dash-label'>este mês</div></div>";
echo "<div class='pt-dash-card'><div class='pt-dash-card-top'><span style='font-size:.8rem;font-weight:700;color:#d97706;'><i class='ti ti-circle-filled'></i> Pend. Termo Entrega</span></div><div class='pt-dash-number' style='color:#d97706;'>$totalPendRec</div><div class='pt-dash-label'>termos</div><a href='#pendencias' class='pt-dash-link'>Ver abaixo &rarr;</a></div>";
echo "<div class='pt-dash-card'><div class='pt-dash-card-top'><span style='font-size:.8rem;font-weight:700;color:#dc2626;'><i class='ti ti-circle-filled'></i> Pend. Termo Retirada</span></div><div class='pt-dash-number' style='color:#dc2626;'>$totalPendRet</div><div class='pt-dash-label'>termos</div><a href='#pendencias' class='pt-dash-link'>Ver abaixo &rarr;</a></div>";
echo "</div>";

echo "<div class='pt-filters-bar' style='padding:12px 16px;margin-bottom:20px;'>";
echo "<button type='button' id='dash-filter-btn' class='pt-filter-toggle-btn' onclick=\"ptToggleFilter('dash-filter-content','dash-filter-btn','dash-filter-text','dash-filter-icon')\"><i class='ti ti-filter'></i> Filtros <span id='dash-filter-text'>Expandir</span> <i id='dash-filter-icon' class='ti ti-chevron-down ms-1'></i></button>";
if ($categoriaFiltro) echo " <span class='pt-badge pt-badge-pasta ms-2'>Filtrando: " . htmlspecialchars(Pasta::getEspecieLabel($categoriaFiltro)) . "</span>";
echo "<div id='dash-filter-content' class='collapsed' style='display:none;margin-top:12px;'>";
echo "<div class='d-flex gap-2 flex-wrap align-items-center'>";
echo "<span class='text-muted small'><i class='ti ti-filter'></i> Espécie:</span>";
$baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
$qBase = $_GET; unset($qBase['categoria']);
$buildUrl = function($cat) use ($baseUrl, $qBase) {
    $q = $qBase;
    if ($cat) $q['categoria']=$cat;
    return $baseUrl . ($q ? '?'.http_build_query($q) : '');
};
$especieIcons = ['pasta'=>'ti ti-folder','malote'=>'ti ti-mail','envelope'=>'ti ti-envelope','caixa'=>'ti ti-box','outro'=>'ti ti-dots'];
echo "<div class='pt-tabs'>";
echo "<a href='" . htmlspecialchars($buildUrl('')) . "' class='pt-tab" . ($categoriaFiltro===''?' active':'') . "'><i class='ti ti-apps'></i> " . __('Todos','protocolo') . "</a>";
foreach (Pasta::getEspecieOptions() as $val=>$label) {
    $active = $categoriaFiltro===$val;
    $cls = $active ? 'pt-tab active' : 'pt-tab';
    $icon = $especieIcons[$val] ?? 'ti ti-tag';
    echo "<a href='" . htmlspecialchars($buildUrl($val)) . "' class='$cls'><i class='$icon'></i> " . htmlspecialchars($label) . "</a>";
}
echo "</div>";
if ($categoriaFiltro) echo "<span class='pt-badge pt-badge-pasta ms-2'>Filtrando: " . htmlspecialchars(Pasta::getEspecieLabel($categoriaFiltro)) . "</span>";
echo "</div>";
echo "</div>";
echo "</div>";

if ($alertaAtivo && $totalAtrasadas > 0) {
    echo "<div class='pt-alert pt-alert-danger'><div style='flex:1'><i class='ti ti-alert-triangle'></i> <strong>$totalAtrasadas " . __('pasta(s) aguardando há mais de', 'protocolo') . " $prazoAlerta " . __('dias', 'protocolo') . "</strong> — " . __('regularize a retirada ou contate a escola.', 'protocolo') . "</div><a href='#atrasadas' class='pt-btn pt-btn-danger pt-btn-sm'>" . __('Ver atrasadas', 'protocolo') . "</a></div>";
}

// Tabs Resumo / Dashboards
$activeTab = $_GET['tab'] ?? 'resumo';
if (!in_array($activeTab, ['resumo', 'dashboards'])) $activeTab = 'resumo';
echo "<ul class='nav nav-tabs mb-3' id='protocoloDashTabs' role='tablist'>";
echo "<li class='nav-item' role='presentation'><a class='nav-link " . ($activeTab==='resumo'?'active':'') . "' data-pt-tab='resumo' href='#tab-resumo' role='tab' onclick=\"return ptDashTab(event,'resumo')\"><i class='ti ti-list'></i> " . __('Resumo', 'protocolo') . "</a></li>";
echo "<li class='nav-item' role='presentation'><a class='nav-link " . ($activeTab==='dashboards'?'active':'') . "' data-pt-tab='dashboards' href='#tab-dashboards' role='tab' onclick=\"return ptDashTab(event,'dashboards')\"><i class='ti ti-chart-bar'></i> " . __('Dashboards', 'protocolo') . "</a></li>";
echo "</ul>";

// Tab content
echo "<div class='tab-content'>";
// Resumo
echo "<div class='tab-pane fade " . ($activeTab==='resumo'?'show active':'') . "' id='tab-resumo'>";

// Tabela aguardando
echo "<div class='pt-card'><div class='pt-card-header'><strong><i class='ti ti-clock'></i> " . __('Pastas aguardando retirada (recentes)', 'protocolo') . "</strong><a href='" . Pasta::getSearchURL() . "' class='pt-btn pt-btn-outline pt-btn-sm'>" . __('Ver todas') . "</a></div><div style='overflow-x:auto;'><table class='pt-list-table'><thead><tr><th>" . __('Código') . "</th><th>" . __('Categoria', 'protocolo') . "</th><th>" . __('Origem', 'protocolo') . " → " . __('Destino', 'protocolo') . "</th><th>" . __('Recebido de') . "</th><th>" . __('Data') . "</th><th>" . __('Dias', 'protocolo') . "</th><th>" . __('Itens') . "</th><th>" . __('Status') . "</th><th></th></tr></thead><tbody>";
if ($lastRows) {
    foreach ($lastRows as $r) {
        $itens = (int)($r['itens_qtd'] ?? 0);
        $dias = (int)($r['dias_parada'] ?? 0);
        $isAtrasada = $alertaAtivo && $dias >= $prazoAlerta;
        $isAtencao = $alertaAtivo && !$isAtrasada && $dias >= max(1, $prazoAlerta - 5);
        $rowCls = $isAtrasada ? "table-danger" : ($isAtencao ? "table-warning" : "");
        $badgeDias = $isAtrasada ? "<span class='pt-badge pt-badge-warn'><i class='ti ti-alert-triangle'></i> $dias d</span>" : ($isAtencao ? "<span class='pt-badge pt-badge-aguardando'>$dias d</span>" : "<span class='pt-badge pt-badge-cancelada'>$dias d</span>");
        $catBadge = Pasta::getCategoriaBadge($r['categoria'] ?? 'pasta', $r['especie_outro'] ?? null);
        // origem -> destino display
        $origem = Pasta::getOrigemDestinoDisplay($r, 'origem');
        $destino = Pasta::getOrigemDestinoDisplay($r, 'destino');
        $fluxo = "$origem <i class='ti ti-arrow-right text-muted mx-1'></i> $destino";
        echo "<tr class='pt-list-row $rowCls'><td><span class='pt-row-title'>" . htmlspecialchars($r['codigo']) . "</span></td><td>$catBadge</td><td class='small' style='min-width:180px'>$fluxo</td><td>" . htmlspecialchars($r['recebido_de']) . "</td><td>" . Html::convDateTime($r['data_recebimento']) . "</td><td>$badgeDias</td><td><span class='pt-badge pt-badge-cancelada'>$itens</span></td><td>" . Pasta::getStatusBadge($r['status']) . "</td><td><a href='" . Pasta::getFormURLWithID($r['id']) . "' class='pt-btn " . ($isAtrasada ? "pt-btn-danger" : "pt-btn-outline") . " pt-btn-sm'><i class='ti ti-eye'></i> Ver</a></td></tr>";
    }
} else {
    echo "<tr class='pt-list-row'><td colspan='9'><div class='pt-empty-state pt-empty-small'><i class='ti ti-folder-off'></i><p>" . __('Nenhuma pasta aguardando no momento.', 'protocolo') . "</p></div></td></tr>";
}
echo "</tbody></table></div></div>";

// Tabela atrasadas
if ($alertaAtivo && $totalAtrasadas > 0) {
    echo "<div id='atrasadas' class='pt-card' style='border-color:#fecaca;'><div class='pt-card-header' style='background:#fef2f2;border-color:#fecaca;'><strong style='color:#991b1b;'><i class='ti ti-alarm' style='color:#dc2626;'></i> " . __('Pastas atrasadas', 'protocolo') . " — " . __('aguardando há mais de', 'protocolo') . " $prazoAlerta " . __('dias', 'protocolo') . " ($totalAtrasadas)</strong><a href='" . Pasta::getSearchURL() . "?criteria[0][field]=2&criteria[0][searchtype]=equals&criteria[0][value]=aguardando' class='pt-btn pt-btn-secondary pt-btn-sm'>" . __('Ver todas aguardando', 'protocolo') . "</a></div><div style='overflow-x:auto;'><table class='pt-list-table'><thead><tr><th>" . __('Código') . "</th><th>" . __('Categoria', 'protocolo') . "</th><th>" . __('Origem', 'protocolo') . " → " . __('Destino', 'protocolo') . "</th><th>" . __('Recebido de') . "</th><th>" . __('Data') . "</th><th>" . __('Dias', 'protocolo') . "</th><th></th></tr></thead><tbody>";
    foreach ($atrasadasRows as $r) {
        $dias = (int)($r['dias_parada'] ?? 0);
        $catBadge = Pasta::getCategoriaBadge($r['categoria'] ?? 'pasta', $r['especie_outro'] ?? null);
        $origem = isset($r['origem_tipo']) ? Pasta::getOrigemDestinoDisplay($r, 'origem') . " <i class='ti ti-arrow-right'></i> " . Pasta::getOrigemDestinoDisplay($r, 'destino') : htmlspecialchars($r['escola_nome']);
        echo "<tr class='pt-list-row table-danger'><td><span class='pt-row-title'>" . htmlspecialchars($r['codigo']) . "</span></td><td>$catBadge</td><td class='small'>$origem</td><td>" . htmlspecialchars($r['recebido_de']) . "</td><td>" . Html::convDateTime($r['data_recebimento']) . "</td><td><span class='pt-badge pt-badge-warn'>$dias d</span></td><td><a href='" . Pasta::getFormURLWithID($r['id']) . "' class='pt-btn pt-btn-danger pt-btn-sm'><i class='ti ti-alert-triangle'></i> Regularizar</a></td></tr>";
    }
    echo "</tbody></table></div></div>";
    echo "<div class='form-text mt-1 text-muted small'><i class='ti ti-settings'></i> " . __('Ajuste o prazo em', 'protocolo') . " <a href='" . Plugin::getWebDir('protocolo') . "/front/config.php'>Configuração → Prazo alerta</a>.</div>";
}

// Pendências
echo "<div id='pendencias' class='pt-card'><div class='pt-card-header'><strong><i class='ti ti-alert-triangle'></i> " . __('Pendências de upload de termos', 'protocolo') . "</strong><span class='small text-muted'><i class='ti ti-circle-filled' style='color:#d97706;'></i> Entrega &nbsp; <i class='ti ti-circle-filled' style='color:#dc2626;'></i> Retirada</span></div><div style='overflow-x:auto;'><table class='pt-list-table'><thead><tr><th>" . __('Código') . "</th><th>" . __('Escola') . "</th><th>" . __('Status') . "</th><th>" . __('Pendência') . "</th><th></th></tr></thead><tbody>";
if ($pendentes) {
    foreach ($pendentes as $r) {
        $amarelo = empty($r['rec_assinado']);
        $vermelho = (!empty($r['ret_existe']) && empty($r['ret_assinado'])) || ($r['status'] === 'retirada' && empty($r['ret_existe']));
        echo "<tr class='pt-list-row'><td><span class='pt-row-title'>" . htmlspecialchars($r['codigo']) . "</span></td><td>" . htmlspecialchars($r['escola_nome']) . "</td><td>" . Pasta::getStatusBadge($r['status']) . "</td><td>";
        if ($amarelo) echo "<span class='pt-badge pt-badge-aguardando'><i class='ti ti-circle-filled'></i> Entrega</span> ";
        if ($vermelho) echo "<span class='pt-badge pt-badge-warn'><i class='ti ti-circle-filled'></i> Retirada</span> ";
        if (!$amarelo && !$vermelho) echo "<span class='pt-badge pt-badge-retirada'>OK</span>";
        echo "</td><td><a href='" . Pasta::getFormURLWithID($r['id']) . "' class='pt-btn pt-btn-outline pt-btn-sm'><i class='ti ti-upload'></i> Resolver</a></td></tr>";
    }
} else {
    echo "<tr class='pt-list-row'><td colspan='5'><div class='pt-empty-state pt-empty-small' style='color:#10b981;'><i class='ti ti-circle-check'></i><p>" . __('Nenhuma pendência! Todos os termos com upload em dia.', 'protocolo') . "</p></div></td></tr>";
}
echo "</tbody></table></div></div>";

echo "<div class='pt-alert pt-alert-info'><div><strong>" . __('Fluxo do sistema:', 'protocolo') . "</strong> 1) " . __('Alguém deixa a pasta → Registrar Entrada → imprime Termo de Recebimento → assina e digitaliza (upload).', 'protocolo') . "<br>2) " . __('Escola vem buscar → abrir pasta → Registrar Retirada → imprime Termo de Entrega/Retirada → assina e digitaliza.', 'protocolo') . "</div></div>";

echo "</div>"; // fim tab-resumo

// Dashboards
echo "<div class='tab-pane fade " . ($activeTab==='dashboards'?'show active':'') . "' id='tab-dashboards'>";

echo "<div class='pt-section-title'>";
echo "<i class='ti ti-chart-bar'></i><h5>" . __('Dashboards - Gráficos', 'protocolo') . "</h5>";
echo "<a href='" . Plugin::getWebDir('protocolo') . "/front/export.php?type=dashboards' class='pt-btn pt-btn-green pt-btn-sm' style='margin-left:auto;'><i class='ti ti-file-spreadsheet'></i> " . __('Exportar XLSX', 'protocolo') . "</a>";
echo "</div>";

if ($graficosAtivo) {
    echo "<div class='row g-3 mb-4'>";
    echo "<div class='col-lg-5'><div class='pt-card h-100'><div class='pt-card-header'><strong><i class='ti ti-chart-bar'></i> " . __('Entradas por mês (últimos 6 meses)', 'protocolo') . "</strong></div><div class='pt-card-body'><canvas id='chartEntradas' height='200'></canvas></div></div></div>";
    echo "<div class='col-lg-3'><div class='pt-card h-100'><div class='pt-card-header'><strong><i class='ti ti-chart-pie'></i> " . __('Por status', 'protocolo') . "</strong></div><div class='pt-card-body d-flex align-items-center justify-content-center'><canvas id='chartStatus' height='200'></canvas></div></div></div>";
    echo "<div class='col-lg-4'><div class='pt-card h-100'><div class='pt-card-header'><strong><i class='ti ti-clock'></i> " . __('Tempo médio de guarda (dias)', 'protocolo') . "</strong><span class='pt-badge pt-badge-pasta'>Média geral: " . ($tempoMedioGeral ?: '—') . "d</span></div><div class='pt-card-body'><canvas id='chartTempo' height='200'></canvas><small class='text-muted d-block mt-2'>" . __('Média entre recebimento e retirada por mês de retirada.', 'protocolo') . "</small></div></div></div>";
    echo "</div>";
    echo "<div class='pt-alert pt-alert-info'><i class='ti ti-info-circle'></i><div>" . __('Use Exportar XLSX para baixar os dados dos gráficos e tabelas filtradas por sua entidade ativa.', 'protocolo') . "</div></div>";
} else {
    echo "<div class='pt-alert pt-alert-warning'><i class='ti ti-alert-triangle'></i><div>" . __('Gráficos desativados. Ative em', 'protocolo') . " <a href='" . Plugin::getWebDir('protocolo') . "/front/config.php'>" . __('Configuração', 'protocolo') . "</a>.</div></div>";
}
echo "</div>"; // fim tab-dashboards

echo "</div>"; // fim tab-content

echo "</div>"; // fim container-fluid

// Janela flutuante Registrar Entrada (mesmo formulário da tela cheia, sem trocar de página)
if (Pasta::canCreate()) {
    echo "<div id='pt-register-overlay' class='pt-modal-overlay' onclick='ptCloseRegisterModal(event)'>";
    echo "<div class='pt-modal pt-modal-lg' onclick='event.stopPropagation()' role='dialog' aria-modal='true' aria-label='" . __('Registrar Entrada', 'protocolo') . "'>";
    echo "<div class='pt-modal-header'><div class='pt-modal-title'><i class='ti ti-folder-plus'></i><span>" . __('Registrar Entrada', 'protocolo') . "</span></div><button type='button' class='pt-modal-close' onclick='ptCloseRegisterModal()' aria-label='Fechar'><i class='ti ti-x'></i></button></div>";
    echo "<div class='pt-modal-body'>";
    $pastaModal = new Pasta();
    $pastaModal->showForm(0, ['modal' => true]);
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

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
  window._ptCharts = window._ptCharts || [];
  // Cria os gráficos quando visíveis e reajusta ao trocar de aba (sem reload)
  window.ptInitProtocoloCharts = function(){
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = "Inter, system-ui, sans-serif";
    Chart.defaults.color = "#6c757d";
    function mk(id, cfg){
      var c = document.getElementById(id);
      if (!c || c.dataset.inited) return;
      if (c.offsetParent === null) return; // aba oculta: adia a criação
      c.dataset.inited = '1';
      window._ptCharts.push(new Chart(c, cfg));
    }
    mk('chartEntradas', {
      type: 'bar',
      data: { labels: $jsonEntradasLabels, datasets: [{ label: 'Entradas', data: $jsonEntradasValues, backgroundColor: '#4f46e5', hoverBackgroundColor: '#7c3aed', borderRadius: 6 }] },
      options: { responsive: true, plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label: ctx => ctx.parsed.y + ' pasta(s)' } } }, scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } } }
    });
    mk('chartStatus', {
      type: 'doughnut',
      data: { labels: $jsonStatusLabels, datasets: [{ data: $jsonStatusValues, backgroundColor:['#f59e0b','#10b981','#9ca3af'], borderWidth:0 }] },
      options: { responsive:true, plugins:{ legend:{ position:'bottom' } }, cutout:'58%' }
    });
    mk('chartTempo', {
      type: 'line',
      data: { labels: $jsonTempoLabels, datasets: [{ label:'Dias médios', data: $jsonTempoValues, borderColor:'#4f46e5', backgroundColor:'rgba(79,70,229,0.12)', tension:0.35, fill:true, pointRadius:3, pointBackgroundColor:'#4f46e5' }] },
      options: { responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, title:{ display:true, text:'dias' } } } }
    });
    window._ptCharts.forEach(function(ch){ try { ch.resize(); } catch(e){} });
  };
  window.ptInitProtocoloCharts();
});
</script>
HTML;
}

Html::footer();
