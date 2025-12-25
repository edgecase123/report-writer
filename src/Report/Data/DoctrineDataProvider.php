<?php

namespace ReportWriter\Report\Data;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Iterator;

/**
 * Doctrine-specific data provider that streams results using Doctrine's iterable mode.
 *
 * - Low memory usage (does not hydrate the full result set at once)
 * - Works with any entity or custom DQL/QueryBuilder
 * - Supports parameters (filters) fluently
 */
class DoctrineDataProvider extends AbstractDataProvider
{
    private EntityManagerInterface $entityManager;

    /**
     * One of these must be provided:
     * - a QueryBuilder
     * - a ready Query
     * - a DQL string + optional class name
     */
    private ?QueryBuilder $queryBuilder = null;
    private ?Query $query = null;
    private ?string $dql = null;
    private ?string $entityClass = null;
    private iterable $data;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Use an existing QueryBuilder (most common in Symfony services).
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder): self
    {
        $this->queryBuilder = $queryBuilder;
        $this->query = null;
        $this->dql = null;
        $this->entityClass = null;

        return $this;
    }

    /**
     * Use a pre-built Query object.
     */
    public function setQuery(Query $query): self
    {
        $this->query = $query;
        $this->queryBuilder = null;
        $this->dql = null;
        $this->entityClass = null;

        return $this;
    }

    /**
     * Provide raw DQL and the entity class (or result class).
     */
    public function setDql(string $dql, ?string $entityClass = null): self
    {
        $this->dql = $dql;
        $this->entityClass = $entityClass;
        $this->queryBuilder = null;
        $this->query = null;

        return $this;
    }

    /**
     * Main method required by DataProviderInterface.
     *
     * Returns a Doctrine iterable result that yields rows one by one.
     */
    public function getRecords(): Iterator
    {
        if ($this->data instanceof \Traversable) {
            yield from $this->data;
            return;
        }

        foreach ($this->data as $record) {
            yield $record;
        }
    }

    /**
     * Internal helper that builds the final Query object based on what was provided.
     *
     * @throws \LogicException if no query source was configured
     */
    private function getPreparedQuery(): Query
    {
        if ($this->query !== null) {
            return $this->query;
        }

        if ($this->queryBuilder !== null) {
            return $this->queryBuilder->getQuery();
        }

        if ($this->dql !== null) {
            $qb = $this->entityManager->createQuery($this->dql);

            // If an entity class is given, hint Doctrine for better hydration
            if ($this->entityClass !== null) {
                $qb->setHint(Query::HINT_FORCE_PARTIAL_LOAD, true);
                // Optional: custom result class if using non-entity mapping
            }

            return $qb;
        }

        throw new \LogicException('No query source provided to DoctrineDataProvider. Use setQueryBuilder(), setQuery() or setDql().');
    }
}