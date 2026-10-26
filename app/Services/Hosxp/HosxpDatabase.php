<?php

namespace App\Services\Hosxp;

use Illuminate\Support\Facades\DB;

/**
 * Read-only gateway to the HOSxP hospital database (connection "hosxp").
 */
class HosxpDatabase
{
    public const CONNECTION = 'hosxp';

    /**
     * Run a SELECT against the HOSxP database.
     *
     * @param  array<string, mixed>  $params  Named bindings (without the leading colon)
     * @return array<int, array<string, mixed>>
     *
     * @throws \Throwable When the HOSxP database is unreachable or the query fails
     */
    public function select(string $sql, array $params = [], int $limit = 0): array
    {
        $rows = array_map(
            fn (object $row): array => (array) $row,
            DB::connection(self::CONNECTION)->select($sql, $params)
        );

        return $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
    }

    /**
     * Verify a HOSxP login and return the officer when they are an active
     * member of the "ผู้ดูแลระบบ" group (officer_group_id 19), otherwise null.
     *
     * @return array{officer_id: int, name: string}|null
     */
    public function authenticateAdministrator(string $loginName, string $password): ?array
    {
        $sql = "SELECT o.officer_id, o.officer_name, o.officer_login_password_md5
                FROM officer o
                JOIN officer_group_list ogl ON ogl.officer_id = o.officer_id
                JOIN officer_group og ON og.officer_group_id = ogl.officer_group_id
                WHERE (og.officer_group_name = 'ผู้ดูแลระบบ' OR og.officer_group_id = 19)
                  AND o.officer_login_name = :login_name
                  AND o.officer_active = 'Y'";

        foreach ($this->select($sql, ['login_name' => $loginName], 5) as $officer) {
            $storedHash = strtolower((string) ($officer['officer_login_password_md5'] ?? ''));

            if ($storedHash !== '' && hash_equals($storedHash, md5($password))) {
                return [
                    'officer_id' => (int) $officer['officer_id'],
                    'name' => (string) ($officer['officer_name'] ?? $loginName),
                ];
            }
        }

        return null;
    }

    /**
     * Hospital code from opdconfig.hospitalcode.
     */
    public function hospitalCode(): ?string
    {
        $rows = $this->select('SELECT hospitalcode FROM opdconfig LIMIT 1', [], 1);

        return ! empty($rows[0]['hospitalcode']) ? (string) $rows[0]['hospitalcode'] : null;
    }

    /**
     * Hospital name from the hospcode table.
     */
    public function hospitalName(string $hospitalCode): ?string
    {
        $rows = $this->select('SELECT name FROM hospcode WHERE hospcode = :hospcode', ['hospcode' => $hospitalCode], 1);

        return ! empty($rows[0]['name']) ? (string) $rows[0]['name'] : null;
    }
}
