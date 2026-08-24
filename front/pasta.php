<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;

Html::header(Pasta::getTypeName(2), $_SERVER['PHP_SELF'], 'tools', Pasta::class);

if (!Pasta::canView()) {
    Html::displayRightError();
}

try {
    Search::show(Pasta::class);
} catch (Throwable $e) {
    echo "<div class='alert alert-danger m-3'><strong>Erro ao listar Pastas:</strong> " . htmlspecialchars($e->getMessage()) . "<br><small>" . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</small><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></div>";
    error_log("[protocolo] Search::show(Pasta) falhou: " . $e->getMessage());
}

Html::footer();
