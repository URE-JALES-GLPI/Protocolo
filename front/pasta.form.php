<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;

Session::checkLoginUser();

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

if (isset($_POST['add'])) {
    if (!Session::validateCSRF($_POST)) error_log("[protocolo] CSRF mismatch add uid=".Session::getLoginUserID()." token=".($_POST['_glpi_csrf_token']??'none'));
    $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    $canCreate = Pasta::canCreate();
    $canView = Pasta::canView();
    if (!$canCreate) {
        error_log("[protocolo] ADD negado: uid=" . Session::getLoginUserID() . " pid=" . ($_SESSION['glpiactive_profile']['id'] ?? '?') . " canCreate=0 canView=" . ($canView ? '1' : '0') . " ajax=" . ($isAjax ? '1' : '0') . " postkeys=" . implode(',', array_keys($_POST)));
        if ($isAjax) {
            protocolo_ajax_answer(['ok' => false, 'code' => 'NO_CREATE_RIGHT', 'errors' => ['Sem permissão para Registrar Entrada. Verifique em Administração > Perfis > (seu perfil) > aba Protocolo > Usar = Sim (depois saia e entre de novo no GLPI).']]);
        }
        Html::displayRightError();
    }
    $newID = $pasta->add($_POST);
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
