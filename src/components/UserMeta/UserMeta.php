<?php

namespace Crm\UsersModule\Components;

use Crm\ApplicationModule\Widget\WidgetInterface;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class UserMeta extends Control implements WidgetInterface
{
    private $templateName = 'user_meta.latte';

    private $userMetaRepository;

    private $usersRepository;

    private $user;

    private $userId;

    public function __construct(UserMetaRepository $userMetaRepository, UsersRepository $usersRepository)
    {
        parent::__construct();
        $this->userMetaRepository = $userMetaRepository;
        $this->usersRepository = $usersRepository;
    }

    public function header($id = '')
    {
        $header = 'Vlastné nastavenia';
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }
        return $header;
    }

    public function identifier()
    {
        return 'usermeta';
    }

    private function getUser($id)
    {
        if (!$this->user) {
            $this->user = $this->usersRepository->find($id);
        }
        return $this->user;
    }

    public function render($id)
    {
        $this->userId = $id;
        $meta = $this->userMetaRepository->userMetaRows($this->getUser($id));
        $this->template->meta = $meta;
        $this->template->id = $id;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    private $totalCount = null;

    public function handleDelete($id, $key)
    {
        $this->userMetaRepository->removeMeta($id, $key);
        $this->presenter->flashMessage('Hodnota bola zmazaná');
        $this->presenter->redirect('UsersAdmin:Show', $id);
    }

    protected function createComponentMetaForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->addText('key', 'Kľúč');
        $form->addText('value', 'Hodnota');
        $form->addCheckbox('is_public', 'Verejné');
        $form->addHidden('user_id', $this->userId);
        $form->addSubmit('submit', 'Ulož');
        $form->onSubmit[] = function ($form) {
            $values = $form->getValues();
            $this->userMetaRepository->setMeta(
                $this->getUser($values['user_id']),
                [$values['key'] => $values['value']],
                $values->is_public
            );
            $this->presenter->flashMessage('Hodnota bola pridaná');
            $this->presenter->redirect('UsersAdmin:Show', $values['user_id']);
        };
        return $form;
    }

    private function totalCount($id)
    {
        if ($this->totalCount == null) {
            $this->totalCount = count($this->userMetaRepository->userMeta($id));
        }
        return $this->totalCount;
    }
}
