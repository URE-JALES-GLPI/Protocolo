<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Escola;

if (!Escola::canView()) {
    Html::displayRightError();
}
Html::header(Escola::getTypeName(2), $_SERVER['PHP_SELF'], 'tools', Escola::class);
Search::show(Escola::class);
Html::footer();
