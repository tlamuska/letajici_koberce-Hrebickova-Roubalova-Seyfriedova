<?php

namespace App\FrontModule\Presenters;

use App\FrontModule\Components\ProductCartForm\ProductCartForm;
use App\FrontModule\Components\ProductCartForm\ProductCartFormFactory;
use App\Model\Facades\ProductsFacade;
use App\Model\Facades\CategoriesFacade;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Multiplier;
use Nette\Utils\Strings;

/**
 * Class ProductPresenter
 * @package App\FrontModule\Presenters
 * @property $category
 */
class ProductPresenter extends BasePresenter{
    private ProductsFacade $productsFacade;
    private ProductCartFormFactory $productCartFormFactory;
    //private CategoriesFacade $categoriesFacade;
    /** @persistent */
    public $category = null;
    /** @persistent */
    public $q = null; // parametr pro vyhledávání

    /** @persistent */
    public $categoryText = null;

    /**
     * Akce pro zobrazení jednoho produktu
     * @param string $url
     * @throws BadRequestException
     */
    public function renderShow(string $url):void {
        try{
            $product = $this->productsFacade->getProductByUrl($url);
        }catch (\Exception $e){
            throw new BadRequestException('Produkt nebyl nalezen.');
        }

        $this->template->product = $product;
        $this->template->images = $this->productsFacade->getProductImages($product->productId);
    }

    protected function createComponentProductCartForm():Multiplier {
        return new Multiplier(function($productId){
            // vytvoříme 1 instanci formuláře
            $form = $this->productCartFormFactory->create();
            $form->setDefaults(['productId' => $productId]);

            try {
                $product = $this->productsFacade->getProduct($productId);
            } catch (\Exception $e) {
            
                return $form;
            }


            if ($product->category && $product->category->title === "Na míru") {
                $form->addText('size', 'Zadejte rozměr:')
                    ->setHtmlAttribute('placeholder', 'např. 120x200')
                    ->setRequired('Zadejte prosím požadovaný rozměr.');
            } else {
                $velikosti = [
                    '90x170' => 'Menší (90x170 cm)',
                    '140x200' => 'Větší (140x200 cm)'
                ];

                $form->addSelect('size', 'Velikost:', $velikosti)
                    ->setRequired('Vyberte prosím velikost koberce.');
            }

            $form->onSubmit[]=function(ProductCartForm $form){
                $values = $form->getValues();
                try {
                    $product = $this->productsFacade->getProduct($form->values->productId);
                } catch (\Exception $e) {
                    $this->flashMessage('Produkt nenalezen.');
                    $this->redirect('list');
                }


                if ($product->category && $product->category->title === "Na míru" ) {
                    $size = $values->size;
                } elseif ($product->type === "Prislusenstvi") {
                    $size = null;
                } else {
                    $size = '90x170';
                }

                $cart = $this->getComponent('cart');
                $cart->addToCart(
                    $product,
                    $values->count,
                    ['color' => $values->color ?? null,
                        'size' => $size]
                );

                //pošleme uživatele zpět na stránku, ze které chtěl zboží přidat
                $this->flashMessage('Produkt přidán do košíku');
                $this->redirect('this');
            };

            return $form;
        });
    }



    /**
     * Akce pro vykreslení přehledu produktů
     */
    public function renderList():void {
        $criteria = [
            'available' => true,
            'order' => 'title',
        ];

        $currentCategory = null;

        if ($this->categoryText !== null) {

            // 1. ZJISTÍME, JESTLI JDE O STARÝ ODKAZ (obsahuje "kategorie-")
            if (strpos($this->categoryText, 'kategorie-') !== false) {
                // Je to starý odkaz, vytáhneme z něj číslo ID
                $id = (int) str_replace('kategorie-', '', $this->categoryText);
                try {
                    $currentCategory = $this->categoriesFacade->getCategory($id);
                    // Volitelně: Tady bychom mohli udělat redirect na novou URL, ale pro teď stačí, že to nespadne
                } catch (\Exception $e) {
                    // Kategorie nenalezena
                }
            } else {
                // 2. JE TO NOVÝ ODKAZ (hledáme podle názvu)
                $allCategories = $this->categoriesFacade->findCategories();
                foreach ($allCategories as $cat) {
                    if (Strings::webalize($cat->title) === $this->categoryText) {
                        $currentCategory = $cat;
                        break;
                    }
                }
            }

            // Pokud jsme nenašli kategorii ani podle ID, ani podle Názvu -> chyba
            if (!$currentCategory) {
                // PRO DEBUGGING: Odkomentujte řádek níže, abyste viděla, co přesně se hledalo
                // dump($this->categoryText); die();
                throw new BadRequestException('Kategorie neexistuje.');
            }

            $criteria['category_id'] = $currentCategory->categoryId;
        }

        // Filtrování podle vyhledávacího dotazu
        if ($this->q !== null && $this->q !== '') {
            $products = $this->productsFacade->searchProducts($this->q, $criteria);
            $this->setView('search');
        } else {
            // Pokud nevyhledává, použijeme původní metodu findProducts
            $criteria['order'] = 'title';
            $products = $this->productsFacade->findProducts($criteria);
        }
        $this->template->products = $products;
        $this->template->q = $this->q;
        $this->template->currentCategory = $currentCategory;
        $this->template->categories = $this->categoriesFacade->findCategories();

        // Obrázky produktů
        $productImages = [];
        foreach ($products as $product) {
            $images = $this->productsFacade->getProductImages($product->productId);

            $mainImg = null;
            foreach ($images as $image) {
                if ($image->is_main) {
                    $mainImg = $image;
                    break;
                }
            }

            $productImages[$product->productId] = $mainImg ?? ($images[0] ?? null);
        }

        $this->template->productImages = $productImages;
    }

    #region injections
    public function injectProductsFacade(ProductsFacade $productsFacade):void {
        $this->productsFacade=$productsFacade;
    }

    public function injectProductCartFormFactory(ProductCartFormFactory $productCartFormFactory):void {
        $this->productCartFormFactory=$productCartFormFactory;
    }

//    public function injectCategoriesFacade(CategoriesFacade $categoriesFacade):void {
//        $this->categoriesFacade=$categoriesFacade;
//    }
    #endregion injections
}
