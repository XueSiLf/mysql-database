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

use InvalidArgumentException;

class JsonExpression extends Expression
{
    /**
     * 创建新的原始查询表达式。
     * Create a new raw query expression.
     * @param mixed $value
     */
    public function __construct($value)
    {
        parent::__construct(
            $this->getJsonBindingParameter($value)
        );
    }

    /**
     * 将给定值转换为正确的JSON绑定参数。
     * Translate the given value into the appropriate JSON binding parameter.
     *
     * @param mixed $value
     * @throws \InvalidArgumentException
     * @return string
     */
    protected function getJsonBindingParameter($value)
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }

        switch ($type = gettype($value)) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'NULL':
            case 'integer':
            case 'double':
            case 'string':
                return '?';
            case 'object':
            case 'array':
                return '?';
        }

        throw new InvalidArgumentException("JSON value is of illegal type: {$type}");
    }
}
