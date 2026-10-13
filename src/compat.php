<?php
/**
 * Compatibilidade Protocolo entre GLPI 11 e GLPI 12.
 *
 * Espelha kanpro/inc/compat.php (commit aec8d16).
 * Helpers guardados por function_exists/method_exists para funcionar nas duas
 * versões. No GLPI 12: Plugin::getWebDir() foi removido, Session::getNewCSRFToken()
 * e Session::checkCSRF()/validateCSRF() viraram no-ops (só emitem deprecation) e
 * o CSRF passou a ser validado pelo CheckCsrfListener (Sec-Fetch-Site/Origin).
 */
if (!defined('GLPI_ROOT')) {
    // setup.php roda antes do GLPI_ROOT existir (carrega manifest); não morre aqui.
    // As funções abaixo só usam GLPI_ROOT em runtime, quando já definido.
}

if (!function_exists('protocolo_glpi_version')) {
    function protocolo_glpi_version(): string
    {
        return defined('GLPI_VERSION') ? (string) GLPI_VERSION : '';
    }
}

if (!function_exists('protocolo_glpi_major')) {
    function protocolo_glpi_major(): int
    {
        $v = protocolo_glpi_version();
        if ($v === '') {
            return 0;
        }
        return (int) explode('.', $v)[0];
    }
}

if (!function_exists('protocolo_is_glpi12_plus')) {
    function protocolo_is_glpi12_plus(): bool
    {
        return protocolo_glpi_major() >= 12;
    }
}

if (!function_exists('protocolo_web_dir')) {
    /**
     * URL base do plugin (ex.: "/glpi/plugins/protocolo" ou "/glpi/marketplace/protocolo").
     * GLPI <=11: usa Plugin::getWebDir(); GLPI 12 (método removido): monta via root_doc.
     * Suporta plugins/ ou marketplace/ sem duplicar root_doc.
     */
    function protocolo_web_dir(string $plugin = 'protocolo'): string
    {
        if (method_exists('Plugin', 'getWebDir')) {
            try {
                return (string) Plugin::getWebDir($plugin);
            } catch (\Throwable $e) {
                // cai para o fallback abaixo
            }
        }
        global $CFG_GLPI;
        $root = (is_array($CFG_GLPI) && isset($CFG_GLPI['root_doc'])) ? (string) $CFG_GLPI['root_doc'] : '';
        // Tenta detectar marketplace vs plugins pelo disco quando possível
        if (defined('GLPI_ROOT')) {
            $base = (string) GLPI_ROOT;
            if (@is_dir($base . '/marketplace/' . $plugin)) {
                return $root . '/marketplace/' . $plugin;
            }
        }
        return $root . '/plugins/' . $plugin;
    }
}

if (!function_exists('protocolo_php_dir')) {
    /**
     * Caminho físico do plugin. GLPI <=11: usa Plugin::getPhpDir(); GLPI 12: GLPI_ROOT/plugins.
     */
    function protocolo_php_dir(string $plugin = 'protocolo'): string
    {
        if (method_exists('Plugin', 'getPhpDir')) {
            try {
                return (string) Plugin::getPhpDir($plugin);
            } catch (\Throwable $e) {
            }
        }
        if (defined('GLPI_ROOT')) {
            $base = (string) GLPI_ROOT;
            if (@is_dir($base . '/marketplace/' . $plugin)) {
                return $base . '/marketplace/' . $plugin;
            }
            return $base . '/plugins/' . $plugin;
        }
        return __DIR__ . '/..';
    }
}

if (!function_exists('protocolo_csrf_token')) {
    /**
     * Token CSRF da página. No GLPI 12 não há token (retorna ''); o CSRF é
     * tratado pelo listener do core. No GLPI 11 mantém o comportamento atual.
     */
    function protocolo_csrf_token(): string
    {
        if (protocolo_is_glpi12_plus()) {
            return '';
        }
        if (method_exists('Session', 'getNewCSRFToken')) {
            try {
                return (string) Session::getNewCSRFToken();
            } catch (\Throwable $e) {
                return '';
            }
        }
        return '';
    }
}

if (!function_exists('protocolo_check_csrf')) {
    /**
     * Validação CSRF explícita. No GLPI 12 é no-op (o CheckCsrfListener cuida);
     * no GLPI 11 delega para Session::checkCSRF() preservando o token.
     */
    function protocolo_check_csrf(array $data): void
    {
        if (protocolo_is_glpi12_plus()) {
            return;
        }
        Session::checkCSRF($data, true);
    }
}

if (!function_exists('protocolo_validate_csrf')) {
    /**
     * Wrapper de Session::validateCSRF() com gate de versão.
     * No GLPI 12 sempre retorna true (listener do core valida).
     */
    function protocolo_validate_csrf(array $data): bool
    {
        if (protocolo_is_glpi12_plus()) {
            return true;
        }
        if (method_exists('Session', 'validateCSRF')) {
            try {
                return (bool) Session::validateCSRF($data);
            } catch (\Throwable $e) {
                return false;
            }
        }
        return true;
    }
}
