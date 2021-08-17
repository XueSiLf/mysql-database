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

namespace EasySwoole\Database\Query\Grammars;

use EasySwoole\Database\Query\Builder;

class MySqlGrammar extends Grammar
{
    /**
     * 组成select子句的组件。
     * The components that make up a select clause.
     *
     * @var array
     */
    protected $selectComponents = [
        'columns',
        'from',
        'wheres',
    ];

    /**
     * 编译查询的“from”部分。
     * Compile a select query into SQL.
     *
     * @param Builder $query
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        $sql = parent::compileSelect($query);

        return $sql;
    }

    /**
     * 在关键字标识符中包装单个字符串
     * Wrap a single string in keyword identifiers.
     *
     * @param string $value
     * @return string
     */
    protected function wrapValue($value)
    {
        return $value === '*' ? $value : '`' . str_replace('`', '``', $value) . '`';
    }
}
