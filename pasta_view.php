<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);
if (!$id) die('ID inválido');

$stmt=$pdo->prepare("SELECT p.*, e.nome AS escola_nome, e.codigo AS escola_codigo, e.email AS escola_email, e.telefone AS escola_telefone, u.nome AS criador FROM pastas p JOIN escolas e ON e.id=p.escola_id LEFT JOIN usuarios u ON u.id=p.criado_por WHERE p.id=?");
$stmt->execute([$id]); $p=$stmt->fetch();
if (!$p) die('Pasta não encontrada');

$itens=$pdo->prepare("SELECT * FROM pasta_itens WHERE pasta_id=? ORDER BY id"); $itens->execute([$id]); $itens=$itens->fetchAll();
$termos=$pdo->prepare("SELECT * FROM termos WHERE pasta_id=? ORDER BY criado_em"); $termos->execute([$id]); $termos=$termos->fetchAll();
try {
  $tipos=$pdo->prepare("SELECT t.nome FROM tipos_arquivo t JOIN pasta_tipos pt ON pt.tipo_id=t.id WHERE pt.pasta_id=? ORDER BY t.nome");
  $tipos->execute([$id]); $tipos=$tipos->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) { $tipos=[]; }

// Ações: registrar retirada, upload assinado, cancelar
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) die('CSRF inválido');
    $acao=$_POST['acao']??'';
    if ($acao==='retirar' && $p['status']==='aguardando') {
        $retirado_por=trim($_POST['retirado_por']??'');
        $retirado_doc=trim($_POST['retirado_documento']??'');
        $obs=trim($_POST['observacao_retirada']??'');
        $data_ret=trim($_POST['data_retirada']??'');
        if ($retirado_por==='') { flash('error','Informe quem retirou.'); header("Location: pasta_view.php?id=$id"); exit; }
        if ($data_ret==='') $data_ret=date('Y-m-d H:i:s'); else $data_ret=str_replace('T',' ',$data_ret). (strlen($data_ret)==16?':00':'');
        $pdo->prepare("UPDATE pastas SET status='retirada', data_retirada=?, retirado_por=?, retirado_documento=?, observacao_retirada=?, retirado_por_usuario=? WHERE id=?")
            ->execute([$data_ret,$retirado_por,$retirado_doc?:null,$obs?:null,$_SESSION['usuario_id'],$id]);
        $codigo=gerarCodigoTermo('retirada');
        $pdo->prepare("INSERT INTO termos (pasta_id,tipo,codigo,hash_verificacao,criado_por) VALUES (?,?,?,?,?)")
            ->execute([$id,'retirada',$codigo,bin2hex(random_bytes(16)),$_SESSION['usuario_id']]);
        flash('success','Retirada registrada! Agora gere o Termo de Retirada.');
        header("Location: pasta_view.php?id=$id"); exit;
    }
    if ($acao==='upload') {
        $termo_id=(int)($_POST['termo_id']??0);
        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error']!==UPLOAD_ERR_OK) { flash('error','Selecione um arquivo válido.'); header("Location: pasta_view.php?id=$id"); exit; }
        $t=$pdo->prepare("SELECT * FROM termos WHERE id=? AND pasta_id=?"); $t->execute([$termo_id,$id]); $termo=$t->fetch();
        if (!$termo) { flash('error','Termo não encontrado.'); header("Location: pasta_view.php?id=$id"); exit; }
        $ext=strtolower(pathinfo($_FILES['arquivo']['name'],PATHINFO_EXTENSION));
        if (!in_array($ext,['pdf','jpg','jpeg','png'])) { flash('error','Apenas PDF, JPG ou PNG.'); header("Location: pasta_view.php?id=$id"); exit; }
        if ($_FILES['arquivo']['size']> 10*1024*1024) { flash('error','Arquivo muito grande (máx 10MB).'); header("Location: pasta_view.php?id=$id"); exit; }
        $novoNome = $termo['codigo'].'-ASSINADO-'.time().'.'.$ext;
        $dest = __DIR__.'/uploads/termos/'.$novoNome;
        if (!is_dir(dirname($dest))) mkdir(dirname($dest),0775,true);
        if (!move_uploaded_file($_FILES['arquivo']['tmp_name'],$dest)) { flash('error','Falha ao salvar arquivo. Verifique permissões de uploads/termos.'); header("Location: pasta_view.php?id=$id"); exit; }
        // remove antigo se existir
        if ($termo['arquivo_assinado'] && file_exists(__DIR__.'/'.$termo['arquivo_assinado'])) @unlink(__DIR__.'/'.$termo['arquivo_assinado']);
        $pdo->prepare("UPDATE termos SET arquivo_assinado=? WHERE id=?")->execute(['uploads/termos/'.$novoNome,$termo_id]);
        flash('success','Arquivo assinado enviado com sucesso! Ele substitui o termo sem assinatura.');
        header("Location: pasta_view.php?id=$id"); exit;
    }
    if ($acao==='cancelar' && $p['status']==='aguardando') {
        $pdo->prepare("UPDATE pastas SET status='cancelada' WHERE id=?")->execute([$id]);
        flash('success','Pasta cancelada.'); header("Location: pasta_view.php?id=$id"); exit;
    }
    if ($acao==='reabrir' && $p['status']!=='aguardando') {
        $pdo->prepare("UPDATE pastas SET status='aguardando', data_retirada=NULL, retirado_por=NULL WHERE id=?")->execute([$id]);
        flash('success','Pasta reaberta para aguardando.'); header("Location: pasta_view.php?id=$id"); exit;
    }
}

$pageTitle = $p['codigo'].' - '.$p['escola_nome'];
include __DIR__.'/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0"><?= h($p['codigo']) ?> <?= statusBadge($p['status']) ?></h4>
    <small class="text-muted"><?= h($p['escola_nome']) ?> <?= $p['escola_codigo']?'('.h($p['escola_codigo']).')':'' ?> · Criada por <?= h($p['criador']??'-') ?> em <?= dataBr($p['criado_em']) ?></small>
  </div>
  <a href="pastas.php" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white"><strong><i class="bi bi-info-circle"></i> Dados da pasta</strong></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6"><strong>Escola:</strong> <?= h($p['escola_nome']) ?><br><strong>E-mail:</strong> <?= h($p['escola_email']??'-') ?><br><strong>Tel:</strong> <?= h($p['escola_telefone']??'-') ?></div>
          <div class="col-md-6">
            <strong>Recebimento:</strong> <?= dataBr($p['data_recebimento']) ?><br>
            <strong>Recebido de:</strong> <?= h($p['recebido_de']) ?> <?= $p['recebido_documento']?'('.h($p['recebido_documento']).')':'' ?><br>
            <?php if($p['status']==='retirada'): ?><strong>Retirado por:</strong> <?= h($p['retirado_por']) ?> <?= $p['retirado_documento']?'('.h($p['retirado_documento']).')':'' ?><br><strong>Em:</strong> <?= dataBr($p['data_retirada']) ?><?php endif; ?>
          </div>
          <?php if($p['observacao']): ?><div class="col-12 mt-2"><strong>Obs. recebimento:</strong> <?= nl2br(h($p['observacao'])) ?></div><?php endif; ?>
          <?php if($p['observacao_retirada']): ?><div class="col-12"><strong>Obs. retirada:</strong> <?= nl2br(h($p['observacao_retirada'])) ?></div><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white"><strong><i class="bi bi-tags"></i> Tipos de arquivos</strong></div>
      <div class="card-body">
        <?php if ($tipos): ?>
          <?php foreach ($tipos as $tn): ?><span class="badge bg-warning text-dark border me-1 mb-1"><i class="bi bi-check-square"></i> <?= h($tn) ?></span><?php endforeach; ?>
        <?php else: ?>
          <span class="text-muted small">Nenhum tipo marcado (pastas antigas).</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white"><strong><i class="bi bi-list-ul"></i> Itens (<?= count($itens) ?>)</strong></div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>#</th><th>Descrição</th><th>Qtd</th><th>Obs</th></tr></thead>
          <tbody>
          <?php foreach($itens as $i=>$it): ?><tr><td><?= $i+1 ?></td><td><?= h($it['descricao']) ?></td><td><?= (int)$it['quantidade'] ?></td><td><?= h($it['observacao']??'') ?></td></tr><?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong><i class="bi bi-file-earmark-text"></i> Termos</strong></div>
      <div class="card-body">
        <?php if(!$termos): ?><p class="text-muted">Nenhum termo gerado ainda.</p><?php endif; ?>
        <?php foreach($termos as $t): ?>
          <div class="border rounded p-3 mb-3 <?= $t['arquivo_assinado']?'bg-light':'' ?>">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <span class="badge <?= $t['tipo']==='recebimento'?'bg-primary':'bg-success' ?>"><?= $t['tipo']==='recebimento'?'RECEBIMENTO':'RETIRADA' ?></span>
                <code class="ms-2"><?= h($t['codigo']) ?></code><br>
                <small class="text-muted">Gerado em <?= dataBr($t['criado_em']) ?> · Hash: <?= h(substr($t['hash_verificacao']??'',0,12)) ?>...</small><br>
                <?php if($t['arquivo_assinado']): ?>
                  <span class="badge bg-success mt-1"><i class="bi bi-check-circle"></i> Assinado digitalizado</span>
                  <a href="<?= h($t['arquivo_assinado']) ?>" target="_blank" class="btn btn-sm btn-success ms-2"><i class="bi bi-file-earmark-pdf"></i> Abrir assinado</a>
                <?php else: ?>
                  <span class="badge bg-warning text-dark mt-1">Sem arquivo assinado</span>
                <?php endif; ?>
              </div>
              <div class="text-end">
                <a href="gerar_termo.php?id=<?= (int)$p['id'] ?>&tipo=<?= h($t['tipo']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i> Ver/Imprimir</a>
              </div>
            </div>
            <form method="post" enctype="multipart/form-data" class="mt-3 d-flex gap-2 align-items-end">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="acao" value="upload">
              <input type="hidden" name="termo_id" value="<?= (int)$t['id'] ?>">
              <div class="flex-grow-1">
                <label class="form-label small mb-1">Substituir por arquivo assinado (PDF/JPG/PNG, máx 10MB) - imprime, assina, digitaliza e envia aqui:</label>
                <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm" required>
              </div>
              <button class="btn btn-sm btn-dark"><i class="bi bi-upload"></i> Enviar</button>
            </form>
            <?php if($t['arquivo_assinado']): ?><small class="text-muted">Arquivo atual: <?= h($t['arquivo_assinado']) ?> — enviar novo substitui automaticamente.</small><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="alert alert-secondary small mb-0">
          <strong>Como funciona assinatura:</strong> Clique em <em>Ver/Imprimir</em> → imprime o termo → coleta as assinaturas manualmente → digitalize (scanner/foto) → volte aqui e faça <em>upload</em> no termo correspondente. O arquivo assinado fica vinculado e o botão "Abrir assinado" passa a exibir o documento assinado.
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <?php if($p['status']==='aguardando'): ?>
    <div class="card shadow-sm border-success mb-3">
      <div class="card-header bg-success text-white"><strong><i class="bi bi-box-arrow-right"></i> Registrar retirada</strong></div>
      <div class="card-body">
        <p class="small text-muted">Quando a escola vier buscar, preencha e gere o Termo de Retirada.</p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="acao" value="retirar">
          <div class="mb-2"><label class="form-label">Retirado por *</label><input name="retirado_por" class="form-control" required placeholder="Nome de quem retirou"></div>
          <div class="mb-2"><label class="form-label">Documento</label><input name="retirado_documento" class="form-control" placeholder="CPF/RG"></div>
          <div class="mb-2"><label class="form-label">Data/hora retirada</label><input type="datetime-local" name="data_retirada" class="form-control" value="<?= date('Y-m-d\TH:i') ?>"></div>
          <div class="mb-3"><label class="form-label">Observação</label><textarea name="observacao_retirada" class="form-control" rows="2"></textarea></div>
          <button class="btn btn-success w-100"><i class="bi bi-check2-square"></i> Confirmar retirada</button>
        </form>
        <form method="post" class="mt-2" onsubmit="return confirm('Cancelar esta pasta?')">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="acao" value="cancelar">
          <button class="btn btn-outline-danger btn-sm w-100">Cancelar pasta</button>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body text-center">
        <p class="mb-2">Status: <?= statusBadge($p['status']) ?></p>
        <form method="post" onsubmit="return confirm('Reabrir pasta?')">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="acao" value="reabrir">
          <button class="btn btn-sm btn-outline-secondary">Reabrir para aguardando</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong>Ações rápidas</strong></div>
      <div class="list-group list-group-flush">
        <a href="gerar_termo.php?id=<?= (int)$p['id'] ?>&tipo=recebimento" target="_blank" class="list-group-item list-group-item-action"><i class="bi bi-printer"></i> Imprimir Termo de Recebimento</a>
        <?php if($p['status']==='retirada'): ?><a href="gerar_termo.php?id=<?= (int)$p['id'] ?>&tipo=retirada" target="_blank" class="list-group-item list-group-item-action"><i class="bi bi-printer-fill"></i> Imprimir Termo de Retirada</a><?php endif; ?>
        <a href="pastas.php?escola=<?= (int)$p['escola_id'] ?>" class="list-group-item list-group-item-action"><i class="bi bi-building"></i> Ver outras pastas desta escola</a>
      </div>
    </div>

    <div class="alert alert-info small mt-3">
      <strong>Notificação:</strong> quando a pasta chega, o sistema futuramente notificará a escola por e-mail/WhatsApp. Por enquanto, use o telefone/e-mail da escola acima para avisar manualmente.
    </div>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
