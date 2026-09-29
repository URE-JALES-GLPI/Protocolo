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
     * Direitos simplificados: apenas Usar e Admin
     * - Usar: acesso ao uso do protocolo (pastas, escolas, tipos, dashboard, registrar entrada/retirada)
     * - Admin: acesso à configuração (prazo alerta, notificações, e-mails por entidade, templates)
     */
    public function getRights($interface = 'central')
    {
        return [
            'plugin_protocolo_use'   => __('Usar', 'protocolo'),
            'plugin_protocolo_admin' => __('Admin', 'protocolo'),
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
     * Níveis simplificados: apenas Sem acesso vs Com acesso
     * Usar = 31 (READ+UPDATE+CREATE+DELETE+PURGE) cobre todo uso
     * Admin = 23? ou 31? Usa 1+2=3 (READ+UPDATE) para config, mas setamos 31 para garantir UPDATE
     */
    private static function getLevelsForRight(string $rightName): array
    {
        if ($rightName === 'plugin_protocolo_admin') {
            return [
                0  => ['label' => 'Não', 'desc' => 'Sem acesso à configuração'],
                1  => ['label' => 'Sim', 'desc' => 'Acesso total à configuração (prazo alerta, notificações, e-mails por entidade, templates)'],
            ];
        }
        // plugin_protocolo_use
        return [
            0  => ['label' => 'Não', 'desc' => 'Sem acesso — não vê menu Protocolo'],
            1  => ['label' => 'Sim', 'desc' => 'Pode usar: dashboard, pastas (entrada/retirada/termos), escolas e tipos'],
        ];
    }

    private static function describeCurrent(int $current, array $levels): string
    {
        if (isset($levels[$current])) {
            return $levels[$current]['label'];
        }
        if ($current > 0) {
            return 'Sim';
        }
        return 'Não';
    }

    public static function showFormForProfile(GlpiProfile $profile): void
    {
        global $DB, $CFG_GLPI;
        $id = $profile->getID();
        $rights = self::getRightsStatic();

        $action = Plugin::getWebDir('protocolo') . "/front/profile.php";
        echo "<form method='post' action='$action' id='protocoloProfileSaveForm'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<input type='hidden' name='profiles_id' value='$id'>";
        echo "<div class='spaced' id='protocoloProfileForm'>";
        echo "<table class='tab_cadre_fixe'>";

        echo "<tr><th colspan='3'>" . __('Direitos do Plugin Protocolo', 'protocolo') . " <span class='badge bg-primary ms-2'>Usar / Admin</span></th></tr>";
        echo "<tr><th>" . __('Direito', 'protocolo') . "</th><th>" . __('Acesso', 'protocolo') . "</th><th>" . __('O que pode fazer', 'protocolo') . "</th></tr>";

        foreach ($rights as $rightName => $label) {
            $current = 0;
            $iterator = $DB->request([
                'FROM' => 'glpi_profilerights',
                'WHERE' => ['profiles_id' => $id, 'name' => $rightName]
            ]);
            foreach ($iterator as $row) {
                $current = (int)$row['rights'];
            }
            // Migração suave: se novo direito ainda não existe mas legado tem valor, sugere migração visual
            // Não altera DB aqui, apenas mostra badge de legado
            $legacyInfo = '';
            if ($current === 0) {
                // Verifica direitos legados para hint
                $legacyRights = ['plugin_protocolo_pasta','plugin_protocolo_escola','plugin_protocolo_tipo','plugin_protocolo_config'];
                $hasLegacy = 0;
                foreach ($legacyRights as $lr) {
                    $it = $DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $id, 'name' => $lr]]);
                    foreach ($it as $r) { if ((int)$r['rights'] > 0) $hasLegacy = 1; break; }
                }
                if ($hasLegacy && $rightName === 'plugin_protocolo_use') {
                    $legacyInfo = "<br><small class='text-warning'><i class='ti ti-alert-triangle'></i> Legado detectado: havia permissões antigas. Salve para migrar para Usar/Admin.</small>";
                }
            }

            $levels = self::getLevelsForRight($rightName);
            $hasCustom = !isset($levels[$current]) && $current !== 0 && $current !== 1;
            // Normaliza: qualquer >0 mostra como Com acesso
            $displayVal = ($current > 0) ? 1 : 0;
            $badgeClass = $current > 0 ? 'bg-success' : 'bg-secondary';
            $badgeLabel = self::describeCurrent($current, $levels);

            echo "<tr class='tab_bg_1'>";
            echo "<td style='min-width:220px'><strong>$label</strong><br><small class='text-muted'><code>$rightName</code></small><br><span class='badge $badgeClass mt-1' id='badge_$rightName'>$badgeLabel</span> <small class='text-muted'>($current)</small>$legacyInfo</td>";
            echo "<td style='min-width:260px'>";
            echo "<select name='_{$rightName}' id='drop_{$rightName}' class='form-select form-select-sm protocolo-dropdown' data-right='$rightName' style='max-width:100%'>";
            foreach ($levels as $val => $info) {
                // Para Usar/Admin, qualquer valor >0 deve marcar como 1 selecionado (para não confundir com custom legado)
                $isSelected = false;
                if ($current === (int)$val) $isSelected = true;
                elseif ($val === 1 && $current > 0) $isSelected = true;
                elseif ($val === 0 && $current === 0) $isSelected = true;
                $selected = $isSelected ? 'selected' : '';
                echo "<option value='$val' $selected>" . htmlspecialchars($info['label']) . "</option>";
            }
            if ($hasCustom) {
                echo "<option value='$current' selected>" . htmlspecialchars("Valor legado ($current) — será convertido para Com acesso") . "</option>";
            }
            echo "</select>";
            $desc = $levels[$displayVal]['desc'] ?? $levels[$current]['desc'] ?? '—';
            // Se custom legado >0, mostra desc de Com acesso
            if ($current > 0 && !isset($levels[$current])) $desc = $levels[1]['desc'];
            echo "<div class='mt-1'><small class='text-muted' id='desc_$rightName'>" . htmlspecialchars($desc) . "</small></div>";
            // Acesso EFETIVO deste perfil (lido do banco — é isto que as telas obedecem)
            $eff = self::hasEffectiveRight($id, $rightName);
            $effBadge = $eff ? "<span class='badge bg-success'>Efetivo: PODE</span>" : "<span class='badge bg-secondary'>Efetivo: NÃO PODE</span>";
            echo "<div class='mt-1'>$effBadge</div>";
            echo "</td>";
            echo "<td class='small text-muted' style='max-width:320px'>";
            if ($rightName === 'plugin_protocolo_use') {
                echo "<b>Usar:</b> acesso operacional. Inclui Dashboard, Pastas (Registrar Entrada, Registrar Retirada, Termos, upload assinado), Escolas e Tipos de Arquivo. <b>Usuário comum deve ter Usar habilitado.</b>";
            } else {
                echo "<b>Admin:</b> acesso à <b>Configuração</b> do Protocolo (prazo alerta, notificações por e-mail, e-mails por entidade, templates de e-mail, gráficos). Também libera gestão avançada. <b>Sem Admin, o usuário não vê nem altera Configuração.</b>";
            }
            echo "</td>";
            echo "</tr>";
        }

        echo "<tr><td colspan='3' class='center p-3'>";
        echo "<button type='submit' name='update_protocolo' value='1' class='btn btn-primary'><i class='ti ti-device-floppy me-1'></i> " . __('Save') . "</button> ";
        echo "<a href='" . $CFG_GLPI['root_doc'] . "/front/profile.form.php?id=$id' class='btn btn-outline-secondary ms-2'>Cancelar</a>";
        echo "<br><small class='text-muted d-block mt-2'>Simplificado: <b>Usar</b> = operacional (dashboard/pastas/escolas/tipos). <b>Admin</b> = configurações. Super-Admin já tem acesso total.</small>";
        echo "</td></tr>";

        echo "</table></div>";
        echo "</form>";
    }

    /**
     * Modelo 2.0 — UMA fonte da verdade (sem adivinhação via legados).
     * - Avalia SOMENTE as linhas novas (plugin_protocolo_use/admin).
     * - Legados (pasta/escola/tipo/config) NÃO entram aqui: a migration
     *   (migrateLegacyRights) copia legado>0 para as linhas novas, uma vez.
     * - Qualquer valor >0 equivale a acesso total (bits ignorados).
     */
    private static function isNewRight(string $right): bool
    {
        return in_array($right, ['plugin_protocolo_use', 'plugin_protocolo_admin'], true);
    }

    /** Checagem via sessão (o GLPI carrega as linhas no login). */
    public static function haveRight(string $right, int $level = 1): bool
    {
        if (!self::isNewRight($right)) {
            return Session::haveRight($right, $level);
        }
        $v = $_SESSION['glpiactive_profile'][$right] ?? $_SESSION['glpiactiveprofile'][$right] ?? null;
        if ($v !== null) {
            return (int)$v > 0;
        }
        if ($right === 'plugin_protocolo_admin' && Session::haveRight('config', UPDATE)) {
            return true;
        }
        return (bool)Session::haveRight($right, READ);
    }

    /**
     * Checagem via banco primeiro (reflete na hora, sem precisar relogar),
     * com fallback para a sessão.
     */
    public static function haveRightDB(string $right, int $level = 1): bool
    {
        global $DB;
        if (!self::isNewRight($right)) {
            return Session::haveRight($right, $level);
        }
        $pid = (int)($_SESSION['glpiactive_profile']['id'] ?? 0);
        if ($pid > 0 && isset($DB)) {
            try {
                if ($DB->tableExists('glpi_profilerights')) {
                    $it = $DB->request([
                        'SELECT' => ['rights'],
                        'FROM' => 'glpi_profilerights',
                        'WHERE' => ['profiles_id' => $pid, 'name' => $right],
                        'LIMIT' => 1,
                    ]);
                    foreach ($it as $row) {
                        return (int)$row['rights'] > 0;
                    }
                }
            } catch (\Throwable $e) {}
        }
        return self::haveRight($right, $level);
    }

    /**
     * Acesso efetivo de um perfil QUALQUER (para exibir na aba Perfis).
     * Leitura pura do banco, sem depender da sessão ativa.
     */
    public static function hasEffectiveRight(int $profiles_id, string $right): bool
    {
        global $DB;
        if (!self::isNewRight($right)) {
            return false;
        }
        try {
            if ($profiles_id > 0 && isset($DB) && $DB->tableExists('glpi_profilerights')) {
                $it = $DB->request([
                    'SELECT' => ['rights'],
                    'FROM' => 'glpi_profilerights',
                    'WHERE' => ['profiles_id' => $profiles_id, 'name' => $right],
                    'LIMIT' => 1,
                ]);
                foreach ($it as $row) {
                    return (int)$row['rights'] > 0;
                }
            }
        } catch (\Throwable $e) {}
        return false;
    }

    /**
     * Retorna true se perfil tem perfil de uso (legacy compat)
     */
    public static function canUse(): bool
    {
        return self::haveRightDB('plugin_protocolo_use', READ);
    }
    public static function canAdmin(): bool
    {
        return self::haveRightDB('plugin_protocolo_admin', READ);
    }
}
