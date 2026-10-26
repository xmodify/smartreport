<?php

namespace Tests;

use App\Services\Hosxp\HosxpDatabase;
use Closure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeHosxpDatabase();
    }

    /**
     * Replace the HOSxP database with a fake so tests never touch a real server.
     * Without a resolver every query fails, as when the database is unreachable.
     *
     * @param  (Closure(string, array<string, mixed>): array<int, array<string, mixed>>)|null  $resolver
     */
    protected function fakeHosxpDatabase(?Closure $resolver = null): void
    {
        $this->app->instance(HosxpDatabase::class, new class($resolver) extends HosxpDatabase
        {
            public function __construct(protected ?Closure $resolver) {}

            public function select(string $sql, array $params = [], int $limit = 0): array
            {
                if ($this->resolver === null) {
                    throw new \RuntimeException('HOSxP database is unavailable in tests.');
                }

                $rows = ($this->resolver)($sql, $params);

                return $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
            }
        });
    }
}
