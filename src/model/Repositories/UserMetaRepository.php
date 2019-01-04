<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\UsersModule\Events\UserMetaEvent;
use DateTime;
use League\Event\Emitter;
use Nette\Caching\IStorage;
use Nette\Database\Context;
use Nette\Database\Table\IRow;

class UserMetaRepository extends Repository
{
    private $emitter;

    protected $tableName = 'user_meta';

    public function __construct(Context $database, Emitter $emitter, IStorage $cacheStorage = null)
    {
        parent::__construct($database, $cacheStorage);
        $this->emitter = $emitter;
    }

    public function exists(IRow $user, $key)
    {
        return $this->getTable()->where(['user_id' => $user->id, 'key' => $key])->count('*') > 0;
    }

    public function add(IRow $user, $key, $value, ?DateTime $createdAt = null, $isPublic = false)
    {
        if ($this->exists($user, $key)) {
            $result = $this->getTable()->where(['user_id' => $user, 'key' => $key])
                ->update(['value' => $value, 'updated_at' => new DateTime()]);
            if ($result) {
                $this->emitter->emit(new UserMetaEvent($user->id, $key, $value));
            }
            return $result;
        }
        $result = $this->insert([
            'user_id' => $user->id,
            'key' => $key,
            'value' => $value,
            'is_public' => $isPublic,
            'created_at' => $createdAt ?? new DateTime(),
            'updated_at' => new DateTime(),
        ]);
        if ($result) {
            $this->emitter->emit(new UserMetaEvent($user->id, $key, $value));
        }
        return $result;
    }

    public function setMeta(IRow $user, array $metas, $isPublic = false)
    {
        foreach ($metas as $key => $value) {
            $this->add($user, $key, $value, null, $isPublic);
        }
    }

    public function removeMeta($userId, $key)
    {
        $result = $this->getTable()->where(['user_id' => $userId, 'key' => $key])->delete();
        if ($result) {
            $this->emitter->emit(new UserMetaEvent($userId, $key, null));
        }
        return $result;
    }

    public function userMeta($user)
    {
        return $this->userMetaRows($user)->fetchPairs('key', 'value');
    }

    public function userMetaRows($user)
    {
        if ($user instanceof IRow) {
            $user = $user->id;
        }
        return $this->getTable()->where(['user_id' => $user])->order('key ASC');
    }

    public function usersWithKey($key, $value = null)
    {
        $users = $this->getTable()->where('key = ?', $key);
        if ($value) {
            $users->where('value = ?', $value);
        }
        return $users;
    }
}
