<?php
namespace GlpiPlugin\Protocolo;

use CommonDBTM;
use CommonGLPI;
use Entity;
use Html;
use Session;
use Dropdown;
use Search;
use MassiveAction;
use Plugin;

class Pasta extends CommonDBTM
{
    // Direito único do plugin (ver Profile::getRights). Usa 31 (todos os bits)
    // para que o núcleo do GLPI (Session::haveRight) também passe em CREATE/UPDATE.
    public static $rightname = 'plugin_protocolo_use';

    // Registra criação/alterações em glpi_logs → aba "Histórico"/Log da ficha
    // (quem mudou, quando, campo, valor antigo → novo).
    // Sem tipo de propósito: o CommonDBTM desta versão declara sem tipo.
    public $dohistory = true;

    public function isEntityAssign()
    {
        return true;
    }

    public function maybeRecursive()
    {
        return true;
    }

    public function maybeDeleted()
    {
        return true;
    }

    // Para GLPI menu icon
    public static function getTypeName($nb = 0)
    {
        return $nb == 1 ? __('Pasta', 'protocolo') : __('Pastas', 'protocolo');
    }

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_protocolo_pastas';
    }

    public static function getIcon()
    {
        return 'ti ti-folder';
    }

    public static function getMenuContent()
    {
        if (!self::canView()) {
            return [];
        }
        $menu = [];
        $menu['title'] = __('Protocolo', 'protocolo');
        $menu['page']  = '/plugins/protocolo/front/dashboard.php';
        $menu['icon']  = self::getIcon();
        $menu['options']['dashboard']['title'] = __('Dashboard', 'protocolo');
        $menu['options']['dashboard']['page']  = '/plugins/protocolo/front/dashboard.php';
        $menu['options']['dashboard']['icon']  = 'ti ti-dashboard';
        $menu['options']['pasta']['title'] = self::getTypeName(2);
        $menu['options']['pasta']['page']  = self::getSearchURL(false);
        $menu['options']['pasta']['icon']  = self::getIcon();
        $menu['options']['escola']['title'] = \GlpiPlugin\Protocolo\Escola::getTypeName(2);
        $menu['options']['escola']['page']  = \GlpiPlugin\Protocolo\Escola::getSearchURL(false);
        $menu['options']['escola']['icon']  = \GlpiPlugin\Protocolo\Escola::getIcon();
        $menu['options']['tipo']['title'] = \GlpiPlugin\Protocolo\TipoArquivo::getTypeName(2);
        $menu['options']['tipo']['page']  = \GlpiPlugin\Protocolo\TipoArquivo::getSearchURL(false);
        $menu['options']['tipo']['icon']  = \GlpiPlugin\Protocolo\TipoArquivo::getIcon();
        return $menu;
    }

    private static function hasRightDB(int $level): bool
    {
        // Modelo 2.0: avaliador único (linhas novas plugin_protocolo_use).
        return \GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', $level);
    }

    public static function canView(): bool
    {
        return self::hasRightDB(READ);
    }

    public static function canCreate(): bool
    {
        return self::hasRightDB(CREATE);
    }

    // Sobrescreve estáticos do núcleo para usar o banco (sem depender de
    // sessão recarregada). Sem isto, check($id, UPDATE/DELETE/PURGE) usaria
    // Session::haveRight com sessão antiga (valor 1) e daria 403.
    public static function canUpdate(): bool
    {
        return self::hasRightDB(UPDATE);
    }

    public static function canDelete(): bool
    {
        return self::hasRightDB(DELETE);
    }

    public static function canPurge(): bool
    {
        return self::hasRightDB(PURGE);
    }

    private function hasRight(int $level): bool
    {
        return self::hasRightDB($level);
    }

    public function canViewItem(): bool
    {
        if (!self::hasRightDB(READ)) return false;
        if ($this->isEntityAssign() && isset($this->fields['entities_id'])) {
            $ent = (int)$this->fields['entities_id'];
            $rec = (int)($this->fields['is_recursive'] ?? 0);
            if (method_exists(Session::class, 'haveAccessToEntity')) {
                if (!Session::haveAccessToEntity($ent, $rec)) return false;
            }
        }
        return true;
    }

    public function canCreateItem(): bool { return self::canCreate(); }
    public function canUpdateItem(): bool { return self::hasRightDB(UPDATE); }
    public function canDeleteItem(): bool { return self::hasRightDB(DELETE); }
    public function canPurgeItem(): bool { return self::hasRightDB(PURGE); }

    public static function getNameField()
    {
        return 'codigo';
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = ['id' => 'common', 'name' => __('Pasta', 'protocolo')];

        $tab[] = [
            'id' => 1,
            'table' => self::getTable(),
            'field' => 'codigo',
            'name' => __('Código', 'protocolo'),
            'datatype' => 'itemlink',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => 2,
            'table' => self::getTable(),
            'field' => 'status',
            'name' => __('Status', 'protocolo'),
            'datatype' => 'specific',
            'searchtype' => ['equals', 'notequals']
        ];

        $tab[] = [
            'id' => 3,
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __('Escola (Entidade)', 'protocolo'),
            'datatype' => 'dropdown',
            'linkfield' => 'plugin_protocolo_escolas_id',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => 4,
            'table' => self::getTable(),
            'field' => 'plugin_protocolo_escolas_id',
            'name' => __('Escola ID', 'protocolo'),
            'datatype' => 'integer',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => 5,
            'table' => self::getTable(),
            'field' => 'data_recebimento',
            'name' => __('Data Recebimento', 'protocolo'),
            'datatype' => 'datetime'
        ];

        $tab[] = [
            'id' => 6,
            'table' => self::getTable(),
            'field' => 'recebido_de',
            'name' => __('Recebido de', 'protocolo'),
            'datatype' => 'string'
        ];

        $tab[] = [
            'id' => 7,
            'table' => self::getTable(),
            'field' => 'data_retirada',
            'name' => __('Data Retirada', 'protocolo'),
            'datatype' => 'datetime'
        ];

        $tab[] = [
            'id' => 8,
            'table' => self::getTable(),
            'field' => 'retirado_por',
            'name' => __('Retirado por', 'protocolo'),
            'datatype' => 'string'
        ];

        $tab[] = [
            'id' => 9,
            'table' => self::getTable(),
            'field' => 'recebido_documento',
            'name' => __('Documento (Recebimento)', 'protocolo'),
            'datatype' => 'string'
        ];

        $tab[] = [
            'id' => 10,
            'table' => self::getTable(),
            'field' => 'recebido_documento_tipo',
            'name' => __('Tipo Doc. Recebimento', 'protocolo'),
            'datatype' => 'specific'
        ];

        $tab[] = [
            'id' => 11,
            'table' => self::getTable(),
            'field' => 'retirado_documento',
            'name' => __('Documento (Retirada)', 'protocolo'),
            'datatype' => 'string'
        ];

        $tab[] = [
            'id' => 12,
            'table' => self::getTable(),
            'field' => 'retirado_documento_tipo',
            'name' => __('Tipo Doc. Retirada', 'protocolo'),
            'datatype' => 'specific'
        ];

        $tab[] = [
            'id' => 13,
            'table' => self::getTable(),
            'field' => 'categoria',
            'name' => __('Espécie', 'protocolo'),
            'datatype' => 'specific',
            'searchtype' => ['equals', 'notequals']
        ];

        $tab[] = [
            'id' => 21,
            'table' => self::getTable(),
            'field' => 'assunto',
            'name' => __('Assunto', 'protocolo'),
            'datatype' => 'string'
        ];

        $tab[] = [
            'id' => 14,
            'table' => self::getTable(),
            'field' => 'origem_tipo',
            'name' => __('Origem Tipo', 'protocolo'),
            'datatype' => 'specific',
            'searchtype' => ['equals', 'notequals']
        ];

        $tab[] = [
            'id' => 15,
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __('Origem (Escola)', 'protocolo'),
            'datatype' => 'dropdown',
            'linkfield' => 'origem_entities_id',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => 17,
            'table' => self::getTable(),
            'field' => 'destino_tipo',
            'name' => __('Destino Tipo', 'protocolo'),
            'datatype' => 'specific',
            'searchtype' => ['equals', 'notequals']
        ];

        $tab[] = [
            'id' => 18,
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __('Destino (Escola)', 'protocolo'),
            'datatype' => 'dropdown',
            'linkfield' => 'destino_entities_id',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => 16,
            'table' => self::getTable(),
            'field' => 'date_creation',
            'name' => __('Criação', 'protocolo'),
            'datatype' => 'datetime'
        ];

        $tab[] = [
            'id' => 19,
            'table' => self::getTable(),
            'field' => 'date_mod',
            'name' => __('Atualização', 'protocolo'),
            'datatype' => 'datetime'
        ];

        $tab[] = [
            'id' => 80,
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __('Entity'),
            'datatype' => 'dropdown'
        ];

        $tab[] = [
            'id' => 86,
            'table' => self::getTable(),
            'field' => 'is_recursive',
            'name' => __('Child entities'),
            'datatype' => 'bool'
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if ($field === 'status') {
            return self::getStatusBadge($values[$field] ?? '');
        }
        if ($field === 'categoria') {
            return self::getCategoriaBadge($values[$field] ?? 'pasta', $values['especie_outro'] ?? null);
        }
        if (in_array($field, ['origem_tipo', 'destino_tipo'])) {
            $map = ['outro' => 'Outro', 'ure' => 'URE', 'escola' => 'Escola'];
            return $map[$values[$field] ?? ''] ?? htmlspecialchars($values[$field] ?? '');
        }
        if (in_array($field, ['recebido_documento_tipo', 'retirado_documento_tipo'])) {
            $v = strtolower($values[$field] ?? 'cpf');
            return $v === 'rg' ? 'RG' : 'CPF';
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Espécies disponíveis (substitui a antiga Categoria Pasta/Malote).
     * @return array valor => rótulo
     */
    public static function getEspecieOptions(): array
    {
        return [
            'pasta'    => __('Pasta', 'protocolo'),
            'malote'   => __('Malote', 'protocolo'),
            'envelope' => __('Envelope', 'protocolo'),
            'caixa'    => __('Caixa', 'protocolo'),
            'outro'    => __('Outros', 'protocolo'),
        ];
    }

    public static function getEspecieLabel(string $cat, ?string $outro = null): string
    {
        $cat = strtolower(trim($cat));
        $opts = self::getEspecieOptions();
        if ($cat === 'outro') {
            $outro = trim((string)$outro);
            return $outro !== '' ? $outro : ($opts['outro'] ?? 'Outros');
        }
        return $opts[$cat] ?? ucfirst($cat);
    }

    /** Artigo definido para a espécie (para os textos do termo). */
    public static function getEspecieArtigo(string $cat): string
    {
        return match (strtolower(trim($cat))) {
            'malote', 'envelope' => 'o',
            default => 'a',
        };
    }

    public static function getCategoriaBadge(string $cat, ?string $outro = null): string
    {
        // Identidade visual padronizada com assetmgrstatus (pt-badge-*)
        $cat = strtolower(trim($cat));
        $label = self::getEspecieLabel($cat, $outro);
        if (mb_strlen($label) > 40) {
            $label = mb_substr($label, 0, 37) . '...';
        }
        $cls = match ($cat) {
            'malote' => 'pt-badge-malote',
            'envelope' => 'pt-badge-info',
            'caixa' => 'pt-badge-ure',
            'outro' => 'pt-badge-outro',
            default => 'pt-badge-pasta',
        };
        $title = htmlspecialchars(self::getEspecieLabel($cat, $outro));
        return '<span class="pt-badge ' . $cls . '" title="' . $title . '">' . htmlspecialchars($label) . '</span>';
    }

    public static function getOrigemDestinoDisplay(array $fields, string $prefix): string
    {
        $tipo = $fields[$prefix . '_tipo'] ?? 'escola';
        $outro = $fields[$prefix . '_outro'] ?? '';
        $entId = (int)($fields[$prefix . '_entities_id'] ?? 0);
        if ($tipo === 'outro') {
            return htmlspecialchars($outro ?: 'Outro');
        }
        if ($tipo === 'ure') {
            return 'URE';
        }
        // escola
        $name = $entId ? self::getEscolaName($entId) : '—';
        return htmlspecialchars($name);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if ($field === 'status') {
            $options['value'] = $values;
            $options['name'] = $name;
            $options['display'] = false;
            return Dropdown::showFromArray($name, [
                'aguardando' => __('Aguardando retirada', 'protocolo'),
                'retirada'   => __('Retirada', 'protocolo'),
                'cancelada'  => __('Cancelada', 'protocolo'),
            ], $options);
        }
        if ($field === 'categoria') {
            $options['value'] = $values;
            $options['name'] = $name;
            $options['display'] = false;
            return Dropdown::showFromArray($name, self::getEspecieOptions(), $options);
        }
        if (in_array($field, ['origem_tipo', 'destino_tipo'])) {
            $options['value'] = $values;
            $options['name'] = $name;
            $options['display'] = false;
            return Dropdown::showFromArray($name, [
                'outro' => __('Outro', 'protocolo'),
                'ure' => __('URE', 'protocolo'),
                'escola' => __('Escola', 'protocolo'),
            ], $options);
        }
        if (in_array($field, ['recebido_documento_tipo', 'retirado_documento_tipo'])) {
            $options['value'] = $values;
            $options['name'] = $name;
            $options['display'] = false;
            return Dropdown::showFromArray($name, [
                'cpf' => 'CPF',
                'rg'  => 'RG',
            ], $options);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public static function getStatusBadge(string $status): string
    {
        // Identidade visual padronizada com assetmgrstatus (pt-badge-*)
        $map = [
            'aguardando' => '<span class="pt-badge pt-badge-aguardando">' . __('Aguardando retirada', 'protocolo') . '</span>',
            'retirada'   => '<span class="pt-badge pt-badge-retirada">' . __('Retirada', 'protocolo') . '</span>',
            'cancelada'  => '<span class="pt-badge pt-badge-cancelada">' . __('Cancelada', 'protocolo') . '</span>',
        ];
        return $map[$status] ?? htmlspecialchars($status);
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(__CLASS__, $ong, $options); // itens/termos
        $this->addStandardTab('Log', $ong, $options);
        return $ong;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        // Se não tem leitura, nem mostra aba (evita aba vazia com erro)
        if (!self::canView()) {
            return '';
        }
        if ($item instanceof self) {
            $nb = 0;
            if ($_SESSION['glpishow_count_on_tabs'] ?? false) {
                global $DB;
                $nbItens = countElementsInTable('glpi_plugin_protocolo_itens', ['plugin_protocolo_pastas_id' => $item->getID()]);
                $nbTermos = countElementsInTable('glpi_plugin_protocolo_termos', ['plugin_protocolo_pastas_id' => $item->getID()]);
                $nb = $nbItens + $nbTermos;
            }
            return self::createTabEntry(__('Itens & Termos', 'protocolo'), $nb);
        }
        if ($item instanceof Escola) {
            $count = 0;
            if ($_SESSION['glpishow_count_on_tabs'] ?? false) {
                $count = countElementsInTable(self::getTable(), ['plugin_protocolo_escolas_id' => $item->getID()]);
            }
            return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $count);
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof self) {
            self::showItensTermosTab($item);
            return true;
        }
        if ($item instanceof Escola) {
            self::showForEscola($item);
            return true;
        }
        return false;
    }

    public static function showForEscola(Escola $escola): void
    {
        global $DB;
        $escolaId = $escola->getID();
        echo "<div class='spaced'>";
        Search::show(self::class);
        // Fallback simples: lista
        $iterator = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['plugin_protocolo_escolas_id' => $escolaId], 'ORDER' => 'id DESC', 'LIMIT' => 50]);
        echo "<table class='tab_cadre_fixe'><tr><th>" . __('Código') . "</th><th>" . __('Status') . "</th><th>" . __('Recebido de') . "</th><th>" . __('Data') . "</th></tr>";
        foreach ($iterator as $row) {
            echo "<tr class='tab_bg_1'><td><a href='" . self::getFormURLWithID($row['id']) . "'>" . htmlspecialchars($row['codigo']) . "</a></td>";
            echo "<td>" . self::getStatusBadge($row['status']) . "</td>";
            echo "<td>" . htmlspecialchars($row['recebido_de']) . "</td>";
            echo "<td>" . Html::convDateTime($row['data_recebimento']) . "</td></tr>";
        }
        echo "</table></div>";
    }

    public static function showItensTermosTab(self $pasta): void
    {
        global $DB;
        $id = $pasta->getID();
        // Itens
        $itens = $DB->request(['FROM' => 'glpi_plugin_protocolo_itens', 'WHERE' => ['plugin_protocolo_pastas_id' => $id], 'ORDER' => 'id']);
        echo "<div class='spaced'><h3><i class='ti ti-list'></i> " . __('Itens da Pasta', 'protocolo') . " (" . count($itens) . ")</h3>";
        echo "<table class='tab_cadre_fixe'><tr><th>#</th><th>" . __('Descrição') . "</th><th>" . __('Qtd') . "</th><th>" . __('Obs', 'protocolo') . "</th></tr>";
        $i = 1;
        foreach ($itens as $it) {
            echo "<tr class='tab_bg_1'><td>$i</td><td>" . htmlspecialchars($it['name']) . "</td><td>" . (int)$it['quantidade'] . "</td><td>" . htmlspecialchars($it['comment'] ?? '') . "</td></tr>";
            $i++;
        }
        if ($i === 1) echo "<tr><td colspan='4' class='center text-muted'>" . __('Nenhum item', 'protocolo') . "</td></tr>";
        echo "</table></div>";

        // Tipos
        $tipos = $DB->request([
            'SELECT' => ['t.name'],
            'FROM' => 'glpi_plugin_protocolo_pastatipos as pt',
            'LEFT JOIN' => ['glpi_plugin_protocolo_tipos as t' => ['FKEY' => ['pt' => 'plugin_protocolo_tipos_id', 't' => 'id']]],
            'WHERE' => ['pt.plugin_protocolo_pastas_id' => $id]
        ]);
        $nomes = [];
        foreach ($tipos as $t) { $nomes[] = $t['name']; }
        echo "<div class='spaced'><h3><i class='ti ti-tags'></i> " . __('Tipos de Arquivo', 'protocolo') . "</h3>";
        if ($nomes) {
            foreach ($nomes as $n) echo "<span class='badge bg-warning text-dark me-1'><i class='ti ti-check'></i> " . htmlspecialchars($n) . "</span>";
        } else {
            echo "<span class='text-muted'>" . __('Nenhum tipo marcado', 'protocolo') . "</span>";
        }
        echo "</div>";

        // Termos
        $termos = Termo::getForPasta($id);
        echo "<div class='spaced'><h3><i class='ti ti-file-text'></i> " . __('Termos', 'protocolo') . "</h3>";
        if (!$termos) echo "<p class='text-muted'>" . __('Nenhum termo gerado', 'protocolo') . "</p>";
        foreach ($termos as $t) {
            $badge = $t['tipo'] === 'recebimento' ? "<span class='badge bg-primary'>RECEBIMENTO</span>" : "<span class='badge bg-success'>RETIRADA</span>";
            $sigOk = ($t['tipo'] === 'recebimento') ? !empty($this->fields['recebido_assinatura_image']) : !empty($this->fields['retirada_assinatura_image']);
            $assinado = $sigOk ? "<span class='badge bg-success'><i class='ti ti-signature'></i> Assinado digitalmente</span>" : "<span class='badge bg-secondary'>Sem assinatura digital</span>";
            $imprimirUrl = Plugin::getWebDir('protocolo') . "/front/termo.php?id=$id&tipo=" . htmlspecialchars($t['tipo']);
            echo "<div class='border rounded p-3 mb-3'>";
            echo "<div class='d-flex justify-content-between'><div>$badge <code class='ms-2'>" . htmlspecialchars($t['codigo']) . "</code><br><small class='text-muted'>" . Html::convDateTime($t['date_creation']) . " · Hash " . htmlspecialchars(substr($t['hash_verificacao'] ?? '', 0, 12)) . "...</small><br>$assinado</div>";
            echo "<div><a href='$imprimirUrl' target='_blank' class='btn btn-sm btn-outline-primary'><i class='ti ti-printer'></i> Ver/Imprimir</a></div></div>";
            echo "</div>";
        }
        echo "</div>";

        // Timeline - histórico da pasta
        echo "<div class='spaced'><h3><i class='ti ti-history'></i> " . __('Histórico', 'protocolo') . "</h3>";
        echo "<div style='border-left:3px solid #dee2e6; padding-left:22px; margin-left:8px;'>";
        $events = [];
        $events[] = [
            'date' => $pasta->fields['date_creation'] ?? $pasta->fields['data_recebimento'],
            'icon' => 'ti ti-plus',
            'color' => 'bg-primary',
            'title' => __('Pasta criada', 'protocolo') . " — " . htmlspecialchars($pasta->fields['codigo']),
            'desc' => __('Por', 'protocolo') . " " . htmlspecialchars(getUserName($pasta->fields['users_id'] ?? 0)) . " em " . Html::convDateTime($pasta->fields['date_creation'] ?? $pasta->fields['data_recebimento']) . "<br>" . __('Recebido de', 'protocolo') . ": " . htmlspecialchars($pasta->fields['recebido_de'])
        ];
        $events[] = [
            'date' => $pasta->fields['data_recebimento'],
            'icon' => 'ti ti-inbox',
            'color' => 'bg-warning text-dark',
            'title' => __('Recebimento registrado', 'protocolo'),
            'desc' => Html::convDateTime($pasta->fields['data_recebimento']) . " — " . htmlspecialchars($pasta->fields['recebido_de']) . ($pasta->fields['recebido_documento'] ? " (" . htmlspecialchars($pasta->fields['recebido_documento']) . ")" : "")
        ];
        foreach ($termos as $t) {
            $isRec = $t['tipo'] === 'recebimento';
            $sigEv = $isRec ? !empty($pasta->fields['recebido_assinatura_image']) : !empty($pasta->fields['retirada_assinatura_image']);
            $events[] = [
                'date' => $t['date_creation'],
                'icon' => $isRec ? 'ti ti-file-text' : 'ti ti-file-export',
                'color' => $isRec ? 'bg-primary' : 'bg-success',
                'title' => ($isRec ? __('Termo de Recebimento gerado', 'protocolo') : __('Termo de Retirada gerado', 'protocolo')) . " <code>" . htmlspecialchars($t['codigo']) . "</code>",
                'desc' => Html::convDateTime($t['date_creation']) . " por " . htmlspecialchars(getUserName($t['users_id'] ?? 0)) . ($sigEv ? "<br><span class='badge bg-success'><i class='ti ti-signature'></i> Assinado digitalmente</span>" : '')
            ];
        }
        if (!empty($pasta->fields['data_retirada'])) {
            $events[] = [
                'date' => $pasta->fields['data_retirada'],
                'icon' => 'ti ti-logout',
                'color' => 'bg-success',
                'title' => __('Retirada registrada', 'protocolo'),
                'desc' => Html::convDateTime($pasta->fields['data_retirada']) . " — " . htmlspecialchars($pasta->fields['retirado_por'] ?? '') . ($pasta->fields['retirado_documento'] ? " (" . htmlspecialchars($pasta->fields['retirado_documento']) . ")" : "") . ( $pasta->fields['observacao_retirada'] ? "<br><em>" . htmlspecialchars($pasta->fields['observacao_retirada']) . "</em>" : "")
            ];
        }
        if ($pasta->fields['status'] === 'cancelada') {
            $events[] = [
                'date' => $pasta->fields['date_mod'] ?? date('Y-m-d H:i:s'),
                'icon' => 'ti ti-ban',
                'color' => 'bg-secondary',
                'title' => __('Pasta cancelada', 'protocolo'),
                'desc' => Html::convDateTime($pasta->fields['date_mod'] ?? '')
            ];
        }
        // Ordena por data
        usort($events, function($a,$b){ return strtotime($a['date'] ?? '0') <=> strtotime($b['date'] ?? '0'); });
        foreach ($events as $ev) {
            echo "<div class='mb-3 position-relative'>";
            echo "<span class='position-absolute d-flex align-items-center justify-content-center " . $ev['color'] . " text-white' style='left:-32px; top:0; width:20px; height:20px; border-radius:50%; font-size:11px;'><i class='" . $ev['icon'] . "'></i></span>";
            echo "<div class='small text-muted'>" . Html::convDateTime($ev['date']) . "</div>";
            echo "<div class='fw-semibold'>" . $ev['title'] . "</div>";
            echo "<div class='small text-muted'>" . $ev['desc'] . "</div>";
            echo "</div>";
        }
        echo "</div></div>";
    }

    public function showForm($ID, array $options = [])
    {
        $isNew = ((int)$ID === 0);
        // modal=true: renderiza só o formulário (para dentro da janela flutuante), sem wrapper pt-page
        $isModal = !empty($options['modal']);
        if ($isNew) {
            if (!self::canCreate() && !self::canView()) { return false; }
        } else {
            if (!self::canView()) { return false; }
        }

        $this->initForm($ID, $options);
        // Para novo, não chama showFormHeader ainda porque precisamos custom
        // Se reexibindo após falha de validação, preserva input
        $lastInput = $options['input'] ?? [];
        if (!empty($lastInput) && $isNew) {
            // Mescla para facilitar pré-preenchimento
            foreach (['categoria','especie_outro','assunto','origem_tipo','origem_outro','origem_entities_id','destino_tipo','destino_outro','destino_entities_id','recebido_de','recebido_documento','recebido_documento_tipo','recebedor_nome','recebedor_documento','recebedor_documento_tipo','observacao','data_recebimento','data_recebimento_date','data_recebimento_time'] as $k) {
                if (isset($lastInput[$k])) $this->fields[$k] = $lastInput[$k];
            }
        }

        $csrf = Session::getNewCSRFToken();
        $formUrl = self::getFormURL();
        // Wrapper pt-page: aplica identidade visual moderna (compat com tab_cadre_fixe/cards/btn);
        // no modal usa padding zerado (o pt-modal-body já tem respiro)
        if ($isModal) {
            echo "<div class='pt-page' style='max-width:none;padding:0;'>";
        } else {
            echo "<div class='pt-page' style='max-width:none;padding:4px 4px 24px;'>";
        }
        // Ficha existente abre em modo visualização (edição só via botão Editar)
        echo "<form method='post' action='$formUrl' enctype='multipart/form-data' id='plugin_protocolo_pasta_form' novalidate" . ($isNew ? '' : " data-viewonly='1'") . ">";
        echo '<input type="hidden" name="_glpi_csrf_token" value="' . $csrf . '">';
        echo "<style>#plugin_protocolo_pasta_form input[type='text']:not([name*='observacao']){text-transform:uppercase}</style>";
        if (!$isNew) {
            echo Html::hidden('id', ['value' => $ID]);
        }

        // Usa layout GLPI padrão: tab_cadre_fixe
        echo "<div class='spaced'><table class='tab_cadre_fixe'>";
        if ($isNew) {
            echo "<div id='pt-wiz-ind' style='display:flex;gap:8px;align-items:center;margin:2px 8px 12px;flex-wrap:wrap;'>";
            echo "<span class='pt-wiz-dot' data-s='1' style='display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:20px;font-size:.82rem;font-weight:700;border:1.5px solid #16a34a;background:#f0fdf4;color:#16a34a;'><span>1</span> Dados</span>";
            echo "<span style='color:#cbd5e1;'>→</span>";
            echo "<span class='pt-wiz-dot' data-s='2' style='display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:20px;font-size:.82rem;font-weight:700;border:1.5px solid #e8eaf0;background:#fff;color:#9ca3af;'><span>2</span> Origem e destino</span>";
            echo "<span style='color:#cbd5e1;'>→</span>";
            echo "<span class='pt-wiz-dot' data-s='3' style='display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:20px;font-size:.82rem;font-weight:700;border:1.5px solid #e8eaf0;background:#fff;color:#9ca3af;'><span>3</span> Quem entrega</span>";
            echo "<span style='color:#cbd5e1;'>→</span>";
            echo "<span class='pt-wiz-dot' data-s='4' style='display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:20px;font-size:.82rem;font-weight:700;border:1.5px solid #e8eaf0;background:#fff;color:#9ca3af;'><span>4</span> Quem recebe</span>";
            echo "<span style='color:#cbd5e1;'>→</span>";
            echo "<span class='pt-wiz-dot' data-s='5' style='display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:20px;font-size:.82rem;font-weight:700;border:1.5px solid #e8eaf0;background:#fff;color:#9ca3af;'><span>5</span> Itens</span>";
            echo "</div>";
            echo "<tbody data-ptstep='1'>";
        }
        if ($isNew) {
            echo "<div id='pt-dev-overlay' style='display:none;position:fixed;inset:0;background:rgba(17,24,39,.6);z-index:10090;align-items:center;justify-content:center;padding:20px;'>";
            echo "<div style='background:#fff;border-radius:16px;max-width:420px;width:100%;padding:28px 24px;text-align:center;'>";
            echo "<div style='width:64px;height:64px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:16px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;'><i class='ti ti-device-tablet' style='font-size:1.8rem;color:#fff;'></i></div>";
            echo "<div style='font-weight:800;font-size:1.1rem;margin-bottom:8px;'>Devolva o tablet ao responsável</div>";
            echo "<p style='font-size:.9rem;color:#6b7280;'>Para finalizar a entrega, devolva o equipamento ao responsável do protocolo e continue.</p>";
            echo "<button type='button' class='pt-btn pt-btn-green' style='width:100%;' onclick='ptDevOk()'>Entendi, continuar</button>";
            echo "</div></div>";
        }

        if (!$isNew) {
            $catBadge = self::getCategoriaBadge($this->fields['categoria'] ?? 'pasta', $this->fields['especie_outro'] ?? null);
            $origemDisp = self::getOrigemDestinoDisplay($this->fields, 'origem');
            $destinoDisp = self::getOrigemDestinoDisplay($this->fields, 'destino');
            echo "<tr><th colspan='4' class='center'><h3>" . htmlspecialchars($this->fields['codigo']) . " $catBadge " . self::getStatusBadge($this->fields['status']) . "</h3>";
            echo "<small class='text-muted'>Origem: $origemDisp &rarr; Destino: $destinoDisp · " . __('Criada por', 'protocolo') . " " . htmlspecialchars(getUserName($this->fields['users_id'] ?? 0)) . " em " . Html::convDateTime($this->fields['date_creation']) . "</small>";
            if (!empty($this->fields['assunto'])) {
                echo "<div style='margin-top:4px;'><small class='text-muted'>" . __('Assunto', 'protocolo') . ": <strong>" . htmlspecialchars($this->fields['assunto']) . "</strong></small></div>";
            }
            echo "</th></tr>";
        }

        // Alerta inline (evita F5 e perda de dados)
        if ($isNew) {
            echo "<div id='protocoloAlert' class='alert alert-warning d-none mx-2' role='alert' style='border-left:4px solid #ffc107'><i class='ti ti-alert-triangle me-1'></i> <span id='protocoloAlertMsg'></span></div>";
        }
        // Data + Hora + Espécie na mesma linha
        $valDtRaw = $this->fields['data_recebimento'] ?? date('Y-m-d H:i:s');
        try { $tsDt = strtotime((string)$valDtRaw) ?: time(); } catch (\Throwable $e) { $tsDt = time(); }
        $valDate = date('Y-m-d', $tsDt);
        $valTime = date('H:i', $tsDt);
        // Espécie: para novo, sem pré-seleção (obriga atenção); para edição, mantém valor salvo
        $espVal = strtolower($this->fields['categoria'] ?? '');
        $espOpts = self::getEspecieOptions();
        if (!array_key_exists($espVal, $espOpts)) $espVal = $isNew ? '' : 'pasta';
        $espOutro = $this->fields['especie_outro'] ?? '';
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='4'><div class='d-flex gap-3 align-items-start flex-wrap'>";
        echo "<div style='min-width:160px'><label>" . __('Data', 'protocolo') . " <span class='required'>*</span></label><input type='date' name='data_recebimento_date' id='data_recebimento_date' class='form-control' required value='$valDate'></div>";
        echo "<div style='min-width:130px'><label>" . __('Hora', 'protocolo') . " <span class='required'>*</span></label><input type='time' name='data_recebimento_time' id='data_recebimento_time' class='form-control' required value='$valTime'></div>";
        echo "<div class='flex-fill' style='min-width:220px'><label>" . __('Espécie', 'protocolo') . " <span class='required'>*</span></label>";
        echo "<select name='categoria' id='especieSelect' class='form-select' required style='width:100%'>";
        echo "<option value=''>-- " . __('Selecione', 'protocolo') . " --</option>";
        foreach ($espOpts as $ev => $el) {
            $sel = $espVal === $ev ? 'selected' : '';
            echo "<option value='$ev' $sel>" . htmlspecialchars($el) . "</option>";
        }
        echo "</select>";
        echo "<div id='especie_outro_wrap' style='display:" . ($espVal==='outro'?'block':'none') . ";margin-top:6px;'><input type='text' name='especie_outro' id='especie_outro_input' class='form-control' value='" . Html::cleanInputText($espOutro) . "' placeholder='Descreva a espécie'></div>";
        echo "<small class='text-muted'>Separa gráficos e filtros</small>";
        echo "</div>";
        echo "</div></td>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td width='15%'><label>" . __('Assunto', 'protocolo') . " <span class='required'>*</span></label></td>";
        echo "<td colspan='3'><input type='text' name='assunto' id='assunto_field' class='form-control' required maxlength='255' style='width:100%' value='" . Html::cleanInputText($this->fields['assunto'] ?? '') . "' placeholder='Ex: Ofício nº 123/2026 — matrícula'></td>";
        echo "</tr>";
        if ($isNew) echo "</tbody><tbody data-ptstep='2' style='display:none'>";

        // Origem/Interessado (Escola ou Outros; URE só aparece em registros antigos)
        $origemTipoRaw = $this->fields['origem_tipo'] ?? '';
        if ($isNew) {
            $origemTipo = in_array(strtolower($origemTipoRaw), ['outro','ure','escola']) ? strtolower($origemTipoRaw) : '';
        } else {
            $origemTipo = $this->fields['origem_tipo'] ?? 'escola';
            if (!in_array($origemTipo, ['outro','ure','escola'])) $origemTipo = 'escola';
            if (empty($this->fields['origem_tipo']) && !empty($this->fields['plugin_protocolo_escolas_id'])) $origemTipo='escola';
        }
        $origemOutro = $this->fields['origem_outro'] ?? '';
        $origemEnt = (int)($this->fields['origem_entities_id'] ?? 0);
        $filhasURE = [];
        try {
            $dbLoc = $GLOBALS['DB'] ?? null;
            if ($dbLoc && function_exists('getSonsOf')) {
                $parentURE = 0;
                $sonsURE = getSonsOf('glpi_entities', $parentURE);
                if (empty($sonsURE)) {
                    $parentURE = 1;
                    $sonsURE = getSonsOf('glpi_entities', $parentURE);
                }
                $idsURE = [];
                foreach ((array)$sonsURE as $eidURE) {
                    $eidURE = (int)$eidURE;
                    if ($eidURE !== (int)$parentURE) $idsURE[] = $eidURE;
                }
                if (!empty($idsURE)) {
                    $itURE = $dbLoc->request(['SELECT' => ['id', 'name', 'completename'], 'FROM' => 'glpi_entities', 'WHERE' => ['id' => $idsURE], 'ORDER' => 'completename']);
                    foreach ($itURE as $rowURE) $filhasURE[] = $rowURE;
                }
            }
        } catch (\Throwable $e) { $filhasURE = []; }
        echo "<tr class='tab_bg_1'>";
        echo "<td><label>" . __('Origem/Interessado', 'protocolo') . " <span class='required'>*</span> <small class='text-muted'>(de onde vem)</small></label></td>";
        echo "<td colspan='3'>";
        echo "<div class='d-flex gap-3 mb-2 flex-wrap' id='origemGroup'>";
        echo "<div class='form-check'><input class='form-check-input origem-tipo' type='radio' name='origem_tipo' id='origem_escola' value='escola' " . ($origemTipo==='escola'?'checked':'') . ($isNew?' required':' required') . "><label class='form-check-label' for='origem_escola'>Escola</label></div>";
        echo "<div class='form-check'><input class='form-check-input origem-tipo' type='radio' name='origem_tipo' id='origem_ure' value='ure' " . ($origemTipo==='ure'?'checked':'') . "><label class='form-check-label' for='origem_ure'>URE</label></div>";
        echo "<div class='form-check'><input class='form-check-input origem-tipo' type='radio' name='origem_tipo' id='origem_outro' value='outro' " . ($origemTipo==='outro'?'checked':'') . "><label class='form-check-label' for='origem_outro'>Outros</label></div>";
        echo "</div>";
        echo "<div id='origem_locked_wrap' style='display:" . ($origemTipo===''?'block':'none') . "'><input type='text' class='form-control' disabled value='Selecione o tipo'></div>";
        echo "<div id='origem_outro_wrap' style='display:" . ($origemTipo==='outro'?'block':'none') . "'><input type='text' name='origem_outro' id='origem_outro_input' class='form-control' value='" . Html::cleanInputText($origemOutro) . "' placeholder='Escreva a origem (ex: Correios, Secretaria...)'></div>";
        echo "<div id='origem_ure_wrap' style='display:" . ($origemTipo==='ure'?'block':'none') . "'><input type='text' class='form-control' disabled value='Unidade Regional de Ensino de Jales - URE'><input type='hidden' name='origem_entities_id_ure' value='0'></div>";
        echo "<div id='origem_escola_wrap' style='display:" . ($origemTipo==='escola'?'block':'none') . "'>";
        if (!empty($filhasURE)) {
            echo "<select name='origem_entities_id' class='form-select pt-escola-combo' style='width:100%'>";
            echo "<option value=''>-- " . __('Selecione a escola', 'protocolo') . " --</option>";
            foreach ($filhasURE as $frO) {
                $fidO = (int)$frO['id'];
                $fselO = ($origemTipo==='escola' && $fidO===$origemEnt) ? 'selected' : '';
                $flblOp = explode('>', (string)($frO['completename'] ?? $frO['name']));
                $flblO = trim(end($flblOp));
                echo "<option value='$fidO' $fselO>" . htmlspecialchars($flblO) . "</option>";
            }
            echo "</select>";
        } else {
        try {
            \Entity::dropdown([
                'name'   => 'origem_entities_id',
                'value'  => $origemTipo==='escola' ? $origemEnt : 0,
                'width'  => '100%',
                'display_emptychoice' => true,
                'emptylabel' => '-- ' . __('Selecione a escola', 'protocolo') . ' --',
                'comments' => false,
                'entity' => 0,
                'entity_sons' => true,
            ]);
        } catch (\Throwable $e) {
            echo "<select name='origem_entities_id' class='form-select pt-escola-combo' style='width:100%'><option value=''>-- Selecione --</option>";
            if ($origemEnt) { $n = self::getEscolaName($origemEnt); echo "<option value='$origemEnt' selected>" . htmlspecialchars($n) . "</option>"; }
            echo "</select>";
        }
        }
        echo "</div>";
        echo "</td></tr>";

        // Destino
        $destinoTipoRaw = $this->fields['destino_tipo'] ?? '';
        if ($isNew) {
            $destinoTipo = in_array(strtolower($destinoTipoRaw), ['outro','ure','escola']) ? strtolower($destinoTipoRaw) : '';
        } else {
            $destinoTipo = $this->fields['destino_tipo'] ?? 'escola';
            if (!in_array($destinoTipo, ['outro','ure','escola'])) $destinoTipo = 'escola';
        }
        $destinoOutro = $this->fields['destino_outro'] ?? '';
        $destinoEnt = (int)($this->fields['destino_entities_id'] ?? $this->fields['plugin_protocolo_escolas_id'] ?? 0);
        echo "<tr class='tab_bg_1'>";
        echo "<td><label>" . __('Destino', 'protocolo') . " <span class='required'>*</span> <small class='text-muted'>(para onde vai)</small></label></td>";
        echo "<td colspan='3'>";
        echo "<div class='d-flex gap-3 mb-2 flex-wrap' id='destinoGroup'>";
        echo "<div class='form-check'><input class='form-check-input destino-tipo' type='radio' name='destino_tipo' id='destino_escola' value='escola' " . ($destinoTipo==='escola'?'checked':'') . ($isNew?' required':' required') . "><label class='form-check-label' for='destino_escola'>Escola</label></div>";
        echo "<div class='form-check'><input class='form-check-input destino-tipo' type='radio' name='destino_tipo' id='destino_ure' value='ure' " . ($destinoTipo==='ure'?'checked':'') . "><label class='form-check-label' for='destino_ure'>URE</label></div>";
        echo "<div class='form-check'><input class='form-check-input destino-tipo' type='radio' name='destino_tipo' id='destino_outro' value='outro' " . ($destinoTipo==='outro'?'checked':'') . "><label class='form-check-label' for='destino_outro'>Outros</label></div>";
        echo "</div>";
        echo "<div id='destino_locked_wrap' style='display:" . ($destinoTipo===''?'block':'none') . "'><input type='text' class='form-control' disabled value='Selecione o tipo'></div>";
        echo "<div id='destino_outro_wrap' style='display:" . ($destinoTipo==='outro'?'block':'none') . "'><input type='text' name='destino_outro' id='destino_outro_input' class='form-control' value='" . Html::cleanInputText($destinoOutro) . "' placeholder='Escreva o destino'></div>";
        echo "<div id='destino_ure_wrap' style='display:" . ($destinoTipo==='ure'?'block':'none') . "'><input type='text' class='form-control' disabled value='Unidade Regional de Ensino de Jales - URE'><input type='hidden' name='destino_entities_id_ure' value='0'></div>";
        echo "<div id='destino_escola_wrap' style='display:" . ($destinoTipo==='escola'?'block':'none') . "'>";
        if (!empty($filhasURE)) {
            echo "<select name='destino_entities_id' class='form-select pt-escola-combo' style='width:100%'>";
            echo "<option value=''>-- " . __('Selecione a escola', 'protocolo') . " --</option>";
            foreach ($filhasURE as $frD) {
                $fidD = (int)$frD['id'];
                $fselD = ($destinoTipo==='escola' && $fidD===$destinoEnt) ? 'selected' : '';
                $flblDp = explode('>', (string)($frD['completename'] ?? $frD['name']));
                $flblD = trim(end($flblDp));
                echo "<option value='$fidD' $fselD>" . htmlspecialchars($flblD) . "</option>";
            }
            echo "</select>";
        } else {
        try {
            \Entity::dropdown([
                'name'   => 'destino_entities_id',
                'value'  => $destinoTipo==='escola' ? $destinoEnt : 0,
                'width'  => '100%',
                'display_emptychoice' => true,
                'emptylabel' => '-- ' . __('Selecione a escola', 'protocolo') . ' --',
                'comments' => false,
                'entity' => 0,
                'entity_sons' => true,
            ]);
        } catch (\Throwable $e) {
            echo "<select name='destino_entities_id' class='form-select pt-escola-combo' style='width:100%'><option value=''>-- Selecione --</option>";
            if ($destinoEnt) { $n = self::getEscolaName($destinoEnt); echo "<option value='$destinoEnt' selected>" . htmlspecialchars($n) . "</option>"; }
            echo "</select>";
        }
        }
        echo "</div>";
        // compat: mantém plugin_protocolo_escolas_id escondido para buscas antigas (espelha destino quando escola)
        echo "<input type='hidden' name='plugin_protocolo_escolas_id' id='compat_escola_id' value='$destinoEnt'>";
        echo "</td></tr>";
        if ($isNew) echo "</tbody><tbody data-ptstep='3' style='display:none'>";

        if ($isNew) {
            echo "<tr class='tab_bg_1' data-pts2='r1'>";
            echo "<td><label>" . __('Recebido de (quem deixou)', 'protocolo') . " <span class='required'>*</span></label><br><small class='text-muted'>Entregue o tablet para a pessoa preencher</small></td>";
            echo "<td colspan='3'><input type='text' name='recebido_de' id='recebido_de_field' class='form-control' required value='" . Html::cleanInputText($this->fields['recebido_de'] ?? '') . "' placeholder='Ex: João da Silva - Secretaria' style='font-size:1.05rem;padding:12px;'></td>";
            echo "</tr>";
            echo "<tr class='tab_bg_1' data-pts2='r2' style='display:none;'>";
            echo "<td><label>" . __('Documento de quem deixou', 'protocolo') . "</label></td>";
            echo "<td colspan='3'><div style='display:flex;gap:10px;margin-bottom:12px;'>";
            echo "<button type='button' class='pt-btn pt-btn-secondary pt-recdoc-btn' data-t='rg' onclick='ptRecDocType(\"rg\")' style='flex:1;padding:14px;'><i class='ti ti-id' style='font-size:1.6rem;'></i><br><span style='font-weight:800;'>RG</span></button>";
            echo "<button type='button' class='pt-btn pt-btn-secondary pt-recdoc-btn' data-t='cpf' onclick='ptRecDocType(\"cpf\")' style='flex:1;padding:14px;'><i class='ti ti-id-badge-2' style='font-size:1.6rem;'></i><br><span style='font-weight:800;'>CPF</span></button>";
            echo "</div><div class='input-group'><input type='text' name='recebido_documento' id='recebido_documento' class='form-control' value='" . Html::cleanInputText($this->fields['recebido_documento'] ?? '') . "' placeholder='Somente números' maxlength='14' inputmode='numeric' style='font-size:1.05rem;padding:12px;'></div><input type='hidden' name='recebido_documento_tipo' id='recebido_documento_tipo' value='" . htmlspecialchars($this->fields['recebido_documento_tipo'] ?? 'cpf') . "'><small class='text-muted' id='recebido_doc_hint'>CPF: 11 dígitos | RG: 7 a 9 dígitos</small></td>";
            echo "</tr>";
        } else {
        echo "<tr class='tab_bg_1'>";
        echo "<td><label>" . __('Recebido de (quem deixou)', 'protocolo') . " <span class='required'>*</span></label></td>";
        echo "<td><input type='text' name='recebido_de' class='form-control' required value='" . Html::cleanInputText($this->fields['recebido_de'] ?? '') . "' placeholder='Ex: João da Silva - Secretaria'></td>";
        echo "<td><label>" . __('Documento', 'protocolo') . "</label></td>";
        echo "<td><div class='input-group'><select name='recebido_documento_tipo' id='recebido_documento_tipo' class='form-select' style='max-width:95px'><option value='cpf'" . ((($this->fields['recebido_documento_tipo'] ?? 'cpf')==='cpf')?' selected':'') . ">CPF</option><option value='rg'" . ((($this->fields['recebido_documento_tipo'] ?? 'cpf')==='rg')?' selected':'') . ">RG</option></select><input type='text' name='recebido_documento' id='recebido_documento' class='form-control' value='" . Html::cleanInputText($this->fields['recebido_documento'] ?? '') . "' placeholder='000.000.000-00' maxlength='14'></div><small class='text-muted' id='recebido_doc_hint'>CPF: 11 dígitos (000.000.000-00) | RG: 7-9 dígitos</small></td>";
        echo "</tr>";
        }
        if ($isNew) {
            echo "<tr class='tab_bg_1' data-pts2='r3' style='display:none;'><td><label>Assinatura de quem deixou <span class='required'>*</span></label><br><small class='text-muted'>Entregue o tablet para assinatura</small></td>";
            echo "<td colspan='3'><div style='background:#fff;border:2px solid #e8eaf0;border-radius:12px;overflow:hidden;touch-action:none;'><canvas id='pt-rec-canvas' style='width:100%;height:180px;display:block;touch-action:none;cursor:crosshair;'></canvas></div><div style='display:flex;justify-content:space-between;align-items:center;margin-top:6px;'><small class='text-muted'>Assine com dedo/caneta</small><button type='button' id='pt-rec-clear' class='pt-btn pt-btn-secondary pt-btn-sm'><i class='ti ti-eraser'></i> Limpar</button></div><input type='hidden' name='recebido_assinatura_image' id='pt-rec-image' value=''></td></tr>";
            echo "<tr class='tab_bg_1' data-pts2='nav'><td colspan='4'><div style='display:flex;gap:8px;justify-content:center;'><button type='button' id='pt-s2-back' class='pt-btn pt-btn-secondary' style='display:none;' onclick='ptS2Nav(-1)'><i class='ti ti-arrow-left'></i> Voltar</button><button type='button' id='pt-s2-next' class='pt-btn pt-btn-green' onclick='ptS2Nav(1)'>Continuar <i class='ti ti-arrow-right'></i></button></div></td></tr>";
        }
        if ($isNew) echo "</tbody><tbody data-ptstep='4' style='display:none'>";
        if ($isNew) {
            echo "<tr class='tab_bg_1' data-pts3='r1'>";
            echo "<td><label>Nome completo de quem recebe <span class='required'>*</span></label><br><small class='text-muted'>Atendente que recebe a pasta</small></td>";
            echo "<td colspan='3'><input type='text' name='recebedor_nome' id='recebedor_nome_field' class='form-control' required value='" . Html::cleanInputText($this->fields['recebedor_nome'] ?? '') . "' placeholder='Ex: Maria Souza' style='font-size:1.05rem;padding:12px;'></td>";
            echo "</tr>";
            echo "<tr class='tab_bg_1' data-pts3='r2' style='display:none;'>";
            echo "<td><label>Documento de quem recebe</label></td>";
            echo "<td colspan='3'><div style='display:flex;gap:10px;margin-bottom:12px;'>";
            echo "<button type='button' class='pt-btn pt-btn-secondary pt-recbdoc-btn' data-t='rg' onclick='ptRecbDocType(\"rg\")' style='flex:1;padding:14px;'><i class='ti ti-id' style='font-size:1.6rem;'></i><br><span style='font-weight:800;'>RG</span></button>";
            echo "<button type='button' class='pt-btn pt-btn-secondary pt-recbdoc-btn' data-t='cpf' onclick='ptRecbDocType(\"cpf\")' style='flex:1;padding:14px;'><i class='ti ti-id-badge-2' style='font-size:1.6rem;'></i><br><span style='font-weight:800;'>CPF</span></button>";
            echo "</div><div class='input-group'><input type='text' name='recebedor_documento' id='recebedor_documento' class='form-control' value='" . Html::cleanInputText($this->fields['recebedor_documento'] ?? '') . "' placeholder='Somente números' maxlength='14' inputmode='numeric' style='font-size:1.05rem;padding:12px;'></div><input type='hidden' name='recebedor_documento_tipo' id='recebedor_documento_tipo' value='" . htmlspecialchars($this->fields['recebedor_documento_tipo'] ?? 'cpf') . "'><small class='text-muted' id='recebedor_doc_hint'>CPF: 11 dígitos | RG: 7 a 9 dígitos</small></td>";
            echo "</tr>";
            echo "<tr class='tab_bg_1' data-pts3='r3' style='display:none;'><td><label>Assinatura de quem recebe <span class='required'>*</span></label><br><small class='text-muted'>Atendente assina no tablet</small></td>";
            echo "<td colspan='3'><div style='background:#fff;border:2px solid #e8eaf0;border-radius:12px;overflow:hidden;touch-action:none;'><canvas id='pt-recb-canvas' style='width:100%;height:180px;display:block;touch-action:none;cursor:crosshair;'></canvas></div><div style='display:flex;justify-content:space-between;align-items:center;margin-top:6px;'><small class='text-muted'>Assine com dedo/caneta</small><button type='button' id='pt-recb-clear' class='pt-btn pt-btn-secondary pt-btn-sm'><i class='ti ti-eraser'></i> Limpar</button></div><input type='hidden' name='recebedor_assinatura_image' id='pt-recb-image' value=''></td></tr>";
            echo "<tr class='tab_bg_1' data-pts3='nav'><td colspan='4'><div style='display:flex;gap:8px;justify-content:center;'><button type='button' id='pt-s3-back' class='pt-btn pt-btn-secondary' style='display:none;' onclick='ptS3Nav(-1)'><i class='ti ti-arrow-left'></i> Voltar</button><button type='button' id='pt-s3-next' class='pt-btn pt-btn-green' onclick='ptS3Nav(1)'>Continuar <i class='ti ti-arrow-right'></i></button></div></td></tr>";
        }
        if ($isNew) echo "</tbody><tbody data-ptstep='5' style='display:none'>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><label>" . __('Código', 'protocolo') . "</label></td>";
        echo "<td><input class='form-control' disabled placeholder='PROT-YYYY-0001' value='" . Html::cleanInputText($this->fields['codigo'] ?? '') . "'>";
        if (!$isNew) echo "<small class='text-muted'>" . __('Gerado automaticamente', 'protocolo') . "</small>";
        echo "</td>";
        echo "<td><label>" . __('Status', 'protocolo') . "</label></td>";
        echo "<td>" . (!$isNew ? self::getStatusBadge($this->fields['status']) : "<span class='badge bg-warning text-dark'>Aguardando retirada</span>") . "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><label>" . __('Observação', 'protocolo') . "</label></td>";
        echo "<td colspan='3'><textarea name='observacao' class='form-control' rows='2' placeholder='" . __('Observações gerais', 'protocolo') . "'>" . Html::cleanInputText($this->fields['observacao'] ?? '') . "</textarea></td>";
        echo "</tr>";

        if (!$isNew && !empty($this->fields['recebedor_nome'])) {
            $recbDocV = trim($this->fields['recebedor_documento'] ?? '');
            echo "<tr class='tab_bg_1'><td><label>Recebido por (atendente)</label></td><td colspan='3'>" . htmlspecialchars($this->fields['recebedor_nome']) . ($recbDocV !== '' ? " (" . htmlspecialchars($recbDocV) . ")" : "") . (!empty($this->fields['recebedor_assinatura_image']) ? " <span class='badge bg-success'><i class='ti ti-signature'></i> Assinado digitalmente</span>" : "") . "</td></tr>";
        }

        if (!$isNew && $this->fields['status'] === 'retirada') {
            $tipoRet = strtoupper($this->fields['retirado_documento_tipo'] ?? 'CPF');
            $docRet = htmlspecialchars($this->fields['retirado_documento'] ?? '');
            echo "<tr class='tab_bg_1'><td colspan='4' class='center bg-success bg-opacity-10'><strong>" . __('Retirada registrada', 'protocolo') . "</strong> " . Html::convDateTime($this->fields['data_retirada']) . " por " . htmlspecialchars($this->fields['retirado_por'] ?? '') . ($docRet ? " ($tipoRet: $docRet)" : "") . "</td></tr>";
            if (!empty($this->fields['observacao_retirada'])) {
                echo "<tr class='tab_bg_1'><td>" . __('Obs. retirada', 'protocolo') . "</td><td colspan='3'>" . nl2br(Html::cleanInputText($this->fields['observacao_retirada'])) . "</td></tr>";
            }
        }

        if ($isNew) echo "</tbody>";
        echo "</table></div>";

        // Se for novo: tipos + itens (preserva input se reexibindo após falha)
        if ($isNew) {
            echo "<div data-ptstep='5' style='display:none'>";
            // Tipos
            $tipos = TipoArquivo::getAllActive();
            $lastTipos = $lastInput['tipos'] ?? [];
            $lastTipos = array_map('intval', (array)$lastTipos);
            echo "<div class='spaced'><div class='card border-warning mb-3'><div class='card-header bg-warning bg-opacity-10 d-flex align-items-center justify-content-between flex-wrap gap-2'><div class='d-flex align-items-center gap-2'><span class='fw-bold'><i class='ti ti-tags'></i> " . __('Quais tipos de arquivos', 'protocolo') . " <span class='text-danger'>*</span></span><span class='badge bg-light text-muted border fw-normal'> " . __('marque as caixinhas', 'protocolo') . "</span></div><a href='" . TipoArquivo::getSearchURL() . "' target='_blank' class='btn btn-sm btn-outline-secondary'><i class='ti ti-settings'></i> " . __('Gerenciar tipos', 'protocolo') . "</a></div>";
            echo "<div class='card-body'>";
            if (!$tipos) {
                echo "<div class='alert alert-warning small'>" . __('Nenhum tipo cadastrado', 'protocolo') . " <a href='" . TipoArquivo::getSearchURL() . "'>" . __('Cadastre', 'protocolo') . "</a></div>";
            } else {
                echo "<div class='row g-2'>";
                foreach ($tipos as $t) {
                    $checked = in_array((int)$t['id'], $lastTipos) ? 'checked' : '';
                    echo "<div class='col-md-4 col-sm-6'><div class='form-check'><input class='form-check-input tipo-check' type='checkbox' name='tipos[]' value='" . (int)$t['id'] . "' id='tipo" . (int)$t['id'] . "' data-nome='" . Html::cleanInputText($t['name']) . "' $checked><label class='form-check-label' for='tipo" . (int)$t['id'] . "'>" . htmlspecialchars($t['name']) . "</label></div></div>";
                }
                echo "</div>";
                echo "<div class='form-text mt-2'>" . __('Selecione pelo menos 1. Os itens abaixo são preenchidos automaticamente', 'protocolo') . "</div>";
            }
            echo "</div></div></div>";

            $lastItens = $lastInput['itens'] ?? [];
            echo "<div class='spaced'><div class='d-flex justify-content-between align-items-center mb-2'><h3 class='mb-0'><i class='ti ti-list-check'></i> " . __('Itens da pasta', 'protocolo') . " *</h3></div>";
            if (!empty($lastItens) && is_array($lastItens)) {
                echo "<div id='itensWrap'>";
                foreach ($lastItens as $idx => $it) {
                    $desc = Html::cleanInputText($it['descricao'] ?? '');
                    $qtd = max(1, (int)($it['quantidade'] ?? 1));
                    $obs = Html::cleanInputText($it['observacao'] ?? '');
                    if ($desc === '' && $qtd === 1 && $obs === '' && $idx !== 0) continue;
                    echo "<div class='row g-2 mb-2 item-row'><div class='col-md-7'><input name='itens[$idx][descricao]' class='form-control' value='$desc' placeholder='" . __('Descrição do item', 'protocolo') . "' required></div><div class='col-md-2'><input name='itens[$idx][quantidade]' type='number' min='1' value='$qtd' class='form-control' placeholder='Qtd'></div><div class='col-md-2'><input name='itens[$idx][observacao]' class='form-control' value='$obs' placeholder='Obs.'></div><div class='col-md-1'><button type='button' class='btn btn-outline-danger w-100 btnRemove'><i class='ti ti-trash'></i></button></div></div>";
                }
                echo "</div>";
            } else {
                echo "<div id='itensWrap'><div class='row g-2 mb-2 item-row'><div class='col-md-7'><input name='itens[0][descricao]' class='form-control' placeholder='" . __('Descrição do item', 'protocolo') . "' required></div><div class='col-md-2'><input name='itens[0][quantidade]' type='number' min='1' value='1' class='form-control' placeholder='Qtd'></div><div class='col-md-2'><input name='itens[0][observacao]' class='form-control' placeholder='Obs.'></div><div class='col-md-1'><button type='button' class='btn btn-outline-danger w-100 btnRemove'><i class='ti ti-trash'></i></button></div></div></div>";
            }
            echo "<div class='form-text mb-3'>" . __('Exemplos: Ofício nº 123/2026, Processo de matrícula...', 'protocolo') . "</div></div>";
            echo "</div>";
        }

        // Botões GLPI — identidade pt-*
        echo "<div class='card-body d-flex gap-2 justify-content-center' style='padding:16px;'>";
        if ($isNew) {
            if ($isModal) {
                echo "<button type='button' id='pt-wiz-fechar' class='pt-btn pt-btn-secondary' onclick='ptCloseRegisterModal()'>" . __('Fechar') . "</button>";
            } else {
                echo "<a href='" . self::getSearchURL() . "' id='pt-wiz-fechar' class='pt-btn pt-btn-secondary'>" . __('Cancelar') . "</a>";
            }
            echo "<button type='button' id='pt-wiz-back' class='pt-btn pt-btn-secondary' style='display:none;' onclick='ptWizNav(-1)'><i class='ti ti-arrow-left'></i> Voltar</button>";
            echo "<button type='button' id='pt-wiz-next' class='pt-btn pt-btn-green' onclick='ptWizNav(1)'>Próximo <i class='ti ti-arrow-right'></i></button>";
            echo "<button type='submit' name='add' value='1' id='pt-reg-submit' class='pt-btn pt-btn-green' style='display:none;'><i class='ti ti-check'></i> " . __('Registrar pasta', 'protocolo') . "</button>";
        } else {
            // Ficha existente: abre em visualização; edição liberada via botão Editar
            // Usa haveRightDB (aceita legado 1 e novo 31) em vez de Session::haveRight puro.
            if (\GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', UPDATE)) {
                echo "<button type='button' id='pt-pasta-edit-btn' class='pt-btn pt-btn-secondary' onclick='ptTogglePastaEdit(true)'><i class='ti ti-pencil'></i> " . __('Editar', 'protocolo') . "</button>";
                echo "<button type='submit' name='update' value='1' id='pt-pasta-save-btn' class='pt-btn pt-btn-primary' style='display:none;'><i class='ti ti-device-floppy'></i> " . _x('button', 'Save') . "</button>";
                echo "<button type='button' id='pt-pasta-canceledit-btn' class='pt-btn pt-btn-secondary' style='display:none;' onclick='location.reload()'>" . __('Cancelar', 'protocolo') . "</button>";
            }
            // Ações específicas: retirada/cancelar/reabrir ficam em forms separados abaixo
        }
        echo "</div>";

        echo "</form>";

        // Trava imediata inline (não depende do app.js nem do DOMContentLoaded):
        // o script viaja junto do form (inclusive se a aba vier via AJAX) e
        // roda no parse, com o form já existente. O app.js mantém o estado
        // depois; o botão Editar destrava via ptTogglePastaEdit(true).
        if (!$isNew) {
            echo "<script>(function(){var f=document.getElementById('plugin_protocolo_pasta_form');if(!f)return;var els=f.querySelectorAll('input:not([type=\"hidden\"]):not([type=\"submit\"]):not([type=\"button\"]),select,textarea');for(var i=0;i<els.length;i++){try{els[i].disabled=true;}catch(e){}}})();</script>";
        }

        // Se não é novo, mostra ações laterais (retirada, upload, cancelar) - fora do form principal
        if (!$isNew) {
            echo "<div class='row g-3 mt-3'>";

            // Coluna esquerda já tem tabs com itens/termos; aqui mostramos ações rápidas na lateral
            echo "<div class='col-lg-8'>";
            // O conteúdo de itens/termos já está na tab, mas para vista form sem tabs, duplicamos link para termo
            echo "<div class='pt-card'><div class='pt-card-header'><strong><i class='ti ti-printer'></i> " . __('Ações rápidas', 'protocolo') . "</strong></div><div class='list-group list-group-flush'>";
            echo "<a href='" . Plugin::getWebDir('protocolo') . "/front/termo.php?id=$ID&tipo=recebimento' target='_blank' class='list-group-item list-group-item-action'><i class='ti ti-printer'></i> " . __('Imprimir Termo de Recebimento', 'protocolo') . "</a>";
            if ($this->fields['status'] === 'retirada') {
                echo "<a href='" . Plugin::getWebDir('protocolo') . "/front/termo.php?id=$ID&tipo=retirada' target='_blank' class='list-group-item list-group-item-action'><i class='ti ti-printer'></i> " . __('Imprimir Termo de Retirada', 'protocolo') . "</a>";
            }
            echo "<a href='" . self::getSearchURL() . "?criteria[0][field]=4&criteria[0][searchtype]=equals&criteria[0][value]=" . (int)$this->fields['plugin_protocolo_escolas_id'] . "' class='list-group-item list-group-item-action'><i class='ti ti-building'></i> " . __('Ver outras pastas desta escola', 'protocolo') . "</a>";
            echo "</div></div>";
            echo "</div>";

            echo "<div class='col-lg-4'>";
            if ($this->fields['status'] === 'aguardando') {
                echo "<div class='pt-card mb-3' style='border-color:#bbf7d0;'><div class='pt-card-header' style='background:#f0fdf4;border-color:#bbf7d0;'><strong style='color:#065f46;'><i class='ti ti-logout' style='color:#10b981;'></i> " . __('Registrar retirada', 'protocolo') . "</strong></div><div class='pt-card-body'>";
                echo "<p class='small text-muted'>" . __('Quando a escola vier buscar, preencha e gere o Termo de Retirada.', 'protocolo') . "</p>";
                echo "<form method='post' action='" . self::getFormURL() . "'>";
                echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
                echo Html::hidden('id', ['value' => $ID]);
                echo "<input type='hidden' name='action' value='retirar'>";
                echo "<div class='mb-2'><label class='form-label'>" . __('Retirado por', 'protocolo') . " *</label><input name='retirado_por' class='form-control' required placeholder='" . __('Nome de quem retirou', 'protocolo') . "'></div>";
                echo "<div class='mb-2'><label class='form-label'>" . __('Documento', 'protocolo') . "</label><div class='input-group'><select name='retirado_documento_tipo' id='retirado_documento_tipo' class='form-select' style='max-width:95px'><option value='cpf'>CPF</option><option value='rg'>RG</option></select><input name='retirado_documento' id='retirado_documento' class='form-control' placeholder='000.000.000-00' maxlength='14'></div><small class='text-muted' id='retirado_doc_hint'>CPF: 11 dígitos | RG: 7-9 dígitos</small></div>";
                echo "<div class='mb-2'><label class='form-label'>" . __('Data/hora retirada', 'protocolo') . "</label><input type='datetime-local' name='data_retirada' id='data_retirada_field' class='form-control' value='" . date('Y-m-d\TH:i') . "'></div>";
                echo "<div class='mb-3'><label class='form-label'>" . __('Observação', 'protocolo') . "</label><textarea name='observacao_retirada' class='form-control' rows='2'></textarea></div>";
                echo "<button class='pt-btn pt-btn-green w-100'><i class='ti ti-check'></i> " . __('Confirmar retirada', 'protocolo') . "</button>";
                echo "</form>";

                echo "<form method='post' action='" . self::getFormURL() . "' class='mt-2' onsubmit=\"return confirm('" . __('Cancelar esta pasta?', 'protocolo') . "')\">";
                echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
                echo Html::hidden('id', ['value' => $ID]);
                echo "<input type='hidden' name='action' value='cancelar'>";
                echo "<button class='pt-btn pt-btn-danger pt-btn-sm w-100'>" . __('Cancelar pasta', 'protocolo') . "</button>";
                echo "</form></div></div>";
            } else {
                echo "<div class='pt-card mb-3'><div class='pt-card-body text-center'><p class='mb-2'>" . __('Status', 'protocolo') . ": " . self::getStatusBadge($this->fields['status']) . "</p>";
                echo "<form method='post' action='" . self::getFormURL() . "' onsubmit=\"return confirm('" . __('Reabrir pasta?', 'protocolo') . "')\">";
                echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
                echo Html::hidden('id', ['value' => $ID]);
                echo "<input type='hidden' name='action' value='reabrir'>";
                echo "<button class='pt-btn pt-btn-secondary pt-btn-sm'>" . __('Reabrir para aguardando', 'protocolo') . "</button>";
                echo "</form></div></div>";
            }
            echo "</div>"; // col
            echo "</div>"; // row
        }

        // JS: Data/hora local do computador + máscara CPF/RG
        echo "<script>
        document.addEventListener('DOMContentLoaded', function(){
            function toLocalDatetimeValue(d){
                var pad = function(n){ return n<10?'0'+n:n; };
                return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
            }
            var now = new Date();
            var localVal = toLocalDatetimeValue(now);
            var recDt = document.getElementById('data_recebimento_field');
            if(recDt) recDt.value = localVal;
            var retDt = document.getElementById('data_retirada_field');
            if(retDt) retDt.value = localVal;

            function formatCPF(v){
                v=v.replace(/\\D/g,'').slice(0,11);
                v=v.replace(/(\\d{3})(\\d)/,'\$1.\$2');
                v=v.replace(/(\\d{3})(\\d)/,'\$1.\$2');
                v=v.replace(/(\\d{3})(\\d{1,2})\$/,'\$1-\$2');
                return v;
            }
            function formatRG(v){
                v=v.replace(/[^0-9xX]/g,'').slice(0,9).toUpperCase();
                if(v.length>2) v=v.replace(/^(\\d{2})(\\d)/,'\$1.\$2');
                if(v.length>6) v=v.replace(/^(\\d{2})\\.(\\d{3})(\\d)/,'\$1.\$2.\$3');
                if(v.length>9) v=v.replace(/^(\\d{2})\\.(\\d{3})\\.(\\d{3})([\\dX])/,'\$1.\$2.\$3-\$4');
                else if(v.length>8) v=v.replace(/\\.(\\d{3})([\\dX])\$/,'.\$1-\$2');
                return v;
            }
            function setupDoc(tipoSel, docInput, hint){
                if(!tipoSel || !docInput) return;
                function update(){
                    var tipo=tipoSel.value;
                    if(tipo==='cpf'){
                        docInput.placeholder='000.000.000-00';
                        docInput.maxLength=14;
                        if(hint) hint.textContent='CPF: 11 dígitos (000.000.000-00)';
                        docInput.value=formatCPF(docInput.value);
                    } else {
                        docInput.placeholder='00.000.000-0';
                        docInput.maxLength=12;
                        if(hint) hint.textContent='RG: 7 a 9 dígitos (00.000.000-0)';
                        docInput.value=formatRG(docInput.value);
                    }
                }
                tipoSel.addEventListener('change', update);
                docInput.addEventListener('input', function(){
                    if(tipoSel.value==='cpf') this.value=formatCPF(this.value);
                    else this.value=formatRG(this.value);
                });
                update();
            }
            setupDoc(document.getElementById('recebido_documento_tipo'), document.getElementById('recebido_documento'), document.getElementById('recebido_doc_hint'));
            setupDoc(document.getElementById('recebedor_documento_tipo'), document.getElementById('recebedor_documento'), document.getElementById('recebedor_doc_hint'));
            setupDoc(document.getElementById('retirado_documento_tipo'), document.getElementById('retirado_documento'), document.getElementById('retirado_doc_hint'));

            // Origem/Destino toggle + compat escola_id
            function setupOrigemDestino(){
                function bindTipo(prefix){
                    var radios = document.querySelectorAll('input[name=\"'+prefix+'_tipo\"]');
                    var outroWrap = document.getElementById(prefix+'_outro_wrap');
                    var ureWrap = document.getElementById(prefix+'_ure_wrap');
                    var escolaWrap = document.getElementById(prefix+'_escola_wrap');
                    function update(){
                        var checkedRt = document.querySelector('input[name=\"'+prefix+'_tipo\"]:checked');
                        var val = checkedRt ? checkedRt.value : '';
                        var lockedWrap = document.getElementById(prefix+'_locked_wrap');
                        if(outroWrap) outroWrap.style.display = val==='outro' ? 'block' : 'none';
                        if(ureWrap) ureWrap.style.display = val==='ure' ? 'block' : 'none';
                        if(escolaWrap) escolaWrap.style.display = val==='escola' ? 'block' : 'none';
                        if(lockedWrap) lockedWrap.style.display = val==='' ? 'block' : 'none';
                        // Escola: esconde URE da combo de escola; demais tipos: restaura
                        var escSel = escolaWrap ? escolaWrap.querySelector('select') : null;
                        if(escSel){
                            if(val==='escola'){
                                if(!escSel.dataset.ureHidden){
                                    var ureOpt = null;
                                    var allOpts = escSel.querySelectorAll('option');
                                    for(var oi=0; oi<allOpts.length; oi++){
                                        var oTxt = allOpts[oi].textContent.toLowerCase();
                                        if(oTxt.indexOf('--')===0) continue;
                                        if(oTxt.indexOf('unidade regional')!==-1 || allOpts[oi].value==='1'){ ureOpt=allOpts[oi]; break; }
                                    }
                                    if(ureOpt){
                                        escSel.dataset.ureBackup = ureOpt.outerHTML;
                                        if(escSel.value===ureOpt.value) escSel.value='';
                                        ureOpt.remove();
                                        escSel.dataset.ureHidden='1';
                                        if(window.jQuery) window.jQuery(escSel).trigger('change');
                                    }
                                }
                            } else if(escSel.dataset.ureHidden){
                                var tpl=document.createElement('template');
                                tpl.innerHTML=(escSel.dataset.ureBackup||'').trim();
                                if(tpl.content.firstChild) escSel.insertBefore(tpl.content.firstChild, escSel.firstChild);
                                delete escSel.dataset.ureHidden; delete escSel.dataset.ureBackup;
                                if(window.jQuery) window.jQuery(escSel).trigger('change');
                            }
                        }
                        // required handling
                        var outroInp = document.getElementById(prefix+'_outro_input');
                        if(outroInp) outroInp.required = val==='outro';
                        // compat: atualiza plugin_protocolo_escolas_id escondido com destino
                        if(prefix==='destino'){
                            var compat = document.getElementById('compat_escola_id');
                            var sel = escolaWrap ? escolaWrap.querySelector('select') : null;
                            if(compat){
                                if(val==='escola' && sel) compat.value = sel.value || '0';
                                else if(val==='ure') compat.value = '0';
                                else compat.value = '0';
                            }
                        }
                    }
                    radios.forEach(r => r.addEventListener('change', update));
                    // escuta mudança no select escola para compat
                    var sel2 = document.getElementById(prefix+'_escola_wrap')?.querySelector('select');
                    if(sel2) sel2.addEventListener('change', function(){
                        if(prefix==='destino'){
                            var c=document.getElementById('compat_escola_id');
                            if(c) c.value = this.value;
                        }
                    });
                    update();
                }
                bindTipo('origem');
                bindTipo('destino');
            }
            document.addEventListener('keydown', function(e){
                var k = e.key || '';
                if(k !== 'Escape' && k !== 'Esc') return;
                var ovPt = document.getElementById('pt-register-overlay');
                if(!ovPt || !ovPt.classList.contains('open')) return;
                var swallowed = false;
                try {
                    if(window.jQuery && window.jQuery('.select2-container--open').length){
                        window.jQuery('select').each(function(){
                            try{
                                var sq = window.jQuery(this);
                                if(sq.data('select2') && sq.select2('isOpen')){ sq.select2('close'); swallowed = true; }
                            }catch(errSq){}
                        });
                    }
                } catch(errJq) {}
                if(!swallowed){
                    var aePt = document.activeElement;
                    if(aePt && aePt.tagName === 'SELECT') swallowed = true;
                }
                if(swallowed){
                    e.preventDefault();
                    if(e.stopImmediatePropagation) e.stopImmediatePropagation();
                    if(e.stopPropagation) e.stopPropagation();
                }
            }, true);
            window.__ptWizStep = 1;
            window.__ptRecDrawn = false;
            function ptWizAlertMsg(msg, el){
                var ab = document.getElementById('protocoloAlert');
                var am = document.getElementById('protocoloAlertMsg');
                if(ab && am){ am.textContent = msg; ab.classList.remove('d-none'); ab.scrollIntoView({behavior:'smooth', block:'center'}); }
                if(el){ el.classList.add('is-invalid'); try{ el.focus(); }catch(ef){} setTimeout(function(){ el.classList.remove('is-invalid'); }, 3000); }
            }
            function ptWizShow(n){
                window.__ptWizStep = n;
                document.querySelectorAll('[data-ptstep]').forEach(function(el){
                    el.style.display = (el.getAttribute('data-ptstep') === String(n)) ? '' : 'none';
                });
                document.querySelectorAll('#pt-wiz-ind .pt-wiz-dot').forEach(function(d){
                    var on = d.getAttribute('data-s') === String(n);
                    d.style.borderColor = on ? '#16a34a' : '#e8eaf0';
                    d.style.background = on ? '#f0fdf4' : '#fff';
                    d.style.color = on ? '#16a34a' : '#9ca3af';
                });
                var bV = document.getElementById('pt-wiz-back');
                if(bV) bV.style.display = n === 1 ? 'none' : '';
                var bF = document.getElementById('pt-wiz-fechar');
                if(bF) bF.style.display = n === 1 ? '' : 'none';
                var bN = document.getElementById('pt-wiz-next');
                if(bN) bN.style.display = n === 5 ? 'none' : '';
                var bS = document.getElementById('pt-reg-submit');
                if(bS) bS.style.display = n === 5 ? '' : 'none';
                if(n === 3) setTimeout(ptRecFit, 60);
                if(n === 4) setTimeout(ptRecbFit, 60);
            }
            window.__ptDevShown = false;
            window.ptDevOk = function(){
                var ov = document.getElementById('pt-dev-overlay');
                if(ov) ov.style.display = 'none';
                ptWizShow(5);
            };
            window.ptWizNav = function(d){
                var cur = window.__ptWizStep || 1;
                if(d > 0){
                    if(cur === 1){
                        var esp = document.getElementById('especieSelect');
                        if(!esp || !esp.value){ ptWizAlertMsg('Selecione a Espécie.', esp); return; }
                        if(esp.value === 'outro'){
                            var eo = document.getElementById('especie_outro_input');
                            if(!eo || !eo.value.trim()){ ptWizAlertMsg('Espécie = Outros: descreva a espécie.', eo); return; }
                        }
                        var asf = document.getElementById('assunto_field');
                        if(!asf || !asf.value.trim()){ ptWizAlertMsg('Preencha o Assunto.', asf); return; }
                    } else if(cur === 2){
                        var os = document.querySelector('input[name=\"origem_tipo\"]:checked');
                        if(!os){ ptWizAlertMsg('Selecione a Origem/Interessado (Escola, URE ou Outros).', document.getElementById('origem_escola')); return; }
                        if(os.value === 'outro'){
                            var oo = document.getElementById('origem_outro_input');
                            if(!oo || !oo.value.trim()){ ptWizAlertMsg('Origem = Outro: preencha \"Escreva a origem\".', oo); return; }
                        } else if(os.value === 'escola'){
                            var ose = document.querySelector('#origem_escola_wrap select');
                            if(!ose || !ose.value){ ptWizAlertMsg('Origem = Escola: selecione a escola.', ose); return; }
                        }
                        var ds = document.querySelector('input[name=\"destino_tipo\"]:checked');
                        if(!ds){ ptWizAlertMsg('Selecione o Destino (Outro, URE ou Escola).', document.getElementById('destino_outro')); return; }
                        if(ds.value === 'outro'){
                            var dout = document.getElementById('destino_outro_input');
                            if(!dout || !dout.value.trim()){ ptWizAlertMsg('Destino = Outro: preencha \"Escreva o destino\".', dout); return; }
                        } else if(ds.value === 'escola'){
                            var dse = document.querySelector('#destino_escola_wrap select');
                            if(!dse || !dse.value){ ptWizAlertMsg('Destino = Escola: selecione a escola.', dse); return; }
                        }
                    } else if(cur === 3){
                        var rn3 = document.querySelector('input[name=\"recebido_de\"]');
                        if(!rn3 || !rn3.value.trim()){ ptWizAlertMsg('Preencha o nome de quem deixou.', rn3); return; }
                        if(!window.__ptRecDrawn){ ptWizAlertMsg('Colete a assinatura de quem deixou no quadro.', document.getElementById('pt-rec-canvas')); return; }
                    } else if(cur === 4){
                        var rb4 = document.querySelector('input[name=\"recebedor_nome\"]');
                        if(!rb4 || !rb4.value.trim()){ ptWizAlertMsg('Preencha o nome de quem recebe.', rb4); return; }
                        if(!window.__ptRecbDrawn){ ptWizAlertMsg('Colete a assinatura de quem recebe no quadro.', document.getElementById('pt-recb-canvas')); return; }
                    }
                }
                if(d > 0 && cur === 4 && !window.__ptDevShown){
                    window.__ptDevShown = true;
                    var dov = document.getElementById('pt-dev-overlay');
                    if(dov) dov.style.display = 'flex';
                    return;
                }
                ptWizShow(Math.min(5, Math.max(1, cur + d)));
            };
            function ptRecFit(){
                var c = document.getElementById('pt-rec-canvas');
                if(!c) return;
                var r = c.getBoundingClientRect();
                c.width = Math.max(280, Math.floor(r.width));
                c.height = 180;
            }
            function ptRecBind(){
                var c = document.getElementById('pt-rec-canvas');
                if(!c || c.dataset.bound) return;
                c.dataset.bound = '1';
                var ctx = c.getContext('2d');
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111827';
                var dw = false;
                function pp(ev){
                    var r = c.getBoundingClientRect();
                    var x = (ev.touches && ev.touches.length) ? ev.touches[0].clientX : ev.clientX;
                    var y = (ev.touches && ev.touches.length) ? ev.touches[0].clientY : ev.clientY;
                    return [(x - r.left) * (c.width / r.width), (y - r.top) * (c.height / r.height)];
                }
                function st(ev){ dw = true; var p = pp(ev); ctx.beginPath(); ctx.moveTo(p[0], p[1]); if(ev.preventDefault) ev.preventDefault(); }
                function mv(ev){ if(!dw) return; var p = pp(ev); ctx.lineTo(p[0], p[1]); ctx.stroke(); window.__ptRecDrawn = true; if(ev.preventDefault) ev.preventDefault(); }
                function en(){ dw = false; }
                c.addEventListener('mousedown', st);
                c.addEventListener('mousemove', mv);
                document.addEventListener('mouseup', en);
                c.addEventListener('touchstart', st, {passive: false});
                c.addEventListener('touchmove', mv, {passive: false});
                c.addEventListener('touchend', en);
                var cl = document.getElementById('pt-rec-clear');
                if(cl) cl.addEventListener('click', function(){ ptRecFit(); var cx = c.getContext('2d'); cx.clearRect(0, 0, c.width, c.height); window.__ptRecDrawn = false; var hi = document.getElementById('pt-rec-image'); if(hi) hi.value = ''; });
            }
            window.__ptS2 = 1;
            window.__ptRecDocType = 'cpf';
            function ptS2Show(m){
                window.__ptS2 = m;
                [1, 2, 3].forEach(function(i){
                    document.querySelectorAll('[data-pts2=\"r' + i + '\"]').forEach(function(el){ el.style.display = (i === m) ? '' : 'none'; });
                });
                var bB = document.getElementById('pt-s2-back');
                if(bB) bB.style.display = m === 1 ? 'none' : '';
                var bN = document.getElementById('pt-s2-next');
                if(bN) bN.style.display = m === 3 ? 'none' : '';
                if(m === 3) setTimeout(ptRecFit, 60);
            }
            window.ptS2Nav = function(d){
                var cur = window.__ptS2 || 1;
                if(d > 0){
                    if(cur === 1){
                        var rn = document.querySelector('input[name=\"recebido_de\"]');
                        if(!rn || !rn.value.trim()){ ptWizAlertMsg('Preencha o nome de quem deixou.', rn); return; }
                    }
                    if(cur === 2) ptRecDocPaint();
                }
                ptS2Show(Math.min(3, Math.max(1, cur + d)));
            };
            window.ptRecDocType = function(t){
                window.__ptRecDocType = (t === 'rg') ? 'rg' : 'cpf';
                var h = document.getElementById('recebido_documento_tipo');
                if(h) h.value = window.__ptRecDocType;
                var hint = document.getElementById('recebido_doc_hint');
                if(hint) hint.textContent = window.__ptRecDocType === 'cpf' ? 'CPF: 11 dígitos' : 'RG: 7 a 9 dígitos';
                ptRecDocPaint();
            };
            function ptRecDocPaint(){
                document.querySelectorAll('.pt-recdoc-btn').forEach(function(b){
                    var on = b.getAttribute('data-t') === (window.__ptRecDocType || 'cpf');
                    b.style.borderColor = on ? '#16a34a' : '';
                    b.style.background = on ? '#f0fdf4' : '';
                    b.style.color = on ? '#16a34a' : '';
                });
            }
            window.__ptS3 = 1;
            window.__ptRecbDrawn = false;
            window.__ptRecbDocType = 'cpf';
            function ptS3Show(m){
                window.__ptS3 = m;
                [1, 2, 3].forEach(function(i){
                    document.querySelectorAll('[data-pts3=\"r' + i + '\"]').forEach(function(el){ el.style.display = (i === m) ? '' : 'none'; });
                });
                var bB3 = document.getElementById('pt-s3-back');
                if(bB3) bB3.style.display = m === 1 ? 'none' : '';
                var bN3 = document.getElementById('pt-s3-next');
                if(bN3) bN3.style.display = m === 3 ? 'none' : '';
                if(m === 3) setTimeout(ptRecbFit, 60);
            }
            window.ptS3Nav = function(d){
                var cur = window.__ptS3 || 1;
                if(d > 0){
                    if(cur === 1){
                        var rb = document.querySelector('input[name=\"recebedor_nome\"]');
                        if(!rb || !rb.value.trim()){ ptWizAlertMsg('Preencha o nome de quem recebe.', rb); return; }
                    }
                }
                ptS3Show(Math.min(3, Math.max(1, cur + d)));
            };
            window.ptRecbDocType = function(t){
                window.__ptRecbDocType = (t === 'rg') ? 'rg' : 'cpf';
                var h = document.getElementById('recebedor_documento_tipo');
                if(h) h.value = window.__ptRecbDocType;
                var hint = document.getElementById('recebedor_doc_hint');
                if(hint) hint.textContent = window.__ptRecbDocType === 'cpf' ? 'CPF: 11 dígitos' : 'RG: 7 a 9 dígitos';
                ptRecbDocPaint();
            };
            function ptRecbDocPaint(){
                document.querySelectorAll('.pt-recbdoc-btn').forEach(function(b){
                    var on = b.getAttribute('data-t') === (window.__ptRecbDocType || 'cpf');
                    b.style.borderColor = on ? '#16a34a' : '';
                    b.style.background = on ? '#f0fdf4' : '';
                    b.style.color = on ? '#16a34a' : '';
                });
            }
            function ptRecbFit(){
                var c = document.getElementById('pt-recb-canvas');
                if(!c) return;
                var r = c.getBoundingClientRect();
                c.width = Math.max(280, Math.floor(r.width));
                c.height = 180;
            }
            function ptRecbBind(){
                var c = document.getElementById('pt-recb-canvas');
                if(!c || c.dataset.bound) return;
                c.dataset.bound = '1';
                var ctx = c.getContext('2d');
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111827';
                var dw = false;
                function pp(ev){
                    var r = c.getBoundingClientRect();
                    var x = (ev.touches && ev.touches.length) ? ev.touches[0].clientX : ev.clientX;
                    var y = (ev.touches && ev.touches.length) ? ev.touches[0].clientY : ev.clientY;
                    return [(x - r.left) * (c.width / r.width), (y - r.top) * (c.height / r.height)];
                }
                function st(ev){ dw = true; var p = pp(ev); ctx.beginPath(); ctx.moveTo(p[0], p[1]); if(ev.preventDefault) ev.preventDefault(); }
                function mv(ev){ if(!dw) return; var p = pp(ev); ctx.lineTo(p[0], p[1]); ctx.stroke(); window.__ptRecbDrawn = true; if(ev.preventDefault) ev.preventDefault(); }
                function en(){ dw = false; }
                c.addEventListener('mousedown', st);
                c.addEventListener('mousemove', mv);
                document.addEventListener('mouseup', en);
                c.addEventListener('touchstart', st, {passive: false});
                c.addEventListener('touchmove', mv, {passive: false});
                c.addEventListener('touchend', en);
                var cl = document.getElementById('pt-recb-clear');
                if(cl) cl.addEventListener('click', function(){ ptRecbFit(); var cx = c.getContext('2d'); cx.clearRect(0, 0, c.width, c.height); window.__ptRecbDrawn = false; var hi = document.getElementById('pt-recb-image'); if(hi) hi.value = ''; });
            }
            if(document.getElementById('pt-wiz-ind')){ ptRecBind(); ptWizShow(1); ptS2Show(1); ptRecDocPaint(); ptRecbBind(); ptS3Show(1); ptRecbDocPaint(); }
            function ptSchoolEnsure(){
                var ov = document.getElementById('pt-school-overlay');
                if(ov) return ov;
                ov = document.createElement('div');
                ov.id = 'pt-school-overlay';
                ov.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(17,24,39,.6);z-index:10080;align-items:center;justify-content:center;padding:20px;';
                var box = document.createElement('div');
                box.style.cssText = 'background:#fff;border-radius:16px;width:100%;max-width:480px;max-height:84vh;display:flex;flex-direction:column;box-shadow:0 25px 80px rgba(0,0,0,.3);';
                var head = document.createElement('div');
                head.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #f0f2f8;';
                var tt = document.createElement('strong');
                tt.style.fontSize = '.95rem';
                tt.textContent = 'Selecionar escola';
                var x = document.createElement('button');
                x.type = 'button';
                x.style.cssText = 'background:#f3f4f6;border:0;border-radius:8px;padding:6px 12px;cursor:pointer;font-weight:800;';
                x.textContent = 'X';
                x.addEventListener('click', ptSchoolClose);
                head.appendChild(tt);
                head.appendChild(x);
                var sWrap = document.createElement('div');
                sWrap.style.cssText = 'padding:12px 14px;border-bottom:1px solid #f0f2f8;';
                var q = document.createElement('input');
                q.type = 'text';
                q.id = 'pt-school-q';
                q.className = 'form-control';
                q.placeholder = 'Digite para pesquisar...';
                q.setAttribute('autocomplete', 'off');
                q.addEventListener('input', function(){ ptSchoolRender(q.value); });
                q.addEventListener('keydown', function(ev){ ev.stopPropagation(); if(ev.key === 'Escape'){ ptSchoolClose(); } });
                sWrap.appendChild(q);
                var list = document.createElement('div');
                list.id = 'pt-school-list';
                list.style.cssText = 'overflow-y:auto;padding:8px;min-height:120px;';
                box.appendChild(head);
                box.appendChild(sWrap);
                box.appendChild(list);
                ov.appendChild(box);
                ov.addEventListener('click', function(e){ if(e.target === ov) ptSchoolClose(); });
                document.body.appendChild(ov);
                return ov;
            }
            window.__ptSchoolSel = null;
            function ptSchoolOpen(sel){
                if(!sel) return;
                window.__ptSchoolSel = sel;
                var ov = ptSchoolEnsure();
                var q = document.getElementById('pt-school-q');
                if(q) q.value = '';
                ptSchoolRender('');
                ov.style.display = 'flex';
                setTimeout(function(){ var qq = document.getElementById('pt-school-q'); if(qq) qq.focus(); }, 60);
            }
            function ptSchoolClose(){
                var ov = document.getElementById('pt-school-overlay');
                if(ov) ov.style.display = 'none';
                window.__ptSchoolSel = null;
            }
            function ptSchoolRender(filter){
                var sel = window.__ptSchoolSel;
                var list = document.getElementById('pt-school-list');
                if(!sel || !list) return;
                list.innerHTML = '';
                var f = (filter || '').toLowerCase();
                var n = 0;
                Array.from(sel.options).forEach(function(o){
                    if(o.value === '' || (f !== '' && o.textContent.toLowerCase().indexOf(f) === -1)) return;
                    n++;
                    var it = document.createElement('div');
                    it.textContent = o.textContent;
                    it.style.cssText = 'padding:11px 12px;cursor:pointer;font-size:.9rem;border-radius:8px;' + ((sel.value === o.value) ? 'background:#f0fdf4;color:#16a34a;font-weight:700;' : '');
                    it.addEventListener('mouseenter', function(){ it.style.background = '#f1f5f9'; });
                    it.addEventListener('mouseleave', function(){ it.style.background = (sel.value === o.value) ? '#f0fdf4' : ''; });
                    it.addEventListener('click', function(){
                        sel.value = o.value;
                        var b = sel._ptBtn;
                        if(b){
                            var so = sel.selectedOptions.length ? sel.selectedOptions[0] : null;
                            b.textContent = (so && so.value !== '') ? so.textContent : '-- Selecione a escola --';
                        }
                        ptSchoolClose();
                        sel.dispatchEvent(new Event('change', {bubbles: true}));
                    });
                    list.appendChild(it);
                });
                if(!n){
                    var em = document.createElement('div');
                    em.style.cssText = 'padding:16px;text-align:center;color:#9ca3af;font-size:.88rem;';
                    em.textContent = 'Nenhuma escola encontrada';
                    list.appendChild(em);
                }
            }
            function ptComboBuild(sel){
                if(!sel || sel.dataset.combo) return;
                sel.dataset.combo = '1';
                sel.style.display = 'none';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'form-control';
                btn.style.cssText = 'width:100%;text-align:left;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
                function curLabel(){
                    var o = sel.selectedOptions.length ? sel.selectedOptions[0] : null;
                    return (o && o.value !== '') ? o.textContent : '-- Selecione a escola --';
                }
                btn.textContent = curLabel();
                sel._ptBtn = btn;
                btn.addEventListener('click', function(ev){ ev.stopPropagation(); ptSchoolOpen(sel); });
                sel.parentElement.insertBefore(btn, sel.nextSibling);
            }
            function ptComboInit(){
                document.querySelectorAll('select.pt-escola-combo').forEach(ptComboBuild);
            }
            ptComboInit();
            setupOrigemDestino();

            // Espécie -> mostra campo livre quando Outros
            var espSel2 = document.getElementById('especieSelect');
            if(espSel2){
                espSel2.addEventListener('change', function(){
                    var w = document.getElementById('especie_outro_wrap');
                    if(w) w.style.display = espSel2.value==='outro' ? 'block' : 'none';
                });
            }

            // Validação sem F5: aviso inline, não perde dados
            (function(){
                var form = document.getElementById('plugin_protocolo_pasta_form');
                var alertBox = document.getElementById('protocoloAlert');
                var alertMsg = document.getElementById('protocoloAlertMsg');
                function showAlert(msg, el){
                    if(!alertBox||!alertMsg) return;
                    alertMsg.textContent = msg;
                    alertBox.classList.remove('d-none');
                    alertBox.scrollIntoView({behavior:'smooth', block:'center'});
                    if(el){ el.classList.add('is-invalid'); el.focus(); setTimeout(function(){ el.classList.remove('is-invalid'); }, 3000); }
                }
                function hideAlert(){ if(alertBox) alertBox.classList.add('d-none'); }
                if(form){
                    form.addEventListener('input', hideAlert);
                    form.addEventListener('change', hideAlert);
                    form.addEventListener('submit', function(e){
                        hideAlert();
                        if(document.getElementById('pt-wiz-ind') && (window.__ptWizStep || 1) < 5){ e.preventDefault(); ptWizNav(1); return; }
                        try{
                            var upEls=form.querySelectorAll('input[type=\"text\"]');
                            for(var ui=0;ui<upEls.length;ui++){
                                var un=upEls[ui].name||'';
                                if(un.indexOf('observacao')!==-1) continue;
                                if(upEls[ui].disabled) continue;
                                upEls[ui].value=upEls[ui].value.toUpperCase();
                            }
                        }catch(ue){}
                        try{
                            var rcv = document.getElementById('pt-rec-canvas');
                            var rhi = document.getElementById('pt-rec-image');
                            if(rcv && rhi && window.__ptRecDrawn){ rhi.value = rcv.toDataURL('image/png'); }
                        }catch(rce){}
                        try{
                            var rbv = document.getElementById('pt-recb-canvas');
                            var bhi = document.getElementById('pt-recb-image');
                            if(rbv && bhi && window.__ptRecbDrawn){ bhi.value = rbv.toDataURL('image/png'); }
                        }catch(bce){}
                        var rcv2 = document.getElementById('pt-rec-canvas');
                        if(rcv2 && !window.__ptRecDrawn){ e.preventDefault(); ptWizShow(3); ptS2Show(3); ptWizAlertMsg('Colete a assinatura de quem deixou no quadro.', rcv2); return; }
                        var rcb2 = document.getElementById('pt-recb-canvas');
                        if(rcb2 && !window.__ptRecbDrawn){ e.preventDefault(); ptWizShow(4); ptS3Show(3); ptWizAlertMsg('Colete a assinatura de quem recebe no quadro.', rcb2); return; }
                        var espSel = form.querySelector('select[name=\"categoria\"]');
                        if(!espSel || !espSel.value){
                            e.preventDefault(); showAlert('Selecione a Espécie.', document.getElementById('especieSelect')); return;
                        }
                        if(espSel.value==='outro'){
                            var espOutro = document.getElementById('especie_outro_input');
                            if(!espOutro || !espOutro.value.trim()){ e.preventDefault(); showAlert('Espécie = Outros: descreva a espécie.', espOutro); return; }
                        }
                        var assunto = document.getElementById('assunto_field');
                        if(!assunto || !assunto.value.trim()){ e.preventDefault(); showAlert('Preencha o Assunto.', assunto); return; }
                        var origemSel = form.querySelector('input[name=\"origem_tipo\"]:checked');
                        if(!origemSel){ e.preventDefault(); showAlert('Selecione a Origem/Interessado (Escola, URE ou Outros).', document.getElementById('origem_escola')); return; }
                        var origemVal = origemSel.value;
                        if(origemVal==='outro'){
                            var oOutro = document.getElementById('origem_outro_input');
                            if(!oOutro || !oOutro.value.trim()){ e.preventDefault(); showAlert('Origem = Outro: preencha \"Escreva a origem\".', oOutro); return; }
                        } else if(origemVal==='escola'){
                            var oSel = document.querySelector('#origem_escola_wrap select');
                            if(!oSel || !oSel.value){ e.preventDefault(); showAlert('Origem = Escola: selecione a escola.', oSel); return; }
                        }
                        var destSel = form.querySelector('input[name=\"destino_tipo\"]:checked');
                        if(!destSel){ e.preventDefault(); showAlert('Selecione o Destino (Outro, URE ou Escola).', document.getElementById('destino_outro')); return; }
                        var destVal = destSel.value;
                        if(destVal==='outro'){
                            var dOutro = document.getElementById('destino_outro_input');
                            if(!dOutro || !dOutro.value.trim()){ e.preventDefault(); showAlert('Destino = Outro: preencha \"Escreva o destino\".', dOutro); return; }
                        } else if(destVal==='escola'){
                            var dSel = document.querySelector('#destino_escola_wrap select');
                            if(!dSel || !dSel.value){ e.preventDefault(); showAlert('Destino = Escola: selecione a escola.', dSel); return; }
                        }
                        var recDe = form.querySelector('input[name=\"recebido_de\"]');
                        if(!recDe || !recDe.value.trim()){ e.preventDefault(); showAlert('Preencha \"Recebido de (quem deixou)\".', recDe); return; }
                        var tiposChk = form.querySelectorAll('input[name=\"tipos[]\"]:checked');
                        if(tiposChk.length===0){ e.preventDefault(); showAlert('Marque pelo menos 1 tipo de arquivo.', document.querySelector('.tipo-check')); return; }
                        var itensDesc = form.querySelectorAll('input[name*=\"[descricao]\"]');
                        var hasItem=false; itensDesc.forEach(function(inp){ if(inp.value.trim()) hasItem=true; });
                        if(!hasItem){ e.preventDefault(); showAlert('Adicione pelo menos 1 item com descrição.', document.querySelector('input[name*=\"[descricao]\"]')); return; }
                        // Dentro da janela flutuante: envia via AJAX (fica na tela; erro vira popup)
                        if (form.closest && form.closest('#pt-register-overlay') && typeof window.ptSubmitRegisterAjax === 'function') {
                            e.preventDefault();
                            window.ptSubmitRegisterAjax(form);
                            return;
                        }
                    });
                }
            })();

            // Tipos -> Itens auto preenchimento (fallback inline caso js/app.js não carregue)
            var tipoChecks = document.querySelectorAll('.tipo-check');
            var wrap = document.getElementById('itensWrap');
            var btnAdd = document.getElementById('btnAddItem');
            if(tipoChecks.length && wrap){
                tipoChecks.forEach(function(chk){
                    chk.addEventListener('change', function(){
                        var tid = chk.value;
                        var nome = chk.dataset.nome || chk.nextElementSibling?.textContent.trim() || 'Item';
                        if(chk.checked){
                            if(wrap.querySelector('.item-row[data-tipo-id=\"'+tid+'\"]')) return;
                            var rows = wrap.querySelectorAll('.item-row');
                            var targetRow = null;
                            if(rows.length===1){
                                var inp = rows[0].querySelector('input[name*=\"[descricao]\"]');
                                if(inp && inp.value.trim()==='' && !rows[0].dataset.tipoId) targetRow=rows[0];
                            }
                            if(targetRow){
                                targetRow.dataset.tipoId=tid;
                                var inp2 = targetRow.querySelector('input[name*=\"[descricao]\"]');
                                if(inp2) inp2.value=nome;
                                targetRow.classList.add('border','border-warning','rounded','p-1');
                            } else {
                                var idx = wrap.querySelectorAll('.item-row').length;
                                var row = document.createElement('div');
                                row.className='row g-2 mb-2 item-row border border-warning rounded p-1';
                                row.dataset.tipoId=tid;
                                row.innerHTML='<div class=\"col-md-7\"><input name=\"itens['+idx+'][descricao]\" class=\"form-control\" value=\"'+nome.replace(/\"/g,'&quot;')+'\" required></div><div class=\"col-md-2\"><input name=\"itens['+idx+'][quantidade]\" type=\"number\" min=\"1\" value=\"1\" class=\"form-control\" placeholder=\"Qtd\"></div><div class=\"col-md-2\"><input name=\"itens['+idx+'][observacao]\" class=\"form-control\" placeholder=\"Obs.\"></div><div class=\"col-md-1\"><button type=\"button\" class=\"btn btn-outline-danger w-100 btnRemove\"><i class=\"ti ti-trash\"></i></button></div>';
                                wrap.appendChild(row);
                            }
                        } else {
                            var row2 = wrap.querySelector('.item-row[data-tipo-id=\"'+tid+'\"]');
                            if(row2){ row2.remove(); if(wrap.querySelectorAll('.item-row').length===0 && btnAdd) btnAdd.click(); }
                        }
                    });
                });
            }
        });
        </script>";

        // NOTA: public/js/app.js já é carregado via hook add_javascript em todas as páginas;
        // tag inline aqui duplicaria a execução (handlers duplos), então não incluir.
        echo "</div>"; // fecha .pt-page

        return true;
    }

    public function prepareInputForAdd($input)
    {
        global $DB;
        // Espécie (substitui Categoria — obrigatória, sem padrão, força atenção)
        $categoria = strtolower(trim($input['categoria'] ?? ''));
        if (!array_key_exists($categoria, self::getEspecieOptions())) {
            Session::addMessageAfterRedirect(__('Selecione a Espécie', 'protocolo'), false, ERROR);
            return false;
        }
        $input['categoria'] = $categoria;
        if ($categoria === 'outro') {
            $espOutro = trim($input['especie_outro'] ?? '');
            if ($espOutro === '') {
                Session::addMessageAfterRedirect(__('Espécie: descreva em Outros', 'protocolo'), false, ERROR);
                return false;
            }
            $input['especie_outro'] = $espOutro;
        } else {
            $input['especie_outro'] = null;
        }

        // Assunto (obrigatório)
        $assunto = trim($input['assunto'] ?? '');
        if ($assunto === '') {
            Session::addMessageAfterRedirect(__('Preencha o Assunto', 'protocolo'), false, ERROR);
            return false;
        }
        $input['assunto'] = $assunto;

        // Data + Hora (campos separados) → data_recebimento
        $dataP = trim($input['data_recebimento_date'] ?? '');
        $horaP = trim($input['data_recebimento_time'] ?? '');
        if ($dataP !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataP)) {
            if (!preg_match('/^\d{2}:\d{2}$/', $horaP)) {
                $horaP = date('H:i');
            }
            $input['data_recebimento'] = $dataP . ' ' . $horaP . ':00';
        }
        unset($input['data_recebimento_date'], $input['data_recebimento_time']);

        // Origem (obrigatório)
        $origemTipo = strtolower(trim($input['origem_tipo'] ?? ''));
        if (!in_array($origemTipo, ['outro','ure','escola'])) {
            Session::addMessageAfterRedirect(__('Selecione a Origem (Outro, URE ou Escola)', 'protocolo'), false, ERROR);
            return false;
        }
        $input['origem_tipo'] = $origemTipo;
        if ($origemTipo === 'outro') {
            $outro = trim($input['origem_outro'] ?? '');
            if ($outro === '') {
                Session::addMessageAfterRedirect(__('Origem: preencha o campo Outro', 'protocolo'), false, ERROR);
                return false;
            }
            $input['origem_outro'] = $outro;
            $input['origem_entities_id'] = null;
        } elseif ($origemTipo === 'ure') {
            $input['origem_outro'] = null;
            $input['origem_entities_id'] = 0;
        } else { // escola
            $eid = (int)($input['origem_entities_id'] ?? 0);
            if ($eid <= 0) {
                Session::addMessageAfterRedirect(__('Origem: selecione a Escola', 'protocolo'), false, ERROR);
                return false;
            }
            $input['origem_entities_id'] = $eid;
            $input['origem_outro'] = null;
        }

        // Destino (obrigatório)
        $destinoTipo = strtolower(trim($input['destino_tipo'] ?? ''));
        if (!in_array($destinoTipo, ['outro','ure','escola'])) {
            Session::addMessageAfterRedirect(__('Selecione o Destino (Outro, URE ou Escola)', 'protocolo'), false, ERROR);
            return false;
        }
        $input['destino_tipo'] = $destinoTipo;
        if ($destinoTipo === 'outro') {
            $outro = trim($input['destino_outro'] ?? '');
            if ($outro === '') {
                Session::addMessageAfterRedirect(__('Destino: preencha o campo Outro', 'protocolo'), false, ERROR);
                return false;
            }
            $input['destino_outro'] = $outro;
            $input['destino_entities_id'] = null;
        } elseif ($destinoTipo === 'ure') {
            $input['destino_outro'] = null;
            $input['destino_entities_id'] = 0;
        } else {
            $eid = (int)($input['destino_entities_id'] ?? 0);
            if ($eid <= 0) {
                Session::addMessageAfterRedirect(__('Destino: selecione a Escola', 'protocolo'), false, ERROR);
                return false;
            }
            $input['destino_entities_id'] = $eid;
            $input['destino_outro'] = null;
        }

        // Compat: plugin_protocolo_escolas_id espelha destino quando escola, senão origem
        if ($destinoTipo === 'escola') {
            $input['plugin_protocolo_escolas_id'] = $input['destino_entities_id'];
        } elseif ($origemTipo === 'escola') {
            $input['plugin_protocolo_escolas_id'] = $input['origem_entities_id'];
        } else {
            $input['plugin_protocolo_escolas_id'] = 0;
        }

        if (empty($input['recebido_de'])) {
            error_log("[protocolo][lab-debug] prepareInputForAdd falhou: recebido_de vazio POST=" . json_encode($input, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR));
            Session::addMessageAfterRedirect(__('Recebido de é obrigatório', 'protocolo'), false, ERROR);
            return false;
        }
        $recSig = trim($input['recebido_assinatura_image'] ?? '');
        if ($recSig !== '' && strpos($recSig, 'data:image/') !== 0) $recSig = '';
        if (strlen($recSig) > 1500000) $recSig = '';
        try {
            if ($DB->fieldExists(self::getTable(), 'recebido_assinatura_image')) {
                $input['recebido_assinatura_image'] = $recSig !== '' ? $recSig : null;
                $input['recebido_assinatura_data'] = $recSig !== '' ? date('Y-m-d H:i:s') : null;
                $input['recebido_assinatura_ip'] = $recSig !== '' ? ($_SERVER['REMOTE_ADDR'] ?? null) : null;
            } else {
                unset($input['recebido_assinatura_image']);
            }
        } catch (\Throwable $e) { unset($input['recebido_assinatura_image']); }
        $recbNome = trim($input['recebedor_nome'] ?? '');
        if ($recbNome === '') {
            Session::addMessageAfterRedirect(__('Informe o nome de quem recebe', 'protocolo'), false, ERROR);
            return false;
        }
        $input['recebedor_nome'] = $recbNome;
        $recbDocTipo = strtolower(trim($input['recebedor_documento_tipo'] ?? 'cpf'));
        if (!in_array($recbDocTipo, ['cpf', 'rg'], true)) $recbDocTipo = 'cpf';
        $input['recebedor_documento_tipo'] = $recbDocTipo;
        $input['recebedor_documento'] = trim($input['recebedor_documento'] ?? '');
        $recbSig = trim($input['recebedor_assinatura_image'] ?? '');
        if ($recbSig !== '' && strpos($recbSig, 'data:image/') !== 0) $recbSig = '';
        if (strlen($recbSig) > 1500000) $recbSig = '';
        try {
            if ($DB->fieldExists(self::getTable(), 'recebedor_assinatura_image')) {
                if ($recSig === '' || $recbSig === '') {
                    Session::addMessageAfterRedirect(__('Colete as duas assinaturas (quem deixa e quem recebe)', 'protocolo'), false, ERROR);
                    return false;
                }
                $input['recebedor_assinatura_image'] = $recbSig;
                $input['recebedor_assinatura_data'] = date('Y-m-d H:i:s');
                $input['recebedor_assinatura_ip'] = $_SERVER['REMOTE_ADDR'] ?? null;
            } else {
                unset($input['recebedor_assinatura_image']);
            }
        } catch (\Throwable $e) { unset($input['recebedor_assinatura_image']); }
        // Itens validation
        $itens = $input['itens'] ?? [];
        $filtered = [];
        foreach ($itens as $it) {
            $desc = trim($it['descricao'] ?? '');
            if ($desc === '') continue;
            $filtered[] = ['descricao' => $desc, 'quantidade' => max(1, (int)($it['quantidade'] ?? 1)), 'observacao' => trim($it['observacao'] ?? '')];
        }
        if (count($filtered) === 0) {
            error_log("[protocolo][lab-debug] prepareInputForAdd falhou: nenhum item filtrado POST.itens=" . json_encode($input['itens'] ?? [], JSON_UNESCAPED_UNICODE));
            Session::addMessageAfterRedirect(__('Adicione pelo menos 1 item', 'protocolo'), false, ERROR);
            return false;
        }
        $tipos = array_filter(array_map('intval', (array)($input['tipos'] ?? [])));
        // Só valida tipos se houver tipos ativos
        $ativos = TipoArquivo::getAllActive();
        if (!empty($ativos) && count($tipos) === 0) {
            error_log("[protocolo][lab-debug] prepareInputForAdd falhou: nenhum tipo marcado ativos=" . count($ativos) . " POST.tipos=" . json_encode($input['tipos'] ?? [], JSON_UNESCAPED_UNICODE));
            Session::addMessageAfterRedirect(__('Selecione pelo menos 1 tipo de arquivo', 'protocolo'), false, ERROR);
            return false;
        }

        // Valida documento (CPF/RG)
        $tipoRec = strtolower(trim($input['recebido_documento_tipo'] ?? 'cpf'));
        if (!in_array($tipoRec, ['cpf','rg'])) $tipoRec = 'cpf';
        $input['recebido_documento_tipo'] = $tipoRec;
        $docRec = trim($input['recebido_documento'] ?? '');
        if ($docRec !== '') {
            $ok = ($tipoRec === 'cpf') ? self::validarCPF($docRec) : self::validarRG($docRec);
            if (!$ok) {
                error_log("[protocolo][lab-debug] prepareInputForAdd falhou: doc invalido tipo=$tipoRec doc=$docRec");
                $msg = $tipoRec === 'cpf' ? __('CPF inválido. Deve ter 11 dígitos válidos (000.000.000-00)', 'protocolo') : __('RG inválido. Deve ter 7 a 9 dígitos (00.000.000-0)', 'protocolo');
                Session::addMessageAfterRedirect($msg, false, ERROR);
                return false;
            }
        }

        // Gera código e datas (prefixo depende da categoria)
        $input['codigo'] = Install::gerarCodigoPasta($input['categoria'] ?? 'pasta');
        $input['status'] = 'aguardando';
        $input['data_recebimento'] = $input['data_recebimento'] ?? date('Y-m-d H:i:s');
        if (strpos($input['data_recebimento'], 'T') !== false) {
            $input['data_recebimento'] = str_replace('T', ' ', $input['data_recebimento']);
            if (strlen($input['data_recebimento']) === 16) $input['data_recebimento'] .= ':00';
        }
        $input['users_id'] = Session::getLoginUserID();
        $input['entities_id'] = $_SESSION['glpiactive_entity'] ?? 0;
        $input['is_recursive'] = 0;

        // Guarda temporariamente para post_addItem
        $input['_itens'] = $filtered;
        $input['_tipos'] = $tipos;
        unset($input['itens'], $input['tipos']);

        return $input;
    }

    public function post_addItem()
    {
        global $DB;
        $id = $this->getID();
        $input = $this->input ?? [];

        // Insere itens e tipos que foram preparados
        if (!empty($input['_itens'])) {
            foreach ($input['_itens'] as $iv) {
                $DB->insert('glpi_plugin_protocolo_itens', [
                    'plugin_protocolo_pastas_id' => $id,
                    'name' => $iv['descricao'],
                    'quantidade' => $iv['quantidade'],
                    'comment' => $iv['observacao'] ?: null
                ]);
            }
        }
        if (!empty($input['_tipos'])) {
            foreach ($input['_tipos'] as $tid) {
                $DB->insert('glpi_plugin_protocolo_pastatipos', [
                    'plugin_protocolo_pastas_id' => $id,
                    'plugin_protocolo_tipos_id' => $tid
                ]);
            }
        }
        // Cria termo de recebimento pendente
        $codigoTermo = Install::gerarCodigoTermo('recebimento');
        $DB->insert('glpi_plugin_protocolo_termos', [
            'plugin_protocolo_pastas_id' => $id,
            'tipo' => 'recebimento',
            'codigo' => $codigoTermo,
            'hash_verificacao' => bin2hex(random_bytes(16)),
            'users_id' => Session::getLoginUserID(),
            'date_creation' => date('Y-m-d H:i:s')
        ]);

        // Notificação automática de entrada
        try {
            if (class_exists(Notificacao::class)) {
                Notificacao::createForPasta($this, 'entrada');
            }
        } catch (\Throwable $e) {
            error_log("[protocolo] Notificacao entrada falhou: " . $e->getMessage());
        }
    }

    public function prepareInputForUpdate($input)
    {
        // Espécie / Origem / Destino (se enviados)
        if (isset($input['categoria'])) {
            $cat = strtolower(trim($input['categoria']));
            if (!array_key_exists($cat, self::getEspecieOptions())) {
                Session::addMessageAfterRedirect(__('Espécie inválida', 'protocolo'), false, ERROR);
                return false;
            }
            $input['categoria'] = $cat;
            if ($cat === 'outro') {
                $espOutro = trim($input['especie_outro'] ?? $this->fields['especie_outro'] ?? '');
                if ($espOutro === '') {
                    Session::addMessageAfterRedirect(__('Espécie: descreva em Outros', 'protocolo'), false, ERROR);
                    return false;
                }
                $input['especie_outro'] = $espOutro;
            } else {
                $input['especie_outro'] = null;
            }
        }
        if (isset($input['assunto'])) {
            $input['assunto'] = trim($input['assunto']);
        }
        // Data + Hora (campos separados) → data_recebimento
        if (isset($input['data_recebimento_date'])) {
            $dataP = trim($input['data_recebimento_date']);
            $horaP = trim($input['data_recebimento_time'] ?? '');
            if ($dataP !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataP)) {
                if (!preg_match('/^\d{2}:\d{2}$/', $horaP)) {
                    $horaP = '00:00';
                }
                $input['data_recebimento'] = $dataP . ' ' . $horaP . ':00';
            }
            unset($input['data_recebimento_date'], $input['data_recebimento_time']);
        }
        if (isset($input['origem_tipo']) || isset($input['origem_outro']) || isset($input['origem_entities_id'])) {
            $origemTipo = strtolower(trim($input['origem_tipo'] ?? $this->fields['origem_tipo'] ?? 'ure'));
            if (!in_array($origemTipo, ['outro','ure','escola'])) {
                Session::addMessageAfterRedirect(__('Origem tipo inválido', 'protocolo'), false, ERROR);
                return false;
            }
            $input['origem_tipo'] = $origemTipo;
            if ($origemTipo === 'outro') {
                $outro = trim($input['origem_outro'] ?? $this->fields['origem_outro'] ?? '');
                if ($outro === '') { Session::addMessageAfterRedirect(__('Origem Outro vazio', 'protocolo'), false, ERROR); return false; }
                $input['origem_outro'] = $outro;
                $input['origem_entities_id'] = null;
            } elseif ($origemTipo === 'ure') {
                $input['origem_outro'] = null;
                $input['origem_entities_id'] = 0;
            } else {
                $eid = (int)($input['origem_entities_id'] ?? $this->fields['origem_entities_id'] ?? 0);
                if ($eid <= 0) { Session::addMessageAfterRedirect(__('Origem Escola inválida', 'protocolo'), false, ERROR); return false; }
                $input['origem_entities_id'] = $eid;
                $input['origem_outro'] = null;
            }
        }
        if (isset($input['destino_tipo']) || isset($input['destino_outro']) || isset($input['destino_entities_id'])) {
            $destinoTipo = strtolower(trim($input['destino_tipo'] ?? $this->fields['destino_tipo'] ?? 'escola'));
            if (!in_array($destinoTipo, ['outro','ure','escola'])) {
                Session::addMessageAfterRedirect(__('Destino tipo inválido', 'protocolo'), false, ERROR);
                return false;
            }
            $input['destino_tipo'] = $destinoTipo;
            if ($destinoTipo === 'outro') {
                $outro = trim($input['destino_outro'] ?? $this->fields['destino_outro'] ?? '');
                if ($outro === '') { Session::addMessageAfterRedirect(__('Destino Outro vazio', 'protocolo'), false, ERROR); return false; }
                $input['destino_outro'] = $outro;
                $input['destino_entities_id'] = null;
            } elseif ($destinoTipo === 'ure') {
                $input['destino_outro'] = null;
                $input['destino_entities_id'] = 0;
            } else {
                $eid = (int)($input['destino_entities_id'] ?? $this->fields['destino_entities_id'] ?? 0);
                if ($eid <= 0) { Session::addMessageAfterRedirect(__('Destino Escola inválida', 'protocolo'), false, ERROR); return false; }
                $input['destino_entities_id'] = $eid;
                $input['destino_outro'] = null;
            }
            // compat
            if ($destinoTipo === 'escola') $input['plugin_protocolo_escolas_id'] = $input['destino_entities_id'];
            elseif (isset($input['origem_entities_id']) && $input['origem_tipo']==='escola') $input['plugin_protocolo_escolas_id'] = $input['origem_entities_id'];
        }
        // Só permite atualizar campos básicos se ainda aguardando; se retirada, só obs?
        // Por simplicidade permite editar recebido_de, observacao, etc se tiver UPDATE
        if (isset($input['recebido_de']) && empty(trim($input['recebido_de']))) {
            Session::addMessageAfterRedirect(__('Recebido de não pode ficar vazio', 'protocolo'), false, ERROR);
            return false;
        }
        if (isset($input['data_recebimento']) && strpos($input['data_recebimento'], 'T') !== false) {
            $input['data_recebimento'] = str_replace('T', ' ', $input['data_recebimento']);
            if (strlen($input['data_recebimento']) === 16) $input['data_recebimento'] .= ':00';
        }
        if (isset($input['recebido_documento']) || isset($input['recebido_documento_tipo'])) {
            $tipo = strtolower(trim($input['recebido_documento_tipo'] ?? $this->fields['recebido_documento_tipo'] ?? 'cpf'));
            if (!in_array($tipo, ['cpf','rg'])) $tipo = 'cpf';
            $input['recebido_documento_tipo'] = $tipo;
            $doc = trim($input['recebido_documento'] ?? $this->fields['recebido_documento'] ?? '');
            if ($doc !== '') {
                $ok = ($tipo === 'cpf') ? self::validarCPF($doc) : self::validarRG($doc);
                if (!$ok) {
                    $msg = $tipo === 'cpf' ? __('CPF inválido', 'protocolo') : __('RG inválido', 'protocolo');
                    Session::addMessageAfterRedirect($msg, false, ERROR);
                    return false;
                }
            }
        }
        return $input;
    }

    // Ações custom: retirar, cancelar, reabrir (chamadas via front/pasta.form.php)
    public function doRetirar(array $params): bool
    {
        global $DB;
        if ($this->fields['status'] !== 'aguardando') {
            Session::addMessageAfterRedirect(__('Pasta não está aguardando', 'protocolo'), false, ERROR);
            return false;
        }
        $retiradoPor = trim($params['retirado_por'] ?? '');
        if ($retiradoPor === '') {
            Session::addMessageAfterRedirect(__('Informe quem retirou', 'protocolo'), false, ERROR);
            return false;
        }
        $dataRet = trim($params['data_retirada'] ?? '');
        if ($dataRet === '') $dataRet = date('Y-m-d H:i:s');
        else {
            $dataRet = str_replace('T', ' ', $dataRet);
            if (strlen($dataRet) === 16) $dataRet .= ':00';
        }
        $tipoRet = strtolower(trim($params['retirado_documento_tipo'] ?? 'cpf'));
        if (!in_array($tipoRet, ['cpf','rg'])) $tipoRet = 'cpf';
        $docRet = trim($params['retirado_documento'] ?? '');
        if ($docRet !== '') {
            $ok = ($tipoRet === 'cpf') ? self::validarCPF($docRet) : self::validarRG($docRet);
            if (!$ok) {
                $msg = $tipoRet === 'cpf' ? __('CPF inválido na retirada. Deve ter 11 dígitos válidos', 'protocolo') : __('RG inválido na retirada. Deve ter 7 a 9 dígitos', 'protocolo');
                Session::addMessageAfterRedirect($msg, false, ERROR);
                return false;
            }
        }
        $sigImage = trim($params['retirada_assinatura_image'] ?? '');
        if ($sigImage !== '' && strpos($sigImage, 'data:image/') !== 0) $sigImage = '';
        if (strlen($sigImage) > 1500000) $sigImage = '';
        $sigData = date('Y-m-d H:i:s');
        $sigIp = $_SERVER['REMOTE_ADDR'] ?? null;
        $updRetirada = [
            'status' => 'retirada',
            'data_retirada' => $dataRet,
            'retirado_por' => $retiradoPor,
            'retirado_documento' => $docRet ?: null,
            'retirado_documento_tipo' => $tipoRet,
            'observacao_retirada' => trim($params['observacao_retirada'] ?? '') ?: null,
            'users_id_retirada' => Session::getLoginUserID(),
            'date_mod' => date('Y-m-d H:i:s')
        ];
        try {
            if ($DB->fieldExists(self::getTable(), 'retirada_assinatura_image')) {
                $updRetirada['retirada_assinatura_image'] = $sigImage !== '' ? $sigImage : null;
                $updRetirada['retirada_assinatura_data'] = $sigImage !== '' ? $sigData : null;
                $updRetirada['retirada_assinatura_ip'] = $sigImage !== '' ? $sigIp : null;
            }
        } catch (\Throwable $e) {}
        $DB->update(self::getTable(), $updRetirada, ['id' => $this->getID()]);

        // cria termo retirada
        $codigo = Install::gerarCodigoTermo('retirada');
        $DB->insert('glpi_plugin_protocolo_termos', [
            'plugin_protocolo_pastas_id' => $this->getID(),
            'tipo' => 'retirada',
            'codigo' => $codigo,
            'hash_verificacao' => bin2hex(random_bytes(16)),
            'users_id' => Session::getLoginUserID(),
            'date_creation' => date('Y-m-d H:i:s')
        ]);
        // Atualiza fields em memória para notificação usar dados frescos
        $this->fields['status'] = 'retirada';
        $this->fields['data_retirada'] = $dataRet;
        $this->fields['retirado_por'] = $retiradoPor;
        $this->fields['retirado_documento'] = $docRet;
        $this->fields['retirado_documento_tipo'] = $tipoRet;
        $this->fields['observacao_retirada'] = trim($params['observacao_retirada'] ?? '') ?: null;
        $this->fields['retirada_assinatura_image'] = $sigImage !== '' ? $sigImage : null;
        $this->fields['retirada_assinatura_data'] = $sigImage !== '' ? $sigData : null;
        // Notificação automática de retirada
        try {
            if (class_exists(Notificacao::class)) {
                Notificacao::createForPasta($this, 'retirada');
            }
        } catch (\Throwable $e) {
            error_log("[protocolo] Notificacao retirada falhou: " . $e->getMessage());
        }
        Session::addMessageAfterRedirect(__('Retirada registrada! Agora gere o Termo de Retirada.', 'protocolo'), false, INFO);
        return true;
    }

    public function doCancelar(): bool
    {
        global $DB;
        if ($this->fields['status'] !== 'aguardando') return false;
        $DB->update(self::getTable(), ['status' => 'cancelada', 'date_mod' => date('Y-m-d H:i:s')], ['id' => $this->getID()]);
        Session::addMessageAfterRedirect(__('Pasta cancelada', 'protocolo'), false, INFO);
        return true;
    }

    public function doReabrir(): bool
    {
        global $DB;
        if ($this->fields['status'] === 'aguardando') return false;
        $DB->update(self::getTable(), ['status' => 'aguardando', 'data_retirada' => null, 'retirado_por' => null, 'date_mod' => date('Y-m-d H:i:s')], ['id' => $this->getID()]);
        Session::addMessageAfterRedirect(__('Pasta reaberta para aguardando', 'protocolo'), false, INFO);
        return true;
    }

    public static function limparDocumento(string $doc): string
    {
        return preg_replace('/[^0-9Xx]/', '', $doc);
    }

    public static function validarCPF(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11) return false;
        if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) $d += $cpf[$c] * (($t + 1) - $c);
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) return false;
        }
        return true;
    }

    public static function validarRG(string $rg): bool
    {
        $rg = preg_replace('/[^0-9Xx]/', '', $rg);
        $len = strlen($rg);
        // RG: 7 a 9 dígitos (SP 9, outros 7-8), permite X no final
        if ($len < 7 || $len > 9) return false;
        if (!preg_match('/^[0-9]{7,8}[0-9Xx]?$/', $rg)) return false;
        return true;
    }

    public static function getEscolaName(int $escolaId): string
    {
        static $cache = [];
        if (isset($cache[$escolaId])) return $cache[$escolaId];
        global $DB;
        // ESCOLA = ENTIDADE: tenta glpi_entities primeiro, fallback para tabela antiga glpi_plugin_protocolo_escolas
        $it = $DB->request(['FROM' => 'glpi_entities', 'WHERE' => ['id' => $escolaId], 'LIMIT' => 1]);
        foreach ($it as $r) {
            $full = $r['completename'] ?? $r['name'];
            $parts = explode('>', (string)$full);
            $cache[$escolaId] = trim(end($parts));
            return $cache[$escolaId];
        }
        $it2 = $DB->request(['FROM' => Escola::getTable(), 'WHERE' => ['id' => $escolaId], 'LIMIT' => 1]);
        foreach ($it2 as $r) { $cache[$escolaId] = $r['name']; return $cache[$escolaId]; }
        $cache[$escolaId] = '-';
        return '-';
    }

    /**
     * Retorna URL web correta sem duplicar root_doc.
     * Plugin::getWebDir já inclui root_doc em GLPI 11 (ex: /glpi/marketplace/...), então evita /glpi/glpi/...
     */
    private static function getProtocoloWebDir(): string
    {
        $web = Plugin::getWebDir('protocolo');
        // Se $web já está vazio por algum motivo, fallback
        if ($web === '' || $web === null) {
            $web = '/plugins/protocolo';
        }
        return $web;
    }

    public static function getSearchURL($full = true)
    {
        // Plugin::getWebDir já retorna com root_doc em GLPI 11; não prepend de novo
        // Para compat com GLPI onde getWebDir retorna sem root_doc, adiciona se necessário
        $web = self::getProtocoloWebDir();
        $root = $GLOBALS['CFG_GLPI']['root_doc'] ?? '';
        if ($full && $root !== '' && !str_starts_with($web, $root) && str_starts_with($web, '/')) {
            // getWebDir retornou sem root_doc (ex: /plugins/...), então prepend
            return $root . $web . '/front/pasta.php';
        }
        // Se full=false, GLPI menu espera sem root_doc; remove se já tem
        if (!$full && $root !== '' && str_starts_with($web, $root)) {
            return substr($web, strlen($root)) . '/front/pasta.php';
        }
        return $web . '/front/pasta.php';
    }

    public static function getFormURL($full = true)
    {
        $web = self::getProtocoloWebDir();
        $root = $GLOBALS['CFG_GLPI']['root_doc'] ?? '';
        if ($full && $root !== '' && !str_starts_with($web, $root) && str_starts_with($web, '/')) {
            return $root . $web . '/front/pasta.form.php';
        }
        if (!$full && $root !== '' && str_starts_with($web, $root)) {
            return substr($web, strlen($root)) . '/front/pasta.form.php';
        }
        return $web . '/front/pasta.form.php';
    }

    public static function getFormURLWithID($id = 0, $full = true)
    {
        return self::getFormURL($full) . '?id=' . (int)$id;
    }

    // Massive actions desabilitadas temporariamente para compatibilidade GLPI 11 (assinatura mudou em CommonDBTM::getMassiveActionsForItem(): MassiveAction)
    // Para reativar, implementar conforme nova API GLPI 11:
    // public function getMassiveActionsForItem(): array { ... }
    // public static function showMassiveActionsSubForm(MassiveAction $ma) { ... }
    // public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) { ... }
}
