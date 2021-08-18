<?php
declare(strict_types=1);
/**
 * This file is part of EasySwoole.
 *
 * @link https://www.easyswoole.com
 * @document https://www.easyswoole.com
 * @contact https://www.easyswoole.com/Preface/contact.html
 * @license https://github.com/easy-swoole/easyswoole/blob/3.x/LICENSE
 */

namespace EasySwoole\Database\Schema;

use EasySwoole\Database\ConnectionInterface;
use EasySwoole\Database\Query\Builder;

/**
 * @method static bool hasTable(string $table)
 * @method static array getColumnListing(string $table)
 * @method static array getColumnTypeListing(string $table)
 * @method static void dropAllTables()
 * @method static void dropAllViews()
 * @method static array getAllTables()
 * @method static array getAllViews()
 * @method static bool hasColumn(string $table, string $column)
 * @method static bool hasColumns(string $table, array $columns)
 * @method static string getColumnType(string $table, string $column)
 * @method static void table(string $table, \Closure $callback)
 * @method static void create(string $table, \Closure $callback))
 * @method static void drop(string $table)
 * @method static void dropIfExists(string $table)
 * @method static void rename(string $from, string $to)
 * @method static bool enableForeignKeyConstraints()
 * @method static bool disableForeignKeyConstraints()
 * @method static \EasySwoole\Database\Connection getConnection()
 * @method static Builder setConnection(\EasySwoole\Database\Connection $connection)
 * @method static void blueprintResolver(\Closure $resolver)
 */
class Schema
{
    public static function __callStatic($name, $arguments)
    {
        // TODO: Implement __callStatic() method.
    }

    public function __call($name, $arguments)
    {
        return self::__callStatic($name, $arguments);
    }

    /**
     * Create a connection by ConnectionResolver.
     */
    public function connection(string $name = 'default'): ConnectionInterface
    {
        // TODO: Implement connection() method.
    }
}
