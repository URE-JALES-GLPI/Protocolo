<?php
include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);
Html::header(__('Configuração - Protocolo', 'protocolo'), $_SERVER['PHP_SELF'], 'config', 'plugins');

echo "<div class='container-fluid'>";
echo "<h3><i class='ti ti-settings'></i> " . __('Configuração do Plugin Protocolo', 'protocolo') . "</h3>";
echo "<div class='card shadow-sm'><div class='card-body'>";

echo "<p>" . __('Este plugin integra o Sistema de Protocolo de Pastas (URE) ao GLPI.', 'protocolo') . "</p>";
echo "<ul>";
echo "<li><a href='" . \GlpiPlugin\Protocolo\Pasta::getSearchURL() . "'>" . __('Pastas', 'protocolo') . "</a> - " . __('Registro de entrada/retirada', 'protocolo') . "</li>";
echo "<li><a href='" . \GlpiPlugin\Protocolo\Escola::getSearchURL() . "'>" . __('Escolas', 'protocolo') . "</a></li>";
echo "<li><a href='" . \GlpiPlugin\Protocolo\TipoArquivo::getSearchURL() . "'>" . __('Tipos de Arquivo', 'protocolo') . "</a></li>";
echo "<li><a href='" . Plugin::getWebDir('protocolo') . "/front/dashboard.php'>" . __('Dashboard', 'protocolo') . "</a></li>";
echo "</ul>";

echo "<div class='alert alert-info'>";
echo "<strong>" . __('Fluxo:', 'protocolo') . "</strong> " . __('Registrar Entrada → Termo Recebimento → Upload assinado → Retirada → Termo Retirada → Upload.', 'protocolo');
echo "</div>";

echo "<h5>" . __('Direitos de Perfil', 'protocolo') . "</h5>";
echo "<p class='text-muted'>" . __('Configure os direitos em Administração → Perfis → selecione o perfil → aba Protocolo.', 'protocolo') . "</p>";
echo "<a href='" . $CFG_GLPI['root_doc'] . "/front/profile.php' class='btn btn-outline-primary'><i class='ti ti-shield-lock'></i> " . __('Gerenciar Perfis', 'protocolo') . "</a>";

echo "</div></div></div>";

Html::footer();
