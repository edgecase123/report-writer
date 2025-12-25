<?php

namespace ReportWriter\Report\Data;

interface DataProviderInterface
{
    public function getRecords(): \Iterator;
}