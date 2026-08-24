<?php
namespace GlpiPlugin\Protocolo;

use CommonDBTM;
use CommonGLPI;
use Html;
use Session;
use Dropdown;
use Search;
use Plugin;

class Escola extends CommonDBTM
{
    public static $rightname = 'plugin_protocolo_escola';

    public static function getTypeName($nb = 0)
    {
        return $nb == 1 ? __('Escola', 'protocolo') : __('Escolas', 'protocolo');
    }

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_protocolo_escolas';
    }

    public static function getIcon()
    {
        return 'ti ti-building';
    }

    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ) || Session::haveRight('plugin_protocolo_pasta', READ);
    }

    public static function canCreate(): bool
    {
        return Session::haveRight(self::$rightname, CREATE);
    }

    public function canViewItem(): bool
    {
        return self::canView();
    }

    public function canCreateItem(): bool
    {
        return self::canCreate();
    }

    public function canUpdateItem(): bool
    {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    public function canDeleteItem(): bool
    {
        return Session::haveRight(self::$rightname, DELETE);
    }

    public function canPurgeItem(): bool
    {
        return Session::haveRight(self::$rightname, PURGE);
    }

    public function getNameField()
    {
        return 'name';
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id' => 'common',
            'name' => __('Escola', 'protocolo')
        ];

        $tab[] = [
            'id' => 1,
            'table' => self::getTable(),
            'field' => 'name',
            'name' => __('Nome', 'protocolo'),
            'datatype' => 'itemlink',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => 2,
            'table' => self::getTable(),
            'field' => 'codigo',
            'name' => __('Código', 'protocolo'),
            'datatype' => 'string'
        ];

        $tab[] = [
            'id' => 3,
            'table' => self::getTable(),
            'field' => 'email',
            'name' => __('E-mail', 'protocolo'),
            'datatype' => 'string'
        ];

        $tab[] = [
            'id' => 4,
            'table' => self::getTable(),
            'field' => 'phone',
            'name' => __('Telefone', 'protocolo'),
            'datatype' => 'string'
        ];

        $tab[] = [
            'id' => 5,
            'table' => self::getTable(),
            'field' => 'responsavel',
            'name' => __('Responsável', 'protocolo'),
            'datatype' => 'string'
        ];

        $tab[] = [
            'id' => 6,
            'table' => self::getTable(),
            'field' => 'is_active',
            'name' => __('Ativo', 'protocolo'),
            'datatype' => 'bool'
        ];

        $tab[] = [
            'id' => 7,
            'table' => self::getTable(),
            'field' => 'address',
            'name' => __('Endereço', 'protocolo'),
            'datatype' => 'text'
        ];

        $tab[] = [
            'id' => 16,
            'table' => self::getTable(),
            'field' => 'date_creation',
            'name' => __('Criação', 'protocolo'),
            'datatype' => 'datetime'
        ];

        return $tab;
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(Pasta::class, $ong, $options);
        $this->addStandardTab('Log', $ong, $options);
        return $ong;
    }

    public function showForm($ID, array $options = [])
    {
        if (!self::canView()) {
            return false;
        }

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='name'>" . __('Nome') . " <span class='required'>*</span></label></td>";
        echo "<td><input type='text' name='name' id='name' value='" . Html::cleanInputText($this->fields['name'] ?? '') . "' class='form-control' required style='width:100%'></td>";
        echo "<td><label for='codigo'>" . __('Código', 'protocolo') . "</label></td>";
        echo "<td><input type='text' name='codigo' id='codigo' value='" . Html::cleanInputText($this->fields['codigo'] ?? '') . "' class='form-control' placeholder='ESC001'></td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='responsavel'>" . __('Responsável', 'protocolo') . "</label></td>";
        echo "<td><input type='text' name='responsavel' id='responsavel' value='" . Html::cleanInputText($this->fields['responsavel'] ?? '') . "' class='form-control'></td>";
        echo "<td><label for='is_active'>" . __('Ativo', 'protocolo') . "</label></td>";
        echo "<td>";
        Dropdown::showYesNo('is_active', $this->fields['is_active'] ?? 1);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='email'>" . __('E-mail', 'protocolo') . "</label></td>";
        echo "<td><input type='email' name='email' id='email' value='" . Html::cleanInputText($this->fields['email'] ?? '') . "' class='form-control'></td>";
        echo "<td><label for='phone'>" . __('Telefone') . "</label></td>";
        echo "<td><input type='text' name='phone' id='phone' value='" . Html::cleanInputText($this->fields['phone'] ?? '') . "' class='form-control'></td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='address'>" . __('Endereço') . "</label></td>";
        echo "<td colspan='3'><textarea name='address' id='address' class='form-control' rows='2'>" . Html::cleanInputText($this->fields['address'] ?? '') . "</textarea></td>";
        echo "</tr>";

        // Se estiver visualizando, mostra link para pastas filtradas
        if ($ID > 0) {
            echo "<tr class='tab_bg_1'><td colspan='4' class='center'>";
            echo "<a class='btn btn-sm btn-outline-primary' href='" . Pasta::getSearchURL() . "?criteria[0][field]=4&criteria[0][searchtype]=equals&criteria[0][value]=$ID'>";
            echo "<i class='ti ti-folder'></i> " . __('Ver pastas desta escola', 'protocolo') . "</a>";
            echo "</td></tr>";
        }

        $this->showFormButtons($options);
        return true;
    }

    public function prepareInputForAdd($input)
    {
        if (empty($input['name'])) {
            Session::addMessageAfterRedirect(__('Nome da escola é obrigatório', 'protocolo'), false, ERROR);
            return false;
        }
        $input['entities_id'] = $_SESSION['glpiactive_entity'] ?? 0;
        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        if (isset($input['name']) && empty($input['name'])) {
            Session::addMessageAfterRedirect(__('Nome da escola é obrigatório', 'protocolo'), false, ERROR);
            return false;
        }
        return $input;
    }

    // Helper para dropdown em formulários de pasta
    public static function dropdown(array $options = []): int
    {
        $options['name'] = $options['name'] ?? 'plugin_protocolo_escolas_id';
        $options['entity'] = $options['entity'] ?? ($_SESSION['glpiactive_entity'] ?? 0);
        return Dropdown::show(self::class, $options);
    }

    public static function getSearchURL($full = true)
    {
        global $CFG_GLPI;
        $dir = $full ? $CFG_GLPI['root_doc'] : '';
        return $dir . '/plugins/protocolo/front/escola.php';
    }

    public static function getFormURL($full = true)
    {
        global $CFG_GLPI;
        $dir = $full ? $CFG_GLPI['root_doc'] : '';
        return $dir . '/plugins/protocolo/front/escola.form.php';
    }
}
