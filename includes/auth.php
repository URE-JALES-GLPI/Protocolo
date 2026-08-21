<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['usuario_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['perfil'] ?? '') !== 'admin') {
        http_response_code(403);
        die('Acesso negado. Apenas administradores.');
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['usuario_id'],
        'nome' => $_SESSION['usuario_nome'],
        'username' => $_SESSION['username'],
        'perfil' => $_SESSION['perfil'],
    ];
}

function getPermissoes(int $userId = null): array {
    if ($userId === null) $userId = (int)($_SESSION['usuario_id'] ?? 0);
    if (!$userId) return [];
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM usuario_permissoes WHERE usuario_id=?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row) return $row;
    } catch (Exception $e) {}
    // fallback pelo perfil
    $isAdmin = (($_SESSION['perfil'] ?? '') === 'admin');
    return [
        'pode_gerenciar_tipos' => $isAdmin ? 1 : 0,
        'pode_gerenciar_escolas' => 1,
        'pode_gerenciar_usuarios' => $isAdmin ? 1 : 0,
        'pode_acessar_config' => $isAdmin ? 1 : 0,
    ];
}

function hasPerm(string $perm): bool {
    // admin sempre tem tudo
    if (($_SESSION['perfil'] ?? '') === 'admin') return true;
    $perms = getPermissoes();
    return !empty($perms[$perm]);
}

function requirePerm(string $perm): void {
    requireLogin();
    if (!hasPerm($perm)) {
        http_response_code(403);
        die('Acesso negado. Você não tem permissão para acessar esta área.');
    }
}

function requireConfig(): void {
    requirePerm('pode_acessar_config');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_validate(?string $token): bool {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token);
}
