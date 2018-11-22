<?php

namespace Crm\UsersModule\Components;

use Crm\ApplicationModule\Widget\WidgetInterface;
use Crm\UsersModule\Repository\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repository\PasswordResetTokensRepository;
use Nette\Application\UI\Control;

class UserPasswordChanges extends Control implements WidgetInterface
{
    private $templateName = 'user_password_changes.latte';

    private $changePasswordsLogsRepository;

    private $passwordResetTokensRepository;

    public function __construct(
        ChangePasswordsLogsRepository $changePasswordsLogsRepository,
        PasswordResetTokensRepository $passwordResetTokensRepository
    ) {
        parent::__construct();
        $this->changePasswordsLogsRepository = $changePasswordsLogsRepository;
        $this->passwordResetTokensRepository = $passwordResetTokensRepository;
    }

    public function header($id = '')
    {
        $header = 'Zmeny hesla';
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }
        return $header;
    }

    public function identifier()
    {
        return 'userpasswordchanges';
    }

    public function render($id)
    {
        $this->template->changePasswordLogs = $this->changePasswordsLogsRepository->lastUserLogs($id);
        $this->template->totalPasswordChanges = $this->totalCount($id);
        $this->template->passwordResetTokens = $this->passwordResetTokensRepository->userTokens($id);
        $this->template->id = $id;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    private $totalCount = null;

    private function totalCount($id)
    {
        if ($this->totalCount == null) {
            $this->totalCount = $this->changePasswordsLogsRepository->totalUserLogs($id);
        }
        return $this->totalCount;
    }
}
