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

use EasySwoole\Database\Util\Traits\Macroable;

/**
 * 本文件中的大多数方法来自illuminate/support，
 * 感谢Laravel团队提供了如此有用的类。
 * Most of the methods in this file come from illuminate/support,
 * thanks Laravel Team provide such a useful class.
 */
class Str
{
    use Macroable;

    /**
     * 确定给定字符串是否包含给定子字符串。
     * Determine if a given string contains a given substring.
     *
     * @param string $haystack
     * @param array|string $needles
     * @return bool
     */
    public static function contains($haystack, $needles)
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
