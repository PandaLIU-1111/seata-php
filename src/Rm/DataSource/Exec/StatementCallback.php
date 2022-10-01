<?php

namespace Hyperf\Seata\Rm\DataSource\Exec;

interface StatementCallback
{
    public function execute();
}