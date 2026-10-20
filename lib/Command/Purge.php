<?php

declare(strict_types=1);

namespace OCA\Vitalog\Command;

use OCA\Vitalog\Service\DataPurger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Purge extends Command {
	public function __construct(
		private DataPurger $purger,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('vitalog:purge')
			->setDescription('Permanently delete all the data stored by Vitalog (run it before "app:remove vitalog")')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$counts = $this->purger->counts();
		if ($counts === []) {
			$output->writeln('Vitalog has no data to delete.');

			return self::SUCCESS;
		}

		$output->writeln('This will permanently delete:');
		foreach ($counts as $table => $rows) {
			$output->writeln(sprintf('  %s: %d row(s)', $table, $rows));
		}
		$output->writeln('Calendar events created by Vitalog and attached files are not touched.');

		if (!$input->getOption('force')) {
			if (!$input->isInteractive() || !$this->confirm($input, $output)) {
				$output->writeln('Aborted. Use --force to delete without asking.');

				return self::FAILURE;
			}
		}

		$this->purger->purge();
		$output->writeln('Done. You can now run "occ app:remove vitalog".');

		return self::SUCCESS;
	}

	private function confirm(InputInterface $input, OutputInterface $output): bool {
		/** @var QuestionHelper $helper */
		$helper = $this->getHelper('question');

		return (bool)$helper->ask($input, $output, new ConfirmationQuestion('Delete all of it? [y/N] ', false));
	}
}
