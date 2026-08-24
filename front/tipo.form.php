<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\TipoArquivo;

Session::checkLoginUser();
$obj = new TipoArquivo();

if (isset($_POST['add'])) {
    Session::checkCSRF($_POST);
    $obj->check(-1, CREATE, $_POST);
    $newID = $obj->add($_POST);
    Html::redirect(TipoArquivo::getFormURLWithID($newID));
} elseif (isset($_POST['update'])) {
    Session::checkCSRF($_POST);
    $obj->check($_POST['id'], UPDATE);
    $obj->update($_POST);
    Html::back();
} elseif (isset($_POST['delete'])) {
    Session::checkCSRF($_POST);
    $obj->check($_POST['id'], DELETE);
    $obj->delete($_POST);
    Html::redirect(TipoArquivo::getSearchURL());
} elseif (isset($_POST['purge'])) {
    Session::checkCSRF($_POST);
    $obj->check($_POST['id'], PURGE);
    $obj->delete($_POST, 1);
    Html::redirect(TipoArquivo::getSearchURL());
} elseif (isset($_GET['id'])) {
    $obj->check($_GET['id'], READ);
    Html::header(TipoArquivo::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', TipoArquivo::class);
    $obj->display(['id' => (int)$_GET['id']]);
    Html::footer();
} else {
    if (!TipoArquivo::canCreate()) Html::displayRightError();
    Html::header(TipoArquivo::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', TipoArquivo::class);
    $obj->showForm(0);
    Html::footer();
}
