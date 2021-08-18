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

namespace EasySwoole\Database\Util;

use ArrayAccess;
use EasySwoole\Database\Interfaces\Arrayable;
use EasySwoole\Database\Interfaces\Jsonable;
use JsonSerializable;

/**
 * 本文件中的大多数方法来自illuminate/support，
 * 感谢Laravel团队提供了如此有用的类。
 * Most of the methods in this file come from illuminate/support,
 * thanks Laravel Team provide such a useful class.
 */
class Fluent implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
{
    /**
     * fluent实例上设置的所有属性。
     * All of the attributes set on the fluent instance.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * 创建一个新的fluent实例。
     * Create a new fluent instance.
     *
     * @param array|object $attributes
     */
    public function __construct($attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * 处理对fluent实例的动态调用以设置属性。
     * Handle dynamic calls to the fluent instance to set attributes.
     *
     * @param string $method
     * @param array $parameters
     * @return $this
     */
    public function __call($method, $parameters)
    {
        $this->attributes[$method] = count($parameters) > 0 ? $parameters[0] : true;

        return $this;
    }

    /**
     * 动态检索属性的值。
     * Dynamically retrieve the value of an attribute.
     *
     * @param string $key
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * 动态设置属性的值。
     * Dynamically set the value of an attribute.
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->offsetSet($key, $value);
    }

    /**
     * 动态检查是否设置了属性。
     * Dynamically check if an attribute is set.
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * 动态删除设置的属性。
     * Dynamically unset an attribute.
     *
     * @param string $key
     */
    public function __unset($key)
    {
        $this->offsetUnset($key);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * 从fluent实例获取属性。
     * Get an attribute from the fluent instance.
     *
     * @param string $key
     * @param null|mixed $default
     */
    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        return value($default);
    }

    /**
     * 获取fluent实例的所有属性。
     * Get the attributes from the fluent instance.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * 将fluent实例转换为数组。
     * Convert the fluent instance to an array.
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * 将对象转换为JSON可序列化的内容。
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * 将fluent实例转换为JSON。
     * Convert the fluent instance to JSON.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * 判断给定的索引是否存在。
     * Determine if the given offset exists.
     *
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * 获取给定索引的值。
     * Get the value for a given offset.
     *
     * @param string $offset
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * 设置给定索引的值。
     * Set the value at the given offset.
     *
     * @param string $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->attributes[$offset] = $value;
    }

    /**
     * 删除给定索引的值
     * Unset the value at the given offset.
     *
     * @param string $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }
}
