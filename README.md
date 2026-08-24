# Sistema de Protocolo de Pastas - URE (Plugin GLPI 11.x)

> **NOVO:** Este projeto foi **convertido para plugin GLPI 11.x** em `setup.php:20` / `hook.php:11`. Veja [`README_GLPI.md`](README_GLPI.md) para instalação no GLPI (`glpi/plugins/protocolo`). O sistema standalone original continua disponível abaixo para referência.

Sistema web em **PHP + MySQL** para controle de pastas que ficam no setor até retirada pelas escolas. Agora também como **plugin GLPI** (`glpi_plugin_protocolo_*` em `src/Install.php:18`).

## 📋 Sobre

Sistema para **protocolar, rastrear e comprovar** a movimentação de pastas entre a URE e as escolas, com geração de termos e arquivamento do comprovante assinado.

**Fluxo resumido:** Registrar Entrada (escola + itens) → imprimir **Termo de Recebimento** → coletar assinatura → fazer **upload do assinado** → Escola retira → Registrar Retirada → imprimir **Termo de Entrega/Retirada** → upload do assinado. Notificação automática (e-mail/WhatsApp) prevista via tabela `notificacoes`.

> Stack: PHP + MySQL · Apache (Ubuntu 24.04 LTS - Proxmox) · Login com perfis `admin`/`operador`

---

## ✨ Funcionalidades

* Registro de entrada e retirada de pastas com código sequencial (`PROT-2026-0001`).
* Geração de Termos em PDF (recebimento/entrega) com código de verificação.
* Upload do termo assinado (substitui versão sem assinatura).
* Cadastro de escolas e usuários com perfis e permissões.
* Dashboard com pendências de upload e alertas.
* Controle de status: aguardando / retirada / cancelada.
* Log e histórico completo por pasta.

---

## 🎯 Objetivos

* Centralizar o protocolo de pastas do setor.
* Padronizar comprovantes e rastreabilidade.
* Reduzir extravios e facilitar auditorias.

---

## 🚀 Benefícios

* Histórico organizado por escola/período.
* Comprovantes digitalizados e verificáveis.
* Fluxo simples para operadores e administradores.

---

## 🖥️ Compatibilidade

* PHP 8.x + MySQL 8.x
* Apache 2.4 (Ubuntu 24.04 LTS)
* Navegadores modernos

---

## ⚙️ Instalação (resumida)

1. **Requisitos:** Apache + PHP (`php-mysql php-mbstring php-xml php-curl php-gd`) + MySQL.
2. **Banco:** crie o BD `protocolo`, importe `sql/schema.sql` e ajuste credenciais em `config/database.php` (`SUA_SENHA_FORTE_AQUI`).
3. **Deploy:** copie para `/var/www/protocolo`, ajuste permissões (`chown www-data`, `chmod 775 uploads/termos`) e ative o VirtualHost (`apache/protocolo.conf` + `a2enmod rewrite` + `a2ensite protocolo`).
4. **Acesso:** `http://SEU_IP` ou `http://protocolo.local` (configure `hosts` se usar nome). Ou use o script automático: `bash install.sh`.

**Primeiro acesso:** usuário `admin` (senha definida em `sql/schema.sql` - troque imediatamente em **Usuários**). Personalize brasão/nome em `gerar_termo.php` e `includes/header.php`.

> Detalhes completos de LAMP/VirtualHost/hosts estão em `install.sh` e `apache/protocolo.conf`.

---

## 📁 Estrutura

```
# Standalone (legado)
config/database.php   -> credenciais PDO (src/Install.php:183 no plugin usa $DB)
includes/auth.php     -> sessão/login/CSRF (plugin usa Session::haveRight)
sql/schema.sql        -> DDL + admin padrão (plugin usa install/mysql/plugin_protocolo_empty.sql:1)
uploads/termos/       -> PDFs/JPGs assinados (plugin usa GLPI_PLUGIN_DOC_DIR/protocolo/termos em src/Pasta.php:651)
apache/protocolo.conf -> VirtualHost

# Plugin GLPI 11.x (NOVO) - ver README_GLPI.md
setup.php             -> plugin_version/init (menu, direitos, CSS/JS)
hook.php              -> install/uninstall
src/Install.php       -> cria glpi_plugin_protocolo_* + direitos
src/Pasta.php         -> CommonDBTM pastas (PROT-YYYY-...), retirada, upload
src/Escola.php        -> CommonDBTM escolas
src/TipoArquivo.php   -> CommonDBTM tipos
src/Termo.php         -> helper termos
src/Profile.php       -> aba Perfil + direitos plugin_protocolo_*
front/dashboard.php   -> dashboard GLPI
front/pasta.php|pasta.form.php -> Search + form
front/termo.php       -> termo A4 imprimível
tools/migrate_standalone.php -> migra BD antigo para plugin
```

---

## 🤝 Contribuições

Contribuições são bem-vindas. Abra uma **Issue** ou envie um **Pull Request**.

---

## 📄 Licença

Este projeto é distribuído sob a licença **GPL v2+**.

---

## 👨‍💻 Autor

Desenvolvido por **Leonardo Poiatti Fação**.
