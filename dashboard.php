<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
$pdo = getPDO();

// Estatísticas
$totalAguardando = $pdo->query("SELECT COUNT(*) FROM pastas WHERE status='aguardando'")->fetchColumn();
$totalRetiradas = $pdo->query("SELECT COUNT(*) FROM pastas WHERE status='retirada'")->fetchColumn();
$totalMes = $pdo->query("SELECT COUNT(*) FROM pastas WHERE MONTH(data_recebimento)=MONTH(NOW()) AND YEAR(data_recebimento)=YEAR(NOW())")->fetchColumn();
$totalEscolas = $pdo->query("SELECT COUNT(*) FROM escolas WHERE ativo=1")->fetchColumn();
// Pendências de upload
try {
    $totalPendRec = $pdo->query("SELECT COUNT(*) FROM pastas p WHERE (SELECT arquivo_assinado FROM termos WHERE pasta_id=p.id AND tipo='recebimento' ORDER BY id DESC LIMIT 1) IS NULL")->fetchColumn();
    $totalPendRet = $pdo->query("SELECT COUNT(*) FROM pastas p WHERE p.status='retirada' AND ((SELECT arquivo_assinado FROM termos WHERE pasta_id=p.id AND tipo='retirada' ORDER BY id DESC LIMIT 1) IS NULL)")->fetchColumn();
} catch(Exception $e) { $totalPendRec = 0; $totalPendRet = 0; }

// Últimas pastas aguardando
$stmt = $pdo->query("SELECT p.*, e.nome AS escola_nome FROM pastas p JOIN escolas e ON e.id=p.escola_id WHERE p.status='aguardando' ORDER BY p.data_recebimento DESC LIMIT 8");
// Pendências de upload (top 10)
try {
    $pendentes = $pdo->query("SELECT p.*, e.nome AS escola_nome,
        (SELECT arquivo_assinado FROM termos WHERE pasta_id=p.id AND tipo='recebimento' ORDER BY id DESC LIMIT 1) AS rec_assinado,
        (SELECT arquivo_assinado FROM termos WHERE pasta_id=p.id AND tipo='retirada' ORDER BY id DESC LIMIT 1) AS ret_assinado,
        (SELECT id FROM termos WHERE pasta_id=p.id AND tipo='retirada' LIMIT 1) AS ret_existe
        FROM pastas p JOIN escolas e ON e.id=p.escola_id
        HAVING rec_assinado IS NULL OR (ret_existe IS NOT NULL AND ret_assinado IS NULL) OR (p.status='retirada' AND ret_existe IS NULL)
        ORDER BY p.id DESC LIMIT 10")->fetchAll();
} catch(Exception $e) { $pendentes = []; }

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">Dashboard</h4>
  <a href="pasta_nova.php" class="btn btn-primary"><i class="bi bi-folder-plus"></i> Registrar Entrada</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card card-stat shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Aguardando retirada</div>
        <div class="h3 mb-0"><?= (int)$totalAguardando ?></div>
        <a href="pastas.php?status=aguardando" class="small">Ver lista &rarr;</a>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-stat shadow-sm" style="border-left-color:#198754">
      <div class="card-body">
        <div class="text-muted small">Retiradas</div>
        <div class="h3 mb-0"><?= (int)$totalRetiradas ?></div>
        <a href="pastas.php?status=retirada" class="small">Ver lista &rarr;</a>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-stat shadow-sm" style="border-left-color:#0d6efd">
      <div class="card-body">
        <div class="text-muted small">Entradas no mês</div>
        <div class="h3 mb-0"><?= (int)$totalMes ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-stat shadow-sm" style="border-left-color:#6c757d">
      <div class="card-body">
        <div class="text-muted small">Escolas ativas</div>
        <div class="h3 mb-0"><?= (int)$totalEscolas ?></div>
        <a href="escolas.php" class="small">Gerenciar &rarr;</a>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-stat shadow-sm" style="border-left-color:#ffc107">
      <div class="card-body">
        <div class="text-muted small"><i class="bi bi-circle-fill text-warning"></i> Pend. Termo Entrega</div>
        <div class="h3 mb-0"><?= (int)$totalPendRec ?></div>
        <a href="#pendencias" class="small">Ver abaixo &rarr;</a>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-stat shadow-sm" style="border-left-color:#dc3545">
      <div class="card-body">
        <div class="text-muted small"><i class="bi bi-circle-fill text-danger"></i> Pend. Termo Retirada</div>
        <div class="h3 mb-0"><?= (int)$totalPendRet ?></div>
        <a href="#pendencias" class="small">Ver abaixo &rarr;</a>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong><i class="bi bi-clock-history"></i> Pastas aguardando retirada (recentes)</strong>
    <a href="pastas.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Código</th><th>Escola</th><th>Recebido de</th><th>Data</th><th>Itens</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($stmt as $r):
        $qtd = $pdo->prepare("SELECT COUNT(*) FROM pasta_itens WHERE pasta_id=?");
        $qtd->execute([$r['id']]);
        $itens = $qtd->fetchColumn();
      ?>
        <tr>
          <td class="fw-bold"><?= h($r['codigo']) ?></td>
          <td><?= h($r['escola_nome']) ?></td>
          <td><?= h($r['recebido_de']) ?></td>
          <td><?= dataBr($r['data_recebimento']) ?></td>
          <td><span class="badge bg-light text-dark border"><?= (int)$itens ?> item(s)</span></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td><a href="pasta_view.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Ver</a></td>
        </tr>
      <?php endforeach; if ($stmt->rowCount()==0): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma pasta aguardando no momento.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="pendencias" class="card shadow-sm mt-4">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong><i class="bi bi-exclamation-triangle"></i> Pendências de upload de termos</strong>
    <span class="small text-muted"><i class="bi bi-circle-fill text-warning"></i> Entrega &nbsp; <i class="bi bi-circle-fill text-danger"></i> Retirada</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Código</th><th>Escola</th><th>Status</th><th>Pendência</th><th></th></tr></thead>
      <tbody>
      <?php foreach($pendentes as $r):
        $amarelo = empty($r['rec_assinado']);
        $vermelho = (!empty($r['ret_existe']) && empty($r['ret_assinado'])) || ($r['status']==='retirada' && empty($r['ret_existe']));
      ?>
        <tr>
          <td class="fw-bold"><?= h($r['codigo']) ?></td>
          <td><?= h($r['escola_nome']) ?></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td>
            <?php if($amarelo): ?><span class="badge bg-warning text-dark"><i class="bi bi-circle-fill"></i> Entrega</span><?php endif; ?>
            <?php if($vermelho): ?><span class="badge bg-danger"><i class="bi bi-circle-fill"></i> Retirada</span><?php endif; ?>
            <?php if(!$amarelo && !$vermelho): ?><span class="badge bg-success">OK</span><?php endif; ?>
          </td>
          <td><a href="pasta_view.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i> Resolver</a></td>
        </tr>
      <?php endforeach; if(empty($pendentes)): ?>
        <tr><td colspan="5" class="text-center text-success py-3"><i class="bi bi-check-circle"></i> Nenhuma pendência! Todos os termos com upload em dia.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-info mt-4">
  <strong>Fluxo do sistema:</strong> 1) Alguém deixa a pasta → <em>Registrar Entrada</em> → imprime <strong>Termo de Recebimento</strong> → assina e digitaliza (upload).<br>
  2) Escola vem buscar → abrir pasta → <em>Registrar Retirada</em> → imprime <strong>Termo de Entrega/Retirada</strong> → assina e digitaliza.<br>
  <span class="small">Notificação automática para escolas fica para a próxima etapa.</span>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
