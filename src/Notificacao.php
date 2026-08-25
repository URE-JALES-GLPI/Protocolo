<?php
namespace GlpiPlugin\Protocolo;

use Session;
use Html;

class Notificacao
{
    public static function getTable(): string
    {
        return 'glpi_plugin_protocolo_notificacoes';
    }

    /**
     * Enfileira notificação na tabela plugin
     */
    public static function enqueue(int $pastaId, int $escolaId, string $canal, string $destinatario, string $mensagem): ?int
    {
        global $DB;
        if (!$pastaId || !$destinatario || !$mensagem) return null;
        $canal = in_array($canal, ['email', 'whatsapp', 'sistema']) ? $canal : 'sistema';
        try {
            $DB->insert(self::getTable(), [
                'plugin_protocolo_pastas_id'  => $pastaId,
                'plugin_protocolo_escolas_id' => $escolaId,
                'canal'                       => $canal,
                'destinatario'                => substr($destinatario, 0, 150),
                'mensagem'                    => $mensagem,
                'status'                      => 'pendente',
                'date_creation'               => date('Y-m-d H:i:s'),
            ]);
            return (int)$DB->insertId();
        } catch (\Throwable $e) {
            error_log("[protocolo] Notificacao::enqueue falhou: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cria notificações automáticas para evento de pasta
     * evento: entrada | retirada | atraso
     */
    public static function createForPasta(\GlpiPlugin\Protocolo\Pasta $pasta, string $evento): int
    {
        if (!Config::isNotificacaoAtiva()) return 0;
        $pastaId = $pasta->getID();
        $escolaId = (int)($pasta->fields['plugin_protocolo_escolas_id'] ?? 0);
        $created = 0;

        // Busca dados escola/entidade para e-mails
        global $DB;
        $escolaNome = Pasta::getEscolaName($escolaId);
        $codigo = $pasta->fields['codigo'] ?? "ID $pastaId";
        $destinatarios = [];

        // 1) E-mail da escola (tabela escolas)
        try {
            $it = $DB->request(['FROM' => 'glpi_plugin_protocolo_escolas', 'WHERE' => ['id' => $escolaId], 'LIMIT' => 1]);
            foreach ($it as $row) {
                if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $destinatarios[] = [$row['email'], 'escola'];
                }
            }
        } catch (\Throwable $e) {}

        // 2) E-mail da entidade (glpi_entities)
        try {
            $it = $DB->request(['FROM' => 'glpi_entities', 'WHERE' => ['id' => $escolaId], 'LIMIT' => 1]);
            foreach ($it as $row) {
                if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $destinatarios[] = [$row['email'], 'entidade'];
                }
            }
        } catch (\Throwable $e) {}

        // 3) Email cópia da config
        $copia = Config::get('notificacao_email_copia', '');
        if ($copia && filter_var($copia, FILTER_VALIDATE_EMAIL)) {
            $destinatarios[] = [$copia, 'copia'];
        }

        // Dedup por email
        $seen = [];
        $filtered = [];
        foreach ($destinatarios as [$email, $src]) {
            $low = strtolower($email);
            if (!isset($seen[$low])) {
                $seen[$low] = true;
                $filtered[] = [$email, $src];
            }
        }
        $destinatarios = $filtered;

        if (empty($destinatarios)) {
            // Mesmo sem e-mail, cria notificação sistema para dashboard
            $msg = self::buildMensagem($pasta, $escolaNome, $codigo, $evento);
            self::enqueue($pastaId, $escolaId, 'sistema', 'sistema', $msg);
            return 1;
        }

        foreach ($destinatarios as [$email, $src]) {
            $msg = self::buildMensagem($pasta, $escolaNome, $codigo, $evento);
            $subject = self::buildAssunto($codigo, $evento);
            // Mensagem com assunto embutido para e-mail (primeira linha)
            $fullMsg = $subject . "\n\n" . $msg;
            $id = self::enqueue($pastaId, $escolaId, 'email', $email, $fullMsg);
            if ($id) $created++;
        }

        // Sempre cria uma de sistema para dashboard
        $msgSistema = self::buildMensagem($pasta, $escolaNome, $codigo, $evento);
        self::enqueue($pastaId, $escolaId, 'sistema', 'sistema', $msgSistema);

        return $created;
    }

    public static function buildAssunto(string $codigo, string $evento): string
    {
        $prefix = trim((string)Config::get('notificacao_assunto_prefix', '[Protocolo]'));
        if ($prefix === '') $prefix = '[Protocolo]';
        $map = [
            'entrada'  => "Nova pasta registrada",
            'retirada' => "Pasta retirada",
            'atraso'   => "Pasta com retirada pendente",
            'lembrete' => "Lembrete de retirada",
        ];
        $acao = $map[$evento] ?? $evento;
        return "$prefix $acao - $codigo";
    }

    public static function buildMensagem(\GlpiPlugin\Protocolo\Pasta $pasta, string $escolaNome, string $codigo, string $evento): string
    {
        global $CFG_GLPI;
        $root = $CFG_GLPI['root_doc'] ?? '';
        $link = $root . "/plugins/protocolo/front/pasta.form.php?id=" . $pasta->getID();
        $dataRec = Html::convDateTime($pasta->fields['data_recebimento'] ?? date('Y-m-d H:i:s'));
        $recebidoDe = $pasta->fields['recebido_de'] ?? '-';
        $itensResumo = '';
        try {
            global $DB;
            $it = $DB->request(['COUNT' => 'cpt', 'FROM' => 'glpi_plugin_protocolo_itens', 'WHERE' => ['plugin_protocolo_pastas_id' => $pasta->getID()]]);
            foreach ($it as $r) $itensResumo = $r['cpt'] . " item(s)";
        } catch (\Throwable $e) {}

        if ($evento === 'entrada') {
            return "Olá,\n\nA pasta $codigo destinada à escola \"$escolaNome\" foi registrada no protocolo em $dataRec.\n"
                . "Recebido de: $recebidoDe\n"
                . "Itens: $itensResumo\n"
                . "Status: Aguardando retirada\n\n"
                . "Acesse: $link\n\n"
                . "Quando for retirar, apresente o Termo de Recebimento impresso + documento com foto.\n"
                . "— Sistema de Protocolo - URE";
        }
        if ($evento === 'retirada') {
            $retiradoPor = $pasta->fields['retirado_por'] ?? '-';
            $dataRet = Html::convDateTime($pasta->fields['data_retirada'] ?? date('Y-m-d H:i:s'));
            return "Olá,\n\nA pasta $codigo da escola \"$escolaNome\" foi RETIRADA em $dataRet por $retiradoPor.\n"
                . "Acesse o termo e comprovante: $link\n\n"
                . "— Sistema de Protocolo - URE";
        }
        if ($evento === 'atraso') {
            $dias = 0;
            try { $dias = (int)floor((time() - strtotime($pasta->fields['data_recebimento'])) / 86400); } catch (\Throwable $e) {}
            return "Atenção: A pasta $codigo da escola \"$escolaNome\" está aguardando retirada há $dias dias (desde $dataRec).\n"
                . "Recebido de: $recebidoDe\n"
                . "Acesse para regularizar: $link\n\n"
                . "— Sistema de Protocolo - URE (alerta automático)";
        }
        return "Evento $evento para pasta $codigo - escola $escolaNome. Acesse: $link";
    }

    /**
     * Tenta enviar pendentes (chamado por cron)
     * Retorna [enviados, falhas]
     */
    public static function processPending(int $limit = 20): array
    {
        global $DB, $CFG_GLPI;
        $enviados = 0;
        $falhas = 0;
        try {
            $it = $DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => ['status' => 'pendente'],
                'ORDER' => 'id ASC',
                'LIMIT' => $limit
            ]);
            $rows = [];
            foreach ($it as $r) $rows[] = $r;

            foreach ($rows as $row) {
                $id = (int)$row['id'];
                $canal = $row['canal'];
                $dest = $row['destinatario'];
                $msg = $row['mensagem'];

                if ($canal === 'sistema') {
                    // Sistema = apenas marca como enviado (visível em dashboard futuro)
                    $DB->update(self::getTable(), ['status' => 'enviado'], ['id' => $id]);
                    $enviados++;
                    continue;
                }

                if ($canal === 'email') {
                    $ok = self::sendEmail($dest, $msg, $row);
                    if ($ok) {
                        $DB->update(self::getTable(), ['status' => 'enviado'], ['id' => $id]);
                        $enviados++;
                    } else {
                        $DB->update(self::getTable(), ['status' => 'falha'], ['id' => $id]);
                        $falhas++;
                    }
                } elseif ($canal === 'whatsapp') {
                    // Placeholder - marca como falha com log para não travar
                    error_log("[protocolo] Notificacao whatsapp não configurado para $dest (ID $id)");
                    $DB->update(self::getTable(), ['status' => 'falha'], ['id' => $id]);
                    $falhas++;
                }
            }
        } catch (\Throwable $e) {
            error_log("[protocolo] Notificacao::processPending falhou: " . $e->getMessage());
        }
        return [$enviados, $falhas];
    }

    /**
     * Envia e-mail usando GLPI queuednotification ou GLPIMailer ou mail()
     */
    public static function sendEmail(string $to, string $mensagem, array $row): bool
    {
        global $CFG_GLPI, $DB;

        // Tenta extrair assunto (primeira linha)
        $lines = explode("\n", $mensagem, 2);
        $subject = trim($lines[0]);
        if (mb_strlen($subject) > 120) $subject = mb_substr($subject, 0, 120);
        $body = $lines[1] ?? $mensagem;
        // Se primeira linha não parece assunto, usa padrão
        if (!str_starts_with($subject, '[')) {
            $subject = "[Protocolo] Notificação - Pasta " . ($row['plugin_protocolo_pastas_id'] ?? '');
            $body = $mensagem;
        }

        $bodyHtml = nl2br(htmlspecialchars($body)) . "<br><hr><small style='color:#666'>Enviado automaticamente pelo Sistema de Protocolo - URE. Não responda.</small>";
        $bodyText = $body;

        // 1) Tenta usar QueuedNotification via Model (GLPI 10/11) ou insert direto
        try {
            // Tenta via classe oficial se existir
            if (class_exists('\QueuedNotification')) {
                $qn = new \QueuedNotification();
                $from = $CFG_GLPI['admin_email'] ?? 'noreply@protocolo.local';
                if (empty($from) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $it = $DB->request(['FROM' => 'glpi_configs', 'WHERE' => ['context' => 'core', 'name' => 'admin_email'], 'LIMIT' => 1]);
                        foreach ($it as $r) if (!empty($r['value'])) $from = $r['value'];
                    } catch (\Throwable $e) {}
                }
                $inputQ = [
                    'itemtype'          => 'GlpiPlugin\\Protocolo\\Pasta',
                    'items_id'          => (int)($row['plugin_protocolo_pastas_id'] ?? 0),
                    'notificationtemplates_id' => 0,
                    'entities_id'       => 0,
                    'is_deleted'        => 0,
                    'sent_try'          => 0,
                    'create_time'       => date('Y-m-d H:i:s'),
                    'send_time'         => date('Y-m-d H:i:s'),
                    'name'              => $subject,
                    'sender'            => $from,
                    'sendername'        => 'Protocolo URE',
                    'recipient'         => $to,
                    'recipientname'     => $to,
                    'replyto'           => $from,
                    'replytoname'       => 'Protocolo',
                    'headers'           => '',
                    'body_html'         => $bodyHtml,
                    'body_text'         => $bodyText,
                    'messageid'         => bin2hex(random_bytes(8)) . '@protocolo',
                ];
                // QueuedNotification::add pode validar nome de campo 'name' vs 'subject'
                $added = $qn->add($inputQ);
                if ($added) return true;
                // fallback para insert direto se add falhou
            }
            if ($DB->tableExists('glpi_queuednotifications')) {
                // Descobre nome da entidade para from
                $from = $CFG_GLPI['admin_email'] ?? null;
                if (!$from) {
                    try {
                        $it = $DB->request(['FROM' => 'glpi_configs', 'WHERE' => ['context' => 'core', 'name' => 'admin_email'], 'LIMIT' => 1]);
                        foreach ($it as $r) $from = $r['value'];
                    } catch (\Throwable $e) {}
                }
                $from = $from ?: 'noreply@protocolo.local';

                $DB->insert('glpi_queuednotifications', [
                    'itemtype'          => 'GlpiPlugin\\Protocolo\\Pasta',
                    'items_id'          => (int)($row['plugin_protocolo_pastas_id'] ?? 0),
                    'notificationtemplates_id' => 0,
                    'entities_id'       => 0,
                    'is_deleted'        => 0,
                    'sent_try'          => 0,
                    'create_time'       => date('Y-m-d H:i:s'),
                    'send_time'         => date('Y-m-d H:i:s'),
                    'name'              => $subject,
                    'sender'            => $from,
                    'sendername'        => 'Protocolo URE',
                    'recipient'         => $to,
                    'recipientname'     => $to,
                    'replyto'           => $from,
                    'replytoname'       => 'Protocolo',
                    'headers'           => '',
                    'body_html'         => $bodyHtml,
                    'body_text'         => $bodyText,
                    'messageid'         => bin2hex(random_bytes(8)) . '@protocolo',
                    'documents'         => '',
                ]);
                return true;
            }
        } catch (\Throwable $e) {
            error_log("[protocolo] sendEmail queued falhou, tentando GLPIMailer: " . $e->getMessage());
        }

        // 2) Tenta GLPIMailer / NotificationMailing
        try {
            if (class_exists('\GLPIMailer')) {
                $mailer = new \GLPIMailer();
                // GLPIMailer varia por versão - tenta addCustomHeader + send
                if (method_exists($mailer, 'sendNotification')) {
                    // Algumas versões
                    return true;
                }
                // fallback usa PHPMailer interno
                if (method_exists($mailer, 'addAddress')) {
                    $mailer->addAddress($to);
                    $mailer->Subject = $subject;
                    $mailer->Body = $bodyHtml;
                    $mailer->AltBody = $bodyText;
                    return $mailer->send();
                }
            }
        } catch (\Throwable $e) {
            error_log("[protocolo] GLPIMailer falhou: " . $e->getMessage());
        }

        // 3) Fallback mail()
        try {
            $headers = "From: Protocolo URE <noreply@protocolo.local>\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            return mail($to, $subject, $bodyText, $headers);
        } catch (\Throwable $e) {
            error_log("[protocolo] mail() falhou: " . $e->getMessage());
            return false;
        }
    }

    public static function countByStatus(string $status, array $entityFilter = []): int
    {
        global $DB;
        $where = ['status' => $status];
        if (!empty($entityFilter)) {
            // filtra por escola/entidade se possível - via pasta entities_id
            // simplifica: não filtra por enquanto
        }
        try {
            $it = $DB->request(['COUNT' => 'cpt', 'FROM' => self::getTable(), 'WHERE' => $where]);
            foreach ($it as $r) return (int)$r['cpt'];
        } catch (\Throwable $e) {}
        return 0;
    }

    public static function cronInfo(): array
    {
        return [
            'description' => __('Envio de notificações pendentes do Protocolo', 'protocolo'),
            'parameter'   => __('Número de notificações a processar por execução', 'protocolo'),
        ];
    }

    public static function cronProtocolo(\CronTask $task): int
    {
        $limit = (int)($task->fields['param'] ?? 20);
        if ($limit <= 0) $limit = 20;
        [$enviados, $falhas] = self::processPending($limit);
        $task->log("Protocolo: $enviados enviadas, $falhas falhas");
        // Também verifica atrasadas e enfileira lembrete (no máximo 1 por dia por pasta)
        try { self::enfileirarAtrasadas(); } catch (\Throwable $e) {}
        return $enviados > 0 ? 1 : 0;
    }

    public static function cronProtocolo_send(\CronTask $task): int
    {
        return self::cronProtocolo($task);
    }

    /**
     * Enfileira notificações de atraso para pastas > prazo
     */
    public static function enfileirarAtrasadas(): int
    {
        if (!Config::isNotificacaoAtiva() || !Config::isAlertaAtivo()) return 0;
        $prazo = Config::getPrazoAlertaDias();
        global $DB;
        $count = 0;
        try {
            // Pega pastas aguardando há mais de prazo dias e que não tiveram notificação de atraso nas últimas 24h
            $sql = "SELECT p.* FROM glpi_plugin_protocolo_pastas p
                    WHERE p.status='aguardando' AND p.is_deleted=0
                    AND DATEDIFF(NOW(), p.data_recebimento) >= $prazo
                    AND NOT EXISTS (
                        SELECT 1 FROM glpi_plugin_protocolo_notificacoes n
                        WHERE n.plugin_protocolo_pastas_id=p.id
                        AND n.canal='email'
                        AND n.mensagem LIKE '%Atenção: A pasta%'
                        AND n.date_creation > DATE_SUB(NOW(), INTERVAL 1 DAY)
                    )
                    LIMIT 10";
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $escolaId = (int)$row['plugin_protocolo_escolas_id'];
                    $codigo = $row['codigo'];
                    $escolaNome = Pasta::getEscolaName($escolaId);
                    $msg = "Atenção: A pasta $codigo da escola \"$escolaNome\" está aguardando retirada há $prazo+ dias.\nAcesse o GLPI para regularizar.";
                    // busca email cópia
                    $dest = Config::get('notificacao_email_copia', '');
                    if ($dest && filter_var($dest, FILTER_VALIDATE_EMAIL)) {
                        $assunto = self::buildAssunto($codigo, 'atraso');
                        self::enqueue((int)$row['id'], $escolaId, 'email', $dest, $assunto . "\n\n" . $msg);
                        self::enqueue((int)$row['id'], $escolaId, 'sistema', 'sistema', $msg);
                        $count++;
                    } else {
                        // tenta email da escola
                        $schoolEmail = null;
                        $it = $DB->request(['FROM' => 'glpi_plugin_protocolo_escolas', 'WHERE' => ['id' => $escolaId], 'LIMIT' => 1]);
                        foreach ($it as $erow) $schoolEmail = $erow['email'] ?? null;
                        if ($schoolEmail && filter_var($schoolEmail, FILTER_VALIDATE_EMAIL)) {
                            $assunto = self::buildAssunto($codigo, 'atraso');
                            self::enqueue((int)$row['id'], $escolaId, 'email', $schoolEmail, $assunto . "\n\n" . $msg);
                            self::enqueue((int)$row['id'], $escolaId, 'sistema', 'sistema', $msg);
                            $count++;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("[protocolo] enfileirarAtrasadas falhou: " . $e->getMessage());
        }
        return $count;
    }
}
