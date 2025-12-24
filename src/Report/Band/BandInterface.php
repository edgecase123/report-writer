<?php

namespace ReportWriter\Report\Band;

interface BandInterface
{
    public function render(): string;
}
