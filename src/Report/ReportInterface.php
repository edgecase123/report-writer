<?php

namespace ReportWriter\Report;

interface ReportInterface
{
    public function render(): string;
}
