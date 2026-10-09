<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Recebedor;

Session::checkLoginUser();
$recebedor = new Recebedor();

if (isset($_POST['add'])) {
    Session::checkCSRF($_POST);
    $recebedor->check(-1, CREATE, $_POST);
    $newID = $recebedor->add($_POST);
    Html::redirect(Recebedor::getFormURLWithID($newID));
} elseif (isset($_POST['update'])) {
    Session::checkCSRF($_POST);
    $recebedor->check($_POST['id'], UPDATE);
    $recebedor->update($_POST);
    Html::back();
} elseif (isset($_POST['delete'])) {
    Session::checkCSRF($_POST);
    $recebedor->check($_POST['id'], DELETE);
    $recebedor->delete($_POST);
    Html::redirect(Recebedor::getSearchURL());
} elseif (isset($_POST['purge'])) {
    Session::checkCSRF($_POST);
    $recebedor->check($_POST['id'], PURGE);
    $recebedor->delete($_POST, 1);
    Html::redirect(Recebedor::getSearchURL());
} elseif (isset($_GET['id'])) {
    $recebedor->check($_GET['id'], READ);
    Html::header(Recebedor::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', Recebedor::class);
    $recebedor->display(['id' => (int)$_GET['id']]);
    Html::footer();
} else {
    if (!Recebedor::canCreate()) Html::displayRightError();
    Html::header(Recebedor::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', Recebedor::class);
    $recebedor->showForm(0);
    Html::footer();
}
