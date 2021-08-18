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


interface ConnectionResolverInterface
{
    /**
     * 获取数据库连接实例。
     * Get a database connection instance.
     *
     * @param string $name
     * @return ConnectionInterface
     */
    public function connection($name = null);

    /**
     * 获取默认连接名。
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection();

    /**
     * 设置默认连接名称。
     * Set the default connection name.
     *
     * @param string $name
     */
    public function setDefaultConnection($name);
}
