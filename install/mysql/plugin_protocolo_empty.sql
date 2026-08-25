-- plugin_protocolo_empty.sql - GLPI Plugin Protocolo - Schema v1.0.0
-- Compatível com Install.php (usado como fallback)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_escolas` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_tipos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `comment` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_pastas` (
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
  KEY `idx_status_deleted` (`status`, `is_deleted`),
  KEY `idx_entities_deleted` (`entities_id`, `is_deleted`),
  KEY `idx_data_recebimento` (`data_recebimento`),
  KEY `idx_escola` (`plugin_protocolo_escolas_id`),
  KEY `idx_entities_status` (`entities_id`, `status`, `is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_itens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plugin_protocolo_pastas_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `quantidade` INT NOT NULL DEFAULT 1,
  `comment` VARCHAR(255) DEFAULT NULL,
  KEY `idx_pasta` (`plugin_protocolo_pastas_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_pastatipos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plugin_protocolo_pastas_id` INT NOT NULL,
  `plugin_protocolo_tipos_id` INT NOT NULL,
  UNIQUE KEY `uniq_pasta_tipo` (`plugin_protocolo_pastas_id`, `plugin_protocolo_tipos_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_termos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plugin_protocolo_pastas_id` INT NOT NULL,
  `tipo` ENUM('recebimento','retirada') NOT NULL,
  `codigo` VARCHAR(50) NOT NULL UNIQUE,
  `arquivo_assinado` VARCHAR(255) DEFAULT NULL,
  `hash_verificacao` VARCHAR(64) DEFAULT NULL,
  `users_id` INT DEFAULT NULL,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_pasta_tipo` (`plugin_protocolo_pastas_id`, `tipo`),
  KEY `idx_codigo_hash` (`codigo`, `hash_verificacao`),
  KEY `idx_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_notificacoes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plugin_protocolo_pastas_id` INT NOT NULL,
  `plugin_protocolo_escolas_id` INT NOT NULL,
  `canal` ENUM('email','whatsapp','sistema') NOT NULL DEFAULT 'sistema',
  `destinatario` VARCHAR(150) NOT NULL,
  `mensagem` TEXT NOT NULL,
  `status` ENUM('pendente','enviado','falha') NOT NULL DEFAULT 'pendente',
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `glpi_plugin_protocolo_entity_emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `entities_id` INT NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `date_mod` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_entity_email` (`entities_id`, `email`),
  KEY `entities_id` (`entities_id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

INSERT INTO `glpi_plugin_protocolo_tipos` (name, comment) VALUES
('Ofício', 'Ofícios diversos'),
('Memorando', 'Memorandos e comunicados'),
('Processo', 'Processos administrativos'),
('Prestação de Contas', 'Documentos de prestação de contas'),
('Nota Fiscal / Recibo', 'Notas fiscais, recibos, comprovantes'),
('Fotos / Mídia', 'Fotos, prints, mídias'),
('Planilha', 'Planilhas e relatórios'),
('Ata', 'Atas de reunião'),
('Outros', 'Outros tipos não listados')
ON DUPLICATE KEY UPDATE name=VALUES(name);
