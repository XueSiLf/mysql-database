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

use EasySwoole\Database\Interfaces\Arrayable;
use EasySwoole\Database\Interfaces\Jsonable;
use EasySwoole\Database\Util\Arr;
use JsonSerializable;
use Traversable;

class Collection
{
    /**
     * 定义集合中包含的元素。
     * The items contained in the collection.
     *
     * @var array $items
     */
    protected $items = [];

    /**
     * 获取集合中的所有元素。
     * Get all of the items in the collection.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * 从结果集合或者可被数组式访问中获取数组的元素。
     * Results array of items from Collection or Arrayable.
     *
     * @param mixed $items
     */
    protected function getArrayableItems($items): array
    {
        if (is_array($items)) {
            return $items;
        }
        if ($items instanceof self) {
            return $items->all();
        }
        if ($items instanceof Arrayable) {
            return $items->toArray();
        }
        if ($items instanceof Jsonable) {
            return json_decode($items->__toString(), true);
        }
        if ($items instanceof JsonSerializable) {
            return $items->jsonSerialize();
        }
        if ($items instanceof Traversable) {
            return iterator_to_array($items);
        }
        return (array)$items;
    }

    /**
     * 创建一个新集合。
     * Create a new collection.
     *
     * @param mixed $items
     */
    public function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /**
     * 在每个元素上运行一个map。
     * Run a map over each of the items.
     *
     * @param callable $callback
     * @return Collection
     */
    public function map(callable $callback): self
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);
        return new static(array_combine($keys, $items));
    }

    /**
     * 获取集合的第一个元素。
     * Get the first item from the collection.
     *
     * @param callable|null $callback
     * @param null|mixed $default
     * @return mixed
     */
    public function first(callable $callback = null, $default = null)
    {
        return Arr::first($this->items, $callback, $default);
    }

    /**
     * 根据给定的键key得到对应的值。
     * Get the values of a given key.
     *
     * @param array|string $value
     * @param string|null $key
     * @return Collection
     */
    public function pluck($value, ?string $key = null): self
    {
        return new static(Arr::pluck($this->items, $value, $key));
    }

    /**
     * 将给定键的值串连接为字符串。
     * Concatenate values of a given key as a string.
     *
     * @param string $value
     * @param string|null $glue
     * @return string
     */
    public function implode(string $value, string $glue = null): string
    {
        $first = $this->first();
        if (is_array($first) || is_object($first)) {
            return implode($glue, $this->pluck($value)->all());
        }
        return implode($value, $this->items);
    }
}
