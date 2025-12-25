<?php

namespace ReportWriter\Report\Builder\Exception;

use Throwable;

class ReportWriterException extends \LogicException
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}