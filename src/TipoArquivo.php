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
        global $DB;
        $pid = (int)($_SESSION['glpiactive_profile']['id'] ?? 0);
        if ($pid && isset($DB) && $DB->tableExists('glpi_profilerights')) {
            try {
                $it = $DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $pid, 'name' => $right]]);
                foreach ($it as $row) {
                    $dbRights = (int)$row['rights'];
                    $hasDb = ($dbRights & $level) === $level;
                    $hasSess = Session::haveRight($right, $level);
                    if ($hasSess !== $hasDb) {
                        error_log("[protocolo] Tipo hasRightDB MISMATCH pid=$pid right=$right level=$level db=$dbRights");
                        if (!$hasDb && $hasSess) {
                            $_SESSION['glpiactive_profile'][$right] = $dbRights;
                            $_SESSION['glpiactiveprofile'][$right] = $dbRights;
                        }
                    }
                    return $hasDb;
                }
                return false;
            } catch (\Throwable $e) {}
        }
        return Session::haveRight($right, $level);
    }

    public static function canView(): bool
    {
        return self::hasRightDB(self::$rightname, READ) || self::hasRightDB('plugin_protocolo_config', READ);
    }

    public static function canCreate(): bool
    {
        return self::hasRightDB(self::$rightname, CREATE);
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

    public static function getFormURL($full = true)
    {
        $dir = $full ? ($GLOBALS['CFG_GLPI']['root_doc'] ?? '') : '';
        return $dir . Plugin::getWebDir('protocolo') . '/front/tipo.form.php';
    }

    public static function getSearchURL($full = true)
    {
        $dir = $full ? ($GLOBALS['CFG_GLPI']['root_doc'] ?? '') : '';
        return $dir . Plugin::getWebDir('protocolo') . '/front/tipo.php';
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
