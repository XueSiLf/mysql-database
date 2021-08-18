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
use InvalidArgumentException;
use EasySwoole\Database\Collection;

/**
 * 本文件中的大多数方法来自illuminate/support，
 * 感谢Laravel团队提供了如此有用的类。
 * Most of the methods in this file come from illuminate/support,
 * thanks Laravel Team provide such a useful class.
 */
class Arr
{
    /**
     * 判断给定值是否为可访问数组。
     * Determine whether the given value is array accessible.
     * @param mixed $value
     */
    public static function accessible($value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * 如果元素不存在，则使用“点”符号将其添加到数组中。
     * Add an element to an array using "dot" notation if it doesn't exist.
     * @param mixed $value
     */
    public static function add(array $array, string $key, $value): array
    {
        if (is_null(static::get($array, $key))) {
            static::set($array, $key, $value);
        }
        return $array;
    }

    /**
     * 将一个数组折叠为单个数组。
     * Collapse an array of arrays into a single array.
     */
    public static function collapse(array $array): array
    {
        $results = [];
        foreach ($array as $values) {
            if ($values instanceof Collection) {
                $values = $values->all();
            } elseif (! is_array($values)) {
                continue;
            }
            $results[] = $values;
        }
        return array_merge([], ...$results);
    }

    /**
     * 交叉连接给定数组，返回所有可能的排列。
     * Cross join the given arrays, returning all possible permutations.
     *
     * @param array ...$arrays
     */
    public static function crossJoin(...$arrays): array
    {
        $results = [[]];
        foreach ($arrays as $index => $array) {
            $append = [];
            foreach ($results as $product) {
                foreach ($array as $item) {
                    $product[$index] = $item;
                    $append[] = $product;
                }
            }
            $results = $append;
        }
        return $results;
    }

    /**
     * 将一个数组分成两个数组。一个带有键，另一个带有值。
     * Divide an array into two arrays. One with keys and the other with values.
     *
     * @param array $array
     * @return array
     */
    public static function divide($array)
    {
        return [array_keys($array), array_values($array)];
    }

    /**
     * 用点展平多维关联数组。
     * Flatten a multi-dimensional associative array with dots.
     */
    public static function dot(array $array, string $prepend = ''): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            if (is_array($value) && ! empty($value)) {
                $results = array_merge($results, static::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }
        return $results;
    }

    /**
     * 获取除指定的键组成的数组之外的所有给定数组。
     * Get all of the given array except for a specified array of keys.
     *
     * @param array|string $keys
     */
    public static function except(array $array, $keys): array
    {
        static::forget($array, $keys);
        return $array;
    }

    /**
     * 判断提供的数组中是否存在给定的键。
     * Determine if the given key exists in the provided array.
     *
     * @param array|\ArrayAccess $array
     * @param int|string $key
     */
    public static function exists($array, $key): bool
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }
        return array_key_exists($key, $array);
    }

    /**
     * 返回数组中通过给定真值测试的第一个元素。
     * Return the first element in an array passing a given truth test.
     *
     * @param array $array
     * @param callable|null $callback
     * @param null|mixed $default
     */
    public static function first(array $array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            if (empty($array)) {
                return value($default);
            }
            foreach ($array as $item) {
                return $item;
            }
        }
        foreach ($array as $key => $value) {
            if (call_user_func($callback, $value, $key)) {
                return $value;
            }
        }
        return value($default);
    }

    /**
     * 返回数组中通过给定真值测试的最后一个元素。
     * Return the last element in an array passing a given truth test.
     *
     * @param null|mixed $default
     */
    public static function last(array $array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($array) ? value($default) : end($array);
        }
        return static::first(array_reverse($array, true), $callback, $default);
    }

    /**
     * 将多维数组展平为单个级别。
     * Flatten a multi-dimensional array into a single level.
     * @param float|int $depth
     */
    public static function flatten(array $array, $depth = INF): array
    {
        $result = [];
        foreach ($array as $item) {
            $item = $item instanceof Collection ? $item->all() : $item;
            if (! is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, static::flatten($item, $depth - 1));
            }
        }
        return $result;
    }

    /**
     * 使用“点”符号从给定数组中删除一个或多个数组项。
     * Remove one or many array items from a given array using "dot" notation.
     *
     * @param array|string $keys
     */
    public static function forget(array &$array, $keys): void
    {
        $original = &$array;
        $keys = (array) $keys;
        if (count($keys) === 0) {
            return;
        }
        foreach ($keys as $key) {
            // if the exact key exists in the top-level, remove it
            if (static::exists($array, $key)) {
                unset($array[$key]);
                continue;
            }
            $parts = explode('.', (string) $key);
            // clean up before each pass
            $array = &$original;
            while (count($parts) > 1) {
                $part = array_shift($parts);
                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }
            unset($array[array_shift($parts)]);
        }
    }

    /**
     * 使用“点”符号从数组中获取元素。
     * Get an item from an array using "dot" notation.
     *
     * @param array|\ArrayAccess $array
     * @param null|int|string $key
     * @param mixed $default
     */
    public static function get($array, $key = null, $default = null)
    {
        if (! static::accessible($array)) {
            return value($default);
        }
        if (is_null($key)) {
            return $array;
        }
        if (static::exists($array, $key)) {
            return $array[$key];
        }
        if (! is_string($key) || strpos($key, '.') === false) {
            return $array[$key] ?? value($default);
        }
        foreach (explode('.', $key) as $segment) {
            if (static::accessible($array) && static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return value($default);
            }
        }
        return $array;
    }

    /**
     * 使用“点”符号检查数组中是否存在一个或多个元素。
     * Check if an item or items exist in an array using "dot" notation.
     *
     * @param array|\ArrayAccess $array
     * @param null|array|string $keys
     */
    public static function has($array, $keys): bool
    {
        if (is_null($keys)) {
            return false;
        }
        $keys = (array) $keys;
        if (! $array) {
            return false;
        }
        if ($keys === []) {
            return false;
        }
        foreach ($keys as $key) {
            $subKeyArray = $array;
            if (static::exists($array, $key)) {
                continue;
            }
            foreach (explode('.', $key) as $segment) {
                if (static::accessible($subKeyArray) && static::exists($subKeyArray, $segment)) {
                    $subKeyArray = $subKeyArray[$segment];
                } else {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 确定数组是否关联数组。
     * 如果数组没有从零开始的连续数字键，则该数组是“关联数组”。
     * Determines if an array is associative.
     * An array is "associative" if it doesn't have sequential numerical keys beginning with zero.
     */
    public static function isAssoc(array $array): bool
    {
        $keys = array_keys($array);
        return array_keys($keys) !== $keys;
    }

    /**
     * 从给定数组中获取元素的子集。
     * Get a subset of the items from the given array.
     *
     * @param array|string $keys
     */
    public static function only(array $array, $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    /**
     * 从数组中提取数组的值。
     * Pluck an array of values from an array.
     *
     * @param array $array
     * @param array|string $value
     * @param null|array|string $key
     * @return array
     */
    public static function pluck(array $array, $value, $key = null): array
    {
        $results = [];
        [$value, $key] = static::explodePluckParameters($value, $key);
        foreach ($array as $item) {
            $itemValue = data_get($item, $value);
            // If the key is "null", we will just append the value to the array and keep
            // looping. Otherwise we will key the array using the value of the key we
            // received from the developer. Then we'll return the final array form.
            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = data_get($item, $key);
                if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                    $itemKey = (string)$itemKey;
                }
                $results[$itemKey] = $itemValue;
            }
        }
        return $results;
    }

    /**
     * 将元素放到数组的开头。
     * Push an item onto the beginning of an array.
     * @param null|mixed $key
     * @param mixed $value
     */
    public static function prepend(array $array, $value, $key = null): array
    {
        if (is_null($key)) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }
        return $array;
    }

    /**
     * 从数组中获取一个值，并将其删除。
     * Get a value from the array, and remove it.
     *
     * @param null|mixed $default
     */
    public static function pull(array &$array, string $key, $default = null)
    {
        $value = static::get($array, $key, $default);
        static::forget($array, $key);
        return $value;
    }

    /**
     * 从数组中获取一个或指定数量的随机值。
     * Get one or a specified number of random values from an array.
     *
     * @throws \InvalidArgumentException
     */
    public static function random(array $array, int $number = null)
    {
        $requested = is_null($number) ? 1 : $number;
        $count = count($array);
        if ($requested > $count) {
            throw new InvalidArgumentException("You requested {$requested} items, but there are only {$count} items available.");
        }
        if (is_null($number)) {
            return $array[array_rand($array)];
        }
        if ((int) $number === 0) {
            return [];
        }
        $keys = array_rand($array, $number);
        $results = [];
        foreach ((array) $keys as $key) {
            $results[] = $array[$key];
        }
        return $results;
    }

    /**
     * 使用“点”符号将数组项设置为给定值。
     * 如果没有为该方法提供键，则将替换整个数组。
     *
     * Set an array item to a given value using "dot" notation.
     * If no key is given to the method, the entire array will be replaced.
     *
     * @param null|int|string $key
     * @param mixed $value
     */
    public static function set(array &$array, $key, $value): array
    {
        if (is_null($key)) {
            return $array = $value;
        }
        if (! is_string($key)) {
            $array[$key] = $value;
            return $array;
        }
        $keys = explode('.', $key);
        while (count($keys) > 1) {
            $key = array_shift($keys);
            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }
        $array[array_shift($keys)] = $value;
        return $array;
    }

    /**
     * 随机打乱给定数组并返回结果。
     * Shuffle the given array and return the result.
     */
    public static function shuffle(array $array, int $seed = null): array
    {
        if (is_null($seed)) {
            shuffle($array);
        } else {
            srand($seed);
            usort($array, function () {
                return rand(-1, 1);
            });
        }
        return $array;
    }

    /**
     * 使用给定的回调或“点”符号对数组进行排序。
     * Sort the array using the given callback or "dot" notation.
     *
     * @param null|callable|string $callback
     */
    public static function sort(array $array, $callback = null): array
    {
        return Collection::make($array)->sortBy($callback)->all();
    }

    /**
     * 按键和值对数组进行递归排序。
     * Recursively sort an array by keys and values.
     */
    public static function sortRecursive(array $array): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = static::sortRecursive($value);
            }
        }
        if (static::isAssoc($array)) {
            ksort($array);
        } else {
            sort($array);
        }
        return $array;
    }

    /**
     * 将数组转换为查询字符串。
     * Convert the array into a query string.
     */
    public static function query(array $array): string
    {
        return http_build_query($array, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * 使用给定的回调筛选数组。
     * Filter the array using the given callback.
     */
    public static function where(array $array, callable $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * 如果给定值不是数组且不为null，则将其包装为一个数组。
     * If the given value is not an array and not null, wrap it in one.
     * @param mixed $value
     */
    public static function wrap($value): array
    {
        if (is_null($value)) {
            return [];
        }
        return ! is_array($value) ? [$value] : $value;
    }

    /**
     * 使数组元素唯一。
     * Make array elements unique.
     */
    public static function unique(array $array): array
    {
        $result = [];
        foreach ($array ?? [] as $key => $item) {
            if (is_array($item)) {
                $result[$key] = self::unique($item);
            } else {
                $result[$key] = $item;
            }
        }

        if (! self::isAssoc($result)) {
            return array_unique($result);
        }

        return $result;
    }

    /**
     * 合并多个数组
     * Merge two arrays.
     *
     * @param array $array1
     * @param array $array2
     * @param bool $unique
     * @return array
     */
    public static function merge(array $array1, array $array2, bool $unique = true): array
    {
        $isAssoc = static::isAssoc($array1 ?: $array2);
        if ($isAssoc) {
            foreach ($array2 as $key => $value) {
                if (is_array($value)) {
                    $array1[$key] = static::merge($array1[$key] ?? [], $value, $unique);
                } else {
                    $array1[$key] = $value;
                }
            }
        } else {
            foreach ($array2 as $key => $value) {
                if ($unique && in_array($value, $array1, true)) {
                    continue;
                }
                $array1[] = $value;
            }

            $array1 = array_values($array1);
        }
        return $array1;
    }


    /**
     * 分解传递给“pluck”的“value”和“key”参数。
     * Explode the "value" and "key" arguments passed to "pluck".
     *
     * @param array|string $value
     * @param null|array|string $key
     * @return array
     */
    protected static function explodePluckParameters($value, $key): array
    {
        $value = is_string($value) ? explode('.', $value) : $value;
        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);
        return [$value, $key];
    }
}
