<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\UsersModule\Auth\Repository\AdminAccessRepository;
use Crm\UsersModule\Auth\Repository\AdminGroupsRepository;
use Crm\UsersModule\Forms\AdminGroupFormFactory;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AdminGroupAdminPresenter extends AdminPresenter
{
    /** @var  AdminGroupsRepository @inject */
    public $adminGroupsRepository;

    /** @var  AdminGroupFormFactory @inject */
    public $adminGroupFormFactory;

    /** @var  AdminAccessRepository @inject */
    public $adminAccessRepository;

    public function renderDefault()
    {
        $this->template->groups = $this->adminGroupsRepository->all();
    }

    public function renderNew()
    {
    }

    public function renderEdit($id)
    {
        $this->template->group = $this->adminGroupsRepository->find($id);
    }

    public function renderShow($id)
    {
        $this->template->group = $this->adminGroupsRepository->find($id);
        $this->template->accesses = $this->adminAccessRepository->all();
    }

    public function createComponentGroupForm()
    {
        $id = null;
        if (isset($this->params['id'])) {
            $id = $this->params['id'];
        }

        $form = $this->adminGroupFormFactory->create($id);
        $this->adminGroupFormFactory->onCreate = function ($group) {
            $this->flashMessage($this->translator->translate('users.admin.admin_group_admin.group_created'));
            $this->redirect('show', $group->id);
        };
        $this->adminGroupFormFactory->onUpdate = function ($group) {
            $this->flashMessage($this->translator->translate('users.admin.admin_group_admin.group_updated'));
            $this->redirect('show', $group->id);
        };
        return $form;
    }

    public function createComponentAccessForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $accesses = $this->adminAccessRepository->all();

        $group = $this->adminGroupsRepository->find($this->params['id']);

        $defaults = [];
        foreach ($group->related('admin_groups_access') as $adminGroupAccess) {
            $defaults['access_' . $adminGroupAccess->admin_access_id] = true;
        }

        $formGroup = false;
        foreach ($accesses as $access) {
            $parts = explode(':', $access->resource);
            if (!isset($parts[0])) {
                continue;
            }
            if ($formGroup != $parts[0]) {
                $form->addGroup($parts[0]);
            }
            $permission = $access->resource . ':' . $access->action;
            $form->addCheckbox('access_' . $access->id, ' ' . $permission);

            $formGroup = $parts[0];
        }

        $form->setDefaults($defaults);

        $form->addGroup();

        $form->addSubmit('submit', $this->translator->translate('users.admin.admin_group_admin.submit'));
        $form->onSuccess[] = function ($form, $values) use ($group) {
            $group->related('admin_groups_access')->delete();

            $accesses = [];
            foreach ($values as $key => $value) {
                if (!$value) {
                    continue;
                }
                $accessId = str_replace('access_', '', $key);
                $accesses[] = $accessId;
            }
            $group->related('admin_groups_access')->where(['NOT admin_access_id' => $accesses])->delete();
            foreach ($accesses as $accessId) {
                $group->related('admin_groups_access')->insert([
                    'admin_access_id' => $accessId,
                    'created_at' => new DateTime(),
                ]);
            }

            $this->flashMessage($this->translator->translate('users.admin.admin_group_admin.rights_updated'));
            $this->redirect('show', $group->id);
        };
        return $form;
    }
}
