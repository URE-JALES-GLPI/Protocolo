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
     * Direitos do plugin
     * GLPI lê via $RIGHTS ou via ::getRights()
     */
    public static function getRights(): array
    {
        return [
            'plugin_protocolo_pasta'  => __('Pastas - Protocolo', 'protocolo'),
            'plugin_protocolo_escola' => __('Escolas', 'protocolo'),
            'plugin_protocolo_tipo'   => __('Tipos de Arquivo', 'protocolo'),
            'plugin_protocolo_config' => __('Configuração Protocolo', 'protocolo'),
        ];
    }

    /**
     * Hook change_profile: atualiza sessão
     */
    public static function changeProfile(): void
    {
        $prof = new GlpiProfile();
        $prof->getFromDB(Session::getLoginUserID() ? $_SESSION['glpiactive_profile']['id'] : 0);
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
        $rights = self::getRights();

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
            echo "<td>$label<br><small><code>$rightName</code></small></td>";
            echo "<td>";
            // GLPI padrão: RIGHTS: READ=1, UPDATE=2, CREATE=4, DELETE=8, PURGE=16, READNOTE=32, UPDATENOTE=64
            // Para plugin vamos usar bits simples: 1=LER, 2=CRIAR, 4=EDITAR, 8=EXCLUIR, 16=PURGE, 255=ALL
            // Render como dropdown GlpiProfile::dropdownRights
            GlpiProfile::dropdownRights(
                [$rightName => $current],
                $rightName,
                null,
                false
            );
            echo "</td>";
            echo "<td class='small text-muted'>1=Ler, 2+4=Editar/Criar, 8=Excluir, 16=Purge, 255=Todos</td>";
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
