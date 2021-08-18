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
use stdClass;

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
     * 如果该值尚未存在，将创建新集合实例。
     * Create a new collection instance if the value isn't one already.
     * @param mixed $items
     */
    public static function make($items = []): self
    {
        return new static($items);
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

    /**
     * 对每个元素运行筛选器。
     * Run a filter over each of the items.
     */
    public function filter(callable $callback = null): self
    {
        if ($callback) {
            return new static(Arr::where($this->items, $callback));
        }
        return new static(array_filter($this->items));
    }

    /**
     * 确定集合中是否存在某个元素。
     * Determine if an item exists in the collection.
     *
     * @param null|mixed $operator
     * @param null|mixed $value
     * @param mixed $key
     */
    public function contains($key, $operator = null, $value = null): bool
    {
        if (func_num_args() === 1) {
            if ($this->useAsCallable($key)) {
                $placeholder = new stdClass();
                return $this->first($key, $placeholder) !== $placeholder;
            }
            return in_array($key, $this->items);
        }
        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    /**
     * 使用给定的回调对集合进行排序。
     * Sort the collection using the given callback.
     *
     * @param callable|string $callback
     */
    public function sortBy($callback, int $options = SORT_REGULAR, bool $descending = false): self
    {
        $results = [];
        $callback = $this->valueRetriever($callback);
        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // and grab the corresponding values for the sorted keys from this array.
        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }
        $descending ? arsort($results, $options) : asort($results, $options);
        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }
        return new static($results);
    }

    /**
     * 创建未通过给定真理测试的所有元素的集合。
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param callable|mixed $callback
     */
    public function reject($callback): self
    {
        if ($this->useAsCallable($callback)) {
            return $this->filter(function ($value, $key) use ($callback) {
                return ! $callback($value, $key);
            });
        }
        return $this->filter(function ($item) use ($callback) {
            return $item != $callback;
        });
    }

    /**
     * 判断给定值是否可调用类型，但不是字符串。
     * Determine if the given value is callable, but not a string.
     * @param mixed $value
     */
    protected function useAsCallable($value): bool
    {
        return ! is_string($value) && is_callable($value);
    }

    /**
     * 获取检索回调的值。
     * Get a value retrieving callback.
     * @param mixed $value
     */
    protected function valueRetriever($value): callable
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }
        return function ($item) use ($value) {
            return data_get($item, $value);
        };
    }
}
