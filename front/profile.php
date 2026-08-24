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

// Calcula valores enviados (formato GLPI: _plugin_protocolo_xxx[bit]=1)
foreach ($rights as $rightName => $label) {
    $posted = $_POST["_{$rightName}"] ?? [];
    // GLPI envia como array com chaves = bit, valores 0/1 (hidden 0 + checkboxes 1)
    $value = 0;
    if (is_array($posted)) {
        foreach ($posted as $bit => $v) {
            if ((int)$v === 1) $value |= (int)$bit;
        }
    } elseif (is_numeric($posted)) {
        $value = (int)$posted;
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
Html::redirect($CFG_GLPI['root_doc'] . "/front/profile.form.php?id=$profiles_id&forcetab=GlpiPlugin\\Protocolo\\Profile$1");
