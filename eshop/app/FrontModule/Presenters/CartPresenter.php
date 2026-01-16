<?php

namespace App\FrontModule\Presenters;

class CartPresenter extends BasePresenter
{
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

    public function renderDefault(): void
    {
        $this->template->checkoutMaxStep = $this->getMaxCheckoutStep();
    }



}