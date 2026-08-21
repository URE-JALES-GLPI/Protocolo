-- Migration: Tipos de Arquivos para pastas
-- Executar: mysql -u protocolo_user -pProtocolo@2026 protocolo < sql/migration_tipos_arquivo.sql
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tipos_arquivo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  descricao VARCHAR(255) DEFAULT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pasta_tipos (
  pasta_id INT NOT NULL,
  tipo_id INT NOT NULL,
  PRIMARY KEY (pasta_id, tipo_id),
  FOREIGN KEY (pasta_id) REFERENCES pastas(id) ON DELETE CASCADE,
  FOREIGN KEY (tipo_id) REFERENCES tipos_arquivo(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tipos iniciais (ajuste livre em Tipos de Arquivos no menu)
INSERT INTO tipos_arquivo (nome, descricao) VALUES
('Ofício', 'Ofícios diversos'),
('Memorando', 'Memorandos e comunicados'),
('Processo', 'Processos administrativos'),
('Prestação de Contas', 'Documentos de prestação de contas'),
('Nota Fiscal / Recibo', 'Notas fiscais, recibos, comprovantes'),
('Fotos / Mídia', 'Fotos, prints, mídias'),
('Planilha', 'Planilhas e relatórios'),
('Ata', 'Atas de reunião'),
('Outros', 'Outros tipos não listados')
ON DUPLICATE KEY UPDATE nome=VALUES(nome);
