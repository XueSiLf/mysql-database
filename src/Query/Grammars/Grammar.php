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

namespace EasySwoole\Database\Query\Grammars;

use EasySwoole\Database\Grammar as BaseGrammar;
use EasySwoole\Database\Query\Builder;
use EasySwoole\Database\Query\JoinClause;
use EasySwoole\Database\Util\Arr;
use EasySwoole\Database\Util\Str;
use RuntimeException;

class Grammar extends BaseGrammar
{
    /**
     * 语法特定运算符。
     * The grammar specific operators.
     *
     * @var array
     */
    protected $operators = [];

    /**
     * 组成select子句的组件。
     * The components that make up a select clause.
     *
     * @var array
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'unions',
        'lock',
    ];

    /**
     * 将select查询编译为SQL。
     * Compile a select query into SQL.
     *
     * @param Builder $query
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        if ($query->unions && $query->aggregate) {
            return $this->compileUnionAggregate($query);
        }

        // If the query does not have any columns set, we'll set the columns to the
        // * character to just get all of the columns from the database. Then we
        // can build the query and concatenate all the pieces together as one.
        $original = $query->columns;

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        // To compile the query, we'll spin through each component of the query and
        // see if that component exists. If it does we'll just call the compiler
        // function for the component which is responsible for making the SQL.
        $sql = trim(
            $this->concatenate(
                $this->compileComponents($query)
            )
        );

        $query->columns = $original;

        return $sql;
    }

    /**
     * 为“JSON contains”语句准备绑定。
     * Prepare the binding for a "JSON contains" statement.
     *
     * @param mixed $binding
     * @return string
     */
    public function prepareBindingForJsonContains($binding)
    {
        return json_encode($binding);
    }

    /**
     * 将随机语句编译成SQL。
     * Compile the random statement into SQL.
     *
     * @param string $seed
     * @return string
     */
    public function compileRandom($seed)
    {
        return 'RANDOM()';
    }

    /**
     * 将insert ignore语句编译到SQL中。
     * Compile an insert ignore statement into SQL.
     */
    public function compileInsertOrIgnore(Builder $query, array $values)
    {
        throw new RuntimeException('This database engine does not support insert or ignore.');
    }

    /**
     * 将exists语句编译成SQL。
     * Compile an exists statement into SQL.
     *
     * @return string
     */
    public function compileExists(Builder $query)
    {
        $select = $this->compileSelect($query);

        return "select exists({$select}) as {$this->wrap('exists')}";
    }

    /**
     * 将insert语句编译到SQL中。
     * Compile an insert statement into SQL.
     *
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $table = $this->wrapTable($query->from);

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));

        // We need to build a list of parameter place-holders of values that are bound
        // to the query. Each insert should have the exact same amount of parameter
        // bindings so we will loop through the record and parameterize them all.
        $parameters = collect($values)->map(function ($record) {
            return '(' . $this->parameterize($record) . ')';
        })->implode(', ');

        return "insert into {$table} ({$columns}) values {$parameters}";
    }

    /**
     * 将insert和get ID语句编译成SQL。
     * Compile an insert and get ID statement into SQL.
     *
     * @param array $values
     * @param string $sequence
     * @return string
     */
    public function compileInsertGetId(Builder $query, $values, $sequence)
    {
        return $this->compileInsert($query, $values);
    }

    /**
     * 使用子查询将insert语句编译为SQL。
     * Compile an insert statement using a subquery into SQL.
     *
     * @return string
     */
    public function compileInsertUsing(Builder $query, array $columns, string $sql)
    {
        return "insert into {$this->wrapTable($query->from)} ({$this->columnize($columns)}) {$sql}";
    }

    /**
     * 将update语句编译成SQL。
     * Compile an update statement into SQL.
     *
     * @param array $values
     * @return string
     */
    public function compileUpdate(Builder $query, $values)
    {
        $table = $this->wrapTable($query->from);

        // Each one of the columns in the update statements needs to be wrapped in the
        // keyword identifiers, also a place-holder needs to be created for each of
        // the values in the list of bindings so we can make the sets statements.
        $columns = collect($values)->map(function ($value, $key) {
            return $this->wrap($key) . ' = ' . $this->parameter($value);
        })->implode(', ');

        // If the query has any "join" clauses, we will setup the joins on the builder
        // and compile them so we can attach them to this update, as update queries
        // can get join statements to attach to other tables when they're needed.
        $joins = '';

        if (isset($query->joins)) {
            $joins = ' ' . $this->compileJoins($query, $query->joins);
        }

        // Of course, update queries may also be constrained by where clauses so we'll
        // need to compile the where clauses and attach it to the query so only the
        // intended records are updated by the SQL statements we generate to run.
        $wheres = $this->compileWheres($query);

        return trim("update {$table}{$joins} set {$columns} {$wheres}");
    }

    /**
     * 为update语句准备绑定。
     * Prepare the bindings for an update statement.
     *
     * @return array
     */
    public function prepareBindingsForUpdate(array $bindings, array $values)
    {
        $cleanBindings = Arr::except($bindings, ['join', 'select']);

        return array_values(
            array_merge($bindings['join'], $values, Arr::flatten($cleanBindings))
        );
    }

    /**
     * 将delete语句编译成SQL。
     * Compile a delete statement into SQL.
     *
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        $wheres = is_array($query->wheres) ? $this->compileWheres($query) : '';

        return trim("delete from {$this->wrapTable($query->from)} {$wheres}");
    }

    /**
     * 为delete语句准备绑定。
     * Prepare the bindings for a delete statement.
     *
     * @return array
     */
    public function prepareBindingsForDelete(array $bindings)
    {
        return Arr::flatten($bindings);
    }

    /**
     * 将truncate table语句编译为SQL。
     * Compile a truncate table statement into SQL.
     *
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        return ['truncate ' . $this->wrapTable($query->from) => []];
    }

    /**
     * 确定语法是否支持保存点。
     * Determine if the grammar supports savepoints.
     *
     * @return bool
     */
    public function supportsSavepoints()
    {
        return true;
    }

    /**
     * 编译SQL语句以定义保存点。
     * Compile the SQL statement to define a savepoint.
     *
     * @param string $name
     * @return string
     */
    public function compileSavepoint($name)
    {
        return 'SAVEPOINT ' . $name;
    }

    /**
     * 编译SQL语句以执行保存点回滚。
     * Compile the SQL statement to execute a savepoint rollback.
     *
     * @param string $name
     * @return string
     */
    public function compileSavepointRollBack($name)
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }

    /**
     * 将值包装在关键字标识符中。
     * Wrap a value in keyword identifiers.
     *
     * @param \EasySwoole\Database\Query\Expression|string $value
     * @param bool $prefixAlias
     * @return string
     */
    public function wrap($value, $prefixAlias = false)
    {
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        // If the value being wrapped has a column alias we will need to separate out
        // the pieces so we can wrap each of the segments of the expression on its
        // own, and then join these both back together using the "as" connector.
        if (stripos($value, ' as ') !== false) {
            return $this->wrapAliasedValue($value, $prefixAlias);
        }

        // If the given value is a JSON selector we will wrap it differently than a
        // traditional value. We will need to split this path and wrap each part
        // wrapped, etc. Otherwise, we will simply wrap the value as a string.
        if ($this->isJsonSelector($value)) {
            return $this->wrapJsonSelector($value);
        }

        return $this->wrapSegments(explode('.', $value));
    }

    /**
     * 获取语法特定的运算符。
     * Get the grammar specific operators.
     *
     * @return array
     */
    public function getOperators()
    {
        return $this->operators;
    }

    /**
     * 编译select子句所需的组件。
     * Compile the components necessary for a select clause.
     *
     * @param Builder $query
     * @return array
     */
    protected function compileComponents(Builder $query)
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            // To compile the query, we'll spin through each component of the query and
            // see if that component exists. If it does we'll just call the compiler
            // function for the component which is responsible for making the SQL.
            if (isset($query->{$component}) && !is_null($query->{$component})) {
                $method = 'compile' . ucfirst($component);

                $sql[$component] = $this->{$method}($query, $query->{$component});
            }
        }

        return $sql;
    }

    /**
     * 编译一个聚合的select子句。
     * Compile an aggregated select clause.
     *
     * @param array $aggregate
     * @return string
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        $column = $this->columnize($aggregate['columns']);

        // If the query has a "distinct" constraint and we're not asking for all columns
        // we need to prepend "distinct" onto the column name so that the query takes
        // it into account when it performs the aggregating operations on the data.
        if ($query->distinct && $column !== '*') {
            $column = 'distinct ' . $column;
        }

        return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
    }

    /**
     * 编译查询的“select*”部分。
     * Compile the "select *" portion of the query.
     *
     * @param array $columns
     * @return null|string
     */
    protected function compileColumns(Builder $query, $columns)
    {
        // If the query is actually performing an aggregating select, we will let that
        // compiler handle the building of the select clauses, as it will need some
        // more syntax that is best handled by that function to keep things neat.
        if (!is_null($query->aggregate)) {
            return;
        }

        $select = $query->distinct ? 'select distinct ' : 'select ';

        return $select . $this->columnize($columns);
    }

    /**
     * 编译查询的“from”部分。
     * Compile the "from" portion of the query.
     *
     * @param string $table
     * @return string
     */
    protected function compileFrom(Builder $query, $table)
    {
        return 'from ' . $this->wrapTable($table);
    }

    /**
     * 编译查询的“连接”部分。
     * Compile the "join" portions of the query.
     *
     * @param array $joins
     * @return string
     */
    protected function compileJoins(Builder $query, $joins)
    {
        return collect($joins)->map(function ($join) use ($query) {
            $table = $this->wrapTable($join->table);

            $nestedJoins = is_null($join->joins) ? '' : ' ' . $this->compileJoins($query, $join->joins);

            $tableAndNestedJoins = is_null($join->joins) ? $table : '(' . $table . $nestedJoins . ')';

            return trim("{$join->type} join {$tableAndNestedJoins} {$this->compileWheres($join)}");
        })->implode(' ');
    }

    /**
     * 编译查询的“where”部分。
     * Compile the "where" portions of the query.
     *
     * @return string
     */
    protected function compileWheres(Builder $query)
    {
        // Each type of where clauses has its own compiler function which is responsible
        // for actually creating the where clauses SQL. This helps keep the code nice
        // and maintainable since each clause has a very small method that it uses.
        if (is_null($query->wheres)) {
            return '';
        }

        // If we actually have some where clauses, we will strip off the first boolean
        // operator, which is added by the query builders for convenience so we can
        // avoid checking for the first clauses in each of the compilers methods.
        if (count($sql = $this->compileWheresToArray($query)) > 0) {
            return $this->concatenateWhereClauses($query, $sql);
        }

        return '';
    }

    /**
     * 获取查询的所有where子句的数组。
     * Get an array of all the where clauses for the query.
     *
     * @param \EasySwoole\Database\Query\Builder $query
     * @return array
     */
    protected function compileWheresToArray($query)
    {
        return collect($query->wheres)->map(function ($where) use ($query) {
            return $where['boolean'] . ' ' . $this->{"where{$where['type']}"}($query, $where);
        })->all();
    }

    /**
     * 将where子句语句格式化为一个字符串。
     * Format the where clause statements into one string.
     *
     * @param \EasySwoole\Database\Query\Builder $query
     * @param array $sql
     * @return string
     */
    protected function concatenateWhereClauses($query, $sql)
    {
        $conjunction = $query instanceof JoinClause ? 'on' : 'where';

        return $conjunction . ' ' . $this->removeLeadingBoolean(implode(' ', $sql));
    }

    /**
     * 编译一个原始where子句。
     * Compile a raw where clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereRaw(Builder $query, $where)
    {
        return $where['sql'];
    }

    /**
     * 编译一个基本where子句。
     * Compile a basic where clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereBasic(Builder $query, $where)
    {
        $value = $this->parameter($where['value']);

        return $this->wrap($where['column']) . ' ' . $where['operator'] . ' ' . $value;
    }

    /**
     * 编译一个where-in子句。
     * Compile a "where in" clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereIn(Builder $query, $where)
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' in (' . $this->parameterize($where['values']) . ')';
        }

        return '0 = 1';
    }

    /**
     * 编译一个“where not in”子句。
     * Compile a "where not in" clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereNotIn(Builder $query, $where)
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' not in (' . $this->parameterize($where['values']) . ')';
        }

        return '1 = 1';
    }

    /**
     * 编译一个“where not in raw”子句。
     * Compile a "where not in raw" clause.
     *
     * For safety, whereIntegerInRaw ensures this method is only used with integer values.
     *
     * @param array $where
     * @return string
     */
    protected function whereNotInRaw(Builder $query, $where)
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' not in (' . implode(', ', $where['values']) . ')';
        }

        return '1 = 1';
    }

    /**
     * 编译where in子查询子句。
     * Compile a where in sub-select clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereInSub(Builder $query, $where)
    {
        return $this->wrap($where['column']) . ' in (' . $this->compileSelect($where['query']) . ')';
    }

    /**
     * 编译where not in子查询子句。
     * Compile a where not in sub-select clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereNotInSub(Builder $query, $where)
    {
        return $this->wrap($where['column']) . ' not in (' . $this->compileSelect($where['query']) . ')';
    }

    /**
     * 编译一个“where in raw”子句。
     * Compile a "where in raw" clause.
     *
     * For safety, whereIntegerInRaw ensures this method is only used with integer values.
     *
     * @param array $where
     * @return string
     */
    protected function whereInRaw(Builder $query, $where)
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' in (' . implode(', ', $where['values']) . ')';
        }

        return '0 = 1';
    }

    /**
     * 编译一个“where null”子句。
     * Compile a "where null" clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereNull(Builder $query, $where)
    {
        return $this->wrap($where['column']) . ' is null';
    }

    /**
     * 编译一个“where not null”子句。
     * Compile a "where not null" clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereNotNull(Builder $query, $where)
    {
        return $this->wrap($where['column']) . ' is not null';
    }

    /**
     * 编译一个“between”where子句。
     * Compile a "between" where clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereBetween(Builder $query, $where)
    {
        $between = $where['not'] ? 'not between' : 'between';

        $min = $this->parameter(reset($where['values']));

        $max = $this->parameter(end($where['values']));

        return $this->wrap($where['column']) . ' ' . $between . ' ' . $min . ' and ' . $max;
    }

    /**
     * 编译一个“where date”子句。
     * Compile a "where date" clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereDate(Builder $query, $where)
    {
        return $this->dateBasedWhere('date', $query, $where);
    }

    /**
     * 编译一个“where time”子句。
     * Compile a "where time" clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereTime(Builder $query, $where)
    {
        return $this->dateBasedWhere('time', $query, $where);
    }

    /**
     * 编译一个“where day”子句。
     * Compile a "where day" clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereDay(Builder $query, $where)
    {
        return $this->dateBasedWhere('day', $query, $where);
    }

    /**
     * 编译一个“where month”子句。
     * Compile a "where month" clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereMonth(Builder $query, $where)
    {
        return $this->dateBasedWhere('month', $query, $where);
    }

    /**
     * 编译一个“where year”子句。
     * Compile a "where year" clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereYear(Builder $query, $where)
    {
        return $this->dateBasedWhere('year', $query, $where);
    }

    /**
     * 编译一个基于日期的where子句。
     * Compile a date based where clause.
     *
     * @param string $type
     * @param array $where
     * @return string
     */
    protected function dateBasedWhere($type, Builder $query, $where)
    {
        $value = $this->parameter($where['value']);

        return $type . '(' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
    }

    /**
     * 编译一个比较两列的where子句。
     * Compile a where clause comparing two columns.
     *
     * @param array $where
     * @return string
     */
    protected function whereColumn(Builder $query, $where)
    {
        return $this->wrap($where['first']) . ' ' . $where['operator'] . ' ' . $this->wrap($where['second']);
    }

    /**
     * 编译嵌套的where子句。
     * Compile a nested where clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereNested(Builder $query, $where)
    {
        // Here we will calculate what portion of the string we need to remove. If this
        // is a join clause query, we need to remove the "on" portion of the SQL and
        // if it is a normal query we need to take the leading "where" of queries.
        $offset = $query instanceof JoinClause ? 3 : 6;

        return '(' . substr($this->compileWheres($where['query']), $offset) . ')';
    }

    /**
     * 使用子查询编译where条件。
     * Compile a where condition with a sub-select.
     *
     * @param array $where
     * @return string
     */
    protected function whereSub(Builder $query, $where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) . ' ' . $where['operator'] . " ({$select})";
    }

    /**
     * 编译where exists子句。
     * Compile a where exists clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereExists(Builder $query, $where)
    {
        return 'exists (' . $this->compileSelect($where['query']) . ')';
    }

    /**
     * 编译where not exists子句。
     * Compile a where not exists clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereNotExists(Builder $query, $where)
    {
        return 'not exists (' . $this->compileSelect($where['query']) . ')';
    }

    /**
     * 编译where行值条件。
     * Compile a where row values condition.
     *
     * @param array $where
     * @return string
     */
    protected function whereRowValues(Builder $query, $where)
    {
        $columns = $this->columnize($where['columns']);

        $values = $this->parameterize($where['values']);

        return '(' . $columns . ') ' . $where['operator'] . ' (' . $values . ')';
    }

    /**
     * 编译一个“where JSON contains”子句。
     * Compile a "where JSON contains" clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereJsonContains(Builder $query, $where)
    {
        $not = $where['not'] ? 'not ' : '';

        return $not . $this->compileJsonContains(
                $where['column'],
                $this->parameter($where['value'])
            );
    }

    /**
     * 将“JSON contains”语句编译成SQL。
     * Compile a "JSON contains" statement into SQL.
     *
     * @param string $column
     * @param string $value
     * @throws \RuntimeException
     * @return string
     */
    protected function compileJsonContains($column, $value)
    {
        throw new RuntimeException('This database engine does not support JSON contains operations.');
    }

    /**
     * 编译一个“where-JSON-length”子句。
     * Compile a "where JSON length" clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereJsonLength(Builder $query, $where)
    {
        return $this->compileJsonLength(
            $where['column'],
            $where['operator'],
            $this->parameter($where['value'])
        );
    }

    /**
     * 将“JSON长度”语句编译成SQL。
     * Compile a "JSON length" statement into SQL.
     *
     * @param string $column
     * @param string $operator
     * @param string $value
     * @throws \RuntimeException
     * @return string
     */
    protected function compileJsonLength($column, $operator, $value)
    {
        throw new RuntimeException('This database engine does not support JSON length operations.');
    }

    /**
     * 编译查询的“group by”部分。
     * Compile the "group by" portions of the query.
     *
     * @param array $groups
     * @return string
     */
    protected function compileGroups(Builder $query, $groups)
    {
        return 'group by ' . $this->columnize($groups);
    }

    /**
     * 编译查询的“having”部分。
     * Compile the "having" portions of the query.
     *
     * @param array $havings
     * @return string
     */
    protected function compileHavings(Builder $query, $havings)
    {
        $sql = implode(' ', array_map([$this, 'compileHaving'], $havings));

        return 'having ' . $this->removeLeadingBoolean($sql);
    }

    /**
     * 编译一个having子句。
     * Compile a single having clause.
     *
     * @return string
     */
    protected function compileHaving(array $having)
    {
        // If the having clause is "raw", we can just return the clause straight away
        // without doing any more processing on it. Otherwise, we will compile the
        // clause into SQL based on the components that make it up from builder.
        if ($having['type'] === 'Raw') {
            return $having['boolean'] . ' ' . $having['sql'];
        }
        if ($having['type'] === 'between') {
            return $this->compileHavingBetween($having);
        }

        return $this->compileBasicHaving($having);
    }

    /**
     * 编译一个基本的having子句。
     * Compile a basic having clause.
     *
     * @param array $having
     * @return string
     */
    protected function compileBasicHaving($having)
    {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return $having['boolean'] . ' ' . $column . ' ' . $having['operator'] . ' ' . $parameter;
    }

    /**
     * 编写一个“between”having子句。
     * Compile a "between" having clause.
     *
     * @param array $having
     * @return string
     */
    protected function compileHavingBetween($having)
    {
        $between = $having['not'] ? 'not between' : 'between';

        $column = $this->wrap($having['column']);

        $min = $this->parameter(head($having['values']));

        $max = $this->parameter(last($having['values']));

        return $having['boolean'] . ' ' . $column . ' ' . $between . ' ' . $min . ' and ' . $max;
    }

    /**
     * 编译查询的“orderby”部分。
     * Compile the "order by" portions of the query.
     *
     * @param array $orders
     * @return string
     */
    protected function compileOrders(Builder $query, $orders)
    {
        if (!empty($orders)) {
            return 'order by ' . implode(', ', $this->compileOrdersToArray($query, $orders));
        }

        return '';
    }

    /**
     * 将查询顺序编译为数组。
     * Compile the query orders to an array.
     *
     * @param array $orders
     * @return array
     */
    protected function compileOrdersToArray(Builder $query, $orders)
    {
        return array_map(function ($order) {
            return !isset($order['sql'])
                ? $this->wrap($order['column']) . ' ' . $order['direction']
                : $order['sql'];
        }, $orders);
    }

    /**
     * 编译查询的“limit”部分。
     * Compile the "limit" portions of the query.
     *
     * @param int $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return 'limit ' . (int)$limit;
    }

    /**
     * 编译查询的“offset”部分。
     * Compile the "offset" portions of the query.
     *
     * @param int $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return 'offset ' . (int)$offset;
    }

    /**
     * 编译附加到主查询的“union”查询。
     * Compile the "union" queries attached to the main query.
     *
     * @return string
     */
    protected function compileUnions(Builder $query)
    {
        $sql = '';

        foreach ($query->unions as $union) {
            $sql .= $this->compileUnion($union);
        }

        if (!empty($query->unionOrders)) {
            $sql .= ' ' . $this->compileOrders($query, $query->unionOrders);
        }

        if (isset($query->unionLimit)) {
            $sql .= ' ' . $this->compileLimit($query, $query->unionLimit);
        }

        if (isset($query->unionOffset)) {
            $sql .= ' ' . $this->compileOffset($query, $query->unionOffset);
        }

        return ltrim($sql);
    }

    /**
     * 编译一个union语句。
     * Compile a single union statement.
     *
     * @return string
     */
    protected function compileUnion(array $union)
    {
        $conjunction = $union['all'] ? ' union all ' : ' union ';

        return $conjunction . $union['query']->toSql();
    }

    /**
     * 将联合聚合查询编译为SQL。
     * Compile a union aggregate query into SQL.
     *
     * @return string
     */
    protected function compileUnionAggregate(Builder $query)
    {
        $sql = $this->compileAggregate($query, $query->aggregate);

        $query->aggregate = null;

        return $sql . ' from (' . $this->compileSelect($query) . ') as ' . $this->wrapTable('temp_table');
    }

    /**
     * 将锁编译为SQL。
     * Compile the lock into SQL.
     *
     * @param bool|string $value
     * @return string
     */
    protected function compileLock(Builder $query, $value)
    {
        return is_string($value) ? $value : '';
    }

    /**
     * 包装给定的JSON选择器。
     * Wrap the given JSON selector.
     *
     * @param string $value
     * @return string
     */
    protected function wrapJsonSelector($value)
    {
        throw new RuntimeException('This database engine does not support JSON operations.');
    }

    /**
     * 将给定的JSON选择器拆分为字段和可选路径，并分别包装它们。
     * Split the given JSON selector into the field and the optional path and wrap them separately.
     *
     * @param string $column
     * @return array
     */
    protected function wrapJsonFieldAndPath($column)
    {
        $parts = explode('->', $column, 2);

        $field = $this->wrap($parts[0]);

        $path = count($parts) > 1 ? ', ' . $this->wrapJsonPath($parts[1], '->') : '';

        return [$field, $path];
    }

    /**
     * 包装给定的JSON路径。
     * Wrap the given JSON path.
     *
     * @param string $value
     * @param string $delimiter
     * @return string
     */
    protected function wrapJsonPath($value, $delimiter = '->')
    {
        return '\'$."' . str_replace($delimiter, '"."', $value) . '"\'';
    }

    /**
     * 判断给定字符串是否为JSON选择器。
     * Determine if the given string is a JSON selector.
     *
     * @param string $value
     * @return bool
     */
    protected function isJsonSelector($value)
    {
        return Str::contains($value, '->');
    }

    /**
     * 连接一个数组的所有值，删除空值。
     * Concatenate an array of segments, removing empties.
     *
     * @param array $segments
     * @return string
     */
    protected function concatenate($segments)
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string)$value !== '';
        }));
    }

    /**
     * 从语句中删除前导布尔值。
     * Remove the leading boolean from a statement.
     *
     * @param string $value
     * @return string
     */
    protected function removeLeadingBoolean($value)
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }
}
