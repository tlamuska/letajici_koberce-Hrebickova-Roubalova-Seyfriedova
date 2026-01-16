<?php

namespace App\AdminModule\Presenters;

use App\Model\Facades\OrdersFacade;
use App\Model\Facades\ProductsFacade;

class DashboardPresenter extends BasePresenter{

    private OrdersFacade $ordersFacade;
    private ProductsFacade $productsFacade;

    public function renderDefault(): void
    {
        $this->template->countProducts = $this->productsFacade->findProductsCount([]);

        $allOrders = $this->ordersFacade->findOrders();

        $this->template->countOrders = count($allOrders);

        $this->template->lastOrders = array_slice(array_reverse($allOrders), 0, 5);
    }



    public function injectOrdersFacade(OrdersFacade $ordersFacade): void
    {
        $this->ordersFacade = $ordersFacade;
    }

    public function injectProductsFacade(ProductsFacade $productsFacade): void
    {
        $this->productsFacade = $productsFacade;
    }
}




