<?php

namespace Crm\UsersModule\User;

use Crm\ApplicationModule\User\UserDataProviderInterface;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\DateTime;

class BasicUserDataProvider implements UserDataProviderInterface
{
    private $usersRepository;

    public function __construct(UsersRepository $usersRepository)
    {
        $this->usersRepository = $usersRepository;
    }

    public static function identifier(): string
    {
        return 'basic';
    }

    public function data($userId)
    {
        $user = $this->usersRepository->find($userId);

        if (!$user || !$user->active) {
            return [];
        }

        return [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ];
    }

    public function download($userId)
    {
        $user = $this->usersRepository->find($userId);

        if (!$user || !$user->active) {
            return [];
        }

        return [
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'public_name' => $user->public_name,
            'created_at' => $user->created_at->format(\DateTime::RFC3339), //confirmed_at?
            'last_sign_in_up' => $user->last_sign_in_ip, //?
            'current_sign_in_up' => $user->current_sign_in_ip, //?
        ];
    }

    public function downloadAttachments($userId)
    {
        return [];
    }

    public function protect($userId): array
    {
        return [];
    }

    public function delete($userId, $protectedData = [])
    {
        $user = $this->usersRepository->find($userId);
        $now = new DateTime();
        $GDPRTemplateUser = [
            // anonymize
            'email' => 'GDPR_removal@' . $now->getTimestamp() . rand(),
            'first_name' => 'GDPR Removal',
            'last_name' => 'GDPR Removal',
            'public_name' => 'GDPR Removal',
            'password' => 'GDPR Removal',
            'ext_id' => null,
            'last_sign_in_ip' => 'GDPR Removal',
            'current_sign_in_ip' => 'GDPR Removal',
            'referer' => 'GDPR Removal',
            'institution_name' => 'GDPR Removal',

            // deactivate & mark as deleted
            'active' => false,
            'deleted_at' => $now,

            // TODO: probably not needed columns? there data are in address table now
            'address' => 'GDPR Removal',
            'phone_number' => 'GDPR Removal',
        ];

        $this->usersRepository->update($user, $GDPRTemplateUser);
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
