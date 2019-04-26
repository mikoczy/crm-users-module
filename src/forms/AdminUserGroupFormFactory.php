<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\Forms\BootstrapSmallInlineFormRenderer;
use Crm\UsersModule\Auth\Repository\AdminGroupsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Nette\Utils\DateTime;

class AdminUserGroupFormFactory
{
    protected $usersRepository;

    protected $adminGroupsRepository;

    protected $translator;

    public $onAddedUserToGroup;

    public $onRemovedUserFromGroup;

    public $authorize;

    public function __construct(
        UsersRepository $usersRepository,
        AdminGroupsRepository $adminGroupsRepository,
        ITranslator $translator
    ) {
        $this->usersRepository = $usersRepository;
        $this->adminGroupsRepository = $adminGroupsRepository;
        $this->translator = $translator;
    }

    /**
     * @param @userId
     * @return Form
     * @throws BadRequestException
     */
    public function create($userId)
    {
        $defaults = [];

        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException();
        }

        $form = new Form;

        $form->setRenderer(new BootstrapSmallInlineFormRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $userGroups = $user->related('admin_user_groups');

        $userGroupsIds = [];
        if ($userGroups->count('*') > 0) {
            $factory = $this;
            foreach ($userGroups as $userGroup) {
                $group = $userGroup->group;
                $userGroupsIds[] = $group->id;
                $accesses = $group->related('admin_groups_access')->count('*');
                $button = $form->addSubmit('group_' . $group->id, $group->name);
                $button->setAttribute('class', 'btn btn-default btn-blxock btn-sm');
                $button->getControlPrototype()->setName('button')->setHtml('<i class="fa fa-times"></i> ' . $group->name . ' (' . $accesses . ')');
                $button->onClick[] = function () use ($factory, $group, $user, $form) {
                    $user->related('admin_user_groups')->where(['admin_group_id' => $group->id])->delete();
                    $factory->onRemovedUserFromGroup->__invoke($form, $group, $user);
                    return false;
                };
            }
        }

        $groups = $this->adminGroupsRepository->all()->fetchPairs('id', 'name');

        $groupsArray = [];
        foreach ($groups as $groupId => $groupName) {
            if (!in_array($groupId, $userGroupsIds)) {
                $groupsArray[$groupId] = $groupName;
            }
        }

        if (count($groupsArray) > 0) {
            $form->addSelect('group_id', '', $groupsArray)
                ->setPrompt('users.form.admin_user_group.group_id.prompt');

            $form->addSubmit('send', 'users.form.admin_user_group.send')
                ->setAttribute('class', 'btn btn-primary')
                ->getControlPrototype()
                ->setName('button')
                ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('users.form.admin_user_group.send'));
        }

        $form->addHidden('user_id', $userId);

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        if (!$this->authorize->__invoke()) {
            $form->addError('users.form.admin_user_group.error.insufficient_rights');
            return;
        }

        $group = $this->adminGroupsRepository->find($values['group_id']);
        if (!$group) {
            $form['group_id']->addError('users.form.admin_user_group.error.no_group');
            return;
        }
        $user = $this->usersRepository->find($values['user_id']);
        if (!$user) {
            $form['user_id']->addError('users.form.admin_user_group.error.no_user');
            return;
        }

        $result = $user->related('admin_user_groups')->insert([
            'admin_group_id' => $group->id,
            'user_id' => $user->id,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
        if ($result) {
            $this->onAddedUserToGroup->__invoke($form, $group, $user);
        }
    }
}
