<?php
/**
 * front/pasta.php - Lista de Pastas estilo site original (custom, não Search::show)
 * Mantém filtros: q, escola, status e exibe Termos com bolinhas amarelo/vermelho
 */
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;
use GlpiPlugin\Protocolo\Escola;

Html::header(Pasta::getTypeName(2), $_SERVER['PHP_SELF'], 'tools', Pasta::class);

if (!Pasta::canView()) {
    Html::displayRightError();
}

global $DB;

// Filtros (igual pastas.php original)
$status = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$escola_filtro = (int)($_GET['escola'] ?? 0);

$where = " WHERE p.is_deleted=0 ";
$params = [];
if (in_array($status, ['aguardando','retirada','cancelada'])) {
    $where .= " AND p.status=" . $DB->quoteValue($status);
}
if ($q !== '') {
    $like = $DB->escape("%$q%");
    $where .= " AND (p.codigo LIKE '$like' OR p.recebido_de LIKE '$like' OR e.name LIKE '$like')";
}
if ($escola_filtro > 0) {
    $where .= " AND p.plugin_protocolo_escolas_id=" . (int)$escola_filtro;
}

// Busca escolas para filtro
$escolas = [];
try {
    $it = $DB->request(['FROM' => Escola::getTable(), 'WHERE' => ['is_active' => 1], 'ORDER' => 'name']);
    foreach ($it as $row) $escolas[] = $row;
} catch (Throwable $e) {
    $escolas = [];
}

// Lista principal (200 últimos) - adapta query original para glpi_plugin_protocolo_*
$sql = "SELECT p.*, e.name AS escola_nome, e.codigo AS escola_codigo,
               u.name AS criador,
               (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='recebimento' ORDER BY id DESC LIMIT 1) AS rec_assinado,
               (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' ORDER BY id DESC LIMIT 1) AS ret_assinado,
               (SELECT id FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' LIMIT 1) AS ret_existe
        FROM glpi_plugin_protocolo_pastas p
        JOIN glpi_plugin_protocolo_escolas e ON e.id=p.plugin_protocolo_escolas_id
        LEFT JOIN glpi_users u ON u.id=p.users_id
        $where ORDER BY p.id DESC LIMIT 200";

$lista = [];
try {
    $res = $DB->doQuery($sql);
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) $lista[] = $row;
    }
} catch (Throwable $e) {
    echo "<div class='alert alert-danger m-3'>Erro ao listar pastas: " . htmlspecialchars($e->getMessage()) . "</div>";
    error_log("[protocolo] pasta.php query falhou: " . $e->getMessage());
}

// Render (estilo original pastas.php:30)
echo "<div class='container-fluid'>";
echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
echo "<h4 class='mb-0'><i class='ti ti-folder'></i> " . Pasta::getTypeName(2) . "</h4>";
if (Pasta::canCreate()) {
    echo "<a href='" . Pasta::getFormURL() . "' class='btn btn-primary btn-sm'><i class='ti ti-folder-plus'></i> Nova</a>";
}
echo "</div>";

echo "<div class='d-flex gap-3 mb-2 small'>";
echo "<span><i class='ti ti-circle-filled text-warning'></i> Sem upload Termo Entrega/Recebimento</span>";
echo "<span><i class='ti ti-circle-filled text-danger'></i> Sem upload Termo Retirada</span>";
echo "<span class='text-muted'>— clique em Ver para fazer upload</span>";
echo "</div>";

$self = Pasta::getSearchURL(false);
echo "<form class='card shadow-sm mb-3' method='get'>";
echo "<div class='card-body row g-2 align-items-end'>";
echo "<div class='col-md-3'><label class='form-label small'>Buscar</label><input name='q' value='" . htmlspecialchars($q) . "' class='form-control form-control-sm' placeholder='Código, remetente, escola'></div>";
echo "<div class='col-md-3'><label class='form-label small'>Escola</label><select name='escola' class='form-select form-select-sm'><option value=''>Todas</option>";
foreach ($escolas as $e) {
    $sel = $escola_filtro === (int)$e['id'] ? 'selected' : '';
    echo "<option value='" . (int)$e['id'] . "' $sel>" . htmlspecialchars($e['name']) . "</option>";
}
echo "</select></div>";
echo "<div class='col-md-2'><label class='form-label small'>Status</label><select name='status' class='form-select form-select-sm'><option value=''>Todos</option><option value='aguardando' " . ($status==='aguardando'?'selected':'') . ">Aguardando</option><option value='retirada' " . ($status==='retirada'?'selected':'') . ">Retirada</option><option value='cancelada' " . ($status==='cancelada'?'selected':'') . ">Cancelada</option></select></div>";
echo "<div class='col-md-2'><button class='btn btn-sm btn-primary w-100'><i class='ti ti-search'></i> Filtrar</button></div>";
echo "<div class='col-md-2'><a href='$self' class='btn btn-sm btn-light w-100'>Limpar</a></div>";
echo "</div></form>";

echo "<div class='card shadow-sm'><div class='table-responsive'><table class='table table-hover align-middle mb-0'><thead><tr><th>Código</th><th>Escola</th><th>Recebido de</th><th>Recebimento</th><th>Retirada</th><th>Status</th><th>Termos</th><th></th></tr></thead><tbody>";
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
        if ($vermelho) {
            $termosHtml .= "<i class='ti ti-circle-filled text-danger' title='Pendente upload Termo de Retirada'></i>";
        } else {
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
    echo "<tr><td colspan='8' class='text-center text-muted py-4'>Nenhum resultado. <a href='" . Pasta::getFormURL() . "'>Registrar a primeira pasta</a>?</td></tr>";
}
echo "</tbody></table></div></div>";
echo "</div>";

Html::footer();
