<?php

namespace Crm\UsersModule\Events;

use Crm\ApiModule\Repository\UserSourceAccessesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Detection\MobileDetect;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class UserLastAccessHandler extends AbstractListener
{
    private $usersRepository;

    private $userSourceAccessesRepository;

    public function __construct(UsersRepository $usersRepository, UserSourceAccessesRepository $userSourceAccessesRepository)
    {
        $this->usersRepository = $usersRepository;
        $this->userSourceAccessesRepository = $userSourceAccessesRepository;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof UserLastAccessEvent)) {
            throw new \Exception("Unable to handle event, expected UserLastAccessEvent");
        }

        $source = $this->getSource($event->getSource(), $event->getUserAgent());
        $user = $event->getUser();
        if (!$user) {
            return true;
        }

        $this->userSourceAccessesRepository->upsert($user->id, $source, $event->getDateTime());
        return true;
    }

    private function getSource($source, $userAgent)
    {
        $source = 'web';
        $detector = new MobileDetect(null, $userAgent);
        if ($detector->isTablet()) {
            $source .= '_tablet';
        } elseif ($detector->isMobile()) {
            $source .= '_mobile';
        }
        return $source;
    }
}
