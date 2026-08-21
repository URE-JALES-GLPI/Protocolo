<?php
// includes/helpers.php
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function dataBr(?string $dt): string {
    if (!$dt) return '-';
    $t = strtotime($dt);
    return $t ? date('d/m/Y H:i', $t) : h($dt);
}
function statusBadge(string $status): string {
    $map = [
        'aguardando' => '<span class="badge bg-warning text-dark">Aguardando retirada</span>',
        'retirada'   => '<span class="badge bg-success">Retirada</span>',
        'cancelada'  => '<span class="badge bg-secondary">Cancelada</span>',
    ];
    return $map[$status] ?? h($status);
}
function flash(string $key, ?string $msg = null) {
    if ($msg !== null) { $_SESSION['flash_'.$key] = $msg; return; }
    $k = 'flash_'.$key;
    if (isset($_SESSION[$k])) { $v = $_SESSION[$k]; unset($_SESSION[$k]); return $v; }
    return null;
}
