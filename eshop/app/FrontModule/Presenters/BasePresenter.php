<?php

namespace App\FrontModule\Presenters;

use App\FrontModule\Components\CartControl\CartControl;
use App\FrontModule\Components\CartControl\CartControlFactory;
use App\FrontModule\Components\UserLoginControl\UserLoginControl;
use App\FrontModule\Components\UserLoginControl\UserLoginControlFactory;
use App\Model\Facades\CartFacade;
use App\Model\Facades\CategoriesFacade;
use Nette\Application\AbortException;
use Nette\Application\ForbiddenRequestException;

/**
 * Class BasePresenter
 * @package App\FrontModule\Presenters
 */
abstract class BasePresenter extends \Nette\Application\UI\Presenter {
  private UserLoginControlFactory $userLoginControlFactory;
  private CartControlFactory $cartControlFactory;
  private CartFacade $cartFacade;
    /** @inject */
    public CategoriesFacade $categoriesFacade;

    protected function beforeRender(): void // metoda se spustí před vykreslením každé šablony
    {
        parent::beforeRender();
        // přidání proměnné do všech šablon
        $this->template->cartItemCount = $this['cart']->getTotalCount();

        $categories = $this->categoriesFacade->findAllCategories();
        $this->template->categories = $categories;

        $catMap = [];
        foreach ($categories as $cat) {
            // klíče pro menu v layoutu (krátké)
            $catMap[$cat->title] = $cat->categoryId;

            // klíče pro dlaždice na Homepage (dlouhé) podle původní logiky
            if ($cat->title === 'Základní') $catMap['Základní koberce'] = $cat->categoryId;
            if ($cat->title === 'Speciální') $catMap['Speciální koberce'] = $cat->categoryId;
            if ($cat->title === 'Na míru') $catMap['Koberce na míru'] = $cat->categoryId;
        }

        $this->template->catMap = $catMap;
    }

  /**
   * @throws ForbiddenRequestException
   * @throws AbortException
   */
  protected function startup():void {
    parent::startup();
    $presenterName = $this->request->presenterName;
    $action = !empty($this->request->parameters['action'])?$this->request->parameters['action']:'';

    if (!$this->user->isAllowed($presenterName,$action)){
      if ($this->user->isLoggedIn()){
        throw new ForbiddenRequestException();
      }else{
        $this->flashMessage('Pro zobrazení požadovaného obsahu se musíte přihlásit!','warning');
        //uložíme původní požadavek - předáme ho do persistentní proměnné v UserPresenteru
        $this->redirect('User:login', ['backlink' => $this->storeRequest()]);
      }
    }
  }

  /**
   * Komponenta pro zobrazení údajů o aktuálním uživateli (přihlášeném či nepřihlášeném)
   * @return UserLoginControl
   */
  public function createComponentUserLogin():UserLoginControl {
    return $this->userLoginControlFactory->create();
  }

  /**
   * Komponenta košíku
   * @return CartControl
   */
  public function createComponentCart():CartControl {
    return $this->cartControlFactory->create();
  }

  #region injections
  public function injectUserLoginControlFactory(UserLoginControlFactory $userLoginControlFactory):void {
    $this->userLoginControlFactory=$userLoginControlFactory;
  }

  public function injectCartControlFactory(CartControlFactory $cartControlFactory):void {
    $this->cartControlFactory=$cartControlFactory;
  }

    public function injectCartFacade(CartFacade $cartFacade): void {
        $this->cartFacade = $cartFacade;
    }
  #endregion injections
}