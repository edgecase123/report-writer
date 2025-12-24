<?php

namespace ReportWriter\Tests\Unit\Report;

use ReportWriter\Report\AbstractReport;
use ReportWriter\Report\ReportInterface;

class ConcreteTestReport extends AbstractReport implements ReportInterface
{
    // We will spy on the rendered band
}