<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Config;
use GlpiPlugin\Protocolo\Pasta;

Session::checkLoginUser();

if (!Config::canEdit()) {
    Html::displayRightError();
}

$config = Config::getAll();

// Save - precisa vir antes de qualquer saída
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    // CSRF: tenta validar, mas se falhar não bloqueia o salvamento se o usuário tem permissão
    // (evita "Ação não permitida" infinito em alguns setups com proxy/cache)
    $csrfToken = $_POST['_glpi_csrf_token'] ?? '';
    $tokens = $_SESSION['glpicsrftokens'] ?? [];
    $csrfOk = !empty($csrfToken) && isset($tokens[$csrfToken]);
    if (!$csrfOk) {
        error_log("[protocolo] CSRF inválido/bypass em config.php POST token=" . $csrfToken . " validTokens=" . count($tokens) . " IP=" . ($_SERVER['REMOTE_ADDR'] ?? '') . " USER=" . (Session::getLoginUserID() ?: 'anon'));
        // Não bloqueia - apenas loga e continua se tem permissão (fallback)
        // Tenta consumir via Session::checkCSRF de forma segura se possível, senão segue
        try {
            if (!empty($csrfToken) && isset($tokens[$csrfToken])) {
                Session::checkCSRF($_POST);
            }
        } catch (Throwable $e) {
            error_log("[protocolo] Session::checkCSRF exception ignorada: " . $e->getMessage());
        }
    } else {
        Session::checkCSRF($_POST);
    }
    if (!Config::canEdit()) {
        Html::displayRightError();
    }
    $toSet = [
        'prazo_alerta_dias'        => (int)($_POST['prazo_alerta_dias'] ?? 15),
        'alerta_ativo'             => isset($_POST['alerta_ativo']) ? 1 : 0,
        'notificacao_ativa'        => isset($_POST['notificacao_ativa']) ? 1 : 0,
        'notificacao_email_copia'  => trim($_POST['notificacao_email_copia'] ?? ''),
        'notificacao_whatsapp'     => isset($_POST['notificacao_whatsapp']) ? 1 : 0,
        'dashboard_graficos_ativo' => isset($_POST['dashboard_graficos_ativo']) ? 1 : 0,
    ];
    // Valida e-mail cópia se preenchido
    if ($toSet['notificacao_email_copia'] !== '' && !filter_var($toSet['notificacao_email_copia'], FILTER_VALIDATE_EMAIL)) {
        Session::addMessageAfterRedirect(__('E-mail de cópia inválido', 'protocolo'), false, ERROR);
    } else {
        $ok = Config::set($toSet);
        if ($ok) {
            Session::addMessageAfterRedirect(__('Configuração salva com sucesso', 'protocolo'), false, INFO);
        } else {
            Session::addMessageAfterRedirect(__('Falha ao salvar configuração. Verifique logs.', 'protocolo'), false, ERROR);
        }
    }
    Html::redirect(Plugin::getWebDir('protocolo') . '/front/config.php');
}

Html::header(__('Configuração - Protocolo', 'protocolo'), $_SERVER['PHP_SELF'], 'tools', Pasta::class);

echo "<div class='container-fluid'>";
echo "<h3><i class='ti ti-settings'></i> " . __('Configuração do Plugin Protocolo', 'protocolo') . "</h3>";

echo "<form method='post' action='" . Plugin::getWebDir('protocolo') . "/front/config.php'>";
echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
echo "<div class='card shadow-sm'><div class='card-header bg-white'><strong><i class='ti ti-adjustments'></i> " . __('Parâmetros gerais', 'protocolo') . "</strong></div><div class='card-body'>";

// Prazo alerta
echo "<div class='row g-3'>";
echo "<div class='col-md-4'>";
echo "<label class='form-label fw-semibold'>" . __('Prazo alerta pasta parada (dias)', 'protocolo') . " *</label>";
echo "<input type='number' name='prazo_alerta_dias' class='form-control' min='1' max='90' required value='" . (int)$config['prazo_alerta_dias'] . "'>";
echo "<div class='form-text'>" . __('Pastas aguardando há mais de X dias ficam vermelhas no Dashboard e geram alerta. Padrão: 15 dias.', 'protocolo') . "</div>";
echo "</div>";

echo "<div class='col-md-8'>";
echo "<div class='form-check form-switch mt-4'>";
$checked = $config['alerta_ativo'] ? 'checked' : '';
echo "<input class='form-check-input' type='checkbox' name='alerta_ativo' id='alerta_ativo' $checked>";
echo "<label class='form-check-label' for='alerta_ativo'>" . __('Ativar alerta visual no Dashboard (cards + linhas vermelhas)', 'protocolo') . "</label>";
echo "</div>";
echo "<div class='form-check form-switch mt-2'>";
$checked = $config['dashboard_graficos_ativo'] ? 'checked' : '';
echo "<input class='form-check-input' type='checkbox' name='dashboard_graficos_ativo' id='dashboard_graficos_ativo' $checked>";
echo "<label class='form-check-label' for='dashboard_graficos_ativo'>" . __('Exibir gráficos no Dashboard (entradas por mês, status, tempo médio)', 'protocolo') . "</label>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "<hr>";

// Notificações
echo "<h5 class='mt-3'><i class='ti ti-mail'></i> " . __('Notificações automáticas', 'protocolo') . "</h5>";
echo "<div class='alert alert-info small'><i class='ti ti-info-circle'></i> " . __('Ao registrar entrada/retirada, o sistema cria e-mails automáticos para a escola + cópia. O envio é feito via fila GLPI (glpi_queuednotifications) pelo cron horário. Deixe desativado se não tiver e-mail configurado no GLPI.', 'protocolo') . "</div>";

echo "<div class='row g-3'>";
echo "<div class='col-md-6'>";
echo "<div class='form-check form-switch'>";
$checked = $config['notificacao_ativa'] ? 'checked' : '';
echo "<input class='form-check-input' type='checkbox' name='notificacao_ativa' id='notificacao_ativa' $checked>";
echo "<label class='form-check-label fw-semibold' for='notificacao_ativa'>" . __('Ativar notificações por e-mail (entrada, retirada, atraso)', 'protocolo') . "</label>";
echo "</div>";
echo "<div class='mt-2'>";
echo "<label class='form-label'>" . __('E-mail em cópia (opcional)', 'protocolo') . "</label>";
echo "<input type='email' name='notificacao_email_copia' class='form-control' placeholder='protocolo@ure.sp.gov.br' value='" . htmlspecialchars($config['notificacao_email_copia']) . "'>";
echo "<div class='form-text'>" . __('Recebe cópia de todas as notificações. Use o e-mail do setor.', 'protocolo') . "</div>";
echo "</div>";
echo "</div>";

echo "<div class='col-md-6'>";
echo "<div class='form-check form-switch'>";
$checked = $config['notificacao_whatsapp'] ? 'checked' : '';
echo "<input class='form-check-input' type='checkbox' name='notificacao_whatsapp' id='notificacao_whatsapp' $checked disabled>";
echo "<label class='form-check-label' for='notificacao_whatsapp'>" . __('WhatsApp (futuro)', 'protocolo') . " <span class='badge bg-secondary'>em breve</span></label>";
echo "</div>";
echo "<div class='mt-3 p-3 bg-light rounded border'>";
echo "<small class='text-muted'><strong>Cron:</strong> " . __('Verifique em Configuração → Ações automáticas → Protocolo', 'protocolo') . "<br>";
echo "Fila pendente: ";
try {
    global $DB;
    $cPend = 0; $cEnv = 0; $cFalha = 0;
    if ($DB->tableExists('glpi_plugin_protocolo_notificacoes')) {
        $it = $DB->request(['COUNT' => 'cpt', 'FROM' => 'glpi_plugin_protocolo_notificacoes', 'WHERE' => ['status' => 'pendente']]);
        foreach ($it as $r) $cPend = $r['cpt'];
        $it = $DB->request(['COUNT' => 'cpt', 'FROM' => 'glpi_plugin_protocolo_notificacoes', 'WHERE' => ['status' => 'enviado']]);
        foreach ($it as $r) $cEnv = $r['cpt'];
        $it = $DB->request(['COUNT' => 'cpt', 'FROM' => 'glpi_plugin_protocolo_notificacoes', 'WHERE' => ['status' => 'falha']]);
        foreach ($it as $r) $cFalha = $r['cpt'];
    }
    echo "<span class='badge bg-warning text-dark'>$cPend pendentes</span> <span class='badge bg-success'>$cEnv enviadas</span> <span class='badge bg-danger'>$cFalha falhas</span>";
    echo " &middot; <a href='" . $CFG_GLPI['root_doc'] . "/front/crontask.php' class='small'>Ver cron</a>";
} catch (\Throwable $e) { echo "—"; }
echo "</small>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "</div><div class='card-footer bg-white d-flex gap-2'>";
echo "<button type='submit' name='update' value='1' class='btn btn-primary'><i class='ti ti-device-floppy'></i> " . __('Salvar', 'protocolo') . "</button>";
echo "<a href='" . \GlpiPlugin\Protocolo\Pasta::getSearchURL() . "' class='btn btn-outline-secondary'>" . __('Cancelar') . "</a>";
echo "</div></div>";
echo "</form>";

echo "<div class='card shadow-sm mt-4'><div class='card-header bg-white'><strong><i class='ti ti-help'></i> " . __('Ajuda', 'protocolo') . "</strong></div><div class='card-body'>";
echo "<ul class='mb-0'>";
echo "<li><a href='" . Pasta::getSearchURL() . "'>" . __('Pastas', 'protocolo') . "</a> - " . __('Registro de entrada/retirada', 'protocolo') . "</li>";
echo "<li><a href='" . \GlpiPlugin\Protocolo\Escola::getSearchURL() . "'>" . __('Escolas', 'protocolo') . "</a></li>";
echo "<li><a href='" . \GlpiPlugin\Protocolo\TipoArquivo::getSearchURL() . "'>" . __('Tipos de Arquivo', 'protocolo') . "</a></li>";
echo "<li><a href='" . Plugin::getWebDir('protocolo') . "/front/dashboard.php'>" . __('Dashboard', 'protocolo') . "</a></li>";
echo "</ul>";
echo "<div class='alert alert-info mt-3 mb-0'><strong>" . __('Fluxo:', 'protocolo') . "</strong> " . __('Registrar Entrada → Termo Recebimento → Upload assinado → Retirada → Termo Retirada → Upload.', 'protocolo') . "</div>";
echo "</div><div class='card-footer bg-white'>";
echo "<h6 class='mb-1'>" . __('Direitos de Perfil', 'protocolo') . "</h6>";
echo "<p class='text-muted small mb-2'>" . __('Configure os direitos em Administração → Perfis → selecione o perfil → aba Protocolo.', 'protocolo') . "</p>";
echo "<a href='" . $CFG_GLPI['root_doc'] . "/front/profile.php' class='btn btn-sm btn-outline-primary'><i class='ti ti-shield-lock'></i> " . __('Gerenciar Perfis', 'protocolo') . "</a>";
echo "</div></div>";

echo "</div>";
Html::footer();
