<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class AddressRemovedEvent extends AbstractEvent implements IAddressEvent
{
    private $address;

    public function __construct(ActiveRow $address)
    {
        $this->address = $address;
    }

    public function getAddress(): ActiveRow
    {
        return $this->address;
    }

    public function isAdmin(): bool
    {
        return true;
    }
}
