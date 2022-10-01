<?php

declare(strict_types=1);
/**
 * Copyright 2019-2022 Seata.io Group.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */
namespace Hyperf\Seata\Rm\DataSource\Exec;

use Hyperf\Seata\Rm\DataSource\Sql\Struct\TableRecords;
use Hyperf\Seata\Rm\DataSource\Undo\SQLUndoLog;

abstract class AbstractDMLBaseExecutor extends BaseTransactionalExecutor
{
    public function beforeImage(): TableRecords
    {
        $tableMeta = $this->getTableMeta($this->parser->getDbType());
        return $this->buildTableRecords($tableMeta);
    }

    public function prepareUndoLog(?TableRecords $beforeImages = null, ?TableRecords $afterImages = null)
    {
        if (count($beforeImages->getRows()) == 0) {
            return;
        }

        $lockKey = $this->buildLockKey($beforeImages);
        $this->PDO->appendLockKey($lockKey);

        $undoLogItems = $this->buildUndoItem($beforeImages, $afterImages);
        $this->PDO->appendUndoLog($undoLogItems);
    }

    public function afterImage(TableRecords $tableRecords): ?TableRecords
    {
        return null;
    }

    protected function doExecute(?array $params = null)
    {
        try {
            var_dump('=doExecute====beforImage');
            $beforeImage = $this->beforeImage();
            dump('build beforeImage');
            $res = $this->PDO->prepare($this->parser->getOriginSql());
            $result = $res->execute($params);
            dump('build afterImage');
            $afterImage = $this->afterImage($beforeImage);
            dump('prepareUndoLog');
            $this->prepareUndoLog($beforeImage, $afterImage);
            return $result;
        } catch (\Throwable $exception) {
            dump($exception->getMessage());
            dump($exception->getTraceAsString());
        }

    }

    protected function buildLockKey(TableRecords $tableRecords): string
    {
        if (empty($tableRecords->getRows()) || count($tableRecords->getRows()) == 0) {
            return '';
        }

        $lockKey = $tableRecords->getTableName() . ':';

        $fields = $tableRecords->pkFields();
        $len = count($fields);
        for ($i = 0; $i < $len; ++$i) {
            $lockKey .= $fields[$i]->getName();
            if ($i < $len - 1) {
                $lockKey .= ',';
            }
        }
        return $lockKey;
    }

    protected function buildUndoItem(?TableRecords $beforeImages = null, ?TableRecords $afterImages = null): SQLUndoLog
    {
        $sqlType = $this->parser->getSqlType();
        $tableName = $this->parser->getTableName();

        $undoLog = new SQLUndoLog();
        $undoLog->setTableName($tableName)
            ->setSqlType($sqlType);
        ! empty($afterImages) && $undoLog->setAfterImage($afterImages);
        ! empty($beforeImages) && $undoLog->setAfterImage($beforeImages);
        return $undoLog;
    }
}
