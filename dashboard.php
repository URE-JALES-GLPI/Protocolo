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

// Últimas pastas aguardando
$stmt = $pdo->query("SELECT p.*, e.nome AS escola_nome FROM pastas p JOIN escolas e ON e.id=p.escola_id WHERE p.status='aguardando' ORDER BY p.data_recebimento DESC LIMIT 8");

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

<div class="alert alert-info mt-4">
  <strong>Fluxo do sistema:</strong> 1) Alguém deixa a pasta → <em>Registrar Entrada</em> → imprime <strong>Termo de Recebimento</strong> → assina e digitaliza (upload).<br>
  2) Escola vem buscar → abrir pasta → <em>Registrar Retirada</em> → imprime <strong>Termo de Entrega/Retirada</strong> → assina e digitaliza.<br>
  <span class="small">Notificação automática para escolas fica para a próxima etapa.</span>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
