<?php

namespace App\AdminModule\Components\OrderEditForm;

use App\Model\Entities\Order;
use App\Model\Facades\OrdersFacade;
use Nette;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\SubmitButton;
use Nette\SmartObject;
use Nextras\FormsRendering\Renderers\Bs4FormRenderer;
use Nextras\FormsRendering\Renderers\FormLayout;

/**
 * Class OrderEditForm
 * @package App\AdminModule\Components\OrderEditForm
 *
 * @method onFinished(string $message = '')
 * @method onFailed(string $message = '')
 * @method onCancel()
 */
class OrderEditForm extends Form{

    use SmartObject;

    /** @var callable[] $onFinished */
    public array $onFinished = [];
    /** @var callable[] $onFailed */
    public array $onFailed = [];
    /** @var callable[] $onCancel */
    public array $onCancel = [];

    private OrdersFacade $ordersFacade;

    /**
     * OrderEditForm constructor.
     * @param OrdersFacade $ordersFacade
     * @param Nette\ComponentModel\IContainer|null $parent
     * @param string|null $name
     */
    public function __construct(OrdersFacade $ordersFacade, Nette\ComponentModel\IContainer $parent = null, string $name = null){
        parent::__construct($parent, $name);
        $this->setRenderer(new Bs4FormRenderer(FormLayout::VERTICAL));
        $this->ordersFacade = $ordersFacade;
        $this->createSubcomponents();
    }

    // povolené přechody mezi stavy objednávky
    private const ALLOWED_NEXT = [
        'new' => ['new', 'paid', 'processing', 'cancelled'],
        'paid' => ['paid', 'processing', 'cancelled'],
        'processing' => ['processing', 'shipped', 'cancelled'],
        'shipped' => ['shipped', 'delivered'],
        'delivered' => ['delivered'],
        'cancelled' => ['cancelled'],
    ];

    private function createSubcomponents(): void{
        $orderId = $this->addHidden('orderId');
        $this->addSelect('status', 'Změna stavu', Order::getStatusLabels())
            ->setRequired('Musíte vybrat stav objednávky.');

        $this->addSubmit('ok', 'uložit')
            ->onClick[] = function (SubmitButton $submitButton) {
            $values = $this->getValues('array');

            if (empty($values['orderId'])) {
                $this->onFailed('Chybí ID objednávky');
                return;
            }

            try {
                $order = $this->ordersFacade->getOrder($values['orderId']);
            } catch (\Exception $e) {
                $this->onFailed('Požadovaná objednávka nebyla nalezena.');
                return;
            }

            $current = (string) $order->status;
            $new = (string) $values['status'];

            $allowed = self::ALLOWED_NEXT[$current] ?? [$current];
            if (!in_array($new, $allowed, true)) {
                $this->onFailed('Nepovolený přechod stavu.');
                return;
            }

            try {
                $this->ordersFacade->changeStatus($order->orderId, $new);
            } catch (\Throwable $e) {
                $this->onFailed('Uložení se nepodařilo: ' . $e->getMessage());
                return;
            }

            $this->setValues(['orderId' => $order->orderId]);
            $this->onFinished('Stav objednávky byl uložen');
        };

        $this->addSubmit('storno', 'zrušit')
            ->setValidationScope([$orderId])
            ->onClick[] = function (SubmitButton $btn) {
                $this->onCancel();
            };
    }

    /**
     * Metoda pro nastavení výchozích hodnot formuláře
     * @param Order|array|object $values
     * @param bool $erase = false
     * @return $this
     */
    public function setDefaults($values, bool $erase = false): self{
        if ($values instanceof Order) {
            $current = (string) $values->status;

            $allowed = self::ALLOWED_NEXT[$current] ?? [$current];
            $options = [];
            foreach ($allowed as $option) {
                $options[$option] = Order::statusLabel($option);
            }

            $this['status']->setItems($options);

            $values = [
                'orderId' => $values->orderId,
                'status' => $current,
            ];
        };

        parent::setDefaults($values, $erase);
        return $this;
    }

}