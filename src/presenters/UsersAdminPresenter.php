<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\User\DeleteUserData;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Components\Widgets\DetailWidgetFactoryInterface;
use Crm\UsersModule\DataProvider\FilterUsersFormDataProviderInterface;
use Crm\UsersModule\DataProvider\FilterUsersSelectionDataProviderInterface;
use Crm\UsersModule\Events\AddressRemovedEvent;
use Crm\UsersModule\Forms\AdminUserGroupFormFactory;
use Crm\UsersModule\Forms\UserFormFactory;
use Crm\UsersModule\Forms\UserGroupsFormFactory;
use Crm\UsersModule\Forms\UserNoteFormFactory;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repository\GroupsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class UsersAdminPresenter extends AdminPresenter
{
    /** @persistent */
    public $text;

    /** @persistent */
    public $group;

    /** @persistent */
    public $source;

    private $usersRepository;

    private $factory;

    private $groupsRepository;

    private $userGroupsFormFactory;

    private $adminUserGroupsFormFactory;

    private $userNoteFormFactory;

    private $addressesRepository;

    private $deleteUserData;

    private $dataProviderManager;

    private $userManager;

    private $changePasswordsLogsRepository;

    private $emitter;

    public function __construct(
        UsersRepository $usersRepository,
        UserFormFactory $userFormFactory,
        GroupsRepository $groupsRepository,
        UserGroupsFormFactory $userGroupsFormFactory,
        AdminUserGroupFormFactory $adminUserGroupsFormFactory,
        UserNoteFormFactory $userNoteFormFactory,
        AddressesRepository $addressesRepository,
        DeleteUserData $deleteUserData,
        DataProviderManager $dataProviderManager,
        UserManager $userManager,
        ChangePasswordsLogsRepository $changePasswordsLogsRepository,
        Emitter $emitter
    ) {
        parent::__construct();
        $this->usersRepository = $usersRepository;
        $this->factory = $userFormFactory;
        $this->groupsRepository = $groupsRepository;
        $this->userGroupsFormFactory = $userGroupsFormFactory;
        $this->adminUserGroupsFormFactory = $adminUserGroupsFormFactory;
        $this->userNoteFormFactory = $userNoteFormFactory;
        $this->addressesRepository = $addressesRepository;
        $this->deleteUserData = $deleteUserData;
        $this->dataProviderManager = $dataProviderManager;
        $this->userManager = $userManager;
        $this->changePasswordsLogsRepository = $changePasswordsLogsRepository;
        $this->emitter = $emitter;
    }

    public function startup()
    {
        parent::startup();
        $this->text = isset($this->params['text']) ? $this->params['text'] : null;
    }

    public function renderDefault()
    {
        $filteredCount = $this->getFilteredUsers(true)->count('distinct(users.id)');

        $users = $this->getFilteredUsers();

        $vp = new VisualPaginator();
        $this->addComponent($vp, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($filteredCount);
        $paginator->setItemsPerPage($this->onPage);
        $this->template->filteredCount = $filteredCount;
        $this->template->vp = $vp;
        $this->template->users = $users->limit($paginator->getLength(), $paginator->getOffset());
        $this->template->totalUsers = $this->usersRepository->totalCount();
    }

    private function getFilteredUsers($onlyCount = false)
    {
        $users = $this->usersRepository
            ->all($this->text)
            ->select('users.*')
            ->group('users.id');

        $where = [];

        if (isset($this->params['group'])) {
            $where[':user_groups.group_id'] = intval($this->params['group']);
        }
        if (isset($this->params['source'])) {
            $where['users.source'] = $this->params['source'];
        }

        /** @var FilterUsersSelectionDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.filter_users_selection', FilterUsersSelectionDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $users = $provider
                ->provide(['selection' => $users, 'params' => $this->params, 'only_count' => $onlyCount]);
        }

        if (count($where) > 0) {
            $users->where($where);
        }
        return $users;
    }

    public function renderShow($id)
    {
        $user = $this->usersRepository->find($id);
        if (!$user) {
            throw new Nette\Application\BadRequestException();
        }
        $this->template->user = $user;
        $this->template->invoiceAddress = $this->addressesRepository->address($user, 'invoice');
        $this->template->printAddresses = array_filter($this->addressesRepository->addresses($user), function ($item) {
            return $item->type != 'invoice';
        });

        $this->template->lastSuspicious = $this->changePasswordsLogsRepository->lastUserLog($user->id, ChangePasswordsLogsRepository::TYPE_SUSPICIOUS);


        $this->template->canEditRoles = $this->getUser()->isAllowed('Users:AdminGroupAdmin', 'edit');
    }

    public function renderAbusive()
    {
        $startTime = new DateTime();
        $endTime = new DateTime();
        $startTime->modify('- 2 months');

        if (isset($this->params['start_time'])) {
            $startTime = $startTime->createFromFormat('Y-m-d', $this->params['start_time']);
        }
        if (isset($this->params['end_time'])) {
            $endTime = $endTime->createFromFormat('Y-m-d', $this->params['end_time']);
        }

        $loginCount = isset($this->params['login_count']) ? $this->params['login_count'] : 25;
        $deviceCount = isset($this->params['device_count']) ? $this->params['device_count'] : 1;
        $sortBy = isset($this->params['sort_by']) ? $this->params['sort_by'] : 'device_count';

        $getParams = [
            'login_count' => $loginCount,
            'device_count' => $deviceCount,
            'start_time' => $startTime->format('Y-m-d'),
            'end_time' => $endTime->format('Y-m-d'),
        ];

        $this->template->sortByTokenCountLink = $this->link('UsersAdmin:Abusive', array_merge($getParams, ['sort_by' => 'token_count']));
        $this->template->sortByDeviceCountLink = $this->link('UsersAdmin:Abusive', array_merge($getParams, ['sort_by' => 'device_count']));

        $this->template->startTime = $startTime->format('Y-m-d');
        $this->template->endTime = $endTime->format('Y-m-d');
        $this->template->loginCount = $loginCount;
        $this->template->loginCountRanges = [10,25,50,100];
        $this->template->deviceCount = $deviceCount;
        $this->template->deviceCountRanges = [1,5,10,25,50];

        $users = $this->usersRepository->getAbusiveUsers($startTime, $endTime, $loginCount, $deviceCount, $sortBy)->fetchAll();
        $this->template->abusers = $users;
    }

    public function renderEdit($id)
    {
        $user = $this->usersRepository->find($id);
        if (!$user) {
            throw new Nette\Application\BadRequestException();
        }
        $this->template->user = $user;
    }

    public function renderNew()
    {
    }

    public function handleLogOut($userId)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new Nette\Application\BadRequestException();
        }

        $this->userManager->logoutUser($userId);
        $this->presenter->flashMessage('Užívatel bol odhlásený na všetkých zariadeniach');
        $this->redirect('show', $userId);
    }

    public function createComponentUserForm()
    {
        $id = null;
        if (isset($this->params['id'])) {
            $id = $this->params['id'];
        }

        $form = $this->factory->create($id);
        $this->factory->onSave = function ($form, $user) {
            $this->flashMessage($this->translator->translate('users.admin.user_form.user_created'));
            $this->redirect('UsersAdmin:Show', $user->id);
        };
        $this->factory->onUpdate = function ($form, $user) {
            $this->flashMessage($this->translator->translate('users.admin.user_form.user_updated'));
            $this->redirect('UsersAdmin:Show', $user->id);
        };
        return $form;
    }

    public function createComponentAdminFilterForm()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->addText('text', $this->translator->translate('users.admin.admin_filter_form.text.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.admin.admin_filter_form.text.placeholder'))
            ->setAttribute('autofocus');
        $form->addSelect('group', '', $this->groupsRepository->all()->fetchPairs('id', 'name'))
            ->setPrompt($this->translator->translate('users.admin.admin_filter_form.group'));
        $form->addSelect('source', '', $this->usersRepository->getUserSources())
            ->setPrompt($this->translator->translate('users.admin.admin_filter_form.source'));

        /** @var FilterUsersFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.filter_users_form', FilterUsersFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form]);
        }

        $form->addSubmit('send', $this->translator->translate('users.admin.admin_filter_form.submit'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('users.admin.admin_filter_form.submit'));
        $presenter = $this;
        $form->addSubmit('cancel', $this->translator->translate('users.admin.admin_filter_form.cancel_filter'))->onClick[] = function () use ($presenter) {
            $presenter->redirect('UsersAdmin:Default', ['text' => '']);
        };
        $export = $form->addSubmit('export', $this->translator->translate('users.admin.admin_filter_form.export'));
        $export->getControlPrototype()->setName('button')->setHtml('<i class="fa fa-external-link"></i> ' . $this->translator->translate('users.admin.admin_filter_form.export'));
        $export->onClick[] = function () use ($presenter) {
            $presenter->redirect('UsersAdmin:Export');
        };
        $form->onSuccess[] = [$this, 'adminFilterSubmited'];

        $form->setDefaults((array)$this->params);
        return $form;
    }

    public function adminFilterSubmited($form, $values)
    {
        $this->redirect('Default', (array) $values);
    }

    public function createComponentUserGroupsForm()
    {
        if (!isset($this->params['id'])) {
            return null;
        }

        $form = $this->userGroupsFormFactory->create($this->params['id']);
        $this->userGroupsFormFactory->onAddedUserToGroup = function ($form, $group, $user) {
            $this->flashMessage($this->translator->translate('users.admin.user_groups_form.user_added') . ' ' . $group->name);
            $this->redirect('UsersAdmin:Show', $user->id);
        };
        $this->userGroupsFormFactory->onRemovedUserFromGroup = function ($form, $group, $user) {
            $this->flashMessage($this->translator->translate('users.admin.user_groups_form.user_removed') . ' ' . $group->name);
            $this->redirect('UsersAdmin:Show', $user->id);
        };

        return $form;
    }

    public function createComponentAdminUserGroupsForm()
    {
        if (!isset($this->params['id'])) {
            return null;
        }

        $user = $this->getUser();

        $form = $this->adminUserGroupsFormFactory->create($this->params['id']);
        $this->adminUserGroupsFormFactory->authorize = function () use ($user) {
            return $user->isAllowed('Users:AdminGroupAdmin', 'edit');
        };
        $this->adminUserGroupsFormFactory->onAddedUserToGroup = function ($form, $group, $user) {
            $this->flashMessage($this->translator->translate('users.admin.user_groups_form.user_added') . ' ' . $group->name);
            $this->redirect('UsersAdmin:Show', $user->id);
        };
        $this->adminUserGroupsFormFactory->onRemovedUserFromGroup = function ($form, $group, $user) {
            $this->flashMessage($this->translator->translate('users.admin.user_groups_form.user_removed') . ' ' . $group->name);
            $this->redirect('UsersAdmin:Show', $user->id);
        };

        return $form;
    }

    public function handleChangeActivation($userId)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new Nette\Application\BadRequestException();
        }

        $this->usersRepository->toggleActivation($user);

        $this->flashMessage($this->translator->translate('users.admin.change_activation.activated'));
        $this->redirect('UsersAdmin:Show', $user->id);
    }

    public function handleDeleteUser($id)
    {
        $user = $this->usersRepository->find($id);
        if (!$user) {
            throw new Nette\Application\BadRequestException();
        }

        // checking if customer can be deleted (e.g. due to active subscription within last three months - customer claim managment)
        list($canBeDeleted, $errors) = $this->deleteUserData->canBeDeleted($user->id);
        if ($canBeDeleted) {
            $this->deleteUserData->deleteData($user->id);
            $this->flashMessage($this->translator->translate('users.admin.delete_user.deleted'));
        } else {
            $this->flashMessage("<br/>" . implode("<br/>", $errors), 'error');
        }
        $this->redirect('UsersAdmin:Show', $user->id);
    }

    public function handleSuspicious($id)
    {
        $user = $this->usersRepository->find($id);
        if (!$user) {
            throw new Nette\Application\BadRequestException();
        }

        $this->userManager->suspiciousUser($user->email);
        $this->flashMessage("OK"); // todo preklady
        $this->redirect('show', $user->id);
    }

    public function renderExport()
    {
        $this->getHttpResponse()->addHeader('Content-Type', 'application/csv');
        $this->getHttpResponse()->addHeader('Content-Disposition', 'attachment; filename=export.csv');

        $this->template->users = $this->getFilteredUsers()->limit(100000);
    }

    protected function createComponentDetailWidget(DetailWidgetFactoryInterface $factory)
    {
        $control = $factory->create();
        return $control;
    }

    public function createComponentUserNoteForm()
    {
        $userRow = $this->usersRepository->find($this->params['id']);
        $form = $this->userNoteFormFactory->create($userRow);
        $presenter = $this;
        $this->userNoteFormFactory->onUpdate = function ($form, $user) use ($presenter) {
            $presenter->flashMessage($this->translator->translate('users.admin.user_note_form.note_updated'));
            $presenter->redirect('UsersAdmin:Show', $user->id);
        };
        return $form;
    }

    public function handleRemoveAddress($addressId)
    {
        $address = $this->addressesRepository->find($addressId);
        $this->addressesRepository->softDelete($address);
        $this->emitter->emit(new AddressRemovedEvent($address));
    }
}
