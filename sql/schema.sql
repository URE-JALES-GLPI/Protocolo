-- Sistema de Protocolo de Pastas - Schema MySQL
-- Criar database: CREATE DATABASE protocolo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- Usuários do setor (login)
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(120) DEFAULT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  perfil ENUM('admin','operador') NOT NULL DEFAULT 'operador',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Escolas destinatárias
CREATE TABLE IF NOT EXISTS escolas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(180) NOT NULL,
  codigo VARCHAR(40) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  telefone VARCHAR(30) DEFAULT NULL,
  endereco TEXT DEFAULT NULL,
  responsavel VARCHAR(120) DEFAULT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pastas protocoladas
CREATE TABLE IF NOT EXISTS pastas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  escola_id INT NOT NULL,
  status ENUM('aguardando','retirada','cancelada') NOT NULL DEFAULT 'aguardando',
  -- Recebimento
  data_recebimento DATETIME NOT NULL,
  recebido_de VARCHAR(150) NOT NULL COMMENT 'Quem deixou a pasta',
  recebido_documento VARCHAR(30) DEFAULT NULL COMMENT 'CPF/RG de quem deixou',
  observacao TEXT DEFAULT NULL,
  -- Retirada
  data_retirada DATETIME DEFAULT NULL,
  retirado_por VARCHAR(150) DEFAULT NULL,
  retirado_documento VARCHAR(30) DEFAULT NULL,
  observacao_retirada TEXT DEFAULT NULL,
  -- Sistema
  criado_por INT DEFAULT NULL,
  retirado_por_usuario INT DEFAULT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (escola_id) REFERENCES escolas(id),
  FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  FOREIGN KEY (retirado_por_usuario) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Itens dentro da pasta
CREATE TABLE IF NOT EXISTS pasta_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pasta_id INT NOT NULL,
  descricao VARCHAR(255) NOT NULL,
  quantidade INT NOT NULL DEFAULT 1,
  observacao VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (pasta_id) REFERENCES pastas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Termos gerados (controle de arquivos)
CREATE TABLE IF NOT EXISTS termos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pasta_id INT NOT NULL,
  tipo ENUM('recebimento','retirada') NOT NULL,
  codigo VARCHAR(50) NOT NULL UNIQUE,
  arquivo_assinado VARCHAR(255) DEFAULT NULL COMMENT 'Caminho do PDF/imagem assinado digitalizado',
  hash_verificacao VARCHAR(64) DEFAULT NULL,
  criado_por INT DEFAULT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pasta_id) REFERENCES pastas(id) ON DELETE CASCADE,
  FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notificações (placeholder para futuro)
CREATE TABLE IF NOT EXISTS notificacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pasta_id INT NOT NULL,
  escola_id INT NOT NULL,
  canal ENUM('email','whatsapp','sistema') NOT NULL DEFAULT 'sistema',
  destinatario VARCHAR(150) NOT NULL,
  mensagem TEXT NOT NULL,
  status ENUM('pendente','enviado','falha') NOT NULL DEFAULT 'pendente',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pasta_id) REFERENCES pastas(id) ON DELETE CASCADE,
  FOREIGN KEY (escola_id) REFERENCES escolas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuário admin padrão: admin / admin123
-- Senha hash gerada com password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO usuarios (nome, username, email, senha_hash, perfil) VALUES
('Administrador', 'admin', 'admin@protocolo.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE username=username;

-- Escolas exemplo
INSERT INTO escolas (nome, codigo, email, telefone) VALUES
('Escola Municipal Exemplo 01', 'ESC001', 'escola01@educacao.local', '(00) 0000-0000'),
('Escola Estadual Exemplo 02', 'ESC002', 'escola02@educacao.local', '(00) 0000-0001')
ON DUPLICATE KEY UPDATE nome=nome;
