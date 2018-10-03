<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\UsersModule\Builder\UserBuilder;
use Crm\UsersModule\DataProvider\UserFormDataProviderInterface;
use Crm\UsersModule\Repository\UserAlreadyExistsException;
use Crm\UsersModule\Repository\UsersRepository;
use Nette;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class UserFormFactory
{
    private $userRepository;

    private $userBuilder;

    private $translator;

    private $dataProviderManager;

    public $onSave;

    public $onUpdate;

    private $onCallback;

    public function __construct(
        UsersRepository $userRepository,
        UserBuilder $userBuilder,
        ITranslator $translator,
        DataProviderManager $dataProviderManager
    ) {
        $this->userRepository = $userRepository;
        $this->userBuilder = $userBuilder;
        $this->translator = $translator;
        $this->dataProviderManager = $dataProviderManager;
    }

    /**
     * @return Form
     */
    public function create($userId)
    {
        $defaults = [];
        $user = null;

        if (isset($userId)) {
            $user = $this->userRepository->find($userId);
            $defaults = $user->toArray();
            unset($defaults['password']);
        }

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->onSuccess[] = [$this, 'formSucceeded'];

        $form->addGroup($this->translator->translate('users.admin.user_form.credentials'));
        $form->addText('email', $this->translator->translate('users.admin.user_form.email.label'))
            ->setRequired($this->translator->translate('users.admin.user_form.email.required'))
            ->setType('email')
            ->setAttribute('placeholder', $this->translator->translate('users.admin.user_form.email.placeholder'));
        $password = $form->addPassword('password', $this->translator->translate('users.admin.user_form.password.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.admin.user_form.password.placeholder'));
        if (!$userId) {
            $password->setRequired($this->translator->translate('users.admin.user_form.password.required'));
        } else {
            $password->setOption('description', $this->translator->translate('users.admin.user_form.password.description'));
        }

        $form->addGroup($this->translator->translate('users.admin.user_form.personal_information'));

        $form->addText('first_name', $this->translator->translate('users.admin.user_form.first_name.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.admin.user_form.first_name.placeholder'));
        $form->addText('last_name', $this->translator->translate('users.admin.user_form.last_name.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.admin.user_form.last_name.placeholder'));


        $form->addGroup($this->translator->translate('users.admin.user_form.institution'));

        $form->addCheckbox('is_institution', $this->translator->translate('users.admin.user_form.is_institution'));
        $form->addText('institution_name', $this->translator->translate('users.admin.user_form.institution_name.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.admin.user_form.institution_name.placeholder'))
            ->addConditionOn($form['is_institution'], Form::EQUAL, true)
                ->setRequired($this->translator->translate('users.admin.user_form.institution_name.required'));

        /** @var UserFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.user_form', UserFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'user' => $user]);
        }

        $form->addGroup($this->translator->translate('users.admin.user_form.other'));

        $form->addText('ext_id', $this->translator->translate('users.admin.user_form.external_id.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.admin.user_form.external_id.placeholder'))
            ->addCondition(Form::FILLED)
            ->addRule(Form::INTEGER, $this->translator->translate('users.admin.user_form.external_id.integer'));
        $form->addSelect('role', $this->translator->translate('users.admin.user_form.role.label'), [
            UsersRepository::ROLE_USER => $this->translator->translate('users.admin.user_form.role.user'),
            UsersRepository::ROLE_ADMIN => $this->translator->translate('users.admin.user_form.role.admin'),
        ]);
        $form->addCheckbox('active', $this->translator->translate('users.admin.user_form.active'));

        $form->addSubmit('send', $this->translator->translate('users.admin.user_form.submit'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('users.admin.user_form.submit'));

        if ($userId) {
            $form->addHidden('user_id', $userId);
        }

        $form->setDefaults($defaults);
        $form->onSuccess[] = [$this, 'callback'];

        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $user = null;

        $values = clone($values);
        foreach ($values as $i => $item) {
            if ($item instanceof Nette\Utils\ArrayHash) {
                unset($values[$i]);
            }
        }

        if (isset($values['user_id'])) {
            $userId = $values['user_id'];
            unset($values['user_id']);
            try {
                if (isset($values['password'])) {
                    if (strlen($values['password']) > 0) {
                        $values['password'] = Nette\Security\Passwords::hash($values['password']);
                    } else {
                        unset($values['password']);
                    }
                }

                $user = $this->userRepository->find($userId);
                $this->userRepository->update($user, $values);
                $this->onCallback = function () use ($form, $user) {
                    $this->onUpdate->__invoke($form, $user);
                };
            } catch (UserAlreadyExistsException $e) {
                $form['email']->addError($e->getMessage());
            }
        } else {
            $user = $this->userBuilder->createNew()
                ->setEmail($values['email'])
                ->setPassword($values['password'])
                ->setFirstName($values['first_name'])
                ->setLastName($values['last_name'])
                ->setRole($values['role'])
                ->setActive($values['active'])
                ->setExtId(!empty($values['ext_id']) ? intval($values['ext_id']) : null)
                ->setInstitutionName($values['institution_name'])
                ->setIsInstitution($values['is_institution'])
                ->setSource('backend')
                ->save();

            if (!$user) {
                $form['email']->addError(implode("\n", $this->userBuilder->getErrors()));
            } else {
                $this->onCallback = function () use ($form, $user) {
                    $this->onSave->__invoke($form, $user);
                };
            }
        }
    }

    public function callback()
    {
        if ($this->onCallback) {
            $this->onCallback->__invoke();
        }
    }
}
