# Sistema de Protocolo de Pastas - URE

Sistema web em **PHP + MySQL** para controle de pastas que ficam no setor até retirada pelas escolas.

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
config/database.php   -> credenciais PDO
includes/auth.php     -> sessão/login/CSRF
sql/schema.sql        -> DDL + admin padrão
uploads/termos/       -> PDFs/JPGs assinados (775 www-data)
apache/protocolo.conf -> VirtualHost
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
