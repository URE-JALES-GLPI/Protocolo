<?php
namespace GlpiPlugin\Protocolo;

use CommonDBTM;
use Html;
use Session;
use Dropdown;
use Plugin;

class Recebedor extends \CommonDBTM
{
    public static $rightname = 'plugin_protocolo_use';

    public function isEntityAssign()
    {
        return false;
    }

    public function maybeRecursive()
    {
        return false;
    }

    public static function getTypeName($nb = 0)
    {
        return $nb == 1 ? __('Recebedor', 'protocolo') : __('Recebedores', 'protocolo');
    }

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_protocolo_recebedores';
    }

    public static function getIcon()
    {
        return 'ti ti-users';
    }

    private static function hasRightDB(string $right, int $level): bool
    {
        return \GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', $level);
    }

    public static function canView(): bool
    {
        return self::hasRightDB(self::$rightname, READ) || \GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', READ);
    }

    public static function canCreate(): bool
    {
        return self::hasRightDB(self::$rightname, CREATE) || \GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', CREATE);
    }

    public static function canUpdate(): bool
    {
        return \GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', UPDATE);
    }

    public static function canDelete(): bool
    {
        return \GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', DELETE);
    }

    public static function canPurge(): bool
    {
        return \GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_use', PURGE);
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
        return self::canUpdate();
    }

    public function canDeleteItem(): bool
    {
        return self::canDelete();
    }

    public function canPurgeItem(): bool
    {
        return self::canPurge();
    }

    public static function getNameField()
    {
        return 'name';
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id' => 'common',
            'name' => __('Recebedor', 'protocolo')
        ];

        $tab[] = [
            'id' => 1,
            'table' => self::getTable(),
            'field' => 'name',
            'name' => __('Nome', 'protocolo'),
            'datatype' => 'itemlink',
            'massiveaction' => true
        ];

        $tab[] = [
            'id' => 2,
            'table' => self::getTable(),
            'field' => 'id',
            'name' => __('ID'),
            'datatype' => 'number'
        ];

        $tab[] = [
            'id' => 3,
            'table' => self::getTable(),
            'field' => 'document',
            'name' => __('Documento', 'protocolo'),
            'datatype' => 'string'
        ];

        $tab[] = [
            'id' => 4,
            'table' => self::getTable(),
            'field' => 'document_type',
            'name' => __('Tipo', 'protocolo'),
            'datatype' => 'string'
        ];

        $tab[] = [
            'id' => 5,
            'table' => self::getTable(),
            'field' => 'is_active',
            'name' => __('Ativo', 'protocolo'),
            'datatype' => 'bool'
        ];

        $tab[] = [
            'id' => 6,
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

        $docType = $this->fields['document_type'] ?? 'cpf';
        if (!in_array($docType, ['cpf', 'rg'], true)) {
            $docType = 'cpf';
        }
        $selCpf = $docType === 'cpf' ? ' selected' : '';
        $selRg = $docType === 'rg' ? ' selected' : '';
        $sigImg = $this->fields['assinatura_image'] ?? '';

        echo '<tr class=\'tab_bg_1\'>';
        echo '<td><label for=\'name\'>' . __('Nome') . ' <span class=\'required\'>*</span></label></td>';
        echo '<td><input type=\'text\' name=\'name\' id=\'name\' value=\'' . Html::cleanInputText($this->fields['name'] ?? '') . '\' class=\'form-control\' required style=\'width:100%\' placeholder=\'Ex: Maria Souza\'></td>';
        echo '<td><label for=\'pt-recebedor-doctipo\'>' . __('Tipo de documento', 'protocolo') . '</label></td>';
        echo '<td><select name=\'document_type\' id=\'pt-recebedor-doctipo\' class=\'form-select\'><option value=\'cpf\'' . $selCpf . '>CPF</option><option value=\'rg\'' . $selRg . '>RG</option></select></td>';
        echo '</tr>';

        echo '<tr class=\'tab_bg_1\'>';
        echo '<td><label for=\'pt-recebedor-doc\'>' . __('Documento', 'protocolo') . '</label></td>';
        echo '<td colspan=\'3\'><input type=\'text\' name=\'document\' id=\'pt-recebedor-doc\' class=\'form-control\' value=\'' . Html::cleanInputText($this->fields['document'] ?? '') . '\' placeholder=\'Somente números\' maxlength=\'14\' inputmode=\'numeric\' style=\'font-size:1.2rem;padding:10px;text-align:center;letter-spacing:2px;\'>';
        echo '<small class=\'text-muted\' id=\'pt-recebedor-doc-hint\'>CPF: 11 dígitos | RG: 7 a 9 dígitos</small>';
        echo '<div style=\'display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;\'>';
        foreach (['7', '8', '9', '4', '5', '6', '1', '2', '3'] as $nk) {
            echo '<button type=\'button\' class=\'pt-btn pt-btn-secondary\' style=\'flex:1 1 30%;padding:12px;font-size:1.15rem;font-weight:700;\' onclick=\'ptRecebNum("' . $nk . '")\'>' . $nk . '</button>';
        }
        echo '<button type=\'button\' class=\'pt-btn pt-btn-secondary\' style=\'flex:1 1 30%;padding:12px;font-weight:700;color:#dc2626;\' onclick=\'ptRecebNum("clr")\'>C</button>';
        echo '<button type=\'button\' class=\'pt-btn pt-btn-secondary\' style=\'flex:1 1 30%;padding:12px;font-size:1.15rem;font-weight:700;\' onclick=\'ptRecebNum("0")\'>0</button>';
        echo '<button type=\'button\' class=\'pt-btn pt-btn-secondary\' style=\'flex:1 1 30%;padding:12px;font-size:1.15rem;\' onclick=\'ptRecebNum("del")\'><i class=\'ti ti-backspace\'></i></button>';
        echo '</div></td>';
        echo '</tr>';

        echo '<tr class=\'tab_bg_1\'>';
        echo '<td><label>' . __('Assinatura', 'protocolo') . '</label><br><small class=\'text-muted\'>Assine com dedo/caneta</small></td>';
        echo '<td colspan=\'3\'><div style=\'background:#fff;border:2px solid #e8eaf0;border-radius:12px;overflow:hidden;touch-action:none;\'><canvas id=\'pt-recebedor-canvas\' style=\'width:100%;height:180px;display:block;touch-action:none;cursor:crosshair;\'></canvas></div>';
        echo '<div style=\'display:flex;justify-content:space-between;align-items:center;margin-top:6px;\'><small class=\'text-muted\'>' . __('Assinatura usada no termo', 'protocolo') . '</small><button type=\'button\' id=\'pt-recebedor-clear\' class=\'pt-btn pt-btn-secondary pt-btn-sm\'><i class=\'ti ti-eraser\'></i> Limpar</button></div>';
        echo '<input type=\'hidden\' name=\'assinatura_image\' id=\'pt-recebedor-image\' value=\'\'>';
        echo '</td></tr>';

        echo '<tr class=\'tab_bg_1\'>';
        echo '<td><label for=\'is_active\'>' . __('Ativo', 'protocolo') . '</label></td>';
        echo '<td colspan=\'3\'>';
        Dropdown::showYesNo('is_active', $this->fields['is_active'] ?? 1);
        echo '</td>';
        echo '</tr>';

        $this->showFormButtons($options);

        echo '<script>';
        echo '(function(){';
        echo 'var sigExisting = ' . json_encode((string)$sigImg) . ';';
        echo 'window.__ptRecebedorDrawn = false;';
        echo 'function ptRecebDocType(){ var s = document.getElementById("pt-recebedor-doctipo"); return (s && s.value === "rg") ? "rg" : "cpf"; }';
        echo 'window.ptRecebNum = function(d){ var inp = document.getElementById("pt-recebedor-doc"); if(!inp) return; var t = ptRecebDocType(); var isRg = (t === "rg"); var max = isRg ? 9 : 11; var v = isRg ? ("" + (inp.value || "")).replace(/[^0-9xX]/g, "").toUpperCase().slice(0, 9) : ("" + (inp.value || "")).replace(/\\D/g, "").slice(0, 11); if(d === "del"){ v = v.slice(0, -1); } else if(d === "clr"){ v = ""; } else if(isRg ? /^[0-9xX]$/i.test(d) : /^[0-9]$/.test(d)){ if(v.length < max) v += String(d).toUpperCase(); } if(window.ptFormatDoc){ inp.value = window.ptFormatDoc(v, t); } else { inp.value = v; } };';
        echo 'function ptRecebApplyMask(){ var inp = document.getElementById("pt-recebedor-doc"); if(!inp) return; var t = ptRecebDocType(); if(window.ptFormatDoc){ inp.value = window.ptFormatDoc(inp.value, t); } }';
        echo 'var tipoSel = document.getElementById("pt-recebedor-doctipo"); if(tipoSel){ tipoSel.addEventListener("change", ptRecebApplyMask); }';
        echo 'var docInp = document.getElementById("pt-recebedor-doc"); if(docInp){ docInp.addEventListener("input", function(){ var t = ptRecebDocType(); if(window.ptFormatDoc){ var pos = this.selectionStart; this.value = window.ptFormatDoc(this.value, t); } }); ptRecebApplyMask(); }';
        echo 'function ptRecebFitCanvas(){ var c = document.getElementById("pt-recebedor-canvas"); if(!c) return; var r = c.getBoundingClientRect(); var w = Math.max(280, Math.floor(r.width)); if(w > 0){ c.width = w; c.height = 180; } }';
        echo 'function ptRecebLoadExisting(){ var c = document.getElementById("pt-recebedor-canvas"); if(!c || !sigExisting || sigExisting.indexOf("data:image/") !== 0) return; var img = new Image(); img.onload = function(){ try{ ptRecebFitCanvas(); var ctx = c.getContext("2d"); ctx.clearRect(0, 0, c.width, c.height); ctx.drawImage(img, 0, 0, c.width, c.height); }catch(e){} }; img.src = sigExisting; }';
        echo 'function ptRecebBindCanvas(){ var c = document.getElementById("pt-recebedor-canvas"); if(!c || c.dataset.ptBound) return; c.dataset.ptBound = "1"; ptRecebFitCanvas(); ptRecebLoadExisting(); var drawing = false; var ctx = c.getContext("2d"); ctx.lineWidth = 2.5; ctx.lineCap = "round"; ctx.strokeStyle = "#111827"; function pos(e){ var r = c.getBoundingClientRect(); if(e.touches && e.touches[0]){ return {x: e.touches[0].clientX - r.left, y: e.touches[0].clientY - r.top}; } return {x: e.clientX - r.left, y: e.clientY - r.top}; } function scalePt(p){ return {x: p.x * (c.width / c.getBoundingClientRect().width), y: p.y * (c.height / c.getBoundingClientRect().height)}; } function start(e){ drawing = true; window.__ptRecebedorDrawn = true; var p = scalePt(pos(e)); ctx.beginPath(); ctx.moveTo(p.x, p.y); if(e.preventDefault) e.preventDefault(); } function move(e){ if(!drawing) return; var p = scalePt(pos(e)); ctx.lineTo(p.x, p.y); ctx.stroke(); if(e.preventDefault) e.preventDefault(); } function end(){ drawing = false; } c.addEventListener("mousedown", start); c.addEventListener("mousemove", move); window.addEventListener("mouseup", end); c.addEventListener("touchstart", start, {passive: false}); c.addEventListener("touchmove", move, {passive: false}); c.addEventListener("touchend", end); var clr = document.getElementById("pt-recebedor-clear"); if(clr){ clr.addEventListener("click", function(){ ctx.clearRect(0, 0, c.width, c.height); window.__ptRecebedorDrawn = false; var h = document.getElementById("pt-recebedor-image"); if(h) h.value = ""; }); } var frm = c.closest("form"); if(frm && !frm.dataset.ptRecBound){ frm.dataset.ptRecBound = "1"; frm.addEventListener("submit", function(){ var h = document.getElementById("pt-recebedor-image"); if(!h) return; try{ if(window.__ptRecebedorDrawn){ h.value = c.toDataURL("image/png"); } else { h.value = ""; } }catch(e){ h.value = ""; } }); } }';
        echo 'if(document.readyState === "loading"){ document.addEventListener("DOMContentLoaded", ptRecebBindCanvas); } else { ptRecebBindCanvas(); }';
        echo 'setTimeout(ptRecebBindCanvas, 300);';
        echo '})();';
        echo '</script>';

        return true;
    }

    private function validateDocument($input)
    {
        $doc = trim($input['document'] ?? '');
        if ($doc === '') {
            return true;
        }
        $tipo = strtolower(trim($input['document_type'] ?? 'cpf'));
        if (!in_array($tipo, ['cpf', 'rg'], true)) {
            $tipo = 'cpf';
        }
        $ok = ($tipo === 'cpf') ? Pasta::validarCPF($doc) : Pasta::validarRG($doc);
        if (!$ok) {
            Session::addMessageAfterRedirect(
                $tipo === 'cpf' ? __('CPF inválido (11 dígitos válidos).', 'protocolo') : __('RG inválido (7 a 9 dígitos).', 'protocolo'),
                false,
                ERROR
            );
            return false;
        }
        return true;
    }

    public function prepareInputForAdd($input)
    {
        if (empty(trim($input['name'] ?? ''))) {
            Session::addMessageAfterRedirect(__('Nome do recebedor é obrigatório', 'protocolo'), false, ERROR);
            return false;
        }
        $input['name'] = trim($input['name']);
        $tipo = strtolower(trim($input['document_type'] ?? 'cpf'));
        $input['document_type'] = in_array($tipo, ['cpf', 'rg'], true) ? $tipo : 'cpf';
        $input['document'] = trim($input['document'] ?? '');
        if (!$this->validateDocument($input)) {
            return false;
        }
        if ($input['document'] === '') {
            $input['document'] = null;
        }
        $sig = trim($input['assinatura_image'] ?? '');
        if ($sig !== '' && strpos($sig, 'data:image/') === 0) {
            $input['assinatura_image'] = $sig;
            $input['assinatura_data'] = date('Y-m-d H:i:s');
        } else {
            unset($input['assinatura_image']);
            unset($input['assinatura_data']);
        }
        $input['users_id'] = Session::getLoginUserID();
        if (!isset($input['is_active'])) {
            $input['is_active'] = 1;
        } else {
            $input['is_active'] = (int)$input['is_active'];
        }
        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        if (isset($input['name']) && empty(trim($input['name']))) {
            Session::addMessageAfterRedirect(__('Nome do recebedor é obrigatório', 'protocolo'), false, ERROR);
            return false;
        }
        if (isset($input['name'])) {
            $input['name'] = trim($input['name']);
        }
        if (isset($input['document_type'])) {
            $tipo = strtolower(trim($input['document_type']));
            $input['document_type'] = in_array($tipo, ['cpf', 'rg'], true) ? $tipo : 'cpf';
        }
        if (isset($input['document'])) {
            $input['document'] = trim($input['document']);
            $merged = array_merge($this->fields, $input);
            if (!$this->validateDocument($merged)) {
                return false;
            }
            if ($input['document'] === '') {
                $input['document'] = null;
            }
        } elseif (isset($input['document_type'])) {
            $merged = array_merge($this->fields, $input);
            if (!$this->validateDocument($merged)) {
                return false;
            }
        }
        $sig = trim($input['assinatura_image'] ?? '');
        if ($sig !== '' && strpos($sig, 'data:image/') === 0) {
            $input['assinatura_image'] = $sig;
            $input['assinatura_data'] = date('Y-m-d H:i:s');
        } else {
            unset($input['assinatura_image']);
            unset($input['assinatura_data']);
        }
        if (isset($input['is_active'])) {
            $input['is_active'] = (int)$input['is_active'];
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

    public static function getSearchURL($full = true)
    {
        $web = self::getProtocoloWebDir();
        $root = $GLOBALS['CFG_GLPI']['root_doc'] ?? '';
        if ($full && $root !== '' && !str_starts_with($web, $root) && str_starts_with($web, '/')) {
            return $root . $web . '/front/recebedor.php';
        }
        if (!$full && $root !== '' && str_starts_with($web, $root)) {
            return substr($web, strlen($root)) . '/front/recebedor.php';
        }
        return $web . '/front/recebedor.php';
    }

    public static function getFormURL($full = true)
    {
        $web = self::getProtocoloWebDir();
        $root = $GLOBALS['CFG_GLPI']['root_doc'] ?? '';
        if ($full && $root !== '' && !str_starts_with($web, $root) && str_starts_with($web, '/')) {
            return $root . $web . '/front/recebedor.form.php';
        }
        if (!$full && $root !== '' && str_starts_with($web, $root)) {
            return substr($web, strlen($root)) . '/front/recebedor.form.php';
        }
        return $web . '/front/recebedor.form.php';
    }
}
