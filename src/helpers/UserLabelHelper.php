<?php

namespace Crm\UsersModule\Helpers;

use Nette\Localization\ITranslator;

class UserLabelHelper
{
    private $translator;

    public function __construct(ITranslator $translator)
    {
        $this->translator = $translator;
    }

    public function process($user)
    {
        $append = '';
        if ($user->is_institution) {
            $append .= " <small>{$this->translator->translate('users.admin.default.institution')}: {$user->institution_name}</small>";
        }

        return $user->email . $append;
    }
}
