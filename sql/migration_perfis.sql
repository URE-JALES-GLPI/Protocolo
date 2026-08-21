-- Migration: Perfis com permissões
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS perfis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(60) NOT NULL UNIQUE,
  descricao VARCHAR(255) DEFAULT NULL,
  pode_gerenciar_tipos TINYINT(1) NOT NULL DEFAULT 0,
  pode_gerenciar_escolas TINYINT(1) NOT NULL DEFAULT 0,
  pode_gerenciar_usuarios TINYINT(1) NOT NULL DEFAULT 0,
  pode_acessar_config TINYINT(1) NOT NULL DEFAULT 0,
  pode_gerenciar_pastas TINYINT(1) NOT NULL DEFAULT 1,
  pode_ver_dashboard TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Perfis padrão
INSERT INTO perfis (nome, descricao, pode_gerenciar_tipos, pode_gerenciar_escolas, pode_gerenciar_usuarios, pode_acessar_config, pode_gerenciar_pastas, pode_ver_dashboard) VALUES
('Administrador', 'Acesso total ao sistema', 1,1,1,1,1,1),
('Operador', 'Pode gerenciar pastas e escolas, sem acesso a configurações', 0,1,0,0,1,1),
('Visualizador', 'Apenas visualização de pastas e dashboard', 0,0,0,0,0,1)
ON DUPLICATE KEY UPDATE nome=VALUES(nome);

-- Adiciona coluna perfil_id em usuarios
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='perfil_id');
SET @sql = IF(@col_exists=0, 'ALTER TABLE usuarios ADD COLUMN perfil_id INT DEFAULT NULL, ADD FOREIGN KEY (perfil_id) REFERENCES perfis(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Vincula usuários existentes ao perfil correspondente (pelo perfil antigo)
UPDATE usuarios u JOIN perfis p ON p.nome = CASE WHEN u.perfil='admin' THEN 'Administrador' ELSE 'Operador' END SET u.perfil_id = p.id WHERE u.perfil_id IS NULL;

-- Garante que admin sempre seja Administrador
UPDATE usuarios SET perfil_id = (SELECT id FROM perfis WHERE nome='Administrador' LIMIT 1) WHERE username='admin';
