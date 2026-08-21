#!/bin/bash
# install.sh - Script de instalação para Ubuntu 24.04.3 LTS
set -e
echo "=== Protocolo - Instalação LAMP ==="
sudo apt update
sudo apt install -y apache2 mysql-server php php-mysql php-mbstring php-xml php-curl php-gd libapache2-mod-php unzip
echo "Criando banco..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS protocolo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'protocolo_user'@'localhost' IDENTIFIED BY 'Protocolo@2026';"
sudo mysql -e "GRANT ALL PRIVILEGES ON protocolo.* TO 'protocolo_user'@'localhost'; FLUSH PRIVILEGES;"
echo "Importando schema..."
mysql -u protocolo_user -pProtocolo@2026 protocolo < sql/schema.sql
echo "Configurando Apache..."
sudo cp apache/protocolo.conf /etc/apache2/sites-available/protocolo.conf
sudo a2enmod rewrite
sudo a2ensite protocolo
sudo apache2ctl configtest
sudo systemctl restart apache2
sudo chown -R www-data:www-data uploads
sudo chmod -R 775 uploads
echo "=== Pronto! Acesse http://IP_DA_VM - admin/admin123 ==="
