<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requirePerm('pode_gerenciar_tipos');
$pdo = getPDO();

// Ações: criar / editar / toggle / excluir
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) die('CSRF inválido');
    $acao = $_POST['acao'] ?? '';
    if ($acao==='salvar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        if ($nome==='') { flash('error','Nome é obrigatório.'); header('Location: tipos_arquivo.php'); exit; }
        try {
            if ($id>0) {
                $pdo->prepare("UPDATE tipos_arquivo SET nome=?, descricao=? WHERE id=?")->execute([$nome,$descricao?:null,$id]);
                flash('success','Tipo atualizado.');
            } else {
                $pdo->prepare("INSERT INTO tipos_arquivo (nome, descricao) VALUES (?,?)")->execute([$nome,$descricao?:null]);
                flash('success','Tipo cadastrado.');
            }
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(),'uniq_nome') || $e->getCode()==23000) flash('error','Já existe um tipo com esse nome.');
            else flash('error','Erro: '.$e->getMessage());
        }
        header('Location: tipos_arquivo.php'); exit;
    }
    if ($acao==='toggle') {
        $id=(int)$_POST['id']; $pdo->prepare("UPDATE tipos_arquivo SET ativo=1-ativo WHERE id=?")->execute([$id]);
        header('Location: tipos_arquivo.php'); exit;
    }
    if ($acao==='excluir') {
        $id=(int)($_POST['id'] ?? 0);
        $chk=$pdo->prepare("SELECT COUNT(*) FROM pasta_tipos WHERE tipo_id=?");
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) {
            flash('error','Não é possível excluir: tipo já usado em pastas. Inative-o.');
        } else {
            $pdo->prepare("DELETE FROM tipos_arquivo WHERE id=?")->execute([$id]);
            flash('success','Tipo excluído.');
        }
        header('Location: tipos_arquivo.php'); exit;
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt=$pdo->prepare("SELECT * FROM tipos_arquivo WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit=$stmt->fetch();
}

$busca = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM tipos_arquivo WHERE 1";
$params=[];
if ($busca!=='') { $sql.=" AND (nome LIKE ? OR descricao LIKE ?)"; $params=array_fill(0,2,"%$busca%"); }
$sql.=" ORDER BY ativo DESC, nome ASC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $lista=$stmt->fetchAll();

$pageTitle='Tipos de Arquivo';
include __DIR__.'/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-tags"></i> Tipos de Arquivos</h4>
  <form class="d-flex" method="get"><input name="q" value="<?= h($busca) ?>" class="form-control form-control-sm me-2" placeholder="Buscar tipo..."><button class="btn btn-sm btn-outline-secondary">Buscar</button></form>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong><?= $edit ? 'Editar tipo' : 'Novo tipo' ?></strong></div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="mb-2"><label class="form-label">Nome *</label><input name="nome" class="form-control" required placeholder="Ex: Ofício, Fotos, Prestação de Contas" value="<?= h($edit['nome'] ?? '') ?>"></div>
          <div class="mb-3"><label class="form-label">Descrição</label><input name="descricao" class="form-control" placeholder="Opcional" value="<?= h($edit['descricao'] ?? '') ?>"></div>
          <button class="btn btn-primary w-100"><?= $edit ? 'Salvar alterações' : 'Cadastrar' ?></button>
          <?php if ($edit): ?><a href="tipos_arquivo.php" class="btn btn-light w-100 mt-2">Cancelar edição</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Tipo</th><th>Descrição</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($lista as $e): ?>
            <tr class="<?= $e['ativo']?'':'table-secondary' ?>">
              <td><div class="fw-semibold"><?= h($e['nome']) ?></div></td>
              <td><small class="text-muted"><?= h($e['descricao'] ?? '-') ?></small></td>
              <td><?= $e['ativo']?'<span class="badge bg-success">Ativo</span>':'<span class="badge bg-secondary">Inativo</span>' ?></td>
              <td class="text-end">
                <a href="?edit=<?= (int)$e['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Alternar status?')">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary" title="Ativar/Inativar"><i class="bi bi-toggle-on"></i></button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('Excluir definitivo? Só se não estiver em uso.')">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="excluir">
                  <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; if (!$lista): ?><tr><td colspan="4" class="text-center text-muted py-4">Nenhum tipo cadastrado. Cadastre as opções que aparecerão como caixinhas em Nova Pasta.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="alert alert-info small mt-3">
      Esses tipos aparecem como <strong>caixinhas (checkboxes)</strong> em <em>Nova Pasta → Quais tipos de arquivos</em>. Inative ao invés de excluir se já estiver em uso.
    </div>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
