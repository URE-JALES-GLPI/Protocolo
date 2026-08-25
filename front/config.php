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

// --- Entidades / E-mails — VISÃO MELHORADA ---
echo "<div id='entity-emails' class='card shadow-sm mt-4'><div class='card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2'><div><strong><i class='ti ti-building'></i> " . __('E-mails por Entidade (Escola)', 'protocolo') . "</strong> <span class='badge bg-primary ms-2'>" . __('Para onde enviar notificações', 'protocolo') . "</span></div><small class='text-muted'><i class='ti ti-mail-check'></i> " . __('Somente e-mails <span class="badge bg-success">Ativos</span> desta lista recebem notificações', 'protocolo') . "</small></div><div class='card-body'>";

echo "<div class='alert alert-info small'><i class='ti ti-info-circle'></i> " . __('Cadastre e-mails por <strong>Entidade</strong> (GLPI → Administração → Entidades). Esses e-mails recebem notificações de <strong>entrada / retirada / atraso</strong> daquela entidade, além do e-mail da Escola e cópia global. Prioridade de envio: <code>Entidade (Plugin)</code> &gt; <code>Escola</code> &gt; <code>Entidade GLPI</code> &gt; <code>Cópia global</code>.', 'protocolo') . "</div>";

// Dados
$entityMails = EntityMail::getAllWithEntityNames();
$grouped = [];
foreach ($entityMails as $em) {
    $eid = (int)$em['entities_id'];
    if (!isset($grouped[$eid])) $grouped[$eid] = ['name' => $em['entity_name'] ?? EntityMail::getEntityName($eid), 'mails' => []];
    $grouped[$eid]['mails'][] = $em;
}
$totalEmails = count($entityMails);
$totalEntidades = count($grouped);
$activeTotal = count(array_filter($entityMails, fn($m) => (int)$m['is_active'] === 1));
$inactiveTotal = $totalEmails - $activeTotal;
$notifAtiva = (int)($config['notificacao_ativa'] ?? 0);

// KPIs
echo "<div class='row g-2 mb-3'>";
echo "<div class='col-6 col-md-3'><div class='card border h-100'><div class='card-body py-2 text-center'><div class='text-muted small'>" . __('Entidades com e-mail', 'protocolo') . "</div><div class='h4 mb-0'>{$totalEntidades}</div></div></div></div>";
echo "<div class='col-6 col-md-3'><div class='card border h-100'><div class='card-body py-2 text-center'><div class='text-muted small'>" . __('Total de e-mails', 'protocolo') . "</div><div class='h4 mb-0'>{$totalEmails}</div></div></div></div>";
echo "<div class='col-6 col-md-3'><div class='card border-success h-100' style='border-width:2px;'><div class='card-body py-2 text-center'><div class='text-success small fw-semibold'><i class='ti ti-mail-check'></i> " . __('Ativos (recebem)', 'protocolo') . "</div><div class='h4 mb-0 text-success'>{$activeTotal}</div></div></div></div>";
echo "<div class='col-6 col-md-3'><div class='card border h-100'><div class='card-body py-2 text-center'><div class='text-muted small'>" . __('Inativos (pausados)', 'protocolo') . "</div><div class='h4 mb-0 text-secondary'>{$inactiveTotal}</div></div></div></div>";
echo "</div>";

if (!$notifAtiva) {
    echo "<div class='alert alert-warning small py-2'><i class='ti ti-alert-triangle'></i> " . __('Notificações por e-mail estão <strong>DESATIVADAS</strong> nos Parâmetros gerais. Mesmo com e-mails cadastrados aqui, nada será enviado até ativar.', 'protocolo') . "</div>";
}
echo "<div class='alert alert-light border small mb-3'><i class='ti ti-plug'></i> " . __('Mostrando apenas e-mails cadastrados <strong>no PLUGIN</strong> (tabela <code>glpi_plugin_protocolo_entity_emails</code>). São esses que o cron usa. E-mails de <code>glpi_entities.email</code> ou <code>Escola.email</code> são fallback se não houver no plugin.', 'protocolo') . "</div>";

if ($grouped) {
    // Toolbar filtros
    echo "<div class='card border bg-light mb-3'><div class='card-body py-2'>";
    echo "<div class='row g-2 align-items-end'>";
    echo "<div class='col-md-4'><label class='form-label small fw-semibold mb-1'><i class='ti ti-search'></i> Buscar</label><input type='text' id='protocolo-email-search' class='form-control form-control-sm' placeholder='" . __('Filtrar por entidade ou e-mail…', 'protocolo') . "'></div>";
    echo "<div class='col-6 col-md-2'><label class='form-label small fw-semibold mb-1'>Status</label><select id='protocolo-filter-status' class='form-select form-select-sm'><option value='all'>" . __('Todos', 'protocolo') . "</option><option value='1'>" . __('Apenas Ativos', 'protocolo') . "</option><option value='0'>" . __('Apenas Inativos', 'protocolo') . "</option></select></div>";
    echo "<div class='col-6 col-md-3'><label class='form-label small fw-semibold mb-1'>Entidade</label><select id='protocolo-filter-entity' class='form-select form-select-sm'><option value='all'>" . __('Todas as entidades', 'protocolo') . "</option>";
    foreach ($grouped as $eid => $data) {
        $cnt = count($data['mails']);
        echo "<option value='" . (int)$eid . "'>" . htmlspecialchars($data['name']) . " (#{$eid} • {$cnt})</option>";
    }
    echo "</select></div>";
    echo "<div class='col-md-3 d-flex gap-1'>";
    echo "<button type='button' id='protocolo-btn-clear' class='btn btn-sm btn-outline-secondary flex-fill' title='" . __('Limpar filtros', 'protocolo') . "'><i class='ti ti-x'></i> " . __('Limpar', 'protocolo') . "</button>";
    echo "<button type='button' id='protocolo-btn-export' class='btn btn-sm btn-outline-primary flex-fill' title='Exportar CSV'><i class='ti ti-download'></i> CSV</button>";
    echo "<button type='button' id='protocolo-btn-copy-all' class='btn btn-sm btn-outline-success flex-fill' title='" . __('Copiar e-mails ativos visíveis', 'protocolo') . "'><i class='ti ti-copy'></i> " . __('Copiar', 'protocolo') . "</button>";
    echo "</div>";
    echo "</div>";
    echo "<div class='d-flex flex-wrap gap-2 mt-2 align-items-center'>";
    echo "<small class='text-muted'><span id='protocolo-visible-count'>{$totalEmails}</span> / {$totalEmails} e-mails visíveis • <span id='protocolo-visible-active'>{$activeTotal}</span> ativos visíveis <span class='d-none d-md-inline'>— " . __('estes são os que o cron enviará', 'protocolo') . "</span></small>";
    echo "<div class='ms-auto d-flex gap-1'>";
    echo "<button type='button' class='btn btn-xs btn-outline-secondary py-0 px-2' style='font-size:11px' onclick=\"document.querySelectorAll('#accordion-entity .accordion-collapse').forEach(el=>new bootstrap.Collapse(el,{toggle:false}).show())\"><i class='ti ti-chevrons-down'></i> Expandir</button>";
    echo "<button type='button' class='btn btn-xs btn-outline-secondary py-0 px-2' style='font-size:11px' onclick=\"document.querySelectorAll('#accordion-entity .accordion-collapse').forEach(el=>new bootstrap.Collapse(el,{toggle:false}).hide())\"><i class='ti ti-chevrons-up'></i> Recolher</button>";
    echo "</div>";
    echo "</div>";
    echo "</div></div>";

    // Tabs
    echo "<ul class='nav nav-tabs' role='tablist'>";
    echo "<li class='nav-item' role='presentation'><button class='nav-link active' id='tab-lista-btn' data-bs-toggle='tab' data-bs-target='#tab-lista' type='button' role='tab'><i class='ti ti-table'></i> " . __('Lista completa', 'protocolo') . " <span class='badge bg-primary ms-1'>{$totalEmails}</span></button></li>";
    echo "<li class='nav-item' role='presentation'><button class='nav-link' id='tab-agrupado-btn' data-bs-toggle='tab' data-bs-target='#tab-agrupado' type='button' role='tab'><i class='ti ti-layout-grid'></i> " . __('Agrupado por entidade', 'protocolo') . " <span class='badge bg-secondary ms-1'>{$totalEntidades}</span></button></li>";
    echo "</ul>";
    echo "<div class='tab-content border border-top-0 rounded-bottom bg-white'>";

    // TAB 1 — Lista completa (tabela)
    echo "<div class='tab-pane fade show active p-0' id='tab-lista' role='tabpanel'>";
    echo "<div class='table-responsive' style='max-height:520px; overflow:auto;'>";
    echo "<table class='table table-hover table-striped table-sm align-middle mb-0' id='protocolo-table-all'><thead class='table-light sticky-top' style='top:0; z-index:1;'><tr>";
    echo "<th style='width:40px'>#</th><th><i class='ti ti-building'></i> " . __('Entidade', 'protocolo') . "</th><th><i class='ti ti-mail'></i> E-mail</th><th style='width:110px'>" . __('Status', 'protocolo') . "</th><th style='width:140px' class='d-none d-md-table-cell'>" . __('Cadastrado em', 'protocolo') . "</th><th style='width:200px' class='text-end'>" . __('Ações', 'protocolo') . "</th>";
    echo "</tr></thead><tbody>";
    $idx = 0;
    foreach ($entityMails as $em) {
        $idx++;
        $eid = (int)$em['entities_id'];
        $entityName = htmlspecialchars($grouped[$eid]['name'] ?? $em['entity_name'] ?? "Entidade $eid");
        $emailRaw = $em['email'];
        $emailEsc = htmlspecialchars($emailRaw);
        $active = (int)$em['is_active'];
        $badge = $active ? "<span class='badge bg-success'><i class='ti ti-check'></i> Ativo</span> <small class='text-success d-block' style='font-size:10px'>" . __('recebe notificação', 'protocolo') . "</small>" : "<span class='badge bg-secondary'><i class='ti ti-pause'></i> Inativo</span> <small class='text-muted d-block' style='font-size:10px'>" . __('pausado', 'protocolo') . "</small>";
        $rowClass = $active ? '' : 'table-secondary opacity-75';
        $dateCre = htmlspecialchars($em['date_creation'] ?? '-');
        // formatar data curta
        try { if (!empty($em['date_creation']) && $em['date_creation'] !== '-') $dateCre = date('d/m/Y H:i', strtotime($em['date_creation'])); } catch (Throwable $e) {}
        echo "<tr class='protocolo-row $rowClass' data-entity='{$eid}' data-email='" . htmlspecialchars(strtolower($emailRaw), ENT_QUOTES) . "' data-entityname='" . htmlspecialchars(strtolower($grouped[$eid]['name'] ?? ''), ENT_QUOTES) . "' data-active='{$active}'>";
        echo "<td class='text-muted small'>{$idx}</td>";
        echo "<td><strong>{$entityName}</strong><br><small class='text-muted'>#{$eid}</small></td>";
        echo "<td><code title='{$emailEsc}' class='user-select-all'>{$emailEsc}</code> <button type='button' class='btn btn-xs btn-ghost-secondary ms-1 py-0 px-1 protocolo-copy-btn' data-email='{$emailEsc}' title='" . __('Copiar e-mail', 'protocolo') . "'><i class='ti ti-copy' style='font-size:12px'></i></button></td>";
        echo "<td>{$badge}</td>";
        echo "<td class='small text-muted d-none d-md-table-cell'>{$dateCre}</td>";
        echo "<td class='text-end'><div class='d-inline-flex gap-1'>";
        // Toggle
        echo "<form method='post' action='" . Plugin::getWebDir('protocolo') . "/front/config.php#entity-emails' class='d-inline'>";
        echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
        echo "<input type='hidden' name='id' value='" . (int)$em['id'] . "'>";
        $toggleLabel = $active ? __('Desativar', 'protocolo') : __('Ativar', 'protocolo');
        $toggleIcon = $active ? 'ti ti-player-pause' : 'ti ti-player-play';
        $toggleClass = $active ? 'btn-outline-warning' : 'btn-outline-success';
        $toggleTitle = $active ? __('Pausar sem apagar — não receberá mais', 'protocolo') : __('Reativar — voltará a receber', 'protocolo');
        echo "<button type='submit' name='toggle_entity_email' value='1' class='btn btn-sm $toggleClass' title='{$toggleTitle}'><i class='$toggleIcon'></i> <span class='d-none d-lg-inline'>{$toggleLabel}</span></button>";
        echo "</form>";
        // Delete
        echo "<form method='post' action='" . Plugin::getWebDir('protocolo') . "/front/config.php#entity-emails' class='d-inline' onsubmit=\"return confirm('Remover {$emailEsc} da entidade {$entityName} (#{$eid})?')\">";
        echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
        echo "<input type='hidden' name='id' value='" . (int)$em['id'] . "'>";
        echo "<button type='submit' name='delete_entity_email' value='1' class='btn btn-sm btn-outline-danger' title='" . __('Remover definitivamente', 'protocolo') . "'><i class='ti ti-trash'></i></button>";
        echo "</form>";
        echo "</div></td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
    echo "</div>";
    echo "<div id='protocolo-no-results' class='alert alert-warning small m-3 d-none'><i class='ti ti-search-off'></i> " . __('Nenhum e-mail corresponde aos filtros.', 'protocolo') . " <a href='#' onclick=\"document.getElementById('protocolo-btn-clear').click(); return false;\">" . __('Limpar filtros', 'protocolo') . "</a></div>";
    echo "<div class='card-footer bg-light py-2 d-flex flex-wrap gap-2 align-items-center'><small class='text-muted'><i class='ti ti-info-circle'></i> " . __('Dica: use <strong>Buscar</strong> para filtrar por nome da entidade ou e-mail. Linhas <span class="badge bg-success">Ativo</span> são as que o cron envia.', 'protocolo') . "</small><span class='ms-auto badge bg-success' id='protocolo-footer-active'>{$activeTotal} ativos</span></div>";
    echo "</div>";

    // TAB 2 — Agrupado por entidade (accordion)
    echo "<div class='tab-pane fade p-3' id='tab-agrupado' role='tabpanel'>";
    echo "<div class='accordion' id='accordion-entity'>";
    foreach ($grouped as $eid => $data) {
        $cnt = count($data['mails']);
        $activeCnt = count(array_filter($data['mails'], fn($m) => (int)$m['is_active'] === 1));
        $inactiveCnt = $cnt - $activeCnt;
        $entityEsc = htmlspecialchars($data['name']);
        $accId = "ent-{$eid}";
        $emailsJson = htmlspecialchars(json_encode(array_column($data['mails'], 'email')), ENT_QUOTES);
        $activeEmails = array_filter($data['mails'], fn($m) => (int)$m['is_active'] === 1);
        $activeList = implode('; ', array_column($activeEmails, 'email'));
        echo "<div class='accordion-item protocolo-group' data-entity='{$eid}' data-entityname='" . htmlspecialchars(strtolower($data['name']), ENT_QUOTES) . "' data-count='{$cnt}'>";
        echo "<h2 class='accordion-header' id='heading-{$accId}'><button class='accordion-button collapsed py-2' type='button' data-bs-toggle='collapse' data-bs-target='#collapse-{$accId}'>";
        echo "<span class='d-flex align-items-center gap-2 w-100'><i class='ti ti-building'></i> <strong>{$entityEsc}</strong> <small class='text-muted'>#{$eid}</small> <span class='badge bg-primary ms-2'>{$cnt} <i class='ti ti-mail'></i></span> <span class='badge bg-success'>{$activeCnt} ativos</span>" . ($inactiveCnt ? " <span class='badge bg-secondary'>{$inactiveCnt} inativos</span>" : "") . " <small class='text-muted ms-auto d-none d-md-inline'><i class='ti ti-send'></i> " . __('envia para', 'protocolo') . " {$activeCnt}</small></span>";
        echo "</button></h2>";
        echo "<div id='collapse-{$accId}' class='accordion-collapse collapse' data-bs-parent='#accordion-entity'><div class='accordion-body p-2'>";
        // barra da entidade
        echo "<div class='d-flex flex-wrap gap-2 mb-2 align-items-center'>";
        echo "<small class='text-muted'><i class='ti ti-mail-check'></i> " . __('Notificações ativas desta entidade:', 'protocolo') . " <strong class='text-success'>{$activeCnt}</strong> / {$cnt}</small>";
        echo "<div class='ms-auto d-flex gap-1'>";
        if ($activeList !== '') {
            echo "<button type='button' class='btn btn-sm btn-outline-success py-0 px-2 protocolo-copy-group' data-emails=\"" . htmlspecialchars($activeList, ENT_QUOTES) . "\" style='font-size:12px'><i class='ti ti-copy'></i> " . __('Copiar ativos', 'protocolo') . "</button>";
        }
        $allList = implode('; ', array_column($data['mails'], 'email'));
        echo "<button type='button' class='btn btn-sm btn-outline-secondary py-0 px-2 protocolo-copy-group-all' data-emails=\"" . htmlspecialchars($allList, ENT_QUOTES) . "\" style='font-size:12px'><i class='ti ti-copy'></i> " . __('Copiar todos', 'protocolo') . "</button>";
        echo "</div></div>";
        echo "<div class='table-responsive'><table class='table table-sm table-hover mb-0'><thead class='table-light'><tr><th>E-mail</th><th style='width:110px'>Status</th><th class='d-none d-md-table-cell' style='width:130px'>Cadastro</th><th style='width:180px' class='text-end'>" . __('Ações', 'protocolo') . "</th></tr></thead><tbody>";
        foreach ($data['mails'] as $em) {
            $email = htmlspecialchars($em['email']);
            $active = (int)$em['is_active'];
            $badge = $active ? "<span class='badge bg-success'>Ativo</span>" : "<span class='badge bg-secondary'>Inativo</span>";
            $rowBg = $active ? '' : 'table-secondary opacity-75';
            $dateCre = htmlspecialchars($em['date_creation'] ?? '-');
            try { if (!empty($em['date_creation']) && $em['date_creation'] !== '-') $dateCre = date('d/m/Y H:i', strtotime($em['date_creation'])); } catch (Throwable $e) {}
            echo "<tr class='protocolo-group-row $rowBg' data-email='" . htmlspecialchars(strtolower($em['email']), ENT_QUOTES) . "' data-active='{$active}'><td><code>{$email}</code> <button type='button' class='btn btn-xs btn-ghost-secondary ms-1 py-0 px-1 protocolo-copy-btn' data-email='{$email}' title='" . __('Copiar', 'protocolo') . "'><i class='ti ti-copy' style='font-size:12px'></i></button></td><td>{$badge}</td><td class='small text-muted d-none d-md-table-cell'>{$dateCre}</td><td class='text-end'><div class='d-inline-flex gap-1'>";
            echo "<form method='post' action='" . Plugin::getWebDir('protocolo') . "/front/config.php#entity-emails' class='d-inline'>";
            echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
            echo "<input type='hidden' name='id' value='" . (int)$em['id'] . "'>";
            $toggleLabel = $active ? __('Desativar') : __('Ativar');
            $toggleIcon = $active ? 'ti ti-player-pause' : 'ti ti-player-play';
            $toggleClass = $active ? 'btn-outline-warning' : 'btn-outline-success';
            echo "<button type='submit' name='toggle_entity_email' value='1' class='btn btn-sm $toggleClass py-0 px-2' style='font-size:12px'><i class='$toggleIcon'></i> {$toggleLabel}</button>";
            echo "</form>";
            echo "<form method='post' action='" . Plugin::getWebDir('protocolo') . "/front/config.php#entity-emails' class='d-inline' onsubmit=\"return confirm('Remover {$email} da entidade {$entityEsc}?')\">";
            echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
            echo "<input type='hidden' name='id' value='" . (int)$em['id'] . "'>";
            echo "<button type='submit' name='delete_entity_email' value='1' class='btn btn-sm btn-outline-danger py-0 px-2' style='font-size:12px'><i class='ti ti-trash'></i></button>";
            echo "</form>";
            echo "</div></td></tr>";
        }
        echo "</tbody></table></div>";
        echo "<div class='form-text mt-2'><i class='ti ti-info-circle'></i> " . __('Este agrupamento mostra todos e-mails do PLUGIN para a entidade. Apenas os <span class="badge bg-success">Ativos</span> serão usados no envio.', 'protocolo') . "</div>";
        echo "</div></div>";
        echo "</div>";
    }
    echo "</div>"; // accordion
    // Entidades sem e-mail (diagnóstico)
    try {
        global $DB;
        if ($DB->tableExists('glpi_entities')) {
            $allEnt = [];
            $it = $DB->request(['FROM' => 'glpi_entities', 'ORDER' => 'completename', 'LIMIT' => 200]);
            foreach ($it as $r) $allEnt[(int)$r['id']] = $r['completename'] ?? $r['name'];
            $missing = array_diff_key($allEnt, $grouped);
            if (!empty($missing)) {
                $missCnt = count($missing);
                echo "<div class='alert alert-light border mt-3 mb-0'><details><summary class='fw-semibold' style='cursor:pointer'><i class='ti ti-alert-circle'></i> " . __('Entidades sem e-mail no plugin', 'protocolo') . " ({$missCnt}) — " . __('usarão fallback', 'protocolo') . " <small class='text-muted'>(" . __('clique para ver', 'protocolo') . ")</small></summary><div class='mt-2 small' style='max-height:160px; overflow:auto;'><ul class='mb-0'>";
                $shown = 0;
                foreach ($missing as $mid => $mname) {
                    if ($shown++ >= 50) { echo "<li class='text-muted'>... + " . ($missCnt - 50) . " outras</li>"; break; }
                    echo "<li>" . htmlspecialchars($mname) . " <small class='text-muted'>#{$mid}</small> <span class='badge bg-light text-dark border'>sem e-mail plugin</span></li>";
                }
                echo "</ul><div class='form-text'>" . __('Cadastre e-mails para essas entidades se quiser notificações específicas, senão o sistema usa Escola/Entidade GLPI + cópia.', 'protocolo') . "</div></div></details></div>";
            }
        }
    } catch (Throwable $e) {}
    echo "</div>"; // tab agrupado
    echo "</div>"; // tab-content

    // JS filtros + ações (nowdoc para não precisar escapar)
    echo <<<'JS'
<script>
    (function(){
        const search = document.getElementById('protocolo-email-search');
        const fStatus = document.getElementById('protocolo-filter-status');
        const fEntity = document.getElementById('protocolo-filter-entity');
        const btnClear = document.getElementById('protocolo-btn-clear');
        const btnExport = document.getElementById('protocolo-btn-export');
        const btnCopyAll = document.getElementById('protocolo-btn-copy-all');
        const rows = document.querySelectorAll('#protocolo-table-all tbody tr.protocolo-row');
        const groups = document.querySelectorAll('.protocolo-group');
        const noRes = document.getElementById('protocolo-no-results');
        const visCount = document.getElementById('protocolo-visible-count');
        const visActive = document.getElementById('protocolo-visible-active');
        const footActive = document.getElementById('protocolo-footer-active');
        function apply(){
            const q = (search.value||'').toLowerCase().trim();
            const s = fStatus.value;
            const e = fEntity.value;
            let visible=0, visibleActive=0;
            rows.forEach(r=>{
                const email = r.dataset.email||'';
                const ename = r.dataset.entityname||'';
                const eid = r.dataset.entity||'';
                const active = r.dataset.active||'';
                let ok = true;
                if(q && email.indexOf(q)===-1 && ename.indexOf(q)===-1 && eid.indexOf(q)===-1) ok=false;
                if(s!=='all' && active!==s) ok=false;
                if(e!=='all' && eid!==e) ok=false;
                r.style.display = ok ? '' : 'none';
                if(ok){ visible++; if(active==='1') visibleActive++; }
            });
            groups.forEach(g=>{
                const gid = g.dataset.entity||'';
                const gname = g.dataset.entityname||'';
                const innerRows = g.querySelectorAll('.protocolo-group-row');
                let gVisible=0;
                innerRows.forEach(ir=>{
                    const iemail = ir.dataset.email||'';
                    const iact = ir.dataset.active||'';
                    let ok = true;
                    if(q && iemail.indexOf(q)===-1 && gname.indexOf(q)===-1) ok=false;
                    if(s!=='all' && iact!==s) ok=false;
                    ir.style.display = ok ? '' : 'none';
                    if(ok){ gVisible++; }
                });
                let groupOk = true;
                if(e!=='all' && gid!==e) groupOk=false;
                if(q && gVisible===0) groupOk=false;
                if(s!=='all' && gVisible===0) groupOk=false;
                g.style.display = groupOk ? '' : 'none';
            });
            if(visCount) visCount.textContent = visible;
            if(visActive) visActive.textContent = visibleActive;
            if(footActive) footActive.textContent = visibleActive + ' ativos';
            if(noRes) noRes.classList.toggle('d-none', visible!==0);
        }
        if(search) search.addEventListener('input', apply);
        if(fStatus) fStatus.addEventListener('change', apply);
        if(fEntity) fEntity.addEventListener('change', apply);
        if(btnClear) btnClear.addEventListener('click', ()=>{ search.value=''; fStatus.value='all'; fEntity.value='all'; apply(); search.focus(); });
        document.addEventListener('click', function(ev){
            const btn = ev.target.closest('.protocolo-copy-btn');
            if(btn){
                const em = btn.dataset.email||'';
                if(!em) return;
                if(navigator.clipboard){ navigator.clipboard.writeText(em).then(()=>{ const o=btn.innerHTML; btn.innerHTML='<i class="ti ti-check" style="font-size:12px"></i>'; setTimeout(()=>btn.innerHTML=o,1200); }); } else { prompt('Copie:', em); }
            }
            const gbtn = ev.target.closest('.protocolo-copy-group');
            if(gbtn){
                const ems = gbtn.dataset.emails||'';
                if(!ems) return;
                if(navigator.clipboard){ navigator.clipboard.writeText(ems).then(()=>{ const o=gbtn.innerHTML; gbtn.innerHTML='<i class="ti ti-check"></i> Copiado!'; setTimeout(()=>gbtn.innerHTML=o,1500); }); } else { prompt('Copie:', ems); }
            }
            const gall = ev.target.closest('.protocolo-copy-group-all');
            if(gall){
                const ems = gall.dataset.emails||'';
                if(!ems) { alert('Nenhum e-mail neste grupo.'); return; }
                if(!confirm('Copiar TODOS os e-mails desta entidade (ativos + inativos)?')) return;
                if(navigator.clipboard){ navigator.clipboard.writeText(ems).then(()=>{ const o=gall.innerHTML; gall.innerHTML='<i class="ti ti-check"></i> Copiado!'; setTimeout(()=>gall.innerHTML=o,1500); }); } else { prompt('Copie:', ems); }
            }
        });
        if(btnCopyAll) btnCopyAll.addEventListener('click', ()=>{
            const visibleRows = Array.from(rows).filter(r=>r.style.display!=='none' && r.dataset.active==='1');
            const emails = visibleRows.map(r=>r.dataset.email).filter(Boolean);
            const uniq = [...new Set(emails)];
            if(!uniq.length){ alert('Nenhum e-mail ativo visível para copiar.'); return; }
            const txt = uniq.join('; ');
            if(navigator.clipboard){ navigator.clipboard.writeText(txt).then(()=>{ const o=btnCopyAll.innerHTML; btnCopyAll.innerHTML='<i class="ti ti-check"></i> Copiado!'; setTimeout(()=>btnCopyAll.innerHTML=o,1500); }); } else { prompt('Copie:', txt); }
        });
        if(btnExport) btnExport.addEventListener('click', ()=>{
            const visibleRows = Array.from(rows).filter(r=>r.style.display!=='none');
            if(!visibleRows.length){ alert('Nada para exportar com filtros atuais.'); return; }
            let csv = 'Entidade ID,Entidade,E-mail,Status,Cadastrado em\n';
            visibleRows.forEach(r=>{
                const eid = r.dataset.entity;
                const tds = r.querySelectorAll('td');
                const ent = tds[1]?.innerText.replace(/\n/g,' ').replace(/"/g,'""')||'';
                const email = r.dataset.email||'';
                const status = r.dataset.active==='1'?'Ativo':'Inativo';
                const date = tds[4]?.innerText||'';
                csv += '"'+eid+'","'+ent+'","'+email+'","'+status+'","'+date+'"\n';
            });
            const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a'); a.href=url; a.download='protocolo-emails-por-entidade.csv'; a.click(); URL.revokeObjectURL(url);
        });
        apply();
    })();
</script>
JS;
    echo "<div class='form-text mt-2'><i class='ti ti-info-circle'></i> " . __('Cada card/accordion = 1 Entidade. Clique em <strong>Ativar/Desativar</strong> para pausar sem apagar. Use <strong>Buscar</strong> ou filtros para achar rápido. Aba <strong>Lista completa</strong> mostra tudo em tabela — ideal para conferência e exportação.', 'protocolo') . "</div>";
} else {
    echo "<div class='text-center py-4'><div class='mb-2'><i class='ti ti-inbox' style='font-size:32px; opacity:.4'></i></div><p class='text-muted mb-2'><strong>" . __('Nenhum e-mail por entidade cadastrado no PLUGIN.', 'protocolo') . "</strong></p><p class='small text-muted'>" . __('Adicione abaixo. Enquanto não cadastrar, o sistema usa <code>Escola.email</code> / <code>glpi_entities.email</code> + <code>cópia global</code> como fallback.', 'protocolo') . "</p></div>";
    echo "<div class='alert alert-light border small'><i class='ti ti-bulb'></i> <strong>" . __('Como funciona o envio?', 'protocolo') . "</strong><br>" . __('1) Se a entidade da pasta tem e-mails <span class="badge bg-success">Ativos</span> aqui → envia para eles.<br>2) Senão, tenta e-mail da Escola e da Entidade GLPI.<br>3) Sempre adiciona cópia global (se configurada).<br>Portanto, cadastre aqui os e-mails que <strong>devem receber</strong>.', 'protocolo') . "</div>";
}

// Form add — melhorado
echo "<form method='post' action='" . Plugin::getWebDir('protocolo') . "/front/config.php#entity-emails' class='row g-2 align-items-end mt-4 p-3 bg-light rounded border'>";
echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
echo "<div class='col-md-5'><label class='form-label fw-semibold'><i class='ti ti-building'></i> " . __('Entidade', 'protocolo') . " <span class='text-danger'>*</span></label>";
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
echo "<div class='form-text' style='font-size:11px'>" . __('Entidade que receberá notificações neste e-mail.', 'protocolo') . "</div></div>";
echo "<div class='col-md-5'><label class='form-label fw-semibold'><i class='ti ti-mail'></i> E-mail <span class='text-danger'>*</span></label><input type='email' name='entity_email' class='form-control' placeholder='escola@ure.sp.gov.br' required><div class='form-text' style='font-size:11px'><i class='ti ti-send'></i> " . __('Este e-mail entrará como <span class="badge bg-success">Ativo</span> e já começará a receber.', 'protocolo') . "</div></div>";
echo "<div class='col-md-2'><button type='submit' name='add_entity_email' value='1' class='btn btn-primary w-100'><i class='ti ti-plus'></i> " . __('Adicionar', 'protocolo') . "</button></div>";
echo "</form>";
echo "<div class='form-text mt-2'><small class='text-muted'><i class='ti ti-lightbulb'></i> " . __('Dica: cadastre a <strong>Entidade raiz (0)</strong> para fallback quando a entidade da pasta não tiver e-mail específico. Você pode cadastrar <strong>vários e-mails por entidade</strong> — todos os ativos recebem.', 'protocolo') . "</small></div>";

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
