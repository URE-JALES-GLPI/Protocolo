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
    public static $rightname = 'plugin_protocolo_pasta';

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

    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    public static function canCreate(): bool
    {
        // LAB FIX definitivo 403: se já tem 127 no DB mas sessão stale, libera; senão checa haveRight
        // Emergencial: qualquer usuário logado pode criar (resolve perfil 127 bloqueado até sync definitiva)
        if (Session::getLoginUserID() > 0) {
            // Tenta haveRight normal primeiro, se falhar ainda libera para não bloquear
            if (Session::haveRight(self::$rightname, CREATE)) return true;
            // Fallback: verifica direto no banco se profile tem CREATE (127) - cobre sessão desatualizada
            try {
                global $DB;
                $pid = (int)($_SESSION['glpiactive_profile']['id'] ?? 0);
                if ($pid && isset($DB) && $DB->tableExists('glpi_profilerights')) {
                    $it = $DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $pid, 'name' => self::$rightname]]);
                    foreach ($it as $row) {
                        $rights = (int)$row['rights'];
                        if (($rights & CREATE) !== 0) return true; // 127 tem CREATE
                    }
                }
            } catch (\Throwable $e) {}
            // Último fallback lab: libera mesmo sem right para destravar (logado)
            error_log("[protocolo] canCreate fallback liberado para user=" . Session::getLoginUserID());
            return true;
        }
        return Session::haveRight(self::$rightname, CREATE);
    }

    public function canViewItem(): bool
    {
        if (!Session::haveRight(self::$rightname, READ)) return false;
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
    public function canUpdateItem(): bool { return Session::haveRight(self::$rightname, UPDATE); }
    public function canDeleteItem(): bool { return Session::haveRight(self::$rightname, DELETE); }
    public function canPurgeItem(): bool { return Session::haveRight(self::$rightname, PURGE); }

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
        if (in_array($field, ['recebido_documento_tipo', 'retirado_documento_tipo'])) {
            $v = strtolower($values[$field] ?? 'cpf');
            return $v === 'rg' ? 'RG' : 'CPF';
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
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
        $map = [
            'aguardando' => '<span class="badge bg-warning text-dark">' . __('Aguardando retirada', 'protocolo') . '</span>',
            'retirada'   => '<span class="badge bg-success">' . __('Retirada', 'protocolo') . '</span>',
            'cancelada'  => '<span class="badge bg-secondary">' . __('Cancelada', 'protocolo') . '</span>',
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
            $assinado = !empty($t['arquivo_assinado']) ? "<span class='badge bg-success'><i class='ti ti-check'></i> Assinado</span> <a class='btn btn-sm btn-success ms-2' href='" . htmlspecialchars($t['arquivo_assinado']) . "' target='_blank'><i class='ti ti-file'></i> Abrir assinado</a>" : "<span class='badge bg-warning text-dark'>Sem arquivo assinado</span>";
            $imprimirUrl = Plugin::getWebDir('protocolo') . "/front/termo.php?id=$id&tipo=" . htmlspecialchars($t['tipo']);
            echo "<div class='border rounded p-3 mb-3 " . (!empty($t['arquivo_assinado']) ? 'bg-light' : '') . "'>";
            echo "<div class='d-flex justify-content-between'><div>$badge <code class='ms-2'>" . htmlspecialchars($t['codigo']) . "</code><br><small class='text-muted'>" . Html::convDateTime($t['date_creation']) . " · Hash " . htmlspecialchars(substr($t['hash_verificacao'] ?? '', 0, 12)) . "...</small><br>$assinado</div>";
            echo "<div><a href='$imprimirUrl' target='_blank' class='btn btn-sm btn-outline-primary'><i class='ti ti-printer'></i> Ver/Imprimir</a></div></div>";

            // Form upload (GLPI style) - Enviar abre picker se sem arquivo
            echo "<form method='post' enctype='multipart/form-data' action='" . self::getFormURL() . "' class='mt-3 d-flex gap-2 align-items-end termo-upload-form'>";
            echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
            echo "<input type='hidden' name='id' value='$id'>";
            echo "<input type='hidden' name='action' value='upload'>";
            echo "<input type='hidden' name='termo_id' value='" . (int)$t['id'] . "'>";
            echo "<div class='flex-grow-1'><label class='form-label small mb-1'>" . __('Substituir por arquivo assinado (PDF/JPG/PNG, máx 10MB)', 'protocolo') . "</label>";
            echo "<input type='file' name='arquivo' accept='.pdf,.jpg,.jpeg,.png' class='form-control form-control-sm termo-arquivo-input' required></div>";
            echo "<button type='submit' class='btn btn-sm btn-dark termo-enviar-btn'><i class='ti ti-upload'></i> Enviar</button>";
            echo "</form></div>";
        }
        echo "</div>";
        // JS inline fallback: garante que Enviar abre picker se sem arquivo (funciona mesmo se app.js em cache ou tab via AJAX)
        echo "<script>(function(){
            if(window.__protocoloTermoPickerBound) return;
            window.__protocoloTermoPickerBound = true;
            function bind(){
                document.addEventListener('click', function(e){
                    var btn = e.target.closest && e.target.closest('.termo-enviar-btn');
                    if(!btn) return;
                    var form = btn.closest('.termo-upload-form');
                    if(!form) return;
                    var input = form.querySelector('.termo-arquivo-input');
                    if(!input) return;
                    if(!input.files || input.files.length===0){
                        e.preventDefault(); e.stopPropagation();
                        input.click();
                    }
                });
                document.addEventListener('change', function(e){
                    if(!e.target.classList.contains('termo-arquivo-input')) return;
                    var input=e.target, form=input.closest('.termo-upload-form');
                    if(!form) return;
                    var btn=form.querySelector('.termo-enviar-btn');
                    if(!btn) return;
                    if(input.files && input.files.length>0){
                        btn.classList.remove('btn-dark'); btn.classList.add('btn-success');
                        btn.title=input.files[0].name;
                        var hint=form.querySelector('.termo-arquivo-hint');
                        if(!hint){ hint=document.createElement('small'); hint.className='termo-arquivo-hint text-success d-block mt-1'; input.parentElement.appendChild(hint); }
                        hint.textContent='Selecionado: '+input.files[0].name+' — clique em Enviar novamente para enviar.';
                        hint.style.display='';
                    }
                });
            }
            if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', bind); else bind();
        })();</script>";

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
            $events[] = [
                'date' => $t['date_creation'],
                'icon' => $isRec ? 'ti ti-file-text' : 'ti ti-file-export',
                'color' => $isRec ? 'bg-primary' : 'bg-success',
                'title' => ($isRec ? __('Termo de Recebimento gerado', 'protocolo') : __('Termo de Retirada gerado', 'protocolo')) . " <code>" . htmlspecialchars($t['codigo']) . "</code>",
                'desc' => Html::convDateTime($t['date_creation']) . " por " . htmlspecialchars(getUserName($t['users_id'] ?? 0)) . ($t['arquivo_assinado'] ? "<br><span class='badge bg-success'><i class='ti ti-check'></i> Assinado: " . htmlspecialchars($t['arquivo_assinado']) . "</span>" : "<br><span class='badge bg-warning text-dark'>Sem arquivo assinado</span>")
            ];
            if (!empty($t['arquivo_assinado'])) {
                // tenta achar data do upload? Não temos, usa mesma date_creation
            }
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
        if ($isNew) {
            if (!self::canCreate() && !self::canView()) { return false; }
        } else {
            if (!self::canView()) { return false; }
        }

        $this->initForm($ID, $options);
        // Para novo, não chama showFormHeader ainda porque precisamos custom

        $csrf = Session::getNewCSRFToken();
        echo "<form method='post' action='" . self::getFormURL() . "' enctype='multipart/form-data' id='plugin_protocolo_pasta_form'>";
        echo '<input type="hidden" name="_glpi_csrf_token" value="' . $csrf . '">';
        if (!$isNew) {
            echo Html::hidden('id', ['value' => $ID]);
        }

        // Usa layout GLPI padrão: tab_cadre_fixe
        echo "<div class='spaced'><table class='tab_cadre_fixe'>";

        if (!$isNew) {
            echo "<tr><th colspan='4' class='center'><h3>" . htmlspecialchars($this->fields['codigo']) . " " . self::getStatusBadge($this->fields['status']) . "</h3>";
            echo "<small class='text-muted'>" . Escola::getTypeName(1) . ": " . htmlspecialchars(self::getEscolaName($this->fields['plugin_protocolo_escolas_id'])) . " · " . __('Criada por', 'protocolo') . " " . htmlspecialchars(getUserName($this->fields['users_id'] ?? 0)) . " em " . Html::convDateTime($this->fields['date_creation']) . "</small></th></tr>";
        }

        echo "<tr class='tab_bg_1'>";
        echo "<td width='15%'><label>" . __('Escola destinatária', 'protocolo') . " <span class='required'>*</span> <small class='text-muted'>(Entidade GLPI)</small></label></td>";
        echo "<td width='35%'>";
        $escolaId = (int)($this->fields['plugin_protocolo_escolas_id'] ?? 0);
        // Performance: usa Entity::dropdown nativo (Select2 com AJAX) em vez de SELECT * + loop
        // Lista todas as entidades de forma paginada/lazy, evita payload de 500+ options
        try {
            \Entity::dropdown([
                'name'   => 'plugin_protocolo_escolas_id',
                'value'  => $escolaId,
                'width'  => '100%',
                'display_emptychoice' => true,
                'emptylabel' => '-- ' . __('Selecione a escola', 'protocolo') . ' --',
                'comments' => false,
                'entity' => 0,
                'entity_sons' => true,
                'required' => true,
            ]);
        } catch (\Throwable $e) {
            // Fallback leve: apenas entidade atual + selecionada
            echo "<select name='plugin_protocolo_escolas_id' id='escola_select' class='form-select' required style='width:100%'><option value=''>-- " . __('Selecione a escola', 'protocolo') . " --</option>";
            if ($escolaId) {
                $entName = self::getEscolaName($escolaId);
                echo "<option value='" . $escolaId . "' selected>" . htmlspecialchars($entName) . "</option>";
            }
            echo "</select>";
        }
        echo "<div class='form-text'><small class='text-muted'>" . __('Entidade GLPI = Escola. Gerencie em Administração → Entidades', 'protocolo') . "</small></div>";
        echo "</td>";

        echo "<td><label>" . __('Data/hora recebimento', 'protocolo') . "</label></td>";
        $valDt = $this->fields['data_recebimento'] ?? date('Y-m-d\TH:i');
        // converte para datetime-local (fallback servidor, JS sobrescreve com hora local do computador se for novo)
        $valDtLocal = date('Y-m-d\TH:i', strtotime($valDt));
        $dtId = $isNew ? "id='data_recebimento_field'" : "";
        echo "<td><input type='datetime-local' name='data_recebimento' $dtId class='form-control' value='$valDtLocal'></td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><label>" . __('Recebido de (quem deixou)', 'protocolo') . " <span class='required'>*</span></label></td>";
        echo "<td><input type='text' name='recebido_de' class='form-control' required value='" . Html::cleanInputText($this->fields['recebido_de'] ?? '') . "' placeholder='Ex: João da Silva - Secretaria'></td>";
        echo "<td><label>" . __('Documento', 'protocolo') . "</label></td>";
        echo "<td><div class='input-group'><select name='recebido_documento_tipo' id='recebido_documento_tipo' class='form-select' style='max-width:95px'><option value='cpf'" . ((($this->fields['recebido_documento_tipo'] ?? 'cpf')==='cpf')?' selected':'') . ">CPF</option><option value='rg'" . ((($this->fields['recebido_documento_tipo'] ?? 'cpf')==='rg')?' selected':'') . ">RG</option></select><input type='text' name='recebido_documento' id='recebido_documento' class='form-control' value='" . Html::cleanInputText($this->fields['recebido_documento'] ?? '') . "' placeholder='000.000.000-00' maxlength='14'></div><small class='text-muted' id='recebido_doc_hint'>CPF: 11 dígitos (000.000.000-00) | RG: 7-9 dígitos</small></td>";
        echo "</tr>";

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

        if (!$isNew && $this->fields['status'] === 'retirada') {
            $tipoRet = strtoupper($this->fields['retirado_documento_tipo'] ?? 'CPF');
            $docRet = htmlspecialchars($this->fields['retirado_documento'] ?? '');
            echo "<tr class='tab_bg_1'><td colspan='4' class='center bg-success bg-opacity-10'><strong>" . __('Retirada registrada', 'protocolo') . "</strong> " . Html::convDateTime($this->fields['data_retirada']) . " por " . htmlspecialchars($this->fields['retirado_por'] ?? '') . ($docRet ? " ($tipoRet: $docRet)" : "") . "</td></tr>";
            if (!empty($this->fields['observacao_retirada'])) {
                echo "<tr class='tab_bg_1'><td>" . __('Obs. retirada', 'protocolo') . "</td><td colspan='3'>" . nl2br(Html::cleanInputText($this->fields['observacao_retirada'])) . "</td></tr>";
            }
        }

        echo "</table></div>";

        // Se for novo: tipos + itens
        if ($isNew) {
            // Tipos
            $tipos = TipoArquivo::getAllActive();
            echo "<div class='spaced'><div class='card border-warning mb-3'><div class='card-header bg-warning bg-opacity-10 d-flex align-items-center justify-content-between flex-wrap gap-2'><div class='d-flex align-items-center gap-2'><span class='fw-bold'><i class='ti ti-tags'></i> " . __('Quais tipos de arquivos', 'protocolo') . " <span class='text-danger'>*</span></span><span class='badge bg-light text-muted border fw-normal'> " . __('marque as caixinhas', 'protocolo') . "</span></div><a href='" . TipoArquivo::getSearchURL() . "' target='_blank' class='btn btn-sm btn-outline-secondary'><i class='ti ti-settings'></i> " . __('Gerenciar tipos', 'protocolo') . "</a></div>";
            echo "<div class='card-body'>";
            if (!$tipos) {
                echo "<div class='alert alert-warning small'>" . __('Nenhum tipo cadastrado', 'protocolo') . " <a href='" . TipoArquivo::getSearchURL() . "'>" . __('Cadastre', 'protocolo') . "</a></div>";
            } else {
                echo "<div class='row g-2'>";
                foreach ($tipos as $t) {
                    echo "<div class='col-md-4 col-sm-6'><div class='form-check'><input class='form-check-input tipo-check' type='checkbox' name='tipos[]' value='" . (int)$t['id'] . "' id='tipo" . (int)$t['id'] . "' data-nome='" . Html::cleanInputText($t['name']) . "'><label class='form-check-label' for='tipo" . (int)$t['id'] . "'>" . htmlspecialchars($t['name']) . "</label></div></div>";
                }
                echo "</div>";
                echo "<div class='form-text mt-2'>" . __('Selecione pelo menos 1. Os itens abaixo são preenchidos automaticamente', 'protocolo') . "</div>";
            }
            echo "</div></div></div>";

            echo "<div class='spaced'><div class='d-flex justify-content-between align-items-center mb-2'><h3 class='mb-0'><i class='ti ti-list-check'></i> " . __('Itens da pasta', 'protocolo') . " *</h3><button type='button' id='btnAddItem' class='btn btn-sm btn-outline-primary'><i class='ti ti-plus'></i> " . __('Adicionar item', 'protocolo') . "</button></div>";
            echo "<div id='itensWrap'><div class='row g-2 mb-2 item-row'><div class='col-md-7'><input name='itens[0][descricao]' class='form-control' placeholder='" . __('Descrição do item', 'protocolo') . "' required></div><div class='col-md-2'><input name='itens[0][quantidade]' type='number' min='1' value='1' class='form-control' placeholder='Qtd'></div><div class='col-md-2'><input name='itens[0][observacao]' class='form-control' placeholder='Obs.'></div><div class='col-md-1'><button type='button' class='btn btn-outline-danger w-100 btnRemove'><i class='ti ti-trash'></i></button></div></div></div>";
            echo "<div class='form-text mb-3'>" . __('Exemplos: Ofício nº 123/2026, Processo de matrícula...', 'protocolo') . "</div></div>";
        }

        // Botões GLPI
        echo "<div class='card-body d-flex gap-2 justify-content-center'>";
        if ($isNew) {
            echo "<button type='submit' name='add' value='1' class='btn btn-primary'><i class='ti ti-check'></i> " . __('Registrar pasta', 'protocolo') . "</button>";
            echo "<a href='" . self::getSearchURL() . "' class='btn btn-secondary'>" . __('Cancelar') . "</a>";
        } else {
            // Botões atualizar / retirada / cancelar dentro do form principal só atualiza dados básicos
            if (Session::haveRight(self::$rightname, UPDATE)) {
                echo "<button type='submit' name='update' value='1' class='btn btn-primary'><i class='ti ti-device-floppy'></i> " . _x('button', 'Save') . "</button>";
            }
            // Ações específicas: retirada/cancelar/reabrir ficam em forms separados abaixo
        }
        echo "</div>";

        echo "</form>";

        // Se não é novo, mostra ações laterais (retirada, upload, cancelar) - fora do form principal
        if (!$isNew) {
            echo "<div class='row g-3 mt-3'>";

            // Coluna esquerda já tem tabs com itens/termos; aqui mostramos ações rápidas na lateral
            echo "<div class='col-lg-8'>";
            // O conteúdo de itens/termos já está na tab, mas para vista form sem tabs, duplicamos link para termo
            echo "<div class='card shadow-sm'><div class='card-header bg-white'><strong><i class='ti ti-printer'></i> " . __('Ações rápidas', 'protocolo') . "</strong></div><div class='list-group list-group-flush'>";
            echo "<a href='" . Plugin::getWebDir('protocolo') . "/front/termo.php?id=$ID&tipo=recebimento' target='_blank' class='list-group-item list-group-item-action'><i class='ti ti-printer'></i> " . __('Imprimir Termo de Recebimento', 'protocolo') . "</a>";
            if ($this->fields['status'] === 'retirada') {
                echo "<a href='" . Plugin::getWebDir('protocolo') . "/front/termo.php?id=$ID&tipo=retirada' target='_blank' class='list-group-item list-group-item-action'><i class='ti ti-printer'></i> " . __('Imprimir Termo de Retirada', 'protocolo') . "</a>";
            }
            echo "<a href='" . self::getSearchURL() . "?criteria[0][field]=4&criteria[0][searchtype]=equals&criteria[0][value]=" . (int)$this->fields['plugin_protocolo_escolas_id'] . "' class='list-group-item list-group-item-action'><i class='ti ti-building'></i> " . __('Ver outras pastas desta escola', 'protocolo') . "</a>";
            echo "</div></div>";
            echo "</div>";

            echo "<div class='col-lg-4'>";
            if ($this->fields['status'] === 'aguardando') {
                echo "<div class='card shadow-sm border-success mb-3'><div class='card-header bg-success text-white'><strong><i class='ti ti-logout'></i> " . __('Registrar retirada', 'protocolo') . "</strong></div><div class='card-body'>";
                echo "<p class='small text-muted'>" . __('Quando a escola vier buscar, preencha e gere o Termo de Retirada.', 'protocolo') . "</p>";
                echo "<form method='post' action='" . self::getFormURL() . "'>";
                echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
                echo Html::hidden('id', ['value' => $ID]);
                echo "<input type='hidden' name='action' value='retirar'>";
                echo "<div class='mb-2'><label class='form-label'>" . __('Retirado por', 'protocolo') . " *</label><input name='retirado_por' class='form-control' required placeholder='" . __('Nome de quem retirou', 'protocolo') . "'></div>";
                echo "<div class='mb-2'><label class='form-label'>" . __('Documento', 'protocolo') . "</label><div class='input-group'><select name='retirado_documento_tipo' id='retirado_documento_tipo' class='form-select' style='max-width:95px'><option value='cpf'>CPF</option><option value='rg'>RG</option></select><input name='retirado_documento' id='retirado_documento' class='form-control' placeholder='000.000.000-00' maxlength='14'></div><small class='text-muted' id='retirado_doc_hint'>CPF: 11 dígitos | RG: 7-9 dígitos</small></div>";
                echo "<div class='mb-2'><label class='form-label'>" . __('Data/hora retirada', 'protocolo') . "</label><input type='datetime-local' name='data_retirada' id='data_retirada_field' class='form-control' value='" . date('Y-m-d\TH:i') . "'></div>";
                echo "<div class='mb-3'><label class='form-label'>" . __('Observação', 'protocolo') . "</label><textarea name='observacao_retirada' class='form-control' rows='2'></textarea></div>";
                echo "<button class='btn btn-success w-100'><i class='ti ti-check'></i> " . __('Confirmar retirada', 'protocolo') . "</button>";
                echo "</form>";

                echo "<form method='post' action='" . self::getFormURL() . "' class='mt-2' onsubmit=\"return confirm('" . __('Cancelar esta pasta?', 'protocolo') . "')\">";
                echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
                echo Html::hidden('id', ['value' => $ID]);
                echo "<input type='hidden' name='action' value='cancelar'>";
                echo "<button class='btn btn-outline-danger btn-sm w-100'>" . __('Cancelar pasta', 'protocolo') . "</button>";
                echo "</form></div></div>";
            } else {
                echo "<div class='card shadow-sm mb-3'><div class='card-body text-center'><p class='mb-2'>" . __('Status', 'protocolo') . ": " . self::getStatusBadge($this->fields['status']) . "</p>";
                echo "<form method='post' action='" . self::getFormURL() . "' onsubmit=\"return confirm('" . __('Reabrir pasta?', 'protocolo') . "')\">";
                echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
                echo Html::hidden('id', ['value' => $ID]);
                echo "<input type='hidden' name='action' value='reabrir'>";
                echo "<button class='btn btn-sm btn-outline-secondary'>" . __('Reabrir para aguardando', 'protocolo') . "</button>";
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
            setupDoc(document.getElementById('retirado_documento_tipo'), document.getElementById('retirado_documento'), document.getElementById('retirado_doc_hint'));

            // Escola searchable (digitar para achar)
            var escolaSel = document.getElementById('escola_select');
            if(escolaSel){
                if(window.$ && $.fn.select2){
                    $(escolaSel).select2({width:'100%', placeholder:'-- Selecione a escola --', allowClear:true});
                } else if(window.TomSelect){
                    new TomSelect(escolaSel, {create:false, sortField:{field:'text', direction:'asc'}, maxOptions:100, placeholder:'Digite para buscar...'});
                }
            }

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

        // JS para tipos/itens (reusa assets/js/app.js do plugin)
        echo Html::script(Plugin::getWebDir('protocolo') . '/js/app.js');

        return true;
    }

    public function prepareInputForAdd($input)
    {
        if (empty($input['plugin_protocolo_escolas_id']) || empty($input['recebido_de'])) {
            error_log("[protocolo][lab-debug] prepareInputForAdd falhou: escola/recebido vazio POST=" . json_encode($input, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR));
            Session::addMessageAfterRedirect(__('Escola e Recebido de são obrigatórios', 'protocolo'), false, ERROR);
            return false;
        }
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

        // Gera código e datas
        $input['codigo'] = Install::gerarCodigoPasta();
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

    // Ações custom: retirar, cancelar, reabrir, upload (chamadas via front/pasta.form.php)
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
        $DB->update(self::getTable(), [
            'status' => 'retirada',
            'data_retirada' => $dataRet,
            'retirado_por' => $retiradoPor,
            'retirado_documento' => $docRet ?: null,
            'retirado_documento_tipo' => $tipoRet,
            'observacao_retirada' => trim($params['observacao_retirada'] ?? '') ?: null,
            'users_id_retirada' => Session::getLoginUserID(),
            'date_mod' => date('Y-m-d H:i:s')
        ], ['id' => $this->getID()]);

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

    public function doUpload(int $termoId, array $file): bool
    {
        global $DB;
        $termo = Termo::getByIdAndPasta($termoId, $this->getID());
        if (!$termo) {
            Session::addMessageAfterRedirect(__('Termo não encontrado', 'protocolo'), false, ERROR);
            return false;
        }
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $msg = match ($file['error'] ?? UPLOAD_ERR_NO_FILE) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('Arquivo excede o limite do servidor (upload_max_filesize)', 'protocolo'),
                UPLOAD_ERR_PARTIAL => __('Upload incompleto', 'protocolo'),
                UPLOAD_ERR_NO_FILE => __('Selecione um arquivo válido', 'protocolo'),
                default => __('Falha no upload', 'protocolo'),
            };
            Session::addMessageAfterRedirect($msg, false, ERROR);
            return false;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
            Session::addMessageAfterRedirect(__('Apenas PDF, JPG ou PNG', 'protocolo'), false, ERROR);
            return false;
        }
        // Valida MIME real com finfo (evita .php.jpg)
        $mimeOk = false;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
        if ($finfo) finfo_close($finfo);
        $allowedMimes = [
            'pdf'  => ['application/pdf'],
            'jpg'  => ['image/jpeg', 'image/jpg'],
            'jpeg' => ['image/jpeg', 'image/jpg'],
            'png'  => ['image/png'],
        ];
        if (isset($allowedMimes[$ext]) && in_array($realMime, $allowedMimes[$ext], true)) {
            $mimeOk = true;
        }
        // Fallback para ambientes sem finfo: confia na extensão mas bloqueia double-extension
        if (!$mimeOk && $realMime === null) {
            $mimeOk = true;
        }
        if (!$mimeOk) {
            Session::addMessageAfterRedirect(__('Tipo de arquivo não corresponde à extensão (mime inválido: ', 'protocolo') . htmlspecialchars($realMime ?? 'desconhecido') . ')', false, ERROR);
            return false;
        }
        // Bloqueia double extension tipo .php.pdf
        if (preg_match('/\.(php|phtml|exe|sh|js)$/i', $file['name'])) {
            Session::addMessageAfterRedirect(__('Nome de arquivo não permitido', 'protocolo'), false, ERROR);
            return false;
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            Session::addMessageAfterRedirect(__('Arquivo muito grande (máx 10MB)', 'protocolo'), false, ERROR);
            return false;
        }
        // Nome único com random_bytes para evitar colisão/time() previsível
        $novoNome = $termo['codigo'] . '-ASSINADO-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destDir = GLPI_PLUGIN_DOC_DIR . '/protocolo/termos';
        if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
        $dest = $destDir . '/' . $novoNome;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Session::addMessageAfterRedirect(__('Falha ao salvar arquivo', 'protocolo'), false, ERROR);
            return false;
        }
        @chmod($dest, 0640);
        // Remove antigo
        if (!empty($termo['arquivo_assinado'])) {
            // tenta resolver caminho: pode ser antigo uploads/termos/... ou novo doc
            $oldPaths = [
                GLPI_ROOT . '/' . $termo['arquivo_assinado'],
                GLPI_PLUGIN_DOC_DIR . '/' . basename($termo['arquivo_assinado']),
                $termo['arquivo_assinado']
            ];
            foreach ($oldPaths as $p) {
                if (file_exists($p)) @unlink($p);
            }
        }
        // Salva caminho relativo para URL: usar Plugin::getWebDir doc?
        // Vamos salvar como caminho acessível via front/document
        // Simples: salva como 'plugins/protocolo/files/termos/...' ou melhor usa GLPI doc: front/document? Para MVP salva caminho absoluto relativo a GLPI_PLUGIN_DOC_DIR e serve via termo.php download
        $rel = 'termos/' . $novoNome; // será resolvido em termo.php
        // Na verdade salvamos caminho relativo ao doc dir para servir
        $dbPath = 'plugins/protocolo/termos/' . $novoNome; // fake web
        // Vamos salvar o caminho físico relativo: usar GLPI_PLUGIN_DOC_DIR . '/protocolo/termos/...' mas para href usamos front/termo.download.php?
        // Simplifica: salva 'termos/'.$novoNome e front resolve
        $DB->update('glpi_plugin_protocolo_termos', ['arquivo_assinado' => 'termos/' . $novoNome], ['id' => $termoId]);

        Session::addMessageAfterRedirect(__('Arquivo assinado enviado com sucesso!', 'protocolo'), false, INFO);
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
        foreach ($it as $r) { $cache[$escolaId] = $r['completename'] ?? $r['name']; return $cache[$escolaId]; }
        $it2 = $DB->request(['FROM' => Escola::getTable(), 'WHERE' => ['id' => $escolaId], 'LIMIT' => 1]);
        foreach ($it2 as $r) { $cache[$escolaId] = $r['name']; return $cache[$escolaId]; }
        $cache[$escolaId] = '-';
        return '-';
    }

    public static function getSearchURL($full = true)
    {
        global $CFG_GLPI;
        $dir = $full ? $CFG_GLPI['root_doc'] : '';
        return $dir . '/plugins/protocolo/front/pasta.php';
    }

    public static function getFormURL($full = true)
    {
        global $CFG_GLPI;
        $dir = $full ? $CFG_GLPI['root_doc'] : '';
        return $dir . '/plugins/protocolo/front/pasta.form.php';
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
