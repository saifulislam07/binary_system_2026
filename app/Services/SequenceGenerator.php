<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hands out gap-free, never-reused integers from a named row in `sequences`.
 * The row is locked for the duration of the caller's transaction, so
 * concurrent callers are serialized by the database, not by PHP.
 */
class SequenceGenerator
{
    public function next(string $name): int
    {
        return DB::transaction(function () use ($name) {
            $row = DB::table('sequences')->where('name', $name)->lockForUpdate()->first();

            if ($row === null) {
                throw new RuntimeException("Sequence [{$name}] does not exist.");
            }

            $value = (int) $row->next_value;

            DB::table('sequences')->where('name', $name)->update([
                'next_value' => $value + 1,
                'updated_at' => now(),
            ]);

            return $value;
        });
    }
}
