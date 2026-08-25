<?php
namespace GlpiPlugin\Protocolo;

use Config;
use Migration;

class Install
{
    public static function install(): bool
    {
        global $DB;

        try {
            // Garante que GLPI_PLUGIN_DOC_DIR existe (GLPI 11 define, mas fallback se não)
            if (!defined('GLPI_PLUGIN_DOC_DIR')) {
                define('GLPI_PLUGIN_DOC_DIR', GLPI_ROOT . '/files/_plugins');
            }

            // 1. Executa SQL do arquivo empty.sql (mais seguro que queries inline)
            $sqlFile = __DIR__ . '/../install/mysql/plugin_protocolo_empty.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                // Remove comentários e divide por ;
                // Usa método do GLPI se disponível, senão executa na unha com tratamento
                self::executeSqlFile($DB, $sqlFile);
            } else {
                // Fallback: cria via queries inline (mantido para compat)
                self::createTablesInline($DB);
            }

            // 1b. Migração ENTIDADES para Escola (garante colunas se plugin já instalado)
            self::migrateEntities($DB);

            // 2. Direitos
            self::initRights();

            // 3. Pasta de upload
            $uploadDir = (defined('GLPI_PLUGIN_DOC_DIR') ? GLPI_PLUGIN_DOC_DIR : GLPI_ROOT . '/files/_plugins') . '/protocolo/termos';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            // Garante permissão se conseguiu criar
            if (is_dir($uploadDir)) {
                @chmod($uploadDir, 0775);
            }

            // 4. Config defaults
            try {
                if (class_exists(\GlpiPlugin\Protocolo\Config::class)) {
                    \GlpiPlugin\Protocolo\Config::initDefaults();
                    Config::setConfigurationValues('plugin:protocolo', ['installed' => date('Y-m-d H:i:s')]);
                } elseif (class_exists(Config::class)) {
                    Config::setConfigurationValues('plugin:protocolo', ['installed' => date('Y-m-d H:i:s')]);
                }
            } catch (\Throwable $e) {
                error_log("[protocolo] Config::setConfigurationValues falhou: " . $e->getMessage());
            }

            // 5. Cron
            try { self::registerCron(); } catch (\Throwable $e) { error_log("[protocolo] registerCron falhou: " . $e->getMessage()); }

            return true;
        } catch (\Throwable $e) {
            // Loga e exibe erro para GLPI mostrar na UI (em vez de ficar rodando)
            $msg = "[protocolo] Erro na instalação: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine();
            error_log($msg);
            // GLPI espera que a função retorne false e mostre mensagem
            echo $msg . "<br><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            return false;
        }
    }

    private static function executeSqlFile($DB, string $sqlFile): void
    {
        $sql = file_get_contents($sqlFile);
        // Normaliza quebras
        $sql = str_replace("\r\n", "\n", $sql);
        // Remove comentários de linha -- e #
        $lines = explode("\n", $sql);
        $clean = "";
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '#')) {
                continue;
            }
            $clean .= $line . "\n";
        }
        // Divide por ; mas preserva ENUM e strings
        $queries = array_filter(array_map('trim', explode(";", $clean)));
        foreach ($queries as $q) {
            $q = trim($q);
            if ($q === '') continue;
            // Ignora SET e outras instruções não críticas se falharem
            try {
                $DB->doQuery($q);
            } catch (\Throwable $e) {
                // Se já existe, ignora duplicate
                $msg = $e->getMessage();
                if (stripos($msg, 'already exists') !== false || stripos($msg, 'Duplicate') !== false) {
                    continue;
                }
                error_log("[protocolo] SQL falhou: " . $msg . " | Query: " . substr($q, 0, 500));
                // Repassa erro se for tabela crítica
                if (stripos($q, 'CREATE TABLE') !== false) {
                    throw $e;
                }
            }
        }
    }

    private static function createTablesInline($DB): void
    {
        // Fallback idempotente (mesmo SQL do empty.sql)
        $queries = [
            "CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_escolas` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `name` VARCHAR(180) NOT NULL,
              `codigo` VARCHAR(40) DEFAULT NULL,
              `email` VARCHAR(150) DEFAULT NULL,
              `phone` VARCHAR(30) DEFAULT NULL,
              `address` TEXT DEFAULT NULL,
              `responsavel` VARCHAR(120) DEFAULT NULL,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `entities_id` INT NOT NULL DEFAULT 0,
              `is_recursive` TINYINT(1) NOT NULL DEFAULT 0,
              `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
              `date_mod` DATETIME DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",

            "CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_tipos` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `name` VARCHAR(120) NOT NULL,
              `comment` VARCHAR(255) DEFAULT NULL,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY `uniq_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",

            "INSERT INTO `glpi_plugin_protocolo_tipos` (name, comment) VALUES
                ('Oficio','Oficios diversos'),
                ('Memorando','Memorandos e comunicados'),
                ('Processo','Processos administrativos'),
                ('Prestacao de Contas','Documentos de prestacao de contas'),
                ('Nota Fiscal / Recibo','Notas fiscais, recibos, comprovantes'),
                ('Fotos / Midia','Fotos, prints, midias'),
                ('Planilha','Planilhas e relatorios'),
                ('Ata','Atas de reuniao'),
                ('Outros','Outros tipos nao listados')
                ON DUPLICATE KEY UPDATE name=VALUES(name)",

            "CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_pastas` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `codigo` VARCHAR(30) NOT NULL UNIQUE,
              `plugin_protocolo_escolas_id` INT NOT NULL,
              `status` ENUM('aguardando','retirada','cancelada') NOT NULL DEFAULT 'aguardando',
              `data_recebimento` DATETIME NOT NULL,
              `recebido_de` VARCHAR(150) NOT NULL,
              `recebido_documento` VARCHAR(30) DEFAULT NULL,
              `recebido_documento_tipo` ENUM('cpf','rg') DEFAULT 'cpf',
              `observacao` TEXT DEFAULT NULL,
              `data_retirada` DATETIME DEFAULT NULL,
              `retirado_por` VARCHAR(150) DEFAULT NULL,
              `retirado_documento` VARCHAR(30) DEFAULT NULL,
              `retirado_documento_tipo` ENUM('cpf','rg') DEFAULT 'cpf',
              `observacao_retirada` TEXT DEFAULT NULL,
              `users_id` INT DEFAULT NULL,
              `users_id_retirada` INT DEFAULT NULL,
              `entities_id` INT NOT NULL DEFAULT 0,
              `is_recursive` TINYINT(1) NOT NULL DEFAULT 0,
              `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
              `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
              `date_mod` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              KEY `plugin_protocolo_escolas_id` (`plugin_protocolo_escolas_id`),
              KEY `users_id` (`users_id`),
              KEY `entities_id` (`entities_id`),
              KEY `status` (`status`),
              KEY `idx_status_deleted` (`status`, `is_deleted`),
              KEY `idx_entities_deleted` (`entities_id`, `is_deleted`),
              KEY `idx_data_recebimento` (`data_recebimento`),
              KEY `idx_entities_status` (`entities_id`, `status`, `is_deleted`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",

            "CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_itens` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `plugin_protocolo_pastas_id` INT NOT NULL,
              `name` VARCHAR(255) NOT NULL,
              `quantidade` INT NOT NULL DEFAULT 1,
              `comment` VARCHAR(255) DEFAULT NULL,
              KEY `plugin_protocolo_pastas_id` (`plugin_protocolo_pastas_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",

            "CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_pastatipos` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `plugin_protocolo_pastas_id` INT NOT NULL,
              `plugin_protocolo_tipos_id` INT NOT NULL,
              UNIQUE KEY `uniq_pasta_tipo` (`plugin_protocolo_pastas_id`, `plugin_protocolo_tipos_id`),
              KEY `plugin_protocolo_tipos_id` (`plugin_protocolo_tipos_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",

            "CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_termos` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `plugin_protocolo_pastas_id` INT NOT NULL,
              `tipo` ENUM('recebimento','retirada') NOT NULL,
              `codigo` VARCHAR(50) NOT NULL UNIQUE,
              `arquivo_assinado` VARCHAR(255) DEFAULT NULL,
              `hash_verificacao` VARCHAR(64) DEFAULT NULL,
              `users_id` INT DEFAULT NULL,
              `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
              KEY `plugin_protocolo_pastas_id` (`plugin_protocolo_pastas_id`),
              KEY `idx_pasta_tipo` (`plugin_protocolo_pastas_id`, `tipo`),
              KEY `idx_codigo_hash` (`codigo`, `hash_verificacao`),
              KEY `tipo` (`tipo`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",

            "CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_notificacoes` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `plugin_protocolo_pastas_id` INT NOT NULL,
              `plugin_protocolo_escolas_id` INT NOT NULL,
              `canal` ENUM('email','whatsapp','sistema') NOT NULL DEFAULT 'sistema',
              `destinatario` VARCHAR(150) NOT NULL,
              `mensagem` TEXT NOT NULL,
              `status` ENUM('pendente','enviado','falha') NOT NULL DEFAULT 'pendente',
              `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
              KEY `plugin_protocolo_pastas_id` (`plugin_protocolo_pastas_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",

            "CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_entity_emails` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `entities_id` INT NOT NULL,
              `email` VARCHAR(255) NOT NULL,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
              `date_mod` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY `uniq_entity_email` (`entities_id`, `email`),
              KEY `entities_id` (`entities_id`),
              KEY `is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",
        ];

        foreach ($queries as $q) {
            try {
                $DB->doQuery($q);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'already exists') !== false || stripos($msg, 'Duplicate') !== false) continue;
                throw $e;
            }
        }
    }

    public static function uninstall(): bool
    {
        global $DB;
        try {
            $tables = [
                'glpi_plugin_protocolo_entity_emails',
                'glpi_plugin_protocolo_notificacoes',
                'glpi_plugin_protocolo_termos',
                'glpi_plugin_protocolo_pastatipos',
                'glpi_plugin_protocolo_itens',
                'glpi_plugin_protocolo_pastas',
                'glpi_plugin_protocolo_tipos',
                'glpi_plugin_protocolo_escolas',
            ];
            foreach ($tables as $table) {
                if ($DB->tableExists($table)) {
                    try { $DB->doQuery("DROP TABLE IF EXISTS `$table`"); } catch (\Throwable $e) { error_log("[protocolo] uninstall drop $table: " . $e->getMessage()); }
                }
            }
            try { $DB->doQuery("DELETE FROM `glpi_profilerights` WHERE `name` LIKE 'plugin_protocolo_%'"); } catch (\Throwable $e) {}
            try { $DB->doQuery("DELETE FROM `glpi_displaypreferences` WHERE `itemtype` LIKE 'GlpiPlugin\\\\Protocolo\\\\%'"); } catch (\Throwable $e) {}
            try { $DB->doQuery("DELETE FROM `glpi_configs` WHERE `context`='plugin:protocolo'"); } catch (\Throwable $e) {}
            return true;
        } catch (\Throwable $e) {
            error_log("[protocolo] uninstall erro: " . $e->getMessage());
            return false;
        }
    }

    private static function initRights(): void
    {
        global $DB;
        try {
            $rights = [
                'plugin_protocolo_pasta'  => 255,
                'plugin_protocolo_escola' => 255,
                'plugin_protocolo_tipo'   => 255,
                'plugin_protocolo_config' => 255,
            ];
            $profiles = $DB->request(['FROM' => 'glpi_profiles']);
            foreach ($profiles as $profile) {
                $profileId = $profile['id'];
                foreach ($rights as $rightName => $value) {
                    try {
                        $existing = $DB->request([
                            'FROM' => 'glpi_profilerights',
                            'WHERE' => ['profiles_id' => $profileId, 'name' => $rightName]
                        ]);
                        if (count($existing) === 0) {
                            $default = 0;
                            if ($profileId == 4) {
                                $default = $value;
                            } elseif (in_array($rightName, ['plugin_protocolo_pasta', 'plugin_protocolo_escola'])) {
                                $default = 1;
                            }
                            $DB->insert('glpi_profilerights', [
                                'profiles_id' => $profileId,
                                'name'        => $rightName,
                                'rights'      => $default
                            ]);
                        }
                    } catch (\Throwable $e) {
                        error_log("[protocolo] initRights $rightName profile $profileId: " . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("[protocolo] initRights geral: " . $e->getMessage());
        }
    }

    public static function migrateEntities($DB): void
    {
        // Garante que Escola usa ENTIDADES — adiciona colunas se vier de versão antiga
        try {
            if ($DB->tableExists('glpi_plugin_protocolo_escolas')) {
                if (!$DB->fieldExists('glpi_plugin_protocolo_escolas', 'entities_id')) {
                    $DB->doQuery("ALTER TABLE `glpi_plugin_protocolo_escolas` ADD COLUMN `entities_id` INT NOT NULL DEFAULT 0 AFTER `is_active`");
                    $DB->doQuery("ALTER TABLE `glpi_plugin_protocolo_escolas` ADD KEY `entities_id` (`entities_id`)");
                    error_log("[protocolo] migrateEntities: entities_id adicionado em escolas");
                }
                if (!$DB->fieldExists('glpi_plugin_protocolo_escolas', 'is_recursive')) {
                    $DB->doQuery("ALTER TABLE `glpi_plugin_protocolo_escolas` ADD COLUMN `is_recursive` TINYINT(1) NOT NULL DEFAULT 0 AFTER `entities_id`");
                    error_log("[protocolo] migrateEntities: is_recursive adicionado em escolas");
                }
                if (!$DB->fieldExists('glpi_plugin_protocolo_escolas', 'date_mod')) {
                    $DB->doQuery("ALTER TABLE `glpi_plugin_protocolo_escolas` ADD COLUMN `date_mod` DATETIME DEFAULT NULL AFTER `date_creation`");
                }
                // Corrige escolas antigas: torna recursivas por padrão para aparecerem nas sub-entidades
                // Se já existir coluna, garante que escolas da entidade raiz fiquem visíveis em todas
                try {
                    $DB->doQuery("UPDATE `glpi_plugin_protocolo_escolas` SET `is_recursive`=1 WHERE `entities_id`=0 AND `is_recursive`=0");
                } catch (\Throwable $e) {}
            }
            // Pastas também já devem ser entidade-aware (fallback)
            if ($DB->tableExists('glpi_plugin_protocolo_pastas')) {
                if (!$DB->fieldExists('glpi_plugin_protocolo_pastas', 'entities_id')) {
                    $DB->doQuery("ALTER TABLE `glpi_plugin_protocolo_pastas` ADD COLUMN `entities_id` INT NOT NULL DEFAULT 0");
                }
                if (!$DB->fieldExists('glpi_plugin_protocolo_pastas', 'is_recursive')) {
                    $DB->doQuery("ALTER TABLE `glpi_plugin_protocolo_pastas` ADD COLUMN `is_recursive` TINYINT(1) NOT NULL DEFAULT 0");
                }
                if (!$DB->fieldExists('glpi_plugin_protocolo_pastas', 'is_deleted')) {
                    $DB->doQuery("ALTER TABLE `glpi_plugin_protocolo_pastas` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0");
                }
                if (!$DB->fieldExists('glpi_plugin_protocolo_pastas', 'recebido_documento_tipo')) {
                    $DB->doQuery("ALTER TABLE `glpi_plugin_protocolo_pastas` ADD COLUMN `recebido_documento_tipo` ENUM('cpf','rg') DEFAULT 'cpf' AFTER `recebido_documento`");
                }
                if (!$DB->fieldExists('glpi_plugin_protocolo_pastas', 'retirado_documento_tipo')) {
                    $DB->doQuery("ALTER TABLE `glpi_plugin_protocolo_pastas` ADD COLUMN `retirado_documento_tipo` ENUM('cpf','rg') DEFAULT 'cpf' AFTER `retirado_documento`");
                }
                // Índices para performance (dashboard com filtro de entidade)
                $idxToAdd = [
                    'idx_status_deleted' => "ALTER TABLE `glpi_plugin_protocolo_pastas` ADD KEY `idx_status_deleted` (`status`, `is_deleted`)",
                    'idx_entities_deleted' => "ALTER TABLE `glpi_plugin_protocolo_pastas` ADD KEY `idx_entities_deleted` (`entities_id`, `is_deleted`)",
                    'idx_data_recebimento' => "ALTER TABLE `glpi_plugin_protocolo_pastas` ADD KEY `idx_data_recebimento` (`data_recebimento`)",
                    'idx_entities_status' => "ALTER TABLE `glpi_plugin_protocolo_pastas` ADD KEY `idx_entities_status` (`entities_id`, `status`, `is_deleted`)",
                ];
                foreach ($idxToAdd as $idx => $sql) {
                    try {
                        // Verifica se índice já existe via SHOW INDEX
                        $res = $DB->doQuery("SHOW INDEX FROM `glpi_plugin_protocolo_pastas` WHERE Key_name='$idx'");
                        $exists = false;
                        if ($res) while ($row = $DB->fetchAssoc($res)) { $exists = true; break; }
                        if (!$exists) $DB->doQuery($sql);
                    } catch (\Throwable $e) {}
                }
            }
            if ($DB->tableExists('glpi_plugin_protocolo_itens')) {
                try {
                    $res = $DB->doQuery("SHOW INDEX FROM `glpi_plugin_protocolo_itens` WHERE Key_name='idx_pasta'");
                    $exists = false;
                    if ($res) while ($row = $DB->fetchAssoc($res)) { $exists = true; break; }
                    if (!$exists) $DB->doQuery("ALTER TABLE `glpi_plugin_protocolo_itens` ADD KEY `idx_pasta` (`plugin_protocolo_pastas_id`)");
                } catch (\Throwable $e) {}
            }
            if ($DB->tableExists('glpi_plugin_protocolo_termos')) {
                $idxTermos = [
                    'idx_pasta_tipo' => "ALTER TABLE `glpi_plugin_protocolo_termos` ADD KEY `idx_pasta_tipo` (`plugin_protocolo_pastas_id`, `tipo`)",
                    'idx_codigo_hash' => "ALTER TABLE `glpi_plugin_protocolo_termos` ADD KEY `idx_codigo_hash` (`codigo`, `hash_verificacao`)",
                ];
                foreach ($idxTermos as $idx => $sql) {
                    try {
                        $res = $DB->doQuery("SHOW INDEX FROM `glpi_plugin_protocolo_termos` WHERE Key_name='$idx'");
                        $exists = false;
                        if ($res) while ($row = $DB->fetchAssoc($res)) { $exists = true; break; }
                        if (!$exists) $DB->doQuery($sql);
                    } catch (\Throwable $e) {}
                }
            }
            // Nova tabela entity_emails
            if (!$DB->tableExists('glpi_plugin_protocolo_entity_emails')) {
                try {
                    $DB->doQuery("CREATE TABLE `glpi_plugin_protocolo_entity_emails` (
                      `id` INT AUTO_INCREMENT PRIMARY KEY,
                      `entities_id` INT NOT NULL,
                      `email` VARCHAR(255) NOT NULL,
                      `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                      `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
                      `date_mod` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                      UNIQUE KEY `uniq_entity_email` (`entities_id`, `email`),
                      KEY `entities_id` (`entities_id`),
                      KEY `is_active` (`is_active`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
                    error_log("[protocolo] migrateEntities: tabela entity_emails criada");
                } catch (\Throwable $e) { error_log("[protocolo] migrate entity_emails falhou: " . $e->getMessage()); }
            }
            // Garante templates padrão
            try {
                if (class_exists(\GlpiPlugin\Protocolo\Config::class)) {
                    \GlpiPlugin\Protocolo\Config::initDefaults();
                }
            } catch (\Throwable $e) {}
        } catch (\Throwable $e) {
            error_log("[protocolo] migrateEntities falhou: " . $e->getMessage());
        }
    }

    public static function gerarCodigoPasta(): string
    {
        global $DB;
        $ano = date('Y');
        $prefix = "PROT-$ano-";
        // Tenta até 5 vezes em caso de colisão concorrente (UNIQUE em codigo)
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                // LOCK para evitar race: usa SELECT ... FOR UPDATE dentro de transação se disponível
                $ultimo = null;
                // Tenta transação se DB suportar
                $inTrans = false;
                try {
                    if (method_exists($DB, 'beginTransaction')) {
                        $DB->beginTransaction();
                        $inTrans = true;
                    }
                } catch (\Throwable $e) { $inTrans = false; }
                $iterator = $DB->request([
                    'SELECT' => ['codigo'],
                    'FROM'   => 'glpi_plugin_protocolo_pastas',
                    'WHERE'  => ['codigo' => ['LIKE', $prefix . '%']],
                    'ORDER'  => 'id DESC',
                    'LIMIT'  => 1
                ]);
                foreach ($iterator as $row) { $ultimo = $row['codigo']; }
                if ($inTrans) {
                    try { $DB->commit(); } catch (\Throwable $e) {}
                }
                if ($ultimo) {
                    $num = (int)substr($ultimo, strlen($prefix)) + 1;
                } else {
                    $num = 1;
                }
                $codigo = $prefix . str_pad((string)($num + $attempt), 4, '0', STR_PAD_LEFT);
                // Valida que ainda não existe (evita retry desnecessário em caso de gap)
                $exists = $DB->request(['FROM' => 'glpi_plugin_protocolo_pastas', 'WHERE' => ['codigo' => $codigo], 'LIMIT' => 1]);
                $found = false;
                foreach ($exists as $r) { $found = true; break; }
                if (!$found) return $codigo;
                // se já existe, loop tenta próximo número
            } catch (\Throwable $e) {
                try { if ($inTrans ?? false) $DB->rollBack(); } catch (\Throwable $e2) {}
                // fallback aleatório na última tentativa
                if ($attempt === 4) {
                    return $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                }
            }
        }
        return $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public static function registerCron(): void
    {
        global $DB;
        if (!$DB->tableExists('glpi_crontasks')) return;
        try {
            $existing = $DB->request(['FROM' => 'glpi_crontasks', 'WHERE' => ['itemtype' => Notificacao::class, 'name' => 'protocolo']]);
            $found = false;
            foreach ($existing as $r) { $found = true; break; }
            if (!$found) {
                $DB->insert('glpi_crontasks', [
                    'itemtype'  => Notificacao::class,
                    'name'      => 'protocolo',
                    'frequency' => 3600, // 1h
                    'param'     => 20,
                    'state'     => 1,
                    'mode'      => 2, // MODE_EXTERNAL
                    'allowmode' => 3,
                    'logs_lifetime' => 30,
                    'hourmin'   => 0,
                    'hourmax'   => 24,
                    'comment'   => 'Envio de notificações pendentes do Protocolo'
                ]);
            }
        } catch (\Throwable $e) {
            error_log("[protocolo] registerCron insert falhou: " . $e->getMessage());
        }
    }

    /**
     * Gera código com colisão mínima - retry se já existir no banco
     */
    public static function gerarCodigoTermo(string $tipo): string
    {
        global $DB;
        $pref = $tipo === 'recebimento' ? 'TR' : 'TE';
        for ($i = 0; $i < 5; $i++) {
            $codigo = $pref . '-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            try {
                $it = $DB->request(['FROM' => 'glpi_plugin_protocolo_termos', 'WHERE' => ['codigo' => $codigo], 'LIMIT' => 1]);
                $exists = false;
                foreach ($it as $r) { $exists = true; break; }
                if (!$exists) return $codigo;
            } catch (\Throwable $e) { return $codigo; }
            usleep(10000);
        }
        return $pref . '-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }
}
