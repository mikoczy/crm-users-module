<?php

namespace Crm\UsersModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\Selection;
use Nette\Application\UI\Form;

interface UserFormDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array $params
     * @return Selection
     */
    public function provide(array $params): Form;
}
