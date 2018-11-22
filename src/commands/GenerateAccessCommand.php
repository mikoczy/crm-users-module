<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Auth\Repository\AdminAccessRepository;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateAccessCommand extends Command
{
    private $adminAccessRepository;

    private $container;

    public function __construct(
        AdminAccessRepository $adminAccessRepository,
        Container $container
    ) {
        parent::__construct();
        $this->adminAccessRepository = $adminAccessRepository;
        $this->container = $container;
    }

    protected function configure()
    {
        $this->setName('user:generate_access')
            ->setDescription('Generate all access data');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->container->findByType('Crm\AdminModule\Presenters\AdminPresenter') as $adminServiceName) {
            try {
                $adminPresenterName = get_class($this->container->getService($adminServiceName));
                $output->writeln("Processing class <info>{$adminPresenterName}</info>");
                $this->processPresenterClass($adminPresenterName, $output);
            } catch (MissingServiceException $e) {
                $output->writeln("<error>Service {$adminServiceName} missing</error>");
            } catch (\Exception $e) {
                if (isset($adminPresenterName)) {
                    $output->writeln("<error>Unable to process presenter {$adminPresenterName}</error>");
                } else {
                    $output->writeln("<error>Unable to process service {$adminServiceName}</error>");
                }
                $output->writeln("<comment> - {$e->getMessage()}</comment>");
            }
        }
    }

    private function processPresenterClass($presenterClass, OutputInterface $output)
    {
        $parts = explode('\\', $presenterClass);
        $presenter = str_replace('Presenter', '', array_pop($parts));
        $next = array_pop($parts);
        if ($next == 'Presenters') {
            $next = array_pop($parts);
        }
        $module = str_replace('Module', '', $next);

        $actions = [];

        $methodPrefixes = ['render', 'action'];
        foreach (get_class_methods($presenterClass) as $methodName) {
            foreach ($methodPrefixes as $methodPrefix) {
                if (substr($methodName, 0, strlen($methodPrefix)) == $methodPrefix) {
                    $method = str_replace($methodPrefix, '', $methodName);
                    $method = lcfirst($method);
                    $actions[] = $method;
                }
            }
        }

        $presenterFolder = ucfirst($presenter);
        foreach (glob(__DIR__ . "/../../{$module}Module/templates/{$presenterFolder}/*.latte") as $templateFile) {
            $path = pathinfo($templateFile);
            if (!in_array($path['filename'], $actions) && $path['filename'][0] != '@') {
                $actions[] = $path['filename'];
            }
        }

        foreach ($actions as $action) {
            $resource = "{$module}:{$presenter}";
            if (!$this->adminAccessRepository->exists($resource, $action)) {
                $this->adminAccessRepository->add($resource, $action);
                $output->writeln(" <fg=yellow>* ACL resource <info>{$resource}:{$action}</info> was created</>");
            }
        }
    }
}
