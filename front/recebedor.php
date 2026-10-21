<?php
include('../../../inc/includes.php');
require_once __DIR__ . '/../src/compat.php';

use GlpiPlugin\Protocolo\Recebedor;

if (!Recebedor::canView()) {
    Html::displayRightError();
}
Html::header(Recebedor::getTypeName(2), $_SERVER['PHP_SELF'], 'tools', Recebedor::class);
echo "<div class='pt-page' style='max-width:none;padding:4px 4px 24px;'>";

ob_start();
try {
    Search::show(Recebedor::class);
    echo ob_get_clean();
} catch (\Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('[protocolo] Search Recebedor falhou, usando fallback: ' . $e->getMessage());

    global $DB;

    echo "<div class='pt-page-header'>";
    echo "<div class='pt-page-title'><i class='ti ti-users'></i><h2>" . Recebedor::getTypeName(2) . "</h2></div>";
    echo "<div class='pt-page-actions'>";
    if (Recebedor::canCreate()) {
        echo "<a href='" . Recebedor::getFormURL() . "' class='pt-btn pt-btn-primary pt-btn-sm'><i class='ti ti-plus'></i> " . __('Novo', 'protocolo') . "</a>";
    }
    echo "</div></div>";
    echo "<div class='pt-card'><div style='overflow-x:auto;'><table class='pt-list-table'><thead><tr>";
    echo "<th>" . __('Nome', 'protocolo') . "</th>";
    echo "<th>" . __('Documento', 'protocolo') . "</th>";
    echo "<th>" . __('Tipo', 'protocolo') . "</th>";
    echo "<th>" . __('Ativo', 'protocolo') . "</th>";
    echo "<th>" . __('Criação', 'protocolo') . "</th>";
    echo "<th></th>";
    echo "</tr></thead><tbody>";

    try {
        $it = $DB->request([
            'FROM'  => Recebedor::getTable(),
            'ORDER' => 'name',
        ]);
        $found = false;
        foreach ($it as $row) {
            $found = true;
            $id = (int)($row['id'] ?? 0);
            $doc = trim((string)($row['document'] ?? ''));
            $docType = strtoupper((string)($row['document_type'] ?? 'cpf'));
            $active = !empty($row['is_active'])
                ? "<span class='pt-badge pt-badge-retirada'>" . __('Sim', 'protocolo') . "</span>"
                : "<span class='pt-badge pt-badge-cancelada'>" . __('Não', 'protocolo') . "</span>";
            $created = !empty($row['date_creation'])
                ? Html::convDateTime($row['date_creation'])
                : '—';
            $url = Recebedor::getFormURL() . '?id=' . $id;

            echo "<tr class='pt-list-row'>";
            echo "<td><span class='pt-row-title'>" . htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') . "</span></td>";
            echo "<td>" . ($doc !== '' ? htmlspecialchars($doc, ENT_QUOTES, 'UTF-8') : '—') . "</td>";
            echo "<td>" . htmlspecialchars($docType, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . $active . "</td>";
            echo "<td>" . htmlspecialchars((string)$created, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td><a href='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "' class='pt-btn pt-btn-outline pt-btn-sm'><i class='ti ti-eye'></i> " . __('Ver', 'protocolo') . "</a></td>";
            echo "</tr>";
        }
        if (!$found) {
            echo "<tr><td colspan='6' class='text-muted' style='padding:16px;text-align:center;'>" . __('Nenhum recebedor cadastrado.', 'protocolo') . "</td></tr>";
        }
    } catch (\Throwable $dbError) {
        error_log('[protocolo] fallback recebedores falhou: ' . $dbError->getMessage());
        echo "<tr><td colspan='6' class='text-danger' style='padding:16px;'>" . __('Não foi possível carregar os recebedores.', 'protocolo') . "</td></tr>";
    }

    echo "</tbody></table></div></div>";
}

echo "</div>";
Html::footer();
