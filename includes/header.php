<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
requireLogin();
$user = currentUser();
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle ?? 'Protocolo') ?> - Sistema de Protocolo</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="dashboard.php"><i class="bi bi-folder2-open"></i> Protocolo</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="pastas.php"><i class="bi bi-collection"></i> Pastas</a></li>
        <li class="nav-item"><a class="nav-link" href="pasta_nova.php"><i class="bi bi-folder-plus"></i> Nova Pasta</a></li>
        <li class="nav-item"><a class="nav-link" href="escolas.php"><i class="bi bi-building"></i> Escolas</a></li>
        <?php if (hasPerm('pode_acessar_config')): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> Configurações</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="configuracoes.php"><i class="bi bi-gear"></i> Painel</a></li>
            <?php if (hasPerm('pode_gerenciar_tipos')): ?><li><a class="dropdown-item" href="tipos_arquivo.php"><i class="bi bi-tags"></i> Tipos de Arquivos</a></li><?php endif; ?>
            <?php if (hasPerm('pode_gerenciar_usuarios')): ?><li><a class="dropdown-item" href="usuarios.php"><i class="bi bi-people"></i> Usuários</a></li><?php endif; ?>
            <li><a class="dropdown-item" href="escolas.php"><i class="bi bi-building"></i> Escolas</a></li>
          </ul>
        </li>
        <?php else: ?>
          <?php if ($user['perfil']==='admin'): ?><li class="nav-item"><a class="nav-link" href="usuarios.php"><i class="bi bi-people"></i> Usuários</a></li><?php endif; ?>
        <?php endif; ?>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <span class="text-white small"><i class="bi bi-person-circle"></i> <?= h($user['nome']) ?> (<?= h($user['perfil']) ?>)</span>
        <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Sair</a>
      </div>
    </div>
  </div>
</nav>
<main class="container py-4">
<?php
$ok = flash('success');
$err = flash('error');
if ($ok): ?><div class="alert alert-success alert-dismissible fade show"><?= h($ok) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;
if ($err): ?><div class="alert alert-danger alert-dismissible fade show"><?= h($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;
?>
