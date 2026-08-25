<?php
namespace GlpiPlugin\Protocolo;

use Session;

class EntityMail
{
    public static function getTable(): string
    {
        return 'glpi_plugin_protocolo_entity_emails';
    }

    public static function getEmailsForEntity(int $entities_id, bool $onlyActive = true): array
    {
        global $DB;
        $emails = [];
        try {
            if (!$DB->tableExists(self::getTable())) return [];
            $where = ['entities_id' => $entities_id];
            if ($onlyActive) $where['is_active'] = 1;
            $it = $DB->request(['FROM' => self::getTable(), 'WHERE' => $where, 'ORDER' => 'email']);
            foreach ($it as $row) {
                if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $row['email'];
                }
            }
        } catch (\Throwable $e) {
            error_log("[protocolo] EntityMail::getEmailsForEntity falhou: " . $e->getMessage());
        }
        return $emails;
    }

    public static function getAllGrouped(): array
    {
        global $DB;
        $out = [];
        try {
            if (!$DB->tableExists(self::getTable())) return [];
            $it = $DB->request(['FROM' => self::getTable(), 'ORDER' => 'entities_id, email']);
            foreach ($it as $row) {
                $eid = (int)$row['entities_id'];
                if (!isset($out[$eid])) $out[$eid] = [];
                $out[$eid][] = $row;
            }
        } catch (\Throwable $e) {}
        return $out;
    }

    public static function getAllWithEntityNames(): array
    {
        global $DB;
        $rows = [];
        try {
            if (!$DB->tableExists(self::getTable())) return [];
            $sql = "SELECT em.*, COALESCE(e.completename, e.name, CONCAT('ID ', em.entities_id)) AS entity_name
                    FROM glpi_plugin_protocolo_entity_emails em
                    LEFT JOIN glpi_entities e ON e.id = em.entities_id
                    ORDER BY entity_name, em.email";
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($r = $DB->fetchAssoc($res)) $rows[] = $r;
            } else {
                $it = $DB->request(['FROM' => self::getTable(), 'ORDER' => 'entities_id']);
                foreach ($it as $r) {
                    $r['entity_name'] = 'Entidade ' . $r['entities_id'];
                    $rows[] = $r;
                }
            }
        } catch (\Throwable $e) {}
        return $rows;
    }

    public static function add(int $entities_id, string $email): ?int
    {
        global $DB;
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        if (!$entities_id && $entities_id !== 0) return null;
        try {
            if (!$DB->tableExists(self::getTable())) return null;
            // Verifica duplicata
            $it = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['entities_id' => $entities_id, 'email' => $email], 'LIMIT' => 1]);
            foreach ($it as $r) return (int)$r['id'];
            $DB->insert(self::getTable(), [
                'entities_id' => $entities_id,
                'email' => $email,
                'is_active' => 1,
                'date_creation' => date('Y-m-d H:i:s')
            ]);
            return (int)$DB->insertId();
        } catch (\Throwable $e) {
            error_log("[protocolo] EntityMail::add falhou: " . $e->getMessage());
            return null;
        }
    }

    public static function delete(int $id): bool
    {
        global $DB;
        try {
            if (!$DB->tableExists(self::getTable())) return false;
            $DB->delete(self::getTable(), ['id' => $id]);
            return true;
        } catch (\Throwable $e) {
            error_log("[protocolo] EntityMail::delete falhou: " . $e->getMessage());
            return false;
        }
    }

    public static function toggle(int $id): bool
    {
        global $DB;
        try {
            if (!$DB->tableExists(self::getTable())) return false;
            $it = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['id' => $id], 'LIMIT' => 1]);
            foreach ($it as $row) {
                $new = $row['is_active'] ? 0 : 1;
                $DB->update(self::getTable(), ['is_active' => $new], ['id' => $id]);
                return true;
            }
        } catch (\Throwable $e) {}
        return false;
    }

    public static function getEntityName(int $entities_id): string
    {
        global $DB;
        try {
            $it = $DB->request(['FROM' => 'glpi_entities', 'WHERE' => ['id' => $entities_id], 'LIMIT' => 1]);
            foreach ($it as $r) return $r['completename'] ?? $r['name'] ?? "Entidade $entities_id";
        } catch (\Throwable $e) {}
        return $entities_id === 0 ? __('Entidade raiz', 'protocolo') : "Entidade $entities_id";
    }

    public static function canEdit(): bool
    {
        return Config::canEdit();
    }
}
