<?php

namespace Crm\UsersModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\ApplicationModule\Snippet\SnippetRenderer;
use Crm\ApplicationModule\User\DeleteUserData;
use Crm\ApplicationModule\User\DownloadUserData;
use Crm\UsersModule\Forms\ChangePasswordFormFactory;
use Crm\UsersModule\Forms\RequestPasswordFormFactory;
use Crm\UsersModule\Forms\ResetPasswordFormFactory;
use Crm\UsersModule\Forms\UserDeleteFormFactory;
use Crm\UsersModule\Repository\PasswordResetTokensRepository;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\User\ZipBuilder;
use Nette\Application\Responses\FileResponse;
use Nette\Forms\Form;
use Nette\Utils\Json;

class UsersPresenter extends FrontendPresenter
{
    private $changePasswordFormFactory;

    private $downloadUserData;

    private $deleteUserData;

    private $requestPasswordFormFactory;

    private $resetPasswordFormFactory;

    private $passwordResetTokensRepository;

    private $snippetRenderer;

    private $zipBuilder;

    private $userDeleteFormFactory;

    private $userMetaRepository;

    public function __construct(
        ChangePasswordFormFactory $changePasswordFormFactory,
        DownloadUserData $downloadUserData,
        DeleteUserData $deleteUserData,
        RequestPasswordFormFactory $requestPasswordFormFactory,
        ResetPasswordFormFactory $resetPasswordFormFactory,
        PasswordResetTokensRepository $passwordResetTokensRepository,
        SnippetRenderer $snippetRenderer,
        ZipBuilder $zipBuilder,
        UserDeleteFormFactory $userDeleteFormFactory,
        UserMetaRepository $userMetaRepository
    ) {
        parent::__construct();
        $this->changePasswordFormFactory = $changePasswordFormFactory;
        $this->downloadUserData = $downloadUserData;
        $this->deleteUserData = $deleteUserData;
        $this->requestPasswordFormFactory= $requestPasswordFormFactory;
        $this->resetPasswordFormFactory = $resetPasswordFormFactory;
        $this->passwordResetTokensRepository = $passwordResetTokensRepository;
        $this->snippetRenderer = $snippetRenderer;
        $this->zipBuilder = $zipBuilder;
        $this->userDeleteFormFactory = $userDeleteFormFactory;
        $this->userMetaRepository = $userMetaRepository;
    }

    public function renderProfile()
    {
        $this->onlyLoggedIn();
        $this->template->user = $this->getUser();
    }

    public function renderChangePassword()
    {
        $this->onlyLoggedIn();
    }

    public function renderResetPassword($id)
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->getUser()->logout(true);
        }

        if (is_null($id)) {
            $this->redirect('requestPassword');
        }

        if (!$this->passwordResetTokensRepository->isAvailable($id)) {
            $this->flashMessage(
                $this->translator->translate('users.frontend.reset_password.errors.invalid_password_reset_token'),
                "error"
            );
            $this->redirect('requestPassword');
        }
    }

    public function renderRequestPassword()
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect($this->homeRoute);
        }
    }

    public function createComponentChangePasswordForm()
    {
        $form = $this->changePasswordFormFactory->create($this->getUser());
        $this->changePasswordFormFactory->onSuccess = function () {
            $this->flashMessage('Heslo bolo úspešne zmenené');
            $this->redirect($this->homeRoute);
        };
        return $form;
    }

    public function createComponentRequestPasswordForm()
    {
        $form = $this->requestPasswordFormFactory->create();
        $this->requestPasswordFormFactory->onSuccess = function () {
            $this->flashMessage($this->translator->translate('users.frontend.request_password.success'));
            $this->redirect(':Users:Sign:In');
        };
        return $form;
    }

    public function createComponentResetPasswordForm()
    {
        $token = '';
        if (isset($this->params['id'])) {
            $token = $this->params['id'];
        }
        $form = $this->resetPasswordFormFactory->create($token);
        $this->resetPasswordFormFactory->onSuccess = function () {
            $this->flashMessage($this->translator->translate('users.frontend.reset_password.success'));
            $this->redirect(':Users:Sign:In');
        };
        return $form;
    }

    public function renderSettings()
    {
        $this->onlyLoggedIn();
        list($this->template->canBeDeleted, $_) = $this->deleteUserData->canBeDeleted($this->getUser()->getId());
    }

    public function handleDownloadData()
    {
        set_time_limit(120);

        $zip = $this->zipBuilder->getZipFile();
        $fileName = $zip->filename;

        // text data
        $userData = $this->downloadUserData->getData($this->getUser()->getId());
        $zip->addFromString('data.json', Json::encode($userData));

        // file attachments
        foreach ($this->downloadUserData->getAttachments($this->getUser()->getId()) as $attachmentName => $attachmentPath) {
            $zip->addFile($attachmentPath, $attachmentName);
        }

        $zip->close();
        clearstatcache();

        $this->sendResponse(new FileResponse($fileName, 'data.zip', 'application/zip', true));
    }

    public function createComponentUserDeleteForm()
    {
        $form = $this->userDeleteFormFactory->create($this->getUser()->getId());
        $form->onError[] = function (Form $form) {
            $this->flashMessage($form->getErrors()[0], 'error');
        };

        $this->userDeleteFormFactory->onSuccess = function () {
            $this->getUser()->logout(true);
            $this->flashMessage('Vaše konto bolo zmazané.');
            $this->redirect(':Users:Sign:In');
        };

        return $form;
    }
}
