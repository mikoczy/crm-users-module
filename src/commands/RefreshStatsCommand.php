<?php

namespace Crm\UsersModule\Commands;

use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshStatsCommand extends Command
{
    private $usersRepository;

    public function __construct(UsersRepository $usersRepository)
    {
        parent::__construct();
        $this->usersRepository = $usersRepository;
    }

    protected function configure()
    {
        $this->setName('user:refresh_stats');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>***** REFRESHING USERS STATS *****</info>');
        $output->writeln('');

        $this->usersRepository->totalCount(true, true);

        $output->writeln('<info>Done</info>');
    }
}
