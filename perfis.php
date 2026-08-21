<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requirePerm('pode_acessar_config');
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) die('CSRF inválido');
    $acao = $_POST['acao'] ?? '';
    if ($acao==='salvar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $p_tipos = isset($_POST['pode_gerenciar_tipos']) ? 1 : 0;
        $p_escolas = isset($_POST['pode_gerenciar_escolas']) ? 1 : 0;
        $p_usuarios = isset($_POST['pode_gerenciar_usuarios']) ? 1 : 0;
        $p_config = isset($_POST['pode_acessar_config']) ? 1 : 0;
        $p_pastas = isset($_POST['pode_gerenciar_pastas']) ? 1 : 0;
        $p_dash = isset($_POST['pode_ver_dashboard']) ? 1 : 0;
        if ($nome==='') { flash('error','Nome do perfil obrigatório.'); header('Location: perfis.php'); exit; }
        try {
            if ($id>0) {
                $pdo->prepare("UPDATE perfis SET nome=?, descricao=?, pode_gerenciar_tipos=?, pode_gerenciar_escolas=?, pode_gerenciar_usuarios=?, pode_acessar_config=?, pode_gerenciar_pastas=?, pode_ver_dashboard=? WHERE id=?")
                    ->execute([$nome,$descricao?:null,$p_tipos,$p_escolas,$p_usuarios,$p_config,$p_pastas,$p_dash,$id]);
                flash('success','Perfil atualizado.');
            } else {
                $pdo->prepare("INSERT INTO perfis (nome, descricao, pode_gerenciar_tipos, pode_gerenciar_escolas, pode_gerenciar_usuarios, pode_acessar_config, pode_gerenciar_pastas, pode_ver_dashboard) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$nome,$descricao?:null,$p_tipos,$p_escolas,$p_usuarios,$p_config,$p_pastas,$p_dash]);
                flash('success','Perfil criado.');
            }
        } catch(PDOException $e){
            if ($e->getCode()==23000) flash('error','Já existe perfil com esse nome.');
            else flash('error','Erro: '.$e->getMessage());
        }
        header('Location: perfis.php'); exit;
    }
    if ($acao==='excluir') {
        $id=(int)($_POST['id'] ?? 0);
        // verifica se tem usuários vinculados
        $chk=$pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE perfil_id=?");
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn()>0) {
            flash('error','Não pode excluir: há usuários vinculados a este perfil. Reatribua antes.');
        } else {
            // não deixa excluir perfis padrão se forem os únicos?
            $nome=$pdo->query("SELECT nome FROM perfis WHERE id=$id")->fetchColumn();
            if (in_array($nome,['Administrador','Operador'])) {
                flash('error','Não pode excluir perfis padrão Administrador/Operador.');
            } else {
                $pdo->prepare("DELETE FROM perfis WHERE id=?")->execute([$id]);
                flash('success','Perfil excluído.');
            }
        }
        header('Location: perfis.php'); exit;
    }
}

$edit=null;
if(isset($_GET['edit'])){
    $stmt=$pdo->prepare("SELECT * FROM perfis WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit=$stmt->fetch();
}
try{
    $lista=$pdo->query("SELECT p.*, (SELECT COUNT(*) FROM usuarios WHERE perfil_id=p.id) AS total_usuarios FROM perfis ORDER BY nome")->fetchAll();
} catch(PDOException $e){
    $lista=[];
    flash('error','Tabela perfis não existe ainda. Rode: mysql -u protocolo_user -pProtocolo@2026 protocolo < sql/migration_perfis.sql');
}

$pageTitle='Perfis';
include __DIR__.'/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-shield-lock"></i> Perfis de Acesso</h4>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong><?= $edit?'Editar perfil':'Novo perfil' ?></strong></div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="mb-2"><label class="form-label">Nome *</label><input name="nome" class="form-control" required placeholder="Ex: Operador Jales, Financeiro" value="<?= h($edit['nome'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label">Descrição</label><input name="descricao" class="form-control" placeholder="Opcional" value="<?= h($edit['descricao'] ?? '') ?>"></div>
          <label class="form-label">Permissões</label>
          <div class="border rounded p-2 mb-3">
            <div class="form-check"><input type="checkbox" class="form-check-input" name="pode_ver_dashboard" id="pd" <?= !isset($edit) || !empty($edit['pode_ver_dashboard'])?'checked':'' ?>><label for="pd" class="form-check-label">Ver Dashboard</label></div>
            <div class="form-check"><input type="checkbox" class="form-check-input" name="pode_gerenciar_pastas" id="pp" <?= !isset($edit) || !empty($edit['pode_gerenciar_pastas'])?'checked':'' ?>><label for="pp" class="form-check-label">Gerenciar Pastas (criar/retirar)</label></div>
            <div class="form-check"><input type="checkbox" class="form-check-input" name="pode_gerenciar_escolas" id="pe" <?= !empty($edit['pode_gerenciar_escolas'])?'checked':'' ?>><label for="pe" class="form-check-label">Gerenciar Escolas</label></div>
            <div class="form-check"><input type="checkbox" class="form-check-input" name="pode_gerenciar_tipos" id="pt" <?= !empty($edit['pode_gerenciar_tipos'])?'checked':'' ?>><label for="pt" class="form-check-label">Gerenciar Tipos de Arquivos</label></div>
            <div class="form-check"><input type="checkbox" class="form-check-input" name="pode_gerenciar_usuarios" id="pu" <?= !empty($edit['pode_gerenciar_usuarios'])?'checked':'' ?>><label for="pu" class="form-check-label">Gerenciar Usuários</label></div>
            <div class="form-check"><input type="checkbox" class="form-check-input" name="pode_acessar_config" id="pc" <?= !empty($edit['pode_acessar_config'])?'checked':'' ?>><label for="pc" class="form-check-label">Acessar Configurações</label></div>
          </div>
          <button class="btn btn-primary w-100"><?= $edit?'Salvar':'Criar perfil' ?></button>
          <?php if($edit): ?><a href="perfis.php" class="btn btn-light w-100 mt-2">Cancelar</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Perfil</th><th>Permissões</th><th>Usuários</th><th></th></tr></thead>
          <tbody>
          <?php foreach($lista as $p): ?>
            <tr>
              <td><div class="fw-semibold"><?= h($p['nome']) ?></div><small class="text-muted"><?= h($p['descricao']??'') ?></small></td>
              <td><small>
                <?= !empty($p['pode_ver_dashboard'])?'<span class="badge bg-light text-dark border">Dashboard</span>':'' ?>
                <?= !empty($p['pode_gerenciar_pastas'])?'<span class="badge bg-light text-dark border">Pastas</span>':'' ?>
                <?= !empty($p['pode_gerenciar_escolas'])?'<span class="badge bg-light text-dark border">Escolas</span>':'' ?>
                <?= !empty($p['pode_gerenciar_tipos'])?'<span class="badge bg-light text-dark border">Tipos</span>':'' ?>
                <?= !empty($p['pode_gerenciar_usuarios'])?'<span class="badge bg-light text-dark border">Usuários</span>':'' ?>
                <?= !empty($p['pode_acessar_config'])?'<span class="badge bg-warning text-dark border">Config</span>':'' ?>
              </small></td>
              <td><span class="badge bg-secondary"><?= (int)$p['total_usuarios'] ?></span></td>
              <td class="text-end">
                <a href="?edit=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Excluir perfil?')">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; if(!$lista): ?><tr><td colspan="4" class="text-center text-muted py-4">Nenhum perfil. Rode a migration.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
