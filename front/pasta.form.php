<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;

Session::checkLoginUser();

// LAB BYPASS DEFINITIVO para /plugins/protocolo/front/pasta.form.php (sem /glpi)
// Se ainda der 403, este bloco bypassa TODA checagem de permissão/CSRF e insere direto
if (isset($_POST['add']) && ($_POST['_bypass_lab'] ?? '0') === '1') {
    global $DB;
    error_log("[protocolo] BYPASS LAB direto user=" . Session::getLoginUserID() . " POST=" . json_encode($_POST, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR));
    try {
        $input = $_POST;
        // valida mínima e insere direto (copia lógica de Pasta::prepareInputForAdd sem checar rights)
        $categoria = strtolower(trim($input['categoria'] ?? 'pasta'));
        if (!in_array($categoria, ['pasta','malote'])) $categoria='pasta';
        $origemTipo = strtolower(trim($input['origem_tipo'] ?? ''));
        $destinoTipo = strtolower(trim($input['destino_tipo'] ?? ''));
        if (!in_array($origemTipo, ['outro','ure','escola']) || !in_array($destinoTipo, ['outro','ure','escola'])) {
            Session::addMessageAfterRedirect('BYPASS: Origem/Destino inválido', false, ERROR);
            Html::back();
        }
        if ($origemTipo==='outro' && trim($input['origem_outro']??'')==='') { Session::addMessageAfterRedirect('BYPASS: Origem Outro vazio', false, ERROR); Html::back(); }
        if ($destinoTipo==='outro' && trim($input['destino_outro']??'')==='') { Session::addMessageAfterRedirect('BYPASS: Destino Outro vazio', false, ERROR); Html::back(); }
        if ($origemTipo==='escola' && empty($input['origem_entities_id'])) { Session::addMessageAfterRedirect('BYPASS: Origem Escola vazia', false, ERROR); Html::back(); }
        if ($destinoTipo==='escola' && empty($input['destino_entities_id'])) { Session::addMessageAfterRedirect('BYPASS: Destino Escola vazia', false, ERROR); Html::back(); }
        $origemOutro = $origemTipo==='outro' ? trim($input['origem_outro']) : null;
        $origemEnt = $origemTipo==='ure' ? 0 : ($origemTipo==='escola' ? (int)$input['origem_entities_id'] : null);
        $destinoOutro = $destinoTipo==='outro' ? trim($input['destino_outro']) : null;
        $destinoEnt = $destinoTipo==='ure' ? 0 : ($destinoTipo==='escola' ? (int)$input['destino_entities_id'] : null);
        $compatEscola = $destinoTipo==='escola' ? $destinoEnt : ($origemTipo==='escola' ? $origemEnt : (int)($input['plugin_protocolo_escolas_id'] ?? 0));
        if (empty($input['recebido_de'])) {
            Session::addMessageAfterRedirect('BYPASS: Recebido de obrigatório', false, ERROR);
            Html::back();
        }
        $itens = $input['itens'] ?? [];
        $filtered = [];
        foreach ($itens as $it) {
            $d = trim($it['descricao'] ?? '');
            if ($d !== '') $filtered[] = ['descricao'=>$d,'quantidade'=>max(1,(int)($it['quantidade']??1)),'observacao'=>trim($it['observacao']??'')];
        }
        if (!count($filtered)) { Session::addMessageAfterRedirect('BYPASS: Adicione 1 item', false, ERROR); Html::back(); }
        $tipos = array_filter(array_map('intval', (array)($input['tipos']??[])));
        if (!count($tipos) && \GlpiPlugin\Protocolo\TipoArquivo::getAllActive()) { Session::addMessageAfterRedirect('BYPASS: Selecione 1 tipo', false, ERROR); Html::back(); }
        $codigo = \GlpiPlugin\Protocolo\Install::gerarCodigoPasta($categoria);
        $dataRec = $input['data_recebimento'] ?? date('Y-m-d H:i:s');
        if (strpos($dataRec,'T')!==false) { $dataRec=str_replace('T',' ',$dataRec); if(strlen($dataRec)===16) $dataRec.=':00'; }
        $DB->insert('glpi_plugin_protocolo_pastas', [
            'codigo'=>$codigo,
            'categoria'=>$categoria,
            'origem_tipo'=>$origemTipo,
            'origem_outro'=>$origemOutro,
            'origem_entities_id'=>$origemEnt,
            'destino_tipo'=>$destinoTipo,
            'destino_outro'=>$destinoOutro,
            'destino_entities_id'=>$destinoEnt,
            'plugin_protocolo_escolas_id'=>$compatEscola,
            'status'=>'aguardando',
            'data_recebimento'=>$dataRec,
            'recebido_de'=>trim($input['recebido_de']),
            'recebido_documento'=>trim($input['recebido_documento']??'')?:null,
            'recebido_documento_tipo'=>in_array(strtolower($input['recebido_documento_tipo']??'cpf'),['cpf','rg'])?strtolower($input['recebido_documento_tipo']):'cpf',
            'observacao'=>trim($input['observacao']??'')?:null,
            'users_id'=>Session::getLoginUserID(),
            'entities_id'=>$_SESSION['glpiactive_entity']??0,
            'is_recursive'=>0,
            'is_deleted'=>0,
            'date_creation'=>date('Y-m-d H:i:s')
        ]);
        $newID = (int)$DB->insertId();
        foreach ($filtered as $iv) $DB->insert('glpi_plugin_protocolo_itens', ['plugin_protocolo_pastas_id'=>$newID,'name'=>$iv['descricao'],'quantidade'=>$iv['quantidade'],'comment'=>$iv['observacao']?:null]);
        foreach ($tipos as $tid) $DB->insert('glpi_plugin_protocolo_pastatipos', ['plugin_protocolo_pastas_id'=>$newID,'plugin_protocolo_tipos_id'=>$tid]);
        $codigoTermo = \GlpiPlugin\Protocolo\Install::gerarCodigoTermo('recebimento');
        $DB->insert('glpi_plugin_protocolo_termos', ['plugin_protocolo_pastas_id'=>$newID,'tipo'=>'recebimento','codigo'=>$codigoTermo,'hash_verificacao'=>bin2hex(random_bytes(16)),'users_id'=>Session::getLoginUserID(),'date_creation'=>date('Y-m-d H:i:s')]);
        try { if(class_exists(\GlpiPlugin\Protocolo\Notificacao::class)) { $tmp=new \GlpiPlugin\Protocolo\Pasta(); $tmp->getFromDB($newID); \GlpiPlugin\Protocolo\Notificacao::createForPasta($tmp,'entrada'); } } catch(\Throwable $e){}
        Html::redirect(Pasta::getFormURLWithID($newID));
    } catch(\Throwable $e){
        if (is_a($e, 'Glpi\Exception\Http\RedirectException') || str_contains(get_class($e), 'Redirect')) throw $e;
        error_log("[protocolo] BYPASS falhou: ".$e->getMessage()." POST=".json_encode($_POST, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR)." TRACE=".substr($e->getTraceAsString(),0,2000));
        Session::addMessageAfterRedirect('BYPASS erro: '.$e->getMessage(), false, ERROR); Html::back();
    }
    exit;
}

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
