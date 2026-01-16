<?php

namespace App\FrontModule\Components\CheckoutForm;

use App\Model\Entities\Order;
use Nette;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\SubmitButton;
use Nette\SmartObject;
use Nextras\FormsRendering\Renderers\Bs4FormRenderer;
use Nextras\FormsRendering\Renderers\FormLayout;

/**
 * Class CheckoutForm
 * @package App\FrontModule\Components\CheckoutForm
 *
 * @method onFinished(array $data)
 */
class CheckoutForm extends Form
{
    use SmartObject;

    /**@var callable[] $onFinished */
    public array $onFinished = [];

    /**
     * CheckoutForm constructor.
     * @param Nette\ComponentModel\IContainer|null $parent
     * @param string|null $name
     */
    public function __construct(Nette\ComponentModel\IContainer $parent = null, string $name = null){
        parent::__construct($parent, $name);
        $this->setRenderer(new Bs4FormRenderer(FormLayout::VERTICAL));
        $this->createSubcomponents();

        $this->onError[] = function (Form $form): void {
            $form->addError('Vyplňte prosím všechna povinná pole.');
        };
    }

    private function createSubcomponents(): void {
        $this->addSelect('shippingMethod', 'Způsob dopravy *', Order::getShippingLabelsWithPrice())
            ->setPrompt('— vyberte dopravu —')
            ->setRequired('Vyberte způsob dopravy.');

        $this->addSelect('paymentMethod', 'Způsob platby *', Order::getPaymentLabels())
            ->setPrompt('— vyberte platbu —')
            ->setRequired('Vyberte způsob platby.');

        $this->addText('customerName', 'Jméno a příjmení *')
            ->setRequired('Vyplňte Vaše celé jméno.')
            ->setHtmlAttribute('maxlength',40);

        $this->addEmail('customerEmail', 'E-mail *')
            ->setRequired('Zadejte platný e-mail.');

        $phone = $this->addText('phone', 'Telefon *')
            ->setRequired('Vyplňte telefonní číslo.');

        $phone->addFilter(function ($value) {
            $v = (string) $value;
            return str_replace([' ', "\t", "\n", "\r"], '', $v);
        });
        $phone->addRule(
            Form::PATTERN,
            'Telefon zadejte ve formátu např. +420602123456 nebo 602123456.',
            '^\+?\d{9,15}$'
        );

        $this->addText('deliveryAddress', 'Adresa *')
            ->setRequired('Vyplňte Vaši adresu.')
            ->addRule(Form::MIN_LENGTH, 'Adresa je moc krátká.', 5)
            ->addRule(Form::MAX_LENGTH, 'Adresa je moc dlouhá.', 255);

        $this->addTextArea('note', 'Poznámka')
            ->setRequired(false)
            ->addRule(Form::MAX_LENGTH, 'Poznámka je moc dlouhá.', 1000);


        $this->addSubmit('ok', 'Pokračovat na rekapitulaci ->');
            $this->onSuccess[] = function (Form $form, array $values): void {
                $this->onFinished($values);
            };

    }
}