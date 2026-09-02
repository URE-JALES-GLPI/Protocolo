<?php
namespace GlpiPlugin\Protocolo;

use CommonDBTM;
use CommonGLPI;
use Session;
use Plugin;

class Termo extends CommonDBTM
{
    public static $rightname = 'plugin_protocolo_pasta';

    public static function getTypeName($nb = 0)
    {
        return __('Termo', 'protocolo');
    }

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_protocolo_termos';
    }

    private static function hasRightDB(int $level): bool
    {
        if (\GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', $level)) return true;
        global $DB;
        $pid = (int)($_SESSION['glpiactive_profile']['id'] ?? 0);
        if ($pid && isset($DB) && $DB->tableExists('glpi_profilerights')) {
            try {
                $it = $DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $pid, 'name' => self::$rightname]]);
                foreach ($it as $row) {
                    $dbRights = (int)$row['rights'];
                    if ($level === READ && $dbRights > 0) return true;
                    return ($dbRights & $level) === $level;
                }
                return false;
            } catch (\Throwable $e) {}
        }
        return Session::haveRight(self::$rightname, $level) || \GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', $level);
    }

    public static function canView(): bool
    {
        return self::hasRightDB(READ);
    }

    public static function canCreate(): bool
    {
        return self::hasRightDB(CREATE);
    }

    public function canViewItem(): bool { return self::canView(); }
    public function canCreateItem(): bool { return self::canCreate(); }
    public function canUpdateItem(): bool { return self::hasRightDB(UPDATE); }
    public function canDeleteItem(): bool { return self::hasRightDB(DELETE); }

    public function rawSearchOptions()
    {
        $tab = [];
        $tab[] = ['id' => 'common', 'name' => __('Termo', 'protocolo')];
        $tab[] = ['id' => 1, 'table' => self::getTable(), 'field' => 'codigo', 'name' => __('Código', 'protocolo'), 'datatype' => 'string'];
        $tab[] = ['id' => 2, 'table' => self::getTable(), 'field' => 'tipo', 'name' => __('Tipo'), 'datatype' => 'specific'];
        $tab[] = ['id' => 3, 'table' => self::getTable(), 'field' => 'plugin_protocolo_pastas_id', 'name' => __('Pasta', 'protocolo'), 'datatype' => 'itemlink'];
        $tab[] = ['id' => 4, 'table' => self::getTable(), 'field' => 'arquivo_assinado', 'name' => __('Assinado', 'protocolo'), 'datatype' => 'string'];
        $tab[] = ['id' => 16, 'table' => self::getTable(), 'field' => 'date_creation', 'name' => __('Criação'), 'datatype' => 'datetime'];
        return $tab;
    }

    // Busca termos de uma pasta
    public static function getForPasta(int $pastas_id): array
    {
        global $DB;
        $rows = [];
        $it = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['plugin_protocolo_pastas_id' => $pastas_id], 'ORDER' => 'date_creation']);
        foreach ($it as $r) { $rows[] = $r; }
        return $rows;
    }

    public static function getByIdAndPasta(int $termId, int $pastaId): ?array
    {
        global $DB;
        $it = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['id' => $termId, 'plugin_protocolo_pastas_id' => $pastaId], 'LIMIT' => 1]);
        foreach ($it as $r) { return $r; }
        return null;
    }

    // Gera ou recupera termo
    public static function getOrCreate(int $pastaId, string $tipo): array
    {
        global $DB;
        $it = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['plugin_protocolo_pastas_id' => $pastaId, 'tipo' => $tipo], 'ORDER' => 'id DESC', 'LIMIT' => 1]);
        foreach ($it as $r) { return $r; }
        // cria
        $codigo = Install::gerarCodigoTermo($tipo);
        $hash = bin2hex(random_bytes(16));
        $DB->insert(self::getTable(), [
            'plugin_protocolo_pastas_id' => $pastaId,
            'tipo' => $tipo,
            'codigo' => $codigo,
            'hash_verificacao' => $hash,
            'users_id' => Session::getLoginUserID(),
            'date_creation' => date('Y-m-d H:i:s')
        ]);
        $id = $DB->insertId();
        return [
            'id' => $id,
            'plugin_protocolo_pastas_id' => $pastaId,
            'tipo' => $tipo,
            'codigo' => $codigo,
            'hash_verificacao' => $hash,
            'date_creation' => date('Y-m-d H:i:s'),
            'arquivo_assinado' => null
        ];
    }

    public static function getFormURL($full = true)
    {
        $web = Plugin::getWebDir('protocolo');
        if ($web === '' || $web === null) $web = '/plugins/protocolo';
        $root = $GLOBALS['CFG_GLPI']['root_doc'] ?? '';
        // Se web já contém root, não duplica
        if ($root !== '' && str_starts_with($web, $root)) {
            $webPath = $web . '/front/termo.form.php';
            if (!$full) return substr($webPath, strlen($root));
            return $webPath;
        }
        $dir = $full ? $root : '';
        return $dir . $web . '/front/termo.form.php';
    }
}
