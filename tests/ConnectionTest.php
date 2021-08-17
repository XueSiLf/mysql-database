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

namespace EasySwoole\Database\Tests;

use Swoole\Coroutine\MySQL;
use EasySwoole\Database\Connection;
use PHPUnit\Framework\TestCase;
use EasySwoole\Database\Query\Grammars\MySqlGrammar;

class ConnectionTest extends TestCase
{
    public function getConnection(): Connection
    {
        $mysql = new MySQL();
        $serverInfo = [
            'host' => '127.0.0.1',
            'user' => 'es_orm_debug',
            'password' => 'es_orm_debug',
            'database' => 'es_orm_debug',
            'charset' => 'utf8'
        ];
        $mysql->connect($serverInfo);
        $connection = new Connection($mysql);
        return $connection->setQueryGrammar(new MySqlGrammar());
    }

    public function testConnectionTable()
    {
        $connection = $this->getConnection();

        $sql = $connection->table('user')->toSql();

        $this->assertSame('select * from `user`', $sql);
    }
}
