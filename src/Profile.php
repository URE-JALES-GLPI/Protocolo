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

    /**
     * Mostra aba de direitos no perfil GLPI
     */
    public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof GlpiProfile) {
            return self::createTabEntry(__('Protocolo', 'protocolo'));
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

    public static function showFormForProfile(GlpiProfile $profile): void
    {
        global $DB;
        $id = $profile->getID();
        $rights = self::getRightsStatic();

        echo "<div class='spaced' id='protocoloProfileForm'>";
        echo "<table class='tab_cadre_fixe'>";

        echo "<tr><th colspan='3'>" . __('Direitos do Plugin Protocolo', 'protocolo') . "</th></tr>";
        echo "<tr><th>" . __('Direito', 'protocolo') . "</th><th>" . __('Valor', 'protocolo') . "</th><th>" . __('Descrição', 'protocolo') . "</th></tr>";

        foreach ($rights as $rightName => $label) {
            $current = 0;
            $iterator = $DB->request([
                'FROM' => 'glpi_profilerights',
                'WHERE' => ['profiles_id' => $id, 'name' => $rightName]
            ]);
            foreach ($iterator as $row) {
                $current = (int)$row['rights'];
            }
            echo "<tr class='tab_bg_1'>";
            echo "<td><strong>$label</strong><br><small class='text-muted'><code>$rightName</code></small></td>";
            echo "<td>";
            // UI mais bonita que dropdownRights (evita lista feia) - checkboxes inline estilo GLPI
            $possible = [
                READ       => ['label' => __('Read'), 'title' => 'Leitura'],
                UPDATE     => ['label' => __('Update'), 'title' => 'Atualizar'],
                CREATE     => ['label' => __('Create'), 'title' => 'Criar'],
                DELETE     => ['label' => __('Delete'), 'title' => 'Excluir'],
                PURGE      => ['label' => __('Purge'), 'title' => 'Apagar definitivo'],
                READNOTE   => ['label' => __('Read note'), 'title' => 'Ler notas'],
                UPDATENOTE => ['label' => __('Update note'), 'title' => 'Atualizar notas'],
            ];
            echo "<input type='hidden' name='_{$rightName}[0]' value='0'>"; // garante que desmarcar tudo zera (0)
            echo "<div class='d-flex flex-wrap gap-3'>";
            foreach ($possible as $bit => $info) {
                $checked = ($current & $bit) ? 'checked' : '';
                $uid = $rightName . '_' . $bit;
                // GLPI espera name=\"_{$rightName}[{$bit}]\" value=\"1\"
                echo "<div class='form-check mb-0' title='{$info['title']} ({$bit})'>";
                echo "<input class='form-check-input' type='checkbox' name='_{$rightName}[{$bit}]' value='1' id='{$uid}' $checked>";
                echo "<label class='form-check-label small' for='{$uid}'>{$info['label']}</label>";
                echo "</div>";
            }
            echo "</div>";
            // atalho Selecionar todos / nenhum
            echo "<div class='mt-2 small text-muted'>";
            echo "<a href='#' onclick=\"var c=document.querySelectorAll('input[name^=\\\"_{$rightName}[\\\"]'); c.forEach(function(e){e.checked=true}); return false;\"><i class='ti ti-checks'></i> " . __('Select all') . "</a> &middot; ";
            echo "<a href='#' onclick=\"var c=document.querySelectorAll('input[name^=\\\"_{$rightName}[\\\"]'); c.forEach(function(e){e.checked=false}); return false;\"><i class='ti ti-x'></i> " . __('Deselect all') . "</a> ";
            echo "<span class='badge bg-light text-dark border ms-2' title='Valor atual'>$current</span>";
            if ($current === 255) echo " <span class='badge bg-success'>Todos</span>";
            elseif ($current === 0) echo " <span class='badge bg-secondary'>Sem acesso</span>";
            echo "</div>";
            echo "</td>";
            echo "<td class='small text-muted' style='max-width:220px'>Marque o que o perfil pode fazer.<br><b>Pastas:</b> precisa de <code>Read</code> para ver menu Ferramentas → Pastas.<br><code>255</code>=todos.</td>";
            echo "</tr>";
        }

        echo "<tr><td colspan='3' class='center'>";
        echo "<small class='text-muted'>Salve o perfil para aplicar. Super-Admin já tem 255 por padrão.</small>";
        echo "</td></tr>";

        echo "</table></div>";
    }

    /**
     * Helper para checar direito curto
     */
    public static function haveRight(string $right, int $level = 1): bool
    {
        return Session::haveRight($right, $level);
    }
}
