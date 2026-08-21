<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
$pdo = getPDO();

$status = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$escola_filtro = (int)($_GET['escola'] ?? 0);

$where = " WHERE 1";
$params = [];
if (in_array($status, ['aguardando','retirada','cancelada'])) { $where.=" AND p.status=?"; $params[]=$status; }
if ($q!=='') { $where.=" AND (p.codigo LIKE ? OR p.recebido_de LIKE ? OR e.nome LIKE ?)"; $params=array_merge($params, ["%$q%","%$q%","%$q%"]); }
if ($escola_filtro>0) { $where.=" AND p.escola_id=?"; $params[]=$escola_filtro; }

$sql = "SELECT p.*, e.nome AS escola_nome, e.codigo AS escola_codigo, u.nome AS criador
        FROM pastas p JOIN escolas e ON e.id=p.escola_id LEFT JOIN usuarios u ON u.id=p.criado_por
        $where ORDER BY p.id DESC LIMIT 200";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $lista=$stmt->fetchAll();
$escolas=$pdo->query("SELECT id,nome FROM escolas WHERE ativo=1 ORDER BY nome")->fetchAll();

$pageTitle='Pastas';
include __DIR__.'/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-collection"></i> Pastas</h4>
  <a href="pasta_nova.php" class="btn btn-primary btn-sm"><i class="bi bi-folder-plus"></i> Nova</a>
</div>

<form class="card shadow-sm mb-3" method="get">
  <div class="card-body row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label small">Buscar</label><input name="q" value="<?= h($q) ?>" class="form-control form-control-sm" placeholder="Código, remetente, escola"></div>
    <div class="col-md-3"><label class="form-label small">Escola</label>
      <select name="escola" class="form-select form-select-sm"><option value="">Todas</option><?php foreach($escolas as $e): ?><option value="<?= (int)$e['id'] ?>" <?= $escola_filtro==(int)$e['id']?'selected':'' ?>><?= h($e['nome']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-md-2"><label class="form-label small">Status</label>
      <select name="status" class="form-select form-select-sm"><option value="">Todos</option><option value="aguardando" <?= $status==='aguardando'?'selected':'' ?>>Aguardando</option><option value="retirada" <?= $status==='retirada'?'selected':'' ?>>Retirada</option><option value="cancelada" <?= $status==='cancelada'?'selected':'' ?>>Cancelada</option></select>
    </div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Filtrar</button></div>
    <div class="col-md-2"><a href="pastas.php" class="btn btn-sm btn-light w-100">Limpar</a></div>
  </div>
</form>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Código</th><th>Escola</th><th>Recebido de</th><th>Recebimento</th><th>Retirada</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach($lista as $r): ?>
        <tr>
          <td><a href="pasta_view.php?id=<?= (int)$r['id'] ?>" class="fw-bold text-decoration-none"><?= h($r['codigo']) ?></a><br><small class="text-muted">por <?= h($r['criador'] ?? '-') ?></small></td>
          <td><?= h($r['escola_nome']) ?><br><small class="text-muted"><?= h($r['escola_codigo'] ?? '') ?></small></td>
          <td><?= h($r['recebido_de']) ?></td>
          <td><?= dataBr($r['data_recebimento']) ?></td>
          <td><?= $r['data_retirada'] ? dataBr($r['data_retirada']).'<br><small>'.h($r['retirado_por']??'').'</small>' : '-' ?></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td class="text-end"><a href="pasta_view.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Ver</a></td>
        </tr>
      <?php endforeach; if(!$lista): ?><tr><td colspan="7" class="text-center text-muted py-4">Nenhum resultado.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
