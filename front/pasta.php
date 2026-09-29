<?php
/**
 * front/pasta.php - Lista de Pastas estilo site original + melhorias (2)
 * - Filtros: q, escola, status
 * - Ordenação por coluna (clique no header)
 * - Paginação (20/50/100) + CSV
 * - Bolinhas amarelo/vermelho para pendências
 */
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;
use GlpiPlugin\Protocolo\Escola;

if (!Pasta::canView()) {
    Html::displayRightError();
}

Html::header(Pasta::getTypeName(2), $_SERVER['PHP_SELF'], 'tools', Pasta::class);

global $DB;

// Filtros
$status = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$escola_filtro = (int)($_GET['escola'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, [10,20,50,100])) $perPage = 20;
$sort = $_GET['sort'] ?? 'id';
$order = strtoupper($_GET['order'] ?? 'DESC');
if (!in_array($order, ['ASC','DESC'])) $order = 'DESC';
$allowedSort = ['id'=>'p.id','codigo'=>'p.codigo','escola'=>'e.completename','recebido'=>'p.recebido_de','data'=>'p.data_recebimento','status'=>'p.status'];
$sortSql = $allowedSort[$sort] ?? 'p.id';

// Where — ENTIDADES: filtra pastas pela entidade ativa (se Pasta for entity-aware)
$where = " WHERE p.is_deleted=0 ";
if (function_exists('getEntitiesRestrictRequest')) {
    $where .= getEntitiesRestrictRequest(' AND ', 'p', '', $_SESSION['glpiactive_entity'] ?? 0, true);
} elseif (function_exists('getEntitiesRestrictCriteria')) {
    // fallback via criteria manual não aplicável em SQL bruto — usa activeentities
    $active = $_SESSION['glpiactiveentities'] ?? [$_SESSION['glpiactive_entity'] ?? 0];
    if (!empty($active)) {
        $ids = implode(',', array_map('intval', (array)$active));
        $where .= " AND p.entities_id IN ($ids)";
    }
}
if (in_array($status, ['aguardando','retirada','cancelada'])) {
    $where .= " AND p.status=" . $DB->quoteValue($status);
}
if ($q !== '') {
    $like = $DB->escape("%$q%");
    $where .= " AND (p.codigo LIKE '$like' OR p.recebido_de LIKE '$like' OR e.completename LIKE '$like')";
}
if ($escola_filtro > 0) {
    $where .= " AND p.plugin_protocolo_escolas_id=" . (int)$escola_filtro;
}

// Escolas para filtro — ESCOLA = ENTIDADE GLPI (mostra TODAS)
$escolas = [];
try {
    $it = $DB->request(['FROM' => 'glpi_entities', 'WHERE' => ['id' => ['>', 0]], 'ORDER' => 'completename']);
    foreach ($it as $row) $escolas[] = ['id' => $row['id'], 'name' => $row['completename']];
    // Fallback compat: se ainda vazio e há escolas antigas, mostra antigas
    if (empty($escolas) && $DB->tableExists('glpi_plugin_protocolo_escolas')) {
        $it = $DB->request(['FROM' => Escola::getTable(), 'WHERE' => ['is_active' => 1], 'ORDER' => 'name']);
        foreach ($it as $row) $escolas[] = $row;
    }
} catch (Throwable $e) { $escolas = []; }

// Export CSV — ESCOLA = ENTIDADE
if (($_GET['export'] ?? '') === 'csv') {
    $sqlCsv = "SELECT p.codigo, COALESCE(e.completename, oe.name) AS escola, COALESCE(e.id, oe.codigo) AS escola_cod, p.recebido_de, p.data_recebimento, p.data_retirada, p.retirado_por, p.status
               FROM glpi_plugin_protocolo_pastas p
               LEFT JOIN glpi_entities e ON e.id=p.plugin_protocolo_escolas_id
               LEFT JOIN glpi_plugin_protocolo_escolas oe ON oe.id=p.plugin_protocolo_escolas_id
               $where ORDER BY $sortSql $order";
    try {
        $res = $DB->doQuery($sqlCsv);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pastas-' . date('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF"; // BOM
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Código','Escola','Cód.Escola','Recebido de','Recebimento','Retirada','Retirado por','Status'], ';');
        while ($row = $DB->fetchAssoc($res)) {
            fputcsv($out, [$row['codigo'],$row['escola'],$row['escola_cod'],$row['recebido_de'],$row['data_recebimento'],$row['data_retirada'],$row['retirado_por'],$row['status']], ';');
        }
        fclose($out);
        exit;
    } catch (Throwable $e) {
        Html::displayErrorAndDie("Erro CSV: " . $e->getMessage());
    }
}

// Total para paginação — ESCOLA = ENTIDADE
$total = 0;
try {
    $countSql = "SELECT COUNT(*) AS cpt FROM glpi_plugin_protocolo_pastas p
                 LEFT JOIN glpi_entities e ON e.id=p.plugin_protocolo_escolas_id
                 LEFT JOIN glpi_plugin_protocolo_escolas oe ON oe.id=p.plugin_protocolo_escolas_id
                 $where";
    $res = $DB->doQuery($countSql);
    if ($res && $row = $DB->fetchAssoc($res)) $total = (int)$row['cpt'];
} catch (Throwable $e) { $total = 0; }
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Lista — ESCOLA = ENTIDADE
$sql = "SELECT p.*, COALESCE(e.completename, oe.name) AS escola_nome, COALESCE(e.id, oe.codigo) AS escola_codigo,
               u.name AS criador,
               (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='recebimento' ORDER BY id DESC LIMIT 1) AS rec_assinado,
               (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' ORDER BY id DESC LIMIT 1) AS ret_assinado,
               (SELECT id FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' LIMIT 1) AS ret_existe
        FROM glpi_plugin_protocolo_pastas p
        LEFT JOIN glpi_entities e ON e.id=p.plugin_protocolo_escolas_id
        LEFT JOIN glpi_plugin_protocolo_escolas oe ON oe.id=p.plugin_protocolo_escolas_id
        LEFT JOIN glpi_users u ON u.id=p.users_id
        $where ORDER BY $sortSql $order LIMIT $perPage OFFSET $offset";

$lista = [];
try {
    $res = $DB->doQuery($sql);
    if ($res) while ($row = $DB->fetchAssoc($res)) $lista[] = $row;
} catch (Throwable $e) {
    echo "<div class='alert alert-danger m-3'>Erro ao listar pastas: " . htmlspecialchars($e->getMessage()) . "</div>";
    error_log("[protocolo] pasta.php query falhou: " . $e->getMessage());
}

function buildUrl(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    // remove page quando muda filtro
    return '?' . http_build_query($params);
}
function sortLink(string $field, string $label, string $currentSort, string $currentOrder): string {
    $newOrder = ($currentSort === $field && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    $icon = $currentSort === $field ? ($currentOrder === 'ASC' ? ' ↑' : ' ↓') : '';
    $url = buildUrl(['sort'=>$field,'order'=>$newOrder,'page'=>1]);
    $style = $currentSort === $field ? " style='color:#4f46e5;'" : "";
    return "<a href='$url' style='text-decoration:none;' $style>$label$icon</a>";
}

// Render — identidade visual padronizada com assetmgrstatus (pt-*)
echo "<div class='container-fluid pt-page'>";
echo "<div class='pt-page-header'>";
echo "<div class='pt-page-title'><i class='ti ti-folder'></i><h2>" . Pasta::getTypeName(2) . " <small>$total registros</small></h2></div>";
echo "<div class='pt-page-actions'>";
if (Pasta::canCreate()) {
    echo "<a href='" . Pasta::getFormURL() . "' onclick=\"return ptOpenRegisterModal(event)\" class='pt-btn pt-btn-primary pt-btn-sm'><i class='ti ti-folder-plus'></i> Nova</a>";
}
$csvUrl = buildUrl(['export'=>'csv']);
echo "<a href='$csvUrl' class='pt-btn pt-btn-secondary pt-btn-sm'><i class='ti ti-download'></i> CSV</a>";
echo "<button id='pt-theme-btn' onclick='ptToggleTheme()' class='pt-btn pt-btn-secondary pt-btn-sm' title='Alternar tema claro/escuro'><i class='ti ti-moon'></i></button>";
echo "</div></div>";

echo "<div class='pt-legend'>";
echo "<span><span class='pt-dot' style='background:#d97706;'></span> Sem upload Termo Entrega/Recebimento</span>";
echo "<span><span class='pt-dot' style='background:#dc2626;'></span> Sem upload Termo Retirada</span>";
echo "<span class='text-muted'>— clique em Ver para fazer upload</span>";
echo "<span class='ms-auto text-muted'>Ordenado por <b>$sort</b> $order</span>";
echo "</div>";

$self = Pasta::getSearchURL();
$hasActiveFilter = ($q !== '' || $escola_filtro > 0 || $status !== '' || $perPage !== 20);
$activeCount = ($q!==''?1:0) + ($escola_filtro>0?1:0) + ($status!==''?1:0) + ($perPage!==20?1:0);
echo "<div class='pt-filters-bar' style='padding:12px 16px;margin-bottom:20px;'>";
echo "<div style='display:flex;align-items:center;gap:8px;'>";
echo "<button type='button' id='pasta-filter-toggle-btn' class='pt-filter-toggle-btn' onclick=\"ptToggleFilter('pasta-filter-content','pasta-filter-toggle-btn','pasta-filter-text','pasta-filter-icon')\"><i class='ti ti-filter'></i> Filtros";
if ($hasActiveFilter) echo " <span class='pt-tab-count ms-1'>$activeCount ativo(s)</span>";
echo " <span id='pasta-filter-text' class='ms-1'>Expandir</span> <i id='pasta-filter-icon' class='ti ti-chevron-down ms-1'></i></button>";
if ($hasActiveFilter) echo "<small class='text-muted ms-2'><i class='ti ti-info-circle'></i> Filtros ativos — clique em Expandir para ajustar</small>";
echo "</div>";
echo "<div id='pasta-filter-content' class='collapsed' style='display:none;margin-top:12px;'>";
echo "<form method='get' id='filtroPastas'>";
echo "<div class='pt-filter-row'>";
echo "<div class='pt-filter-group' style='flex:2;min-width:200px;'><label>Buscar</label><div class='pt-filter-search'><input name='q' value='" . htmlspecialchars($q) . "' placeholder='Código, remetente, escola'></div></div>";
echo "<div class='pt-filter-group'><label>Escola</label><select name='escola' class='pt-select'><option value=''>Todas</option>";
foreach ($escolas as $e) {
    $sel = $escola_filtro === (int)$e['id'] ? 'selected' : '';
    echo "<option value='" . (int)$e['id'] . "' $sel>" . htmlspecialchars($e['name']) . "</option>";
}
echo "</select></div>";
echo "<div class='pt-filter-group'><label>Status</label><select name='status' class='pt-select'><option value=''>Todos</option><option value='aguardando' " . ($status==='aguardando'?'selected':'') . ">Aguardando</option><option value='retirada' " . ($status==='retirada'?'selected':'') . ">Retirada</option><option value='cancelada' " . ($status==='cancelada'?'selected':'') . ">Cancelada</option></select></div>";
echo "<div class='pt-filter-group'><label>Por página</label><select name='per_page' class='pt-select'><option " . ($perPage==10?'selected':'') . ">10</option><option " . ($perPage==20?'selected':'') . ">20</option><option " . ($perPage==50?'selected':'') . ">50</option><option " . ($perPage==100?'selected':'') . ">100</option></select></div>";
// preserva sort/order ao filtrar
echo "<input type='hidden' name='sort' value='" . htmlspecialchars($sort) . "'><input type='hidden' name='order' value='" . htmlspecialchars($order) . "'>";
echo "<div class='pt-filter-group'><label>&nbsp;</label><div style='display:flex;gap:8px;'><button class='pt-btn pt-btn-primary pt-btn-sm'><i class='ti ti-search'></i> Filtrar</button>";
echo "<a href='$self' class='pt-btn pt-btn-secondary pt-btn-sm'>Limpar</a></div></div>";
echo "</div></form>";
echo "</div>";
echo "</div>";

echo "<div class='pt-card'><div style='overflow-x:auto;'><table class='pt-list-table'><thead><tr>";
echo "<th>" . sortLink('codigo','Código',$sort,$order) . "</th>";
echo "<th>" . sortLink('escola','Escola',$sort,$order) . "</th>";
echo "<th>" . sortLink('recebido','Recebido de',$sort,$order) . "</th>";
echo "<th>" . sortLink('data','Recebimento',$sort,$order) . "</th>";
echo "<th>Retirada</th><th>" . sortLink('status','Status',$sort,$order) . "</th><th>Termos</th><th></th>";
echo "</tr></thead><tbody>";
if ($lista) {
    foreach ($lista as $r) {
        $codigo = htmlspecialchars($r['codigo']);
        $escolaNome = htmlspecialchars($r['escola_nome']);
        $escolaCod = htmlspecialchars($r['escola_codigo'] ?? '');
        $recebidoDe = htmlspecialchars($r['recebido_de']);
        $criador = htmlspecialchars($r['criador'] ?? '-');
        $recebimento = Html::convDateTime($r['data_recebimento']);
        $retirada = !empty($r['data_retirada']) ? Html::convDateTime($r['data_retirada']) . "<br><small class='pt-row-sub'>" . htmlspecialchars($r['retirado_por'] ?? '') . "</small>" : '<span class="pt-row-sub">—</span>';
        $statusBadge = Pasta::getStatusBadge($r['status']);
        $amarelo = empty($r['rec_assinado']);
        $vermelho = !empty($r['ret_existe']) && empty($r['ret_assinado']);
        if ($r['status'] === 'retirada' && empty($r['ret_existe'])) $vermelho = true;
        $termosHtml = '';
        $termosHtml .= $amarelo ? "<i class='ti ti-circle-filled' style='color:#d97706;' title='Pendente upload Termo de Entrega/Recebimento'></i> " : "<i class='ti ti-circle-filled text-success' style='opacity:.25' title='Termo Entrega OK'></i> ";
        if ($vermelho) $termosHtml .= "<i class='ti ti-circle-filled' style='color:#dc2626;' title='Pendente upload Termo de Retirada'></i>";
        else {
            if ($r['status'] === 'retirada') $termosHtml .= "<i class='ti ti-circle-filled text-success' style='opacity:.25' title='Termo Retirada OK'></i>";
            else $termosHtml .= "<i class='ti ti-circle-filled' style='color:#ddd' title='Aguardando retirada'></i>";
        }
        $viewUrl = Pasta::getFormURLWithID($r['id']);
        echo "<tr class='pt-list-row'>";
        echo "<td><a href='$viewUrl' class='pt-row-title' style='color:#4f46e5;text-decoration:none;'>$codigo</a><br><small class='pt-row-sub'>por $criador</small></td>";
        echo "<td>$escolaNome<br><small class='pt-row-sub'>$escolaCod</small></td>";
        echo "<td>$recebidoDe</td>";
        echo "<td>$recebimento</td>";
        echo "<td>$retirada</td>";
        echo "<td>$statusBadge</td>";
        echo "<td class='text-center' style='white-space:nowrap'>$termosHtml</td>";
        echo "<td class='text-end'><a href='$viewUrl' class='pt-btn pt-btn-outline pt-btn-sm'><i class='ti ti-eye'></i> Ver</a></td>";
        echo "</tr>";
    }
} else {
    echo "<tr class='pt-list-row'><td colspan='8'><div class='pt-empty-state pt-empty-small'><i class='ti ti-folder-off'></i><p>Nenhum resultado. <a href='" . Pasta::getFormURL() . "'>Registrar a primeira pasta</a>?</p><small class='pt-row-sub'>Filtros: q=" . htmlspecialchars($q) . " escola=$escola_filtro status=$status</small></div></td></tr>";
}
echo "</tbody></table></div>";

// Paginação
if ($totalPages > 1) {
    echo "<div class='pt-pagination'>";
    echo "<span class='pt-pagination-info'>Página $page de $totalPages — $total pastas</span>";
    echo "<div class='pt-pagination-pages'>";
    $prev = max(1, $page-1);
    $next = min($totalPages, $page+1);
    $prevDis = $page==1 ? 'disabled' : '';
    $nextDis = $page==$totalPages ? 'disabled' : '';
    echo "<a class='pt-page-link $prevDis' href='" . buildUrl(['page'=>$prev]) . "'>« Anterior</a>";
    $start = max(1, $page-2);
    $end = min($totalPages, $page+2);
    for ($i=$start;$i<=$end;$i++) {
        $active = $i==$page ? 'active' : '';
        echo "<a class='pt-page-link $active' href='" . buildUrl(['page'=>$i]) . "'>$i</a>";
    }
    echo "<a class='pt-page-link $nextDis' href='" . buildUrl(['page'=>$next]) . "'>Próxima »</a>";
    echo "</div></div>";
} else {
    echo "<div class='pt-pagination' style='justify-content:center;'><span class='pt-pagination-info'>$total pastas • ordenado por $sort $order</span></div>";
}
echo "</div>";
echo "</div>";

// Janela flutuante Nova pasta (mesmo formulário da tela cheia, sem trocar de página)
if (Pasta::canCreate()) {
    echo "<div id='pt-register-overlay' class='pt-modal-overlay' onclick='ptCloseRegisterModal(event)'>";
    echo "<div class='pt-modal pt-modal-lg' onclick='event.stopPropagation()' role='dialog' aria-modal='true' aria-label='Nova pasta'>";
    echo "<div class='pt-modal-header'><div class='pt-modal-title'><i class='ti ti-folder-plus'></i><span>Nova pasta</span></div><button type='button' class='pt-modal-close' onclick='ptCloseRegisterModal()' aria-label='Fechar'><i class='ti ti-x'></i></button></div>";
    echo "<div class='pt-modal-body'>";
    $pastaModal = new Pasta();
    $pastaModal->showForm(0, ['modal' => true]);
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

Html::footer();
