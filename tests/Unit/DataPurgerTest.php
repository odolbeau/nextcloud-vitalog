<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use OCA\Vitalog\Command\Purge;
use OCA\Vitalog\Service\DataPurger;
use OCP\Config\IUserConfig;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IFunctionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DataPurgerTest extends TestCase {
	private IDBConnection&MockObject $db;
	private IUserConfig&MockObject $userConfig;
	private DataPurger $purger;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->purger = new DataPurger($this->db, $this->userConfig);
	}

	/** A query builder whose fluent calls return itself and whose result yields $count. */
	private function queryBuilder(int $count = 0): IQueryBuilder&MockObject {
		$result = $this->createMock(IResult::class);
		$result->method('fetchOne')->willReturn((string)$count);
		$query = $this->createMock(IQueryBuilder::class);
		$query->method('select')->willReturnSelf();
		$query->method('from')->willReturnSelf();
		$query->method('delete')->willReturnSelf();
		$query->method('where')->willReturnSelf();
		$query->method('func')->willReturn($this->createMock(IFunctionBuilder::class));
		$query->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
		$query->method('executeQuery')->willReturn($result);

		return $query;
	}

	public function testPurgeDropsEveryExistingTableThenForgetsTheMigrationsAndTheSettings(): void {
		$this->db->method('tableExists')->willReturnCallback(static fn (string $table): bool => $table !== 'vitalog_places');
		$dropped = [];
		$this->db->method('dropTable')->willReturnCallback(static function (string $table) use (&$dropped): void {
			$dropped[] = $table;
		});
		$query = $this->queryBuilder();
		$query->expects($this->once())->method('delete')->with('migrations')->willReturnSelf();
		$query->expects($this->once())->method('executeStatement');
		$this->db->method('getQueryBuilder')->willReturn($query);
		$this->userConfig->expects($this->once())->method('deleteApp')->with('vitalog');

		$this->purger->purge();

		$this->assertSame(['vitalog_appt_labels', 'vitalog_attachments', 'vitalog_labels', 'vitalog_appointments', 'vitalog_practitioners'], $dropped);
	}

	public function testCountsOnlyListsExistingTables(): void {
		$this->db->method('tableExists')->willReturnCallback(static fn (string $table): bool => $table === 'vitalog_labels');
		$this->db->method('getQueryBuilder')->willReturn($this->queryBuilder(3));

		$this->assertSame(['vitalog_labels' => 3], $this->purger->counts());
	}

	public function testCommandAsksBeforeDeleting(): void {
		$purger = $this->createMock(DataPurger::class);
		$purger->method('counts')->willReturn(['vitalog_labels' => 3]);
		$purger->expects($this->never())->method('purge');

		$tester = new CommandTester(new Purge($purger));
		$status = $tester->execute([], ['interactive' => false]);

		$this->assertSame(1, $status);
		$this->assertStringContainsString('--force', $tester->getDisplay());
	}

	/** @return array{0: DataPurger&MockObject, 1: CommandTester} */
	private function interactiveCommand(): array {
		$purger = $this->createMock(DataPurger::class);
		$purger->method('counts')->willReturn(['vitalog_labels' => 3]);
		$application = new Application();
		$application->addCommand(new Purge($purger));

		return [$purger, new CommandTester($application->find('vitalog:purge'))];
	}

	public function testCommandDeletesAfterAConfirmation(): void {
		[$purger, $tester] = $this->interactiveCommand();
		$purger->expects($this->once())->method('purge');

		$tester->setInputs(['y']);

		$this->assertSame(0, $tester->execute([]));
	}

	public function testCommandKeepsTheDataWhenTheAnswerIsNo(): void {
		[$purger, $tester] = $this->interactiveCommand();
		$purger->expects($this->never())->method('purge');

		$tester->setInputs(['n']);

		$this->assertSame(1, $tester->execute([]));
	}

	public function testCommandDeletesWhenForced(): void {
		$purger = $this->createMock(DataPurger::class);
		$purger->method('counts')->willReturn(['vitalog_labels' => 3]);
		$purger->expects($this->once())->method('purge');

		$tester = new CommandTester(new Purge($purger));
		$status = $tester->execute(['--force' => true], ['interactive' => false]);

		$this->assertSame(0, $status);
		$this->assertStringContainsString('vitalog_labels: 3 row(s)', $tester->getDisplay());
	}

	public function testCommandDoesNothingWithoutData(): void {
		$purger = $this->createMock(DataPurger::class);
		$purger->method('counts')->willReturn([]);
		$purger->expects($this->never())->method('purge');

		$tester = new CommandTester(new Purge($purger));

		$this->assertSame(0, $tester->execute(['--force' => true]));
	}
}
