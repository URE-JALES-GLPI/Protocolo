<?php
/**
 * front/export.php - Exporta dashboards para XLSX (ou CSV fallback)
 */
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;
use GlpiPlugin\Protocolo\Escola;
use GlpiPlugin\Protocolo\Config;

Session::checkLoginUser();

if (!Pasta::canView()) {
    Html::displayRightError();
}

global $DB, $CFG_GLPI;

$type = $_GET['type'] ?? 'dashboards';

// Entidades ativas (mesma lógica do dashboard)
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
    $entityWhereSqlPasta = '';
    if (!empty($entityFilter)) {
        $entityWhereSqlPasta = " AND entities_id IN (" . implode(',', $activeEntities) . ")";
    }
}

$cfg = Config::getAll();
$prazoAlerta = Config::getPrazoAlertaDias();
$alertaAtivo = Config::isAlertaAtivo();

// Stats básicos
$totalAguardando = countElementsInTable(Pasta::getTable(), array_merge(['status' => 'aguardando', 'is_deleted' => 0], $entityFilter));
$totalRetiradas  = countElementsInTable(Pasta::getTable(), array_merge(['status' => 'retirada', 'is_deleted' => 0], $entityFilter));
$totalCanceladas = countElementsInTable(Pasta::getTable(), array_merge(['status' => 'cancelada', 'is_deleted' => 0], $entityFilter));
$totalMes = 0;
try {
    $whereMes = array_merge(['is_deleted' => 0, new \QueryExpression("MONTH(data_recebimento) = MONTH(NOW()) AND YEAR(data_recebimento) = YEAR(NOW())")], $entityFilter);
    $it = $DB->request(['COUNT' => 'cpt', 'FROM' => Pasta::getTable(), 'WHERE' => $whereMes]);
    foreach ($it as $r) $totalMes = $r['cpt'];
} catch (Throwable $e) {}
$totalAtrasadas = 0;
if ($alertaAtivo) {
    try {
        $sql = "SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas p WHERE p.status='aguardando' AND p.is_deleted=0 $entityWhereSql AND DATEDIFF(NOW(), p.data_recebimento) >= " . (int)$prazoAlerta;
        $res = $DB->doQuery($sql);
        if ($res && $row = $DB->fetchAssoc($res)) $totalAtrasadas = (int)$row['cpt'];
    } catch (Throwable $e) {}
}
$totalPendRec = 0; $totalPendRet = 0;
try {
    $res = $DB->doQuery("SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas p WHERE p.is_deleted=0 $entityWhereSql AND (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='recebimento' ORDER BY id DESC LIMIT 1) IS NULL");
    if ($res && $row = $DB->fetchAssoc($res)) $totalPendRec = (int)$row['cpt'];
    $res = $DB->doQuery("SELECT COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas p WHERE p.is_deleted=0 AND p.status='retirada' $entityWhereSql AND ((SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' ORDER BY id DESC LIMIT 1) IS NULL)");
    if ($res && $row = $DB->fetchAssoc($res)) $totalPendRet = (int)$row['cpt'];
} catch (Throwable $e) {}

$tempoMedioGeral = 0;
try {
    $res = $DB->doQuery("SELECT AVG(DATEDIFF(data_retirada, data_recebimento)) as media FROM glpi_plugin_protocolo_pastas WHERE status='retirada' AND is_deleted=0 AND data_retirada IS NOT NULL $entityWhereSqlPasta");
    if ($res && $row = $DB->fetchAssoc($res)) $tempoMedioGeral = round((float)$row['media'], 1);
} catch (Throwable $e) {}

// Entradas 6 meses
$entradasRows = [];
try {
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("-$i months");
        $ym = date('Y-m', $ts);
        $label = date('m/y', $ts);
        $months[$ym] = $label;
    }
    $sql = "SELECT DATE_FORMAT(data_recebimento, '%Y-%m') as ym, COUNT(*) as cpt FROM glpi_plugin_protocolo_pastas WHERE is_deleted=0 $entityWhereSqlPasta AND data_recebimento >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym";
    $dbMap = [];
    $res = $DB->doQuery($sql);
    if ($res) while ($r = $DB->fetchAssoc($res)) $dbMap[$r['ym']] = (int)$r['cpt'];
    foreach ($months as $ym => $label) {
        $entradasRows[] = [$label, $dbMap[$ym] ?? 0];
    }
} catch (Throwable $e) { $entradasRows = []; }

// Status rows
$statusRows = [
    [__('Aguardando retirada', 'protocolo'), $totalAguardando],
    [__('Retirada', 'protocolo'), $totalRetiradas],
    [__('Cancelada', 'protocolo'), $totalCanceladas],
];

// Tempo médio por mês
$tempoRows = [];
try {
    $months2 = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("-$i months");
        $ym = date('Y-m', $ts);
        $label = date('m/y', $ts);
        $months2[$ym] = $label;
    }
    $sql = "SELECT DATE_FORMAT(data_retirada, '%Y-%m') as ym, AVG(DATEDIFF(data_retirada, data_recebimento)) as media FROM glpi_plugin_protocolo_pastas WHERE status='retirada' AND is_deleted=0 AND data_retirada IS NOT NULL $entityWhereSqlPasta AND data_retirada >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym";
    $dbMap2 = [];
    $res = $DB->doQuery($sql);
    if ($res) while ($r = $DB->fetchAssoc($res)) $dbMap2[$r['ym']] = round((float)$r['media'], 1);
    foreach ($months2 as $ym => $label) {
        $tempoRows[] = [$label, $dbMap2[$ym] ?? 0];
    }
} catch (Throwable $e) { $tempoRows = []; }

// Atrasadas detalhadas
$atrasadasExport = [];
if ($alertaAtivo) {
    try {
        $sql = "SELECT p.codigo, COALESCE(e.completename, oe.name) AS escola_nome, p.recebido_de, p.data_recebimento, DATEDIFF(NOW(), p.data_recebimento) AS dias FROM glpi_plugin_protocolo_pastas p LEFT JOIN glpi_entities e ON e.id=p.plugin_protocolo_escolas_id LEFT JOIN glpi_plugin_protocolo_escolas oe ON oe.id=p.plugin_protocolo_escolas_id WHERE p.status='aguardando' AND p.is_deleted=0 $entityWhereSql AND DATEDIFF(NOW(), p.data_recebimento) >= " . (int)$prazoAlerta . " ORDER BY p.data_recebimento ASC LIMIT 200";
        $res = $DB->doQuery($sql);
        if ($res) while ($r = $DB->fetchAssoc($res)) {
            $atrasadasExport[] = [$r['codigo'], $r['escola_nome'], $r['recebido_de'], $r['data_recebimento'], (int)$r['dias']];
        }
    } catch (Throwable $e) {}
}

// Pendências detalhadas (primeiras 200)
$pendExport = [];
try {
    $pendQuery = "SELECT p.codigo, COALESCE(e.completename, oe.name) AS escola_nome, p.status,
        (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='recebimento' ORDER BY id DESC LIMIT 1) AS rec_assinado,
        (SELECT arquivo_assinado FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' ORDER BY id DESC LIMIT 1) AS ret_assinado,
        (SELECT id FROM glpi_plugin_protocolo_termos WHERE plugin_protocolo_pastas_id=p.id AND tipo='retirada' LIMIT 1) AS ret_existe
        FROM glpi_plugin_protocolo_pastas p
        LEFT JOIN glpi_entities e ON e.id=p.plugin_protocolo_escolas_id
        LEFT JOIN glpi_plugin_protocolo_escolas oe ON oe.id=p.plugin_protocolo_escolas_id
        WHERE p.is_deleted=0 $entityWhereSql
        HAVING rec_assinado IS NULL OR (ret_existe IS NOT NULL AND ret_assinado IS NULL) OR (p.status='retirada' AND ret_existe IS NULL)
        ORDER BY p.id DESC LIMIT 200";
    $res = $DB->doQuery($pendQuery);
    if ($res) while ($r = $DB->fetchAssoc($res)) {
        $pend = '';
        if (empty($r['rec_assinado'])) $pend = 'Entrega';
        if ((!empty($r['ret_existe']) && empty($r['ret_assinado'])) || ($r['status'] === 'retirada' && empty($r['ret_existe']))) $pend .= ($pend ? ' + ' : '') . 'Retirada';
        $pendExport[] = [$r['codigo'], $r['escola_nome'], $r['status'], $pend];
    }
} catch (Throwable $e) {}

// Tenta usar PhpSpreadsheet do GLPI ou do plugin
$hasSpreadsheet = false;
if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    // tenta autoload do GLPI
    $glpiVendor = GLPI_ROOT . '/vendor/autoload.php';
    if (file_exists($glpiVendor)) {
        @include_once $glpiVendor;
    }
    // tenta vendor do plugin
    $pluginVendor = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($pluginVendor)) {
        @include_once $pluginVendor;
    }
}
$hasSpreadsheet = class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet');

if ($hasSpreadsheet) {
    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getProperties()->setCreator('Protocolo URE')->setTitle('Dashboards Protocolo')->setDescription('Exportação dashboards Protocolo');

        // Helper para estilizar header
        $styleHeader = function($sheet, $range) {
            $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($range)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF0D6EFD');
            $sheet->getStyle($range)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        };

        // Resumo
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumo');
        $resumoData = [
            ['Indicador', 'Valor'],
            ['Data exportação', date('d/m/Y H:i:s')],
            ['Entidades ativas', implode(',', $activeEntities) ?: 'todas'],
            ['Prazo alerta (dias)', $prazoAlerta],
            ['Aguardando retirada', $totalAguardando],
            ['Atrasadas (>' . $prazoAlerta . 'd)', $totalAtrasadas],
            ['Retiradas', $totalRetiradas],
            ['Canceladas', $totalCanceladas],
            ['Entradas no mês', $totalMes],
            ['Pend. Termo Entrega', $totalPendRec],
            ['Pend. Termo Retirada', $totalPendRet],
            ['Tempo médio geral (dias)', $tempoMedioGeral],
        ];
        $sheet->fromArray($resumoData, null, 'A1');
        $styleHeader($sheet, 'A1:B1');
        foreach (range('A', 'B') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        $sheet->freezePane('A2');

        // Entradas por mês
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Entradas por mes');
        $sheet2->fromArray(array_merge([['Mês (mm/aa)', 'Quantidade']], $entradasRows), null, 'A1');
        $styleHeader($sheet2, 'A1:B1');
        foreach (range('A', 'B') as $col) $sheet2->getColumnDimension($col)->setAutoSize(true);
        $sheet2->freezePane('A2');
        // Total
        $totalEntradas6m = array_sum(array_column($entradasRows, 1));
        $sheet2->setCellValue('A' . (count($entradasRows)+3), 'TOTAL 6 meses');
        $sheet2->setCellValue('B' . (count($entradasRows)+3), $totalEntradas6m);
        $sheet2->getStyle('A' . (count($entradasRows)+3) . ':B' . (count($entradasRows)+3))->getFont()->setBold(true);

        // Por status
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Por status');
        $sheet3->fromArray(array_merge([['Status', 'Quantidade']], $statusRows), null, 'A1');
        $styleHeader($sheet3, 'A1:B1');
        foreach (range('A', 'B') as $col) $sheet3->getColumnDimension($col)->setAutoSize(true);
        $sheet3->freezePane('A2');

        // Tempo médio
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Tempo medio');
        $sheet4->fromArray(array_merge([['Mês (mm/aa)', 'Média dias']], $tempoRows), null, 'A1');
        $styleHeader($sheet4, 'A1:B1');
        foreach (range('A', 'B') as $col) $sheet4->getColumnDimension($col)->setAutoSize(true);
        $sheet4->freezePane('A2');

        // Atrasadas
        $sheet5 = $spreadsheet->createSheet();
        $sheet5->setTitle('Atrasadas');
        $sheet5->fromArray(array_merge([['Código', 'Escola', 'Recebido de', 'Data Recebimento', 'Dias parada']], $atrasadasExport), null, 'A1');
        $styleHeader($sheet5, 'A1:E1');
        foreach (range('A', 'E') as $col) $sheet5->getColumnDimension($col)->setAutoSize(true);
        $sheet5->freezePane('A2');
        if (empty($atrasadasExport)) {
            $sheet5->setCellValue('A2', 'Nenhuma pasta atrasada');
        }

        // Pendências
        $sheet6 = $spreadsheet->createSheet();
        $sheet6->setTitle('Pendencias');
        $sheet6->fromArray(array_merge([['Código', 'Escola', 'Status', 'Pendência']], $pendExport), null, 'A1');
        $styleHeader($sheet6, 'A1:D1');
        foreach (range('A', 'D') as $col) $sheet6->getColumnDimension($col)->setAutoSize(true);
        $sheet6->freezePane('A2');
        if (empty($pendExport)) {
            $sheet6->setCellValue('A2', 'Nenhuma pendência');
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'protocolo_dashboards_' . date('Y-m-d_His') . '.xlsx';
        // Limpa buffers
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (Throwable $e) {
        error_log("[protocolo] export XLSX falhou, fallback CSV: " . $e->getMessage());
        $hasSpreadsheet = false;
    }
}

// Fallback CSV
if (!$hasSpreadsheet) {
    $filename = 'protocolo_dashboards_' . date('Y-m-d_His') . '.csv';
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: public');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    // Resumo
    fputcsv($out, ['Resumo'], ';');
    fputcsv($out, ['Indicador', 'Valor'], ';');
    $resumo = [
        ['Data exportação', date('d/m/Y H:i:s')],
        ['Entidades', implode(',', $activeEntities) ?: 'todas'],
        ['Aguardando', $totalAguardando],
        ['Atrasadas (>' . $prazoAlerta . 'd)', $totalAtrasadas],
        ['Retiradas', $totalRetiradas],
        ['Canceladas', $totalCanceladas],
        ['Entradas no mes', $totalMes],
        ['Pend Termo Entrega', $totalPendRec],
        ['Pend Termo Retirada', $totalPendRet],
        ['Tempo medio geral', $tempoMedioGeral],
    ];
    foreach ($resumo as $r) fputcsv($out, $r, ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Entradas por mes (6 meses)', 'Quantidade'], ';');
    foreach ($entradasRows as $r) fputcsv($out, $r, ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Por status', 'Quantidade'], ';');
    foreach ($statusRows as $r) fputcsv($out, $r, ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Tempo medio por mes', 'Media dias'], ';');
    foreach ($tempoRows as $r) fputcsv($out, $r, ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Atrasadas', 'Escola', 'Recebido de', 'Data', 'Dias'], ';');
    if ($atrasadasExport) foreach ($atrasadasExport as $r) fputcsv($out, $r, ';');
    else fputcsv($out, ['Nenhuma'], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Pendencias', 'Escola', 'Status', 'Pendencia'], ';');
    if ($pendExport) foreach ($pendExport as $r) fputcsv($out, $r, ';');
    else fputcsv($out, ['Nenhuma'], ';');
    fclose($out);
    exit;
}
