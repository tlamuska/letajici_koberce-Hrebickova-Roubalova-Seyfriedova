<?php

namespace App\FrontModule\Presenters;

use App\FrontModule\Components\ProductCartForm\ProductCartForm;
use App\FrontModule\Components\ProductCartForm\ProductCartFormFactory;
use App\Model\Facades\ProductsFacade;
use App\Model\Facades\CategoriesFacade;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Multiplier;

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
            $form->onSubmit[]=function(ProductCartForm $form){
                try {
                    $product = $this->productsFacade->getProduct($form->values->productId);
                } catch (Exception $e) {
                    $this->flashMessage('Produkt nenalezen.');
                    $this->redirect('list');
                }

                $cart = $this->getComponent('cart');
                $cart->addToCart($product, $form->values->count);

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
        //TODO tady by mělo přibýt filtrování podle kategorie, stránkování atp.
        $criteria = [
            'available' => true,
            'order' => 'title',
        ];

        $currentCategory = null;
        
        if ($this->category !== null) {
            $currentCategory = $this->categoriesFacade->getCategory($this->category);

            if (!$currentCategory) {
                throw new BadRequestException('Kategorie neexistuje.');
            }

            $criteria['category_id'] = $currentCategory->categoryId;
        }

        $products = $this->productsFacade->findProducts($criteria);

        $this->template->products = $products;
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