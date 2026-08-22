<?php

declare(strict_types=1);

namespace ReportWriter\Stream;

class Page
{
    private int $pageNumber;
    /** @var PositionedElement[] */
    private array $elements;

    /** @param PositionedElement[] $elements */
    public function __construct(int $pageNumber, array $elements = [])
    {
        $this->pageNumber = $pageNumber;
        $this->elements   = $elements;
    }

    public function getPageNumber(): int { return $this->pageNumber; }

    /** @return PositionedElement[] */
    public function getElements(): array { return $this->elements; }

    public function addElement(PositionedElement $element): void
    {
        $this->elements[] = $element;
    }

    public function toArray(): array
    {
        $elements = [];
        foreach ($this->elements as $e) {
            $elements[] = $e->toArray();
        }
        return [
            'page_number' => $this->pageNumber,
            'elements'    => $elements,
        ];
    }
}
