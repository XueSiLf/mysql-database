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

use EasySwoole\Database\Util\Fluent;

/**
 * @method ColumnDefinition after(string $column) Place the column "after" another column (MySQL)
 * @method ColumnDefinition always() Used as a modifier for generatedAs() (PostgreSQL)
 * @method ColumnDefinition autoIncrement() Set INTEGER columns as auto-increment (primary key)
 * @method ColumnDefinition change() Change the column
 * @method ColumnDefinition charset(string $charset) Specify a character set for the column (MySQL)
 * @method ColumnDefinition collation(string $collation) Specify a collation for the column (MySQL/SQL Server)
 * @method ColumnDefinition comment(string $comment) Add a comment to the column (MySQL)
 * @method ColumnDefinition default(mixed $value) Specify a "default" value for the column
 * @method ColumnDefinition first() Place the column "first" in the table (MySQL)
 * @method ColumnDefinition generatedAs(string $expression) Create a SQL compliant identity column (PostgreSQL)
 * @method ColumnDefinition index(string $name = null) Add an index
 * @method ColumnDefinition nullable(bool $value = true) Allow NULL values to be inserted into the column
 * @method ColumnDefinition primary() Add a primary index
 * @method ColumnDefinition spatialIndex() Add a spatial index
 * @method ColumnDefinition storedAs(string $expression) Create a stored generated column (MySQL)
 * @method ColumnDefinition unique(string $name = null) Add a unique index
 * @method ColumnDefinition unsigned() Set the INTEGER column as UNSIGNED (MySQL)
 * @method ColumnDefinition useCurrent() Set the TIMESTAMP column to use CURRENT_TIMESTAMP as default value
 * @method ColumnDefinition virtualAs(string $expression) Create a virtual generated column (MySQL)
 */
class ColumnDefinition extends Fluent
{

}
