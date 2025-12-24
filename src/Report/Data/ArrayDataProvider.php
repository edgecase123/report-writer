<?php

namespace ReportWriter\Report\Data;

use Traversable;

class ArrayDataProvider extends AbstractDataProvider
{
    /**
     * @var array|Traversable
     */
    private $data = [];

    public function __construct($data = [])
    {
        $this->setData($data);
    }

    /**
     * @param iterable $data
     * @return ArrayDataProvider
     */
    public function setData(iterable $data): self
    {
        if (!is_array($data) && !$data instanceof Traversable) {
            throw new \InvalidArgumentException(
                'Data must be an array or implement Traversable.'
            );
        }

        $this->data = $data;
        return $this;
    }

    /**
     * @param string $json
     * @param bool $assoc
     * @return ArrayDataProvider
     */
    public function setJson(string $json, bool $assoc = false): self
    {
        $decoded = json_decode($json, $assoc);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('JSON must decode to an array of records.');
        }

        $this->data = $decoded;

        return $this;
    }

    /**
     * @return Traversable
     */
    public function getRecords(): Traversable
    {
        // If it's already a Traversable (Generator, Iterator, etc.), yield from it
        if ($this->data instanceof Traversable) {
            yield from $this->data;
            return;
        }

        // Otherwise, it's an array – iterate over it
        foreach ($this->data as $record) {
            yield $record;
        }
    }

    public function getData(): \Traversable
    {
        return $this->data;
    }
}