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

use EasySwoole\Database\Query\Builder;
use EasySwoole\Database\Query\Processors\Processor;
use Swoole\Coroutine\MySQL;
use EasySwoole\Database\Query\Grammars\Grammar as QueryGrammar;

class Connection implements ConnectionInterface
{
    /**
     * The active MySQL connection.
     *
     * @var MySQL $mysql
     */
    protected $mysql;

    /**
     * The name of the connected database.
     *
     * @var string $database
     */
    protected $database;

    /**
     * The table prefix for the connection.
     *
     * @var string $tablePrefix
     */
    protected $tablePrefix = '';

    /**
     * The database connection configuration options.
     *
     * @var array $config
     */
    protected $config = [];

    /**
     * The query grammar implementation.
     *
     * @var \EasySwoole\Database\Query\Grammars\Grammar $queryGrammar
     */
    protected $queryGrammar;

    /**
     * The query post processor implementation.
     *
     * @var \EasySwoole\Database\Query\Processors\Processor
     */
    protected $postProcessor;

    /**
     * Get the default query grammar instance.
     *
     * @return \EasySwoole\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new QueryGrammar();
    }

    /**
     * Set the query grammar to the default implementation.
     */
    public function useDefaultQueryGrammar(): void
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }

    /**
     * Get the default post processor instance.
     *
     * @return \EasySwoole\Database\Query\Processors\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new Processor();
    }

    /**
     * Set the query post processor to the default implementation.
     */
    public function useDefaultPostProcessor(): void
    {
        $this->postProcessor = $this->getDefaultPostProcessor();
    }

    /**
     * Create a new database connection instance.
     *
     * @param MySQL $mysql
     * @param string $database
     * @param string $tablePrefix
     * @param array $config
     */
    public function __construct(MySQL $mysql, string $database = '', string $tablePrefix = '', array $config = [])
    {
        $this->mysql = $mysql;

        // First we will setup the default properties. We keep track of the DB
        // name we are connected to since it is needed when some reflective
        // type commands are run such as checking whether a table exists.
        $this->database = $database;

        $this->tablePrefix = $tablePrefix;

        $this->config = $config;

        // We need to initialize a query grammar and the query post processors
        // which are both very important parts of the database abstractions
        // so we initialize these to their default values while starting.
        $this->useDefaultQueryGrammar();

        $this->useDefaultPostProcessor();
    }

    /**
     * Set the query grammar used by the connection.
     *
     * @param \EasySwoole\Database\Query\Grammars\Grammar $grammar
     * @return $this
     */
    public function setQueryGrammar(Query\Grammars\Grammar $grammar)
    {
        $this->queryGrammar = $grammar;

        return $this;
    }

    /**
     * Get the query grammar used by the connection.
     *
     * @return \EasySwoole\Database\Query\Grammars\Grammar
     */
    public function getQueryGrammar()
    {
        return $this->queryGrammar;
    }

    /**
     * Get the query post processor used by the connection.
     *
     * @return \EasySwoole\Database\Query\Processors\Processor
     */
    public function getPostProcessor()
    {
        return $this->postProcessor;
    }



    /**
     * Begin a fluent query against a database table.
     * @param string $table
     */
    public function table(string $table): Builder
    {
        return $this->query()->from($table);
    }

    /**
     * Get a new query builder instance.
     */
    public function query(): Builder
    {
        return new Builder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }
}
