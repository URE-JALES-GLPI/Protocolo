<?php
namespace GlpiPlugin\Protocolo;

use RuntimeException;
use Throwable;

/**
 * Integração com o Assignat-ure (adaptador "Genérica").
 *
 * Contrato (ver README do Assignat-ure):
 *  GET  ?list=pending&limit=500&offset=0 → pastas aguardando retirada
 *  GET  ?id=123                          → detalhe de uma pasta
 *  POST {"action":"signature_completed", ...} → marca a pasta como retirada
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
            'assignature_sha256'    => 'VARCHAR(64) DEFAULT NULL',
            'assignature_signed_at' => 'DATETIME DEFAULT NULL',
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

    public static function statusFor(string $status): string
    {
        return match ($status) {
            'aguardando' => 'pending',
            'retirada'   => 'done',
            'cancelada'  => 'cancelled',
            default      => $status,
        };
    }

    /** Pastas aguardando retirada, paginadas. */
    public static function listPending(int $limit, int $offset): array
    {
        global $DB;
        $requests = [];
        foreach ($DB->request([
            'FROM'  => 'glpi_plugin_protocolo_pastas',
            'WHERE' => ['status' => 'aguardando', 'is_deleted' => 0],
            'ORDER' => ['data_recebimento ASC', 'id ASC'],
            'START' => $offset,
            'LIMIT' => $limit,
        ]) as $row) {
            $requests[] = self::payload($row);
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

    /**
     * Dados de uma pasta no formato do Assignat-ure. Os itens vão em "machines"
     * para aparecerem linha a linha no termo (mesmo formato usado pelo Tralla).
     */
    public static function payload(array $row): array
    {
        global $DB;
        $id = (int) $row['id'];
        $especie = Pasta::getEspecieLabel((string) ($row['categoria'] ?? 'pasta'), $row['especie_outro'] ?? null);
        $origem = trim(strip_tags(html_entity_decode(Pasta::getOrigemDestinoDisplay($row, 'origem'))));
        $destino = trim(strip_tags(html_entity_decode(Pasta::getOrigemDestinoDisplay($row, 'destino'))));
        $assunto = trim((string) ($row['assunto'] ?? ''));
        $statusLabel = match ((string) $row['status']) {
            'aguardando' => 'Aguardando retirada',
            'retirada'   => 'Retirada',
            'cancelada'  => 'Cancelada',
            default      => (string) $row['status'],
        };

        $itens = [];
        $machines = [];
        foreach ($DB->request([
            'FROM'  => 'glpi_plugin_protocolo_itens',
            'WHERE' => ['plugin_protocolo_pastas_id' => $id],
            'ORDER' => 'id ASC',
        ]) as $item) {
            $qtd = max(1, (int) $item['quantidade']);
            $nome = trim((string) $item['name']);
            $label = $qtd > 1 ? $nome . ' (' . $qtd . ')' : $nome;
            $itens[] = $label;
            $machines[] = [
                'id'           => (int) $item['id'],
                'label'        => $label,
                'model_name'   => $especie,
                'serial'       => '',
                'status_label' => $statusLabel,
                'report'       => trim((string) ($item['comment'] ?? '')),
            ];
        }

        $description = [];
        if ($assunto !== '') {
            $description[] = $assunto;
        }
        $description[] = $origem . ' → ' . $destino;
        if (trim((string) ($row['recebido_de'] ?? '')) !== '') {
            $description[] = 'Recebido de: ' . trim((string) $row['recebido_de']);
        }
        if ($itens !== []) {
            $description[] = 'Itens: ' . implode(', ', $itens);
        }

        return [
            'id'               => (string) $id,
            'code'             => (string) $row['codigo'],
            'title'            => $especie . ' ' . $row['codigo'] . ' — ' . $destino,
            'description'      => implode("\n", $description),
            'status'           => self::statusFor((string) $row['status']),
            'status_label'     => $statusLabel,
            'especie'          => $especie,
            'assunto'          => $assunto,
            'origem'           => $origem,
            'destino'          => $destino,
            'recebido_de'      => (string) ($row['recebido_de'] ?? ''),
            'data_recebimento' => $row['data_recebimento'] ?? null,
            'board'            => ['name' => $destino],
            'machines'         => $machines,
        ];
    }

    /**
     * Retorno do Assignat-ure: conclui a retirada da pasta. Idempotente — repetir
     * o mesmo retorno não altera nada.
     */
    public static function complete(int $id, string $signerName, string $signedAt, string $sha256): void
    {
        global $DB;
        $signerName = mb_strtoupper(trim($signerName), 'UTF-8');
        if ($id <= 0 || $signerName === '' || !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new RuntimeException('Dados da assinatura inválidos.');
        }
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $signedAt);
        $signedAt = $date !== false ? $date->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

        self::ensureColumns();

        $DB->beginTransaction();
        try {
            $DB->doQuery('SELECT `id` FROM `glpi_plugin_protocolo_pastas` WHERE `id` = ' . (int) $id . ' FOR UPDATE');
            $row = self::get($id);
            if ($row === null) {
                throw new RuntimeException('Pasta não encontrada.');
            }
            if ((string) $row['status'] === 'retirada' && (string) ($row['assignature_sha256'] ?? '') === $sha256) {
                $DB->commit();
                return;
            }
            if ((string) $row['status'] !== 'aguardando') {
                throw new RuntimeException('Esta pasta não está aguardando retirada.');
            }
            $DB->update('glpi_plugin_protocolo_pastas', [
                'status'                => 'retirada',
                'data_retirada'         => $signedAt,
                'retirado_por'          => mb_substr($signerName, 0, 150),
                'observacao_retirada'   => 'RETIRADA ASSINADA NO ASSIGNAT-URE. SHA-256: ' . $sha256,
                'assignature_sha256'    => $sha256,
                'assignature_signed_at' => $signedAt,
                'date_mod'              => date('Y-m-d H:i:s'),
            ], ['id' => $id]);
            $DB->commit();
        } catch (Throwable $e) {
            $DB->rollBack();
            throw $e;
        }

        // Mesmo aviso por e-mail da retirada feita pelo Protocolo.
        try {
            $pasta = new Pasta();
            if ($pasta->getFromDB($id) && class_exists(Notificacao::class)) {
                Notificacao::createForPasta($pasta, 'retirada');
            }
        } catch (Throwable $e) {
            error_log('[protocolo] Assignat-ure: notificação de retirada falhou (pasta ' . $id . '): ' . $e->getMessage());
        }
    }
}
