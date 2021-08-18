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

namespace EasySwoole\Database;

use EasySwoole\Database\Query\Expression;
use EasySwoole\Database\Util\Traits\Macroable;

abstract class Grammar
{
    use Macroable;

    /**
     * 语法表前缀。
     * The grammar table prefix.
     *
     * @var string
     */
    protected $tablePrefix = '';

    /**
     * 包装一个数组的值。
     * Wrap an array of values.
     *
     * @return array
     */
    public function wrapArray(array $values)
    {
        return array_map([$this, 'wrap'], $values);
    }

    /**
     * 用关键字标识符包装表。
     * Wrap a table in keyword identifiers.
     *
     * @param \EasySwoole\Database\Query\Expression|string $table
     * @return string
     */
    public function wrapTable($table)
    {
        if (!$this->isExpression($table)) {
            return $this->wrap($this->tablePrefix . $table, true);
        }

        return $this->getValue($table);
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
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        // If the value being wrapped has a column alias we will need to separate out
        // the pieces so we can wrap each of the segments of the expression on its
        // own, and then join these both back together using the "as" connector.
        if (stripos($value, ' as ') !== false) {
            return $this->wrapAliasedValue($value, $prefixAlias);
        }

        return $this->wrapSegments(explode('.', $value));
    }

    /**
     * 将列名数组转换为分隔字符串。
     * Convert an array of column names into a delimited string.
     *
     * @return string
     */
    public function columnize(array $columns)
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    /**
     * 为数组创建查询参数占位符。
     * Create query parameter place-holders for an array.
     *
     * @return string
     */
    public function parameterize(array $values)
    {
        return implode(', ', array_map([$this, 'parameter'], $values));
    }

    /**
     * 获取正确的用于填充占位符的查询参数的值。
     * Get the appropriate query parameter place-holder for a value.
     *
     * @param mixed $value
     * @return string
     */
    public function parameter($value)
    {
        return $this->isExpression($value) ? $this->getValue($value) : '?';
    }

    /**
     * 对给定的字符串加上引号。
     * Quote the given string literal.
     *
     * @param array|string $value
     * @return string
     */
    public function quoteString($value)
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, __FUNCTION__], $value));
        }

        return "'{$value}'";
    }

    /**
     * 判断给出的值是否为原始表达式。
     * Determine if the given value is a raw expression.
     *
     * @param mixed $value
     * @return bool
     */
    public function isExpression($value)
    {
        return $value instanceof Expression;
    }

    /**
     * 获取原始表达式的值。
     * Get the value of a raw expression.
     *
     * @param \EasySwoole\Database\Query\Expression $expression
     * @return mixed
     */
    public function getValue($expression)
    {
        return $expression->getValue();
    }

    /**
     * 获取数据库存储日期的格式。
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * 获取语法的表前缀。
     * Get the grammar's table prefix.
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * 包装具有别名的值。
     * Wrap a value that has an alias.
     *
     * @param string $value
     * @param bool $prefixAlias
     * @return string
     */
    protected function wrapAliasedValue($value, $prefixAlias = false)
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        // If we are wrapping a table we need to prefix the alias with the table prefix
        // as well in order to generate proper syntax. If this is a column of course
        // no prefix is necessary. The condition will be true when from wrapTable.
        if ($prefixAlias) {
            $segments[1] = $this->tablePrefix . $segments[1];
        }

        return $this->wrap(
                $segments[0]
            ) . ' as ' . $this->wrapValue(
                $segments[1]
            );
    }

    /**
     * 组装给定的值段。
     * Wrap the given value segments.
     *
     * @param array $segments
     * @return string
     */
    protected function wrapSegments($segments)
    {
        return collect($segments)->map(function ($segment, $key) use ($segments) {
            return $key == 0 && count($segments) > 1
                ? $this->wrapTable($segment)
                : $this->wrapValue($segment);
        })->implode('.');
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
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }








}
