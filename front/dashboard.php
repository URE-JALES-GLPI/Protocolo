<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;
use GlpiPlugin\Protocolo\Escola;
use GlpiPlugin\Protocolo\Config;
use GlpiPlugin\Protocolo\Recebedor;

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
// Busca livre: código, recebido de, assunto, espécie
$qFiltro = trim($_GET['q'] ?? '');
$qWhereSql = '';
$qWhereSqlPasta = '';
$qLikeCond = [];
if ($qFiltro !== '') {
    $qLike = $DB->quoteValue('%' . $qFiltro . '%');
    $qWhereSql = " AND (p.codigo LIKE $qLike OR p.recebido_de LIKE $qLike OR p.assunto LIKE $qLike OR p.categoria LIKE $qLike OR p.especie_outro LIKE $qLike)";
    $qWhereSqlPasta = " AND (codigo LIKE $qLike OR recebido_de LIKE $qLike OR assunto LIKE $qLike OR categoria LIKE $qLike OR especie_outro LIKE $qLike)";
    $qLikeCond = [new \QueryExpression("(codigo LIKE $qLike OR recebido_de LIKE $qLike OR assunto LIKE $qLike OR categoria LIKE $qLike OR especie_outro LIKE $qLike)")];
}
// Stats - com filtro de entidade + categoria + busca
$totalAguardando = countElementsInTable(Pasta::getTable(), array_merge(['status' => 'aguardando', 'is_deleted' => 0], $entityFilter, $qLikeCond));
$totalRetiradas  = countElementsInTable(Pasta::getTable(), array_merge(['status' => 'retirada', 'is_deleted' => 0], $entityFilter, $qLikeCond));
$totalCanceladas = countElementsInTable(Pasta::getTable(), array_merge(['status' => 'cancelada', 'is_deleted' => 0], $entityFilter, $qLikeCond));
// Breakdown por espécie (para cards quando sem filtro)
$especieCounts = [];
if ($hasCategoriaCol && !$categoriaFiltro) {
    foreach ($especieKeys as $esp) {
        try {
            $especieCounts[$esp] = countElementsInTable(Pasta::getTable(), array_merge(['categoria'=>$esp,'is_deleted'=>0], $entityFilterBase, $qLikeCond));
        } catch (\Throwable $e) { $especieCounts[$esp] = 0; }
    }
}
$totalMes        = 0;
try {
    $whereMes = array_merge(['is_deleted' => 0, new \QueryExpression("MONTH(data_recebimento) = MONTH(NOW()) AND YEAR(data_recebimento) = YEAR(NOW())")], $entityFilter, $qLikeCond);
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
        $sql = "SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas p WHERE p.status='aguardando' AND p.is_deleted=0 $entityWhereSql $categoriaWhereSql $qWhereSql AND DATEDIFF(NOW(), p.data_recebimento) >= " . (int)$prazoAlerta;
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

// Últimas aguardando — ESCOLA = ENTIDADE - com JOIN agregado para evitar N+1 + dias
$lastSql = "SELECT p.*, COALESCE(e.completename, oe.name) AS escola_nome, COALESCE(ic.cpt,0) AS itens_qtd, DATEDIFF(NOW(), p.data_recebimento) AS dias_parada FROM glpi_plugin_protocolo_pastas p LEFT JOIN glpi_entities e ON e.id=p.plugin_protocolo_escolas_id LEFT JOIN glpi_plugin_protocolo_escolas oe ON oe.id=p.plugin_protocolo_escolas_id LEFT JOIN (SELECT plugin_protocolo_pastas_id, COUNT(*) AS cpt FROM glpi_plugin_protocolo_itens GROUP BY plugin_protocolo_pastas_id) ic ON ic.plugin_protocolo_pastas_id=p.id WHERE p.status='aguardando' AND p.is_deleted=0 $entityWhereSql $categoriaWhereSql $qWhereSql ORDER BY p.data_recebimento DESC, p.id DESC LIMIT 12";
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
        $sql = "SELECT DATE_FORMAT(data_recebimento, '%Y-%m') as ym, COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas WHERE is_deleted=0 $entityWhereSqlPasta $categoriaWhereSqlPasta $qWhereSqlPasta AND data_recebimento >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym";
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
        $sql = "SELECT AVG(DATEDIFF(data_retirada, data_recebimento)) as media FROM glpi_plugin_protocolo_pastas WHERE status='retirada' AND is_deleted=0 AND data_retirada IS NOT NULL $entityWhereSqlPasta $categoriaWhereSqlPasta $qWhereSqlPasta";
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
        $sql = "SELECT DATE_FORMAT(data_retirada, '%Y-%m') as ym, AVG(DATEDIFF(data_retirada, data_recebimento)) as media FROM glpi_plugin_protocolo_pastas WHERE status='retirada' AND is_deleted=0 AND data_retirada IS NOT NULL $entityWhereSqlPasta $categoriaWhereSqlPasta $qWhereSqlPasta AND data_retirada >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym";
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
echo "<a href='" . Pasta::getSearchURL() . "' class='pt-btn pt-btn-secondary pt-btn-sm'><i class='ti ti-folder'></i> " . __('Pastas', 'protocolo') . "</a>";
echo "<a href='" . Pasta::getSearchURL() . "?minhas=1' class='pt-btn pt-btn-secondary pt-btn-sm'><i class='ti ti-history'></i> " . __('Histórico', 'protocolo') . "</a>";
if (Recebedor::canView()) { echo "<a href='" . Recebedor::getSearchURL() . "' class='pt-btn pt-btn-secondary pt-btn-sm'><i class='ti ti-users'></i> " . __('Gerenciar Recebedores', 'protocolo') . "</a>"; }
if (Pasta::canCreate()) {
    echo "<a href='" . Pasta::getFormURL() . "' onclick=\"return ptOpenRegisterModal(event)\" class='pt-btn pt-btn-green pt-btn-sm'><i class='ti ti-folder-plus'></i> " . __('Registrar Entrada', 'protocolo') . "</a>";
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
echo "</div>";

echo "<div class='pt-filters-bar' style='padding:12px 16px;margin-bottom:20px;'>";
echo "<button type='button' id='dash-filter-btn' class='pt-filter-toggle-btn' onclick=\"ptToggleFilter('dash-filter-content','dash-filter-btn','dash-filter-text','dash-filter-icon')\"><i class='ti ti-filter'></i> Filtros <span id='dash-filter-text'>Expandir</span> <i id='dash-filter-icon' class='ti ti-chevron-down ms-1'></i></button>";
if ($categoriaFiltro) echo " <span class='pt-badge pt-badge-pasta ms-2'>Filtrando: " . htmlspecialchars(Pasta::getEspecieLabel($categoriaFiltro)) . "</span>";
if ($qFiltro !== '') echo " <span class='pt-badge pt-badge-pasta ms-2'>Busca: " . htmlspecialchars($qFiltro) . "</span>";
echo "<div id='dash-filter-content' class='collapsed' style='display:none;margin-top:12px;'>";
echo "<div class='d-flex gap-2 flex-wrap align-items-center'>";
echo "<span class='text-muted small'><i class='ti ti-filter'></i> Espécie:</span>";
$baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
$clearUrl = $baseUrl . ($categoriaFiltro ? '?categoria=' . urlencode($categoriaFiltro) : '');
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
$invView = 'grid';
try {
    $uidView = (int)Session::getLoginUserID();
    if ($uidView > 0 && $DB->tableExists('glpi_plugin_protocolo_view_prefs')) {
        $itV = $DB->request(['SELECT' => ['view'], 'FROM' => 'glpi_plugin_protocolo_view_prefs', 'WHERE' => ['users_id' => $uidView], 'LIMIT' => 1]);
        foreach ($itV as $rowV) { if (in_array($rowV['view'], ['grid', 'list'], true)) $invView = $rowV['view']; break; }
    }
} catch (\Throwable $e) {}
$tableStyle = $invView === 'grid' ? 'display:none;' : '';
$cardsStyle = $invView === 'grid' ? 'display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));' : 'display:none;';
echo "<div id='pt-ret-bulkbar' class='pt-card' style='display:none;margin-bottom:12px;padding:10px 14px;flex-direction:row;align-items:center;gap:10px;'><span id='pt-ret-bulkcount' style='font-weight:700;'>0 selecionada(s)</span><button type='button' class='pt-btn pt-btn-sm' style='background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border:0;' onclick='ptOpenRetiradaBulk()'><i class='ti ti-signature'></i> Retirar selecionadas</button><button type='button' class='pt-btn pt-btn-secondary pt-btn-sm' onclick='ptRetClearSelection()'><i class='ti ti-x'></i> Limpar</button></div>";
echo "<div class='pt-card'><div class='pt-card-header'><strong><i class='ti ti-clock'></i> " . __('Pastas aguardando retirada (recentes)', 'protocolo') . "</strong><span style='display:inline-flex;gap:4px;align-items:center;'><span style='display:inline-flex;gap:4px;'><button type='button' class='pt-btn pt-btn-secondary pt-btn-sm pt-view-btn' data-v='list' onclick='ptSetInvView(\"list\")' title='Lista'><i class='ti ti-list'></i></button><button type='button' class='pt-btn pt-btn-secondary pt-btn-sm pt-view-btn' data-v='grid' onclick='ptSetInvView(\"grid\")' title='Grade'><i class='ti ti-layout-grid'></i></button></span></span></div>";
echo "<div style='padding:10px 14px 0;'><form method='get' action='' style='display:flex;gap:8px;align-items:center;'>";
if ($categoriaFiltro) echo "<input type='hidden' name='categoria' value='" . htmlspecialchars($categoriaFiltro) . "'>";
echo "<div class='pt-filter-search' style='flex:1;'><input type='text' id='pt-q-live' name='q' value='" . htmlspecialchars($qFiltro) . "' placeholder='Buscar por código, nome, assunto, tipo...' autocomplete='off'></div>";
echo "</form></div>";
echo "<script>document.addEventListener('DOMContentLoaded',function(){var inp=document.getElementById('pt-q-live');if(!inp||inp.dataset.live)return;inp.dataset.live='1';var tb=document.querySelector('#pt-aguard-table tbody');var er=document.createElement('tr');er.id='pt-q-empty-table';er.style.display='none';er.innerHTML=\"<td colspan='10'><div class='pt-empty-state pt-empty-small'><i class='ti ti-search'></i><p>Nenhum resultado para esta busca.</p></div></td>\";if(tb)tb.appendChild(er);var cw=document.getElementById('pt-aguard-cards');var ec=document.createElement('div');ec.id='pt-q-empty-cards';ec.style.display='none';ec.innerHTML=\"<div style='background:#fff;border:1px dashed #cbd5e1;border-radius:12px;padding:20px;text-align:center;color:#9ca3af;grid-column:1/-1;'>Nenhum resultado para esta busca.</div>\";if(cw)cw.appendChild(ec);inp.addEventListener('input',function(){var q=(inp.value||'').toLowerCase();var nT=0;document.querySelectorAll('#pt-aguard-table tbody tr.pt-list-row').forEach(function(tr){if(tr.id==='pt-q-empty-table')return;var hit=q===''||tr.textContent.toLowerCase().indexOf(q)!==-1;tr.style.display=hit?'':'none';if(hit)nT++;});er.style.display=(q!==''&&nT===0)?'':'none';var nC=0;var cw2=document.getElementById('pt-aguard-cards');if(cw2)Array.from(cw2.children).forEach(function(cd){if(cd.id==='pt-q-empty-cards')return;var hit=q===''||cd.textContent.toLowerCase().indexOf(q)!==-1;cd.style.display=hit?'':'none';if(hit)nC++;});ec.style.display=(q!==''&&nC===0)?'':'none';});});</script>";
echo "<div id='pt-aguard-table' class='pt-hide-mobile' style='overflow-x:auto;$tableStyle'><table class='pt-list-table'><thead><tr><th style='width:36px;'><input type='checkbox' id='pt-ret-check-all' title='Selecionar todas' style='width:17px;height:17px;accent-color:#4f46e5;'></th><th>" . __('Código') . "</th><th>" . __('Categoria', 'protocolo') . "</th><th>" . __('Origem', 'protocolo') . " → " . __('Destino', 'protocolo') . "</th><th>" . __('Recebido de') . "</th><th>DATA</th><th>" . __('Dias', 'protocolo') . "</th><th>" . __('Itens') . "</th><th>" . __('Status') . "</th><th>Ações</th></tr></thead><tbody>";
echo "<style>.pt-only-mobile{display:none;}.pt-view-btn.on{border-color:#4f46e5!important;color:#4f46e5!important;background:#eef2ff!important;}@media (max-width:768px){.pt-hide-mobile{display:none;}.pt-only-mobile{display:grid;grid-template-columns:1fr;gap:16px;padding:4px 12px 16px;}#pt-aguard-cards>div{padding:16px!important;}}</style>";
if ($lastRows) {
    $mobileCards = '';
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
        $fluxo = "<div style='line-height:1.35;white-space:normal;'>" . $origem . "</div><div style='line-height:1.35;white-space:normal;color:#4f46e5;'><i class='ti ti-arrow-down' style='font-size:.7rem;'></i> " . $destino . "</div>";
        $fluxoTitle = htmlspecialchars(trim(strip_tags($origem)) . ' → ' . trim(strip_tags($destino)));
        $viewUrlRow = Pasta::getFormURLWithID($r['id']);
        echo "<tr class='pt-list-row $rowCls'><td><input type='checkbox' class='pt-ret-check' value='" . (int)$r['id'] . "' data-codigo='" . htmlspecialchars($r['codigo']) . "' style='width:17px;height:17px;accent-color:#4f46e5;'></td><td style='white-space:normal;min-width:130px;'><span class='pt-row-title' title='" . htmlspecialchars($r['codigo']) . "'>" . htmlspecialchars($r['codigo']) . "</span></td><td>$catBadge</td><td class='small' style='min-width:200px;max-width:300px;' title='$fluxoTitle'>$fluxo</td><td>" . htmlspecialchars($r['recebido_de']) . "</td><td>" . Html::convDateTime($r['data_recebimento']) . "</td><td>$badgeDias</td><td><span class='pt-badge pt-badge-cancelada'>$itens</span></td><td>" . Pasta::getStatusBadge($r['status']) . "</td><td style='white-space:nowrap;'><a href='$viewUrlRow' class='pt-btn " . ($isAtrasada ? "pt-btn-danger" : "pt-btn-outline") . " pt-btn-sm'><i class='ti ti-eye'></i> Ver</a> <button type='button' class='pt-btn pt-btn-sm' style='background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border:0;' title='Registrar retirada' onclick='ptOpenRetiradaModal([" . (int)$r['id'] . "])'><i class='ti ti-signature'></i> Retirar</button></td></tr>";
        $mobileCards .= "<div style='background:#fff;border:1px solid #e8eaf0;border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:10px;box-shadow:0 2px 8px rgba(17,24,39,.06);'>";
        $mobileCards .= "<div style='display:flex;align-items:center;gap:10px;padding-bottom:10px;border-bottom:1px solid #f0f2f8;'><input type='checkbox' class='pt-ret-check' value='" . (int)$r['id'] . "' data-codigo='" . htmlspecialchars($r['codigo']) . "' style='width:20px;height:20px;accent-color:#4f46e5;flex-shrink:0;'><span style='margin-left:auto;flex-shrink:0;'>" . Pasta::getStatusBadge($r['status']) . "</span></div>";
        $mobileCards .= "<div class='pt-row-title' style='font-size:1rem;white-space:normal;word-break:break-all;line-height:1.35;'>" . htmlspecialchars($r['codigo']) . "</div>";
        $mobileCards .= "<div style='display:flex;gap:6px;align-items:center;flex-wrap:wrap;'>$catBadge $badgeDias <span style='font-size:.75rem;color:#9ca3af;'>$itens item(ns)</span></div>";
        $mobileCards .= "<div style='display:flex;flex-direction:column;gap:6px;background:#f8fafc;border-radius:10px;padding:10px 12px;'><div style='font-size:.82rem;color:#374151;'><span style='display:inline-block;min-width:38px;font-size:.68rem;font-weight:800;color:#9ca3af;'>DE</span> " . $origem . "</div><div style='font-size:.82rem;color:#1e40af;'><span style='display:inline-block;min-width:38px;font-size:.68rem;font-weight:800;color:#4f46e5;'>PARA</span> " . $destino . "</div></div>";
        $mobileCards .= "<div style='display:flex;align-items:center;gap:6px;font-size:.78rem;color:#6b7280;'><i class='ti ti-user' style='color:#9ca3af;'></i> " . htmlspecialchars($r['recebido_de']) . " <span style='margin-left:auto;'><i class='ti ti-calendar' style='color:#9ca3af;'></i> " . Html::convDateTime($r['data_recebimento']) . "</span></div>";
        $mobileCards .= "<div style='display:flex;gap:8px;margin-top:2px;'><a href='$viewUrlRow' class='pt-btn pt-btn-outline pt-btn-sm' style='flex:1;min-height:42px;'><i class='ti ti-eye'></i> Ver</a><button type='button' class='pt-btn pt-btn-sm' style='flex:1;min-height:42px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border:0;' onclick='ptOpenRetiradaModal([" . (int)$r['id'] . "])'><i class='ti ti-signature'></i> Retirar</button></div>";
        $mobileCards .= "</div>";
    }
} else {
    echo "<tr class='pt-list-row'><td colspan='10'><div class='pt-empty-state pt-empty-small'><i class='ti ti-folder-off'></i><p>" . __('Nenhuma pasta aguardando no momento.', 'protocolo') . "</p></div></td></tr>";
    $mobileCards = "<div style='background:#fff;border:1px dashed #cbd5e1;border-radius:12px;padding:20px;text-align:center;color:#9ca3af;'>Nenhuma pasta aguardando no momento.</div>";
}
echo "</tbody></table></div></div>";

echo "<div id='pt-aguard-cards' class='pt-only-mobile' style='gap:14px;padding:4px 12px 16px;$cardsStyle'>" . ($mobileCards ?? '') . "</div>";

echo "<div id='pt-retirada-overlay' class='pt-modal-overlay' onclick='if(event.target===this)ptCloseRetiradaModal()'>";
echo "<div class='pt-modal' onclick='event.stopPropagation()' role='dialog' aria-modal='true' aria-label='Registrar retirada' style='max-width:520px;max-height:92vh;display:flex;flex-direction:column;'>";
echo "<div class='pt-modal-header' style='background:linear-gradient(135deg,#1a73b5,#4f46e5);'><div class='pt-modal-title'><i class='ti ti-signature'></i><span id='pt-ret-wiz-title'>Retirada — Etapa 1 de 4</span></div><button type='button' class='pt-modal-close' onclick='ptCloseRetiradaModal()' aria-label='Fechar'><i class='ti ti-x'></i></button></div>";
echo "<div style='height:4px;background:#e8eaf0;'><div id='pt-ret-progress' style='height:100%;width:33%;background:linear-gradient(90deg,#10b981,#059669);transition:width .25s;'></div></div>";
echo "<div id='pt-ret-w1' class='pt-modal-body' style='display:block;text-align:center;'>";
echo "<div style='width:64px;height:64px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:16px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;'><i class='ti ti-user' style='font-size:1.8rem;color:#fff;'></i></div>";
echo "<div style='font-weight:800;font-size:1.1rem;color:#1e1b4b;'>1. Quem retira?</div>";
echo "<div id='pt-ret-list' style='background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 12px;margin:12px 0;font-size:.85rem;text-align:left;'></div>";
echo "<input id='pt-ret-nome' class='form-control' placeholder='Nome completo de quem retira *' autocomplete='off' style='font-size:1.05rem;padding:12px;'>";
echo "<button type='button' class='pt-btn pt-btn-primary' style='width:100%;margin-top:12px;' onclick='ptRWizNext(1)'>Próximo <i class='ti ti-arrow-right'></i></button>";
echo "</div>";
echo "<div id='pt-ret-w2' class='pt-modal-body' style='display:none;'>";
echo "<div style='text-align:center;margin-bottom:14px;'><div style='font-weight:800;font-size:1.05rem;color:#1e1b4b;'>2. Tipo de documento</div><div style='font-size:.85rem;color:#6b7280;'>RG ou CPF de quem retira</div></div>";
echo "<div style='display:flex;gap:10px;margin-bottom:12px;'>";
echo "<button type='button' class='pt-btn pt-btn-secondary' onclick='ptRetChooseDoc(\"rg\")' style='flex:1;padding:22px 12px;'><i class='ti ti-id' style='font-size:2rem;'></i><br><span style='font-size:1.1rem;font-weight:800;'>RG</span><br><small style='color:#6b7280;'>5 a 9 dígitos</small></button>";
echo "<button type='button' class='pt-btn pt-btn-secondary' onclick='ptRetChooseDoc(\"cpf\")' style='flex:1;padding:22px 12px;'><i class='ti ti-id-badge-2' style='font-size:2rem;'></i><br><span style='font-size:1.1rem;font-weight:800;'>CPF</span><br><small style='color:#6b7280;'>11 dígitos</small></button>";
echo "</div>";
echo "<button type='button' class='pt-btn pt-btn-secondary' style='width:100%;' onclick='ptRWizShow(1)'><i class='ti ti-arrow-left'></i> Voltar</button>";
echo "</div>";
echo "<div id='pt-ret-w3' class='pt-modal-body' style='display:none;'>";
echo "<div style='text-align:center;margin-bottom:12px;'><div style='font-weight:800;font-size:1.05rem;color:#1e1b4b;'>3. Número do documento</div><button type='button' id='pt-ret-doc-badge' onclick='ptRWizShow(2)' title='Trocar tipo' style='background:#4f46e5;color:#fff;padding:4px 12px;border-radius:8px;font-weight:700;font-size:.8rem;border:0;cursor:pointer;'>CPF</button></div>";
echo "<input id='pt-ret-doc-input' type='text' inputmode='numeric' autocomplete='off' class='form-control' placeholder='Digite ou toque nos números' style='background:#f8fafc;border:2px solid #e8eaf0;border-radius:10px;padding:12px;font-size:1.4rem;font-weight:700;text-align:center;letter-spacing:3px;color:#1e1b4b;'>";
echo "<input type='hidden' id='pt-ret-doc' value=''>";
echo "<div style='display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;'>";
foreach (['7','8','9','4','5','6','1','2','3'] as $nk) echo "<button type='button' class='pt-btn pt-btn-secondary' style='flex:1 1 30%;padding:14px;font-size:1.2rem;font-weight:700;' onclick='ptRetPress(\"$nk\")'>$nk</button>";
echo "<button type='button' class='pt-btn pt-btn-secondary' style='flex:1 1 30%;padding:14px;font-size:1rem;font-weight:700;color:#dc2626;' onclick='ptRetClearDoc()'>C</button>";
echo "<button type='button' class='pt-btn pt-btn-secondary' style='flex:1 1 30%;padding:14px;font-size:1.2rem;font-weight:700;' onclick='ptRetPress(\"0\")'>0</button>";
echo "<button type='button' class='pt-btn pt-btn-secondary' style='flex:1 1 30%;padding:14px;font-size:1.2rem;' onclick='ptRetPress(\"del\")'><i class='ti ti-backspace'></i></button>";
echo "</div>";
echo "<div style='display:flex;gap:10px;margin-top:14px;'><button type='button' class='pt-btn pt-btn-secondary' style='flex:1;' onclick='ptRWizShow(2)'><i class='ti ti-arrow-left'></i> Voltar</button><button type='button' class='pt-btn pt-btn-primary' style='flex:1;' onclick='ptRWizNext(3)'>Próximo <i class='ti ti-arrow-right'></i></button></div>";
echo "</div>";
echo "<div id='pt-ret-w4' class='pt-modal-body' style='display:none;'>";
echo "<div style='text-align:center;margin-bottom:10px;'><div style='font-weight:800;font-size:1.05rem;color:#1e1b4b;'>4. Assinatura</div><div style='font-size:.85rem;color:#6b7280;'>Use o dedo ou caneta no quadro</div></div>";
echo "<div style='background:#fff;border:2px solid #e8eaf0;border-radius:12px;overflow:hidden;touch-action:none;'><canvas id='pt-ret-canvas' style='width:100%;height:200px;display:block;touch-action:none;cursor:crosshair;'></canvas></div>";
echo "<div class='text-muted' style='font-size:.78rem;margin:6px 0;'>Desenhe acima. Use Limpar para refazer.</div>";
echo "<input type='hidden' id='pt-ret-csrf' value='" . Session::getNewCSRFToken() . "'>";
echo "<div style='display:flex;gap:8px;margin-top:12px;'><button type='button' class='pt-btn pt-btn-secondary' style='flex:1;' onclick='ptRWizShow(3)'><i class='ti ti-arrow-left'></i> Voltar</button><button type='button' class='pt-btn pt-btn-secondary' style='flex:1;' onclick='ptRetClearCanvas()'><i class='ti ti-eraser'></i> Limpar</button></div>";
echo "<div id='pt-ret-err' style='display:none;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:10px;padding:10px 12px;margin-bottom:10px;font-size:.85rem;'></div>";
echo "<button type='button' id='pt-ret-confirm' class='pt-btn pt-btn-green' style='width:100%;margin-top:8px;' onclick='ptSubmitRetirada()'><i class='ti ti-check'></i> Assinar e concluir</button>";
echo "</div>";
echo "<div id='pt-ret-w5' class='pt-modal-body' style='display:none;text-align:center;'>";
echo "<div style='width:80px;height:80px;background:linear-gradient(135deg,#10b981,#059669);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin:16px auto;'><i class='ti ti-check' style='font-size:2.4rem;color:#fff;'></i></div>";
echo "<div style='font-weight:800;font-size:1.3rem;color:#065f46;'>Deu certo!</div>";
echo "<div id='pt-ret-w5-info' style='font-size:.9rem;color:#6b7280;margin:8px 0 16px;'></div>";
echo "<button type='button' class='pt-btn pt-btn-primary' style='width:100%;margin-bottom:8px;' onclick='ptRetPrintTermos()'><i class='ti ti-printer'></i> Imprimir termos</button>";
echo "<button type='button' class='pt-btn pt-btn-secondary' style='width:100%;' onclick='location.reload()'><i class='ti ti-refresh'></i> Fechar e atualizar</button>";
echo "</div>";
echo "</div>";
echo "</div>";

// Tabela atrasadas
if ($alertaAtivo && $totalAtrasadas > 0) {
    echo "<div id='atrasadas' class='pt-card' style='border-color:#fecaca;'><div class='pt-card-header' style='background:#fef2f2;border-color:#fecaca;'><strong style='color:#991b1b;'><i class='ti ti-alarm' style='color:#dc2626;'></i> " . __('Pastas atrasadas', 'protocolo') . " — " . __('aguardando há mais de', 'protocolo') . " $prazoAlerta " . __('dias', 'protocolo') . " ($totalAtrasadas)</strong><a href='" . Pasta::getSearchURL() . "?criteria[0][field]=2&criteria[0][searchtype]=equals&criteria[0][value]=aguardando' class='pt-btn pt-btn-secondary pt-btn-sm'>" . __('Ver todas aguardando', 'protocolo') . "</a></div><div style='overflow-x:auto;'><table class='pt-list-table'><thead><tr><th>" . __('Código') . "</th><th>" . __('Categoria', 'protocolo') . "</th><th>" . __('Origem', 'protocolo') . " → " . __('Destino', 'protocolo') . "</th><th>" . __('Recebido de') . "</th><th>DATA</th><th>" . __('Dias', 'protocolo') . "</th><th></th></tr></thead><tbody>";
    foreach ($atrasadasRows as $r) {
        $dias = (int)($r['dias_parada'] ?? 0);
        $catBadge = Pasta::getCategoriaBadge($r['categoria'] ?? 'pasta', $r['especie_outro'] ?? null);
        $origem = isset($r['origem_tipo']) ? Pasta::getOrigemDestinoDisplay($r, 'origem') . " <i class='ti ti-arrow-right'></i> " . Pasta::getOrigemDestinoDisplay($r, 'destino') : htmlspecialchars($r['escola_nome']);
        echo "<tr class='pt-list-row table-danger'><td><span class='pt-row-title'>" . htmlspecialchars($r['codigo']) . "</span></td><td>$catBadge</td><td class='small'>$origem</td><td>" . htmlspecialchars($r['recebido_de']) . "</td><td>" . Html::convDateTime($r['data_recebimento']) . "</td><td><span class='pt-badge pt-badge-warn'>$dias d</span></td><td><a href='" . Pasta::getFormURLWithID($r['id']) . "' class='pt-btn pt-btn-danger pt-btn-sm'><i class='ti ti-alert-triangle'></i> Regularizar</a></td></tr>";
    }
echo "</tbody></table></div>";
echo "</div>";
    echo "<div class='form-text mt-1 text-muted small'><i class='ti ti-settings'></i> " . __('Ajuste o prazo em', 'protocolo') . " <a href='" . Plugin::getWebDir('protocolo') . "/front/config.php'>Configuração → Prazo alerta</a>.</div>";
}

echo "<div class='pt-alert pt-alert-info'><div><strong>" . __('Fluxo do sistema:', 'protocolo') . "</strong> 1) " . __('Alguém deixa a pasta → Registrar Entrada → assina no tablet → imprime o Termo de Recebimento.', 'protocolo') . "<br>2) " . __('Escola vem buscar → Retirar na grade → assina no tablet → imprime o Termo de Entrega/Retirada.', 'protocolo') . "</div></div>";

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
    echo "<div class='pt-modal-header' style='background:linear-gradient(135deg,#16a34a,#059669);'><div class='pt-modal-title'><i class='ti ti-folder-plus'></i><span>" . __('Registrar Entrada', 'protocolo') . "</span><span id='pt-wiz-steptitle' class='pt-step-badge'>Etapa 1 de 9 — Quem recebe</span></div><button type='button' class='pt-modal-close' onclick='ptCloseRegisterModal()' aria-label='Fechar'><i class='ti ti-x'></i></button></div>";
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
