<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Escola;

if (!Escola::canView()) {
    Html::displayRightError();
}
Html::header(Escola::getTypeName(2), $_SERVER['PHP_SELF'], 'tools', Escola::class);
echo "<div class='pt-page' style='max-width:none;padding:4px 4px 24px;'>";
Search::show(Escola::class);
echo "</div>";
Html::footer();
