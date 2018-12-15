<?php

namespace Crm\UsersModule\Components;

use Crm\ApiModule\Repository\UserSourceAccessesRepository;
use Crm\ApplicationModule\Widget\WidgetInterface;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Nette\Application\UI\Control;
use Nette\Utils\DateTime;

class UserLoginAttempts extends Control implements WidgetInterface
{
    private $templateName = 'user_login_attempts.latte';

    private $loginAttemptsRepository;

    private $userSourceAccessesRepository;

    public function __construct(
        LoginAttemptsRepository $loginAttemptsRepository,
        UserSourceAccessesRepository $userSourceAccessesRepository
    ) {
        parent::__construct();
        $this->loginAttemptsRepository = $loginAttemptsRepository;
        $this->userSourceAccessesRepository = $userSourceAccessesRepository;
    }

    public function header($id = '')
    {
        $header = 'Posledné prihlásenia';
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }

        $today = $this->loginAttemptsRepository->lastUserAttempt($id)->where([
            'created_at > ?' => DateTime::from(strtotime('today 00:00')),
            'status' => $this->loginAttemptsRepository->okStatuses(),
        ])->count('*');
        if ($today) {
            $header .= ' <span class="label label-warning">Dnes</span>';
        }

        return $header;
    }

    public function identifier()
    {
        return 'userloginattempts';
    }

    public function render($id)
    {
        $this->template->lastSignInAttempts = $this->loginAttemptsRepository->lastUserAttempt($id);
        $this->template->isOkStatus = function ($status) {
            return $this->loginAttemptsRepository->okStatus($status);
        };
        $this->template->totalSignInAttempts = $this->totalCount($id);

        $this->template->totalUserIps = $this->loginAttemptsRepository->userIps($id)->count();
        $this->template->totalUserAgents = $this->loginAttemptsRepository->userAgents($id)->count();

        $this->template->mobileUserIps = $this->loginAttemptsRepository->userIps($id)->where(['source != ?' => 'web'])->count();
        $this->template->mobileUserAgents = $this->loginAttemptsRepository->userAgents($id)->where(['source != ?' => 'web'])->count();

        $this->template->webUserIps = $this->loginAttemptsRepository->userIps($id)->where(['source' => 'web'])->count();
        $this->template->webUserAgents = $this->loginAttemptsRepository->userAgents($id)->where(['source' => 'web'])->count();

        $this->template->userSourceAccesses = $this->userSourceAccessesRepository->getTable()->where(['user_id' => $id]);

        $this->template->id = $id;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    private $totalCount = null;

    private function totalCount($id)
    {
        if ($this->totalCount == null) {
            $this->totalCount = $this->loginAttemptsRepository->totalUserAttempts($id);
        }
        return $this->totalCount;
    }
}
