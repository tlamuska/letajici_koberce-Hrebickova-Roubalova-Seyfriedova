<?php

namespace App\AdminModule\Presenters;

use App\Model\Facades\UsersFacade;

final class UserPresenter extends BasePresenter
{
    private UsersFacade $usersFacade;

    public function __construct(UsersFacade $usersFacade)
    {
        $this->usersFacade = $usersFacade;
    }

    public function renderDefault(): void
    {
        // Získáváme pole entit User
        $this->template->users = $this->usersFacade->findUsers();
    }
}