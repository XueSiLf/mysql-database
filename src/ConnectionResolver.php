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

class ConnectionResolver implements ConnectionResolverInterface
{
    /**
     * 所有已注册的连接。
     * All of the registered connections.
     *
     * @var array
     */
    protected $connections = [];

    /**
     * 默认连接名。
     * The default connection name.
     *
     * @var string
     */
    protected $default = 'default';

    /**
     * 创建新的连接解析程序实例。
     * Create a new connection resolver instance.
     */
    public function __construct(array $connections = [])
    {
        foreach ($connections as $name => $connection) {
            $this->addConnection($name, $connection);
        }
    }

    /**
     * 获取数据库连接实例。
     * Get a database connection instance.
     *
     * @param string $name
     * @return \EasySwoole\Database\ConnectionInterface
     */
    public function connection($name = null)
    {
        if (is_null($name)) {
            $name = $this->getDefaultConnection();
        }

        return $this->connections[$name];
    }

    /**
     * 将连接添加到连接解析连接实例容器中。
     * Add a connection to the resolver.
     *
     * @param string $name
     * @param \EasySwoole\Database\ConnectionInterface $connection
     */
    public function addConnection($name, ConnectionInterface $connection)
    {
        $this->connections[$name] = $connection;
    }

    /**
     * 检查一个连接是否已经被注册。
     * Check if a connection has been registered.
     *
     * @param string $name
     * @return bool
     */
    public function hasConnection($name)
    {
        return isset($this->connections[$name]);
    }

    /**
     * 获取默认的连接名称。
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection()
    {
        return $this->default;
    }

    /**
     * 设置默认的连接名称。
     * Set the default connection name.
     *
     * @param string $name
     */
    public function setDefaultConnection($name)
    {
        $this->default = $name;
    }
}
