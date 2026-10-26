<?php
namespace GlpiPlugin\Protocolo;

use RuntimeException;
use Throwable;

/**
 * Integração com o Assignat-ure (adaptador "Genérica").
 *
 * Contrato (ver README do Assignat-ure):
 * Dois tipos de pedido, identificados pelo prefixo do id:
 *  entrada:123  → pasta "aguardando assinatura" (assina quem deixou); ao assinar passa a "aguardando" retirada
 *  retirada:123 → pasta "aguardando" retirada (assina quem retira); ao assinar passa a "retirada"
 *  (id sem prefixo = retirada, por compatibilidade)
 *
 *  GET  ?list=pending&limit=500&offset=0 → entradas e retiradas pendentes
 *  GET  ?id=entrada:123                  → detalhe
 *  POST {"action":"signature_completed", ...} → conclui a assinatura
 * Autenticação: cabeçalho X-Assignature-Token.
 */
class Assignature
{
    public const TOKEN_KEY = 'assignature_token';
    public const URL_KEY   = 'assignature_url';

    /** Token atual; gera um na primeira leitura. */
    public static function token(): string
    {
        $token = trim((string) Config::get(self::TOKEN_KEY, ''));
        if ($token === '') {
            $token = self::regenerateToken();
        }
        return $token;
    }

    public static function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(24));
        Config::set([self::TOKEN_KEY => $token]);
        return $token;
    }

    /** URL do request.php do Assignat-ure (opcional, usada no botão do dashboard). */
    public static function requestUrl(): string
    {
        return trim((string) Config::get(self::URL_KEY, ''));
    }

    /** Link para abrir a pasta no Assignat-ure, ou '' se não configurado. */
    public static function linkForPasta(int $pastaId): string
    {
        $url = self::requestUrl();
        if ($url === '' || $pastaId <= 0) {
            return '';
        }
        return $url . (str_contains($url, '?') ? '&' : '?') . 'source=protocolo&ref=' . $pastaId;
    }

    public static function checkToken(string $provided): bool
    {
        $expected = trim((string) Config::get(self::TOKEN_KEY, ''));
        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    /** Colunas usadas para guardar a prova da assinatura (idempotência do retorno). */
    public static function ensureColumns(): void
    {
        global $DB;
        $table = 'glpi_plugin_protocolo_pastas';
        $cols = [
            'assignature_sha256'            => 'VARCHAR(64) DEFAULT NULL',
            'assignature_signed_at'         => 'DATETIME DEFAULT NULL',
            'assignature_entrada_sha256'    => 'VARCHAR(64) DEFAULT NULL',
            'assignature_entrada_signed_at' => 'DATETIME DEFAULT NULL',
        ];
        $changed = false;
        foreach ($cols as $col => $def) {
            if (!$DB->fieldExists($table, $col, false)) {
                $DB->doQuery("ALTER TABLE `$table` ADD COLUMN `$col` $def");
                $changed = true;
            }
        }
        if ($changed) {
            $DB->listFields($table, false);
        }
    }

    public const KIND_ENTRADA  = 'entrada';
    public const KIND_RETIRADA = 'retirada';

    /** Status da pasta que gera cada tipo de pedido. */
    private static function statusOf(string $kind): string
    {
        return $kind === self::KIND_ENTRADA ? 'aguardando_assinatura' : 'aguardando';
    }

    /** "entrada:12" → ['entrada', 12]; "12" → ['retirada', 12]. */
    public static function parseRef(string $ref): array
    {
        $ref = trim($ref);
        if (preg_match('/^(entrada|retirada):(\d+)$/', $ref, $m)) {
            return [$m[1], (int) $m[2]];
        }
        return [self::KIND_RETIRADA, ctype_digit($ref) ? (int) $ref : 0];
    }

    public static function refFor(string $kind, int $id): string
    {
        return $kind . ':' . $id;
    }

    /** Link para abrir o pedido (entrada ou retirada, conforme o status) no Assignat-ure. */
    public static function linkForRow(array $row): string
    {
        $kind = (string) ($row['status'] ?? '') === 'aguardando_assinatura' ? self::KIND_ENTRADA : self::KIND_RETIRADA;
        $url = self::requestUrl();
        $id = (int) ($row['id'] ?? 0);
        if ($url === '' || $id <= 0) {
            return '';
        }
        return $url . (str_contains($url, '?') ? '&' : '?') . 'source=protocolo&ref=' . rawurlencode(self::refFor($kind, $id));
    }

    /** Pedidos pendentes (entradas primeiro), paginados. */
    public static function listPending(int $limit, int $offset): array
    {
        global $DB;
        $requests = [];
        foreach ($DB->request([
            'FROM'  => 'glpi_plugin_protocolo_pastas',
            'WHERE' => ['status' => ['aguardando_assinatura', 'aguardando'], 'is_deleted' => 0],
            'ORDER' => ['status ASC', 'data_recebimento ASC', 'id ASC'],
            'START' => $offset,
            'LIMIT' => $limit,
        ]) as $row) {
            $kind = (string) $row['status'] === 'aguardando_assinatura' ? self::KIND_ENTRADA : self::KIND_RETIRADA;
            $requests[] = self::payload($row, $kind);
        }
        return $requests;
    }

    public static function get(int $id): ?array
    {
        global $DB;
        if ($id <= 0) {
            return null;
        }
        $row = $DB->request([
            'FROM'  => 'glpi_plugin_protocolo_pastas',
            'WHERE' => ['id' => $id, 'is_deleted' => 0],
            'LIMIT' => 1,
        ])->current();
        return is_array($row) ? $row : null;
    }

    /** Tipos de arquivo marcados na pasta (substituem a antiga lista de itens). */
    public static function tiposDaPasta(int $id): array
    {
        global $DB;
        $nomes = [];
        foreach ($DB->request([
            'SELECT'    => ['t.name'],
            'FROM'      => 'glpi_plugin_protocolo_pastatipos AS pt',
            'INNER JOIN' => ['glpi_plugin_protocolo_tipos AS t' => ['ON' => ['pt' => 'plugin_protocolo_tipos_id', 't' => 'id']]],
            'WHERE'     => ['pt.plugin_protocolo_pastas_id' => $id],
            'ORDER'     => 't.name ASC',
        ]) as $r) {
            $nomes[] = trim((string) $r['name']);
        }
        return array_values(array_filter($nomes, static fn($n) => $n !== ''));
    }

    /**
     * Dados do pedido no formato do Assignat-ure. Sem lista de itens: o termo
     * mostra apenas os tipos de arquivo da pasta.
     */
    public static function payload(array $row, ?string $kind = null): array
    {
        $id = (int) $row['id'];
        if ($kind === null) {
            $kind = (string) $row['status'] === 'aguardando_assinatura' ? self::KIND_ENTRADA : self::KIND_RETIRADA;
        }
        $especie = Pasta::getEspecieLabel((string) ($row['categoria'] ?? 'pasta'), $row['especie_outro'] ?? null);
        $origem = trim(strip_tags(html_entity_decode(Pasta::getOrigemDestinoDisplay($row, 'origem'))));
        $destino = trim(strip_tags(html_entity_decode(Pasta::getOrigemDestinoDisplay($row, 'destino'))));
        $assunto = trim((string) ($row['assunto'] ?? ''));
        $kindLabel = $kind === self::KIND_ENTRADA ? 'Entrada' : 'Retirada';
        $statusLabel = $kind === self::KIND_ENTRADA ? 'Aguardando assinatura de entrada' : 'Aguardando retirada';
        $tipos = self::tiposDaPasta($id);

        $description = [$kindLabel . ' de ' . mb_strtolower($especie, 'UTF-8')];
        if ($assunto !== '') {
            $description[] = $assunto;
        }
        $description[] = $origem . ' → ' . $destino;
        if ($tipos !== []) {
            $description[] = 'Tipos: ' . implode(', ', $tipos);
        }
        if ($kind === self::KIND_RETIRADA && trim((string) ($row['recebido_de'] ?? '')) !== '') {
            $description[] = 'Entregue por: ' . trim((string) $row['recebido_de']);
        }

        return [
            'id'               => self::refFor($kind, $id),
            'kind'             => $kind,
            'pasta_id'         => $id,
            'code'             => (string) $row['codigo'],
            'title'            => $kindLabel . ' — ' . $especie . ' ' . $row['codigo'] . ' — ' . $destino,
            'description'      => implode("\n", $description),
            'status'           => 'pending',
            'status_label'     => $statusLabel,
            'especie'          => $especie,
            'assunto'          => $assunto,
            'origem'           => $origem,
            'destino'          => $destino,
            'tipos'            => $tipos,
            'recebido_de'      => (string) ($row['recebido_de'] ?? ''),
            'data_recebimento' => $row['data_recebimento'] ?? null,
            'board'            => ['name' => $destino],
            // um "item" por tipo, para o termo listar o que está sendo assinado
            'machines'         => array_map(static fn($n, $i) => [
                'id' => $i + 1, 'label' => $n, 'model_name' => $especie,
                'serial' => '', 'status_label' => $statusLabel, 'report' => '',
            ], $tipos, array_keys($tipos)),
        ];
    }

    /**
     * Retorno do Assignat-ure. Idempotente — repetir o mesmo retorno não altera nada.
     *  entrada:  aguardando_assinatura → aguardando (quem assinou passa a ser "recebido de")
     *  retirada: aguardando → retirada
     */
    public static function complete(string $ref, string $signerName, string $signedAt, string $sha256): void
    {
        global $DB;
        [$kind, $id] = self::parseRef($ref);
        $signerName = mb_strtoupper(trim($signerName), 'UTF-8');
        if ($id <= 0 || $signerName === '' || !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new RuntimeException('Dados da assinatura inválidos.');
        }
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $signedAt);
        $signedAt = $date !== false ? $date->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

        self::ensureColumns();
        $entrada = $kind === self::KIND_ENTRADA;
        $shaCol = $entrada ? 'assignature_entrada_sha256' : 'assignature_sha256';

        $DB->beginTransaction();
        try {
            $DB->doQuery('SELECT `id` FROM `glpi_plugin_protocolo_pastas` WHERE `id` = ' . (int) $id . ' FOR UPDATE');
            $row = self::get($id);
            if ($row === null) {
                throw new RuntimeException('Pasta não encontrada.');
            }
            $status = (string) $row['status'];
            if ((string) ($row[$shaCol] ?? '') === $sha256) {
                // mesmo retorno repetido: já aplicado
                $DB->commit();
                return;
            }
            if ($status !== self::statusOf($kind)) {
                throw new RuntimeException($entrada
                    ? 'Esta pasta não está aguardando assinatura de entrada.'
                    : 'Esta pasta não está aguardando retirada.');
            }
            if ($entrada) {
                $DB->update('glpi_plugin_protocolo_pastas', [
                    'status'                        => 'aguardando',
                    'recebido_de'                   => mb_substr($signerName, 0, 150),
                    'assignature_entrada_sha256'    => $sha256,
                    'assignature_entrada_signed_at' => $signedAt,
                    'date_mod'                      => date('Y-m-d H:i:s'),
                ], ['id' => $id]);
            } else {
                $DB->update('glpi_plugin_protocolo_pastas', [
                    'status'                => 'retirada',
                    'data_retirada'         => $signedAt,
                    'retirado_por'          => mb_substr($signerName, 0, 150),
                    'observacao_retirada'   => 'RETIRADA ASSINADA NO ASSIGNAT-URE. SHA-256: ' . $sha256,
                    'assignature_sha256'    => $sha256,
                    'assignature_signed_at' => $signedAt,
                    'date_mod'              => date('Y-m-d H:i:s'),
                ], ['id' => $id]);
            }
            $DB->commit();
        } catch (Throwable $e) {
            $DB->rollBack();
            throw $e;
        }

        // Avisos por e-mail: entrada (ao concluir a assinatura) e retirada.
        try {
            $pasta = new Pasta();
            if ($pasta->getFromDB($id) && class_exists(Notificacao::class)) {
                Notificacao::createForPasta($pasta, $entrada ? 'entrada' : 'retirada');
            }
        } catch (Throwable $e) {
            error_log('[protocolo] Assignat-ure: notificação falhou (pasta ' . $id . '): ' . $e->getMessage());
        }
    }
}
