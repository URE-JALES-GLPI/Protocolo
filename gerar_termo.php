<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pdo = getPDO();

$id = (int)($_GET['id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'recebimento';
if (!in_array($tipo, ['recebimento','retirada'])) $tipo='recebimento';

$stmt=$pdo->prepare("SELECT p.*, e.nome AS escola_nome, e.codigo AS escola_codigo, e.endereco AS escola_endereco, u.nome AS criador FROM pastas p JOIN escolas e ON e.id=p.escola_id LEFT JOIN usuarios u ON u.id=p.criado_por WHERE p.id=?");
$stmt->execute([$id]); $p=$stmt->fetch();
if (!$p) die('Pasta não encontrada');

$itens=$pdo->prepare("SELECT * FROM pasta_itens WHERE pasta_id=? ORDER BY id"); $itens->execute([$id]); $itens=$itens->fetchAll();
try { $tipos=$pdo->prepare("SELECT t.nome FROM tipos_arquivo t JOIN pasta_tipos pt ON pt.tipo_id=t.id WHERE pt.pasta_id=? ORDER BY t.nome"); $tipos->execute([$id]); $tipos=$tipos->fetchAll(PDO::FETCH_COLUMN); } catch(PDOException $e) { $tipos=[]; }
$termo=$pdo->prepare("SELECT * FROM termos WHERE pasta_id=? AND tipo=? ORDER BY id DESC LIMIT 1"); $termo->execute([$id,$tipo]); $termo=$termo->fetch();

// Se não existe termo ainda, cria na hora (evita erro ao imprimir)
if (!$termo) {
    $codigo = gerarCodigoTermo($tipo);
    $pdo->prepare("INSERT INTO termos (pasta_id,tipo,codigo,hash_verificacao,criado_por) VALUES (?,?,?,?,?)")->execute([$id,$tipo,$codigo,bin2hex(random_bytes(16)),$_SESSION['usuario_id']]);
    $termo=['codigo'=>$codigo,'hash_verificacao'=>bin2hex(random_bytes(16)),'criado_em'=>date('Y-m-d H:i:s')];
}

// Se já existe arquivo assinado, opcionalmente redirecionar? Mantemos termo imprimível e aviso.
// Dados para termo
$titulo = $tipo==='recebimento' ? 'TERMO DE RECEBIMENTO DE PASTA' : 'TERMO DE ENTREGA / RETIRADA DE PASTA';
$subtitulo = $tipo==='recebimento' ? 'Comprovante de recebimento e guarda temporária' : 'Comprovante de entrega à escola / retirada';
$dataRef = $tipo==='recebimento' ? $p['data_recebimento'] : ($p['data_retirada'] ?? date('Y-m-d H:i:s'));
$responsavel = $tipo==='recebimento' ? $p['recebido_de'] : ($p['retirado_por'] ?? '_________________________');
$documento = $tipo==='recebimento' ? $p['recebido_documento'] : ($p['retirado_documento'] ?? '');

?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($titulo) ?> - <?= htmlspecialchars($p['codigo']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
@media print { .no-print{ display:none!important; } @page{ margin:15mm; } }
body{ background:#eee; }
.termo{ background:white; max-width:850px; margin:20px auto; padding:50px 45px; box-shadow:0 0 15px rgba(0,0,0,.1); font-family:"Times New Roman", serif; color:#111; }
.termo h1{ font-size:18px; font-weight:bold; text-align:center; margin-bottom:4px; letter-spacing:.5px; }
.termo h2{ font-size:13px; font-weight:normal; text-align:center; color:#444; margin-bottom:20px; }
.termo .cab{ text-align:center; border-bottom:2px solid #111; padding-bottom:12px; margin-bottom:20px; }
.termo .cab small{ font-size:12px; }
.termo .meta{ font-size:13px; margin-bottom:18px; }
.termo .meta td{ padding:4px 8px; }
.termo p{ font-size:13.5px; line-height:1.6; text-align:justify; }
.termo table.itens{ font-size:13px; }
.termo .assinaturas{ margin-top:55px; display:flex; gap:40px; justify-content:space-between; }
.termo .assinatura{ flex:1; border-top:1px solid #000; padding-top:6px; text-align:center; font-size:12px; }
.termo .hash{ margin-top:30px; border:1px dashed #999; padding:8px 10px; font-size:11px; background:#fafafa; }
</style>
</head>
<body>
<div class="text-center no-print py-3">
  <button onclick="window.print()" class="btn btn-primary"><i>🖨️</i> Imprimir / Salvar PDF</button>
  <a href="pasta_view.php?id=<?= (int)$id ?>" class="btn btn-secondary">Voltar</a>
  <div class="small text-muted mt-2">Dica: na janela de impressão, escolha "Salvar como PDF" para gerar o termo sem assinatura. Depois imprima, colete assinaturas e digitalize para upload no sistema.</div>
</div>

<div class="termo">
  <div class="cab">
    <!-- personalize aqui com brasão/nome da secretaria -->
    <div style="font-size:15px; font-weight:bold;">PREFEITURA / SECRETARIA DE EDUCAÇÃO</div>
    <div style="font-size:12px;">Setor de Protocolo de Pastas</div>
    <small><?= htmlspecialchars($titulo) ?> &middot; <?= htmlspecialchars($subtitulo) ?></small>
  </div>

  <h1><?= htmlspecialchars($titulo) ?></h1>
  <h2>Nº <?= htmlspecialchars($termo['codigo']) ?> &middot; Pasta <?= htmlspecialchars($p['codigo']) ?></h2>

  <table class="table table-bordered meta">
    <tr><td style="width:50%"><strong>Escola destinatária:</strong><br><?= htmlspecialchars($p['escola_nome']) ?> <?= $p['escola_codigo']?'('.htmlspecialchars($p['escola_codigo']).')':'' ?></td>
        <td><strong>Data/hora:</strong><br><?= date('d/m/Y \à\s H:i', strtotime($dataRef)) ?></td></tr>
    <tr><td><strong><?= $tipo==='recebimento'?'Recebido de:':'Retirado por:' ?></strong><br><?= htmlspecialchars($responsavel) ?><?= $documento?' ('.htmlspecialchars($documento).')':'' ?></td>
        <td><strong>Responsável protocolo:</strong><br><?= htmlspecialchars($p['criador'] ?? $_SESSION['usuario_nome'] ?? '-') ?></td></tr>
    <?php if($p['escola_endereco']): ?><tr><td colspan="2"><strong>Endereço escola:</strong> <?= htmlspecialchars($p['escola_endereco']) ?></td></tr><?php endif; ?>
  </table>

  <?php if($tipo==='recebimento'): ?>
    <p>Declaro para os devidos fins que <strong>recebi nesta data</strong> no setor de protocolo a pasta identificada acima, destinada à <strong><?= htmlspecialchars($p['escola_nome']) ?></strong>, entregue por <strong><?= htmlspecialchars($p['recebido_de']) ?></strong><?= $p['recebido_documento']?' (doc. '.htmlspecialchars($p['recebido_documento']).')':'' ?>, contendo os seguintes itens/documentos:</p>
  <?php else: ?>
    <p>Declaro para os devidos fins que <strong>retirei nesta data</strong> junto ao setor de protocolo a pasta identificada acima, destinada à <strong><?= htmlspecialchars($p['escola_nome']) ?></strong>, contendo os seguintes itens/documentos, assumindo a responsabilidade pelo transporte e entrega:</p>
  <?php endif; ?>

  <?php if ($tipos): ?>
  <div style="border:1px solid #111; padding:10px 12px; margin-bottom:14px; font-size:13px;">
    <strong>Tipos de arquivos assinalados:</strong><br>
    <div style="margin-top:6px; display:flex; flex-wrap:wrap; gap:6px 14px;">
      <?php foreach ($tipos as $tn): ?><span style="border:1px solid #333; padding:2px 8px; font-size:12px;"><span style="font-weight:bold;">☑</span> <?= htmlspecialchars($tn) ?></span><?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <table class="table table-bordered itens">
    <thead class="table-light"><tr><th style="width:40px">#</th><th>Descrição do item/documento</th><th style="width:70px">Qtd</th><th>Observação</th></tr></thead>
    <tbody>
      <?php foreach($itens as $i=>$it): ?>
        <tr><td><?= $i+1 ?></td><td><?= htmlspecialchars($it['descricao']) ?></td><td><?= (int)$it['quantidade'] ?></td><td><?= htmlspecialchars($it['observacao']??'') ?></td></tr>
      <?php endforeach; ?>
      <?php if(!$itens): ?><tr><td colspan="4" class="text-center text-muted">Sem itens cadastrados.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <?php if($p['observacao'] && $tipo==='recebimento'): ?><p><strong>Observações (recebimento):</strong> <?= nl2br(htmlspecialchars($p['observacao'])) ?></p><?php endif; ?>
  <?php if($p['observacao_retirada'] && $tipo==='retirada'): ?><p><strong>Observações (retirada):</strong> <?= nl2br(htmlspecialchars($p['observacao_retirada'])) ?></p><?php endif; ?>

  <p>
    <?php if($tipo==='recebimento'): ?>
      A pasta ficará sob guarda temporária deste setor até a retirada pela escola destinatária, mediante apresentação deste termo ou identificação do responsável. Este termo é emitido em 2 (duas) vias de igual teor.
    <?php else: ?>
      A partir desta retirada, o setor de protocolo fica isento de responsabilidade sobre o conteúdo da pasta ora entregue. Este termo é emitido em 2 (duas) vias.
    <?php endif; ?>
  </p>

  <p style="text-align:center; margin-top:25px; font-size:13px;">Local/data: ______________________, ___/___/______ &nbsp;&nbsp; Hora: ____:____</p>

  <div class="assinaturas">
    <div class="assinatura">
      <?= $tipo==='recebimento' ? 'Assinatura de quem entregou' : 'Assinatura de quem retirou' ?><br>
      <strong><?= htmlspecialchars($responsavel) ?></strong><br>
      <small><?= htmlspecialchars($documento) ?></small>
    </div>
    <div class="assinatura">
      Assinatura / Carimbo do setor de protocolo<br>
      <strong><?= htmlspecialchars($p['criador'] ?? '') ?></strong>
    </div>
  </div>

  <?php if($tipo==='retirada' && $p['status']!=='retirada'): ?>
    <div class="alert alert-warning mt-4 no-print" style="font-size:12px;">Atenção: esta pasta ainda consta como <strong>aguardando</strong>. Registre a retirada em "Pasta → Registrar retirada" antes de imprimir o termo definitivo.</div>
  <?php endif; ?>

  <div class="hash">
    <strong>Código de verificação:</strong> <?= htmlspecialchars($termo['codigo']) ?> &middot; Hash: <?= htmlspecialchars($termo['hash_verificacao']??'') ?> &middot; Pasta: <?= htmlspecialchars($p['codigo']) ?><br>
    Termo gerado em <?= date('d/m/Y H:i:s') ?> por <?= htmlspecialchars($_SESSION['usuario_nome'] ?? '') ?> &middot; Sistema de Protocolo - Autenticidade mediante consulta no setor.
  </div>

  <div class="text-center mt-3 no-print">
    <small class="text-muted">Após imprimir e colher assinaturas, digitalize e faça upload em Pasta → Termos → "Enviar arquivo assinado".</small>
  </div>
</div>
</body>
</html>
