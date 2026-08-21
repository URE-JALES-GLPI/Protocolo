<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requirePerm('pode_gerenciar_usuarios');
$pdo = getPDO();
try { $perfis = $pdo->query("SELECT id, nome FROM perfis ORDER BY nome")->fetchAll(); } catch(PDOException $e) { $perfis=[]; }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) die('CSRF inválido');
    $acao=$_POST['acao']??'';
    if ($acao==='salvar') {
        $id=(int)($_POST['id']??0);
        $nome=trim($_POST['nome']??'');
        $username=trim($_POST['username']??'');
        $email=trim($_POST['email']??'');
        $perfil=$_POST['perfil']??'operador';
        $perfil_id = (int)($_POST['perfil_id'] ?? 0);
        $senha=$_POST['senha']??'';
        if ($nome===''||$username==='') { flash('error','Nome e usuário obrigatórios.'); header('Location: usuarios.php'); exit; }
        if (!in_array($perfil,['admin','operador'])) $perfil='operador';
        // se escolheu perfil custom, usa o nome dele para compat mas salva perfil_id
        if ($perfil_id) {
            try { $nomePerfil = $pdo->prepare("SELECT nome FROM perfis WHERE id=?"); $nomePerfil->execute([$perfil_id]); $np=$nomePerfil->fetchColumn(); if($np==='Administrador') $perfil='admin'; elseif($np) $perfil='operador'; } catch(Exception $e) {}
        }
        try{
            if ($id>0) {
                if ($senha!=='') {
                    $hash=password_hash($senha,PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE usuarios SET nome=?, username=?, email=?, perfil=?, perfil_id=?, senha_hash=? WHERE id=?")->execute([$nome,$username,$email?:null,$perfil,$perfil_id?:null,$hash,$id]);
                } else {
                    $pdo->prepare("UPDATE usuarios SET nome=?, username=?, email=?, perfil=?, perfil_id=? WHERE id=?")->execute([$nome,$username,$email?:null,$perfil,$perfil_id?:null,$id]);
                }
                flash('success','Usuário atualizado.');
            } else {
                if ($senha==='') { flash('error','Senha obrigatória para novo usuário.'); header('Location: usuarios.php'); exit; }
                $hash=password_hash($senha,PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO usuarios (nome,username,email,senha_hash,perfil,perfil_id) VALUES (?,?,?,?,?,?)")->execute([$nome,$username,$email?:null,$hash,$perfil,$perfil_id?:null]);
                flash('success','Usuário criado.');
            }
        } catch(PDOException $e){
            flash('error','Erro: '.$e->getMessage());
        }
        header('Location: usuarios.php'); exit;
    }
    if ($acao==='toggle') {
        $id=(int)$_POST['id'];
        if ($id===$_SESSION['usuario_id']) { flash('error','Você não pode desativar a si mesmo.'); header('Location: usuarios.php'); exit; }
        $pdo->prepare("UPDATE usuarios SET ativo=1-ativo WHERE id=?")->execute([$id]);
        header('Location: usuarios.php'); exit;
    }
}

$edit=null;
if (isset($_GET['edit'])) { $s=$pdo->prepare("SELECT * FROM usuarios WHERE id=?"); $s->execute([(int)$_GET['edit']]); $edit=$s->fetch(); }
try { $lista=$pdo->query("SELECT u.*, p.nome AS perfil_nome FROM usuarios u LEFT JOIN perfis p ON p.id=u.perfil_id ORDER BY u.ativo DESC, u.nome ASC")->fetchAll(); } catch(PDOException $e) { $lista=$pdo->query("SELECT *, perfil AS perfil_nome FROM usuarios ORDER BY ativo DESC, nome ASC")->fetchAll(); }

$pageTitle='Usuários';
include __DIR__.'/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-people"></i> Usuários do sistema</h4>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong><?= $edit?'Editar usuário':'Novo usuário' ?></strong></div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
          <div class="mb-2"><label class="form-label">Nome *</label><input name="nome" class="form-control" required value="<?= h($edit['nome']??'') ?>"></div>
          <div class="mb-2"><label class="form-label">Usuário *</label><input name="username" class="form-control" required value="<?= h($edit['username']??'') ?>"></div>
          <div class="mb-2"><label class="form-label">E-mail</label><input name="email" type="email" class="form-control" value="<?= h($edit['email']??'') ?>"></div>
          <div class="mb-2"><label class="form-label">Perfil de Acesso *</label>
            <select name="perfil_id" class="form-select" required>
              <option value="">Selecione o perfil...</option>
              <?php foreach($perfis as $pf): ?><option value="<?= (int)$pf['id'] ?>" <?= (int)($edit['perfil_id']??0)===(int)$pf['id']?'selected':'' ?>><?= h($pf['nome']) ?></option><?php endforeach; ?>
            </select>
            <div class="form-text">Gerencie em <a href="perfis.php">Perfis</a> ou <a href="configuracoes.php">Configurações</a>.</div>
          </div>
          <input type="hidden" name="perfil" value="operador">
          <div class="mb-3"><label class="form-label">Senha <?= $edit?' (deixe em branco para manter)':'*' ?></label><input name="senha" type="password" class="form-control" <?= $edit?'':'required' ?>></div>
          <button class="btn btn-primary w-100"><?= $edit?'Salvar':'Criar usuário' ?></button>
          <?php if($edit): ?><a href="usuarios.php" class="btn btn-light w-100 mt-2">Cancelar</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Nome</th><th>Usuário</th><th>Perfil</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach($lista as $u): ?>
            <tr class="<?= $u['ativo']?'':'table-secondary' ?>">
              <td><?= h($u['nome']) ?><br><small class="text-muted"><?= h($u['email']??'') ?></small></td>
              <td><code><?= h($u['username']) ?></code></td>
              <td><span class="badge <?= $u['perfil']==='admin'?'bg-dark':'bg-primary' ?>"><?= h($u['perfil_nome'] ?? $u['perfil']) ?></span><br><small class="text-muted"><?= h($u['perfil']) ?></small></td>
              <td><?= $u['ativo']?'<span class="badge bg-success">Ativo</span>':'<span class="badge bg-secondary">Inativo</span>' ?></td>
              <td class="text-end">
                <a href="?edit=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Alternar status?')">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-toggle-on"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="alert alert-warning mt-3 small">Dica: crie um usuário <strong>operador</strong> para o dia a dia e mantenha o <strong>admin</strong> só para gestão.</div>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
