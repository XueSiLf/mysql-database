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

namespace EasySwoole\Database\Schema;


class Column
{
    /**
     * @var string
     */
    protected $schema;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var mixed
     */
    protected $default;

    /**
     * @var bool
     */
    protected $isNullable;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $comment;

    public function __construct(string $schema, string $table, string $name, int $position, $default, bool $isNullable, string $type, string $comment)
    {
        $this->schema = $schema;
        $this->table = $table;
        $this->name = $name;
        $this->position = $position;
        $this->default = $default;
        $this->isNullable = $isNullable;
        $this->type = $type;
        $this->comment = $comment;
    }

    public function getSchema(): string
    {
        return $this->schema;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getComment(): string
    {
        return $this->comment;
    }
}
