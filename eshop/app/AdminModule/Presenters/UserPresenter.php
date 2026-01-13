<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Components\UserEditForm\UserEditForm;
use App\AdminModule\Components\UserEditForm\UserEditFormFactory;
use App\Model\Facades\UsersFacade;

final class UserPresenter extends BasePresenter
{
    private UsersFacade $usersFacade;
    private UserEditFormFactory $userEditFormFactory;

    public function __construct(UsersFacade $usersFacade, UserEditFormFactory $userEditFormFactory)
    {
        $this->usersFacade = $usersFacade;
        $this->userEditFormFactory = $userEditFormFactory;
    }

    public function renderDefault(): void
    {
        // Získáváme pole entit User
        $this->template->users = $this->usersFacade->findUsers();
    }

    protected function createComponentUserEditForm(): UserEditForm
    {
        // Vytvoření instance formuláře přes továrnu
        $form = $this->userEditFormFactory->create();

        // Nastavení callbacků
        $form->onFinished[] = function (string $message) {
            $this->flashMessage($message, 'success');
            $this->redirect('default');
        };

        $form->onCancel[] = function () {
            $this->redirect('default');
        };

        $form->onFailed[] = function (string $message) {
            $this->flashMessage($message, 'error');
        };

        return $form;
    }

    public function renderEdit(int $id): void
    {
        $user = $this->usersFacade->getUser($id);
        // Naplnění formuláře daty entity
        $this['userEditForm']->setDefaults($user);
    }
    /**
     * Signál pro smazání uživatele
     * @param int $id
     */
    public function handleDelete(int $id): void
    {
        try {
            $user = $this->usersFacade->getUser($id);
            $this->usersFacade->deleteUser($user);
            $this->flashMessage('Uživatel byl úspěšně smazán.', 'success');
        } catch (\Exception $e) {
            $this->flashMessage('Uživatele se nepodařilo smazat.', 'danger');
        }

        // přesměrování, aby se obnovil seznam
        $this->redirect('this');
    }
}