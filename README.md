# Sistema de Protocolo de Pastas - URE

Sistema web em **PHP + MySQL** para controle de pastas que ficam no setor até retirada pelas escolas.

**Fluxo:**
1. Alguém deixa a pasta → **Registrar Entrada** (escola + itens) → imprime **Termo de Recebimento** → coleta assinatura → digitaliza e faz **upload do assinado** (substitui o sem assinatura).
2. Escola vem buscar → **Registrar Retirada** → imprime **Termo de Entrega/Retirada** → assina → digitaliza e faz upload.
3. Notificação automática (e-mail/WhatsApp) fica para próxima etapa — já existe placeholder (`notificacoes`).

> Stack: PHP + MySQL · Apache na VM Ubuntu 24.04.3 LTS (Proxmox) · Login com perfis `admin`/`operador`

---

## 1) Instalação na VM Ubuntu 24.04.3 LTS (Proxmox + Apache)

### A. Atualizar e instalar LAMP
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mysql-server php php-mysql php-mbstring php-xml php-curl php-gd libapache2-mod-php unzip

php -v
mysql --version
apache2 -v
```

### B. Criar banco e usuário
```bash
sudo mysql
```
```sql
CREATE DATABASE protocolo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'protocolo_user'@'localhost' IDENTIFIED BY 'Protocolo@2026';
GRANT ALL PRIVILEGES ON protocolo.* TO 'protocolo_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```
> Altere a senha em `config/database.php` se mudar aqui.

Importar schema:
```bash
mysql -u protocolo_user -p protocolo < /var/www/protocolo/sql/schema.sql
# senha: Protocolo@2026
```

### C. Copiar projeto para Apache
```bash
sudo mkdir -p /var/www/protocolo
sudo cp -r /caminho/onde/esta/protocolo/* /var/www/protocolo/
# ou via scp/sftp do Windows:
# scp -r C:\Projetos\protocolo usuario@IP_VM:/tmp && sudo mv /tmp/protocolo /var/www/

sudo chown -R www-data:www-data /var/www/protocolo
sudo chmod -R 755 /var/www/protocolo
sudo chmod -R 775 /var/www/protocolo/uploads
```

### D. Configurar Apache VirtualHost
```bash
sudo cp /var/www/protocolo/apache/protocolo.conf /etc/apache2/sites-available/protocolo.conf
# Edite se precisar: sudo nano /etc/apache2/sites-available/protocolo.conf

sudo a2enmod rewrite
sudo a2ensite protocolo
# sudo a2dissite 000-default  # opcional

sudo apache2ctl configtest
sudo systemctl restart apache2
sudo systemctl enable apache2
```

### E. Ajustar hosts (acesso via nome)
No Windows da rede interna, `C:\Windows\System32\drivers\etc\hosts`:
```
192.168.1.50  protocolo.local
```
Acesse: `http://protocolo.local` ou `http://IP_DA_VM`

### F. Primeiro acesso
- Usuário: `admin`
- Senha: `admin123`
- Vá em **Usuários** → crie operador do setor e troque senha do admin.

### G. Personalizar termo
Edite `gerar_termo.php` (~linha 60) para brasão/nome da secretaria e `includes/header.php` para logo.

---

## 2) Estrutura
```
config/database.php   -> credenciais PDO
includes/auth.php     -> sessão/login/CSRF
includes/header.php   -> navbar
sql/schema.sql        -> DDL + admin padrão
uploads/termos/       -> PDFs/JPGs assinados (775 www-data)
apache/protocolo.conf -> VirtualHost
login.php, dashboard.php, escolas.php, usuarios.php, pastas.php, pasta_nova.php, pasta_view.php, gerar_termo.php
```

## 3) Permissões importantes
```bash
sudo chown www-data:www-data uploads/termos
sudo chmod 775 uploads/termos
sudo tail -f /var/log/apache2/protocolo_error.log
```

## 4) Backup
```bash
mysqldump -u protocolo_user -p protocolo > backup_$(date +%F).sql
tar -czf uploads_$(date +%F).tar.gz /var/www/protocolo/uploads
```

## 5) Notificação (próxima etapa)
Tabela `notificacoes` já criada. Ideias:
- E-mail via PHPMailer (SMTP da prefeitura)
- WhatsApp via API (Twilio / Z-API / Evolution)
- Ao registrar pasta, disparar `INSERT INTO notificacoes` + cron que envia.

## 6) Segurança
- Troque `DB_PASS` e senha admin.
- Ative HTTPS interno se possível.
- `php_flag engine off` em uploads previne execução de arquivos maliciosos.

## 7) Dúvidas
Logs: `sudo tail -f /var/log/apache2/protocolo_error.log`
Teste DB: `php -r "require 'config/database.php'; var_dump(getPDO()->query('SELECT 1')->fetch());"`
