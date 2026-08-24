<?php
/**
 * tools/migrate_standalone.php
 * Migra dados do sistema standalone (BD `protocolo`) para o plugin GLPI (glpi_plugin_protocolo_*)
 * Uso: php tools/migrate_standalone.php  (rodar dentro do container GLPI ou com acesso ao DB GLPI)
 *
 * Requer: acesso ao DB antigo via config temporária ou dump import.
 * Este script assume que as tabelas antigas foram importadas temporariamente no mesmo DB GLPI
 * com prefixo `old_` OU que você aponta $OLD_DB via PDO DSN.
 *
 * Fluxo:
 *  - Lê `old_escolas` -> glpi_plugin_protocolo_escolas
 *  - Lê `old_tipos_arquivo` -> glpi_plugin_protocolo_tipos
 *  - Lê `old_pastas` + `old_pasta_itens` + `old_pasta_tipos` + `old_termos`
 *  - Mapeia users: tenta casar por username, senão usa id do Super-Admin
 *
 * Execute após instalar o plugin (tabelas já criadas).
 */

define('GLPI_ROOT', dirname(__DIR__, 3)); // adapta se necessário: plugins/protocolo/tools -> glpi
if (!file_exists(GLPI_ROOT . '/inc/includes.php')) {
    // Tenta outro nível (quando plugin ainda está em C:\Projetos\protocolo)
    echo "Rodar este script apenas DENTRO do GLPI (copie para glpi/plugins/protocolo/tools/).\n";
    exit(1);
}
include(GLPI_ROOT . '/inc/includes.php');

global $DB;

// Config: onde estão as tabelas antigas? Opções:
// 1) mesmas BD, tabelas prefixadas `old_`
// 2) DSN externo (descomente abaixo)

$OLD_PREFIX = 'old_'; // ex: old_escolas, old_pastas...
$OLD_DSN = null; // ex: 'mysql:host=localhost;dbname=protocolo;charset=utf8mb4'
$OLD_USER = null;
$OLD_PASS = null;

$oldDB = null;
if ($OLD_DSN) {
    $oldDB = new PDO($OLD_DSN, $OLD_USER, $OLD_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    echo "Conectado ao BD antigo via DSN\n";
} else {
    // usa mesmo $DB mas com prefix old_
    // verifica se existe old_escolas
    if (!$DB->tableExists($OLD_PREFIX . 'escolas')) {
        echo "Tabelas antigas não encontradas ({$OLD_PREFIX}escolas). Importe o dump do standalone com prefixo old_ antes.\n";
        echo "Ex: mysqldump protocolo | sed 's/`escolas`/`old_escolas`/g' | mysql glpi\n";
        exit(1);
    }
    echo "Usando tabelas prefixadas {$OLD_PREFIX}* no mesmo BD GLPI\n";
}

function qOld(string $sql, array $params = []): array
{
    global $DB, $oldDB, $OLD_PREFIX;
    if ($oldDB) {
        $stmt = $oldDB->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } else {
        // substitui nomes de tabelas genéricos
        // $sql vem com placeholders `escolas` etc - prefixa
        $sql = str_replace(['`escolas`', '`pastas`', '`pasta_itens`', '`tipos_arquivo`', '`pasta_tipos`', '`termos`', '`usuarios`'], 
                           ["`{$OLD_PREFIX}escolas`", "`{$OLD_PREFIX}pastas`", "`{$OLD_PREFIX}pasta_itens`", "`{$OLD_PREFIX}tipos_arquivo`", "`{$OLD_PREFIX}pasta_tipos`", "`{$OLD_PREFIX}termos`", "`{$OLD_PREFIX}usuarios`"], $sql);
        $stmt = $DB->doQuery($sql); // não preparado, simplifica
        // fallback para request
        $res = [];
        if ($stmt) while ($row = $DB->fetchAssoc($stmt)) $res[] = $row;
        return $res;
    }
}

echo "Iniciando migração...\n";

// 1) Escolas
echo "Migrando escolas...\n";
$escolas = [];
// tenta via PDO direto se oldDB
if ($oldDB) {
    $stmt = $oldDB->query("SELECT * FROM escolas ORDER BY id");
    $escolas = $stmt->fetchAll();
} else {
    $it = $DB->request(['FROM' => $OLD_PREFIX . 'escolas', 'ORDER' => 'id']);
    foreach ($it as $r) $escolas[] = $r;
}
$mapEscola = []; // old_id => new_id
foreach ($escolas as $e) {
    $oldId = $e['id'];
    // verifica se já existe por codigo+nome
    $existing = $DB->request(['FROM' => 'glpi_plugin_protocolo_escolas', 'WHERE' => ['name' => $e['nome'], 'codigo' => $e['codigo'] ?? ''], 'LIMIT' => 1]);
    $found = null;
    foreach ($existing as $ex) $found = $ex;
    if ($found) {
        $mapEscola[$oldId] = $found['id'];
        echo "  Escola {$e['nome']} já existe id {$found['id']}, mapeada\n";
        continue;
    }
    $DB->insert('glpi_plugin_protocolo_escolas', [
        'name' => $e['nome'],
        'codigo' => $e['codigo'] ?? null,
        'email' => $e['email'] ?? null,
        'phone' => $e['telefone'] ?? null,
        'address' => $e['endereco'] ?? null,
        'responsavel' => $e['responsavel'] ?? null,
        'is_active' => $e['ativo'] ?? 1,
        'entities_id' => 0,
        'date_creation' => $e['criado_em'] ?? date('Y-m-d H:i:s')
    ]);
    $newId = $DB->insertId();
    $mapEscola[$oldId] = $newId;
    echo "  + Escola {$e['nome']} ($oldId -> $newId)\n";
}

// 2) Tipos
echo "Migrando tipos_arquivo...\n";
$tipos = [];
if ($oldDB) {
    $stmt = $oldDB->query("SELECT * FROM tipos_arquivo ORDER BY id");
    $tipos = $stmt->fetchAll();
} else {
    if ($DB->tableExists($OLD_PREFIX . 'tipos_arquivo')) {
        $it = $DB->request(['FROM' => $OLD_PREFIX . 'tipos_arquivo', 'ORDER' => 'id']);
        foreach ($it as $r) $tipos[] = $r;
    }
}
$mapTipo = [];
foreach ($tipos as $t) {
    $oldId = $t['id'];
    $existing = $DB->request(['FROM' => 'glpi_plugin_protocolo_tipos', 'WHERE' => ['name' => $t['nome']], 'LIMIT' => 1]);
    $found = null;
    foreach ($existing as $ex) $found = $ex;
    if ($found) { $mapTipo[$oldId] = $found['id']; continue; }
    $DB->insert('glpi_plugin_protocolo_tipos', [
        'name' => $t['nome'],
        'comment' => $t['descricao'] ?? null,
        'is_active' => $t['ativo'] ?? 1
    ]);
    $mapTipo[$oldId] = $DB->insertId();
    echo "  + Tipo {$t['nome']} ($oldId -> {$mapTipo[$oldId]})\n";
}

// 3) Usuários mapping (username -> glpi_users.id)
echo "Mapeando usuários...\n";
$mapUser = []; // old usuarios.id => glpi_users.id
$usersOld = [];
if ($oldDB) {
    $stmt = $oldDB->query("SELECT id, username FROM usuarios");
    $usersOld = $stmt->fetchAll();
} else {
    if ($DB->tableExists($OLD_PREFIX . 'usuarios')) {
        $it = $DB->request(['FROM' => $OLD_PREFIX . 'usuarios']);
        foreach ($it as $r) $usersOld[] = $r;
    }
}
foreach ($usersOld as $u) {
    $it = $DB->request(['FROM' => 'glpi_users', 'WHERE' => ['name' => $u['username']], 'LIMIT' => 1]);
    $found = null;
    foreach ($it as $ex) $found = $ex;
    if ($found) {
        $mapUser[$u['id']] = $found['id'];
        echo "  User {$u['username']} {$u['id']} -> {$found['id']}\n";
    } else {
        // fallback para admin (id 2 normalmente)
        $mapUser[$u['id']] = 2;
        echo "  User {$u['username']} sem match, usando 2 (glpi)\n";
    }
}
if (empty($mapUser)) {
    echo "  Nenhum usuário antigo, usando glpi=2 para criado_por\n";
    $mapUser[0] = 2;
}

// 4) Pastas
echo "Migrando pastas...\n";
$pastas = [];
if ($oldDB) {
    $stmt = $oldDB->query("SELECT * FROM pastas ORDER BY id");
    $pastas = $stmt->fetchAll();
} else {
    $it = $DB->request(['FROM' => $OLD_PREFIX . 'pastas', 'ORDER' => 'id']);
    foreach ($it as $r) $pastas[] = $r;
}
$mapPasta = []; // old pasta id -> new pasta id
foreach ($pastas as $p) {
    $oldId = $p['id'];
    // verifica se já existe por codigo
    $existing = $DB->request(['FROM' => 'glpi_plugin_protocolo_pastas', 'WHERE' => ['codigo' => $p['codigo']], 'LIMIT' => 1]);
    $found = null;
    foreach ($existing as $ex) $found = $ex;
    if ($found) {
        $mapPasta[$oldId] = $found['id'];
        echo "  Pasta {$p['codigo']} já existe, skip\n";
        continue;
    }
    $newEscolaId = $mapEscola[$p['escola_id']] ?? null;
    if (!$newEscolaId) {
        echo "  ! Escola {$p['escola_id']} não mapeada para pasta {$p['codigo']}, pular\n";
        continue;
    }
    $usersId = $mapUser[$p['criado_por'] ?? 0] ?? 2;
    $usersRet = $mapUser[$p['retirado_por_usuario'] ?? 0] ?? null;
    if (empty($p['retirado_por_usuario'])) $usersRet = null;

    $DB->insert('glpi_plugin_protocolo_pastas', [
        'codigo' => $p['codigo'],
        'plugin_protocolo_escolas_id' => $newEscolaId,
        'status' => $p['status'],
        'data_recebimento' => $p['data_recebimento'],
        'recebido_de' => $p['recebido_de'],
        'recebido_documento' => $p['recebido_documento'] ?? null,
        'observacao' => $p['observacao'] ?? null,
        'data_retirada' => $p['data_retirada'] ?? null,
        'retirado_por' => $p['retirado_por'] ?? null,
        'retirado_documento' => $p['retirado_documento'] ?? null,
        'observacao_retirada' => $p['observacao_retirada'] ?? null,
        'users_id' => $usersId,
        'users_id_retirada' => $usersRet,
        'entities_id' => 0,
        'is_deleted' => 0,
        'date_creation' => $p['criado_em'] ?? date('Y-m-d H:i:s'),
        'date_mod' => $p['atualizado_em'] ?? null
    ]);
    $newId = $DB->insertId();
    $mapPasta[$oldId] = $newId;
    echo "  + Pasta {$p['codigo']} ($oldId -> $newId)\n";
}

// 5) Itens
echo "Migrando pasta_itens...\n";
$itens = [];
if ($oldDB) {
    $stmt = $oldDB->query("SELECT * FROM pasta_itens ORDER BY id");
    $itens = $stmt->fetchAll();
} else {
    $it = $DB->request(['FROM' => $OLD_PREFIX . 'pasta_itens', 'ORDER' => 'id']);
    foreach ($it as $r) $itens[] = $r;
}
foreach ($itens as $itRow) {
    $oldPasta = $itRow['pasta_id'];
    $newPasta = $mapPasta[$oldPasta] ?? null;
    if (!$newPasta) continue;
    $DB->insert('glpi_plugin_protocolo_itens', [
        'plugin_protocolo_pastas_id' => $newPasta,
        'name' => $itRow['descricao'],
        'quantidade' => $itRow['quantidade'],
        'comment' => $itRow['observacao'] ?? null
    ]);
}
echo "  Itens migrados: " . count($itens) . "\n";

// 6) pasta_tipos
echo "Migrando pasta_tipos...\n";
$pastaTipos = [];
if ($oldDB) {
    // tabela pode não existir se migração antiga não rodou
    try { $stmt = $oldDB->query("SELECT * FROM pasta_tipos"); $pastaTipos = $stmt->fetchAll(); } catch (Exception $e) { $pastaTipos = []; }
} else {
    if ($DB->tableExists($OLD_PREFIX . 'pasta_tipos')) {
        $it = $DB->request(['FROM' => $OLD_PREFIX . 'pasta_tipos']);
        foreach ($it as $r) $pastaTipos[] = $r;
    }
}
foreach ($pastaTipos as $pt) {
    $newP = $mapPasta[$pt['pasta_id']] ?? null;
    $newT = $mapTipo[$pt['tipo_id']] ?? null;
    if (!$newP || !$newT) continue;
    $DB->insert('glpi_plugin_protocolo_pastatipos', [
        'plugin_protocolo_pastas_id' => $newP,
        'plugin_protocolo_tipos_id' => $newT
    ]);
}
echo "  pasta_tipos: " . count($pastaTipos) . "\n";

// 7) Termos
echo "Migrando termos...\n";
$termos = [];
if ($oldDB) {
    $stmt = $oldDB->query("SELECT * FROM termos ORDER BY id");
    $termos = $stmt->fetchAll();
} else {
    $it = $DB->request(['FROM' => $OLD_PREFIX . 'termos', 'ORDER' => 'id']);
    foreach ($it as $r) $termos[] = $r;
}
foreach ($termos as $t) {
    $newP = $mapPasta[$t['pasta_id']] ?? null;
    if (!$newP) continue;
    // verifica duplicata por codigo
    $existing = $DB->request(['FROM' => 'glpi_plugin_protocolo_termos', 'WHERE' => ['codigo' => $t['codigo']], 'LIMIT' => 1]);
    $found = null;
    foreach ($existing as $ex) $found = $ex;
    if ($found) continue;
    $usersId = $mapUser[$t['criado_por'] ?? 0] ?? 2;
    $DB->insert('glpi_plugin_protocolo_termos', [
        'plugin_protocolo_pastas_id' => $newP,
        'tipo' => $t['tipo'],
        'codigo' => $t['codigo'],
        'arquivo_assinado' => $t['arquivo_assinado'] ?? null, // caminho antigo uploads/termos/... (copiar arquivos manualmente)
        'hash_verificacao' => $t['hash_verificacao'] ?? null,
        'users_id' => $usersId,
        'date_creation' => $t['criado_em'] ?? date('Y-m-d H:i:s')
    ]);
}
echo "  Termos migrados: " . count($termos) . "\n";

echo "\nMigração concluída!\n";
echo "Lembre de copiar arquivos físicos: cp -r /var/www/protocolo/uploads/termos/* " . GLPI_ROOT . "/files/_plugins/protocolo/termos/\n";
echo "E ajustar permissões: chown -R www-data:www-data " . GLPI_PLUGIN_DOC_DIR . "/protocolo\n";
