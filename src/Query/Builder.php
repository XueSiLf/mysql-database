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


use EasySwoole\Database\ConnectionInterface;
use EasySwoole\Database\Query\Grammars\Grammar;
use EasySwoole\Database\Query\Processors\Processor;

class Builder
{
    /**
     * The database connection instance.
     *
     * @var ConnectionInterface $connection
     */
    public $connection;

    /**
     * The database query grammar instance.
     *
     * @var \EasySwoole\Database\Query\Grammars\Grammar $grammar
     */
    public $grammar;

    /**
     * The database query post processor instance.
     *
     * @var \EasySwoole\Database\Query\Processors\Processor
     */
    public $processor;

    /**
     * The columns that should be returned.
     *
     * @var array
     */
    public $columns;

    /**
     * Indicates if the query returns distinct results.
     *
     * @var bool
     */
    public $distinct = false;

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public $wheres = [];

    /**
     * Create a new query builder instance.
     *
     * @param ConnectionInterface $connection
     * @param Grammar|null $grammar
     * @param Processor|null $processor
     */
    public function __construct(
        ConnectionInterface $connection,
        Grammar $grammar = null,
        Processor $processor = null
    )
    {
        $this->connection = $connection;
        $this->grammar = $grammar ?: $connection->getQueryGrammar();
        $this->processor = $processor ?: $connection->getPostProcessor();
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param string $table
     * @return $this
     */
    public function from($table)
    {
        $this->from = $table;

        return $this;
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toSql()
    {
        return $this->grammar->compileSelect($this);
    }
}
