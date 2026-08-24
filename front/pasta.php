<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;

Html::header(Pasta::getTypeName(2), $_SERVER['PHP_SELF'], 'tools', Pasta::class);

if (!Pasta::canView()) {
    Html::displayRightError();
}

Search::show(Pasta::class);

Html::footer();
