<?php

declare(strict_types=1);

/**
 * Flextype (https://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

namespace Flextype\Console\Commands\Entries;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EntriesDeleteCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('entries:delete');
        $this->setDescription('Delete entry.');
        $this->addArgument('id', InputArgument::REQUIRED, 'Unique identifier of the entry.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (entries()->delete($input->getArgument('id'))) {
            $io->success('Entry ' . $input->getArgument('id') . ' deleted.');
            return Command::SUCCESS;
        } else {
            $io->error('Entry ' . $input->getArgument('id') . ' wasn\'t deleted.');
            return Command::FAILURE;
        }
    }
}