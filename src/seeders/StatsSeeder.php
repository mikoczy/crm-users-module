<?php

namespace Crm\UsersModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\ApplicationModule\Stats\StatsRepository;
use Crm\UsersModule\Auth\Repository\AdminAccessRepository;
use Crm\UsersModule\Auth\Repository\AdminGroupsRepository;
use Crm\UsersModule\Builder\UserBuilder;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Output\OutputInterface;

class StatsSeeder implements ISeeder
{
    private $statsRepository;

    public function __construct(
        StatsRepository $statsRepository
    ) {
        $this->statsRepository = $statsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $usersCount = $this->statsRepository->loadByKey('users_count');
        if (!$usersCount) {
            $this->statsRepository->getDatabase()->query("INSERT INTO stats (`key`, `value`) VALUES ('users_count', (SELECT COUNT(*) FROM users))");
            $output->writeln('  <comment>* stat <info>users_count</info> created</comment>');
        } else {
            $output->writeln('  * stat <info>users_count</info> exists');
        }
    }
}
