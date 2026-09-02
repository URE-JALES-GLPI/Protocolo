<?php
namespace GlpiPlugin\Protocolo;

use CommonDBTM;
use CommonGLPI;
use Html;
use Session;
use Plugin;

class TipoArquivo extends CommonDBTM
{
    public static $rightname = 'plugin_protocolo_tipo';

    public static function getTypeName($nb = 0)
    {
        return $nb == 1 ? __('Tipo de Arquivo', 'protocolo') : __('Tipos de Arquivo', 'protocolo');
    }

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_protocolo_tipos';
    }

    public static function getIcon()
    {
        return 'ti ti-tags';
    }

    private static function hasRightDB(string $right, int $level): bool
    {
        if (\GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', $level)) {
            return true;
        }
        // Admin também pode gerenciar tipos
        if (\GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_admin', $level)) {
            return true;
        }
        global $DB;
        $pid = (int)($_SESSION['glpiactive_profile']['id'] ?? 0);
        if ($pid && isset($DB) && $DB->tableExists('glpi_profilerights')) {
            try {
                $it = $DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $pid, 'name' => $right]]);
                foreach ($it as $row) {
                    $dbRights = (int)$row['rights'];
                    if ($level === READ && $dbRights > 0) return true;
                    if (($dbRights & $level) === $level) return true;
                }
                return false;
            } catch (\Throwable $e) {}
        }
        return Session::haveRight($right, $level) || Session::haveRight('plugin_protocolo_use', $level);
    }

    public static function canView(): bool
    {
        return self::hasRightDB(self::$rightname, READ) || \GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', READ) || \GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_admin', READ);
    }

    public static function canCreate(): bool
    {
        return self::hasRightDB(self::$rightname, CREATE) || \GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', CREATE);
    }

    public function canViewItem(): bool { return self::canView(); }
    public function canCreateItem(): bool { return self::canCreate(); }
    public function canUpdateItem(): bool { return self::hasRightDB(self::$rightname, UPDATE); }
    public function canDeleteItem(): bool { return self::hasRightDB(self::$rightname, DELETE); }
    public function canPurgeItem(): bool { return self::hasRightDB(self::$rightname, PURGE); }

    public static function getNameField() { return 'name'; }

    public function rawSearchOptions()
    {
        $tab = [];
        $tab[] = ['id' => 'common', 'name' => __('Tipo', 'protocolo')];
        $tab[] = ['id' => 1, 'table' => self::getTable(), 'field' => 'name', 'name' => __('Nome'), 'datatype' => 'string', 'massiveaction' => false];
        $tab[] = ['id' => 2, 'table' => self::getTable(), 'field' => 'comment', 'name' => __('Descrição'), 'datatype' => 'text'];
        $tab[] = ['id' => 3, 'table' => self::getTable(), 'field' => 'is_active', 'name' => __('Ativo'), 'datatype' => 'bool'];
        $tab[] = ['id' => 16, 'table' => self::getTable(), 'field' => 'date_creation', 'name' => __('Criação'), 'datatype' => 'datetime'];
        return $tab;
    }

    public function showForm($ID, array $options = [])
    {
        if (!self::canView()) { return false; }
        $this->initForm($ID, $options);
        $this->showFormHeader($options);
        echo "<tr class='tab_bg_1'>";
        echo "<td><label>" . __('Nome') . " <span class='required'>*</span></label></td>";
        echo "<td><input type='text' name='name' value='" . Html::cleanInputText($this->fields['name'] ?? '') . "' class='form-control' required></td>";
        echo "<td><label>" . __('Ativo') . "</label></td>";
        echo "<td>";
        \Dropdown::showYesNo('is_active', $this->fields['is_active'] ?? 1);
        echo "</td>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td><label>" . __('Descrição') . "</label></td>";
        echo "<td colspan='3'><input type='text' name='comment' value='" . Html::cleanInputText($this->fields['comment'] ?? '') . "' class='form-control' placeholder='Opcional'></td>";
        echo "</tr>";
        $this->showFormButtons($options);
        return true;
    }

    public function prepareInputForAdd($input)
    {
        if (empty($input['name'])) {
            Session::addMessageAfterRedirect(__('Nome é obrigatório', 'protocolo'), false, ERROR);
            return false;
        }
        return $input;
    }

    private static function getProtocoloWebDir(): string
    {
        $web = Plugin::getWebDir('protocolo');
        if ($web === '' || $web === null) {
            $web = '/plugins/protocolo';
        }
        return $web;
    }

    public static function getFormURL($full = true)
    {
        $web = self::getProtocoloWebDir();
        $root = $GLOBALS['CFG_GLPI']['root_doc'] ?? '';
        if ($full && $root !== '' && !str_starts_with($web, $root) && str_starts_with($web, '/')) {
            return $root . $web . '/front/tipo.form.php';
        }
        if (!$full && $root !== '' && str_starts_with($web, $root)) {
            return substr($web, strlen($root)) . '/front/tipo.form.php';
        }
        return $web . '/front/tipo.form.php';
    }

    public static function getSearchURL($full = true)
    {
        $web = self::getProtocoloWebDir();
        $root = $GLOBALS['CFG_GLPI']['root_doc'] ?? '';
        if ($full && $root !== '' && !str_starts_with($web, $root) && str_starts_with($web, '/')) {
            return $root . $web . '/front/tipo.php';
        }
        if (!$full && $root !== '' && str_starts_with($web, $root)) {
            return substr($web, strlen($root)) . '/front/tipo.php';
        }
        return $web . '/front/tipo.php';
    }

    // Para popular checkboxes em Pasta form
    public static function getAllActive(): array
    {
        global $DB;
        $rows = [];
        $it = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['is_active' => 1], 'ORDER' => 'name']);
        foreach ($it as $r) { $rows[] = $r; }
        return $rows;
    }
}
