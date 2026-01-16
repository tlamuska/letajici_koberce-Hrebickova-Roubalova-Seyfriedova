<?php

namespace App\FrontModule\Presenters;

use App\Model\Facades\OrdersFacade;

class OrderPresenter extends BasePresenter
{
    private OrdersFacade $ordersFacade;

    public function __construct(OrdersFacade $ordersFacade) {
        parent::__construct();
        $this->ordersFacade = $ordersFacade;
    }

    protected function startup(): void {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('User:login'); // Přesměrování, pokud není přihlášen
        }
    }

    /**
     * @throws \Exception
     */
    public function renderDefault(): void {
        // id přihlášeného uživatele
        $userId = $this->getUser()->getId();
        $this->template->orders = $this->ordersFacade->getUserOrders($userId);
    }

    public function renderShow(int $id): void {
        try {
            $order = $this->ordersFacade->getOrder($id);

            // nesmí přistoupit k cizí objednávce
            if ($order->userId !== $this->getUser()->getId()) {
                throw new \Exception('K této objednávce nemáte přístup.');
            }

            $this->template->order = $order;
            $this->template->items = $this->ordersFacade->getOrderItems($order->orderId);

        } catch (\Exception $e) {
            $this->flashMessage('Objednávka nebyla nalezena nebo k ní nemáte přístup.', 'error');
            $this->redirect('default');
        }
    }
}