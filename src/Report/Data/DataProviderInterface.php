<?php

namespace ReportWriter\Report\Data;

use Traversable;

interface DataProviderInterface
{
    public function getRecords(): iterable;
}