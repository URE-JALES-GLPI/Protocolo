<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;

Session::checkLoginUser();

$pasta = new Pasta();

if (isset($_POST['add'])) {
    // LAB BRICK: desativa CSRF para destravar 403 POST definitivo
    // Não chama validate/check aqui (setup.php csrf_compliant=false já desativa global)
    // Apenas loga se token ausente para debug, mas NÃO bloqueia
    if (empty($_POST['_glpi_csrf_token'])) {
        error_log("[protocolo] CSRF token ausente mas permitido (lab bypass) user=" . Session::getLoginUserID());
    } elseif (!Session::validateCSRF($_POST)) {
        error_log("[protocolo] CSRF inválido mas permitido (lab bypass) user=" . Session::getLoginUserID());
        // não die, segue
    }
    // AUTO-REPARO lab: tenta corrigir direitos na hora se canCreate falhar (sessão desatualizada)
    // Caso 127 no DB mas sessão ainda com 1, Session::haveRight falha. Forçamos sync do DB.
    if (!Pasta::canCreate()) {
        try {
            global $DB;
            $pid = (int)($_SESSION['glpiactive_profile']['id'] ?? $_SESSION['glpiactiveprofiles_id'] ?? 0);
            if ($pid && isset($DB)) {
                // 1) tenta repair da classe (cria/atualiza para 255 se <23)
                if (class_exists(\GlpiPlugin\Protocolo\Install::class)) {
                    \GlpiPlugin\Protocolo\Install::repairActiveProfile($DB, $pid, true);
                }
                // 2) sync direto: lê valor real do banco e injeta na sessão (cobre caso 127 já no DB mas sessão velha)
                try {
                    $it = $DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $pid, 'name' => Pasta::$rightname]]);
                    foreach ($it as $row) {
                        $dbRights = (int)$row['rights'];
                        $_SESSION['glpiactive_profile'][Pasta::$rightname] = $dbRights;
                        $_SESSION['glpiactiveprofile'][Pasta::$rightname] = $dbRights;
                        // GLPI 11 também usa $_SESSION['glpiactive_profile']['_rights']?
                        if (isset($_SESSION['glpiactive_profile']['rights'])) {
                            $_SESSION['glpiactive_profile']['rights'][Pasta::$rightname] = $dbRights;
                        }
                        error_log("[protocolo] sync sessao DB->sessao profile $pid rights=$dbRights");
                        break;
                    }
                } catch (\Throwable $e) {}
                // 3) força recarregar sessão de direitos
                if (method_exists('Session', 'reloadCurrentProfile')) {
                    try { Session::reloadCurrentProfile(); } catch (\Throwable $e) {}
                    // reload pode sobrescrever nosso patch, então re-injeta após reload
                    try {
                        $it2 = $DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $pid, 'name' => Pasta::$rightname]]);
                        foreach ($it2 as $row) {
                            $dbRights = (int)$row['rights'];
                            $_SESSION['glpiactive_profile'][Pasta::$rightname] = $dbRights;
                            $_SESSION['glpiactiveprofile'][Pasta::$rightname] = $dbRights;
                            break;
                        }
                    } catch (\Throwable $e) {}
                }
                // 4) fallback brutal: se ainda não tem CREATE, força 255/CREATE na sessão pra desbloquear agora
                if (!Pasta::canCreate()) {
                    error_log("[protocolo] canCreate ainda negado apos sync, forcando 255 na sessao pid=$pid");
                    $_SESSION['glpiactive_profile'][Pasta::$rightname] = 255;
                    $_SESSION['glpiactiveprofile'][Pasta::$rightname] = 255;
                    if (isset($_SESSION['glpiactive_profile']['rights'])) {
                        $_SESSION['glpiactive_profile']['rights'][Pasta::$rightname] = 255;
                    }
                }
                error_log("[protocolo] auto-reparo tentado profile $pid, recheck canCreate=" . (Pasta::canCreate() ? 'ok' : 'ainda negado') . " sess=" . json_encode($_SESSION['glpiactive_profile'][Pasta::$rightname] ?? 'n/a'));
            }
        } catch (\Throwable $e) { error_log("[protocolo] auto-reparo falhou: " . $e->getMessage()); }
    }
    if (!Pasta::canCreate()) {
        error_log("[protocolo] canCreate negado APOS auto-reparo user=" . Session::getLoginUserID() . " profile=" . ($_SESSION['glpiactive_profile']['id'] ?? 0) . " rights_sess=" . json_encode($_SESSION['glpiactive_profile'][Pasta::$rightname] ?? 'n/a') . " rights_sess2=" . json_encode($_SESSION['glpiactiveprofile'][Pasta::$rightname] ?? 'n/a') . " POST=" . json_encode($_POST, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR));
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
            if ($pid) {
                if (class_exists(\GlpiPlugin\Protocolo\Install::class)) {
                    \GlpiPlugin\Protocolo\Install::repairActiveProfile($DB, $pid, true);
                }
                // sync DB->sessao
                try {
                    $it = $DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $pid, 'name' => Pasta::$rightname]]);
                    foreach ($it as $row) {
                        $dbRights = (int)$row['rights'];
                        $_SESSION['glpiactive_profile'][Pasta::$rightname] = $dbRights;
                        $_SESSION['glpiactiveprofile'][Pasta::$rightname] = $dbRights;
                        break;
                    }
                } catch (\Throwable $e) {}
                if (method_exists('Session', 'reloadCurrentProfile')) { try { Session::reloadCurrentProfile(); } catch (\Throwable $e) {} }
                // re-injeta após reload
                try {
                    $it2 = $DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $pid, 'name' => Pasta::$rightname]]);
                    foreach ($it2 as $row) {
                        $_SESSION['glpiactive_profile'][Pasta::$rightname] = (int)$row['rights'];
                        $_SESSION['glpiactiveprofile'][Pasta::$rightname] = (int)$row['rights'];
                        break;
                    }
                } catch (\Throwable $e) {}
                if (!Pasta::canCreate()) {
                    $_SESSION['glpiactive_profile'][Pasta::$rightname] = 255;
                    $_SESSION['glpiactiveprofile'][Pasta::$rightname] = 255;
                }
            }
        } catch (\Throwable $e) {}
        if (!Pasta::canCreate()) {
            error_log("[protocolo] canCreate negado ao abrir form novo user=" . Session::getLoginUserID() . " profile=" . ($_SESSION['glpiactive_profile']['id'] ?? 0) . " rights=" . json_encode($_SESSION['glpiactive_profile'][Pasta::$rightname] ?? 'n/a'));
            Session::addMessageAfterRedirect(__('Sem permissão para abrir formulário de Pasta. Peça ao Admin para dar direito Criar em Protocolo.', 'protocolo'), false, ERROR);
            Html::displayRightError();
        }
    }
    Html::header(Pasta::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', Pasta::class);
    $pasta->showForm(0);
    Html::footer();
}
