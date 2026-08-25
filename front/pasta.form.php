<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;

Session::checkLoginUser();

$pasta = new Pasta();

if (isset($_POST['add'])) {
    if (!Session::validateCSRF($_POST)) {
        error_log("[protocolo] CSRF inválido ao criar pasta user=" . Session::getLoginUserID() . " IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
        Session::checkCSRF($_POST);
    }
    if (!Pasta::canCreate()) {
        error_log("[protocolo] canCreate negado user=" . Session::getLoginUserID() . " profile=" . ($_SESSION['glpiactive_profile']['id'] ?? 0) . " rights=" . json_encode($_SESSION['glpiactive_profile'][Pasta::$rightname] ?? 'n/a'));
        Html::displayRightError();
    }
    // log para debug de prepareInputForAdd falhando silencioso
    $newID = $pasta->add($_POST);
    if ($newID) {
        Html::redirect(Pasta::getFormURLWithID($newID));
    } else {
        // DEBUG lab: loga motivo do erro quando prepareInputForAdd retorna false sem mensagem visível
        $msgs = $_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [];
        error_log("[protocolo][lab-debug] add falhou user=" . Session::getLoginUserID() . " POST=" . json_encode($_POST, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR) . " MSGS=" . json_encode($msgs, JSON_UNESCAPED_UNICODE));
        // garante que usuário veja algo mesmo se mensagem não apareceu
        if (empty($msgs)) {
            Session::addMessageAfterRedirect(__('Falha ao registrar pasta - verifique Escola, Recebido de, pelo menos 1 Tipo e 1 Item com descrição, e CPF/RG válido. Veja php-errors.log [lab-debug]', 'protocolo'), false, ERROR);
        }
        Html::back();
    }
} elseif (isset($_POST['update'])) {
    Session::checkCSRF($_POST);
    $pasta->check($_POST['id'], UPDATE);
    $pasta->update($_POST);
    Html::back();
} elseif (isset($_POST['action'])) {
    Session::checkCSRF($_POST);
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
    Session::checkCSRF($_POST);
    $pasta->check($_POST['id'], DELETE);
    $pasta->delete($_POST);
    Html::redirect(Pasta::getSearchURL());
} elseif (isset($_POST['purge'])) {
    Session::checkCSRF($_POST);
    $pasta->check($_POST['id'], PURGE);
    $pasta->delete($_POST, 1);
    Html::redirect(Pasta::getSearchURL());
} elseif (isset($_POST['restore'])) {
    Session::checkCSRF($_POST);
    $pasta->check($_POST['id'], DELETE);
    $pasta->restore($_POST);
    Html::redirect(Pasta::getFormURLWithID($_POST['id']));
} elseif (isset($_GET['id'])) {
    $pasta->check($_GET['id'], READ);
    Html::header(Pasta::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', Pasta::class);
    $pasta->display(['id' => (int)$_GET['id']]);
    Html::footer();
} else {
    // Novo - verifica CREATE, loga detalhe se negado
    if (!Pasta::canCreate()) {
        error_log("[protocolo] canCreate negado ao abrir form novo user=" . Session::getLoginUserID() . " profile=" . ($_SESSION['glpiactive_profile']['id'] ?? 0));
        Html::displayRightError();
    }
    Html::header(Pasta::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', Pasta::class);
    $pasta->showForm(0);
    Html::footer();
}
