<?php

declare(strict_types=1);

 /**
 * Flextype - Hybrid Content Management System with the freedom of a headless CMS 
 * and with the full functionality of a traditional CMS!
 * 
 * Copyright (c) Sergey Romanenko (https://awilum.github.io)
 *
 * Licensed under The MIT License.
 *
 * For full copyright and license information, please see the LICENSE
 * Redistributions of files must retain the above copyright notice.
 */

namespace Flextype\Console\Commands\Tokens;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Thermage\div;
use function Thermage\renderToString;

class TokensDeleteCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('tokens:delete');
        $this->setDescription('Delete token entry.');
        $this->addArgument('id', InputArgument::REQUIRED, 'Unique identifier of the token entry.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');

        if (! entries()->has($id)) {
            $output->write(
                renderToString(
                    div('Failure: Token entry [b]' . $id . '[/b] doesn\'t exists.', 
                        'bg-danger px-2 py-1')
                )
            );
            return Command::FAILURE;
        }

        if (entries()->delete($id)) {
            $output->write(
                renderToString(
                    div('Success: Token entry [b]' . $id . '[/b] deleted.', 
                        'bg-success px-2 py-1')
                )
            );
            return Command::SUCCESS;
        } else {
            $output->write(
                renderToString(
                    div('Failure: Token entry [b]' . $id . '[/b] wasn\'t deleted.', 
                        'bg-danger px-2 py-1')
                )
            );
            return Command::FAILURE;
        }
    }
}