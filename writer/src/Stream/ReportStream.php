<?php

declare(strict_types=1);

namespace ReportWriter\Stream;

class ReportStream implements \IteratorAggregate
{
    /** @var Page[] */
    private array $pages;

    /** @param Page[] $pages */
    public function __construct(array $pages)
    {
        $this->pages = $pages;
    }

    /** @return Page[] */
    public function getPages(): array { return $this->pages; }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->pages);
    }

    public function toArray(): array
    {
        $pages = [];
        foreach ($this->pages as $p) {
            $pages[] = $p->toArray();
        }
        return ['pages' => $pages];
    }
}
