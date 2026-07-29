<?php

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * Driver-agnostic inspection of database errors.
 *
 * Several idempotency guards in this application deliberately race for a unique
 * index and treat the resulting failure as "somebody already did this". That
 * only works if a unique violation is recognised regardless of the driver --
 * MySQL reports 1062, SQLite 19, PostgreSQL SQLSTATE 23505 -- so the check
 * lives here rather than being spelt out with a magic number at each call site.
 */
final class DatabaseErrors
{
    /** MySQL / MariaDB duplicate entry. */
    private const MYSQL_DUPLICATE = 1062;

    /** SQLite generic constraint failure; the message distinguishes which. */
    private const SQLITE_CONSTRAINT = 19;

    public static function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = $e->errorInfo[1] ?? null;

        // PostgreSQL is unambiguous.
        if ($sqlState === '23505') {
            return true;
        }

        if ($driverCode === self::MYSQL_DUPLICATE) {
            return true;
        }

        // SQLite lumps every constraint under 19, so a foreign-key failure would
        // look identical without checking the message.
        if ($driverCode === self::SQLITE_CONSTRAINT) {
            return str_contains(strtoupper($e->getMessage()), 'UNIQUE CONSTRAINT FAILED');
        }

        // Integrity violation of some kind: fall back to the message.
        if ($sqlState === '23000') {
            $message = strtoupper($e->getMessage());

            return str_contains($message, 'DUPLICATE ENTRY')
                || str_contains($message, 'UNIQUE CONSTRAINT');
        }

        return false;
    }
}
