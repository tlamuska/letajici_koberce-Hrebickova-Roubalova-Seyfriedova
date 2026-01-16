<?php

namespace App\FrontModule\Presenters;

use App\FrontModule\Components\CheckoutForm\CheckoutFormFactory;
use App\FrontModule\Components\CheckoutForm\CheckoutForm;
use App\Model\Entities\Cart;
use App\Model\Facades\CartFacade;
use App\Model\Facades\OrdersFacade;
use App\Model\Facades\UsersFacade;
use App\Model\Entities\Order;

/**
 * Class CheckoutPresenter
 * @package App\AdminModule\Presenters
 */
class CheckoutPresenter extends BasePresenter
{
    private CartFacade $cartFacade;
    private OrdersFacade $ordersFacade;
    private UsersFacade $usersFacade;
    private CheckoutFormFactory $checkoutFormFactory;

    protected function startup(): void
    {
        parent::startup();

        // checkout jen pro přihlášené
        if (!$this->user->isLoggedIn()) {
            $this->flashMessage('Pro dokončení objednávky se musíte přihlásit.', 'warning');
            $this->redirect(':Front:User:login', ['backlink' => $this->storeRequest()]);
        }

        // jen authenticated/admin
        if (!($this->user->isInRole('authenticated') || $this->user->isInRole('admin'))) {
            $this->flashMessage('Nemáte oprávnění dokončit objednávku.', 'warning');
            $this->redirect(':Front:Cart:default');
        }

        // jen pokud data ze session patří danému uživateli
        $section = $this->getSession('checkout');
        if (isset($section->userId) && (int) $section->userId !== (int) $this->user->getId()) {
            $section->remove();
        }
    }

    protected function beforeRender(): void
    {
        parent::beforeRender();
        $this->template->colors = [
            'red' => 'Červená',
            'blue' => 'Modrá',
            'green' => 'Zelená',
        ];
    }

    /**
     * Akce pro vykreslení formuláře checkoutu
     */
    protected function createComponentCheckoutForm(): CheckoutForm {
        $form = $this->checkoutFormFactory->create();

        $form->onFinished[] = function (array $data): void {
            $section = $this->getSession('checkout');
            $section->userId = (int) $this->user->getId();
            $section->data = $data;
            $this->redirect('recapitulation');
        };

        return $form;
    }

    /**
     * Akce pro vykreslení checkoutu
     */
    public function renderShippingPayment(): void {
        $cart = $this->getCartOrRedirect();
        $this->template->cart = $cart;

        $this->template->maxStep = $this->getMaxCheckoutStep();

        //předvyplnění
        $userId = $this->user->getId(); //ID přihlášeného uživatele
        $userEntity = $this->usersFacade->getUser($userId);

        $defaults = [
            'customerName' => $userEntity->name,
            'customerEmail' => $userEntity->email,
        ];

        $section = $this->getSession('checkout');
        if (!empty($section->data)) {
            $this['checkoutForm']->setDefaults($section->data);
            return;
        }

        $this['checkoutForm']->setDefaults($defaults);
    }

    /**
     * Akce pro vykreslení rekapitulace objednávky
     */
    public function renderRecapitulation(): void
    {
        $cart = $this->getCartOrRedirect();

        $section = $this->getSession('checkout');
        $data = $section->data ?? null;

        if (!$data) {
            $this->flashMessage('Nejdřív vyplňte dopravu a platbu.', 'warning');
            $this->redirect('shippingPayment');
        }

        $shippingMethod = (string) ($data['shippingMethod'] ?? '');
        $paymentMethod = (string) ($data['paymentMethod'] ?? '');

        if ($shippingMethod === '' || $paymentMethod === '') {
            $this->flashMessage('Vyberte prosím dopravu a platbu.', 'warning');
            $this->redirect('shippingPayment');
        }

        $shippingPrice = Order::shippingPrice($shippingMethod);

        $itemsTotal = (float) $cart->getTotalPrice();
        $grandTotal = $itemsTotal + $shippingPrice;

        $this->template->cart = $cart;
        $this->template->data = $data;
        $this->template->itemsTotal = $itemsTotal;
        $this->template->shippingPrice = $shippingPrice;
        $this->template->grandTotal = $grandTotal;

        $this->template->shippingLabel = Order::shippingLabel($shippingMethod);
        $this->template->paymentLabel = Order::paymentLabel($paymentMethod);

        $this->template->maxStep = $this->getMaxCheckoutStep();

    }

    /**
     * Funkce pro vytvoření objednávky (funkce z Orders Facade)
     */
    public function handleSendOrder(): void
    {
        $cart = $this->getCartOrRedirect();

        $section = $this->getSession('checkout');
        $data = $section->data ?? null;

        if (!$data) {
            $this->flashMessage('Chybí data objednávky, zkuste to prosím znovu.', 'error');
            $this->redirect('shippingPayment');
        }

        try {
            $order = $this->ordersFacade->createOrderFromCart((int) $this->user->getId(), $cart, $data);
        } catch (\Throwable $e) {
            $this->flashMessage($e->getMessage(), 'error');
            $this->redirect('shippingPayment');
        }

        $section->orderId = $order->orderId;
        unset($section->data);

        $this->redirect('confirmation', ['id' => $order->orderId]);
    }

    /**
     * Akce pro vykreslení potvrzení objednávky
     */
    public function renderConfirmation(int $id): void
    {
        $order = $this->ordersFacade->getOrder($id);

        $userId  = (int) $this->user->getId();
        $isAdmin = $this->user->isInRole('admin');

        if (!$isAdmin && (int) $order->userId !== $userId) {
            $this->error('Oops. Na zobrazení tohoto obsahu nemáte dostatečná práva.', 403);
        }

        $this->template->maxStep = $this->getMaxCheckoutStep();

        $this->template->orderId = $id;

        $this->getSession('checkout')->remove(); // reset po dokončení
    }

    /**
     * Funkce pro získání košíku
     */
    private function getCartOrRedirect(): Cart {
        try {
            $cart = $this->cartFacade->getCartByUser((int) $this->user->getId());
        } catch (\Throwable $e) {
            $this->flashMessage('Košík nebyl nalezen.', 'warning');
            $this->redirect(':Front:Cart:default');
        }

        if (empty($cart->items)) {
            $this->flashMessage('Košík je prázdný.', 'info');
            $this->redirect(':Front:Cart:default');
        }

        return $cart;
    }

    /**
     * Funkce pro spočítání maximálního dosaženého kroku checkoutu kvůli navigaci
     */
    private function getMaxCheckoutStep(): string
    {
        $section = $this->getSession('checkout');

        if (!empty($section->orderId)) {
            return 'confirmation';
        }
        if (!empty($section->data)) {
            return 'recapitulation';
        }
        return 'shippingPayment';
    }


    public function injectCartFacade(CartFacade $cartFacade): void
    {
        $this->cartFacade = $cartFacade;
    }

    public function injectUsersFacade(UsersFacade $usersFacade): void
    {
        $this->usersFacade = $usersFacade;
    }

    public function injectOrdersFacade(OrdersFacade $ordersFacade): void
    {
        $this->ordersFacade = $ordersFacade;
    }

    public function injectCheckoutFormFactory(CheckoutFormFactory $factory): void
    {
        $this->checkoutFormFactory = $factory;
    }
}