<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Command;

use Ecourty\TokenBundle\Repository\TokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'token:purge',
    description: 'Purge expired, consumed, and revoked tokens.',
)]
final class PurgeTokensCommand extends Command
{
    public function __construct(
        private readonly TokenRepository $tokenRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview how many tokens would be purged without actually deleting them.')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Only purge tokens of a specific type.')
            ->addOption('before', null, InputOption::VALUE_REQUIRED, 'Only purge tokens whose expiry date is before this date (e.g. "2026-01-01", "-30 days"). Consumed and revoked tokens are always purged regardless. Defaults to now.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = (bool) $input->getOption('dry-run');
        $type = $input->getOption('type');
        $type = \is_string($type) ? $type : null;

        $before = null;
        $beforeRaw = $input->getOption('before');
        if (\is_string($beforeRaw) && $beforeRaw !== '') {
            try {
                $before = new \DateTimeImmutable($beforeRaw);
            } catch (\Exception) {
                $io->error(\sprintf('Invalid date format for --before: "%s".', $beforeRaw));

                return Command::FAILURE;
            }
        }

        if ($isDryRun) {
            $count = $this->tokenRepository->countStaleTokens($type, $before);
            $io->info(\sprintf('[DRY RUN] %d token(s) would be purged.', $count));

            return Command::SUCCESS;
        }

        $count = $this->tokenRepository->purgeStaleTokens($type, $before);
        $io->success(\sprintf('%d token(s) purged.', $count));

        return Command::SUCCESS;
    }
}
