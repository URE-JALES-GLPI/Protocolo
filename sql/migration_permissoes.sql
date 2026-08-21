-- Migration: Sistema de Permissões por usuário
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuario_permissoes (
  usuario_id INT PRIMARY KEY,
  pode_gerenciar_tipos TINYINT(1) NOT NULL DEFAULT 0,
  pode_gerenciar_escolas TINYINT(1) NOT NULL DEFAULT 0,
  pode_gerenciar_usuarios TINYINT(1) NOT NULL DEFAULT 0,
  pode_acessar_config TINYINT(1) NOT NULL DEFAULT 0,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inicializa permissões: admin tem tudo, operador só pastas/escolas limitado
INSERT INTO usuario_permissoes (usuario_id, pode_gerenciar_tipos, pode_gerenciar_escolas, pode_gerenciar_usuarios, pode_acessar_config)
SELECT id,
  CASE WHEN perfil='admin' THEN 1 ELSE 0 END,
  CASE WHEN perfil='admin' THEN 1 ELSE 1 END,
  CASE WHEN perfil='admin' THEN 1 ELSE 0 END,
  CASE WHEN perfil='admin' THEN 1 ELSE 0 END
FROM usuarios
ON DUPLICATE KEY UPDATE usuario_id=usuario_id;

-- Trigger para novos usuários herdarem do perfil
DROP TRIGGER IF EXISTS trg_usuario_permissoes_insert;
DELIMITER //
CREATE TRIGGER trg_usuario_permissoes_insert AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
  INSERT INTO usuario_permissoes (usuario_id, pode_gerenciar_tipos, pode_gerenciar_escolas, pode_gerenciar_usuarios, pode_acessar_config)
  VALUES (NEW.id,
    CASE WHEN NEW.perfil='admin' THEN 1 ELSE 0 END,
    CASE WHEN NEW.perfil='admin' THEN 1 ELSE 1 END,
    CASE WHEN NEW.perfil='admin' THEN 1 ELSE 0 END,
    CASE WHEN NEW.perfil='admin' THEN 1 ELSE 0 END
  );
END//
DELIMITER ;
