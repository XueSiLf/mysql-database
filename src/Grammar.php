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

class Grammar
{
    /**
     * 语法表前缀。
     * The grammar table prefix.
     *
     * @var string $tablePrefix
     */
    protected $tablePrefix = '';

    /**
     * 判断给出的值是否为原始表达式。
     * Determine if the given value is a raw expression.
     *
     * @param $value
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
     * Wrap a table in keyword identifiers.
     *
     * @param \EasySwoole\Database\Query\Expression|string $table
     * @return mixed
     */
    public function wrapTable($table)
    {
        if (!$this->isExpression($table)) {
            return $this->wrap($this->tablePrefix . $table, true);
        }

        return $this->getValue($table);
    }
}
