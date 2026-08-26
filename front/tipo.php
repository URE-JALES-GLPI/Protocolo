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
    echo "<div class='container-fluid'><div class='d-flex justify-content-between align-items-center mb-3'>";
    echo "<h4><i class='ti ti-tags'></i> " . TipoArquivo::getTypeName(2) . "</h4>";
    if (TipoArquivo::canCreate()) {
        echo "<a href='" . TipoArquivo::getFormURL() . "' class='btn btn-primary btn-sm'><i class='ti ti-plus'></i> Novo</a>";
    }
    echo "</div>";
    echo "<div class='card'><div class='table-responsive'><table class='table table-hover mb-0'><thead><tr><th>Nome</th><th>Descrição</th><th>Ativo</th><th></th></tr></thead><tbody>";
    $it = $DB->request(['FROM' => TipoArquivo::getTable(), 'ORDER' => 'name']);
    foreach ($it as $row) {
        $id = (int)$row['id'];
        $ativo = $row['is_active'] ? "<span class='badge bg-success'>Sim</span>" : "<span class='badge bg-secondary'>Não</span>";
        $url = TipoArquivo::getFormURL() . "?id=$id";
        echo "<tr><td><a href='$url'>" . htmlspecialchars($row['name']) . "</a></td><td>" . htmlspecialchars($row['comment'] ?? '') . "</td><td>$ativo</td><td><a href='$url' class='btn btn-sm btn-outline-primary'><i class='ti ti-eye'></i> Ver</a></td></tr>";
    }
    echo "</tbody></table></div></div></div>";
}
Html::footer();
