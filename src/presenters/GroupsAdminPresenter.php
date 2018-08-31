<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\UsersModule\Forms\GroupFormFactory;
use Crm\UsersModule\Repository\GroupsRepository;

class GroupsAdminPresenter extends AdminPresenter
{
    /** @var  GroupsRepository @inject */
    public $groupsRepository;

    /** @var  GroupFormFactory @inject */
    public $groupFormFactory;

    public function renderDefault()
    {
        $this->template->groups = $this->groupsRepository->all();
    }

    public function renderNew()
    {
    }

    public function renderEdit($id)
    {
        $this->template->group = $this->groupsRepository->find($id);
    }

    public function renderShow($id)
    {
        $this->template->group = $this->groupsRepository->find($id);
    }

    public function createComponentGroupForm()
    {
        $id = null;
        if (isset($this->params['id'])) {
            $id = $this->params['id'];
        }

        $form = $this->groupFormFactory->create($id);
        $this->groupFormFactory->onCreate = function ($group) {
            $this->flashMessage('Skupina bola vytvorená.');
            $this->redirect('show', $group->id);
        };
        $this->groupFormFactory->onUpdate = function ($group) {
            $this->flashMessage('Skupina bola aktualizovná.');
            $this->redirect('show', $group->id);
        };
        return $form;
    }
}
