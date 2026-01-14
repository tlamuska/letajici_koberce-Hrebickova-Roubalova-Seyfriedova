<?php

namespace App\AdminModule\Components\OrderEditForm;

/**
 * Interface OrderEditFormFactory
 * @package App\AdminModule\Components\OrderEditForm
 */
interface OrderEditFormFactory
{

    public function create():OrderEditForm;

}