<?php
/**
 * front/termo.php - Geração de Termo (recebimento/retirada) estilo gerar_termo.php mas integrado GLPI
 * Usa autenticação GLPI (Session::checkLoginUser)
 */
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Pasta;
use GlpiPlugin\Protocolo\Escola;
use GlpiPlugin\Protocolo\TipoArquivo;
use GlpiPlugin\Protocolo\Termo;
use GlpiPlugin\Protocolo\Install;

Session::checkLoginUser();

if (!Pasta::canView()) {
    Html::displayRightError();
}

$id = (int)($_GET['id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'recebimento';
if (!in_array($tipo, ['recebimento', 'retirada'])) $tipo = 'recebimento';

global $DB, $CFG_GLPI;

$pasta = new Pasta();
if (!$pasta->getFromDB($id)) {
    Html::displayErrorAndDie(__('Pasta não encontrada', 'protocolo'));
}

// Busca escola
$escola = new Escola();
$escola->getFromDB($pasta->fields['plugin_protocolo_escolas_id']);
$escolaNome = $escola->fields['name'] ?? '-';
$escolaCodigo = $escola->fields['codigo'] ?? '';
$escolaEndereco = $escola->fields['address'] ?? '';

// Itens
$itens = [];
$it = $DB->request(['FROM' => 'glpi_plugin_protocolo_itens', 'WHERE' => ['plugin_protocolo_pastas_id' => $id], 'ORDER' => 'id']);
foreach ($it as $row) $itens[] = $row;

// Tipos
$tipos = [];
$it2 = $DB->request([
    'SELECT' => ['t.name'],
    'FROM' => 'glpi_plugin_protocolo_pastatipos as pt',
    'LEFT JOIN' => ['glpi_plugin_protocolo_tipos as t' => ['FKEY' => ['pt' => 'plugin_protocolo_tipos_id', 't' => 'id']]],
    'WHERE' => ['pt.plugin_protocolo_pastas_id' => $id],
    'ORDER' => 't.name'
]);
foreach ($it2 as $r) $tipos[] = $r['name'];

// Termo
$termo = Termo::getOrCreate($id, $tipo);
// Se já existe arquivo assinado, mantemos imprimível

$titulo = $tipo === 'recebimento' ? 'TERMO DE RECEBIMENTO DE PASTA' : 'TERMO DE ENTREGA / RETIRADA DE PASTA';
$subtitulo = $tipo === 'recebimento' ? 'Comprovante de recebimento e guarda temporária' : 'Comprovante de entrega à escola / retirada';
$dataRef = $tipo === 'recebimento' ? $pasta->fields['data_recebimento'] : ($pasta->fields['data_retirada'] ?? date('Y-m-d H:i:s'));
$responsavel = $tipo === 'recebimento' ? $pasta->fields['recebido_de'] : ($pasta->fields['retirado_por'] ?? '_________________________');
$documento = $tipo === 'recebimento' ? $pasta->fields['recebido_documento'] : ($pasta->fields['retirado_documento'] ?? '');

// Criador
$criadorNome = getUserName($pasta->fields['users_id'] ?? 0);
if (empty($criadorNome) || $criadorNome === 'N/A') $criadorNome = $_SESSION['glpiname'] ?? '-';

?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($titulo) ?> - <?= htmlspecialchars($pasta->fields['codigo']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="<?= $CFG_GLPI['root_doc'] ?>/public/lib/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<style>
body{ background:#eee; }
.termo{ background:white; max-width:800px; margin:20px auto; padding:28px 30px; box-shadow:0 0 15px rgba(0,0,0,.1); font-family:"Times New Roman", serif; color:#111; display:flex; flex-direction:column; }
.termo .cab{ text-align:center; border-bottom:2px solid #111; padding-bottom:10px; margin-bottom:14px; }
.termo .cab .logo{ max-height:62px; max-width:100%; object-fit:contain; margin-bottom:6px; }
.termo .cab .org{ font-size:13px; font-weight:bold; letter-spacing:.3px; color:#222; }
.termo .cab .setor{ font-size:11px; color:#333; }
.termo .meta{ font-size:11.5px; margin-bottom:12px; }
.termo .meta td{ padding:4px 7px; }
.termo p{ font-size:11.5px; line-height:1.45; text-align:justify; margin-bottom:8px; }
.termo table.itens{ font-size:11px; }
.termo table.itens th, .termo table.itens td{ padding:3px 6px; }
.termo .assinaturas{ margin-top:24px; display:flex; gap:30px; justify-content:space-between; }
.termo .assinatura{ flex:1; border-top:1px solid #000; padding-top:5px; text-align:center; font-size:10.5px; }
.termo .hash{ margin-top:16px; border:1px dashed #999; padding:6px 8px; font-size:9.5px; background:#fafafa; line-height:1.3; }
@media print {
  .no-print{ display:none!important; }
  @page{ size:A4; margin:8mm; }
  body{ background:white; margin:0; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .termo{ box-shadow:none; margin:0 auto; padding:14px 16px; max-height:277mm; overflow:hidden; }
  .termo .cab{ padding-bottom:7px; margin-bottom:10px; }
  .termo .cab .logo{ max-height:52px; }
}
</style>
</head>
<body>
<div class="text-center no-print py-4 bg-white shadow-sm border-bottom">
  <button onclick="window.print()" class="btn btn-primary btn-lg px-5 py-3 shadow" style="background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); border:none; font-weight:700; font-size:1.15rem; letter-spacing:0.3px; box-shadow:0 4px 15px rgba(13,110,253,.35) !important;"><i class="ti ti-printer me-2" style="font-size:1.3rem; vertical-align:-2px;"></i> <?= __('Imprimir / Salvar PDF', 'protocolo') ?></button>
  <a href="<?= Pasta::getFormURLWithID($id) ?>" class="btn btn-outline-secondary ms-2 px-4"><?= __('Voltar') ?></a>
  <div class="small text-muted mt-3"><i class="ti ti-info-circle me-1"></i><?= __('Dica: na janela de impressão, escolha "Salvar como PDF" para gerar o termo sem assinatura. Depois imprima, colete assinaturas e digitalize para upload no sistema.', 'protocolo') ?></div>
</div>

<div class="termo" id="termo">
  <div class="cab">
    <?php
    $logoPath = Plugin::getWebDir('protocolo') . '/assets/img/logo.png';
    // fallback para logo do GLPI se não existir
    $logoFile = GLPI_ROOT . '/plugins/protocolo/assets/img/logo.png';
    if (!file_exists($logoFile)) $logoFile = GLPI_ROOT . '/pics/logo.png';
    ?>
    <img src="<?= Plugin::getWebDir('protocolo') ?>/assets/img/logo.png" alt="Logo" class="logo" onerror="this.style.display='none'">
    <div class="org">UNIDADE REGIONAL DE ENSINO - JALES</div>
    <div class="setor">Setor de Protocolo</div>
  </div>
  <div style="text-align:center; font-size:10.5px; color:#555; margin-bottom:10px; letter-spacing:.2px;">
    Pasta <strong><?= htmlspecialchars($pasta->fields['codigo']) ?></strong> &middot; Termo <strong><?= htmlspecialchars($termo['codigo']) ?></strong> &middot; <?= $tipo === 'recebimento' ? 'Recebimento' : 'Retirada' ?> &middot; <?= date('d/m/Y H:i', strtotime($dataRef)) ?>
  </div>

  <table class="table table-bordered meta">
    <tr><td style="width:50%"><strong><?= __('Escola destinatária', 'protocolo') ?>:</strong><br><?= htmlspecialchars($escolaNome) ?> <?= $escolaCodigo ? '(' . htmlspecialchars($escolaCodigo) . ')' : '' ?></td>
        <td><strong><?= __('Data/hora', 'protocolo') ?>:</strong><br><?= date('d/m/Y \à\s H:i', strtotime($dataRef)) ?></td></tr>
    <tr><td><strong><?= $tipo === 'recebimento' ? __('Recebido de:', 'protocolo') : __('Retirado por:', 'protocolo') ?></strong><br><?= htmlspecialchars($responsavel) ?><?= $documento ? ' (' . htmlspecialchars($documento) . ')' : '' ?></td>
        <td><strong><?= __('Responsável protocolo:', 'protocolo') ?></strong><br><?= htmlspecialchars($criadorNome) ?></td></tr>
    <?php if ($escolaEndereco): ?><tr><td colspan="2"><strong><?= __('Endereço escola', 'protocolo') ?>:</strong> <?= htmlspecialchars($escolaEndereco) ?></td></tr><?php endif; ?>
  </table>

  <?php if ($tipo === 'recebimento'): ?>
    <p><?= __('Declaro para os devidos fins que', 'protocolo') ?> <strong><?= __('recebi nesta data', 'protocolo') ?></strong> <?= __('no setor de protocolo a pasta identificada acima, destinada à', 'protocolo') ?> <strong><?= htmlspecialchars($escolaNome) ?></strong>, <?= __('entregue por', 'protocolo') ?> <strong><?= htmlspecialchars($pasta->fields['recebido_de']) ?></strong><?= $pasta->fields['recebido_documento'] ? ' (doc. ' . htmlspecialchars($pasta->fields['recebido_documento']) . ')' : '' ?>, <?= __('contendo os seguintes itens/documentos:', 'protocolo') ?></p>
  <?php else: ?>
    <p><?= __('Declaro para os devidos fins que', 'protocolo') ?> <strong><?= __('retirei nesta data', 'protocolo') ?></strong> <?= __('junto ao setor de protocolo a pasta identificada acima, destinada à', 'protocolo') ?> <strong><?= htmlspecialchars($escolaNome) ?></strong>, <?= __('contendo os seguintes itens/documentos, assumindo a responsabilidade pelo transporte e entrega:', 'protocolo') ?></p>
  <?php endif; ?>

  <?php if ($tipos): ?>
  <div style="border:1px solid #111; padding:10px 12px; margin-bottom:14px; font-size:13px;">
    <strong><?= __('Tipos de arquivos assinalados:', 'protocolo') ?></strong><br>
    <div style="margin-top:6px; display:flex; flex-wrap:wrap; gap:6px 14px;">
      <?php foreach ($tipos as $tn): ?><span style="border:1px solid #333; padding:2px 8px; font-size:12px;"><span style="font-weight:bold;">☑</span> <?= htmlspecialchars($tn) ?></span><?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <table class="table table-bordered itens">
    <thead class="table-light"><tr><th style="width:40px">#</th><th><?= __('Descrição do item/documento', 'protocolo') ?></th><th style="width:70px">Qtd</th><th><?= __('Observação', 'protocolo') ?></th></tr></thead>
    <tbody>
      <?php foreach ($itens as $i => $it): ?>
        <tr><td><?= $i + 1 ?></td><td><?= htmlspecialchars($it['name']) ?></td><td><?= (int)$it['quantidade'] ?></td><td><?= htmlspecialchars($it['comment'] ?? '') ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$itens): ?><tr><td colspan="4" class="text-center text-muted"><?= __('Sem itens cadastrados.', 'protocolo') ?></td></tr><?php endif; ?>
    </tbody>
  </table>

  <?php if ($pasta->fields['observacao'] && $tipo === 'recebimento'): ?><p><strong><?= __('Observações (recebimento):', 'protocolo') ?></strong> <?= nl2br(htmlspecialchars($pasta->fields['observacao'])) ?></p><?php endif; ?>
  <?php if ($pasta->fields['observacao_retirada'] && $tipo === 'retirada'): ?><p><strong><?= __('Observações (retirada):', 'protocolo') ?></strong> <?= nl2br(htmlspecialchars($pasta->fields['observacao_retirada'])) ?></p><?php endif; ?>

  <p>
    <?php if ($tipo === 'recebimento'): ?>
      <?= __('A pasta ficará sob guarda temporária deste setor até a retirada pela escola destinatária, mediante apresentação deste termo ou identificação do responsável. Este termo é emitido em 2 (duas) vias de igual teor.', 'protocolo') ?>
    <?php else: ?>
      <?= __('A partir desta retirada, o setor de protocolo fica isento de responsabilidade sobre o conteúdo da pasta ora entregue. Este termo é emitido em 2 (duas) vias.', 'protocolo') ?>
    <?php endif; ?>
  </p>

  <p style="text-align:center; margin-top:25px; font-size:13px;"><?= __('Local/data: ______________________, ___/___/______ &nbsp;&nbsp; Hora: ____:____', 'protocolo') ?></p>

  <div class="assinaturas">
    <div class="assinatura">
      <?= $tipo === 'recebimento' ? __('Assinatura de quem entregou', 'protocolo') : __('Assinatura de quem retirou', 'protocolo') ?><br>
      <strong><?= htmlspecialchars($responsavel) ?></strong><br>
      <small><?= htmlspecialchars($documento) ?></small>
    </div>
    <div class="assinatura">
      <?= __('Assinatura / Carimbo do setor de protocolo', 'protocolo') ?><br>
      <strong><?= htmlspecialchars($criadorNome) ?></strong>
    </div>
  </div>

  <?php if ($tipo === 'retirada' && $pasta->fields['status'] !== 'retirada'): ?>
    <div class="alert alert-warning mt-4 no-print" style="font-size:12px;"><?= __('Atenção: esta pasta ainda consta como', 'protocolo') ?> <strong><?= __('aguardando', 'protocolo') ?></strong>. <?= __('Registre a retirada em "Pasta → Registrar retirada" antes de imprimir o termo definitivo.', 'protocolo') ?></div>
  <?php endif; ?>

  <div class="hash d-flex justify-content-between align-items-center gap-3">
    <div style="flex:1">
      <strong><?= __('Código de verificação:', 'protocolo') ?></strong> <?= htmlspecialchars($termo['codigo']) ?> &middot; Hash: <?= htmlspecialchars($termo['hash_verificacao'] ?? '') ?> &middot; Pasta: <?= htmlspecialchars($pasta->fields['codigo']) ?><br>
      <?= __('Termo gerado em', 'protocolo') ?> <?= date('d/m/Y H:i:s') ?> <?= __('por', 'protocolo') ?> <?= htmlspecialchars($_SESSION['glpiname'] ?? '') ?> &middot; <?= __('Sistema de Protocolo - Autenticidade mediante consulta no setor.', 'protocolo') ?>
    </div>
    <div class="text-center" style="flex-shrink:0">
      <div id="qrcode" style="background:white; padding:4px; border:1px solid #ccc; display:inline-block"></div>
      <div class="small text-muted" style="font-size:8px; margin-top:2px">Escaneie para verificar</div>
    </div>
  </div>

  <div class="text-center mt-3 no-print">
    <small class="text-muted"><?= __('Após imprimir e colher assinaturas, digitalize e faça upload em Pasta → Termos → "Enviar arquivo assinado".', 'protocolo') ?></small>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function(){
  function fitOnePage(){
    const t=document.getElementById('termo');
    if(!t) return;
    t.style.zoom='1';
    const maxH=1050;
    const h=t.scrollHeight;
    if(h>maxH){
      const s=(maxH/h).toFixed(4);
      t.style.zoom=s;
    }
  }
  window.addEventListener('load', fitOnePage);
  window.addEventListener('beforeprint', fitOnePage);
  window.addEventListener('afterprint', ()=>{ const t=document.getElementById('termo'); if(t) t.style.zoom='1'; });

  // QR Code para verificação
  document.addEventListener('DOMContentLoaded', function(){
    var codigo = "<?= addslashes($termo['codigo']) ?>";
    var hash = "<?= addslashes($termo['hash_verificacao'] ?? '') ?>";
    var base = window.location.origin + "<?= $CFG_GLPI['root_doc'] ?>";
    var verifyUrl = base + "/plugins/protocolo/front/verify.php?codigo=" + encodeURIComponent(codigo) + "&hash=" + encodeURIComponent(hash);
    var el = document.getElementById('verifyUrlText');
    if (el) el.textContent = verifyUrl;
    var qrel = document.getElementById('qrcode');
    if (qrel && typeof QRCode !== 'undefined') {
      new QRCode(qrel, {text: verifyUrl, width: 80, height: 80, correctLevel: QRCode.CorrectLevel.M});
    }
  });
})();
</script>
</body>
</html>
