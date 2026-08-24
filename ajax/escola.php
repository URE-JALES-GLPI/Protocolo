<?php
include('../../../inc/includes.php');
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

use GlpiPlugin\Protocolo\Escola;

// Para dropdown ajax GLPI (Select2) — ESCOLA = ENTIDADE (TODAS)
if (isset($_POST['searchText'])) {
    $search = $_POST['searchText'];
    global $DB;
    $where = ['id' => ['>', 0]];
    if (!empty($search)) {
        $where['completename'] = ['LIKE', "%$search%"];
    }
    $it = $DB->request(['FROM' => 'glpi_entities', 'WHERE' => $where, 'ORDER' => 'completename', 'LIMIT' => 30]);
    $results = [];
    foreach ($it as $row) {
        $results[] = ['id' => $row['id'], 'text' => $row['completename']];
    }
    // Fallback compat: se busca vazia e há escolas antigas, complementa
    if (empty($results) && empty($search)) {
        $whereOld = ['is_active' => 1];
        $it2 = $DB->request(['FROM' => Escola::getTable(), 'WHERE' => $whereOld, 'ORDER' => 'name', 'LIMIT' => 10]);
        foreach ($it2 as $row) {
            $results[] = ['id' => $row['id'], 'text' => $row['name'] . ' (legado)'];
        }
    }
    echo json_encode(['results' => $results]);
    exit;
}

echo json_encode(['results' => []]);
