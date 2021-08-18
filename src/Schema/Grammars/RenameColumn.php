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

namespace EasySwoole\Database\Schema\Grammars;

use Doctrine\DBAL\Schema\AbstractSchemaManager as SchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;
use EasySwoole\Database\Connection;
use EasySwoole\Database\Schema\Blueprint;
use EasySwoole\Database\Util\Fluent;

class RenameColumn
{
    /**
     * 编译重命名列命令。
     * Compile a rename column command.
     *
     * @param \EasySwoole\Database\Schema\Grammars\Grammar $grammar
     * @return array
     */
    public static function compile(Grammar $grammar, Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $column = $connection->getDoctrineColumn(
            $grammar->getTablePrefix() . $blueprint->getTable(),
            $command->from
        );

        $schema = $connection->getDoctrineSchemaManager();

        return (array)$schema->getDatabasePlatform()->getAlterTableSQL(static::getRenamedDiff(
            $grammar,
            $blueprint,
            $command,
            $column,
            $schema
        ));
    }

    /**
     * 获取具有新列名的新列实例。
     * Get a new column instance with the new column name.
     *
     * @param \EasySwoole\Database\Schema\Grammars\Grammar $grammar
     * @return \Doctrine\DBAL\Schema\TableDiff
     */
    protected static function getRenamedDiff(Grammar $grammar, Blueprint $blueprint, Fluent $command, Column $column, SchemaManager $schema)
    {
        return static::setRenamedColumns(
            $grammar->getDoctrineTableDiff($blueprint, $schema),
            $command,
            $column
        );
    }

    /**
     * 在diff表上设置重命名的列。
     * Set the renamed columns on the table diff.
     *
     * @return \Doctrine\DBAL\Schema\TableDiff
     */
    protected static function setRenamedColumns(TableDiff $tableDiff, Fluent $command, Column $column)
    {
        $tableDiff->renamedColumns = [
            $command->from => new Column($command->to, $column->getType(), self::getWritableColumnOptions($column)),
        ];
        return $tableDiff;
    }

    /**
     * 获取可写列的选项。
     * Get the writable column options.
     *
     * @return array
     */
    private static function getWritableColumnOptions(Column $column)
    {
        return array_filter($column->toArray(), function (string $name) use ($column) {
            return method_exists($column, 'set' . $name);
        }, ARRAY_FILTER_USE_KEY);
    }
}
