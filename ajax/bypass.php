<?php
// ajax/bypass.php - LAB BYPASS TOTAL - cria pasta sem checar CSRF/rights
// Chamado via POST do form em src/Pasta.php quando _bypass_lab=1
include('../../../inc/includes.php');
Session::checkLoginUser();
global $DB;
if (!isset($_POST['add'])) { Html::back(); }
error_log("[protocolo] BYPASS AJAX direto user=" . Session::getLoginUserID() . " POST=" . json_encode($_POST, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR));
try {
    $input = $_POST;
    // Categoria
    $categoria = strtolower(trim($input['categoria'] ?? 'pasta'));
    if (!in_array($categoria, ['pasta','malote'])) $categoria = 'pasta';
    // Origem
    $origemTipo = strtolower(trim($input['origem_tipo'] ?? ''));
    if (!in_array($origemTipo, ['outro','ure','escola'])) { Session::addMessageAfterRedirect('BYPASS: Origem obrigatória', false, ERROR); Html::back(); }
    if ($origemTipo==='outro' && trim($input['origem_outro']??'')==='') { Session::addMessageAfterRedirect('BYPASS: Origem Outro vazio', false, ERROR); Html::back(); }
    if ($origemTipo==='escola' && empty($input['origem_entities_id'])) { Session::addMessageAfterRedirect('BYPASS: Origem Escola obrigatória', false, ERROR); Html::back(); }
    $origemOutro = $origemTipo==='outro' ? trim($input['origem_outro']) : null;
    $origemEnt = $origemTipo==='ure' ? 0 : ($origemTipo==='escola' ? (int)$input['origem_entities_id'] : null);
    // Destino
    $destinoTipo = strtolower(trim($input['destino_tipo'] ?? ''));
    if (!in_array($destinoTipo, ['outro','ure','escola'])) { Session::addMessageAfterRedirect('BYPASS: Destino obrigatório', false, ERROR); Html::back(); }
    if ($destinoTipo==='outro' && trim($input['destino_outro']??'')==='') { Session::addMessageAfterRedirect('BYPASS: Destino Outro vazio', false, ERROR); Html::back(); }
    if ($destinoTipo==='escola' && empty($input['destino_entities_id'])) { Session::addMessageAfterRedirect('BYPASS: Destino Escola obrigatória', false, ERROR); Html::back(); }
    $destinoOutro = $destinoTipo==='outro' ? trim($input['destino_outro']) : null;
    $destinoEnt = $destinoTipo==='ure' ? 0 : ($destinoTipo==='escola' ? (int)$input['destino_entities_id'] : null);
    // Compat escola
    $compatEscola = 0;
    if ($destinoTipo==='escola') $compatEscola = $destinoEnt;
    elseif ($origemTipo==='escola') $compatEscola = $origemEnt;
    else $compatEscola = (int)($input['plugin_protocolo_escolas_id'] ?? 0);
    if (empty($compatEscola) && empty($input['recebido_de'])) {
        // fallback antigo, mas já validamos origem/destino
    }
    if (empty($input['recebido_de'])) {
        Session::addMessageAfterRedirect('BYPASS: Recebido de obrigatório', false, ERROR);
        Html::back();
    }
    $itens = $input['itens'] ?? [];
    $filtered = [];
    foreach ($itens as $it) { $d=trim($it['descricao']??''); if($d!=='') $filtered[]=['descricao'=>$d,'quantidade'=>max(1,(int)($it['quantidade']??1)),'observacao'=>trim($it['observacao']??'')]; }
    if(!count($filtered)){ Session::addMessageAfterRedirect('BYPASS: Adicione 1 item', false, ERROR); Html::back(); }
    $tipos = array_filter(array_map('intval',(array)($input['tipos']??[])));
    // TipoArquivo check
    $ativos = 0;
    try { $it=$DB->request(['FROM'=>'glpi_plugin_protocolo_tipos','WHERE'=>['is_active'=>1]]); foreach($it as $r) $ativos++; } catch(\Throwable $e){}
    if(!$tipos && $ativos){ Session::addMessageAfterRedirect('BYPASS: Selecione 1 tipo', false, ERROR); Html::back(); }
    $codigo = \GlpiPlugin\Protocolo\Install::gerarCodigoPasta($categoria);
    $dataRec = $input['data_recebimento'] ?? date('Y-m-d H:i:s');
    if(strpos($dataRec,'T')!==false){ $dataRec=str_replace('T',' ',$dataRec); if(strlen($dataRec)===16) $dataRec.=':00'; }
    $ok = $DB->insert('glpi_plugin_protocolo_pastas', [
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
    if (!$ok) {
        $dbErr = method_exists($DB,'error') ? $DB->error() : 'insert falhou sem erro';
        throw new \RuntimeException("INSERT pastas falhou: $dbErr POST=".json_encode($input, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
    $newID=(int)$DB->insertId();
    if (!$newID) {
        $dbErr = method_exists($DB,'error') ? $DB->error() : 'insertId 0';
        throw new \RuntimeException("insertId 0 após pastas: $dbErr");
    }
    foreach($filtered as $iv) $DB->insert('glpi_plugin_protocolo_itens',['plugin_protocolo_pastas_id'=>$newID,'name'=>$iv['descricao'],'quantidade'=>$iv['quantidade'],'comment'=>$iv['observacao']?:null]);
    foreach($tipos as $tid) $DB->insert('glpi_plugin_protocolo_pastatipos',['plugin_protocolo_pastas_id'=>$newID,'plugin_protocolo_tipos_id'=>$tid]);
    $codigoTermo=\GlpiPlugin\Protocolo\Install::gerarCodigoTermo('recebimento');
    $DB->insert('glpi_plugin_protocolo_termos',['plugin_protocolo_pastas_id'=>$newID,'tipo'=>'recebimento','codigo'=>$codigoTermo,'hash_verificacao'=>bin2hex(random_bytes(16)),'users_id'=>Session::getLoginUserID(),'date_creation'=>date('Y-m-d H:i:s')]);
    try{ if(class_exists(\GlpiPlugin\Protocolo\Notificacao::class)){ $tmp=new \GlpiPlugin\Protocolo\Pasta(); $tmp->getFromDB($newID); \GlpiPlugin\Protocolo\Notificacao::createForPasta($tmp,'entrada'); } }catch(\Throwable $e){}
    Html::redirect(\GlpiPlugin\Protocolo\Pasta::getFormURLWithID($newID));
} catch(\Throwable $e){
    // Não tratar redirect (Html::redirect / Html::back lançam RedirectException em GLPI 11)
    if (is_a($e, 'Glpi\Exception\Http\RedirectException') || str_contains(get_class($e), 'Redirect')) {
        throw $e;
    }
    $msg = $e->getMessage() ?: 'sem mensagem';
    $trace = substr($e->getTraceAsString(), 0, 2000);
    $dbErr = '';
    try { if (isset($DB) && method_exists($DB, 'error')) $dbErr = $DB->error(); } catch(\Throwable $e2) {}
    error_log("[protocolo] BYPASS AJAX falhou: $msg | DBerr=$dbErr | POST=" . json_encode($_POST, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR) . " | TRACE=" . $trace);
    Session::addMessageAfterRedirect('BYPASS erro: '.$msg.' DB:'.$dbErr, false, ERROR);
    Html::back();
}
