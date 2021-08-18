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

use EasySwoole\Database\Query\Builder;
use EasySwoole\Database\Util\Traits\Macroable;
use BadMethodCallException;
use Closure;
use EasySwoole\Database\Connection;
use EasySwoole\Database\Schema\Grammars\Grammar;
use EasySwoole\Database\Util\Fluent;


class Blueprint
{
    use Macroable;

    /**
     * 应用于表的存储引擎。
     * The storage engine that should be used for the table.
     *
     * @var string
     */
    public $engine;

    /**
     * 应用于表的默认字符集。
     * The default character set that should be used for the table.
     */
    public $charset;

    /**
     * 应用于表的排序规则。
     * The collation that should be used for the table.
     */
    public $collation;

    /**
     * 是否将该表设为临时表。
     * Whether to make the table temporary.
     *
     * @var bool
     */
    public $temporary = false;

    /**
     * 表的注释。
     * The comment of the table.
     *
     * @var string
     */
    protected $comment = '';

    /**
     * blueprint描述的表格。
     * The table the blueprint describes.
     *
     * @var string
     */
    protected $table;

    /**
     * 表的前缀。
     * The prefix of the table.
     *
     * @var string
     */
    protected $prefix;

    /**
     * 应添加到表中的列。
     * The columns that should be added to the table.
     *
     * @var \EasySwoole\Database\Schema\ColumnDefinition[]
     */
    protected $columns = [];

    /**
     * 应该为表运行的命令。
     * The commands that should be run for the table.
     *
     * @var \EasySwoole\Database\Util\Fluent[]
     */
    protected $commands = [];

    /**
     * 创建一个新的模式blueprint。
     * Create a new schema blueprint.
     *
     * @param string $table
     * @param string $prefix
     */
    public function __construct($table, Closure $callback = null, $prefix = '')
    {
        $this->table = $table;
        $this->prefix = $prefix;

        if (!is_null($callback)) {
            $callback($this);
        }
    }

    /**
     * 针对数据库执行blueprint。
     * Execute the blueprint against the database.
     */
    public function build(Connection $connection, Grammar $grammar)
    {
        foreach ($this->toSql($connection, $grammar) as $statement) {
            $connection->statement($statement);
        }
    }

    /**
     * 获取blueprint的原始SQL语句。
     * Get the raw SQL statements for the blueprint.
     *
     * @return array
     */
    public function toSql(Connection $connection, Grammar $grammar)
    {
        $this->addImpliedCommands($grammar);

        $statements = [];

        // Each type of command has a corresponding compiler function on the schema
        // grammar which is used to build the necessary SQL statements to build
        // the blueprint element, so we'll just call that compilers function.
        $this->ensureCommandsAreValid($connection);

        foreach ($this->commands as $command) {
            $method = 'compile' . ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                if (!is_null($sql = $grammar->{$method}($this, $command, $connection))) {
                    $statements = array_merge($statements, (array)$sql);
                }
            }
        }

        return $statements;
    }

    /**
     * 添加在任何列上指定的fluent命令。
     * Add the fluent commands specified on any columns.
     */
    public function addFluentCommands(Grammar $grammar)
    {
        foreach ($this->columns as $column) {
            foreach ($grammar->getFluentCommands() as $commandName) {
                $attributeName = lcfirst($commandName);

                if (!isset($column->{$attributeName})) {
                    continue;
                }

                $value = $column->{$attributeName};

                $this->addCommand(
                    $commandName,
                    compact('value', 'column')
                );
            }
        }
    }

    /**
     * 指示需要创建表。
     * Indicate that the table needs to be created.
     *
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function create()
    {
        return $this->addCommand('create');
    }

    /**
     * 设置表注释
     * Set the table comment.
     */
    public function comment(string $comment)
    {
        $this->comment = $comment;
    }

    /**
     * 指示该表需要变成临时表。
     * Indicate that the table needs to be temporary.
     */
    public function temporary()
    {
        $this->temporary = true;
    }

    /**
     * 指示应删除该表。
     * Indicate that the table should be dropped.
     *
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function drop()
    {
        return $this->addCommand('drop');
    }

    /**
     * 指示如果该表存在，则应删除该表。
     * Indicate that the table should be dropped if it exists.
     *
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function dropIfExists()
    {
        return $this->addCommand('dropIfExists');
    }

    /**
     * 指示应删除给定列。
     * Indicate that the given columns should be dropped.
     *
     * @param array|mixed $columns
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function dropColumn($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        return $this->addCommand('dropColumn', compact('columns'));
    }

    /**
     * 指示应重命名给定列。
     * Indicate that the given columns should be renamed.
     *
     * @param string $from
     * @param string $to
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function renameColumn($from, $to)
    {
        return $this->addCommand('renameColumn', compact('from', 'to'));
    }

    /**
     * 指示应删除给定的主键。
     * Indicate that the given primary key should be dropped.
     *
     * @param array|string $index
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function dropPrimary($index = null)
    {
        return $this->dropIndexCommand('dropPrimary', 'primary', $index);
    }

    /**
     * 指示应删除给定的唯一密钥。
     * Indicate that the given unique key should be dropped.
     *
     * @param array|string $index
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function dropUnique($index)
    {
        return $this->dropIndexCommand('dropUnique', 'unique', $index);
    }

    /**
     * 指示应删除给定的索引。
     * Indicate that the given index should be dropped.
     *
     * @param array|string $index
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function dropIndex($index)
    {
        return $this->dropIndexCommand('dropIndex', 'index', $index);
    }

    /**
     * 指示应删除给定的空间索引。
     * Indicate that the given spatial index should be dropped.
     *
     * @param array|string $index
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function dropSpatialIndex($index)
    {
        return $this->dropIndexCommand('dropSpatialIndex', 'spatialIndex', $index);
    }

    /**
     * 指示应删除给定的外键。
     * Indicate that the given foreign key should be dropped.
     *
     * @param array|string $index
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function dropForeign($index)
    {
        return $this->dropIndexCommand('dropForeign', 'foreign', $index);
    }

    /**
     * 指示应重命名给定的索引。
     * Indicate that the given indexes should be renamed.
     *
     * @param string $from
     * @param string $to
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function renameIndex($from, $to)
    {
        return $this->addCommand('renameIndex', compact('from', 'to'));
    }

    /**
     * 指示应删除时间戳列。
     * Indicate that the timestamp columns should be dropped.
     */
    public function dropTimestamps()
    {
        $this->dropColumn('created_at', 'updated_at');
    }

    /**
     * 指示应删除时间戳列。
     * Indicate that the timestamp columns should be dropped.
     */
    public function dropTimestampsTz()
    {
        $this->dropTimestamps();
    }

    /**
     * 指示应删除软删除列。
     * Indicate that the soft delete column should be dropped.
     *
     * @param string $column
     */
    public function dropSoftDeletes($column = 'deleted_at')
    {
        $this->dropColumn($column);
    }

    /**
     * 指示应删除软删除列。
     * Indicate that the soft delete column should be dropped.
     *
     * @param string $column
     */
    public function dropSoftDeletesTz($column = 'deleted_at')
    {
        $this->dropSoftDeletes($column);
    }

    /**
     * 指示应删除记住标记列。
     * Indicate that the remember token column should be dropped.
     */
    public function dropRememberToken()
    {
        $this->dropColumn('remember_token');
    }

    /**
     * 指示应删除polymorphic列。
     * Indicate that the polymorphic columns should be dropped.
     *
     * @param string $name
     * @param null|string $indexName
     */
    public function dropMorphs($name, $indexName = null)
    {
        $this->dropIndex($indexName ?: $this->createIndexName('index', ["{$name}_type", "{$name}_id"]));

        $this->dropColumn("{$name}_type", "{$name}_id");
    }

    /**
     * 将表重命名为给定名称。
     * Rename the table to a given name.
     *
     * @param string $to
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function rename($to)
    {
        return $this->addCommand('rename', compact('to'));
    }

    /**
     * 指定表的主键。
     * Specify the primary key(s) for the table.
     *
     * @param array|string $columns
     * @param string $name
     * @param null|string $algorithm
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function primary($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('primary', $columns, $name, $algorithm);
    }

    /**
     * 指定表的唯一索引。
     * Specify a unique index for the table.
     *
     * @param array|string $columns
     * @param string $name
     * @param null|string $algorithm
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function unique($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('unique', $columns, $name, $algorithm);
    }

    /**
     * 指定表的索引。
     * Specify an index for the table.
     *
     * @param array|string $columns
     * @param string $name
     * @param null|string $algorithm
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function index($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('index', $columns, $name, $algorithm);
    }

    /**
     * 指定表的空间索引。
     * Specify a spatial index for the table.
     *
     * @param array|string $columns
     * @param string $name
     * @return \EasySwoole\Database\Util\Fluent
     */
    public function spatialIndex($columns, $name = null)
    {
        return $this->indexCommand('spatialIndex', $columns, $name);
    }

    /**
     * 为表指定外键。
     * Specify a foreign key for the table.
     *
     * @param array|string $columns
     * @param string $name
     * @return \EasySwoole\Database\Schema\ForeignKeyDefinition|\EasySwoole\Database\Util\Fluent
     */
    public function foreign($columns, $name = null)
    {
        return $this->indexCommand('foreign', $columns, $name);
    }

    /**
     * 在表上创建一个新的自动递增整数（4字节）列。
     * Create a new auto-incrementing integer (4-byte) column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function increments($column)
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * 在表上创建一个新的自动递增整数（4字节）列。
     * Create a new auto-incrementing integer (4-byte) column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function integerIncrements($column)
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * 在表上创建一个新的自动递增小整数（1字节）列。
     * Create a new auto-incrementing tiny integer (1-byte) column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function tinyIncrements($column)
    {
        return $this->unsignedTinyInteger($column, true);
    }

    /**
     * 在表上创建一个新的自动递增小整数（2字节）列。
     * Create a new auto-incrementing small integer (2-byte) column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function smallIncrements($column)
    {
        return $this->unsignedSmallInteger($column, true);
    }

    /**
     * 在表上创建一个新的自动递增的中整数（3字节）列。
     * Create a new auto-incrementing medium integer (3-byte) column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function mediumIncrements($column)
    {
        return $this->unsignedMediumInteger($column, true);
    }

    /**
     * 在表上创建一个新的自动递增大整数（8字节）列。
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function bigIncrements($column)
    {
        return $this->unsignedBigInteger($column, true);
    }

    /**
     * 在表上创建一个新的char列。
     * Create a new char column on the table.
     *
     * @param string $column
     * @param int $length
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function char($column, $length = null)
    {
        $length = $length ?: Builder::$defaultStringLength;

        return $this->addColumn('char', $column, compact('length'));
    }

    /**
     * 在表上创建一个新的varchar列。
     * Create a new string column on the table.
     *
     * @param string $column
     * @param int $length
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function string($column, $length = null)
    {
        $length = $length ?: Builder::$defaultStringLength;

        return $this->addColumn('string', $column, compact('length'));
    }

    /**
     * 在表上创建一个新的text列。
     * Create a new text column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function text($column)
    {
        return $this->addColumn('text', $column);
    }

    /**
     * 在表上创建一个新的medium text列。
     * Create a new medium text column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function mediumText($column)
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * 在表上创建一个新的long text列。
     * Create a new long text column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function longText($column)
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * 在表上创建一个新的整数（4字节）列。
     * Create a new integer (4-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function integer($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * 在表上创建一个新的整数（1字节）tiny integer列。
     * Create a new tiny integer (1-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function tinyInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * 在表上创建一个新的（2字节）small integer列。
     * Create a new small integer (2-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function smallInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * 在表上创建一个新的（3字节）medium integer列。
     * Create a new medium integer (3-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function mediumInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * 在表上创建一个新的（8字节）big integer列。
     * Create a new big integer (8-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function bigInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * 在表上创建一个新的无符号整数（4字节）列。
     * Create a new unsigned integer (4-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function unsignedInteger($column, $autoIncrement = false)
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /**
     * 在表上创建一个新的无符号小整数（1字节）tiny integer列。
     * Create a new unsigned tiny integer (1-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function unsignedTinyInteger($column, $autoIncrement = false)
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    /**
     * 在表上创建一个新的无符号小整数（2字节）small integer列。
     * Create a new unsigned small integer (2-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function unsignedSmallInteger($column, $autoIncrement = false)
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    /**
     * 在表上创建一个新的无符号中整数（3字节）medium integer列。
     * Create a new unsigned medium integer (3-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function unsignedMediumInteger($column, $autoIncrement = false)
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    /**
     * 在表上创建一个新的无符号大整数（8字节）big integer列。
     * Create a new unsigned big integer (8-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function unsignedBigInteger($column, $autoIncrement = false)
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    /**
     * 在表上创建一个新的float列。
     * Create a new float column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function float($column, $total = 8, $places = 2)
    {
        return $this->addColumn('float', $column, compact('total', 'places'));
    }

    /**
     * 在表上创建新的double列。
     * Create a new double column on the table.
     *
     * @param string $column
     * @param null|int $total
     * @param null|int $places
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function double($column, $total = null, $places = null)
    {
        return $this->addColumn('double', $column, compact('total', 'places'));
    }

    /**
     * 在表上创建新的decimal列。
     * Create a new decimal column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function decimal($column, $total = 8, $places = 2)
    {
        return $this->addColumn('decimal', $column, compact('total', 'places'));
    }

    /**
     * 在表上创建新的无符号decimal列。
     * Create a new unsigned decimal column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function unsignedDecimal($column, $total = 8, $places = 2)
    {
        return $this->addColumn('decimal', $column, [
            'total' => $total, 'places' => $places, 'unsigned' => true,
        ]);
    }

    /**
     * 在表上创建一个新的布尔类型列。
     * Create a new boolean column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function boolean($column)
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * 在表上创建一个新的枚举类型列。
     * Create a new enum column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function enum($column, array $allowed)
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    /**
     * 在表上创建一个新的json类型列。
     * Create a new json column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function json($column)
    {
        return $this->addColumn('json', $column);
    }

    /**
     * 在表上创建一个新的jsonb类型列。
     * Create a new jsonb column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function jsonb($column)
    {
        return $this->addColumn('jsonb', $column);
    }

    /**
     * 在表上创建一个新的日期类型列。
     * Create a new date column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function date($column)
    {
        return $this->addColumn('date', $column);
    }

    /**
     * 在表上创建一个新的日期时间类型列。
     * Create a new date-time column on the table.
     *
     * @param string $column
     * @param int $precision
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function dateTime($column, $precision = 0)
    {
        return $this->addColumn('dateTime', $column, compact('precision'));
    }

    /**
     * 在表上创建一个新的日期时间类型（带时区）列。
     * Create a new date-time column (with time zone) on the table.
     *
     * @param string $column
     * @param int $precision
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function dateTimeTz($column, $precision = 0)
    {
        return $this->addColumn('dateTimeTz', $column, compact('precision'));
    }

    /**
     * 在表上创建一个新的时间类型列。
     * Create a new time column on the table.
     *
     * @param string $column
     * @param int $precision
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function time($column, $precision = 0)
    {
        return $this->addColumn('time', $column, compact('precision'));
    }

    /**
     * 在表上创建一个新的时间类型（带时区）列。
     * Create a new time column (with time zone) on the table.
     *
     * @param string $column
     * @param int $precision
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function timeTz($column, $precision = 0)
    {
        return $this->addColumn('timeTz', $column, compact('precision'));
    }

    /**
     * 在表上创建一个新的时间戳类型列。
     * Create a new timestamp column on the table.
     *
     * @param string $column
     * @param int $precision
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function timestamp($column, $precision = 0)
    {
        return $this->addColumn('timestamp', $column, compact('precision'));
    }

    /**
     * 在表上创建一个新的时间戳类型（带时区）列。
     * Create a new timestamp (with time zone) column on the table.
     *
     * @param string $column
     * @param int $precision
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function timestampTz($column, $precision = 0)
    {
        return $this->addColumn('timestampTz', $column, compact('precision'));
    }

    /**
     * 将可为空的创建和更新时间戳列添加到表中。
     * Add nullable creation and update timestamps to the table.
     *
     * @param int $precision
     */
    public function timestamps($precision = 0)
    {
        $this->timestamp('created_at', $precision)->nullable();

        $this->timestamp('updated_at', $precision)->nullable();
    }

    /**
     * 将可为空的创建和更新时间戳列添加到表中。
     * Add nullable creation and update timestamps to the table.
     *
     * Alias for self::timestamps().
     *
     * @param int $precision
     */
    public function nullableTimestamps($precision = 0)
    {
        $this->timestamps($precision);
    }

    /**
     * 将可为空的创建和更新时间戳（带时区）列添加到表中。
     * Add creation and update timestampTz columns to the table.
     *
     * @param int $precision
     */
    public function timestampsTz($precision = 0)
    {
        $this->timestampTz('created_at', $precision)->nullable();

        $this->timestampTz('updated_at', $precision)->nullable();
    }

    /**
     * 为表添加一个“deleted at”时间戳列。
     * Add a "deleted at" timestamp for the table.
     *
     * @param string $column
     * @param int $precision
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function softDeletes($column = 'deleted_at', $precision = 0)
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    /**
     * 为表添加一个“deleted at”timestamtz。
     * Add a "deleted at" timestampTz for the table.
     *
     * @param string $column
     * @param int $precision
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function softDeletesTz($column = 'deleted_at', $precision = 0)
    {
        return $this->timestampTz($column, $precision)->nullable();
    }

    /**
     * 在表上创建一个年份类型列。
     * Create a new year column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function year($column)
    {
        return $this->addColumn('year', $column);
    }

    /**
     * 在表上创建一个新的二进制类型列。
     * Create a new binary column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function binary($column)
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * 在表上创建一个新的uuid类型列。
     * Create a new uuid column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function uuid($column)
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * 在表上创建一个新的IP地址类型列。
     * Create a new IP address column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function ipAddress($column)
    {
        return $this->addColumn('ipAddress', $column);
    }

    /**
     * 在表上创建一个新的MAC地址类型列。
     * Create a new MAC address column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function macAddress($column)
    {
        return $this->addColumn('macAddress', $column);
    }

    /**
     * 在表上创建一个新的geometry类型列。
     * Create a new geometry column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function geometry($column)
    {
        return $this->addColumn('geometry', $column);
    }

    /**
     * 在表上创建一个新的point点类型列。
     * Create a new point column on the table.
     *
     * @param string $column
     * @param null|int $srid
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function point($column, $srid = null)
    {
        return $this->addColumn('point', $column, compact('srid'));
    }

    /**
     * 在表上创建一个新的linestring类型列。
     * Create a new linestring column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function lineString($column)
    {
        return $this->addColumn('linestring', $column);
    }

    /**
     * 在表上创建一个新的polygon类型列。
     * Create a new polygon column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function polygon($column)
    {
        return $this->addColumn('polygon', $column);
    }

    /**
     * 在表上创建一个新的geometrycollection类型列。
     * Create a new geometrycollection column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function geometryCollection($column)
    {
        return $this->addColumn('geometrycollection', $column);
    }

    /**
     * 在表上创建一个新的multipoint类型列。
     * Create a new multipoint column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function multiPoint($column)
    {
        return $this->addColumn('multipoint', $column);
    }

    /**
     * 在表上创建一个新的multilinestring类型列。
     * Create a new multilinestring column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function multiLineString($column)
    {
        return $this->addColumn('multilinestring', $column);
    }

    /**
     * 在表上创建一个新的multipolygon类型列。
     * Create a new multipolygon column on the table.
     *
     * @param string $column
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function multiPolygon($column)
    {
        return $this->addColumn('multipolygon', $column);
    }

    /**
     * 为polymorphic表添加适当的列。
     * Add the proper columns for a polymorphic table.
     *
     * @param string $name
     * @param null|string $indexName
     */
    public function morphs($name, $indexName = null)
    {
        $this->string("{$name}_type");

        $this->unsignedBigInteger("{$name}_id");

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * 为polymorphic表添加可为空的列。
     * Add nullable columns for a polymorphic table.
     *
     * @param string $name
     * @param null|string $indexName
     */
    public function nullableMorphs($name, $indexName = null)
    {
        $this->string("{$name}_type")->nullable();

        $this->unsignedBigInteger("{$name}_id")->nullable();

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * 将“记住令牌”类型列添加到表中。
     * Adds the `remember_token` column to the table.
     *
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function rememberToken()
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /**
     * 将新列添加到blueprint中。
     * Add a new column to the blueprint.
     *
     * @param string $type
     * @param string $name
     * @return \EasySwoole\Database\Schema\ColumnDefinition
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        $this->columns[] = $column = new ColumnDefinition(
            array_merge(compact('type', 'name'), $parameters)
        );

        return $column;
    }

    /**
     * 从blueprint模式中删除列。
     * Remove a column from the schema blueprint.
     *
     * @param string $name
     * @return $this
     */
    public function removeColumn($name)
    {
        $this->columns = array_values(array_filter($this->columns, function ($c) use ($name) {
            return $c['attributes']['name'] != $name;
        }));

        return $this;
    }

    /**
     * 获取blueprint描述的表。
     * Get the table the blueprint describes.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * 获取blueprint的注释。
     * Get the comment on the blueprint.
     *
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * 获取blueprint上的列。
     * Get the columns on the blueprint.
     *
     * @return \EasySwoole\Database\Schema\ColumnDefinition[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * 获取blueprint上的命令。
     * Get the commands on the blueprint.
     *
     * @return \EasySwoole\Database\Util\Fluent[]
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * 获取blueprint上的列。获取blueprint上应添加的列。
     * Get the columns on the blueprint that should be added.
     *
     * @return \EasySwoole\Database\Schema\ColumnDefinition[]
     */
    public function getAddedColumns()
    {
        return array_filter($this->columns, function ($column) {
            return !$column->change;
        });
    }

    /**
     * 获取blueprint上应更改的列。
     * Get the columns on the blueprint that should be changed.
     *
     * @return \EasySwoole\Database\Schema\ColumnDefinition[]
     */
    public function getChangedColumns()
    {
        return array_filter($this->columns, function ($column) {
            return (bool)$column->change;
        });
    }

    /**
     * 确保blueprint上的命令对连接类型有效。
     * Ensure the commands on the blueprint are valid for the connection type.
     *
     * @throws \BadMethodCallException
     */
    protected function ensureCommandsAreValid(Connection $connection)
    {
        if (!$connection instanceof Connection) {
            if ($this->commandsNamed(['dropColumn', 'renameColumn'])->count() > 1) {
                throw new BadMethodCallException(
                    "MySQL doesn't support multiple calls to dropColumn / renameColumn in a single modification."
                );
            }

            if ($this->commandsNamed(['dropForeign'])->count() > 0) {
                throw new BadMethodCallException(
                    "MySQL doesn't support dropping foreign keys (you would need to re-create the table)."
                );
            }
        }
    }

    /**
     * 获取与给定名称匹配的所有命令。
     * Get all of the commands matching the given names.
     *
     * @return \EasySwoole\Database\Collection
     */
    protected function commandsNamed(array $names)
    {
        return collect($this->commands)->filter(function ($command) use ($names) {
            return in_array($command->name, $names);
        });
    }

    /**
     * 添加blueprint状态暗示的命令。
     * Add the commands that are implied by the blueprint's state.
     */
    protected function addImpliedCommands(Grammar $grammar)
    {
        if (count($this->getAddedColumns()) > 0 && !$this->creating()) {
            array_unshift($this->commands, $this->createCommand('add'));
        }

        if (count($this->getChangedColumns()) > 0 && !$this->creating()) {
            array_unshift($this->commands, $this->createCommand('change'));
        }

        $this->addFluentIndexes();

        $this->addFluentCommands($grammar);
    }

    /**
     * 添加流畅地在列上指定的索引命令。
     * Add the index commands fluently specified on columns.
     */
    protected function addFluentIndexes()
    {
        foreach ($this->columns as $column) {
            foreach (['primary', 'unique', 'index', 'spatialIndex'] as $index) {
                // If the index has been specified on the given column, but is simply equal
                // to "true" (boolean), no name has been specified for this index so the
                // index method can be called without a name and it will generate one.
                if ($column->{$index} === true) {
                    $this->{$index}($column->name);

                    continue 2;
                }

                // If the index has been specified on the given column, and it has a string
                // value, we'll go ahead and call the index method and pass the name for
                // the index since the developer specified the explicit name for this.
                if (isset($column->{$index})) {
                    $this->{$index}($column->name, $column->{$index});

                    continue 2;
                }
            }
        }
    }

    /**
     * 确定blueprint是否具有“create”命令。
     * Determine if the blueprint has a create command.
     *
     * @return bool
     */
    protected function creating()
    {
        return collect($this->commands)->contains(function ($command) {
            return $command->name === 'create';
        });
    }

    /**
     * 向blueprint添加新的索引命令。
     * Add a new index command to the blueprint.
     *
     * @param string $type
     * @param array|string $columns
     * @param string $index
     * @param null|string $algorithm
     * @return \EasySwoole\Database\Util\Fluent
     */
    protected function indexCommand($type, $columns, $index, $algorithm = null)
    {
        $columns = (array) $columns;

        // If no name was specified for this index, we will create one using a basic
        // convention of the table name, followed by the columns, followed by an
        // index type, such as primary or index, which makes the index unique.
        $index = $index ?: $this->createIndexName($type, $columns);

        return $this->addCommand(
            $type,
            compact('index', 'columns', 'algorithm')
        );
    }

    /**
     * 在blueprint上创建新的放置索引命令。
     * Create a new drop index command on the blueprint.
     *
     * @param string $command
     * @param string $type
     * @param array|string $index
     * @return \EasySwoole\Database\Util\Fluent
     */
    protected function dropIndexCommand($command, $type, $index)
    {
        $columns = [];

        // If the given "index" is actually an array of columns, the developer means
        // to drop an index merely by specifying the columns involved without the
        // conventional name, so we will build the index name from the columns.
        if (is_array($index)) {
            $index = $this->createIndexName($type, $columns = $index);
        }

        return $this->indexCommand($command, $columns, $index);
    }

    /**
     * 为表创建默认索引名。
     * Create a default index name for the table.
     *
     * @param string $type
     * @return string
     */
    protected function createIndexName($type, array $columns)
    {
        $index = strtolower($this->prefix . $this->table . '_' . implode('_', $columns) . '_' . $type);

        return str_replace(['-', '.'], '_', $index);
    }

    /**
     * 将新命令添加到blueprint。
     * Add a new command to the blueprint.
     *
     * @param string $name
     * @return \EasySwoole\Database\Util\Fluent
     */
    protected function addCommand($name, array $parameters = [])
    {
        $this->commands[] = $command = $this->createCommand($name, $parameters);

        return $command;
    }

    /**
     * 创建一个新的Fluent命令。
     * Create a new Fluent command.
     *
     * @param string $name
     * @return \EasySwoole\Database\Util\Fluent
     */
    protected function createCommand($name, array $parameters = [])
    {
        return new Fluent(array_merge(compact('name'), $parameters));
    }
}
