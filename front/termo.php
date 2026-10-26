<?php
/**
 * front/termo.php - Geração de Termo (recebimento/retirada) estilo gerar_termo.php mas integrado GLPI
 * Usa autenticação GLPI (Session::checkLoginUser)
 */
include('../../../inc/includes.php');
require_once __DIR__ . '/../src/compat.php';

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
// Pastas novas não têm itens: o termo lista os tipos de arquivo marcados.
if (!$itens) { foreach ($tipos as $tn) { $itens[] = ['name' => $tn, 'quantidade' => 1, 'comment' => '']; } }

// Termo
$termo = Termo::getOrCreate($id, $tipo);
// Se já existe arquivo assinado, mantemos imprimível

$categoria = strtolower($pasta->fields['categoria'] ?? 'pasta');
$especieLabel = Pasta::getEspecieLabel($categoria, $pasta->fields['especie_outro'] ?? null);
$especieArtigo = Pasta::getEspecieArtigo($categoria);
$catLabel = mb_strtoupper($especieLabel);
$titulo = $tipo === 'recebimento' ? "TERMO DE RECEBIMENTO DE $catLabel" : "TERMO DE ENTREGA / RETIRADA DE $catLabel";
$subtitulo = $tipo === 'recebimento' ? 'Comprovante de recebimento e guarda temporária' : 'Comprovante de entrega à escola / retirada';
$dataRef = $tipo === 'recebimento' ? $pasta->fields['data_recebimento'] : ($pasta->fields['data_retirada'] ?? date('Y-m-d H:i:s'));
$responsavel = $tipo === 'recebimento' ? $pasta->fields['recebido_de'] : ($pasta->fields['retirado_por'] ?? '_________________________');
$documento = $tipo === 'recebimento' ? $pasta->fields['recebido_documento'] : ($pasta->fields['retirado_documento'] ?? '');
$entregadorNome = trim($pasta->fields['entregador_nome'] ?? '');
$entregadorDoc = trim($pasta->fields['entregador_documento'] ?? '');
// Origem/Destino para termo
$origemTipo = $pasta->fields['origem_tipo'] ?? 'ure';
$destinoTipo = $pasta->fields['destino_tipo'] ?? 'escola';
$origemOutro = $pasta->fields['origem_outro'] ?? '';
$destinoOutro = $pasta->fields['destino_outro'] ?? '';
$origemEnt = (int)($pasta->fields['origem_entities_id'] ?? 0);
$destinoEnt = (int)($pasta->fields['destino_entities_id'] ?? $pasta->fields['plugin_protocolo_escolas_id'] ?? 0);
if ($origemTipo === 'outro') $origemNome = $origemOutro ?: 'Outro';
elseif ($origemTipo === 'ure') $origemNome = 'Unidade Regional de Ensino de Jales - URE';
else $origemNome = Pasta::getEscolaName($origemEnt);
if ($destinoTipo === 'outro') $destinoNome = $destinoOutro ?: 'Outro';
elseif ($destinoTipo === 'ure') $destinoNome = 'Unidade Regional de Ensino de Jales - URE';
else $destinoNome = Pasta::getEscolaName($destinoEnt);

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
<link href="<?= protocolo_web_dir('protocolo') ?>/public/css/protocolo-modern.css" rel="stylesheet">
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
.termo table.itens{ font-size:11.5px; border-collapse:collapse; width:100%; margin-bottom:14px; border:1px solid #333; }
.termo table.itens th{ background:#1e3a5f; color:#fff; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:.6px; padding:8px 10px; border:1px solid #1e3a5f; text-align:center; }
.termo table.itens th:first-child{ border-radius:4px 0 0 0; }
.termo table.itens th:last-child{ border-radius:0 4px 0 0; }
.termo table.itens td{ padding:7px 10px; border:1px solid #d0d0d0; vertical-align:top; }
.termo table.itens tbody tr:nth-child(even){ background:#f8f9fa; }
.termo table.itens tbody tr:last-child td:first-child{ border-radius:0 0 0 4px; }
.termo table.itens tbody tr:last-child td:last-child{ border-radius:0 0 4px 0; }
.termo table.itens tfoot td{ font-weight:700; background:#e9ecef; border:1px solid #adb5bd; }
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
<div class="text-center no-print py-3 bg-white shadow-sm border-bottom">
  <button onclick="window.print()" class="pt-btn pt-btn-primary pt-btn-sm"><i class="ti ti-printer me-1"></i> <?= __('Imprimir / Salvar PDF', 'protocolo') ?></button>
  <a href="<?= Pasta::getFormURLWithID($id) ?>" class="pt-btn pt-btn-secondary pt-btn-sm ms-2"><?= __('Voltar') ?></a>
</div>

<div class="termo" id="termo">
  <div class="cab">
    <?php
    $logoWeb = protocolo_web_dir('protocolo') . '/assets/img/logo_termo.png';
    $logoFile = protocolo_php_dir('protocolo') . '/assets/img/logo_termo.png';
    if (!file_exists($logoFile)) {
        $logoWeb = protocolo_web_dir('protocolo') . '/logo.png';
        $logoFile = protocolo_php_dir('protocolo') . '/logo.png';
    }
    $logoSrc = $logoWeb;
    if (is_readable($logoFile)) {
        $logoBin = @file_get_contents($logoFile);
        if ($logoBin !== false && $logoBin !== '') {
            $logoSrc = 'data:image/png;base64,' . base64_encode($logoBin);
        }
    }
    ?>
    <?php if (file_exists($logoFile)): ?>
    <img src="<?= htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Logo" class="logo" onerror="this.style.display='none'">
    <?php endif; ?>
    <div class="org">UNIDADE REGIONAL DE ENSINO - JALES</div>
    <div class="setor">Setor de Protocolo</div>
  </div>
  <div style="text-align:center; font-size:10.5px; color:#555; margin-bottom:10px; letter-spacing:.2px;">
    Pasta <strong><?= htmlspecialchars($pasta->fields['codigo']) ?></strong> &middot; Termo <strong><?= htmlspecialchars($termo['codigo']) ?></strong> &middot; <?= $tipo === 'recebimento' ? 'Recebimento' : 'Retirada' ?> &middot; <?= date('d/m/Y H:i', strtotime($dataRef)) ?>
  </div>

  <table class="table table-bordered meta">
    <tr><td style="width:50%"><strong><?= __('Espécie', 'protocolo') ?>:</strong><br><?= htmlspecialchars($especieLabel) ?> (<?= htmlspecialchars($pasta->fields['codigo']) ?>)</td>
        <td><strong><?= __('Data/hora', 'protocolo') ?>:</strong><br><?= date('d/m/Y \à\s H:i', strtotime($dataRef)) ?></td></tr>
    <?php if (!empty($pasta->fields['assunto'])): ?><tr><td colspan="2"><strong><?= __('Assunto', 'protocolo') ?>:</strong> <?= htmlspecialchars($pasta->fields['assunto']) ?></td></tr><?php endif; ?>
    <tr><td><strong><?= __('Origem', 'protocolo') ?>:</strong><br><?= htmlspecialchars($origemNome) ?></td>
        <td><strong><?= __('Destino', 'protocolo') ?> / <?= __('Destinatário', 'protocolo') ?>:</strong><br><?= htmlspecialchars($destinoNome) ?></td></tr>
    <?php if ($tipo === 'recebimento'): ?>
    <tr><td><strong><?= __('Recebido de:', 'protocolo') ?></strong><br><?= htmlspecialchars($responsavel) ?><?= $documento ? ' (' . htmlspecialchars($documento) . ')' : '' ?></td>
        <td><strong><?= __('Responsável protocolo:', 'protocolo') ?></strong><br><?= htmlspecialchars($criadorNome) ?></td></tr>
    <?php else: ?>
    <tr><td><strong><?= __('Entregue por (protocolo):', 'protocolo') ?></strong><br><?= htmlspecialchars($entregadorNome !== '' ? $entregadorNome : $criadorNome) ?><?= $entregadorDoc ? ' (' . htmlspecialchars($entregadorDoc) . ')' : '' ?></td>
        <td><strong><?= __('Retirado por:', 'protocolo') ?></strong><br><?= htmlspecialchars($responsavel) ?><?= $documento ? ' (' . htmlspecialchars($documento) . ')' : '' ?></td></tr>
    <?php endif; ?>
    <?php if ($escolaEndereco && $destinoTipo==='escola'): ?><tr><td colspan="2"><strong><?= __('Endereço escola', 'protocolo') ?>:</strong> <?= htmlspecialchars($escolaEndereco) ?></td></tr><?php endif; ?>
  </table>

  <?php if ($tipo === 'recebimento'): ?>
    <p><?= __('Declaro para os devidos fins que', 'protocolo') ?> <strong><?= __('recebi nesta data', 'protocolo') ?></strong> <?= __('no setor de protocolo', 'protocolo') ?> <?= $especieArtigo ?> <?= htmlspecialchars(mb_strtolower($especieLabel)) ?> <?= __('identificado acima, com origem em', 'protocolo') ?> <strong><?= htmlspecialchars($origemNome) ?></strong> <?= __('e destino à', 'protocolo') ?> <strong><?= htmlspecialchars($destinoNome) ?></strong>, <?= __('entregue por', 'protocolo') ?> <strong><?= htmlspecialchars($pasta->fields['recebido_de']) ?></strong><?= $pasta->fields['recebido_documento'] ? ' (doc. ' . htmlspecialchars($pasta->fields['recebido_documento']) . ')' : '' ?>, <?= __('contendo os seguintes itens/documentos:', 'protocolo') ?></p>
  <?php else: ?>
    <p><?= __('Declaro para os devidos fins que', 'protocolo') ?> <strong><?= __('retirei nesta data', 'protocolo') ?></strong> <?= __('junto ao setor de protocolo', 'protocolo') ?> <?= $especieArtigo ?> <?= htmlspecialchars(mb_strtolower($especieLabel)) ?> <?= __('identificado acima, com origem em', 'protocolo') ?> <strong><?= htmlspecialchars($origemNome) ?></strong> <?= __('e destino à', 'protocolo') ?> <strong><?= htmlspecialchars($destinoNome) ?></strong>, <?= __('contendo os seguintes itens/documentos, assumindo a responsabilidade pelo transporte e entrega:', 'protocolo') ?></p>
  <?php endif; ?>

  <table class="itens">
    <thead><tr><th style="width:45px">#</th><th style="text-align:left"><i class="ti ti-file-text" style="margin-right:4px"></i><?= __('Descrição do item/documento', 'protocolo') ?></th><th style="width:75px">Qtd</th><th style="text-align:left"><?= __('Observação', 'protocolo') ?></th></tr></thead>
    <tbody>
      <?php foreach ($itens as $i => $it): ?>
        <tr><td style="text-align:center; font-weight:600; color:#1e3a5f"><?= $i + 1 ?></td><td><?= htmlspecialchars($it['name']) ?></td><td style="text-align:center; font-weight:600"><?= (int)$it['quantidade'] ?></td><td class="small text-muted"><?= nl2br(htmlspecialchars($it['comment'] ?? '')) ?: '<span style="color:#adb5bd">—</span>' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$itens): ?><tr><td colspan="4" class="text-center text-muted" style="padding:16px"><?= __('Sem itens cadastrados.', 'protocolo') ?></td></tr><?php endif; ?>
    </tbody>
    <?php if ($itens): $totalQtd = array_sum(array_column($itens, 'quantidade')); ?>
    <tfoot><tr><td colspan="2" style="text-align:right">Total de itens: <?= count($itens) ?> &nbsp;|&nbsp; Quantidade total:</td><td style="text-align:center"><?= $totalQtd ?></td><td></td></tr></tfoot>
    <?php endif; ?>
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

  <?php
  $localFixo = 'R. Oito, 2315 - Centro, Jales - SP, 15700-066';
  $dataFormatada = date('d/m/Y', strtotime($dataRef));
  $horaFormatada = date('H:i', strtotime($dataRef));
  ?>
  <p style="text-align:center; margin-top:25px; font-size:13px;"><?= __('Local:', 'protocolo') ?> <?= htmlspecialchars($localFixo) ?> &nbsp;&nbsp; <?= __('Data:', 'protocolo') ?> <?= htmlspecialchars($dataFormatada) ?> &nbsp;&nbsp; <?= __('Hora:', 'protocolo') ?> <?= htmlspecialchars($horaFormatada) ?></p>

  <div class="assinaturas">
    <div class="assinatura">
      <?php
      $sigImg = '';
      $sigDataFmt = '';
      $sigNome = '';
      $sigDoc = '';
      if ($tipo === 'retirada') {
          // Quem ENTREGA (protocolo): usa SOMENTE os dados do entregador.
          // Nunca cai para retirada_* (que é de quem RECEBE) — isso duplicava a assinatura.
          if (!empty($pasta->fields['entregador_assinatura_image'])) {
              $sigImg = $pasta->fields['entregador_assinatura_image'];
              if (!empty($pasta->fields['entregador_assinatura_data'])) $sigDataFmt = date('d/m/Y H:i', strtotime($pasta->fields['entregador_assinatura_data']));
          }
          $sigNome = $entregadorNome;
          $sigDoc = $entregadorDoc;
      } elseif ($tipo === 'recebimento' && !empty($pasta->fields['recebido_assinatura_image'])) {
          $sigImg = $pasta->fields['recebido_assinatura_image'];
          if (!empty($pasta->fields['recebido_assinatura_data'])) $sigDataFmt = date('d/m/Y H:i', strtotime($pasta->fields['recebido_assinatura_data']));
          $sigNome = $pasta->fields['recebido_de'] ?? '';
          $sigDoc = $pasta->fields['recebido_documento'] ?? '';
      }
      ?>
      <?php if ($sigImg): ?><img src="<?= htmlspecialchars($sigImg) ?>" alt="Assinatura" style="max-height:70px;max-width:100%;object-fit:contain;"><br><?php endif; ?>
      <?= $tipo === 'recebimento' ? __('Assinatura de quem entregou', 'protocolo') : __('Assinatura de quem entregou (protocolo)', 'protocolo') ?><br>
      <?php
      // Nome do entregador: em retirada NUNCA usar $responsavel (é de quem recebe).
      if ($tipo === 'retirada') {
          $sigNomeShow = $sigNome !== '' ? $sigNome : '__________________________';
      } else {
          $sigNomeShow = $sigNome !== '' ? $sigNome : (string)$responsavel;
      }
      ?>
      <strong><?= htmlspecialchars($sigNomeShow) ?></strong><?php if ($sigDoc !== ''): ?><br><small><?= htmlspecialchars($sigDoc) ?></small><?php endif; ?>
      <?php if ($sigImg && $sigDataFmt): ?><br><small>Assinado digitalmente em <?= htmlspecialchars($sigDataFmt) ?></small><?php endif; ?>
    </div>
    <div class="assinatura">
      <?php
      if ($tipo === 'retirada') {
          $recbImg = !empty($pasta->fields['retirada_assinatura_image']) ? $pasta->fields['retirada_assinatura_image'] : '';
          $recbDataFmt = !empty($pasta->fields['retirada_assinatura_data']) ? date('d/m/Y H:i', strtotime($pasta->fields['retirada_assinatura_data'])) : '';
          $recbNome = trim($pasta->fields['retirado_por'] ?? '');
          $recbDoc = trim($pasta->fields['retirado_documento'] ?? '');
          $recbLabel = __('Assinatura de quem recebeu (retirou)', 'protocolo');
      } else {
          $recbImg = !empty($pasta->fields['recebedor_assinatura_image']) ? $pasta->fields['recebedor_assinatura_image'] : '';
          $recbDataFmt = !empty($pasta->fields['recebedor_assinatura_data']) ? date('d/m/Y H:i', strtotime($pasta->fields['recebedor_assinatura_data'])) : '';
          $recbNome = trim($pasta->fields['recebedor_nome'] ?? '');
          $recbDoc = trim($pasta->fields['recebedor_documento'] ?? '');
          $recbLabel = 'Assinatura de quem recebeu';
      }
      ?>
      <?php if ($recbImg): ?><img src="<?= htmlspecialchars($recbImg) ?>" alt="Assinatura" style="max-height:70px;max-width:100%;object-fit:contain;"><br><?php endif; ?>
      <?= $recbLabel ?><br>
      <strong><?= $recbNome !== '' ? htmlspecialchars($recbNome) : htmlspecialchars($criadorNome) ?></strong>
      <?php if ($recbDoc !== ''): ?><br><small><?= htmlspecialchars($recbDoc) ?></small><?php endif; ?>
      <?php if ($recbImg && $recbDataFmt): ?><br><small>Assinado digitalmente em <?= htmlspecialchars($recbDataFmt) ?></small><?php endif; ?>
    </div>
  </div>

  <?php if ($tipo === 'retirada' && $pasta->fields['status'] !== 'retirada'): ?>
    <div class="alert alert-warning mt-4 no-print" style="font-size:12px;"><?= __('Atenção: esta pasta ainda consta como', 'protocolo') ?> <strong><?= __('aguardando', 'protocolo') ?></strong>. <?= __('Registre a retirada em "Pasta → Registrar retirada" antes de imprimir o termo definitivo.', 'protocolo') ?></div>
  <?php endif; ?>

  <div class="hash">
    <div style="flex:1">
      <strong><?= __('Código de verificação:', 'protocolo') ?></strong> <?= htmlspecialchars($termo['codigo']) ?> &middot; Hash: <?= htmlspecialchars($termo['hash_verificacao'] ?? '') ?> &middot; Pasta: <?= htmlspecialchars($pasta->fields['codigo']) ?><br>
      <?= __('Termo gerado em', 'protocolo') ?> <?= date('d/m/Y H:i:s') ?> <?= __('por', 'protocolo') ?> <?= htmlspecialchars($_SESSION['glpiname'] ?? '') ?> &middot; <?= __('Sistema de Protocolo - Autenticidade mediante consulta no setor.', 'protocolo') ?>
    </div>

  </div>

  <div class="text-center mt-3 no-print">
    <small class="text-muted"><?= __('Assinaturas coletadas digitalmente no tablet.', 'protocolo') ?></small>
  </div>
</div>
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


})();
</script>
</body>
</html>
