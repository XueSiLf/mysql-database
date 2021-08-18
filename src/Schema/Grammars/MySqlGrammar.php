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

use EasySwoole\Database\Connection;
use EasySwoole\Database\Schema\Blueprint;
use EasySwoole\Database\Util\Fluent;

class MySqlGrammar extends Grammar
{
    /**
     * 可能的列修饰符。
     * The possible column modifiers.
     *
     * @var array
     */
    protected $modifiers = [
        'Unsigned', 'VirtualAs', 'StoredAs', 'Charset', 'Collate', 'Nullable',
        'Default', 'Increment', 'Comment', 'After', 'First', 'Srid',
    ];

    /**
     * 可能的列序列。
     * The possible column serials.
     *
     * @var array
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * 编译查询以确定时数据库表的列表。
     * Compile the query to determine the list of tables.
     *
     * @return string
     */
    public function compileTableExists()
    {
        return 'select * from information_schema.tables where table_schema = ? and table_name = ?';
    }

    /**
     * 编译查询以确定数据表的列的列表。
     * Compile the query to determine the list of columns.
     */
    public function compileColumnListing(): string
    {
        return 'select `column_key` as `column_key`, `column_name` as `column_name`, `data_type` as `data_type`, `column_comment` as `column_comment`, `extra` as `extra`, `column_type` as `column_type` from information_schema.columns where `table_schema` = ? and `table_name` = ? order by ORDINAL_POSITION';
    }

    /**
     * 编译查询以确定数据表的列的列表。
     * Compile the query to determine the list of columns.
     */
    public function compileColumns(): string
    {
        return 'select `table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `column_comment` from information_schema.columns where `table_schema` = ? order by ORDINAL_POSITION';
    }

    /**
     * 编译一个create table命令。
     * Compile a create table command.
     *
     * @return string
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $sql = $this->compileCreateTable(
            $blueprint,
            $command,
            $connection
        );

        // Once we have the primary SQL, we can add the encoding option to the SQL for
        // the table.  Then, we can check if a storage engine has been supplied for
        // the table. If so, we will add the engine declaration to the SQL query.
        $sql = $this->compileCreateEncoding(
            $sql,
            $connection,
            $blueprint
        );

        // we will append the engine configuration onto this SQL statement as
        // the final thing we do before returning this finished SQL. Once this gets
        // added the query will be ready to execute against the real connections.
        $sql = $this->compileCreateEngine(
            $sql,
            $connection,
            $blueprint
        );

        // Finally we will append table comment.
        return $this->compileCreateComment(
            $sql,
            $connection,
            $blueprint
        );
    }

    /**
     * 编译add column命令。
     * Compile an add column command.
     *
     * @return string
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->prefixArray('add', $this->getColumns($blueprint));

        return 'alter table ' . $this->wrapTable($blueprint) . ' ' . implode(', ', $columns);
    }

    /**
     * 编译primary key命令。
     * Compile a primary key command.
     *
     * @return string
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command)
    {
        $command->name(null);

        return $this->compileKey($blueprint, $command, 'primary key');
    }

    /**
     * 编译一个unique key命令。
     * Compile a unique key command.
     *
     * @return string
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileKey($blueprint, $command, 'unique');
    }

    /**
     * 编译一个普通索引键命令
     * Compile a plain index key command.
     *
     * @return string
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileKey($blueprint, $command, 'index');
    }

    /**
     * 编译一个空间索引键命令。
     * Compile a spatial index key command.
     *
     * @return string
     */
    public function compileSpatialIndex(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileKey($blueprint, $command, 'spatial index');
    }

    /**
     * 编译一个drop table命令。
     * Compile a drop table command.
     *
     * @return string
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table ' . $this->wrapTable($blueprint);
    }

    /**
     * 编译一个drop table (if exists)命令。
     * Compile a drop table (if exists) command.
     *
     * @return string
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table if exists ' . $this->wrapTable($blueprint);
    }

    /**
     * 编译一个drop column命令。
     * Compile a drop column command.
     *
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->prefixArray('drop', $this->wrapArray($command->columns));

        return 'alter table ' . $this->wrapTable($blueprint) . ' ' . implode(', ', $columns);
    }

    /**
     * 编译一个drop primary key命令。
     * Compile a drop primary key command.
     *
     * @return string
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
    {
        return 'alter table ' . $this->wrapTable($blueprint) . ' drop primary key';
    }

    /**
     * 编译一个drop unique key命令。
     * Compile a drop unique key command.
     *
     * @return string
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop index {$index}";
    }

    /**
     * 编译一个drop索引命令。
     * Compile a drop index command.
     *
     * @return string
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop index {$index}";
    }

    /**
     * 编译一个drop空间索引命令。
     * Compile a drop spatial index command.
     *
     * @return string
     */
    public function compileDropSpatialIndex(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileDropIndex($blueprint, $command);
    }

    /**
     * 编译一个drop外键命令。
     * Compile a drop foreign key command.
     *
     * @return string
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop foreign key {$index}";
    }

    /**
     * 编译一个重命名数据表命令。
     * Compile a rename table command.
     *
     * @return string
     */
    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        $from = $this->wrapTable($blueprint);

        return "rename table {$from} to " . $this->wrapTable($command->to);
    }

    /**
     * 编译一个重命名索引命令。
     * Compile a rename index command.
     *
     * @return string
     */
    public function compileRenameIndex(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'alter table %s rename index %s to %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->from),
            $this->wrap($command->to)
        );
    }

    /**
     * 编译删除所有表所需的SQL。
     * Compile the SQL needed to drop all tables.
     *
     * @param array $tables
     * @return string
     */
    public function compileDropAllTables($tables)
    {
        return 'drop table ' . implode(',', $this->wrapArray($tables));
    }

    /**
     * 编译删除所有视图所需的SQL。
     * Compile the SQL needed to drop all views.
     *
     * @param array $views
     * @return string
     */
    public function compileDropAllViews($views)
    {
        return 'drop view ' . implode(',', $this->wrapArray($views));
    }

    /**
     * 编译检索所有表名所需的SQL。
     * Compile the SQL needed to retrieve all table names.
     *
     * @return string
     */
    public function compileGetAllTables()
    {
        return 'SHOW FULL TABLES WHERE table_type = \'BASE TABLE\'';
    }

    /**
     * 编译检索所有视图名称所需的SQL。
     * Compile the SQL needed to retrieve all view names.
     *
     * @return string
     */
    public function compileGetAllViews()
    {
        return 'SHOW FULL TABLES WHERE table_type = \'VIEW\'';
    }

    /**
     * 编译命令以启用外键约束。
     * Compile the command to enable foreign key constraints.
     *
     * @return string
     */
    public function compileEnableForeignKeyConstraints()
    {
        return 'SET FOREIGN_KEY_CHECKS=1;';
    }

    /**
     * 编译命令以禁用外键约束。
     * Compile the command to disable foreign key constraints.
     *
     * @return string
     */
    public function compileDisableForeignKeyConstraints()
    {
        return 'SET FOREIGN_KEY_CHECKS=0;';
    }

    /**
     * 为空间Geometry类型创建列定义。
     * Create the column definition for a spatial Geometry type.
     *
     * @return string
     */
    public function typeGeometry(Fluent $column)
    {
        return 'geometry';
    }

    /**
     * 为空间点类型创建列定义。
     * Create the column definition for a spatial Point type.
     *
     * @return string
     */
    public function typePoint(Fluent $column)
    {
        return 'point';
    }

    /**
     * 为空间LineString类型创建列定义。
     * Create the column definition for a spatial LineString type.
     *
     * @return string
     */
    public function typeLineString(Fluent $column)
    {
        return 'linestring';
    }

    /**
     * 为空间多边形类型创建列定义。
     * Create the column definition for a spatial Polygon type.
     *
     * @return string
     */
    public function typePolygon(Fluent $column)
    {
        return 'polygon';
    }

    /**
     * 为空间GeometryCollection类型创建列定义。
     * Create the column definition for a spatial GeometryCollection type.
     *
     * @return string
     */
    public function typeGeometryCollection(Fluent $column)
    {
        return 'geometrycollection';
    }

    /**
     * 为空间多点类型创建列定义。
     * Create the column definition for a spatial MultiPoint type.
     *
     * @return string
     */
    public function typeMultiPoint(Fluent $column)
    {
        return 'multipoint';
    }

    /**
     * 为空间多重线类型创建列定义。
     * Create the column definition for a spatial MultiLineString type.
     *
     * @return string
     */
    public function typeMultiLineString(Fluent $column)
    {
        return 'multilinestring';
    }

    /**
     * 为空间多边形类型创建列定义。
     * Create the column definition for a spatial MultiPolygon type.
     *
     * @return string
     */
    public function typeMultiPolygon(Fluent $column)
    {
        return 'multipolygon';
    }

    /**
     * 创建主要的CREATE TABLE子句。
     * Create the main create table clause.
     *
     * @param \EasySwoole\Database\Schema\Blueprint $blueprint
     * @param \EasySwoole\Database\Util\Fluent $command
     * @param \EasySwoole\Database\Connection $connection
     * @return string
     */
    protected function compileCreateTable($blueprint, $command, $connection)
    {
        return sprintf(
            '%s table %s (%s)',
            $blueprint->temporary ? 'create temporary' : 'create',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint))
        );
    }

    /**
     * 将字符集规范附加到命令。
     * Append the character set specifications to a command.
     *
     * @param string $sql
     * @return string
     */
    protected function compileCreateEncoding($sql, Connection $connection, Blueprint $blueprint)
    {
        // First we will set the character set if one has been set on either the create
        // blueprint itself or on the root configuration for the connection that the
        // table is being created on. We will add these to the create table query.
        if (isset($blueprint->charset)) {
            $sql .= ' default character set ' . $blueprint->charset;
        } elseif (! is_null($charset = $connection->getConfig('charset'))) {
            $sql .= ' default character set ' . $charset;
        }

        // Next we will add the collation to the create table statement if one has been
        // added to either this create table blueprint or the configuration for this
        // connection that the query is targeting. We'll add it to this SQL query.
        if (isset($blueprint->collation)) {
            $sql .= " collate '{$blueprint->collation}'";
        } elseif (! is_null($collation = $connection->getConfig('collation'))) {
            $sql .= " collate '{$collation}'";
        }

        return $sql;
    }

    /**
     * 将数据库存储规格附加到命令。
     * Append the engine specifications to a command.
     *
     * @param string $sql
     * @return string
     */
    protected function compileCreateEngine($sql, Connection $connection, Blueprint $blueprint)
    {
        if (isset($blueprint->engine)) {
            return $sql . ' engine = ' . $blueprint->engine;
        }
        if (! is_null($engine = $connection->getConfig('engine'))) {
            return $sql . ' engine = ' . $engine;
        }

        return $sql;
    }

    /**
     * 将注释附加到命令。
     * Append the comment to a command.
     *
     * @param string $sql
     * @return string
     */
    protected function compileCreateComment($sql, Connection $connection, Blueprint $blueprint)
    {
        if ($comment = $blueprint->getComment()) {
            return $sql . ' COMMENT = \'' . $comment . '\'';
        }

        return $sql;
    }

    /**
     * 编译索引创建命令。
     * Compile an index creation command.
     *
     * @param string $type
     * @return string
     */
    protected function compileKey(Blueprint $blueprint, Fluent $command, $type)
    {
        return sprintf(
            'alter table %s add %s %s%s(%s)',
            $this->wrapTable($blueprint),
            $type,
            $this->wrap($command->index),
            $command->algorithm ? ' using ' . $command->algorithm : '',
            $this->columnize($command->columns)
        );
    }

    /**
     * 创建char类型的列定义。
     * Create the column definition for a char type.
     *
     * @return string
     */
    protected function typeChar(Fluent $column)
    {
        return "char({$column->length})";
    }

    /**
     * 为varchar类型创建列定义。
     * Create the column definition for a string type.
     *
     * @return string
     */
    protected function typeString(Fluent $column)
    {
        return "varchar({$column->length})";
    }

    /**
     * 为text类型创建列定义。
     * Create the column definition for a text type.
     *
     * @return string
     */
    protected function typeText(Fluent $column)
    {
        return 'text';
    }

    /**
     * 为mediumtext类型创建列定义。
     * Create the column definition for a medium text type.
     *
     * @return string
     */
    protected function typeMediumText(Fluent $column)
    {
        return 'mediumtext';
    }

    /**
     * 为longtext类型创建列定义。
     * Create the column definition for a long text type.
     *
     * @return string
     */
    protected function typeLongText(Fluent $column)
    {
        return 'longtext';
    }

    /**
     * 为大整数类型创建列定义。
     * Create the column definition for a big integer type.
     *
     * @return string
     */
    protected function typeBigInteger(Fluent $column)
    {
        return 'bigint';
    }

    /**
     * 为整数类型创建列定义。
     * Create the column definition for an integer type.
     *
     * @return string
     */
    protected function typeInteger(Fluent $column)
    {
        return 'int';
    }

    /**
     * 为mediumint类型创建列定义。
     * Create the column definition for a medium integer type.
     *
     * @return string
     */
    protected function typeMediumInteger(Fluent $column)
    {
        return 'mediumint';
    }

    /**
     * 为tinyint类型创建列定义。
     * Create the column definition for a tiny integer type.
     *
     * @return string
     */
    protected function typeTinyInteger(Fluent $column)
    {
        return 'tinyint';
    }

    /**
     * 为smallint类型创建列定义。
     * Create the column definition for a small integer type.
     *
     * @return string
     */
    protected function typeSmallInteger(Fluent $column)
    {
        return 'smallint';
    }

    /**
     * 为浮点float类型创建列定义。
     * Create the column definition for a float type.
     *
     * @return string
     */
    protected function typeFloat(Fluent $column)
    {
        return $this->typeDouble($column);
    }

    /**
     * 为浮点double类型创建列定义。
     * Create the column definition for a double type.
     *
     * @return string
     */
    protected function typeDouble(Fluent $column)
    {
        if ($column->total && $column->places) {
            return "double({$column->total}, {$column->places})";
        }

        return 'double';
    }

    /**
     * 为decimal类型创建列定义。
     * Create the column definition for a decimal type.
     *
     * @return string
     */
    protected function typeDecimal(Fluent $column)
    {
        return "decimal({$column->total}, {$column->places})";
    }

    /**
     * 为布尔类型创建列定义。
     * Create the column definition for a boolean type.
     *
     * @return string
     */
    protected function typeBoolean(Fluent $column)
    {
        return 'tinyint(1)';
    }

    /**
     * 为枚举类型创建列定义。
     * Create the column definition for an enumeration type.
     *
     * @return string
     */
    protected function typeEnum(Fluent $column)
    {
        return sprintf('enum(%s)', $this->quoteString($column->allowed));
    }

    /**
     * 为json类型创建列定义。
     * Create the column definition for a json type.
     *
     * @return string
     */
    protected function typeJson(Fluent $column)
    {
        return 'json';
    }

    /**
     * 为jsonb类型创建列定义。
     * Create the column definition for a jsonb type.
     *
     * @return string
     */
    protected function typeJsonb(Fluent $column)
    {
        return 'json';
    }

    /**
     * 为日期类型创建列定义。
     * Create the column definition for a date type.
     *
     * @return string
     */
    protected function typeDate(Fluent $column)
    {
        return 'date';
    }

    /**
     * 为日期时间类型创建列定义。
     * Create the column definition for a date-time type.
     *
     * @return string
     */
    protected function typeDateTime(Fluent $column)
    {
        $columnType = $column->precision ? "datetime({$column->precision})" : 'datetime';

        return $column->useCurrent ? "{$columnType} default CURRENT_TIMESTAMP" : $columnType;
    }

    /**
     * 为日期时间（带时区）类型创建列定义。
     * Create the column definition for a date-time (with time zone) type.
     *
     * @return string
     */
    protected function typeDateTimeTz(Fluent $column)
    {
        return $this->typeDateTime($column);
    }

    /**
     * 为时间类型创建列定义。
     * Create the column definition for a time type.
     *
     * @return string
     */
    protected function typeTime(Fluent $column)
    {
        return $column->precision ? "time({$column->precision})" : 'time';
    }

    /**
     * 为时间（带时区）类型创建列定义。
     * Create the column definition for a time (with time zone) type.
     *
     * @return string
     */
    protected function typeTimeTz(Fluent $column)
    {
        return $this->typeTime($column);
    }

    /**
     * 为时间戳类型创建列定义。
     * Create the column definition for a timestamp type.
     *
     * @return string
     */
    protected function typeTimestamp(Fluent $column)
    {
        $columnType = $column->precision ? "timestamp({$column->precision})" : 'timestamp';

        return $column->useCurrent ? "{$columnType} default CURRENT_TIMESTAMP" : $columnType;
    }

    /**
     * 为时间戳（带时区）类型创建列定义。
     * Create the column definition for a timestamp (with time zone) type.
     *
     * @return string
     */
    protected function typeTimestampTz(Fluent $column)
    {
        return $this->typeTimestamp($column);
    }

    /**
     * 为年份类型创建列定义。
     * Create the column definition for a year type.
     *
     * @return string
     */
    protected function typeYear(Fluent $column)
    {
        return 'year';
    }

    /**
     * 为二进制类型创建列定义。
     * Create the column definition for a binary type.
     *
     * @return string
     */
    protected function typeBinary(Fluent $column)
    {
        return 'blob';
    }

    /**
     * 为uuid类型创建列定义。
     * Create the column definition for a uuid type.
     *
     * @return string
     */
    protected function typeUuid(Fluent $column)
    {
        return 'char(36)';
    }

    /**
     * 创建IP地址类型的列定义。
     * Create the column definition for an IP address type.
     *
     * @return string
     */
    protected function typeIpAddress(Fluent $column)
    {
        return 'varchar(45)';
    }

    /**
     * 为MAC地址类型创建列定义。
     * Create the column definition for a MAC address type.
     *
     * @return string
     */
    protected function typeMacAddress(Fluent $column)
    {
        return 'varchar(17)';
    }

    /**
     * 获取生成的虚拟列修饰符的SQL。
     * Get the SQL for a generated virtual column modifier.
     *
     * @return null|string
     */
    protected function modifyVirtualAs(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->virtualAs)) {
            return " as ({$column->virtualAs})";
        }
    }

    /**
     * 获取生成的存储列修饰符的SQL。
     * Get the SQL for a generated stored column modifier.
     *
     * @return null|string
     */
    protected function modifyStoredAs(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->storedAs)) {
            return " as ({$column->storedAs}) stored";
        }
    }

    /**
     * 获取无符号列修饰符的SQL。
     * Get the SQL for an unsigned column modifier.
     *
     * @return null|string
     */
    protected function modifyUnsigned(Blueprint $blueprint, Fluent $column)
    {
        if ($column->unsigned) {
            return ' unsigned';
        }
    }

    /**
     * 获取字符集列修饰符的SQL。
     * Get the SQL for a character set column modifier.
     *
     * @return null|string
     */
    protected function modifyCharset(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->charset)) {
            return ' character set ' . $column->charset;
        }
    }

    /**
     * 获取排序规则列修饰符的SQL。
     * Get the SQL for a collation column modifier.
     *
     * @return null|string
     */
    protected function modifyCollate(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->collation)) {
            return " collate '{$column->collation}'";
        }
    }

    /**
     * 获取可为空的列修饰符的SQL。
     * Get the SQL for a nullable column modifier.
     *
     * @return null|string
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        if (is_null($column->virtualAs) && is_null($column->storedAs)) {
            return $column->nullable ? ' null' : ' not null';
        }
    }

    /**
     * 获取默认列修饰符的SQL。
     * Get the SQL for a default column modifier.
     *
     * @return null|string
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->default)) {
            return ' default ' . $this->getDefaultValue($column->default);
        }
    }

    /**
     * 获取自动递增列修饰符的SQL。
     * Get the SQL for an auto-increment column modifier.
     *
     * @return null|string
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' auto_increment primary key';
        }
    }

    /**
     * 获取“第一”列修饰符的SQL。
     * Get the SQL for a "first" column modifier.
     *
     * @return null|string
     */
    protected function modifyFirst(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->first)) {
            return ' first';
        }
    }

    /**
     * 获取“after”列修饰符的SQL。
     * Get the SQL for an "after" column modifier.
     *
     * @return null|string
     */
    protected function modifyAfter(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->after)) {
            return ' after ' . $this->wrap($column->after);
        }
    }

    /**
     * 获取“注释”列修饰符的SQL。
     * Get the SQL for a "comment" column modifier.
     *
     * @return null|string
     */
    protected function modifyComment(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->comment)) {
            return " comment '" . addslashes($column->comment) . "'";
        }
    }

    /**
     * 获取SRID列修饰符的SQL。
     * Get the SQL for a SRID column modifier.
     *
     * @return null|string
     */
    protected function modifySrid(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->srid) && is_int($column->srid) && $column->srid > 0) {
            return ' srid ' . $column->srid;
        }
    }

    /**
     * 在关键字标识符中包装单个字符串。
     * Wrap a single string in keyword identifiers.
     *
     * @param string $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value !== '*') {
            return '`' . str_replace('`', '``', $value) . '`';
        }

        return $value;
    }
}
