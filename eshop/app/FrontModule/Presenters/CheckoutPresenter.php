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
            if (isset($section->userId) && (int)$section->userId !== (int)$this->user->getId()) {
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
        protected function createComponentCheckoutForm(): CheckoutForm
        {
            $form = $this->checkoutFormFactory->create();

            $form->onFinished[] = function (array $data): void {
                $section = $this->getSession('checkout');
                $section->userId = (int)$this->user->getId();
                $section->data = $data;
                $this->redirect('recapitulation');
            };

            return $form;
        }

        /**
         * Akce pro vykreslení checkoutu
         */
        public function renderShippingPayment(): void
        {
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

            $shippingMethod = (string)($data['shippingMethod'] ?? '');
            $paymentMethod = (string)($data['paymentMethod'] ?? '');

            if ($shippingMethod === '' || $paymentMethod === '') {
                $this->flashMessage('Vyberte prosím dopravu a platbu.', 'warning');
                $this->redirect('shippingPayment');
            }

            $shippingPrice = Order::shippingPrice($shippingMethod);

            $itemsTotal = (float)$cart->getTotalPrice();
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
                $order = $this->ordersFacade->createOrderFromCart((int)$this->user->getId(), $cart, $data);

                //VYTVOŘENÍ OBJEDNÁVKY

                //mail uživatele
                $userMail = $this->user->getIdentity()->email;

                // řádky v tabulce
                $colors = [
                    'red' => 'Červená',
                    'blue' => 'Modrá',
                    'green' => 'Zelená',
                ];
                $shipping = [
                    'courier' => 'Kurýr',
                    'pickup' => 'Osobní odběr',
                    'post' => 'Pošta'

                ];

                $payment = [
                    'cod' => 'Dobírka',
                    'bank' => 'Převod na účet',
                ];

                // sestavení mailu
                $itemsHtml = "";
                foreach ($cart->items as $item) {
                    $colorLabel = $colors[$item->color] ?? '-';
                    $sizeLabel = ($item->size !== null && $item->size !== '') ? $item->size . ' cm' : '–';
                    $rowPrice = number_format($item->product->price * $item->count, 0, ',', ' ');

                    $itemsHtml .= "
                        <tr>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$item->product->title}</td>
                            <td style='padding: 8px; border: 1px solid #ddd; text-align: center;'>{$colorLabel}</td>
                            <td style='padding: 8px; border: 1px solid #ddd; text-align: center;'>{$sizeLabel}</td>
                            <td style='padding: 8px; border: 1px solid #ddd; text-align: center;'>{$item->count}</td>
                            <td style='padding: 8px; border: 1px solid #ddd; text-align: right;'>{$rowPrice} Kč</td>
                            
                        </tr>";
                }

                // obsah mail

                $shippingLabel = $shipping[$data['shippingMethod']] ?? $data['shippingMethod'];
                $paymentLabel = $payment[$data['paymentMethod']] ?? $data['paymentMethod'];

                $orderUrl = $this->link('//:Front:Order:show', ['id' => $order->orderId]);

                $htmlBody = "
                <p>Vážený zákazníku,</p>
                <p>potvrzujeme, že jsme přijali platbu pro Vaši objednávku č. <strong>{$order->orderId}</strong>.</p>
                <table style='width: 100%; border-collapse: collapse; font-family: Arial, sans-serif;'>
                    <thead>
                        <tr style='background-color: #f2f2f2;'>
                            <th style='padding: 8px; border: 1px solid #ddd; text-align: left;'>Produkt</th>
                            <th style='padding: 8px; border: 1px solid #ddd;'>Barva</th>
                            <th style='padding: 8px; border: 1px solid #ddd;'>Velikost</th>
                            <th style='padding: 8px; border: 1px solid #ddd;'>Ks</th>
                            <th style='padding: 8px; border: 1px solid #ddd;'>Cena</th>
                        </tr>
                    </thead>
                    <tbody>{$itemsHtml}</tbody>
                    <tfoot>
                        <tr>
                            <td colspan='4' style='padding: 8px; border: 1px solid #ddd; text-align: right;'>Doprava: <strong>{$shippingLabel}</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd; text-align: right;'>" . number_format($order->shippingPrice ?? 0, 0, ',', ' ') . " Kč</td>
                        </tr>
                        <tr>
                            <td colspan='4' style='padding: 8px; border: 1px solid #ddd; text-align: right;'>Platba: <strong>{$paymentLabel}</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd; text-align: right;'>0 Kč</td>
                        </tr>   
                        <tr style='background-color: #f8f9fa; font-weight: bold;'>
                            <td colspan='4' style='padding: 10px; border: 1px solid #ddd; text-align: right; font-size: 1.1em;'>Celkem zaplaceno:</td>
                            <td style='padding: 10px; border: 1px solid #ddd; text-align: right; font-size: 1.1em;'>" . number_format($order->grandTotal, 0, ',', ' ') . " Kč</td>
                        </tr>
                    </tfoot>
                </table>
                <p>O dalším průběhu Vás budeme informovat.</p>
                <p>Vaši objednávku můžete sledovat zde: <a href='{$orderUrl}'>Detail objednávky č. {$order->orderId}</a></p>
                <p>Děkujeme za nákup!<br>S pozdravem <br>Létající koberce</p>";

                // poslat mail
                $mail = new \Nette\Mail\Message();
                $mail->setFrom('info@letajicikoberce.cz', 'Létající koberce');
                $mail->addTo($userMail);
                $mail->subject = 'Potvrzení objednávky č. ' . $order->orderId;
                $mail->htmlBody =$htmlBody;


                $mailer = new \Nette\Mail\SendmailMailer;
                $mailer->send($mail);


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

            $userId = (int)$this->user->getId();
            $isAdmin = $this->user->isInRole('admin');

            if (!$isAdmin && (int)$order->userId !== $userId) {
                $this->error('Oops. Na zobrazení tohoto obsahu nemáte dostatečná práva.', 403);
            }

            $this->template->maxStep = $this->getMaxCheckoutStep();

            $this->template->orderId = $id;

            $this->getSession('checkout')->remove(); // reset po dokončení
        }

        /**
         * Funkce pro získání košíku
         */
        private function getCartOrRedirect(): Cart
        {
            try {
                $cart = $this->cartFacade->getCartByUser((int)$this->user->getId());
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