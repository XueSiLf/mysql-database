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

use Closure;

class JoinClause extends Builder
{
    /**
     * 正在执行的连接的类型。
     * The type of join being performed.
     *
     * @var string
     */
    public $type;

    /**
     * join子句要连接到的表。
     * The table the join clause is joining to.
     *
     * @var string
     */
    public $table;

    /**
     * 父查询生成器的连接。
     * The connection of the parent query builder.
     *
     * @var \EasySwoole\Database\ConnectionInterface
     */
    protected $parentConnection;

    /**
     * 父查询生成器的语法。
     * The grammar of the parent query builder.
     *
     * @var \EasySwoole\Database\Query\Grammars\Grammar
     */
    protected $parentGrammar;

    /**
     * 父查询生成器的处理器。
     * The processor of the parent query builder.
     *
     * @var \EasySwoole\Database\Query\Processors\Processor
     */
    protected $parentProcessor;

    /**
     * 父查询生成器的类名。
     * The class name of the parent query builder.
     *
     * @var string
     */
    protected $parentClass;

    /**
     * 创建一个新的join子句实例。
     * Create a new join clause instance.
     *
     * @param string $type
     * @param string $table
     */
    public function __construct(Builder $parentQuery, $type, $table)
    {
        $this->type = $type;
        $this->table = $table;
        $this->parentClass = get_class($parentQuery);
        $this->parentGrammar = $parentQuery->getGrammar();
        $this->parentProcessor = $parentQuery->getProcessor();
        $this->parentConnection = $parentQuery->getConnection();

        parent::__construct(
            $this->parentConnection,
            $this->parentGrammar,
            $this->parentProcessor
        );
    }

    /**
     * 在连接中添加一个“on”子句。
     * Add an "on" clause to the join.
     *
     * On子句可以被连接，例如。
     * On clauses can be chained, e.g.
     *
     *  $join->on('contacts.user_id', '=', 'users.id')
     *       ->on('contacts.info_id', '=', 'info.id')
     *
     * 将产生如下SQL语句：
     * will produce the following SQL:
     *
     * on `contacts`.`user_id` = `users`.`id` and `contacts`.`info_id` = `info`.`id`
     *
     * @param \Closure|string $first
     * @param null|string $operator
     * @param null|\EasySwoole\Database\Query\Expression|string $second
     * @param string $boolean
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function on($first, $operator = null, $second = null, $boolean = 'and')
    {
        if ($first instanceof Closure) {
            return $this->whereNested($first, $boolean);
        }

        return $this->whereColumn($first, $operator, $second, $boolean);
    }

    /**
     * 在连接中添加“or on”子句。
     * Add an "or on" clause to the join.
     *
     * @param \Closure|string $first
     * @param null|string $operator
     * @param null|string $second
     * @return \EasySwoole\Database\Query\JoinClause
     */
    public function orOn($first, $operator = null, $second = null)
    {
        return $this->on($first, $operator, $second, 'or');
    }

    /**
     * 获取连接子句生成器的新实例。
     * Get a new instance of the join clause builder.
     *
     * @return \EasySwoole\Database\Query\JoinClause
     */
    public function newQuery()
    {
        return new static($this->newParentQuery(), $this->type, $this->table);
    }

    /**
     * 为子查询创建新的查询实例。
     * Create a new query instance for sub-query.
     *
     * @return \EasySwoole\Database\Query\Builder
     */
    protected function forSubQuery()
    {
        return $this->newParentQuery()->newQuery();
    }

    /**
     * 创建一个新的父查询实例。
     * Create a new parent query instance.
     *
     * @return \EasySwoole\Database\Query\Builder
     */
    protected function newParentQuery()
    {
        $class = $this->parentClass;

        return new $class($this->parentConnection, $this->parentGrammar, $this->parentProcessor);
    }
}
