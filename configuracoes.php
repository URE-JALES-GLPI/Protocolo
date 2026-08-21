<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requireConfig();
$pdo = getPDO();

// Salvar permissões
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) die('CSRF inválido');
    $acao = $_POST['acao'] ?? '';
    if ($acao==='save_perms') {
        $uid = (int)($_POST['usuario_id'] ?? 0);
        $p_tipos = isset($_POST['pode_gerenciar_tipos']) ? 1 : 0;
        $p_escolas = isset($_POST['pode_gerenciar_escolas']) ? 1 : 0;
        $p_usuarios = isset($_POST['pode_gerenciar_usuarios']) ? 1 : 0;
        $p_config = isset($_POST['pode_acessar_config']) ? 1 : 0;
        $pdo->prepare("INSERT INTO usuario_permissoes (usuario_id, pode_gerenciar_tipos, pode_gerenciar_escolas, pode_gerenciar_usuarios, pode_acessar_config) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE pode_gerenciar_tipos=VALUES(pode_gerenciar_tipos), pode_gerenciar_escolas=VALUES(pode_gerenciar_escolas), pode_gerenciar_usuarios=VALUES(pode_gerenciar_usuarios), pode_acessar_config=VALUES(pode_acessar_config)")
            ->execute([$uid,$p_tipos,$p_escolas,$p_usuarios,$p_config]);
        flash('success','Permissões atualizadas.');
        header('Location: configuracoes.php'); exit;
    }
}

try {
    $usuarios = $pdo->query("SELECT u.id, u.nome, u.username, u.perfil, up.pode_gerenciar_tipos, up.pode_gerenciar_escolas, up.pode_gerenciar_usuarios, up.pode_acessar_config FROM usuarios u LEFT JOIN usuario_permissoes up ON up.usuario_id=u.id ORDER BY u.nome")->fetchAll();
} catch(PDOException $e) { $usuarios = []; }
if(empty($usuarios)){
    try {
        $usuarios = $pdo->query("SELECT id, nome, username, perfil, 0 as pode_gerenciar_tipos, 0 as pode_gerenciar_escolas, 0 as pode_gerenciar_usuarios, 0 as pode_acessar_config FROM usuarios ORDER BY nome")->fetchAll();
    } catch(PDOException $e) { $usuarios = []; }
    if(empty($usuarios)) { $usuarios = []; }
    // aviso se tabela ainda não migrada
    if (!empty($usuarios)) flash('error','Tabela de permissões ainda não criada. Rode: mysql -u protocolo_user -pProtocolo@2026 protocolo < sql/migration_permissoes.sql');
}

$pageTitle='Configurações';
include __DIR__.'/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-gear"></i> Configurações</h4>

<div class="row g-4">
  <div class="col-md-4">
    <div class="list-group shadow-sm">
      <a href="tipos_arquivo.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
        <span><i class="bi bi-tags"></i> Tipos de Arquivos (Recebimento)</span><i class="bi bi-chevron-right"></i>
      </a>
      <a href="usuarios.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people"></i> Usuários</span><i class="bi bi-chevron-right"></i>
      </a>
      <a href="escolas.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
        <span><i class="bi bi-building"></i> Escolas</span><i class="bi bi-chevron-right"></i>
      </a>
    </div>
    <div class="alert alert-info small mt-3">
      <strong>Acesso:</strong> apenas <code>admin</code> ou usuários com <code>pode_acessar_config</code> veem este menu. Operadores sem permissão não veem Configurações nem Tipos.
    </div>
  </div>

  <div class="col-md-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong><i class="bi bi-shield-lock"></i> Permissões por usuário</strong></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Usuário</th><th>Tipos</th><th>Escolas</th><th>Usuários</th><th>Config</th><th></th></tr></thead>
          <tbody>
          <?php foreach($usuarios as $u): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= h($u['nome']) ?> <small class="text-muted">(<?= h($u['username']) ?>)</small></div>
                <span class="badge <?= $u['perfil']==='admin'?'bg-primary':'bg-secondary' ?>"><?= h($u['perfil']) ?></span>
              </td>
              <td colspan="5" class="p-0">
                <form method="post" class="d-flex align-items-center gap-2 px-2 py-2 flex-wrap">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="save_perms">
                  <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                  <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="pode_gerenciar_tipos" <?= !empty($u['pode_gerenciar_tipos'])?'checked':'' ?>> <small>Tipos</small></label>
                  <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="pode_gerenciar_escolas" <?= !empty($u['pode_gerenciar_escolas'])?'checked':'' ?>> <small>Escolas</small></label>
                  <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="pode_gerenciar_usuarios" <?= !empty($u['pode_gerenciar_usuarios'])?'checked':'' ?>> <small>Usuários</small></label>
                  <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="pode_acessar_config" <?= !empty($u['pode_acessar_config'])?'checked':'' ?>> <small>Config</small></label>
                  <button class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-check"></i> Salvar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
