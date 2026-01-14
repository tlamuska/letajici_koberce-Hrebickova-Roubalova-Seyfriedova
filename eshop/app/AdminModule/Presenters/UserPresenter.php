<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Components\UserEditForm\UserEditForm;
use App\AdminModule\Components\UserEditForm\UserEditFormFactory;
use App\Model\Facades\UsersFacade;

final class UserPresenter extends BasePresenter
{
    private UsersFacade $usersFacade;
    private UserEditFormFactory $userEditFormFactory;
    /** @persistent */
    public string $search = '';

    public function __construct(UsersFacade $usersFacade, UserEditFormFactory $userEditFormFactory)
    {
        $this->usersFacade = $usersFacade;
        $this->userEditFormFactory = $userEditFormFactory;
    }

    /**
     * @throws \Exception
     */
    public function renderDefault(int $page = 1): void
    {
        // 1. Nastavení paginatoru
        $paginator = new \Nette\Utils\Paginator;
        $paginator->setItemCount($this->usersFacade->getUsersCount($this->search)); // Celkový počet
        $paginator->setItemsPerPage(10); // Kolik lidí na stránku
        $paginator->setPage($page);

        // 2. Načtení dat pro aktuální stránku
        $this->template->users = $this->usersFacade->findUsers(
            $this->search ?: null,
            $paginator->getLength(),
            $paginator->getOffset()
        );

        $this->template->paginator = $paginator;
        $this->template->search = $this->search;
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

    public function renderAdd(): void
    {
        // metoda pro vykreslení add.latte
        $this->setView('add');
    }
    protected function createComponentSearchForm(): \Nette\Application\UI\Form
    {
        $form = new \Nette\Application\UI\Form;
        $form->addText('query', 'Hledat:')
            ->setDefaultValue($this->search);
        $form->addSubmit('send', 'Hledat');
        $form->onSuccess[] = function ($form, $values) {
            // přesměrování na začátek s novým vyhledáváním
            $this->redirect('this', ['search' => $values->query, 'page' => 1]);
        };
        return $form;
    }
}