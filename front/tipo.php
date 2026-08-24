<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\TipoArquivo;

Html::header(TipoArquivo::getTypeName(2), $_SERVER['PHP_SELF'], 'tools', TipoArquivo::class);
if (!TipoArquivo::canView()) {
    Html::displayRightError();
}
Search::show(TipoArquivo::class);
Html::footer();
