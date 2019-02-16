<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class UserSignInEvent extends AbstractEvent
{
    const SOURCE_WEB = 'web';
    const SOURCE_API = 'api';

    private $user;

    private $source;

    private $regenerateToken;

    public function __construct($user, $source = self::SOURCE_WEB, $regenerateToken = true)
    {
        $this->user = $user;
        $this->source = $source;
        $this->regenerateToken = $regenerateToken;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function getRegenerateToken()
    {
        return $this->regenerateToken;
    }
}
