<?php

namespace App\FrontModule\Components\CheckoutForm;

/**
 * Interface CheckoutFormFactory
 * @package App\FrontModule\Components\CheckoutForm
 */
interface CheckoutFormFactory
{
    public function create(): CheckoutForm;
}