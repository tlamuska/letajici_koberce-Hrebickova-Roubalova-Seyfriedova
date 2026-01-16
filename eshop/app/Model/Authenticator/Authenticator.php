<?php

namespace App\Model\Authenticator;

use App\Model\Facades\UsersFacade;
use Nette\Security\AuthenticationException;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;

/**
 * Class Authenticator - jednoduchý autentifikátor ověřující uživatele vůči databázi
 * @package App\Model\Authenticator
 */
class Authenticator implements \Nette\Security\Authenticator{
  private UsersFacade $usersFacade;
  private Passwords $passwords;

  public function __construct(Passwords $passwords, UsersFacade $usersFacade){
    $this->passwords=$passwords;
    $this->usersFacade=$usersFacade;
  }

  /**
   * @inheritDoc
   */
  function authenticate(string $email, string $password):IIdentity {
      $user = $this->usersFacade->getUserByEmail($email);

      if (!$user) {
          throw new AuthenticationException('Uživatelský účet neexistuje.');
      }

      if ($user->password === null || !$this->passwords->verify($password, $user->password)) {
          throw new AuthenticationException('Chybná kombinace e-mailu a hesla.');
      }

      return $this->usersFacade->getUserIdentity($user);
    }

}