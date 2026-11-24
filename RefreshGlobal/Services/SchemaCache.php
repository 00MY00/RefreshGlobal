<?php

namespace Modules\RefreshGlobal\Services;

/**
 * "Is this table there, with these columns?" for the features checked on every page (1.23.0). A positive answer is kept
 * in the cache for an hour (per module version): information_schema is slow on some servers. A negative answer is never
 * kept, so a migration is seen at once.
 */
class SchemaCache
{
    const KEY = 'refreshglobal.schema.';
    const MINUTES = 60;

    /** @var array|null "table:columns" => true */
    protected static $known = null;

    public static function has($table, array $columns = [])
    {
        $item = $table.':'.implode(',', $columns);
        if (self::$known === null) {
            $cached = \Cache::get(self::key());
            self::$known = is_array($cached) ? $cached : [];
        }
        if (!empty(self::$known[$item])) {
            return true;
        }
        if (!\Schema::hasTable($table) || ($columns && !\Schema::hasColumns($table, $columns))) {
            return false;
        }
        self::$known[$item] = true;
        \Cache::put(self::key(), self::$known, self::MINUTES);

        return true;
    }

    public static function forget()
    {
        self::$known = null;
        \Cache::forget(self::key());
    }

    protected static function key()
    {
        return self::KEY.Compatibility\CompatibilityChecker::moduleVersion();
    }
}
