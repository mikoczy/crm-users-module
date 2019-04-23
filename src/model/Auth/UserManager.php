<?php

namespace Crm\UsersModule\Auth;

use Crm\UsersModule\Auth\Access\StorageInterface;
use Crm\UsersModule\Builder\UserBuilder;
use Crm\UsersModule\Email\EmailValidator;
use Crm\UsersModule\Events\UserChangePasswordEvent;
use Crm\UsersModule\Events\UserChangePasswordRequestEvent;
use Crm\UsersModule\Events\UserConfirmedEvent;
use Crm\UsersModule\Events\UserSuspiciousEvent;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repository\PasswordResetTokensRepository;
use Crm\UsersModule\Repository\UserAlreadyExistsException;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Database\IRow;
use Nette\Database\Table\ActiveRow;
use Nette\Security\Passwords;
use Nette\Security\User;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class UserManager
{
    private $usersRepository;

    private $passwordGenerator;

    private $changePasswordsLogsRepository;

    private $userBuilder;

    private $emitter;

    private $emailValidator;

    private $addressesRepository;

    private $accessTokensRepository;

    private $passwordResetTokensRepository;

    private $tokenStorage;

    public function __construct(
        UsersRepository $usersRepository,
        PasswordGenerator $passwordGenerator,
        Emitter $emitter,
        ChangePasswordsLogsRepository $changePasswordsLogsRepository,
        UserBuilder $userBuilder,
        EmailValidator $emailValidator,
        AddressesRepository $addressesRepository,
        PasswordResetTokensRepository $passwordResetTokensRepository,
        AccessTokensRepository $accessTokensRepository,
        StorageInterface $tokenStorage
    ) {
        $this->usersRepository = $usersRepository;
        $this->passwordGenerator = $passwordGenerator;
        $this->emitter = $emitter;
        $this->changePasswordsLogsRepository = $changePasswordsLogsRepository;
        $this->userBuilder = $userBuilder;
        $this->emailValidator = $emailValidator;
        $this->addressesRepository = $addressesRepository;
        $this->passwordResetTokensRepository = $passwordResetTokensRepository;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @param string $email
     * @param bool $sendEmail
     * @param string $source
     * @param null $referer
     * @param bool $checkEmail
     *
     * @return @var ActiveRow|bool $user
     * @throws InvalidEmailException
     * @throws UserAlreadyExistsException
     * @throws \Nette\Utils\JsonException
     */
    public function addNewUser($email, $sendEmail = true, $source = 'unknown', $referer = null, $checkEmail = true)
    {
        if ($checkEmail && !$this->emailValidator->isValid($email)) {
            throw new InvalidEmailException($email);
        }

        $password = $this->passwordGenerator->generatePassword();

        /** @var ActiveRow|bool $user */
        $user = $this->userBuilder->createNew()
            ->sendEmail($sendEmail)
            ->setEmail($email)
            ->setPassword($password)
            ->setReferer($referer)
            ->setSource($source)
            ->save();

        if (!$user) {
            throw new UserAlreadyExistsException('Fatalna chyba - nepodarilo sa vyrobit user v manageri: ' . Json::encode($this->userBuilder->getErrors()));
        }

        return $user;
    }

    public function loadUserByEmail($email)
    {
        return $this->usersRepository->getByEmail($email);
    }

    public function loadUser(User $user)
    {
        return $this->usersRepository->find($user->getIdentity()->getId());
    }

    /**
     * Zmena hesla
     *
     * @param $userId
     * @param $actualPassword
     * @param $newPassword
     * @return bool
     */
    public function setNewPassword($userId, $actualPassword, $newPassword)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            return false;
        }

        $newPassword = trim($newPassword);

        if (!Passwords::verify($actualPassword, $user[UserAuthenticator::COLUMN_PASSWORD_HASH])) {
            return false;
        }

        $newPassword = Passwords::hash($newPassword);

        $this->changePasswordsLogsRepository->add(
            $user,
            ChangePasswordsLogsRepository::TYPE_CHANGE,
            $user->password,
            $newPassword
        );

        $this->usersRepository->update($user, [
            'password' => $newPassword,
        ]);

        $this->emitter->emit(new UserChangePasswordEvent($user));

        return true;
    }

    public function resetPassword($email, $password)
    {
        $user = $this->usersRepository->findBy('email', $email);
        if (!$user) {
            return false;
        }

        $oldPassword = $user->password;

        $hashedPassword = Passwords::hash($password);

        $this->usersRepository->update($user, [
            'password' => $hashedPassword,
        ]);

        $this->changePasswordsLogsRepository->add(
            $user,
            ChangePasswordsLogsRepository::TYPE_RESET,
            $oldPassword,
            $hashedPassword
        );

        $this->emitter->emit(new UserChangePasswordEvent($user));

        return true;
    }

    /**
     * @param $userId
     *
     * @return bool if user was log out at least on one device
     */
    public function logoutUser($userId): bool
    {
        if (!$userId) {
            return false;
        }
        return $this->accessTokensRepository->removeAllUserTokens($userId) > 0;
    }

    public function suspiciousUser($email)
    {
        $user = $this->usersRepository->findBy('email', $email);
        if (!$user) {
            return false;
        }

        $oldPassword = $user->password;

        $password = $this->passwordGenerator->generatePassword();
        $hashedPassword = Passwords::hash($password);

        $this->usersRepository->update($user, [
            'password' => $hashedPassword,
        ]);

        $this->changePasswordsLogsRepository->add(
            $user,
            ChangePasswordsLogsRepository::TYPE_SUSPICIOUS,
            $oldPassword,
            $hashedPassword
        );

        $this->accessTokensRepository->removeAllUserTokens($user->id);

        $this->emitter->emit(new UserSuspiciousEvent($user, $password));

        return true;
    }

    public function requestResetPassword($email)
    {
        $user = $this->usersRepository->findBy('email', $email);
        if (!$user) {
            return false;
        }

        $passwordResetToken = $this->passwordResetTokensRepository->add($user);

        $this->emitter->emit(new UserChangePasswordRequestEvent($user, $passwordResetToken->token));

        return true;
    }

    public function confirmUser(IRow $user, ?DateTime $date = null)
    {
        if (!$date) {
            $date = new DateTime();
        }
        if (!$user->confirmed_at) {
            $this->usersRepository->update($user, [
                'modified_at' => $date,
                'confirmed_at' => $date,
            ]);

            $tokens = $this->accessTokensRepository->allUserTokens($user->id);
            foreach ($tokens as $token) {
                if (!$this->accessTokensRepository->validCacheToken($token->token, 'register')) {
                    $this->tokenStorage->addToken($token->token, 'register');
                }
            }

            $this->emitter->emit(new UserConfirmedEvent($user));
        }
    }
}
