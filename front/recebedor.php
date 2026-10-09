<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Recebedor;

if (!Recebedor::canView()) {
    Html::displayRightError();
}
Html::header(Recebedor::getTypeName(2), $_SERVER['PHP_SELF'], 'tools', Recebedor::class);
echo "<div class='pt-page' style='max-width:none;padding:4px 4px 24px;'>";
Search::show(Recebedor::class);
echo "</div>";
Html::footer();
