<?php

namespace Hyperf\Seata\Rm\DataSource\Exec;

use Hyperf\Seata\Exception\SQLException;
use Hyperf\Seata\Rm\DataSource\Sql\Struct\TableRecords;

abstract class BaseInsertExecutor extends AbstractDMLBaseExecutor implements InsertExecutor
{
    public function afterImage(TableRecords $tableRecords): ?TableRecords
    {
        $pkValues = $this->getPkValues();
        $afterImage = $this->buildTableRecords($pkValues);
        if (empty($afterImage)) {
            throw new SQLException('Failed to build after-image for insert');
        }
        return $afterImage;
    }

    public function beforeImage(): TableRecords
    {
        return TableRecords::empty($this->tableMeta);
    }

    protected function buildTableRecords(array $pkValuesMap): ?TableRecords
    {
        $parser = $this->parser->parser();
    }
}