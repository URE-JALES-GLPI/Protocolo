<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;

Session::checkLoginUser();

$pasta = new Pasta();

if (isset($_POST['add'])) {
    if (!Session::validateCSRF($_POST)) error_log("[protocolo] CSRF mismatch add uid=".Session::getLoginUserID()." token=".($_POST['_glpi_csrf_token']??'none'));
    if (!Pasta::canCreate()) {
        Html::displayRightError();
    }
    $newID = $pasta->add($_POST);
    if ($newID) {
        Html::redirect(Pasta::getFormURLWithID($newID));
    } else {
        // Validação falhou: reexibe form com dados e aviso (não perde tudo com F5)
        Html::header(Pasta::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', Pasta::class);
        $pasta->showForm(0, ['input' => $_POST]);
        Html::footer();
        exit;
    }
} elseif (isset($_POST['update'])) {
    if (!Session::validateCSRF($_POST)) error_log("[protocolo] CSRF mismatch update uid=".Session::getLoginUserID());
    $pasta->check($_POST['id'], UPDATE);
    $pasta->update($_POST);
    Html::back();
} elseif (isset($_POST['action'])) {
    if (!Session::validateCSRF($_POST)) error_log("[protocolo] CSRF mismatch action=".$_POST['action']??'none');
    $id = (int)($_POST['id'] ?? 0);
    $pasta->getFromDB($id);
    $action = $_POST['action'] ?? '';
    if ($action === 'retirar') {
        $pasta->check($id, UPDATE);
        $pasta->doRetirar($_POST);
        Html::redirect(Pasta::getFormURLWithID($id));
    } elseif ($action === 'cancelar') {
        $pasta->check($id, UPDATE);
        $pasta->doCancelar();
        Html::redirect(Pasta::getFormURLWithID($id));
    } elseif ($action === 'reabrir') {
        $pasta->check($id, UPDATE);
        $pasta->doReabrir();
        Html::redirect(Pasta::getFormURLWithID($id));
    } elseif ($action === 'upload') {
        $pasta->check($id, UPDATE);
        $termoId = (int)($_POST['termo_id'] ?? 0);
        $pasta->doUpload($termoId, $_FILES['arquivo'] ?? []);
        Html::redirect(Pasta::getFormURLWithID($id));
    } elseif ($action === 'purge' || isset($_POST['purge'])) {
        $pasta->check($id, PURGE);
        $pasta->delete($_POST, 1);
        Html::redirect(Pasta::getSearchURL());
    } elseif (isset($_POST['delete'])) {
        $pasta->check($id, DELETE);
        $pasta->delete($_POST);
        Html::redirect(Pasta::getSearchURL());
    } else {
        Html::back();
    }
} elseif (isset($_POST['delete'])) {
    if (!Session::validateCSRF($_POST)) error_log("[protocolo] CSRF mismatch delete");
    $pasta->check($_POST['id'], DELETE);
    $pasta->delete($_POST);
    Html::redirect(Pasta::getSearchURL());
} elseif (isset($_POST['purge'])) {
    if (!Session::validateCSRF($_POST)) error_log("[protocolo] CSRF mismatch purge");
    $pasta->check($_POST['id'], PURGE);
    $pasta->delete($_POST, 1);
    Html::redirect(Pasta::getSearchURL());
} elseif (isset($_POST['restore'])) {
    if (!Session::validateCSRF($_POST)) error_log("[protocolo] CSRF mismatch restore");
    $pasta->check($_POST['id'], DELETE);
    $pasta->restore($_POST);
    Html::redirect(Pasta::getFormURLWithID($_POST['id']));
} elseif (isset($_GET['id'])) {
    $pasta->check($_GET['id'], READ);
    Html::header(Pasta::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', Pasta::class);
    $pasta->display(['id' => (int)$_GET['id']]);
    Html::footer();
} else {
    if (!Pasta::canCreate()) {
        Html::displayRightError();
    }
    Html::header(Pasta::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', Pasta::class);
    $pasta->showForm(0);
    Html::footer();
}
