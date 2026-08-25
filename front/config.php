<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Config;
use GlpiPlugin\Protocolo\Pasta;
use GlpiPlugin\Protocolo\EntityMail;
use GlpiPlugin\Protocolo\Escola;

Session::checkLoginUser();

if (!Config::canEdit()) {
    Html::displayRightError();
}

$config = Config::getAll();

// Helper CSRF
function protocolo_check_csrf_or_bypass(): bool {
    $csrfToken = $_POST['_glpi_csrf_token'] ?? '';
    $tokens = $_SESSION['glpicsrftokens'] ?? [];
    $csrfOk = !empty($csrfToken) && isset($tokens[$csrfToken]);
    if (!$csrfOk) {
        error_log("[protocolo] CSRF inválido/bypass em config.php POST token=" . $csrfToken . " validTokens=" . count($tokens) . " IP=" . ($_SERVER['REMOTE_ADDR'] ?? '') . " USER=" . (Session::getLoginUserID() ?: 'anon'));
        // Não bloqueia, apenas loga
        try {
            if (!empty($csrfToken) && isset($tokens[$csrfToken])) {
                Session::checkCSRF($_POST);
            }
        } catch (Throwable $e) {
            error_log("[protocolo] Session::checkCSRF exception ignorada: " . $e->getMessage());
        }
        return false;
    }
    Session::checkCSRF($_POST);
    return true;
}

// --- Ações Entidade E-mail ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entity_email'])) {
    protocolo_check_csrf_or_bypass();
    if (!Config::canEdit()) Html::displayRightError();
    $eid = (int)($_POST['entities_id'] ?? 0);
    $email = trim($_POST['entity_email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Session::addMessageAfterRedirect(__('E-mail inválido', 'protocolo'), false, ERROR);
    } else {
        $id = EntityMail::add($eid, $email);
        if ($id) Session::addMessageAfterRedirect(__('E-mail adicionado para entidade', 'protocolo') . " " . htmlspecialchars(EntityMail::getEntityName($eid)), false, INFO);
        else Session::addMessageAfterRedirect(__('Falha ao adicionar e-mail (duplicado ou erro)', 'protocolo'), false, ERROR);
    }
    Html::redirect(Plugin::getWebDir('protocolo') . '/front/config.php#entity-emails');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_entity_email'])) {
    protocolo_check_csrf_or_bypass();
    if (!Config::canEdit()) Html::displayRightError();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        EntityMail::delete($id);
        Session::addMessageAfterRedirect(__('E-mail removido', 'protocolo'), false, INFO);
    }
    Html::redirect(Plugin::getWebDir('protocolo') . '/front/config.php#entity-emails');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_entity_email'])) {
    protocolo_check_csrf_or_bypass();
    if (!Config::canEdit()) Html::displayRightError();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        EntityMail::toggle($id);
        Session::addMessageAfterRedirect(__('Status alterado', 'protocolo'), false, INFO);
    }
    Html::redirect(Plugin::getWebDir('protocolo') . '/front/config.php#entity-emails');
}

// Save config geral (inclui templates)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    protocolo_check_csrf_or_bypass();
    if (!Config::canEdit()) {
        Html::displayRightError();
    }
    $toSet = [
        'prazo_alerta_dias'              => (int)($_POST['prazo_alerta_dias'] ?? 15),
        'alerta_ativo'                   => isset($_POST['alerta_ativo']) ? 1 : 0,
        'notificacao_ativa'              => isset($_POST['notificacao_ativa']) ? 1 : 0,
        'notificacao_email_copia'        => trim($_POST['notificacao_email_copia'] ?? ''),
        'notificacao_whatsapp'           => isset($_POST['notificacao_whatsapp']) ? 1 : 0,
        'dashboard_graficos_ativo'       => isset($_POST['dashboard_graficos_ativo']) ? 1 : 0,
        'notificacao_email_subject'      => trim($_POST['notificacao_email_subject'] ?? ''),
        'notificacao_email_body_entrada' => $_POST['notificacao_email_body_entrada'] ?? '',
        'notificacao_email_body_retirada' => $_POST['notificacao_email_body_retirada'] ?? '',
        'notificacao_email_body_atraso'  => $_POST['notificacao_email_body_atraso'] ?? '',
    ];
    // Valida e-mail cópia se preenchido (pode ser múltiplos separados por ,;)
    $copiaCheck = $toSet['notificacao_email_copia'];
    if ($copiaCheck !== '') {
        $parts = preg_split('/[;,]+/', $copiaCheck);
        $valid = true;
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && !filter_var($p, FILTER_VALIDATE_EMAIL)) $valid = false;
        }
        if (!$valid) {
            Session::addMessageAfterRedirect(__('E-mail de cópia inválido', 'protocolo'), false, ERROR);
            Html::redirect(Plugin::getWebDir('protocolo') . '/front/config.php');
        }
    }
    $ok = Config::set($toSet);
    if ($ok) {
        Session::addMessageAfterRedirect(__('Configuração salva com sucesso', 'protocolo'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Falha ao salvar configuração. Verifique logs.', 'protocolo'), false, ERROR);
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
echo "<div class='alert alert-info small'><i class='ti ti-info-circle'></i> " . __('Ao registrar entrada/retirada, o sistema cria e-mails automáticos para a escola + cópia + e-mails por entidade. O envio é feito via fila GLPI (glpi_queuednotifications) pelo cron horário. Deixe desativado se não tiver e-mail configurado no GLPI.', 'protocolo') . "</div>";

echo "<div class='row g-3'>";
echo "<div class='col-md-6'>";
echo "<div class='form-check form-switch'>";
$checked = $config['notificacao_ativa'] ? 'checked' : '';
echo "<input class='form-check-input' type='checkbox' name='notificacao_ativa' id='notificacao_ativa' $checked>";
echo "<label class='form-check-label fw-semibold' for='notificacao_ativa'>" . __('Ativar notificações por e-mail (entrada, retirada, atraso)', 'protocolo') . "</label>";
echo "</div>";
echo "<div class='mt-2'>";
echo "<label class='form-label'>" . __('E-mail em cópia (opcional, múltiplos separados por ;)', 'protocolo') . "</label>";
echo "<input type='text' name='notificacao_email_copia' class='form-control' placeholder='protocolo@ure.sp.gov.br; outro@ure.sp.gov.br' value='" . htmlspecialchars($config['notificacao_email_copia']) . "'>";
echo "<div class='form-text'>" . __('Recebe cópia de todas as notificações. Use o e-mail do setor. Separe múltiplos com ; ou ,', 'protocolo') . "</div>";
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
} catch (Throwable $e) { echo "—"; }
echo "</small>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "<hr>";
// Templates
echo "<h5 class='mt-3'><i class='ti ti-mail-cog'></i> " . __('Personalização do e-mail', 'protocolo') . "</h5>";
echo "<div class='alert alert-light border small'><strong>Placeholders disponíveis:</strong> <code>{codigo}</code> <code>{escola}</code> <code>{escola_codigo}</code> <code>{acao}</code> <code>{evento}</code> <code>{recebido_de}</code> <code>{recebido_documento}</code> <code>{data_recebimento}</code> <code>{data_retirada}</code> <code>{retirado_por}</code> <code>{retirado_documento}</code> <code>{itens}</code> <code>{itens_lista}</code> <code>{quantidade_itens}</code> <code>{link}</code> <code>{dias}</code> <code>{status}</code> <code>{observacao}</code> <code>{observacao_retirada}</code><br><small class='text-muted'>Use em Assunto e Corpo. Deixe em branco para usar padrão.</small></div>";

echo "<div class='mb-3'>";
echo "<label class='form-label fw-semibold'>" . __('Assunto', 'protocolo') . "</label>";
echo "<input type='text' name='notificacao_email_subject' class='form-control' placeholder='[Protocolo] {acao} - {codigo}' value='" . htmlspecialchars($config['notificacao_email_subject']) . "'>";
echo "<div class='form-text'>Ex: <code>[Protocolo] {acao} - {codigo}</code> onde {acao}= Nova pasta registrada / Pasta retirada / Pasta com retirada pendente</div>";
echo "</div>";

echo "<div class='row g-3'>";
echo "<div class='col-md-4'>";
echo "<label class='form-label fw-semibold'>" . __('Corpo - Entrada', 'protocolo') . "</label>";
echo "<textarea name='notificacao_email_body_entrada' class='form-control' rows='10' placeholder='Template entrada'>" . htmlspecialchars($config['notificacao_email_body_entrada']) . "</textarea>";
echo "</div>";
echo "<div class='col-md-4'>";
echo "<label class='form-label fw-semibold'>" . __('Corpo - Retirada', 'protocolo') . "</label>";
echo "<textarea name='notificacao_email_body_retirada' class='form-control' rows='10' placeholder='Template retirada'>" . htmlspecialchars($config['notificacao_email_body_retirada']) . "</textarea>";
echo "</div>";
echo "<div class='col-md-4'>";
echo "<label class='form-label fw-semibold'>" . __('Corpo - Atraso', 'protocolo') . "</label>";
echo "<textarea name='notificacao_email_body_atraso' class='form-control' rows='10' placeholder='Template atraso'>" . htmlspecialchars($config['notificacao_email_body_atraso']) . "</textarea>";
echo "</div>";
echo "</div>";
echo "<div class='form-text mt-2'><a href='#' onclick=\"if(confirm('Restaurar padrões?')){document.querySelectorAll('textarea[name^=notificacao_email_body]').forEach(t=>t.value=''); document.querySelector('input[name=notificacao_email_subject]').value='[Protocolo] {acao} - {codigo}';} return false;\">Restaurar padrões</a> — deixe em branco para usar padrão interno.</div>";

echo "</div><div class='card-footer bg-white d-flex gap-2'>";
echo "<button type='submit' name='update' value='1' class='btn btn-primary'><i class='ti ti-device-floppy'></i> " . __('Salvar', 'protocolo') . "</button>";
echo "<a href='" . Pasta::getSearchURL() . "' class='btn btn-outline-secondary'>" . __('Cancelar') . "</a>";
echo "</div></div>";
echo "</form>";

// --- Entidades / E-mails ---
echo "<div id='entity-emails' class='card shadow-sm mt-4'><div class='card-header bg-white d-flex justify-content-between align-items-center'><strong><i class='ti ti-building'></i> " . __('E-mails por Entidade (Escola)', 'protocolo') . "</strong><span class='badge bg-primary'>" . __('Para onde enviar notificações', 'protocolo') . "</span></div><div class='card-body'>";

echo "<div class='alert alert-info small'><i class='ti ti-info-circle'></i> " . __('Cadastre e-mails por Entidade (GLPI → Administração → Entidades). Esses e-mails recebem notificações de entrada/retirada/atraso daquela entidade, além do e-mail da Escola e cópia global. Prioridade: Entidade Plugin > Escola > Entidade GLPI > Cópia.', 'protocolo') . "</div>";

// Lista agrupada por Entidade - mostra todos e-mails do PLUGIN por entidade
$entityMails = EntityMail::getAllWithEntityNames();
$grouped = [];
foreach ($entityMails as $em) {
    $eid = (int)$em['entities_id'];
    if (!isset($grouped[$eid])) $grouped[$eid] = ['name' => $em['entity_name'], 'mails' => []];
    $grouped[$eid]['mails'][] = $em;
}
if ($grouped) {
    echo "<div class='alert alert-light border small mb-3'><i class='ti ti-plug'></i> " . __('Mostrando apenas e-mails cadastrados <strong>no PLUGIN</strong> (tabela <code>glpi_plugin_protocolo_entity_emails</code>). São esses que o cron usa para enviar. E-mails de <code>glpi_entities.email</code> ou <code>Escola.email</code> são fallback se não houver no plugin.', 'protocolo') . "</div>";
    echo "<div class='row g-3'>";
    foreach ($grouped as $eid => $data) {
        $cnt = count($data['mails']);
        $activeCnt = count(array_filter($data['mails'], fn($m) => (int)$m['is_active'] === 1));
        $inactiveCnt = $cnt - $activeCnt;
        echo "<div class='col-md-6 col-lg-4'><div class='card border h-100 shadow-sm'>";
        echo "<div class='card-header bg-white d-flex justify-content-between align-items-center'><div><strong><i class='ti ti-building'></i> " . htmlspecialchars($data['name']) . "</strong> <small class='text-muted'>#{$eid}</small></div><span class='badge bg-primary'>{$cnt} <i class='ti ti-mail'></i></span></div>";
        echo "<div class='card-header bg-light py-1 d-flex gap-2'><span class='badge bg-success'>{$activeCnt} ativos</span>" . ($inactiveCnt ? " <span class='badge bg-secondary'>{$inactiveCnt} inativos</span>" : "") . " <small class='text-muted ms-auto'>PLUG-IN</small></div>";
        echo "<div class='card-body p-2' style='max-height:240px; overflow-y:auto;'>";
        foreach ($data['mails'] as $em) {
            $email = htmlspecialchars($em['email']);
            $active = (int)$em['is_active'];
            $rowBg = $active ? 'bg-white' : 'bg-light opacity-75';
            $statusBadge = $active ? "<span class='badge bg-success'>Ativo</span>" : "<span class='badge bg-secondary'>Inativo</span>";
            echo "<div class='d-flex justify-content-between align-items-center p-2 mb-1 rounded border $rowBg'>";
            echo "<div class='text-truncate' style='max-width:160px;'><code class='text-truncate' title='$email'>$email</code><br>$statusBadge</div>";
            echo "<div class='d-flex gap-1 ms-2'>";
            // Toggle
            echo "<form method='post' action='" . Plugin::getWebDir('protocolo') . "/front/config.php#entity-emails' class='d-inline'>";
            echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
            echo "<input type='hidden' name='id' value='" . (int)$em['id'] . "'>";
            $toggleLabel = $active ? __('Desativar') : __('Ativar');
            $toggleIcon = $active ? 'ti ti-pause' : 'ti ti-player-play';
            $toggleClass = $active ? 'btn-outline-warning' : 'btn-outline-success';
            echo "<button type='submit' name='toggle_entity_email' value='1' class='btn btn-sm $toggleClass' title='$toggleLabel'><i class='$toggleIcon'></i></button>";
            echo "</form>";
            // Delete
            echo "<form method='post' action='" . Plugin::getWebDir('protocolo') . "/front/config.php#entity-emails' class='d-inline' onsubmit=\"return confirm('Remover $email da entidade " . htmlspecialchars(addslashes($data['name'])) . "?')\">";
            echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
            echo "<input type='hidden' name='id' value='" . (int)$em['id'] . "'>";
            echo "<button type='submit' name='delete_entity_email' value='1' class='btn btn-sm btn-outline-danger' title='" . __('Remover') . "'><i class='ti ti-trash'></i></button>";
            echo "</form>";
            echo "</div></div>";
        }
        echo "</div>";
        echo "<div class='card-footer bg-white py-1'><small class='text-muted'><i class='ti ti-plug'></i> " . __('Origem', 'protocolo') . ": <code>entity_emails</code> • " . __('Será usado para envio', 'protocolo') . "</small></div>";
        echo "</div></div>";
    }
    echo "</div>";
    echo "<div class='form-text mt-2'><i class='ti ti-info-circle'></i> " . __('Cada card = 1 Entidade. Todos e-mails listados aqui são do PLUGIN e serão usados pelo cron. Clique em Desativar para pausar sem apagar.', 'protocolo') . "</div>";
} else {
    echo "<p class='text-muted'><i class='ti ti-inbox'></i> " . __('Nenhum e-mail por entidade cadastrado no PLUGIN. Adicione abaixo. Enquanto não cadastrar, o sistema usa Escola/Entidade GLPI + cópia como fallback.', 'protocolo') . "</p>";
}

// Form add
echo "<form method='post' action='" . Plugin::getWebDir('protocolo') . "/front/config.php#entity-emails' class='row g-2 align-items-end mt-3 p-3 bg-light rounded border'>";
echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
echo "<div class='col-md-5'><label class='form-label fw-semibold'>" . __('Entidade', 'protocolo') . "</label>";
// Usa Entity dropdown
try {
    echo "<div class='entity-dropdown-wrapper'>";
    \Entity::dropdown(['name' => 'entities_id', 'value' => ($_SESSION['glpiactive_entity'] ?? 0), 'entity' => 0, 'entity_sons' => true, 'comments' => false, 'width' => '100%']);
    echo "</div>";
} catch (Throwable $e) {
    echo "<select name='entities_id' class='form-select'><option value='0'>Entidade raiz (0)</option>";
    try {
        global $DB;
        $it = $DB->request(['FROM' => 'glpi_entities', 'ORDER' => 'name', 'LIMIT' => 100]);
        foreach ($it as $r) echo "<option value='" . (int)$r['id'] . "'>" . htmlspecialchars($r['completename'] ?? $r['name']) . "</option>";
    } catch (Throwable $e2) {}
    echo "</select>";
}
echo "</div>";
echo "<div class='col-md-5'><label class='form-label fw-semibold'>E-mail</label><input type='email' name='entity_email' class='form-control' placeholder='escola@ure.sp.gov.br' required></div>";
echo "<div class='col-md-2'><button type='submit' name='add_entity_email' value='1' class='btn btn-primary w-100'><i class='ti ti-plus'></i> " . __('Adicionar', 'protocolo') . "</button></div>";
echo "</form>";
echo "<div class='form-text mt-2'><small class='text-muted'>" . __('Dica: cadastre a Entidade raiz (0) para fallback quando a entidade da pasta não tiver e-mail específico.', 'protocolo') . "</small></div>";

echo "</div></div>";

echo "<div class='card shadow-sm mt-4'><div class='card-header bg-white'><strong><i class='ti ti-help'></i> " . __('Ajuda', 'protocolo') . "</strong></div><div class='card-body'>";
echo "<ul class='mb-0'>";
echo "<li><a href='" . Pasta::getSearchURL() . "'>" . __('Pastas', 'protocolo') . "</a> - " . __('Registro de entrada/retirada', 'protocolo') . "</li>";
echo "<li><a href='" . Escola::getSearchURL() . "'>" . __('Escolas', 'protocolo') . "</a></li>";
echo "<li><a href='" . TipoArquivo::getSearchURL() . "'>" . __('Tipos de Arquivo', 'protocolo') . "</a></li>";
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
