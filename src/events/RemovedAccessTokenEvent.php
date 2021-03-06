<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class RemovedAccessTokenEvent extends AbstractEvent
{
    private $userId;

    private $token;

    public function __construct(int $userId, string $accessToken)
    {
        $this->userId = $userId;
        $this->token = $accessToken;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getAccessToken(): string
    {
        return $this->token;
    }
}
