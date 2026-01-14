<?php

namespace App\AdminModule\Presenters;


use App\AdminModule\Components\OrderEditForm\OrderEditFormFactory;
use App\AdminModule\Components\OrderEditForm\OrderEditForm;
use App\Model\Facades\OrdersFacade;

/**
 * Class OrderPresenter
 * @package App\AdminModule\Presenters
 */
class OrderPresenter extends BasePresenter
{
    private OrdersFacade $ordersFacade;
    private OrderEditFormFactory $orderEditFormFactory;

    /**
     * Akce pro vykreslení seznamu objednávek
    */
    public function renderDefault(): void {
        $this->template->orders=$this->ordersFacade->findOrders();
    }

    /**
     * Akce pro úpravu jedné objednávky
     * @param int $id
     * @throws \Nette\Application\AbortException
     */
    public function renderEdit(int $id):void {
        try{
            $order=$this->ordersFacade->getOrder($id);
        }catch (\Exception $e){
            $this->flashMessage('Požadovaná objednávka nebyla nalezena.', 'error');
            $this->redirect('default');
        }

        $this->template->order=$order;
        $this->template->items=$this->ordersFacade->getOrderItems($order->orderId);

        $form=$this['orderEditForm'];
        $form->setDefaults($order);
    }

    /**
     * Formulář na editaci objednávek
     * @return OrderEditForm
     */
    public function createComponentOrderEditForm(): OrderEditForm{
        $form=$this->orderEditFormFactory->create();
        $form->onCancel[] = function () {
            $this->redirect('default');
        };
        $form->onFinished[] = function ($message = null) {
            if (!empty($message)) {
                $this->flashMessage($message);
            }
            $this->redirect('this');
        };
        return $form;
    }


    #region injections
    public function injectOrdersFacade(OrdersFacade $ordersFacade): void {
        $this->ordersFacade = $ordersFacade;
    }

    public function injectOrderEditFormFactory(OrderEditFormFactory $orderEditFormFactory): void {
        $this->orderEditFormFactory = $orderEditFormFactory;
    }
    #endregion injections

}