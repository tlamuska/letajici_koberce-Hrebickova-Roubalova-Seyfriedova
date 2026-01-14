<?php

namespace App\AdminModule\Components\UserEditForm;

use App\Model\Entities\User;
use App\Model\Facades\UsersFacade;
use Nette;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\SubmitButton;
use Nette\Security\Passwords;
use Nette\SmartObject;
use Nextras\FormsRendering\Renderers\Bs4FormRenderer;
use Nextras\FormsRendering\Renderers\FormLayout;

class UserEditForm extends Form
{
    use SmartObject;

    public array $onFinished = [];
    public array $onFailed = [];
    public array $onCancel = [];

    private UsersFacade $usersFacade;
    private Passwords $passwords;

    public function __construct(UsersFacade $usersFacade, Passwords $passwords, Nette\ComponentModel\IContainer $parent = null, string $name = null)
    {
        parent::__construct($parent, $name);
        // Nastavení Bootstrap 4 rendereru podle vzoru
        $this->setRenderer(new Bs4FormRenderer(FormLayout::VERTICAL));
        $this->usersFacade = $usersFacade;
        $this->passwords = $passwords;
        $this->createSubcomponents();
    }

    private function createSubcomponents(): void
    {
        $userId = $this->addHidden('userId'); // Skryté pole pro ID

        $this->addText('name', 'Jméno uživatele *')
            ->setRequired('Musíte zadat jméno uživatele')
            ->setMaxLength(40); // Podle DB limitu

        $this->addEmail('email', 'E-mail *')
            ->setRequired('Musíte zadat e-mail')
            ->addRule(function ($input) use ($userId) {
                $existingUser = $this->usersFacade->getUserByEmail($input->value);

                // pokud nikdo s tímto e-mailem neexistuje
                if (!$existingUser) {
                    return true;
                }
                // e-mail je volný jen tehdy, pokud je to ten samý uživatel, kterého právě editujeme
                return $existingUser->userId == $userId->value;
            }, 'Tento e-mail je již registrován u jiného uživatele.');

        #region role
        $roles = $this->usersFacade->findRoles();
        $rolesArr = [];
        foreach ($roles as $role) {
            $rolesArr[$role->roleId] = $role->roleId; // Mapování role_id
        }
        $this->addSelect('roleId', 'Role', $rolesArr)
            ->setPrompt('--vyberte roli--')
            ->setRequired(false);
        #endregion role

        #region heslo
        $password = $this->addPassword('password', 'Heslo')
            ->setOption('description', 'Pokud heslo nevyplníte, bude u NOVÉHO uživatele vygenerováno automaticky. ');


        $password->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň 5 znaků.', 5);
        #endregion heslo

        #region tlačítka
        $this->addSubmit('ok', 'Uložit')
            ->onClick[] = function (SubmitButton $button) {
            $values = $this->getValues('array');
            $generatedPassword = null;

            // Načtení nebo vytvoření entity uživatele
            if (!empty($values['userId'])) {
                try {
                    $user = $this->usersFacade->getUser($values['userId']);
                } catch (\Exception $e) {
                    $this->onFailed('Požadovaný uživatel nebyl nalezen.');
                    return;
                }
            } else {
                $user = new User();
            }

            // Naplnění základních hodnot
            $user->name = $values['name'];
            $user->email = $values['email'];

            // logika pro heslo
            if (!empty($values['password'])) {
                // 1. Admin zadal heslo ručně
                $user->password = $this->passwords->hash($values['password']);
            } elseif (empty($values['userId'])) {
                // 2. Jde o nového uživatele a heslo je prázdné -> VYGENEROVAT
                $generatedPassword = \Nette\Utils\Random::generate(10); // 10 znaků
                $user->password = $this->passwords->hash($generatedPassword);
            }

            // Přiřazení role jako entity
            if (!empty($values['roleId'])) {
                $user->role = $this->usersFacade->getRole($values['roleId']);
            } else {
                $user->role = null;
            }

            // Uložení přes fasádu
            $this->usersFacade->saveUser($user);

            // Vyvolání callbacku pro dokončení
            if ($generatedPassword) {
                $this->onFinished("Uživatel byl vytvořen. Vygenerované heslo: **$generatedPassword** (doporučte uživateli změnu hesla)");
            } else {
                $this->onFinished('Uživatel byl úspěšně uložen.');
            }
        };

        $this->addSubmit('storno', 'Zrušit')
            ->setValidationScope([$userId])
            ->onClick[] = fn(SubmitButton $btn) => $this->onCancel();
        #endregion tlačítka
    }

    /**
     * Mapování entity User na prvky formuláře
     */
    public function setDefaults($values, bool $erase = false): self
    {
        if ($values instanceof User) {
            $values = [
                'userId' => $values->userId,
                'name' => $values->name,
                'email' => $values->email,
                'roleId' => $values->role?->roleId ?? null,
            ];
        }
        parent::setDefaults($values, $erase);
        return $this;
    }
}