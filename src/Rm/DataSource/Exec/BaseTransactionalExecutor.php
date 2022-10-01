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

use Hyperf\Seata\Core\Context\RootContext;
use Hyperf\Seata\Rm\DataSource\Sql\Struct\TableMeta;
use Hyperf\Seata\Rm\DataSource\Sql\Struct\TableMetaCacheFactory;
use Hyperf\Seata\Rm\DataSource\Sql\Struct\TableRecords;
use Hyperf\Seata\Rm\PDOProxy;
use Hyperf\Seata\SqlParser\Parser\ParserInterface;

abstract class BaseTransactionalExecutor implements Executor
{
    protected ParserInterface $parser;

    protected PDOProxy $PDO;

    protected array $bindParamContext = [];

    protected array $bindColumnContext = [];

    protected array $bindValueContext = [];

    protected ?TableMeta $tableMeta = null;

    public function __construct(ParserInterface $parser, PDOProxy $PDO, array $bindParamContext = [], array $bindColumnContext = [], array $bindValueContext = [])
    {
        $this->parser = $parser;
        $this->PDO = $PDO;
        $this->bindColumnContext = $bindColumnContext;
        $this->bindParamContext = $bindParamContext;
        $this->bindValueContext = $bindValueContext;
        $tableMetaCache = TableMetaCacheFactory::getTableMetaCache($this->parser->getDbType());


        $this->tableMeta = $tableMetaCache->getTableMeta($PDO, $this->parser->getTableName(), $PDO->getResourceId());
    }

    public function execute(?array $params = null)
    {
        $xid = RootContext::getXID();
        if (! empty($xid)) {
            $this->PDO->bind($xid);
        }
        $this->PDO->setGlobalLockRequire(RootContext::requireGlobalLock());
        return $this->doExecute($params);
    }


    protected function getTableMeta(?string $dbType = null): TableMeta
    {
        var_dump('----getTableMeta-1');
        if (! empty($this->tableMeta)) {
            return $this->tableMeta;
        }
        var_dump('----getTableMeta-2');
        $tableMetaCache = TableMetaCacheFactory::getTableMetaCache($dbType);
        var_dump('----getTableMeta-3');
        return $tableMetaCache->getTableMeta($this->PDO, $this->parser->getTableName(), $this->parser->getResourceId());
    }

    abstract protected function doExecute(?array $params = null);
}
