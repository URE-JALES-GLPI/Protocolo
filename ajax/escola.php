<?php
include('../../../inc/includes.php');
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

use GlpiPlugin\Protocolo\Escola;

// Para dropdown ajax GLPI (Select2)
if (isset($_POST['searchText'])) {
    $search = $_POST['searchText'];
    $where = [];
    if (!empty($search)) {
        $where['name'] = ['LIKE', "%$search%"];
    }
    $where['is_active'] = 1;
    global $DB;
    $it = $DB->request(['FROM' => Escola::getTable(), 'WHERE' => $where, 'ORDER' => 'name', 'LIMIT' => 30]);
    $results = [];
    foreach ($it as $row) {
        $results[] = ['id' => $row['id'], 'text' => $row['name'] . ($row['codigo'] ? ' (' . $row['codigo'] . ')' : '')];
    }
    echo json_encode(['results' => $results]);
    exit;
}

echo json_encode(['results' => []]);
