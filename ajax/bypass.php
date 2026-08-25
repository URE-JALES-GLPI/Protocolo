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
    if (empty($input['plugin_protocolo_escolas_id']) || empty($input['recebido_de'])) {
        Session::addMessageAfterRedirect('BYPASS: Escola e Recebido de obrigatórios', false, ERROR);
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
    $codigo = \GlpiPlugin\Protocolo\Install::gerarCodigoPasta();
    $dataRec = $input['data_recebimento'] ?? date('Y-m-d H:i:s');
    if(strpos($dataRec,'T')!==false){ $dataRec=str_replace('T',' ',$dataRec); if(strlen($dataRec)===16) $dataRec.=':00'; }
    $DB->insert('glpi_plugin_protocolo_pastas', [
        'codigo'=>$codigo,
        'plugin_protocolo_escolas_id'=>(int)$input['plugin_protocolo_escolas_id'],
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
    $newID=(int)$DB->insertId();
    foreach($filtered as $iv) $DB->insert('glpi_plugin_protocolo_itens',['plugin_protocolo_pastas_id'=>$newID,'name'=>$iv['descricao'],'quantidade'=>$iv['quantidade'],'comment'=>$iv['observacao']?:null]);
    foreach($tipos as $tid) $DB->insert('glpi_plugin_protocolo_pastatipos',['plugin_protocolo_pastas_id'=>$newID,'plugin_protocolo_tipos_id'=>$tid]);
    $codigoTermo=\GlpiPlugin\Protocolo\Install::gerarCodigoTermo('recebimento');
    $DB->insert('glpi_plugin_protocolo_termos',['plugin_protocolo_pastas_id'=>$newID,'tipo'=>'recebimento','codigo'=>$codigoTermo,'hash_verificacao'=>bin2hex(random_bytes(16)),'users_id'=>Session::getLoginUserID(),'date_creation'=>date('Y-m-d H:i:s')]);
    try{ if(class_exists(\GlpiPlugin\Protocolo\Notificacao::class)){ $tmp=new \GlpiPlugin\Protocolo\Pasta(); $tmp->getFromDB($newID); \GlpiPlugin\Protocolo\Notificacao::createForPasta($tmp,'entrada'); } }catch(\Throwable $e){}
    Html::redirect(\GlpiPlugin\Protocolo\Pasta::getFormURLWithID($newID));
} catch(\Throwable $e){ error_log("[protocolo] BYPASS AJAX falhou: ".$e->getMessage()); Session::addMessageAfterRedirect('BYPASS erro: '.$e->getMessage(), false, ERROR); Html::back(); }
