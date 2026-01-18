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
                // když se stav změní na 'paid', odešleme e-mail zákazníkovi
                if ($new === 'paid' && $current !== 'paid') {

                    // Mapování popisků
                    $colors = ['red' => 'Červená', 'blue' => 'Modrá', 'green' => 'Zelená'];
                    $shipping = ['courier' => 'Kurýr', 'pickup' => 'Osobní odběr', 'post' => 'Pošta'];
                    $payment = ['cod' => 'Dobírka', 'bank' => 'Převod na účet'];

                    // Sestavení tabulky produktů
                    $itemsHtml = "";
                    foreach ($order->items as $item) {
                        $colorLabel = $colors[$item->color] ?? $item->color ?? '-';
                        $sizeLabel = ($item->size !== null && $item->size !== '') ? $item->size . ' cm' : '–';
                        $rowPrice = number_format($item->unitPrice * $item->count, 0, ',', ' ');

                        $itemsHtml .= "
                    <tr>
                        <td style='padding: 8px; border: 1px solid #ddd;'>{$item->productTitle}</td>
                        <td style='padding: 8px; border: 1px solid #ddd; text-align: center;'>{$colorLabel}</td>
                        <td style='padding: 8px; border: 1px solid #ddd; text-align: center;'>{$sizeLabel}</td>
                        <td style='padding: 8px; border: 1px solid #ddd; text-align: center;'>{$item->count}</td>
                        <td style='padding: 8px; border: 1px solid #ddd; text-align: right;'>{$rowPrice} Kč</td>
                    </tr>";
                    }
                    $shippingLabel = $shipping[$order->shippingMethod] ?? $order->shippingMethod;
                    $paymentLabel = $payment[$order->paymentMethod] ?? $order->paymentMethod;

                    $orderUrl = $this->getPresenter()->link('//:Front:Order:show', ['id' => $order->orderId]);

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

                    // Vytvoření a odeslání mailu
                    $mail = new \Nette\Mail\Message();
                    $mail->setFrom('info@letajicikoberce.cz', 'Létající koberce')
                        ->addTo($order->customerEmail)
                        ->setSubject('Platba přijata - objednávka č. ' . $order->orderId)
                        ->setHtmlBody($htmlBody);

                    $mailer = new \Nette\Mail\SendmailMailer();
                    $mailer->send($mail);

                    $this->onFinished('Stav objednávky byl uložen a zákazníkovi bylo odesláno potvrzení o platbě.');
                } else {
                    $this->onFinished('Stav objednávky byl uložen');
                }
            } catch (\Throwable $e) {
                $this->onFailed('Uložení se nepodařilo: ' . $e->getMessage());
                return;
            }

            $this->setValues(['orderId' => $order->orderId]);
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