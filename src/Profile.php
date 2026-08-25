<?php
namespace GlpiPlugin\Protocolo;

use Profile as GlpiProfile;
use Session;
use Html;
use Dropdown;
use CommonGLPI;
use CommonDBTM;
use Plugin;

class Profile extends \CommonDBTM
{
    public static function getTypeName($nb = 0)
    {
        return __('Perfil Protocolo', 'protocolo');
    }

    /**
     * Direitos do plugin - compatível com CommonDBTM::getRights() não-estático do GLPI 11
     */
    public function getRights($interface = 'central')
    {
        return [
            'plugin_protocolo_pasta'  => __('Pastas - Protocolo', 'protocolo'),
            'plugin_protocolo_escola' => __('Escolas', 'protocolo'),
            'plugin_protocolo_tipo'   => __('Tipos de Arquivo', 'protocolo'),
            'plugin_protocolo_config' => __('Configuração Protocolo', 'protocolo'),
        ];
    }

    // Wrapper estático para uso interno (evita chamar não-estático estaticamente)
    public static function getRightsStatic(): array
    {
        $inst = new self();
        return $inst->getRights();
    }

    /**
     * Hook change_profile: atualiza sessão
     */
    public static function changeProfile(): void
    {
        if (!isset($_SESSION['glpiactive_profile']['id']) || !Session::getLoginUserID()) {
            return;
        }
        $prof = new GlpiProfile();
        $prof->getFromDB((int)$_SESSION['glpiactive_profile']['id']);
        // GLPI já carrega rights de glpi_profilerights para sessão, nada a fazer
        // Se quiser custom, mapear aqui
    }

    public static function getIcon()
    {
        return 'ti ti-shield-lock';
    }

    /**
     * Mostra aba de direitos no perfil GLPI
     */
    public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof GlpiProfile) {
            return self::createTabEntry(__('Protocolo', 'protocolo'), 0, null, self::getIcon());
        }
        return '';
    }

    public static function displayTabContentForItem(\CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof GlpiProfile) {
            self::showFormForProfile($item);
        }
        return true;
    }

    /**
     * Níveis sequenciais para dropdown - cada opção é uma soma de bits GLPI
     * Sequência: quanto maior o valor, mais pode fazer
     */
    private static function getLevelsForRight(string $rightName): array
    {
        // Config: só precisa de leitura vs escrita
        if ($rightName === 'plugin_protocolo_config') {
            return [
                0                    => ['label' => 'Sem acesso', 'desc' => 'Não acessa configuração'],
                READ                 => ['label' => 'Leitura', 'desc' => 'Só visualizar configurações'],
                (READ | UPDATE)      => ['label' => 'Leitura + Atualizar', 'desc' => 'Pode alterar configurações'],
                255                  => ['label' => 'Acesso total', 'desc' => 'Todos (255)'],
            ];
        }

        // Pastas / Escolas / Tipos - sequência completa
        return [
            0                                                      => ['label' => 'Sem acesso', 'desc' => 'Bloqueado — não vê nem o menu'],
            READ                                                   => ['label' => 'Somente leitura', 'desc' => 'Só visualizar / listar'],
            (READ | CREATE)                                        => ['label' => 'Leitura + Criar', 'desc' => 'Ver e criar novos registros'],
            (READ | CREATE | UPDATE)                               => ['label' => 'Padrão operador', 'desc' => 'Ver, criar e editar (recomendado)'],
            (READ | CREATE | UPDATE | DELETE)                      => ['label' => 'Operar + Excluir', 'desc' => 'Anterior + mover para lixeira'],
            (READ | CREATE | UPDATE | DELETE | PURGE)             => ['label' => 'Operar + Purge', 'desc' => 'Anterior + apagar definitivo'],
            (READ | CREATE | UPDATE | DELETE | PURGE | READNOTE | UPDATENOTE) => ['label' => 'Completo + Notas', 'desc' => 'Todas + ler/editar notas (127)'],
            255                                                    => ['label' => 'Acesso total (Super)', 'desc' => 'Todos os bits (255)'],
        ];
    }

    private static function describeCurrent(int $current, array $levels): string
    {
        if (isset($levels[$current])) {
            return $levels[$current]['label'];
        }
        // Valor custom não mapeado
        $parts = [];
        if ($current & READ) $parts[] = 'R';
        if ($current & CREATE) $parts[] = 'C';
        if ($current & UPDATE) $parts[] = 'U';
        if ($current & DELETE) $parts[] = 'D';
        if ($current & PURGE) $parts[] = 'P';
        if ($current & READNOTE) $parts[] = 'RN';
        if ($current & UPDATENOTE) $parts[] = 'UN';
        $hint = $parts ? implode('+', $parts) : '0';
        return "Custom ($current) [$hint]";
    }

    public static function showFormForProfile(GlpiProfile $profile): void
    {
        global $DB, $CFG_GLPI;
        $id = $profile->getID();
        $rights = self::getRightsStatic();

        $action = $CFG_GLPI['root_doc'] . "/plugins/protocolo/front/profile.php";
        echo "<form method='post' action='$action' id='protocoloProfileSaveForm'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<input type='hidden' name='profiles_id' value='$id'>";
        echo "<div class='spaced' id='protocoloProfileForm'>";
        echo "<table class='tab_cadre_fixe'>";

        echo "<tr><th colspan='3'>" . __('Direitos do Plugin Protocolo', 'protocolo') . " <span class='badge bg-primary ms-2'>Dropdown sequencial</span></th></tr>";
        echo "<tr><th>" . __('Direito', 'protocolo') . "</th><th>" . __('Nível de acesso', 'protocolo') . "</th><th>" . __('O que pode fazer', 'protocolo') . "</th></tr>";

        foreach ($rights as $rightName => $label) {
            $current = 0;
            $iterator = $DB->request([
                'FROM' => 'glpi_profilerights',
                'WHERE' => ['profiles_id' => $id, 'name' => $rightName]
            ]);
            foreach ($iterator as $row) {
                $current = (int)$row['rights'];
            }

            $levels = self::getLevelsForRight($rightName);

            // Se valor atual não está nos níveis, injeta como opção custom para não perder dado
            $hasCustom = !isset($levels[$current]);
            $badgeClass = $current === 0 ? 'bg-secondary' : ($current === 255 ? 'bg-success' : ($current >= 31 ? 'bg-warning text-dark' : 'bg-info text-dark'));
            $badgeLabel = self::describeCurrent($current, $levels);

            echo "<tr class='tab_bg_1'>";
            echo "<td style='min-width:220px'><strong>$label</strong><br><small class='text-muted'><code>$rightName</code></small><br><span class='badge $badgeClass mt-1' id='badge_$rightName'>$badgeLabel</span> <small class='text-muted'>($current)</small></td>";
            echo "<td style='min-width:260px'>";
            // Dropdown sequencial
            echo "<select name='_{$rightName}' id='drop_{$rightName}' class='form-select form-select-sm protocolo-dropdown' data-right='$rightName' style='max-width:100%'>";
            foreach ($levels as $val => $info) {
                $selected = ($current === (int)$val) ? 'selected' : '';
                $extra = $val === 255 ? ' ★' : '';
                echo "<option value='$val' $selected>" . htmlspecialchars($info['label'] . " ($val)$extra") . "</option>";
            }
            if ($hasCustom) {
                echo "<option value='$current' selected>" . htmlspecialchars("Custom atual ($current) — manter valor") . "</option>";
            }
            echo "</select>";
            echo "<div class='mt-1'><small class='text-muted' id='desc_$rightName'>" . htmlspecialchars($levels[$current]['desc'] ?? 'Valor custom: ' . $current) . "</small></div>";
            echo "</td>";
            echo "<td class='small text-muted' style='max-width:320px'>";
            // Explica sequência por direito
            if ($rightName === 'plugin_protocolo_pasta') {
                echo "<b>Pastas:</b> precisa de <code>Leitura (1)</code> para ver menu Protocolo. <b>Operador</b> (7) já cria/edita e registra retirada.<br><code>15</code>=+excluir, <code>31</code>=+purge, <code>255</code>=tudo.";
            } elseif ($rightName === 'plugin_protocolo_escola') {
                echo "<b>Escolas:</b> gerenciar Escolas/Entidades. Leitura só lista, Operador (7) já cria/edita.";
            } elseif ($rightName === 'plugin_protocolo_tipo') {
                echo "<b>Tipos:</b> gerenciar Tipos de Arquivo. Recomendado 7 para operadores, 31 para admins.";
            } else {
                echo "<b>Config:</b> 0=bloqueado, 1=só ver, 3=alterar. Use 255 para acesso total.";
            }
            echo "<div class='mt-1'><a href='#' class='small toggle-detail' data-target='detail_$rightName'>Ver sequência ›</a></div>";
            echo "<div id='detail_$rightName' class='small border rounded bg-light p-2 mt-1' style='display:none; font-size:11px'>";
            foreach ($levels as $val => $info) {
                $bits = [];
                if ($val & READ) $bits[] = 'READ(1)';
                if ($val & UPDATE) $bits[] = 'UPDATE(2)';
                if ($val & CREATE) $bits[] = 'CREATE(4)';
                if ($val & DELETE) $bits[] = 'DELETE(8)';
                if ($val & PURGE) $bits[] = 'PURGE(16)';
                if ($val & READNOTE) $bits[] = 'READNOTE(32)';
                if ($val & UPDATENOTE) $bits[] = 'UPDATENOTE(64)';
                $bitsStr = $bits ? implode(' + ', $bits) : '0';
                $active = ($val === $current) ? " <span class='badge bg-primary' style='font-size:10px'>atual</span>" : "";
                echo "<div>" . ($val === 255 ? "<b>$val</b>" : $val) . " = <span class='text-muted'>$bitsStr</span> — " . htmlspecialchars($info['label']) . "$active</div>";
            }
            echo "</div>";
            echo "</td>";
            echo "</tr>";
        }

        echo "<tr><td colspan='3' class='center p-3'>";
        echo "<button type='submit' name='update_protocolo' value='1' class='btn btn-primary'><i class='ti ti-device-floppy me-1'></i> " . __('Save') . "</button> ";
        echo "<a href='" . $CFG_GLPI['root_doc'] . "/front/profile.form.php?id=$id' class='btn btn-outline-secondary ms-2'>Cancelar</a>";
        echo "<br><small class='text-muted d-block mt-2'>Dropdown sequencial: quanto maior o número, mais permissões. Salva só Protocolo. Super-Admin já tem 255.</small>";
        echo "</td></tr>";

        echo "</table></div>";
        echo "</form>";
        // JS: atualiza badge/desc ao trocar dropdown + toggle detalhe
        echo "<script>
        (function(){
            function bind(){
                document.querySelectorAll('.protocolo-dropdown').forEach(function(sel){
                    sel.addEventListener('change', function(){
                        var right = this.dataset.right;
                        var val = parseInt(this.value,10)||0;
                        var badge = document.getElementById('badge_'+right);
                        var desc = document.getElementById('desc_'+right);
                        var levels = {};
                        // reconstrói textos a partir das options
                        var opt = this.options[this.selectedIndex];
                        if(badge && opt) badge.textContent = opt.textContent;
                        if(desc){
                            // pega desc do mapa embutido via dataset? fallback simples
                            var map = {
                                0:'Bloqueado',1:'Só visualizar',5:'Ver e criar',7:'Ver, criar e editar (recomendado)',15:'+ mover lixeira',31:'+ apagar definitivo',127:'Todas + notas',255:'Todos (255)'
                            };
                            // tenta achar desc completa no detail div
                            var detail = document.getElementById('detail_'+right);
                            if(detail){
                                // highlight atual
                                detail.querySelectorAll('span.badge').forEach(function(b){b.remove();});
                            }
                        }
                        // muda cor do badge
                        if(badge){
                            badge.className='badge mt-1 '+(val===0?'bg-secondary':(val===255?'bg-success':(val>=31?'bg-warning text-dark':'bg-info text-dark')));
                        }
                    });
                });
                document.querySelectorAll('.toggle-detail').forEach(function(a){
                    a.addEventListener('click', function(e){
                        e.preventDefault();
                        var t=document.getElementById(this.dataset.target);
                        if(!t) return;
                        t.style.display = t.style.display==='none'?'block':'none';
                        this.textContent = t.style.display==='none'?'Ver sequência ›':'Esconder ‹';
                    });
                });
            }
            if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', bind); else bind();
        })();
        </script>";
    }

    /**
     * Helper para checar direito curto
     */
    public static function haveRight(string $right, int $level = 1): bool
    {
        return Session::haveRight($right, $level);
    }
}
