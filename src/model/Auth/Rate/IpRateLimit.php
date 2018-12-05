<?php

namespace Crm\UsersModule\Auth\Rate;

use Crm\ApplicationModule\Request;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Nette\Utils\DateTime;

class IpRateLimit implements RateLimitInterface
{
    private $loginAttemptsRepository;

    private $attempts;

    private $timeout;

    public function __construct(LoginAttemptsRepository $loginAttemptsRepository, int $attempts = 2, string $timeout = '-100 seconds')
    {
        $this->loginAttemptsRepository = $loginAttemptsRepository;
        $this->attempts = $attempts;
        $this->timeout = $timeout;
    }

    public function check(array $credentials): bool
    {
        $ip = Request::getIp();

        $lastAccess = $this->loginAttemptsRepository->lastIpAttempts($ip, $this->attempts);
        if (count($lastAccess) == 0) {
            return true;
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
            return false;
        }

        return true;
    }
}
