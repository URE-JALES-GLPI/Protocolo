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
            'notificacao_email_subject'  => '[Protocolo] {acao} - {codigo}',
            'notificacao_email_body_entrada' => "Olá,\n\nA pasta {codigo} destinada à escola \"{escola}\" foi registrada no protocolo em {data_recebimento}.\nRecebido de: {recebido_de} {recebido_documento}\nItens: {itens} ({quantidade_itens} item(s))\nObservação: {observacao}\n\nAcesse: {link}\n\nQuando for retirar, apresente o Termo de Recebimento impresso + documento com foto.\n\n— Sistema de Protocolo - URE",
            'notificacao_email_body_retirada' => "Olá,\n\nA pasta {codigo} da escola \"{escola}\" foi RETIRADA em {data_retirada} por {retirado_por} {retirado_documento}.\nObservação retirada: {observacao_retirada}\n\nAcesse o termo e comprovante: {link}\n\n— Sistema de Protocolo - URE",
            'notificacao_email_body_atraso' => "Atenção: A pasta {codigo} da escola \"{escola}\" está aguardando retirada há {dias} dias (desde {data_recebimento}).\nRecebido de: {recebido_de}\nItens: {itens}\n\nAcesse para regularizar: {link}\n\n— Sistema de Protocolo - URE (alerta automático)",
            'installed'                  => date('Y-m-d H:i:s'),
            'migrated_version'           => defined('PLUGIN_PROTOCOLO_VERSION') ? PLUGIN_PROTOCOLO_VERSION : '1.2.0',
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
        $out['notificacao_email_subject'] = trim((string)$out['notificacao_email_subject']);
        $out['notificacao_email_body_entrada'] = (string)$out['notificacao_email_body_entrada'];
        $out['notificacao_email_body_retirada'] = (string)$out['notificacao_email_body_retirada'];
        $out['notificacao_email_body_atraso'] = (string)$out['notificacao_email_body_atraso'];
        if ($out['notificacao_email_subject'] === '') $out['notificacao_email_subject'] = $defaults['notificacao_email_subject'];
        if ($out['notificacao_email_body_entrada'] === '') $out['notificacao_email_body_entrada'] = $defaults['notificacao_email_body_entrada'];
        if ($out['notificacao_email_body_retirada'] === '') $out['notificacao_email_body_retirada'] = $defaults['notificacao_email_body_retirada'];
        if ($out['notificacao_email_body_atraso'] === '') $out['notificacao_email_body_atraso'] = $defaults['notificacao_email_body_atraso'];
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
            if (isset($values['notificacao_email_subject'])) $values['notificacao_email_subject'] = trim((string)$values['notificacao_email_subject']);
            if (isset($values['notificacao_email_body_entrada'])) $values['notificacao_email_body_entrada'] = (string)$values['notificacao_email_body_entrada'];
            if (isset($values['notificacao_email_body_retirada'])) $values['notificacao_email_body_retirada'] = (string)$values['notificacao_email_body_retirada'];
            if (isset($values['notificacao_email_body_atraso'])) $values['notificacao_email_body_atraso'] = (string)$values['notificacao_email_body_atraso'];

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
        // Novo simplificado: Admin do Protocolo ou super-admin de config
        if (Session::haveRight('config', UPDATE)) return true;
        if (\GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_admin', READ)) return true;
        if (Session::haveRight('plugin_protocolo_admin', READ)) return true;
        if (Session::haveRight('plugin_protocolo_admin', UPDATE)) return true;
        // Fallback legado
        if (Session::haveRight('plugin_protocolo_config', UPDATE)) return true;
        if (Session::haveRight('plugin_protocolo_config', READ)) return true;
        // Checa DB direto para Admin
        return \GlpiPlugin\Protocolo\Profile::haveRightDB('plugin_protocolo_admin', READ);
    }
}
