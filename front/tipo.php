<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\TipoArquivo;

if (!TipoArquivo::canView()) {
    Html::displayRightError();
}
Html::header(TipoArquivo::getTypeName(2), $_SERVER['PHP_SELF'], 'tools', TipoArquivo::class);

// Fallback sem Search (evita SQLProvider giveItem cache) — lista simples
global $DB;
try {
    // Tenta Search normal primeiro; se falhar, cai no fallback
    Search::show(TipoArquivo::class);
} catch (Throwable $e) {
    error_log("[protocolo] Search TipoArquivo falhou, usando fallback: " . $e->getMessage());
    echo "<div class='container-fluid pt-page'><div class='pt-page-header'>";
    echo "<div class='pt-page-title'><i class='ti ti-tags'></i><h2>" . TipoArquivo::getTypeName(2) . "</h2></div>";
    echo "<div class='pt-page-actions'>";
    if (TipoArquivo::canCreate()) {
        echo "<a href='" . TipoArquivo::getFormURL() . "' class='pt-btn pt-btn-primary pt-btn-sm'><i class='ti ti-plus'></i> Novo</a>";
    }
    echo "<button id='pt-theme-btn' onclick='ptToggleTheme()' class='pt-btn pt-btn-secondary pt-btn-sm' title='Alternar tema claro/escuro'><i class='ti ti-moon'></i></button>";
    echo "</div></div>";
    echo "<div class='pt-card'><div style='overflow-x:auto;'><table class='pt-list-table'><thead><tr><th>Nome</th><th>Descrição</th><th>Ativo</th><th></th></tr></thead><tbody>";
    $it = $DB->request(['FROM' => TipoArquivo::getTable(), 'ORDER' => 'name']);
    foreach ($it as $row) {
        $id = (int)$row['id'];
        $ativo = $row['is_active'] ? "<span class='pt-badge pt-badge-retirada'>Sim</span>" : "<span class='pt-badge pt-badge-cancelada'>Não</span>";
        $url = TipoArquivo::getFormURL() . "?id=$id";
        echo "<tr class='pt-list-row'><td><span class='pt-row-title'>" . htmlspecialchars($row['name']) . "</span></td><td>" . htmlspecialchars($row['comment'] ?? '') . "</td><td>$ativo</td><td><a href='$url' class='pt-btn pt-btn-outline pt-btn-sm'><i class='ti ti-eye'></i> Ver</a></td></tr>";
    }
    echo "</tbody></table></div></div></div>";
}
Html::footer();
