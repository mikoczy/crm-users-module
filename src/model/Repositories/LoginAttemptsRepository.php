<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Context;
use Sinergi\BrowserDetector\Browser;
use Sinergi\BrowserDetector\Device;
use Sinergi\BrowserDetector\Os;
use Sinergi\BrowserDetector\UserAgent;

class LoginAttemptsRepository extends Repository
{
    const STATUS_UNKNOWN = 'unknown';
    const STATUS_OK = 'ok';
    const STATUS_API_OK = 'api_ok';
    const STATUS_NOT_FOUND_EMAIL = 'not_found_email';
    const STATUS_WRONG_PASS = 'wrong_pass';
    const STATUS_INACTIVE_USER = 'inactive_user';
    const STATUS_TOKEN_DATE_EXPIRED = 'token_date_expired';
    const STATUS_TOKEN_NOT_FOUND = 'token_not_found';
    const STATUS_TOKEN_COUNT_EXPIRED = 'token_count_expired';
    const STATUS_TOKEN_OK = 'token_ok';
    const STATUS_ACCESS_TOKEN_OK = 'access_token_ok';
    const STATUS_LOGIN_AFTER_SIGN_UP = 'login_after_sign_up';
    const RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';

    /** @var Context */
    protected $tableName = 'login_attempts';

    public function okStatuses(): array
    {
        return [
            LoginAttemptsRepository::STATUS_OK,
            LoginAttemptsRepository::STATUS_API_OK,
            LoginAttemptsRepository::STATUS_TOKEN_OK,
            LoginAttemptsRepository::STATUS_ACCESS_TOKEN_OK,
            LoginAttemptsRepository::STATUS_LOGIN_AFTER_SIGN_UP,
        ];
    }

    public function okStatus($status): bool
    {
        return in_array($status, $this->okStatuses());
    }

    public function insertAttempt($email, $userId, $source, $status, $ip, $userAgent, $dateTime, $message = null)
    {
        $browser = null;
        $browserVersion = null;
        $os = null;
        $device = null;
        $isMobile = null;
        if ($userAgent) {
            $ua = new UserAgent($userAgent);
            $o = new Os($ua);
            $d = new Device($ua);
            $b = new Browser($ua);

            $isMobile = $o->isMobile();
            $browser = $b->getName();
            $browserVersion = $b->getVersion();
            $os = $o->getName();
            $device = $d->getName();
        }

        return $this->getTable()->insert([
            'email' => $email,
            'user_id' => $userId,
            'created_at' => $dateTime,
            'status' => $status,
            'source' => $source,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'message' => $message,
            'browser' => $browser,
            'browser_version' => $browserVersion,
            'os' => $os,
            'device' => $device,
            'is_mobile' => $isMobile,
        ]);
    }

    public function all()
    {
        return $this->getTable()->order('id ASC');
    }

    public function totalUserAttempts($userId)
    {
        return $this->getTable()->where(['user_id' => $userId])->count('*');
    }

    public function lastUserAttempt($userId, $count = 100)
    {
        return $this->getTable()->where(['user_id' => $userId])->order('created_at DESC')->limit($count);
    }

    public function userIps($userId)
    {
        return $this->getTable()->where(['user_id' => $userId])->group('ip');
    }

    public function userAgents($userId)
    {
        return $this->getTable()->where(['user_id' => $userId])->group('user_agent');
    }

    public function lastIpAttempts($ip, $count)
    {
        return $this->getTable()->where(['ip' => $ip])->order('created_at DESC')->limit($count);
    }
}
