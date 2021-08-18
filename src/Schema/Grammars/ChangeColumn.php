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
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use EasySwoole\Database\Connection;
use EasySwoole\Database\Schema\Blueprint;
use EasySwoole\Database\Util\Fluent;
use RuntimeException;

class ChangeColumn
{
    /**
     * 将更改列命令编译为一系列SQL语句。
     * Compile a change column command into a series of SQL statements.
     *
     * @param \EasySwoole\Database\Schema\Grammars\Grammar $grammar
     * @throws \RuntimeException
     * @return array
     */
    public static function compile($grammar, Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        if (! $connection->isDoctrineAvailable()) {
            throw new RuntimeException(sprintf(
                'Changing columns for table "%s" requires Doctrine DBAL; install "doctrine/dbal".',
                $blueprint->getTable()
            ));
        }

        $tableDiff = static::getChangedDiff(
            $grammar,
            $blueprint,
            $schema = $connection->getDoctrineSchemaManager()
        );

        if ($tableDiff !== false) {
            return (array) $schema->getDatabasePlatform()->getAlterTableSQL($tableDiff);
        }

        return [];
    }

    /**
     * 获取给定更改的表的Doctrine表差异。
     * Get the Doctrine table difference for the given changes.
     *
     * @param \EasySwoole\Database\Schema\Grammars\Grammar $grammar
     * @return bool|\Doctrine\DBAL\Schema\TableDiff
     */
    protected static function getChangedDiff($grammar, Blueprint $blueprint, SchemaManager $schema)
    {
        $current = $schema->listTableDetails($grammar->getTablePrefix() . $blueprint->getTable());

        return (new Comparator())->diffTable(
            $current,
            static::getTableWithColumnChanges($blueprint, $current)
        );
    }

    /**
     * 更改列后，获取给定的Doctrine表的副本。
     * Get a copy of the given Doctrine table after making the column changes.
     *
     * @return \Doctrine\DBAL\Schema\Table
     */
    protected static function getTableWithColumnChanges(Blueprint $blueprint, Table $table)
    {
        $table = clone $table;

        foreach ($blueprint->getChangedColumns() as $fluent) {
            $column = static::getDoctrineColumn($table, $fluent);

            // Here we will spin through each fluent column definition and map it to the proper
            // Doctrine column definitions - which is necessary because Laravel and Doctrine
            // use some different terminology for various column attributes on the tables.
            foreach ($fluent->getAttributes() as $key => $value) {
                if (! is_null($option = static::mapFluentOptionToDoctrine($key))) {
                    if (method_exists($column, $method = 'set' . ucfirst($option))) {
                        $column->{$method}(static::mapFluentValueToDoctrine($option, $value));
                    }
                }
            }
        }

        return $table;
    }

    /**
     * 获取列更改的Doctrine列实例。
     * Get the Doctrine column instance for a column change.
     *
     * @return \Doctrine\DBAL\Schema\Column
     */
    protected static function getDoctrineColumn(Table $table, Fluent $fluent)
    {
        return $table->changeColumn(
            $fluent['name'],
            static::getDoctrineColumnChangeOptions($fluent)
        )->getColumn($fluent['name']);
    }

    /**
     * 获取Doctrine列更改选项。
     * Get the Doctrine column change options.
     *
     * @return array
     */
    protected static function getDoctrineColumnChangeOptions(Fluent $fluent)
    {
        $options = ['type' => static::getDoctrineColumnType($fluent['type'])];

        if (in_array($fluent['type'], ['text', 'mediumText', 'longText'])) {
            $options['length'] = static::calculateDoctrineTextLength($fluent['type']);
        }

        if ($fluent['type'] === 'json') {
            $options['customSchemaOptions'] = [
                'collation' => '',
            ];
        }

        return $options;
    }

    /**
     * 获取Doctrine列的类型。
     * Get the Doctrine column type.
     *
     * @param string $type
     * @return \Doctrine\DBAL\Types\Type
     */
    protected static function getDoctrineColumnType($type)
    {
        $type = strtolower($type);

        switch ($type) {
            case 'biginteger':
                $type = 'bigint';
                break;
            case 'smallinteger':
                $type = 'smallint';
                break;
            case 'mediumtext':
            case 'longtext':
                $type = 'text';
                break;
            case 'binary':
                $type = 'blob';
                break;
        }

        return Type::getType($type);
    }

    /**
     * 计算适当的列长度以强制输入Doctrine文本类型。
     * Calculate the proper column length to force the Doctrine text type.
     *
     * @param string $type
     * @return int
     */
    protected static function calculateDoctrineTextLength($type)
    {
        switch ($type) {
            case 'mediumText':
                return 65535 + 1;
            case 'longText':
                return 16777215 + 1;
            default:
                return 255 + 1;
        }
    }

    /**
     * 获取给定Fluent属性名称的匹配Doctrine选项。
     * Get the matching Doctrine option for a given Fluent attribute name.
     *
     * @param string $attribute
     * @return null|string
     */
    protected static function mapFluentOptionToDoctrine($attribute)
    {
        switch ($attribute) {
            case 'type':
            case 'name':
                return;
            case 'nullable':
                return 'notnull';
            case 'total':
                return 'precision';
            case 'places':
                return 'scale';
            default:
                return $attribute;
        }
    }

    /**
     * 获取给定的Fluent属性的匹配Doctrine值。
     * Get the matching Doctrine value for a given Fluent attribute.
     *
     * @param string $option
     * @param mixed $value
     */
    protected static function mapFluentValueToDoctrine($option, $value)
    {
        return $option === 'notnull' ? ! $value : $value;
    }
}
