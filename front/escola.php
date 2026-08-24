<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Escola;

Html::header(Escola::getTypeName(2), $_SERVER['PHP_SELF'], 'tools', Escola::class);
if (!Escola::canView()) {
    Html::displayRightError();
}
Search::show(Escola::class);
Html::footer();
