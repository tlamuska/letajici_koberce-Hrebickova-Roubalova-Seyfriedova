<?php

namespace App\AdminModule\Components\ProductEditForm;

use App\Model\Entities\Product;
use App\Model\Facades\CategoriesFacade;
use App\Model\Facades\ProductsFacade;
use Nette;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\SubmitButton;
use Nette\SmartObject;
use Nextras\FormsRendering\Renderers\Bs4FormRenderer;
use Nextras\FormsRendering\Renderers\FormLayout;

class ProductEditForm extends Form
{
    use SmartObject;

    public array $onFinished = [];
    public array $onFailed = [];
    public array $onCancel = [];

    private CategoriesFacade $categoriesFacade;
    private ProductsFacade $productsFacade;

    public function __construct(CategoriesFacade $categoriesFacade, ProductsFacade $productsFacade, Nette\ComponentModel\IContainer $parent = null, string $name = null)
    {
        parent::__construct($parent, $name);
        $this->setRenderer(new Bs4FormRenderer(FormLayout::VERTICAL));
        $this->categoriesFacade = $categoriesFacade;
        $this->productsFacade = $productsFacade;
        $this->createSubcomponents();
    }

    private function createSubcomponents(): void
    {
        $productId = $this->addHidden('productId');

        $this->addText('title', 'Název produktu')
            ->setRequired('Musíte zadat název produktu')
            ->setMaxLength(100);

        $this->addText('url', 'URL produktu')
            ->setMaxLength(100)
            ->addFilter(fn(string $url) => Nette\Utils\Strings::webalize($url))
            ->addRule(function ($input) use ($productId) {
                try {
                    $existingProduct = $this->productsFacade->getProductByUrl($input->value);
                    return $existingProduct->productId == $productId->value;
                } catch (\Exception $e) {
                    return true;
                }
            }, 'Zvolená URL je již obsazena jiným produktem');

        #region kategorie
        $categories = $this->categoriesFacade->findCategories();
        $categoriesArr = [];
        foreach ($categories as $category) {
            $categoriesArr[$category->categoryId] = $category->title;
        }
        $this->addSelect('categoryId', 'Kategorie', $categoriesArr)
            ->setPrompt('--vyberte kategorii--')
            ->setRequired(false);
        #endregion kategorie

        $this->addTextArea('description', 'Popis produktu')->setRequired('Zadejte popis produktu.');

        $this->addText('price', 'Cena')
            ->setHtmlType('number')
            ->addRule(Form::NUMERIC, 'Musíte zadat číslo.')
            ->setRequired('Musíte zadat cenu produktu');

        $this->addCheckbox('available', 'Nabízeno ke koupi')->setDefaultValue(true);

        #region obrázky
        $photosUpload = $this->addMultiUpload('photos', 'Fotky produktu');

        $photosUpload->addConditionOn($productId, Form::EQUAL, '')
            ->setRequired('Pro uložení nového produktu je nutné nahrát alespoň jednu fotku.');

        $photosUpload->addRule(Form::MAX_FILE_SIZE, 'Jeden ze souborů je příliš velký (max 1 MB)', 1000000);

        $photosUpload->addCondition(Form::FILLED)
            ->addRule(function ($control) {
                foreach ($control->value as $uploadedFile) {
                    if ($uploadedFile instanceof Nette\Http\FileUpload && $uploadedFile->isOk()) {
                        $ext = strtolower($uploadedFile->getImageFileExtension());
                        if (!in_array($ext, ['jpg','jpeg','png'])) return false;
                    }
                }
                return true;
            }, 'Je nutné nahrát obrázky ve formátu JPEG či PNG.');
        #endregion obrázky

        #region tlačítka
        $this->addSubmit('ok', 'Uložit')
            ->onClick[] = function (SubmitButton $button) {
            $values = $this->getValues('array');

            // načtení nebo vytvoření produktu
            if (!empty($values['productId'])) {
                try {
                    $product = $this->productsFacade->getProduct($values['productId']);
                } catch (\Exception $e) {
                    $this->onFailed('Požadovaný produkt nebyl nalezen.');
                    return;
                }
            } else {
                $product = new Product();
            }

            // naplnění hodnot
            $product->assign($values, ['title','url','description','available']);
            $product->price = floatval($values['price']);

            // přiřazení kategorie
            if (!empty($values['categoryId'])) {
                $product->category = $this->categoriesFacade->getCategory($values['categoryId']);
            } else {
                $product->category = null;
            }

            // uložit produkt
            $this->productsFacade->saveProduct($product);
            $this->setValues(['productId' => $product->productId]);

            // uložit fotky
            if (!empty($values['photos'])) {
                try {
                    $this->productsFacade->saveProductImages($product->productId, $values['photos']);
                } catch (\Exception $e) {
                    $this->onFailed('Produkt byl uložen, ale nepodařilo se uložit obrázky.');
                }
            }

            $this->onFinished('Produkt byl uložen.');
        };

        $this->addSubmit('storno', 'Zrušit')
            ->setValidationScope([$productId])
            ->onClick[] = fn(SubmitButton $btn) => $this->onCancel();
        #endregion tlačítka
    }

    /**
     * Správně předvyplní formulář včetně kategorie
     */
    public function setDefaults($values, bool $erase = false): self
    {
        if ($values instanceof Product) {
            $values = [
                'productId' => $values->productId,
                'title' => $values->title,
                'url' => $values->url,
                'description' => $values->description,
                'price' => $values->price,
                'available' => $values->available,
                'categoryId' => $values->category?->categoryId ?? null,
            ];
        }
        parent::setDefaults($values, $erase);
        return $this;
    }

}
