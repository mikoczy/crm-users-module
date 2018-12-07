<?php

namespace Crm\UsersModule\Auth\Rate;

use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class WrongPasswordRateLimit
{
    private $loginAttemptsRepository;

    private $attempts;

    private $timeout;

    public function __construct(LoginAttemptsRepository $loginAttemptsRepository, int $attempts = 10, string $timeout = '-10 seconds')
    {
        $this->loginAttemptsRepository = $loginAttemptsRepository;
        $this->attempts = $attempts;
        $this->timeout = $timeout;
    }

    public function reachLimit(IRow $user): bool
    {
        $lastAccess = $this->loginAttemptsRepository->lastUserAttempt($user->id, $this->attempts);
        if (count($lastAccess) == 0) {
            return false;
        }

        $hasOk = false;
        $last = null;
        foreach ($lastAccess as $access) {
            if (!$last) {
                $last = $access;
            }
            if ($this->loginAttemptsRepository->okStatus($access->status)) {
                $hasOk = true;
            }
        }

        if (!$hasOk && $last->created_at > DateTime::from($this->timeout)) {
            return true;
        }

        return false;
    }
}
