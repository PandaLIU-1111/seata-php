<?php

namespace Hyperf\Seata\Rm\DataSource\Sql\Struct;

use Hyperf\Seata\Exception\UnsupportedOperationException;

class EmptyTableRecords extends TableRecords
{
    public function __construct(?TableMeta $tableMeta = null)
    {
        $this->setTableMeta($tableMeta);
    }

    public function size(): int
    {
        return 0;
    }

    public function pkRows(): array
    {
        return [];
    }

    public function add(Row $row)
    {
        throw new UnsupportedOperationException('xxx');
    }

    public function getTableMeta(): ?TableMeta
    {
        throw new UnsupportedOperationException('xxx');
    }
}