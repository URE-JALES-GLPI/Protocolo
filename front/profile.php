<?php
include('../../../inc/includes.php');

use GlpiPlugin\Protocolo\Profile as ProtoProfile;

Session::checkLoginUser();
if (!Session::haveRight('profile', UPDATE) && !Session::haveRight('config', UPDATE)) {
    Html::displayRightError();
}
if (!Session::validateCSRF($_POST)) {
    error_log("[protocolo] CSRF falhou em profile save, seguindo com save");
}

$profiles_id = (int)($_POST['profiles_id'] ?? 0);
if (!$profiles_id) {
    Html::displayErrorAndDie(__('Perfil inválido', 'protocolo'));
}

$profile = new Profile();
if (!$profile->getFromDB($profiles_id)) {
    Html::displayErrorAndDie(__('Perfil não encontrado', 'protocolo'));
}

global $DB;
$rights = ProtoProfile::getRightsStatic();

// Calcula valores enviados: suporta dropdown sequencial (valor único 0..255) e legado checkboxes (array bit=>1)
foreach ($rights as $rightName => $label) {
    $posted = $_POST["_{$rightName}"] ?? null;
    $value = 0;
    if (is_array($posted)) {
        // Legado: checkboxes GLPI enviam como _right[bit]=1
        foreach ($posted as $bit => $v) {
            if ((int)$v === 1) $value |= (int)$bit;
        }
    } elseif (is_numeric($posted)) {
        // Dropdown sequencial: valor direto 0,1,7,15,31,127,255 etc
        $value = (int)$posted;
        // clamp 0..255 para evitar injeção
        if ($value < 0) $value = 0;
        if ($value > 255) $value = 255;
    } elseif ($posted === null) {
        // nada enviado -> mantém 0 (sem acesso)
        $value = 0;
    }
    // Atualiza ou insere em glpi_profilerights
    $exists = $DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $profiles_id, 'name' => $rightName]]);
    if (count($exists) > 0) {
        $DB->update('glpi_profilerights', ['rights' => $value], ['profiles_id' => $profiles_id, 'name' => $rightName]);
    } else {
        $DB->insert('glpi_profilerights', ['profiles_id' => $profiles_id, 'name' => $rightName, 'rights' => $value]);
    }
}

Session::addMessageAfterRedirect(__('Permissões Protocolo salvas com sucesso', 'protocolo'), false, INFO);
// força recarregar sessão de direitos na próxima requisição (dropdown sequencial precisa refletir na hora)
if (isset($_SESSION['glpiactive_profile']['id']) && (int)$_SESSION['glpiactive_profile']['id'] === $profiles_id) {
    try { if (method_exists('Session', 'reloadCurrentProfile')) \Session::reloadCurrentProfile(); } catch (\Throwable $e) {}
    // sincroniza sessão imediatamente para o redirect já ver novo nível
    try {
        foreach ($rights as $rightName => $label) {
            $posted = $_POST["_{$rightName}"] ?? null;
            $val = 0;
            if (is_array($posted)) { foreach ($posted as $b => $v) if ((int)$v===1) $val|=(int)$b; }
            elseif (is_numeric($posted)) $val = max(0,min(255,(int)$posted));
            $_SESSION['glpiactive_profile'][$rightName] = $val;
            $_SESSION['glpiactiveprofile'][$rightName] = $val;
        }
    } catch (\Throwable $e) {}
    try { unset($_SESSION['glpiactive_profile'][$_SESSION['glpiactive_profile']['id'] ?? 0]); } catch (\Throwable $e) {}
}
Html::redirect($CFG_GLPI['root_doc'] . "/front/profile.form.php?id=" . $profiles_id . "&forcetab=GlpiPlugin\\Protocolo\\Profile\$1");
