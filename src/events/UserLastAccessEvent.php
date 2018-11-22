<?php

namespace Crm\UsersModule\Events;

use DateTime;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class UserLastAccessEvent extends AbstractEvent
{
    private $user;

    private $source;

    private $userAgent;

    private $dateTime;

    public function __construct(ActiveRow $user, DateTime $dateTime, $source, $userAgent)
    {
        $this->user = $user;
        $this->source = $source;
        $this->userAgent = $userAgent;
        $this->dateTime = $dateTime;
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @return mixed
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function getDateTime(): DateTime
    {
        return $this->dateTime;
    }
}
