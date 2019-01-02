<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class AddressesMetaRepository extends Repository
{
    protected $tableName = 'addresses_meta';

    /**
     * @param ActiveRow $address
     * @param ActiveRow|null $addressChangeRequest
     * @param string $key
     * @param string $value
     * @param bool $override
     * @return \Nette\Database\Table\IRow
     * @throws \Exception
     */
    public function add(ActiveRow $address, ?Activerow $addressChangeRequest, $key, $value, $override = true)
    {
        if ($override && $this->exists($address, $addressChangeRequest, $key)) {
            $meta = $this->getTable()->where([
                'address_id' => $address->id,
                'address_change_request_id' => $addressChangeRequest->id ?? null,
                'key' => $key,
            ])->fetch();
            if ($meta) {
                if ($meta->value !== $value) {
                    $this->update($meta, [
                        'value' => $value,
                        'updated_at' => new \DateTime(),
                    ]);
                }
                return $meta;
            }
        }
        return $this->insert([
            'address_id' => $address->id,
            'address_change_request_id' => $addressChangeRequest->id,
            'key' => $key,
            'value' => $value,
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
        ]);
    }


    /**
     * @param ActiveRow $address
     * @param array $keys
     * @return Selection
     */
    public function values(ActiveRow $address, ?Activerow $addressChangeRequest, ...$keys)
    {
        $values = $this->getTable()->where([
            'address_id' => $address->id,
            'address_change_request_id' => $addressChangeRequest->id ?? null,
        ]);
        if (!empty($keys)) {
            $values->where(['key' => $keys]);
        }
        return $values;
    }

    /**
     * @param ActiveRow $address
     * @param ActiveRow|null $addressChangeRequest
     * @param string $key
     * @return bool
     */
    public function exists(ActiveRow $address, ?Activerow $addressChangeRequest, $key)
    {
        return $this->getTable()->where([
                'address_id' => $address->id,
                'address_change_request_id' => $addressChangeRequest->id ?? null,
                'key' => $key,
            ])->count('*') > 0;
    }

    /**
     * @param ActiveRow $address
     * @param ActiveRow|null $addressChangeRequest
     * @param string $key
     * @return int
     */
    public function remove(ActiveRow $address, ?Activerow $addressChangeRequest, $key)
    {
        return $this->getTable()->where([
            'address_id' => $address->id,
            'address_change_request_id' => $addressChangeRequest->id ?? null,
            'key' => $key,
        ])->delete();
    }
}
