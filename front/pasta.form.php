<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;

$pasta = new Pasta();

/** Coleta mensagens de erro da sessão para resposta AJAX (aponta o erro exato). */
function protocolo_collect_ajax_errors(): array
{
    $errors = [];
    $collect = function ($node) use (&$errors, &$collect) {
        if (is_string($node)) {
            $t = trim(strip_tags(html_entity_decode($node, ENT_QUOTES, 'UTF-8')));
            $t = preg_replace('/\s+/', ' ', $t);
            if ($t !== '' && !in_array($t, $errors, true)) {
                $errors[] = $t;
            }
            return;
        }
        if (is_array($node)) {
            foreach ($node as $v) {
                $collect($v);
            }
        }
    };
    if (!empty($_SESSION['MESSAGE_AFTER_REDIRECT']) && is_array($_SESSION['MESSAGE_AFTER_REDIRECT'])) {
        $collect($_SESSION['MESSAGE_AFTER_REDIRECT']);
        unset($_SESSION['MESSAGE_AFTER_REDIRECT']);
    }
    if (empty($errors)) {
        $errors[] = 'Verifique os campos e tente novamente.';
    }
    return $errors;
}

function protocolo_ajax_answer(array $data): void
{
    // Limpa buffers (avisos PHP no output corromperiam o JSON)
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Detecta AJAX de forma robusta (header pode ser removido por proxy). */
function protocolo_is_ajax(): bool
{
    if ((($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')) {
        return true;
    }
    if (!empty($_POST['ajax']) || !empty($_GET['ajax'])) {
        return true;
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'application/json') !== false && !empty($_POST)) {
        return true;
    }
    return false;
}

/** Diagnóstico de direitos para log e resposta JSON (não expõe dados sensíveis). */
function protocolo_rights_diag(): array
{
    global $DB;
    $uid = 0;
    try { $uid = (int)Session::getLoginUserID(); } catch (\Throwable $e) {}
    $pid = (int)($_SESSION['glpiactive_profile']['id'] ?? 0);
    $dbVal = null;
    try {
        if ($pid > 0 && isset($DB) && $DB->tableExists('glpi_profilerights')) {
            $it = $DB->request([
                'SELECT' => ['rights'],
                'FROM' => 'glpi_profilerights',
                'WHERE' => ['profiles_id' => $pid, 'name' => 'plugin_protocolo_use'],
                'LIMIT' => 1,
            ]);
            foreach ($it as $r) { $dbVal = (int)$r['rights']; break; }
        }
    } catch (\Throwable $e) {}
    $sessVal = $_SESSION['glpiactive_profile']['plugin_protocolo_use'] ?? $_SESSION['glpiactiveprofile']['plugin_protocolo_use'] ?? null;
    return ['uid' => $uid, 'pid' => $pid, 'db' => $dbVal, 'sess' => $sessVal !== null ? (int)$sessVal : null];
}

// checkLoginUser em GLPI 11 lança AccessDenied (vira 403 HTML). Para AJAX,
// converte em JSON para o popup explicar em vez de mostrar 403 genérico.
try {
    Session::checkLoginUser();
} catch (\Throwable $e) {
    if (protocolo_is_ajax()) {
        $d = protocolo_rights_diag();
        error_log("[protocolo] ADD checkLogin falhou: uid={$d['uid']} pid={$d['pid']} err=" . $e->getMessage());
        protocolo_ajax_answer(['ok' => false, 'code' => 'SESSION_DEAD', 'errors' => ['Sua sessão expirou (ou foi invalidada). Abra o GLPI em outra aba, entre de novo, volte aqui e clique em Registrar novamente — os dados continuam preenchidos.']]);
    }
    throw $e;
}

if (isset($_POST['add'])) {
    $isAjax = protocolo_is_ajax();
    $canCreate = Pasta::canCreate();
    $canView = Pasta::canView();
    $diag = protocolo_rights_diag();
    $csrfOk = Session::validateCSRF($_POST) ? '1' : '0';
    error_log("[protocolo] ADD attempt: uid=" . $diag['uid'] . " pid=" . $diag['pid'] . " db=" . var_export($diag['db'], true) . " sess=" . var_export($diag['sess'], true) . " canCreate=" . ($canCreate ? '1' : '0') . " canView=" . ($canView ? '1' : '0') . " csrf=$csrfOk ajax=" . ($isAjax ? '1' : '0') . " postkeys=" . implode(',', array_keys($_POST)));
    if (!$canCreate) {
        $dbgUid = (int)Session::getLoginUserID();
        $dbgPid = (int)($_SESSION['glpiactive_profile']['id'] ?? 0);
        $dbgPname = '';
        try {
            if ($dbgPid > 0 && isset($DB) && $DB->tableExists('glpi_profiles')) {
                $it = $DB->request(['SELECT' => ['name'], 'FROM' => 'glpi_profiles', 'WHERE' => ['id' => $dbgPid], 'LIMIT' => 1]);
                foreach ($it as $r) { $dbgPname = (string)($r['name'] ?? ''); break; }
            }
        } catch (\Throwable $e) {}
        error_log("[protocolo] ADD negado: uid=$dbgUid pid=$dbgPid pname='$dbgPname' canCreate=0 canView=" . ($canView ? '1' : '0') . " ajax=" . ($isAjax ? '1' : '0') . " postkeys=" . implode(',', array_keys($_POST)));
        if ($isAjax) {
            protocolo_ajax_answer(['ok' => false, 'code' => 'NO_CREATE_RIGHT', 'errors' => ["Sem permissão para Registrar Entrada (usuário $dbgUid, perfil ativo '$dbgPname' #$dbgPid). Verifique em Administração > Perfis > esse perfil > aba Protocolo > Usar = Sim (depois saia e entre de novo no GLPI)."]]);
        }
        Html::displayRightError();
    }
    try {
        $newID = $pasta->add($_POST);
    } catch (\Throwable $e) {
        // GLPI 11 lança AccessDenied (403) via check() interno ou plugin hook.
        // Para AJAX, devolve JSON com diagnóstico em vez de página 403 genérica.
        error_log("[protocolo] ADD excecao: uid=" . $diag['uid'] . " pid=" . $diag['pid'] . " err=" . get_class($e) . ": " . $e->getMessage());
        if ($isAjax) {
            $msg = $e->getMessage();
            // Mensagem amigável: se for negação de acesso, explica perfil/sessão
            if (stripos(get_class($e), 'AccessDenied') !== false || stripos($msg, 'right') !== false || stripos($msg, 'can*') !== false) {
                $dbgPname2 = '';
                try {
                    if ($diag['pid'] > 0 && isset($DB) && $DB->tableExists('glpi_profiles')) {
                        $it2 = $DB->request(['SELECT' => ['name'], 'FROM' => 'glpi_profiles', 'WHERE' => ['id' => $diag['pid']], 'LIMIT' => 1]);
                        foreach ($it2 as $r2) { $dbgPname2 = (string)($r2['name'] ?? ''); break; }
                    }
                } catch (\Throwable $e2) {}
                protocolo_ajax_answer(['ok' => false, 'code' => 'ADD_DENIED', 'errors' => ["O servidor negou a criação (usuário {$diag['uid']}, perfil '$dbgPname2' #{$diag['pid']}, direito DB=" . var_export($diag['db'], true) . "). Saia e entre de novo no GLPI e confira em Administração > Perfis > esse perfil > aba Protocolo > Usar = Sim (Efetivo precisa dizer PODE)."]]);
            }
            protocolo_ajax_answer(['ok' => false, 'code' => 'ADD_EXCEPTION', 'errors' => ['Falha ao registrar: ' . $msg]]);
        }
        throw $e;
    }
    if ($newID) {
        if ($isAjax) {
            protocolo_ajax_answer(['ok' => true, 'code' => 'OK', 'id' => $newID, 'url' => Pasta::getFormURLWithID($newID)]);
        }
        Html::redirect(Pasta::getFormURLWithID($newID));
    } else {
        error_log("[protocolo] ADD falhou validacao: uid=" . Session::getLoginUserID() . " postkeys=" . implode(',', array_keys($_POST)));
        if ($isAjax) {
            protocolo_ajax_answer(['ok' => false, 'code' => 'VALIDATION_FAIL', 'errors' => protocolo_collect_ajax_errors()]);
        }
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
