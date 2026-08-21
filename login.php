<?php
session_start();
require_once __DIR__ . '/config/database.php';
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}
$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if ($username === '' || $senha === '') {
        $erro = 'Preencha usuário e senha.';
    } else {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("SELECT id, nome, username, senha_hash, perfil, ativo FROM usuarios WHERE username=? LIMIT 1");
            $stmt->execute([$username]);
            $u = $stmt->fetch();
            if (!$u || !$u['ativo']) {
                $erro = 'Usuário não encontrado ou inativo.';
            } elseif (!password_verify($senha, $u['senha_hash'])) {
                $erro = 'Senha incorreta.';
            } else {
                // Rehash se necessário
                if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) {
                    $novo = password_hash($senha, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?")->execute([$novo, $u['id']]);
                }
                $_SESSION['usuario_id'] = (int)$u['id'];
                $_SESSION['usuario_nome'] = $u['nome'];
                $_SESSION['username'] = $u['username'];
                $_SESSION['perfil'] = $u['perfil'];
                header('Location: dashboard.php');
                exit;
            }
        } catch (Exception $e) {
            $erro = 'Erro no banco: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - Protocolo</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{ background:#eef2f7; display:flex; align-items:center; justify-content:center; min-height:100vh; }
.login-card{ max-width:420px; width:100%; }
</style>
</head>
<body>
<div class="card shadow login-card">
  <div class="card-body p-4">
    <div class="text-center mb-4">
      <h4 class="fw-bold text-primary"><i class="bi bi-folder2-open"></i> Protocolo</h4>
      <p class="text-muted small mb-0">Sistema de Controle de Pastas - Acesso restrito</p>
    </div>
    <?php if ($erro): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <div class="mb-3">
        <label class="form-label">Usuário</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input name="username" class="form-control" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Senha</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input name="senha" type="password" class="form-control" required>
        </div>
      </div>
      <button class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right"></i> Entrar</button>
    </form>
    <div class="text-center mt-3 small text-muted">
      Padrão: <code>admin / admin123</code> <br>Altere após primeiro acesso em Usuários.
    </div>
  </div>
</div>
</body>
</html>
