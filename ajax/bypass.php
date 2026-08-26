<?php
// ajax/bypass.php - DEPRECATED: mantido por compatibilidade, agora respeita permissões e CSRF
// Antes era BYPASS TOTAL sem checar direitos. Agora exige plugin_protocolo_pasta CREATE.
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;

Session::checkLoginUser();
Session::checkCSRF($_POST);

if (!Pasta::canCreate()) {
    Html::displayRightError();
}

$pasta = new Pasta();
$newID = $pasta->add($_POST);
if ($newID) {
    Html::redirect(Pasta::getFormURLWithID($newID));
} else {
    Html::back();
}
