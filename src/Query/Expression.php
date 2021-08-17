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

namespace EasySwoole\Database\Query;


class Expression
{
    /**
     * 表达式的值。
     * The value of the expression.
     *
     * @var mixed $value
     */
    protected $value;

    /**
     * 创建新的原始查询表达式。
     * Create a new raw query expression.
     *
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }


    /**
     * 获取表达式的值。
     * Get the value of the expression.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->value;
    }

    /**
     * 获取表达式的值。
     * Get the value of the expression.
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
