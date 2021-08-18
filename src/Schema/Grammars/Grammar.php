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
use Doctrine\DBAL\Schema\TableDiff;
use EasySwoole\Database\Connection;
use EasySwoole\Database\Grammar as BaseGrammar;
use EasySwoole\Database\Query\Expression;
use EasySwoole\Database\Schema\Blueprint;
use EasySwoole\Database\Util\Fluent;

abstract class Grammar extends BaseGrammar
{
    /**
     * 如果此语法支持封装在事务中的架构更改。
     * If this Grammar supports schema changes wrapped in a transaction.
     *
     * @var bool
     */
    protected $transactions = false;

    /**
     * 要在create或alter命令之外执行的命令。
     * The commands to be executed outside of create or alter command.
     *
     * @var array
     */
    protected $fluentCommands = [];

    /**
     * 编译重命名列命令。
     * Compile a rename column command.
     *
     * @return array
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        return RenameColumn::compile($this, $blueprint, $command, $connection);
    }

    /**
     * 将更改列命令编译为一系列SQL语句。
     * Compile a change column command into a series of SQL statements.
     *
     * @throws \RuntimeException
     * @return array
     */
    public function compileChange(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        return ChangeColumn::compile($this, $blueprint, $command, $connection);
    }

    /**
     * 编译外键命令。
     * Compile a foreign key command.
     *
     * @return string
     */
    public function compileForeign(Blueprint $blueprint, Fluent $command)
    {
        // We need to prepare several of the elements of the foreign key definition
        // before we can create the SQL, such as wrapping the tables and convert
        // an array of columns to comma-delimited strings for the SQL queries.
        $sql = sprintf(
            'alter table %s add constraint %s ',
            $this->wrapTable($blueprint),
            $this->wrap($command->index)
        );

        // Once we have the initial portion of the SQL statement we will add on the
        // key name, table name, and referenced columns. These will complete the
        // main portion of the SQL statement and this SQL will almost be done.
        $sql .= sprintf(
            'foreign key (%s) references %s (%s)',
            $this->columnize($command->columns),
            $this->wrapTable($command->on),
            $this->columnize((array)$command->references)
        );

        // Once we have the basic foreign key creation statement constructed we can
        // build out the syntax for what should happen on an update or delete of
        // the affected columns, which will get something like "cascade", etc.
        if (!is_null($command->onDelete)) {
            $sql .= " on delete {$command->onDelete}";
        }

        if (!is_null($command->onUpdate)) {
            $sql .= " on update {$command->onUpdate}";
        }

        return $sql;
    }

    /**
     * 向数组的值中添加前缀。
     * Add a prefix to an array of values.
     *
     * @param string $prefix
     * @return array
     */
    public function prefixArray($prefix, array $values)
    {
        return array_map(function ($value) use ($prefix) {
            return $prefix . ' ' . $value;
        }, $values);
    }

    /**
     * 用关键字标识符包装表。
     * Wrap a table in keyword identifiers.
     *
     * @param mixed $table
     * @return string
     */
    public function wrapTable($table)
    {
        return parent::wrapTable(
            $table instanceof Blueprint ? $table->getTable() : $table
        );
    }

    /**
     * 将值包装在关键字标识符中。
     * Wrap a value in keyword identifiers.
     *
     * @param \EasySwoole\Database\Query\Expression|string $value
     * @param bool $prefixAlias
     * @return string
     */
    public function wrap($value, $prefixAlias = false)
    {
        return parent::wrap(
            $value instanceof Fluent ? $value->name : $value,
            $prefixAlias
        );
    }

    /**
     * 从Blueprint创建一个空的Doctrine DBAL TableDiff。
     * Create an empty Doctrine DBAL TableDiff from the Blueprint.
     *
     * @return \Doctrine\DBAL\Schema\TableDiff
     */
    public function getDoctrineTableDiff(Blueprint $blueprint, SchemaManager $schema)
    {
        $table = $this->getTablePrefix() . $blueprint->getTable();

        return tap(new TableDiff($table), function ($tableDiff) use ($schema, $table) {
            $tableDiff->fromTable = $schema->listTableDetails($table);
        });
    }

    /**
     * 获取流畅的语法命令。
     * Get the fluent commands for the grammar.
     *
     * @return array
     */
    public function getFluentCommands()
    {
        return $this->fluentCommands;
    }

    /**
     * 检查此语法是否支持事务中包装的架构更改。
     * Check if this Grammar supports schema changes wrapped in a transaction.
     *
     * @return bool
     */
    public function supportsSchemaTransactions()
    {
        return $this->transactions;
    }

    /**
     * 编译blueprint的列定义。
     * Compile the blueprint's column definitions.
     *
     * @return array
     */
    protected function getColumns(Blueprint $blueprint)
    {
        $columns = [];

        foreach ($blueprint->getAddedColumns() as $column) {
            // Each of the column types have their own compiler functions which are tasked
            // with turning the column definition into its SQL format for this platform
            // used by the connection. The column's modifiers are compiled and added.
            $sql = $this->wrap($column) . ' ' . $this->getType($column);

            $columns[] = $this->addModifiers($sql, $blueprint, $column);
        }

        return $columns;
    }

    /**
     * 获取列数据类型的SQL。
     * Get the SQL for the column data type.
     *
     * @return string
     */
    protected function getType(Fluent $column)
    {
        return $this->{'type' . ucfirst($column->type)}($column);
    }

    /**
     * 将列修饰符添加到定义中。
     * Add the column modifiers to the definition.
     *
     * @param string $sql
     * @return string
     */
    protected function addModifiers($sql, Blueprint $blueprint, Fluent $column)
    {
        foreach ($this->modifiers as $modifier) {
            if (method_exists($this, $method = "modify{$modifier}")) {
                $sql .= $this->{$method}($blueprint, $column);
            }
        }

        return $sql;
    }

    /**
     * 如果blueprint上存在主键命令，则获取该命令。
     * Get the primary key command if it exists on the blueprint.
     *
     * @param string $name
     * @return null|\EasySwoole\Database\Util\Fluent
     */
    protected function getCommandByName(Blueprint $blueprint, $name)
    {
        $commands = $this->getCommandsByName($blueprint, $name);

        if (count($commands) > 0) {
            return reset($commands);
        }
    }

    /**
     * 获取具有给定名称的所有命令。
     * Get all of the commands with a given name.
     *
     * @param string $name
     * @return array
     */
    protected function getCommandsByName(Blueprint $blueprint, $name)
    {
        return array_filter($blueprint->getCommands(), function ($value) use ($name) {
            return $value->name == $name;
        });
    }

    /**
     * 设置值的格式，以便可以在“default”子句中使用。
     * Format a value so that it can be used in "default" clauses.
     *
     * @param mixed $value
     * @return string
     */
    protected function getDefaultValue($value)
    {
        if ($value instanceof Expression) {
            return $value;
        }

        return is_bool($value)
            ? "'" . (int) $value . "'"
            : "'" . (string) $value . "'";
    }
}
