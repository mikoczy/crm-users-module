<?php

namespace Crm\UsersModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\UsersModule\Auth\Authorizator;
use Crm\UsersModule\Events\UserSignOutEvent;
use League\Event\Emitter;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SignPresenter extends FrontendPresenter
{
    /** @var Emitter */
    private $emitter;

    /** @var  Authorizator */
    private $authorizator;

    /** @persistent */
    public $back;

    public function __construct(Emitter $emitter, Authorizator $authorizator)
    {
        parent::__construct();
        $this->emitter = $emitter;
        $this->authorizator = $authorizator;
    }

    /**
     * Sign-in form factory.
     * @return Form
     */
    protected function createComponentSignInForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $form->addText('username', $this->translator->translate('users.frontend.sign_in.username.label'))
            ->setType('email')
            ->setAttribute('autofocus')
            ->setRequired($this->translator->translate('users.frontend.sign_in.username.required'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.sign_in.username.placeholder'));

        $form->addPassword('password', $this->translator->translate('users.frontend.sign_in.password.label'))
            ->setRequired($this->translator->translate('users.frontend.sign_in.password.required'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.sign_in.password.required'));

        $form->addCheckbox('remember', $this->translator->translate('users.frontend.sign_in.remember'));

        $form->addSubmit('send', $this->translator->translate('users.frontend.sign_in.submit'));

        $form->setDefaults([
            'remember' => true,
        ]);

        $form->onSuccess[] = [$this, 'signInFormSucceeded'];
        return $form;
    }

    public function renderIn()
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect($this->homeRoute);
        }
    }

    public function signInFormSucceeded($form, $values)
    {
        if ($values->remember) {
            $this->getUser()->setExpiration('14 days', false);
        } else {
            $this->getUser()->setExpiration('20 minutes', true);
        }

        try {
            $this->getUser()->login(['username' => $values->username, 'password' => $values->password]);

            $this->getUser()->setAuthorizator($this->authorizator);

            $session = $this->getSession('success_login');
            $session->success = 'success';

            $this->restoreRequest($this->getParameter('back'));
            $this->redirect($this->homeRoute);
        } catch (AuthenticationException $e) {
            $form->addError($e->getMessage());
        }
    }

    public function actionOut()
    {
        $this->emitter->emit(new UserSignOutEvent($this->getUser()));

        $this->getUser()->logout();

        $this->flashMessage($this->translator->translate('users.frontend.sign_in.signed_out'));
        $this->restoreRequest($this->getParameter('back'));

        $this->redirect('in');
    }
}
