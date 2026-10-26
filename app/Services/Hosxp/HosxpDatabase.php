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
