<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
$pdo = getPDO();

// Ações: criar / editar / desativar
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) die('CSRF inválido');
    $acao = $_POST['acao'] ?? '';
    if ($acao==='salvar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $responsavel = trim($_POST['responsavel'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        if ($nome==='') { flash('error','Nome da escola é obrigatório.'); header('Location: escolas.php'); exit; }
        if ($id>0) {
            $pdo->prepare("UPDATE escolas SET nome=?, codigo=?, email=?, telefone=?, responsavel=?, endereco=? WHERE id=?")
                ->execute([$nome,$codigo?:null,$email?:null,$telefone?:null,$responsavel?:null,$endereco?:null,$id]);
            flash('success','Escola atualizada.');
        } else {
            $pdo->prepare("INSERT INTO escolas (nome,codigo,email,telefone,responsavel,endereco) VALUES (?,?,?,?,?,?)")
                ->execute([$nome,$codigo?:null,$email?:null,$telefone?:null,$responsavel?:null,$endereco?:null]);
            flash('success','Escola cadastrada.');
        }
        header('Location: escolas.php'); exit;
    }
    if ($acao==='toggle') {
        $id=(int)$_POST['id']; $pdo->prepare("UPDATE escolas SET ativo=1-ativo WHERE id=?")->execute([$id]);
        header('Location: escolas.php'); exit;
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt=$pdo->prepare("SELECT * FROM escolas WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit=$stmt->fetch();
}

$busca = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM escolas WHERE 1";
$params=[];
if ($busca!=='') { $sql.=" AND (nome LIKE ? OR codigo LIKE ? OR email LIKE ?)"; $params=array_fill(0,3,"%$busca%"); }
$sql.=" ORDER BY ativo DESC, nome ASC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $lista=$stmt->fetchAll();

$pageTitle='Escolas';
include __DIR__.'/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-building"></i> Escolas</h4>
  <form class="d-flex" method="get"><input name="q" value="<?= h($busca) ?>" class="form-control form-control-sm me-2" placeholder="Buscar escola..."><button class="btn btn-sm btn-outline-secondary">Buscar</button></form>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong><?= $edit ? 'Editar escola' : 'Nova escola' ?></strong></div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="mb-2"><label class="form-label">Nome *</label><input name="nome" class="form-control" required value="<?= h($edit['nome'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label">Código (ex: ESC001)</label><input name="codigo" class="form-control" value="<?= h($edit['codigo'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label">Responsável</label><input name="responsavel" class="form-control" value="<?= h($edit['responsavel'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label">E-mail (para notificação futura)</label><input name="email" type="email" class="form-control" value="<?= h($edit['email'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label">Telefone</label><input name="telefone" class="form-control" value="<?= h($edit['telefone'] ?? '') ?>"></div>
          <div class="mb-3"><label class="form-label">Endereço</label><textarea name="endereco" class="form-control" rows="2"><?= h($edit['endereco'] ?? '') ?></textarea></div>
          <button class="btn btn-primary w-100"><?= $edit ? 'Salvar alterações' : 'Cadastrar' ?></button>
          <?php if ($edit): ?><a href="escolas.php" class="btn btn-light w-100 mt-2">Cancelar edição</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Escola</th><th>Código</th><th>Contato</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($lista as $e): ?>
            <tr class="<?= $e['ativo']?'':'table-secondary' ?>">
              <td><div class="fw-semibold"><?= h($e['nome']) ?></div><small class="text-muted"><?= h($e['responsavel'] ?? '') ?></small></td>
              <td><?= h($e['codigo'] ?? '-') ?></td>
              <td><small><?= h($e['email'] ?? '') ?><br><?= h($e['telefone'] ?? '') ?></small></td>
              <td><?= $e['ativo']?'<span class="badge bg-success">Ativa</span>':'<span class="badge bg-secondary">Inativa</span>' ?></td>
              <td class="text-end">
                <a href="?edit=<?= (int)$e['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Alternar status?')">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-toggle-on"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; if (!$lista): ?><tr><td colspan="5" class="text-center text-muted py-4">Nenhuma escola cadastrada.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
