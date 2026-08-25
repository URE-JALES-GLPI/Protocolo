<?php
namespace GlpiPlugin\Protocolo;

use Config as GlpiConfig;
use Session;

class Config
{
    public const CONTEXT = 'plugin:protocolo';

    public static function getDefaults(): array
    {
        return [
            'prazo_alerta_dias'          => 15,
            'alerta_ativo'               => 1,
            'notificacao_ativa'          => 0,
            'notificacao_email_copia'    => '',
            'notificacao_whatsapp'       => 0,
            'dashboard_graficos_ativo'   => 1,
            'installed'                  => date('Y-m-d H:i:s'),
            'migrated_version'           => defined('PLUGIN_PROTOCOLO_VERSION') ? PLUGIN_PROTOCOLO_VERSION : '1.1.0',
        ];
    }

    public static function get(string $key, $default = null)
    {
        try {
            $val = GlpiConfig::getConfigurationValue(self::CONTEXT, $key);
            if ($val === null || $val === '') {
                $defaults = self::getDefaults();
                return $defaults[$key] ?? $default;
            }
            return $val;
        } catch (\Throwable $e) {
            $defaults = self::getDefaults();
            return $defaults[$key] ?? $default;
        }
    }

    public static function getAll(): array
    {
        $defaults = self::getDefaults();
        $out = [];
        foreach ($defaults as $k => $def) {
            $out[$k] = self::get($k, $def);
        }
        // Tipagem
        $out['prazo_alerta_dias'] = max(1, min(90, (int)$out['prazo_alerta_dias']));
        $out['alerta_ativo'] = (int)$out['alerta_ativo'] ? 1 : 0;
        $out['notificacao_ativa'] = (int)$out['notificacao_ativa'] ? 1 : 0;
        $out['notificacao_whatsapp'] = (int)$out['notificacao_whatsapp'] ? 1 : 0;
        $out['dashboard_graficos_ativo'] = (int)$out['dashboard_graficos_ativo'] ? 1 : 0;
        $out['notificacao_email_copia'] = trim((string)$out['notificacao_email_copia']);
        return $out;
    }

    public static function set(array $values): bool
    {
        try {
            // Sanitiza
            if (isset($values['prazo_alerta_dias'])) {
                $values['prazo_alerta_dias'] = max(1, min(90, (int)$values['prazo_alerta_dias']));
            }
            if (isset($values['alerta_ativo'])) $values['alerta_ativo'] = $values['alerta_ativo'] ? 1 : 0;
            if (isset($values['notificacao_ativa'])) $values['notificacao_ativa'] = $values['notificacao_ativa'] ? 1 : 0;
            if (isset($values['notificacao_whatsapp'])) $values['notificacao_whatsapp'] = $values['notificacao_whatsapp'] ? 1 : 0;
            if (isset($values['dashboard_graficos_ativo'])) $values['dashboard_graficos_ativo'] = $values['dashboard_graficos_ativo'] ? 1 : 0;
            if (isset($values['notificacao_email_copia'])) $values['notificacao_email_copia'] = trim((string)$values['notificacao_email_copia']);

            GlpiConfig::setConfigurationValues(self::CONTEXT, $values);
            return true;
        } catch (\Throwable $e) {
            error_log("[protocolo] Config::set falhou: " . $e->getMessage());
            return false;
        }
    }

    public static function initDefaults(bool $force = false): void
    {
        try {
            $existing = GlpiConfig::getConfigurationValues(self::CONTEXT);
            $defaults = self::getDefaults();
            $toSet = [];
            foreach ($defaults as $k => $v) {
                if ($force || !array_key_exists($k, $existing)) {
                    $toSet[$k] = $v;
                }
            }
            if (!empty($toSet)) {
                GlpiConfig::setConfigurationValues(self::CONTEXT, $toSet);
            }
        } catch (\Throwable $e) {
            error_log("[protocolo] Config::initDefaults falhou: " . $e->getMessage());
        }
    }

    public static function getPrazoAlertaDias(): int
    {
        return (int)self::get('prazo_alerta_dias', 15);
    }

    public static function isAlertaAtivo(): bool
    {
        return (bool)self::get('alerta_ativo', 1);
    }

    public static function isNotificacaoAtiva(): bool
    {
        return (bool)self::get('notificacao_ativa', 0);
    }

    public static function isGraficosAtivo(): bool
    {
        return (bool)self::get('dashboard_graficos_ativo', 1);
    }

    public static function canEdit(): bool
    {
        return Session::haveRight('config', UPDATE) || Session::haveRight('plugin_protocolo_config', UPDATE);
    }
}
