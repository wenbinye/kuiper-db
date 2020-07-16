<?php

declare(strict_types=1);

namespace kuiper\db;

use Aura\SqlQuery\QueryFactory;
use Dotenv\Dotenv;
use kuiper\db\event\listener\AutoCreateShardTable;
use kuiper\db\event\ShardTableNotExistEvent;
use kuiper\db\event\StatementQueriedEvent;
use kuiper\db\fixtures\DoorRepository;
use kuiper\db\fixtures\Employee;
use kuiper\db\fixtures\EmployeeRepository;
use kuiper\db\metadata\MetaModelFactory;
use kuiper\db\metadata\NamingStrategy;
use kuiper\db\sharding\AbstractShardingCrudRepository;
use kuiper\db\sharding\Cluster;
use kuiper\db\sharding\rule\EqualToRule;
use kuiper\db\sharding\rule\IdentityRule;
use kuiper\db\sharding\rule\RuleInterface;
use kuiper\db\sharding\Strategy;
use function kuiper\helper\env;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ShardingRepositoryTest extends AbstractRepositoryTestCase
{
    public static function setupBeforeClass(): void
    {
        if (file_exists(__DIR__.'/.env')) {
            Dotenv::createMutable(__DIR__)->load();
        }
    }

    private static function employee(int $sharding, string $name): Employee
    {
        $employee = new Employee();
        $employee->setSharding($sharding);
        $employee->setName($name);

        return $employee;
    }

    public static function strategy(RuleInterface $dbRule, RuleInterface $tableRule): Strategy
    {
        $strategy = new Strategy();
        $strategy->setDbRule($dbRule);
        $strategy->setTableRule($tableRule);

        return $strategy;
    }

    public function createConnection(EventDispatcherInterface $eventDispatcher): Connection
    {
        $config = $this->getConfig();
        $conn = new Connection($config[0], $config[1], $config[2]);
        $conn->setEventDispatcher($eventDispatcher);

        return $conn;
    }

    public function getConfig()
    {
        $config = [
            sprintf('mysql:dbname=%s;host=%s;port=%d;charset=%s',
                env('DB_NAME', 'test'),
                env('DB_HOST', 'localhost'),
                env('DB_PORT') ?: 3306,
                env('DB_CHARSET', 'utf8mb4')),
            env('DB_USER', 'root'),
            env('DB_PASS', ''),
        ];

        return $config;
    }

    public function createRepository($repositoryClass): AbstractShardingCrudRepository
    {
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addListener(StatementQueriedEvent::class, function (StatementQueriedEvent $event) {
            error_log($event->getStatement()->getStatement());
        });
        $eventDispatcher->addListener(ShardTableNotExistEvent::class, new AutoCreateShardTable());
        $cluster = new Cluster([new SingleConnectionPool($this->createConnection($eventDispatcher))], new QueryFactory('mysql'), $eventDispatcher);
        $tablePrefix = 'test_';
        $cluster->setTableStrategy($tablePrefix.'employee', self::strategy(new IdentityRule(0), new EqualToRule('sharding')));

        return new $repositoryClass(
            $cluster,
            new MetaModelFactory($this->createAttributeRegistry(), new NamingStrategy($tablePrefix), null, null),
            new DateTimeFactory(),
            $eventDispatcher);
    }

    public function testSave()
    {
        $repository = $this->createRepository(EmployeeRepository::class);
        $result = $repository->save(self::employee(1, 'john'));
        $this->assertNotNull($result);
    }

    public function testBatchInsert()
    {
        $repository = $this->createRepository(EmployeeRepository::class);
        $repository->deleteAllBy(Criteria::create(['sharding' => 1]));

        $result = $repository->batchInsert([
            self::employee(1, 'john'),
            self::employee(2, 'mary'),
            self::employee(2, 'lucy'),
        ]);
        // var_export($result);
        $this->assertCount(3, $result);
        $result[0]->setName('lilei');
        $result[1]->setName('hanmeimei');
        $result[2]->setName('guotao');

        $repository->batchUpdate($result);
    }

    public function testFindBy()
    {
        /** @var DoorRepository $repository */
        $repository = $this->createRepository(EmployeeRepository::class);
        $repository->deleteAllBy(Criteria::create(['sharding' => 1]));
        $repository->save(self::employee(1, 'john'));

        $employee = $repository->findFirstBy(Criteria::create(['sharding' => 1, 'name' => 'john']));
        // var_export($employee);
        $this->assertNotNull($employee);
    }
}
