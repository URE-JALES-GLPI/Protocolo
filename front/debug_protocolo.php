<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

use GlpiPlugin\Protocolo\Pasta;

Html::header("Debug Protocolo", $_SERVER['PHP_SELF'], 'tools', Pasta::class);

global $DB;

$uid = Session::getLoginUserID();
$uname = $_SESSION['glpiname'] ?? 'unknown';
$pid = (int)($_SESSION['glpiactive_profile']['id'] ?? 0);
$pname = $_SESSION['glpiactive_profile']['name'] ?? 'unknown';

echo "<div class='container-fluid'>";
echo "<h3>Debug Protocolo - Permissões</h3>";
echo "<div class='card shadow-sm mb-3'><div class='card-body'>";
echo "<p><b>Usuário:</b> #$uid - ".htmlspecialchars($uname)."<br>";
echo "<b>Perfil ativo:</b> #$pid - ".htmlspecialchars($pname)."<br>";
echo "<b>Session ID:</b> ".session_id()."</p>";

echo "<h5>Sessão (\$_SESSION['glpiactive_profile'])</h5>";
echo "<pre style='background:#f8f9fa; padding:10px; max-height:300px; overflow:auto;'>";
$toShow = $_SESSION['glpiactive_profile'] ?? [];
ksort($toShow);
foreach ($toShow as $k=>$v) {
    if (str_contains($k, 'protocolo') || $k==='id' || $k==='name' || $k==='interface') {
        echo htmlspecialchars("$k => ".json_encode($v))."\n";
    }
}
echo "</pre>";

echo "<h5>glpiactiveprofile</h5>";
echo "<pre style='background:#f8f9fa; padding:10px; max-height:200px; overflow:auto;'>";
$toShow2 = $_SESSION['glpiactiveprofile'] ?? [];
foreach ($toShow2 as $k=>$v) {
    if (str_contains($k, 'protocolo')) {
        echo htmlspecialchars("$k => ".json_encode($v))."\n";
    }
}
if (empty($toShow2)) echo "(vazio ou não setado)\n";
echo "</pre>";

echo "<h5>Banco (glpi_profilerights)</h5>";
echo "<table class='table table-sm table-bordered'><thead><tr><th>profiles_id</th><th>name</th><th>rights</th><th>haveRight(READ)?</th></tr></thead><tbody>";
try {
    $it = $DB->request(['FROM'=>'glpi_profilerights','WHERE'=>['profiles_id'=>$pid, 'name'=>['LIKE','plugin_protocolo%']],'ORDER'=>'name']);
    foreach ($it as $row) {
        $rights = (int)$row['rights'];
        $hasRead = ($rights & READ) ? 'SIM' : 'NÃO';
        $hasCreate = ($rights & CREATE) ? 'SIM' : 'NÃO';
        $badge = $rights==0?'bg-secondary':($rights==255?'bg-success':'bg-warning text-dark');
        echo "<tr><td>{$row['profiles_id']}</td><td><code>{$row['name']}</code></td><td><span class='badge $badge'>$rights</span> READ:$hasRead CREATE:$hasCreate</td><td>".($hasRead)."</td></tr>";
    }
} catch (Throwable $e) {
    echo "<tr><td colspan=4>Erro: ".htmlspecialchars($e->getMessage())."</td></tr>";
}
echo "</tbody></table>";

echo "<h5>Teste canView/canCreate (Pasta)</h5>";
$canView = Pasta::canView() ? 'SIM' : 'NÃO';
$canCreate = Pasta::canCreate() ? 'SIM' : 'NÃO';
$canViewClass = Pasta::canView() ? 'bg-success' : 'bg-danger';
$canCreateClass = Pasta::canCreate() ? 'bg-success' : 'bg-danger';
echo "<p><span class='badge $canViewClass'>canView: $canView</span> <span class='badge $canCreateClass'>canCreate: $canCreate</span><br>";
echo "<small class='text-muted'>Se perfil tem 0, ambos devem ser NÃO. Se mostra SIM com 0 no banco, há cache/sessão stale - deslogue/logue.</small></p>";

echo "<div class='alert alert-info'><b>Como testar:</b><br>";
echo "1) Este debug deve mostrar rights=0 e canView=NÃO para perfil Sem Acesso.<br>";
echo "2) Se mostra rights=0 mas canView=SIM, cole o log de erro do php-errors.log aqui.<br>";
echo "3) Se rights ainda é 1 ou 255, volte em Administração > Perfis > seu perfil > aba Protocolo > selecione 'Sem acesso (0)' para TODOS os 4 direitos e Salve, depois deslogue/logue.<br>";
echo "4) Acesse <code>/plugins/protocolo/front/dashboard.php</code> direto - deve dar 'Ação não permitida'.</div>";

echo "<a href='../front/dashboard.php' class='btn btn-outline-primary'>Ir p/ Dashboard</a> ";
echo "<a href='".$CFG_GLPI['root_doc']."/front/profile.form.php?id=$pid&forcetab=GlpiPlugin\\\\Protocolo\\\\Profile\$1' class='btn btn-outline-secondary ms-2'>Editar perfil #$pid</a>";

echo "</div></div></div>";

Html::footer();
