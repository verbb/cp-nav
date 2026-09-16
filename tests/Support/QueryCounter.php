<?php
namespace Tests\Support;

/** Count real query attempts while retaining Craft's normal command behaviour. */
class QueryCounter extends \craft\db\Command
{
    public static int $queries = 0;

    protected function queryInternal($method, $fetchMode = null)
    {
        self::$queries++;
        return parent::queryInternal($method, $fetchMode);
    }
}
