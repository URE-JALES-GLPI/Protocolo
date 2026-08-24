# Protocolo de Pastas - Plugin GLPI 11.x (URE)

Este repositório foi **convertido para plugin GLPI**. O sistema standalone original ( `dashboard.php`, `pastas.php` etc ) continua presente para referência/legado, mas o **modo plugin** é o recomendado quando você tem GLPI.

> **Plugin dir:** `protocolo` (deve estar em `glpi/plugins/protocolo` ou `glpi/marketplace/protocolo` conforme instalação GLPI 11)
>
> **Namespace:** `GlpiPlugin\Protocolo` | **Tabelas:** `glpi_plugin_protocolo_*` | **Direitos:** `plugin_protocolo_*`

---

## Compatibilidade

- **GLPI:** 11.0.0 → 12.0.0 (testado em 11.x)
- **PHP:** >= 8.1 (exts: mysqli, gd, curl)
- **Banco:** MariaDB/MySQL (usa `$DB` do GLPI)

O plugin **usa usuários/perfís do GLPI** (não tem mais login próprio). Em `setup.php:31` define `PLUGIN_PROTOCOLO_*`.

---

## O que foi portado

| Standalone (antes) | Plugin GLPI (agora) | Observação |
|---|---|---|
| `config/database.php` (PDO) | `$DB` do GLPI (`src/Install.php:11`) | Sem credenciais separadas |
| `includes/auth.php` (`$_SESSION['usuario_id']`) | `Session::getLoginUserID()` / `Session::haveRight()` | Perfis GLPI |
| `sql/schema.sql` (tabelas `pastas`, `escolas`...) | `glpi_plugin_protocolo_*` (`src/Install.php:18`, `install/mysql/plugin_protocolo_empty.sql:1`) | Prefixo GLPI |
| `usuarios.php` / `perfis.php` / `usuario_permissoes` | `glpi_users` + `glpi_profiles` + `glpi_profilerights` (`src/Profile.php:23`) | Direitos `plugin_protocolo_pasta` etc |
| `escolas.php` | `src/Escola.php:14` + `front/escola.php:1` + `front/escola.form.php:1` | `CommonDBTM` |
| `tipos_arquivo.php` | `src/TipoArquivo.php:11` + `front/tipo.php:1` |  |
| `pastas.php` / `pasta_nova.php` / `pasta_view.php` | `src/Pasta.php:11` + `front/pasta.php:1` + `front/pasta.form.php:1` | Lista via `Search::show()` |
| `gerar_termo.php` | `front/termo.php:1` | Reusa layout termo, logo em `assets/img/logo.png` |
| `dashboard.php` | `front/dashboard.php:1` | Mesmos cards: aguardando, retiradas, mês, pendências |
| `uploads/termos/` | `GLPI_PLUGIN_DOC_DIR . '/protocolo/termos'` (`src/Pasta.php:651`, `src/Install.php:183`) | Servido via `front/download.php:1` |
| `assets/js/app.js` | `js/app.js:1` | TomSelect + lógica tipos ↔ itens preservada |
| `notificacoes` (placeholder) | `glpi_plugin_protocolo_notificacoes` (`src/Install.php:130`) | Pronto para integrar com notificações GLPI (`Notification`) |

Código sequencial `PROT-YYYY-0001` e `TR-/TE-` mantidos em `src/Install.php:183` (`gerarCodigoPasta()` `gerarCodigoTermo()`).

---

## Estrutura do plugin

```
setup.php                    # plugin_version_protocolo() + plugin_init_protocolo() (menu, hooks, CSS/JS) :31
hook.php                     # plugin_protocolo_install() / uninstall() :11
src/
  Install.php                # cria tabelas glpi_plugin_protocolo_* e direitos :14
  Pasta.php                  # CommonDBTM principal (código, status, retirada, upload) :11
  Escola.php                 # CommonDBTM escola :14
  TipoArquivo.php            # CommonDBTM tipo :11
  Termo.php                  # helper termos :11
  Profile.php                # direitos e aba Perfil :14
front/
  dashboard.php              # dashboard com stats :1
  pasta.php / pasta.form.php # lista (Search) e form (entrada, retirada, upload)
  escola.php / escola.form.php
  tipo.php / tipo.form.php
  termo.php                  # impressão do termo (A4) :1
  download.php               # serve arquivo assinado de GLPI_PLUGIN_DOC_DIR :1
  config.php                 # página Configuração (link em Administração > Plugins)
install/mysql/plugin_protocolo_empty.sql  # schema SQL puro :1
js/app.js / css/style.css    # reaproveitados de assets/
tools/migrate_standalone.php # migra BD antigo -> plugin :1
composer.json / protocolo.xml
```

---

## Instalação no GLPI 11

### 1. Copiar para o GLPI

```bash
# No servidor GLPI (Ubuntu 24.04)
cd /var/www/glpi/plugins
# ou /var/www/glpi/marketplace se usa marketplace
sudo cp -r /caminho/para/protocolo ./protocolo
# ou se este repo já está no servidor, apenas garanta o nome:
sudo mv /caminho/para/protocolo /var/www/glpi/plugins/protocolo

sudo chown -R www-data:www-data protocolo
sudo chmod -R 755 protocolo
# pasta de arquivos assinados
sudo mkdir -p /var/www/glpi/files/_plugins/protocolo/termos
sudo chown -R www-data:www-data /var/www/glpi/files/_plugins
```

> **Importante:** o diretório **deve** se chamar `protocolo` (alfanumérico, sem hífen). É o que `setup.php:9` espera (`plugin_init_protocolo`).

### 2. Instalar via interface

1. Acesse **GLPI → Configurar → Plugins** (ou Administração → Plugins no GLPI 11).
2. Encontre **Protocolo de Pastas - URE** ( `setup.php:20` ) e clique **Instalar**.
   - `hook.php:11` → `Install::install()` cria as 7 tabelas e semeia `glpi_plugin_protocolo_tipos`.
   - Cria direitos `plugin_protocolo_*` em `glpi_profilerights` e diretório `GLPI_PLUGIN_DOC_DIR/protocolo/termos`.
3. Clique **Ativar**.
4. Vá em **Administração → Perfis** → escolha um perfil (ex: Super-Admin, Technician) → aba **Protocolo** (`src/Profile.php:47`) e ajuste direitos:
   - `plugin_protocolo_pasta` (PASTA) - 255 = tudo, 1 = só ver
   - `plugin_protocolo_escola` / `tipo` / `config`
   - Por padrão Super-Admin ganha 255, Technician ganha leitura em pasta/escola (`src/Install.php:205`).

### 3. Usar

- **Ferramentas → Pastas** ou **Plugins → Protocolo** ( `setup.php:77` `menu_toadd` )
  - **Dashboard:** `plugins/protocolo/front/dashboard.php`
  - **Pastas:** `plugins/protocolo/front/pasta.php` (Search GLPI) → **Adicionar** (`pasta.form.php`) registra entrada (gera `PROT-YYYY-0001` e termo `TR-...`).
  - **Pasta → Ver:** mostra itens, tipos, termos; botão **Ver/Imprimir** (`front/termo.php?id=ID&tipo=recebimento`) imprime A4; **Upload** envia assinado (salva em `GLPI_PLUGIN_DOC_DIR/protocolo/termos/TR-...-ASSINADO-....pdf` e vincula).
  - **Escolas:** `front/escola.php` | **Tipos:** `front/tipo.php`
  - **Configuração:** `front/config.php` (link em `setup.php:94` `config_page`)

Personalize logo: substitua `assets/img/logo.png` (usado em `front/termo.php:56`).

---

## Migração do BD standalone antigo

Se você já tem dados no BD `protocolo` (standalone):

```bash
# 1. No GLPI, instale o plugin (tabelas vazias criadas)

# 2. Importe o dump antigo com prefixo old_ no mesmo BD do GLPI:
mysqldump -u protocolo_user -pProtocolo@2026 protocolo > /tmp/protocolo.sql
# prefixa tabelas (exemplo com sed):
sed -e 's/`escolas`/`old_escolas`/g' \
    -e 's/`pastas`/`old_pastas`/g' \
    -e 's/`pasta_itens`/`old_pasta_itens`/g' \
    -e 's/`pasta_tipos`/`old_pasta_tipos`/g' \
    -e 's/`termos`/`old_termos`/g' \
    -e 's/`tipos_arquivo`/`old_tipos_arquivo`/g' \
    -e 's/`usuarios`/`old_usuarios`/g' /tmp/protocolo.sql | mysql -u glpi -p glpi

# 3. Copie arquivos assinados antigos:
sudo cp -r /var/www/protocolo/uploads/termos/* /var/www/glpi/files/_plugins/protocolo/termos/
sudo chown -R www-data:www-data /var/www/glpi/files/_plugins/protocolo

# 4. Rode a migração:
cd /var/www/glpi/plugins/protocolo
sudo -u www-data php tools/migrate_standalone.php
# log: "Migrando escolas... + Pasta PROT-... (old -> new)"
# O script mapeia old_id -> new_id, mantém códigos, mapeia usuários por username (fallback 2=glpi)

# 5. Verifique em Pastas e Dashboard, depois (opcional) DROP old_*:
# mysql -u glpi -p -e "DROP TABLE old_escolas, old_pastas, old_pasta_itens, old_pasta_tipos, old_termos, old_tipos_arquivo, old_usuarios;" glpi
```

O script está em `tools/migrate_standalone.php:1` e suporta também DSN externo (`$OLD_DSN`).

---

## Permissões (GLPI)

Definidas em `src/Profile.php:23` (`getRights()`) e instaladas em `src/Install.php:183` (`initRights()`):

- `plugin_protocolo_pasta` – ver/criar/editar/excluir pastas (inclui termos e upload)
- `plugin_protocolo_escola` – gerenciar escolas
- `plugin_protocolo_tipo` – gerenciar tipos de arquivo
- `plugin_protocolo_config` – acesso à config

Vá em **Administração → Perfis → (perfil) → Aba Protocolo** para editar. O hook `change_profile` (`setup.php:57`, `src/Profile.php:36`) garante que `Session::haveRight()` reflita o perfil ativo.

Compatível com **Entidades** (`entities_id` em `glpi_plugin_protocolo_pastas`/`escolas`) e `is_recursive`/`is_deleted` para lixeira GLPI.

---

## Desenvolvimento / Standalone ainda funciona?

Sim, os arquivos `dashboard.php`, `pastas.php`, `pasta_view.php` etc continuam funcionais **fora** do GLPI (acesso direto via Apache ` /var/www/protocolo` ). O plugin detecta GLPI via `Plugin::isActivated('protocolo')` (`setup.php:43`) – se não estiver no GLPI, o `setup.php` não interfere no standalone.

Para desenvolver o plugin standalone, edite `src/` e `front/`; para o standalone legado, edite os arquivos da raiz.

---

## Créditos

- Standalone original: Leonardo Poiatti Fação (PHP + MySQL, Apache 24.04 Proxmox)
- Conversão para plugin GLPI 11.x: mantida lógica de `config/database.php:26` (`gerarCodigoPasta`/`gerarCodigoTermo`) e `gerar_termo.php:28` (layout termo A4, hash verificação) agora em `src/Install.php` e `front/termo.php`.

Licença **GPLv2+** (`setup.php:24`, `composer.json:5`).
