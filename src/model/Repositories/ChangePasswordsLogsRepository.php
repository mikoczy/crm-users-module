<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Utils\DateTime;

class ChangePasswordsLogsRepository extends Repository
{
    const TYPE_CHANGE = 'change';
    const TYPE_RESET = 'reset';
    const TYPE_FORCE = 'force';
    const TYPE_SUSPICIOUS = 'suspicious';

    protected $tableName = 'change_passwords_logs';

    public function add($user, $type, $oldPassword, $newPassword)
    {
        return $this->insert([
            'user_id' => $user->id,
            'created_at' => new \DateTime(),
            'type' => $type,
            'from_password' => $oldPassword,
            'to_password' => $newPassword,
        ]);
    }

    public function totalUserLogs($userId)
    {
        return $this->userLogs($userId)->count('*');
    }

    public function lastUserLogs($userId)
    {
        return $this->userLogs($userId)->order('created_at DESC')->limit(100);
    }

    private function userLogs($userId)
    {
        return $this->getTable()->where(['user_id' => $userId]);
    }

    public function removeOldData($from): void
    {
        $records = $this->getTable()
            ->select('change_passwords_logs.id')
            ->where('user.active = ?', false)
            ->where('change_passwords_logs.created_at < ?', DateTime::from($from));

        if ($records->fetchAll()) {
            $this->getTable()->where('id IN (?)', $records)->delete();
        }
    }
}
