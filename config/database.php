<?php
// config/database.php - Conexão PDO MySQL
// Ajuste as credenciais conforme sua VM Ubuntu

define('DB_HOST', 'localhost');
define('DB_NAME', 'protocolo');
define('DB_USER', 'protocolo_user');
define('DB_PASS', 'Protocolo@2026');
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

// Helper para gerar código sequencial da pasta: PROT-2026-0001
function gerarCodigoPasta(PDO $pdo): string {
    $ano = date('Y');
    $prefix = "PROT-$ano-";
    $stmt = $pdo->prepare("SELECT codigo FROM pastas WHERE codigo LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $ultimo = $stmt->fetchColumn();
    if ($ultimo) {
        $num = (int)substr($ultimo, strlen($prefix)) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
}

function gerarCodigoTermo(string $tipo): string {
    $pref = $tipo === 'recebimento' ? 'TR' : 'TE';
    return $pref . '-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}
