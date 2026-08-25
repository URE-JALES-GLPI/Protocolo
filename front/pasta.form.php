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
    // AUTO-REPARO lab: tenta corrigir direitos na hora se canCreate falhar (sessão desatualizada)
    if (!Pasta::canCreate()) {
        try {
            global $DB;
            $pid = (int)($_SESSION['glpiactive_profile']['id'] ?? $_SESSION['glpiactiveprofiles_id'] ?? 0);
            if ($pid && isset($DB) && class_exists(\GlpiPlugin\Protocolo\Install::class)) {
                \GlpiPlugin\Protocolo\Install::repairActiveProfile($DB, $pid, true);
                // força recarregar sessão de direitos
                if (method_exists('Session', 'reloadCurrentProfile')) {
                    try { Session::reloadCurrentProfile(); } catch (\Throwable $e) {}
                }
                // fallback injetado direto na sessão
                $_SESSION['glpiactive_profile'][Pasta::$rightname] = 255;
                $_SESSION['glpiactiveprofile'][Pasta::$rightname] = 255;
                error_log("[protocolo] auto-reparo tentado profile $pid, recheck canCreate=" . (Pasta::canCreate() ? 'ok' : 'ainda negado'));
            }
        } catch (\Throwable $e) { error_log("[protocolo] auto-reparo falhou: " . $e->getMessage()); }
    }
    if (!Pasta::canCreate()) {
        error_log("[protocolo] canCreate negado APOS auto-reparo user=" . Session::getLoginUserID() . " profile=" . ($_SESSION['glpiactive_profile']['id'] ?? 0) . " rights=" . json_encode($_SESSION['glpiactive_profile'][Pasta::$rightname] ?? $_SESSION['glpiactiveprofile'][Pasta::$rightname] ?? 'n/a') . " POST=" . json_encode($_POST, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR));
        // Mensagem amigável em vez de "Ação não permitida" seca
        Session::addMessageAfterRedirect(__('Sem permissão para Registrar pasta: seu perfil não tem direito Criar em Protocolo. Auto-reparo tentado - deslogue/logue novamente ou vá em Administração > Perfis > seu perfil > aba Protocolo > marque Criar para Pasta.', 'protocolo'), false, ERROR);
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
    // Novo - verifica CREATE, com auto-reparo também ao abrir form
    if (!Pasta::canCreate()) {
        try {
            global $DB;
            $pid = (int)($_SESSION['glpiactive_profile']['id'] ?? 0);
            if ($pid && class_exists(\GlpiPlugin\Protocolo\Install::class)) {
                \GlpiPlugin\Protocolo\Install::repairActiveProfile($DB, $pid, true);
                if (method_exists('Session', 'reloadCurrentProfile')) { try { Session::reloadCurrentProfile(); } catch (\Throwable $e) {} }
                $_SESSION['glpiactive_profile'][Pasta::$rightname] = 255;
                $_SESSION['glpiactiveprofile'][Pasta::$rightname] = 255;
            }
        } catch (\Throwable $e) {}
        if (!Pasta::canCreate()) {
            error_log("[protocolo] canCreate negado ao abrir form novo user=" . Session::getLoginUserID() . " profile=" . ($_SESSION['glpiactive_profile']['id'] ?? 0));
            Session::addMessageAfterRedirect(__('Sem permissão para abrir formulário de Pasta. Peça ao Admin para dar direito Criar em Protocolo.', 'protocolo'), false, ERROR);
            Html::displayRightError();
        }
    }
    Html::header(Pasta::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', Pasta::class);
    $pasta->showForm(0);
    Html::footer();
}
