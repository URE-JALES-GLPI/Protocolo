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
    return "<a href='$url' class='text-decoration-none text-dark'>$label$icon</a>";
}

// Render
echo "<div class='container-fluid'>";
echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
echo "<h4 class='mb-0'><i class='ti ti-folder'></i> " . Pasta::getTypeName(2) . " <small class='text-muted fw-normal'>$total registros</small></h4>";
echo "<div class='d-flex gap-2'>";
if (Pasta::canCreate()) {
    echo "<a href='" . Pasta::getFormURL() . "' class='btn btn-primary btn-sm'><i class='ti ti-folder-plus'></i> Nova</a>";
}
$csvUrl = buildUrl(['export'=>'csv']);
echo "<a href='$csvUrl' class='btn btn-outline-secondary btn-sm'><i class='ti ti-download'></i> CSV</a>";
echo "</div></div>";

echo "<div class='d-flex gap-3 mb-2 small flex-wrap'>";
echo "<span><i class='ti ti-circle-filled text-warning'></i> Sem upload Termo Entrega/Recebimento</span>";
echo "<span><i class='ti ti-circle-filled text-danger'></i> Sem upload Termo Retirada</span>";
echo "<span class='text-muted'>— clique em Ver para fazer upload</span>";
echo "<span class='ms-auto text-muted'>Ordenado por <b>$sort</b> $order</span>";
echo "</div>";

$self = Pasta::getSearchURL();
echo "<form class='card shadow-sm mb-3' method='get' id='filtroPastas'>";
echo "<div class='card-body row g-2 align-items-end'>";
echo "<div class='col-md-3'><label class='form-label small'>Buscar</label><input name='q' value='" . htmlspecialchars($q) . "' class='form-control form-control-sm' placeholder='Código, remetente, escola'></div>";
echo "<div class='col-md-3'><label class='form-label small'>Escola</label><select name='escola' class='form-select form-select-sm'><option value=''>Todas</option>";
foreach ($escolas as $e) {
    $sel = $escola_filtro === (int)$e['id'] ? 'selected' : '';
    echo "<option value='" . (int)$e['id'] . "' $sel>" . htmlspecialchars($e['name']) . "</option>";
}
echo "</select></div>";
echo "<div class='col-md-2'><label class='form-label small'>Status</label><select name='status' class='form-select form-select-sm'><option value=''>Todos</option><option value='aguardando' " . ($status==='aguardando'?'selected':'') . ">Aguardando</option><option value='retirada' " . ($status==='retirada'?'selected':'') . ">Retirada</option><option value='cancelada' " . ($status==='cancelada'?'selected':'') . ">Cancelada</option></select></div>";
echo "<div class='col-md-1'><label class='form-label small'>Por página</label><select name='per_page' class='form-select form-select-sm'><option " . ($perPage==10?'selected':'') . ">10</option><option " . ($perPage==20?'selected':'') . ">20</option><option " . ($perPage==50?'selected':'') . ">50</option><option " . ($perPage==100?'selected':'') . ">100</option></select></div>";
// preserva sort/order ao filtrar
echo "<input type='hidden' name='sort' value='" . htmlspecialchars($sort) . "'><input type='hidden' name='order' value='" . htmlspecialchars($order) . "'>";
echo "<div class='col-md-1'><button class='btn btn-sm btn-primary w-100'><i class='ti ti-search'></i> Filtrar</button></div>";
echo "<div class='col-md-2'><a href='$self' class='btn btn-sm btn-light w-100'>Limpar</a></div>";
echo "</div></form>";

echo "<div class='card shadow-sm'><div class='table-responsive'><table class='table table-hover align-middle mb-0'><thead><tr>";
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
        $retirada = !empty($r['data_retirada']) ? Html::convDateTime($r['data_retirada']) . "<br><small>" . htmlspecialchars($r['retirado_por'] ?? '') . "</small>" : '-';
        $statusBadge = Pasta::getStatusBadge($r['status']);
        $amarelo = empty($r['rec_assinado']);
        $vermelho = !empty($r['ret_existe']) && empty($r['ret_assinado']);
        if ($r['status'] === 'retirada' && empty($r['ret_existe'])) $vermelho = true;
        $termosHtml = '';
        $termosHtml .= $amarelo ? "<i class='ti ti-circle-filled text-warning' title='Pendente upload Termo de Entrega/Recebimento'></i> " : "<i class='ti ti-circle-filled text-success' style='opacity:.25' title='Termo Entrega OK'></i> ";
        if ($vermelho) $termosHtml .= "<i class='ti ti-circle-filled text-danger' title='Pendente upload Termo de Retirada'></i>";
        else {
            if ($r['status'] === 'retirada') $termosHtml .= "<i class='ti ti-circle-filled text-success' style='opacity:.25' title='Termo Retirada OK'></i>";
            else $termosHtml .= "<i class='ti ti-circle-filled' style='color:#ddd' title='Aguardando retirada'></i>";
        }
        $viewUrl = Pasta::getFormURLWithID($r['id']);
        echo "<tr>";
        echo "<td><a href='$viewUrl' class='fw-bold text-decoration-none'>$codigo</a><br><small class='text-muted'>por $criador</small></td>";
        echo "<td>$escolaNome<br><small class='text-muted'>$escolaCod</small></td>";
        echo "<td>$recebidoDe</td>";
        echo "<td>$recebimento</td>";
        echo "<td>$retirada</td>";
        echo "<td>$statusBadge</td>";
        echo "<td class='text-center' style='white-space:nowrap'>$termosHtml</td>";
        echo "<td class='text-end'><a href='$viewUrl' class='btn btn-sm btn-outline-primary'><i class='ti ti-eye'></i> Ver</a></td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='8' class='text-center text-muted py-4'>Nenhum resultado. <a href='" . Pasta::getFormURL() . "'>Registrar a primeira pasta</a>?<br><small class='text-muted'>Filtros: q=" . htmlspecialchars($q) . " escola=$escola_filtro status=$status</small></td></tr>";
}
echo "</tbody></table></div>";

// Paginação
if ($totalPages > 1) {
    echo "<div class='card-footer d-flex justify-content-between align-items-center'>";
    echo "<small class='text-muted'>Página $page de $totalPages — $total pastas</small>";
    echo "<nav><ul class='pagination pagination-sm mb-0'>";
    $prev = max(1, $page-1);
    $next = min($totalPages, $page+1);
    $prevDis = $page==1 ? 'disabled' : '';
    $nextDis = $page==$totalPages ? 'disabled' : '';
    echo "<li class='page-item $prevDis'><a class='page-link' href='" . buildUrl(['page'=>$prev]) . "'>« Anterior</a></li>";
    $start = max(1, $page-2);
    $end = min($totalPages, $page+2);
    for ($i=$start;$i<=$end;$i++) {
        $active = $i==$page ? 'active' : '';
        echo "<li class='page-item $active'><a class='page-link' href='" . buildUrl(['page'=>$i]) . "'>$i</a></li>";
    }
    echo "<li class='page-item $nextDis'><a class='page-link' href='" . buildUrl(['page'=>$next]) . "'>Próxima »</a></li>";
    echo "</ul></nav></div>";
} else {
    echo "<div class='card-footer small text-muted text-center'>$total pastas • ordenado por $sort $order</div>";
}
echo "</div>";
echo "</div>";

Html::footer();
