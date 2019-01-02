<?php

namespace Crm\UsersModule\Authenticator;

use Crm\ApplicationModule\Authenticator\AuthenticatorInterface;
use Crm\ApplicationModule\Authenticator\BaseAuthenticator;
use Crm\UsersModule\Auth\Rate\IpRateLimit;
use Crm\UsersModule\Auth\Rate\RateLimitException;
use Crm\UsersModule\Auth\Rate\WrongPasswordRateLimit;
use Crm\UsersModule\Auth\UserAuthenticator;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Database\Table\IRow;
use Nette\Http\Request;
use Nette\Localization\ITranslator;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;

/**
 * UsernameAuthenticator authenticates user based on username, password and alwaysLogin flag.
 *
 * Required credentials (use setCredentials()):
 *
 * - 'username'
 * - 'alwaysLogin' === true
 *
 * OR
 *
 * - 'username'
 * - 'password'
 */
abstract class UsernameAuthenticator extends BaseAuthenticator
{
    private $userManager;

    private $usersRepository;

    private $translator;

    private $wrongPasswordRateLimit;

    private $ipRateLimit;

    /** @var string */
    private $username = null;

    /** @var string */
    private $password = null;

    /** @var bool */
    private $alwaysLogin = false;

    public function __construct(
        Emitter $emitter,
        \Tomaj\Hermes\Emitter $hermesEmitter,
        Request $request,
        UserManager $userManager,
        UsersRepository $usersRepository,
        ITranslator $translator,
        WrongPasswordRateLimit $wrongPasswordRateLimit,
        IpRateLimit $ipRateLimit
    ) {
        parent::__construct($emitter, $hermesEmitter, $request);

        $this->userManager = $userManager;
        $this->usersRepository = $usersRepository;
        $this->translator = $translator;
        $this->wrongPasswordRateLimit = $wrongPasswordRateLimit;
        $this->ipRateLimit = $ipRateLimit;
    }

    public function authenticate()
    {
        if ($this->username !== null && $this->alwaysLogin === true) {
            return $this->processAlwaysLogin();
        }

        if ($this->username !== null && $this->password !== null) {
            return $this->process();
        }

        return false;
    }

    public function setCredentials(array $credentials) : AuthenticatorInterface
    {
        parent::setCredentials($credentials);

        $this->username = $credentials['username'] ?? null;
        $this->password = $credentials['password'] ?? null;
        $this->alwaysLogin = $credentials['alwaysLogin'] ?? false;

        return $this;
    }

    /**
     * @throws AuthenticationException
     */
    private function processAlwaysLogin() : IRow
    {
        $user = $this->usersRepository->getByEmail($this->username);
        $this->addAttempt($this->username, $user, $this->source, LoginAttemptsRepository::STATUS_LOGIN_AFTER_SIGN_UP);
        if (!$user) {
            throw new AuthenticationException('Nesprávne meno.', UserAuthenticator::IDENTITY_NOT_FOUND);
        }
        return $user;
    }


    /**
     * @throws AuthenticationException
     */
    private function process() : IRow
    {
        if ($this->ipRateLimit->reachLimit(\Crm\ApplicationModule\Request::getIp())) {
            $this->addAttempt($this->username, null, $this->source, LoginAttemptsRepository::RATE_LIMIT_EXCEEDED, 'Rate limit exceeded.');
            throw new RateLimitException($this->translator->translate('users.authenticator.rate_limit_exceeded'), UserAuthenticator::FAILURE);
        }

        $user = $this->usersRepository->getByEmail($this->username);

        if (!$user) {
            $this->addAttempt($this->username, null, $this->source, LoginAttemptsRepository::STATUS_NOT_FOUND_EMAIL, 'Nesprávne meno.');
            throw new AuthenticationException($this->translator->translate('users.authenticator.identity_not_found'), UserAuthenticator::IDENTITY_NOT_FOUND);
        } elseif ($this->wrongPasswordRateLimit->reachLimit($user)) {
            $this->addAttempt($this->username, $user, $this->source, LoginAttemptsRepository::RATE_LIMIT_EXCEEDED, 'Rate limit exceeded.');
            throw new RateLimitException($this->translator->translate('users.authenticator.rate_limit_exceeded'), UserAuthenticator::FAILURE);
        } elseif (!$this->checkPassword($this->password, $user[UserAuthenticator::COLUMN_PASSWORD_HASH])) {
            $this->addAttempt($this->username, $user, $this->source, LoginAttemptsRepository::STATUS_WRONG_PASS, 'Heslo je nesprávne.');
            throw new AuthenticationException($this->translator->translate('users.authenticator.invalid_credentials'), UserAuthenticator::INVALID_CREDENTIAL);
        } elseif (!$user->active) {
            $this->addAttempt($this->username, $user, $this->source, LoginAttemptsRepository::STATUS_INACTIVE_USER, 'Konto je neaktívne.');
            throw new AuthenticationException($this->translator->translate('users.authenticator.inactive_account'), UserAuthenticator::IDENTITY_NOT_FOUND);
        } elseif (Passwords::needsRehash($user[UserAuthenticator::COLUMN_PASSWORD_HASH])) {
            $this->usersRepository->update($user, [
                UserAuthenticator::COLUMN_PASSWORD_HASH => Passwords::hash($this->password),
            ]);
        }

        if ($this->api) {
            $this->addAttempt($this->username, $user, $this->source, LoginAttemptsRepository::STATUS_API_OK);
        } else {
            $this->addAttempt($this->username, $user, $this->source, LoginAttemptsRepository::STATUS_OK);
        }

        $this->usersRepository->addSignIn($user);
        if (!$user->confirmed_at) {
            $this->userManager->confirmUser($user);
        }

        return $user;
    }

    protected function checkPassword($inputPassword, $passwordHash)
    {
        return Passwords::verify($inputPassword, $passwordHash);
    }
}
