<?php

declare(strict_types=1);

namespace kuiper\db\sharding;

use Aura\SqlQuery\QueryFactory;
use Aura\SqlQuery\QueryInterface;
use kuiper\db\ConnectionPoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Cluster implements ClusterInterface
{
    /**
     * @var ConnectionPoolInterface[]
     */
    private $poolList;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var QueryFactory
     */
    private $queryFactory;

    /**
     * @var array
     */
    private $tables;

    public function __construct(array $poolList, QueryFactory $queryFactory, EventDispatcherInterface $eventDispatcher)
    {
        $this->poolList = $poolList;
        $this->queryFactory = $queryFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getQueryFactory(): QueryFactory
    {
        return $this->queryFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function from(string $table): \kuiper\db\StatementInterface
    {
        return $this->createStatement($table, $this->getQueryFactory()->newSelect());
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $table): \kuiper\db\StatementInterface
    {
        return $this->createStatement($table, $this->getQueryFactory()->newDelete());
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $table): \kuiper\db\StatementInterface
    {
        return $this->createStatement($table, $this->getQueryFactory()->newUpdate());
    }

    /**
     * {@inheritdoc}
     */
    public function insert(string $table): \kuiper\db\StatementInterface
    {
        return $this->createStatement($table, $this->getQueryFactory()->newInsert());
    }

    public function setTableStrategy(string $table, StrategyInterface $strategy): void
    {
        $this->tables[$table] = $strategy;
    }

    /**
     * {@inheritdoc}
     */
    public function getTableStrategy(string $table): StrategyInterface
    {
        return $this->tables[$table];
    }

    protected function createStatement(string $table, QueryInterface $query): \kuiper\db\StatementInterface
    {
        if (!isset($this->tables[$table])) {
            throw new \InvalidArgumentException("Table '{$table}' strategy was not configured, call setTableStrategy first");
        }

        return new Statement(new ClusterConnectionPool($this->poolList), $query, $table, $this->tables[$table], $this->eventDispatcher);
    }
}
