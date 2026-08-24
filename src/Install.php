<?php
namespace GlpiPlugin\Protocolo;

use DBConnection;
use Migration;
use Config;

class Install
{
    public static function install(): bool
    {
        global $DB;

        $migration = new Migration(PLUGIN_PROTOCOLO_VERSION);

        // Tabela escolas -> glpi_plugin_protocolo_escolas
        if (!$DB->tableExists('glpi_plugin_protocolo_escolas')) {
            $query = "CREATE TABLE `glpi_plugin_protocolo_escolas` (
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
                `date_mod` DATETIME DEFAULT NULL,
                KEY `entities_id` (`entities_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
            $DB->doQuery($query);
        } else {
            $migration->addField('glpi_plugin_protocolo_escolas', 'entities_id', 'int', ['value' => 0, 'update' => 0]);
            $migration->addField('glpi_plugin_protocolo_escolas', 'is_recursive', 'bool', ['value' => 0]);
            $migration->migrationOneTable('glpi_plugin_protocolo_escolas');
        }

        // Tipos arquivo -> glpi_plugin_protocolo_tipos
        if (!$DB->tableExists('glpi_plugin_protocolo_tipos')) {
            $query = "CREATE TABLE `glpi_plugin_protocolo_tipos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(120) NOT NULL,
                `comment` VARCHAR(255) DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
            $DB->doQuery($query);

            // seed inicial (mesmo do migration_tipos_arquivo.sql)
            $DB->doQuery("INSERT INTO `glpi_plugin_protocolo_tipos` (name, comment) VALUES
                ('Ofício', 'Ofícios diversos'),
                ('Memorando', 'Memorandos e comunicados'),
                ('Processo', 'Processos administrativos'),
                ('Prestação de Contas', 'Documentos de prestação de contas'),
                ('Nota Fiscal / Recibo', 'Notas fiscais, recibos, comprovantes'),
                ('Fotos / Mídia', 'Fotos, prints, mídias'),
                ('Planilha', 'Planilhas e relatórios'),
                ('Ata', 'Atas de reunião'),
                ('Outros', 'Outros tipos não listados')
                ON DUPLICATE KEY UPDATE name=VALUES(name)");
        }

        // Pastas -> glpi_plugin_protocolo_pastas
        if (!$DB->tableExists('glpi_plugin_protocolo_pastas')) {
            $query = "CREATE TABLE `glpi_plugin_protocolo_pastas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `codigo` VARCHAR(30) NOT NULL UNIQUE,
                `plugin_protocolo_escolas_id` INT NOT NULL,
                `status` ENUM('aguardando','retirada','cancelada') NOT NULL DEFAULT 'aguardando',
                `data_recebimento` DATETIME NOT NULL,
                `recebido_de` VARCHAR(150) NOT NULL,
                `recebido_documento` VARCHAR(30) DEFAULT NULL,
                `observacao` TEXT DEFAULT NULL,
                `data_retirada` DATETIME DEFAULT NULL,
                `retirado_por` VARCHAR(150) DEFAULT NULL,
                `retirado_documento` VARCHAR(30) DEFAULT NULL,
                `observacao_retirada` TEXT DEFAULT NULL,
                `users_id` INT DEFAULT NULL COMMENT 'criado_por',
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
                CONSTRAINT `fk_pastas_escola` FOREIGN KEY (`plugin_protocolo_escolas_id`) REFERENCES `glpi_plugin_protocolo_escolas` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_pastas_users` FOREIGN KEY (`users_id`) REFERENCES `glpi_users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
            $DB->doQuery($query);
        } else {
            $migration->addField('glpi_plugin_protocolo_pastas', 'entities_id', 'int', ['value' => 0, 'update' => 0]);
            $migration->addField('glpi_plugin_protocolo_pastas', 'is_deleted', 'bool', ['value' => 0]);
            $migration->addField('glpi_plugin_protocolo_pastas', 'is_recursive', 'bool', ['value' => 0]);
            $migration->migrationOneTable('glpi_plugin_protocolo_pastas');
        }

        // Itens -> glpi_plugin_protocolo_itens
        if (!$DB->tableExists('glpi_plugin_protocolo_itens')) {
            $query = "CREATE TABLE `glpi_plugin_protocolo_itens` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `plugin_protocolo_pastas_id` INT NOT NULL,
                `name` VARCHAR(255) NOT NULL COMMENT 'descricao',
                `quantidade` INT NOT NULL DEFAULT 1,
                `comment` VARCHAR(255) DEFAULT NULL COMMENT 'observacao',
                KEY `plugin_protocolo_pastas_id` (`plugin_protocolo_pastas_id`),
                CONSTRAINT `fk_itens_pasta` FOREIGN KEY (`plugin_protocolo_pastas_id`) REFERENCES `glpi_plugin_protocolo_pastas` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
            $DB->doQuery($query);
        }

        // Pasta tipos N:N -> glpi_plugin_protocolo_pastatipos
        if (!$DB->tableExists('glpi_plugin_protocolo_pastatipos')) {
            $query = "CREATE TABLE `glpi_plugin_protocolo_pastatipos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `plugin_protocolo_pastas_id` INT NOT NULL,
                `plugin_protocolo_tipos_id` INT NOT NULL,
                UNIQUE KEY `uniq_pasta_tipo` (`plugin_protocolo_pastas_id`, `plugin_protocolo_tipos_id`),
                KEY `plugin_protocolo_tipos_id` (`plugin_protocolo_tipos_id`),
                CONSTRAINT `fk_pastatipos_pasta` FOREIGN KEY (`plugin_protocolo_pastas_id`) REFERENCES `glpi_plugin_protocolo_pastas` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_pastatipos_tipo` FOREIGN KEY (`plugin_protocolo_tipos_id`) REFERENCES `glpi_plugin_protocolo_tipos` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
            $DB->doQuery($query);
        }

        // Termos -> glpi_plugin_protocolo_termos
        if (!$DB->tableExists('glpi_plugin_protocolo_termos')) {
            $query = "CREATE TABLE `glpi_plugin_protocolo_termos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `plugin_protocolo_pastas_id` INT NOT NULL,
                `tipo` ENUM('recebimento','retirada') NOT NULL,
                `codigo` VARCHAR(50) NOT NULL UNIQUE,
                `arquivo_assinado` VARCHAR(255) DEFAULT NULL,
                `hash_verificacao` VARCHAR(64) DEFAULT NULL,
                `users_id` INT DEFAULT NULL,
                `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY `plugin_protocolo_pastas_id` (`plugin_protocolo_pastas_id`),
                KEY `tipo` (`tipo`),
                CONSTRAINT `fk_termos_pasta` FOREIGN KEY (`plugin_protocolo_pastas_id`) REFERENCES `glpi_plugin_protocolo_pastas` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
            $DB->doQuery($query);
        }

        // Notificações (placeholder)
        if (!$DB->tableExists('glpi_plugin_protocolo_notificacoes')) {
            $query = "CREATE TABLE `glpi_plugin_protocolo_notificacoes` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `plugin_protocolo_pastas_id` INT NOT NULL,
                `plugin_protocolo_escolas_id` INT NOT NULL,
                `canal` ENUM('email','whatsapp','sistema') NOT NULL DEFAULT 'sistema',
                `destinatario` VARCHAR(150) NOT NULL,
                `mensagem` TEXT NOT NULL,
                `status` ENUM('pendente','enviado','falha') NOT NULL DEFAULT 'pendente',
                `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY `plugin_protocolo_pastas_id` (`plugin_protocolo_pastas_id`),
                CONSTRAINT `fk_notif_pasta` FOREIGN KEY (`plugin_protocolo_pastas_id`) REFERENCES `glpi_plugin_protocolo_pastas` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_notif_escola` FOREIGN KEY (`plugin_protocolo_escolas_id`) REFERENCES `glpi_plugin_protocolo_escolas` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
            $DB->doQuery($query);
        }

        // Direitos / profiles - cria direitos se não existirem
        self::initRights();

        // Pasta de upload para termos assinados
        $uploadDir = GLPI_PLUGIN_DOC_DIR . '/protocolo/termos';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $migration->executeMigration();

        // Marca config instalada
        Config::setConfigurationValues('plugin:protocolo', ['installed' => date('Y-m-d H:i:s')]);

        return true;
    }

    public static function uninstall(): bool
    {
        global $DB;

        $tables = [
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
                $DB->doQuery("DROP TABLE IF EXISTS `$table`");
            }
        }

        // Remove direitos
        $DB->doQuery("DELETE FROM `glpi_profilerights` WHERE `name` LIKE 'plugin_protocolo_%'");
        $DB->doQuery("DELETE FROM `glpi_displaypreferences` WHERE `itemtype` LIKE 'GlpiPlugin\\\\Protocolo\\\\%'");

        // Remove config
        $DB->doQuery("DELETE FROM `glpi_configs` WHERE `context`='plugin:protocolo'");

        return true;
    }

    /**
     * Inicializa direitos de perfil (compat GLPI 11)
     */
    private static function initRights(): void
    {
        global $DB;

        $rights = [
            'plugin_protocolo_pasta'  => 255, // ALLSTANDARDRIGHT = 255 (ler+criar+editar+excluir etc)
            'plugin_protocolo_escola' => 255,
            'plugin_protocolo_tipo'   => 255,
            'plugin_protocolo_config' => 255,
        ];

        // Busca perfis
        $profiles = $DB->request(['FROM' => 'glpi_profiles']);
        foreach ($profiles as $profile) {
            $profileId = $profile['id'];
            // Só admin ganha tudo por padrão; outros ganham ver pasta/escola
            $isSuperAdmin = ($profile['interface'] ?? '') === 'central' && ($profile['name'] ?? '') === 'Super-Admin';
            // Na prática GLPI nome pode ser Super-Admin ou Admin; vamos dar full para quem já tem config UPDATE
            // Simples: se for id 4 (Super-Admin) dá tudo, senão dá leitura
            foreach ($rights as $rightName => $value) {
                $existing = $DB->request([
                    'FROM' => 'glpi_profilerights',
                    'WHERE' => [
                        'profiles_id' => $profileId,
                        'name'        => $rightName
                    ]
                ]);
                if (count($existing) === 0) {
                    $default = 0;
                    if ($profileId == 4) { // Super-Admin
                        $default = $value;
                    } elseif (in_array($rightName, ['plugin_protocolo_pasta', 'plugin_protocolo_escola'])) {
                        $default = 1; // READ
                    }
                    $DB->insert('glpi_profilerights', [
                        'profiles_id' => $profileId,
                        'name'        => $rightName,
                        'rights'      => $default
                    ]);
                }
            }
        }

        // Também registra para que apareça na aba Perfil -> Direitos
        // GLPI lê direitos via $PLUGIN_HOOKS['change_profile'] e classe Profile::getRights()
    }

    /**
     * Helpers de código sequencial (mantidos compat com standalone)
     */
    public static function gerarCodigoPasta(): string
    {
        global $DB;
        $ano = date('Y');
        $prefix = "PROT-$ano-";
        $iterator = $DB->request([
            'SELECT' => ['codigo'],
            'FROM'   => 'glpi_plugin_protocolo_pastas',
            'WHERE'  => ['codigo' => ['LIKE', $prefix . '%']],
            'ORDER'  => 'id DESC',
            'LIMIT'  => 1
        ]);
        $ultimo = null;
        foreach ($iterator as $row) {
            $ultimo = $row['codigo'];
        }
        if ($ultimo) {
            $num = (int)substr($ultimo, strlen($prefix)) + 1;
        } else {
            $num = 1;
        }
        return $prefix . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }

    public static function gerarCodigoTermo(string $tipo): string
    {
        $pref = $tipo === 'recebimento' ? 'TR' : 'TE';
        return $pref . '-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}
