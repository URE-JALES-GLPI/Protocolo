<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Escola;

Session::checkLoginUser();
$escola = new Escola();

if (isset($_POST['add'])) {
    Session::checkCSRF($_POST);
    $escola->check(-1, CREATE, $_POST);
    $newID = $escola->add($_POST);
    Html::redirect(Escola::getFormURLWithID($newID));
} elseif (isset($_POST['update'])) {
    Session::checkCSRF($_POST);
    $escola->check($_POST['id'], UPDATE);
    $escola->update($_POST);
    Html::back();
} elseif (isset($_POST['delete'])) {
    Session::checkCSRF($_POST);
    $escola->check($_POST['id'], DELETE);
    $escola->delete($_POST);
    Html::redirect(Escola::getSearchURL());
} elseif (isset($_POST['purge'])) {
    Session::checkCSRF($_POST);
    $escola->check($_POST['id'], PURGE);
    $escola->delete($_POST, 1);
    Html::redirect(Escola::getSearchURL());
} elseif (isset($_GET['id'])) {
    $escola->check($_GET['id'], READ);
    Html::header(Escola::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', Escola::class);
    $escola->display(['id' => (int)$_GET['id']]);
    Html::footer();
} else {
    if (!Escola::canCreate()) Html::displayRightError();
    Html::header(Escola::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', Escola::class);
    $escola->showForm(0);
    Html::footer();
}
