<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Components\ProductEditForm\ProductEditForm;
use App\AdminModule\Components\ProductEditForm\ProductEditFormFactory;
use App\Model\Facades\ProductsFacade;
use Nette\Utils\Paginator;
use Nette\Utils\Strings;

/**
 * Class ProductPresenter
 * @package App\AdminModule\Presenters
 */
class ProductPresenter extends BasePresenter
{
    private ProductsFacade $productsFacade;
    private ProductEditFormFactory $productEditFormFactory;

    /** @persistent */
    public $page = 1;

    /** @persistent */
    public $search = '';

    /**
     * Akce pro vykreslení seznamu produktů
     */
    public function renderDefault(): void
    {
        $criteria = [];

        if (!empty($this->search) && Strings::length($this->search) >= 3) {
            $criteria['search'] = $this->search;
        }

        $paginator = new Paginator();
        $productsCount = $this->productsFacade->findProductsCount($criteria);

        $paginator->setItemCount($productsCount);
        $paginator->setItemsPerPage(8);

        $currentPage = min($this->page, $paginator->getPageCount());
        $currentPage = max($currentPage, 1);

        if ($this->page !== $currentPage) {
            $this->redirect('this', ['page' => $currentPage]);
        }

        $paginator->setPage($currentPage);

        $criteria['order'] = 'title';

        $this->template->products = $this->productsFacade->findProducts(
            $criteria,
            $paginator->getOffset(),
            $paginator->getLength()
        );

        $this->template->paginator = $paginator;
        $this->template->search = $this->search;

    }

    /**
     * Akce pro úpravu jednoho produktu
     * @param int $id
     * @throws \Nette\Application\AbortException
     */

    public function renderEdit(int $id): void
    {
        try {
            $product = $this->productsFacade->getProduct($id);
        } catch (\Exception $e) {
            $this->flashMessage('Požadovaný produkt nebyl nalezen.', 'error');
            $this->redirect('default');
        }

        if (!$this->user->isAllowed($product, 'edit')) {
            $this->flashMessage('Požadovaný produkt nemůžete upravovat.', 'error');
            $this->redirect('default');
        }

        $form = $this['productEditForm'];
        $form->setDefaults($product);

        $this->template->product = $product;
        $this->template->productImages = $this->productsFacade->getProductImages($product->productId);
    }


    /**
     * Formulář na editaci produktů
     * @return ProductEditForm
     */
    public function createComponentProductEditForm(): ProductEditForm
    {
        $form = $this->productEditFormFactory->create();
        $form->onCancel[] = function () {
            $this->redirect('default');
        };
        $form->onFinished[] = function ($message = null) {
            if (!empty($message)) {
                $this->flashMessage($message);
            }
            $this->redirect('default');
        };
        $form->onFailed[] = function ($message = null) {
            if (!empty($message)) {
                $this->flashMessage($message, 'error');
            }
            $this->redirect('default');
        };
        return $form;
    }

    /**
     * Akce pro smazání produktu
     * @param int $productId
     */
    public function handleDeleteProduct(int $productId): void
    {
        try {
            $product = $this->productsFacade->getProduct($productId);

            if (!$this->user->isAllowed($product, 'delete')) {
                $this->flashMessage('Nemáte oprávnění smazat tento produkt.', 'error');
                $this->redirect('this');
            }

            $this->productsFacade->deleteProduct($productId);
            $this->flashMessage('Produkt byl úspěšně smazán.', 'success');
            $this->redirect('default');

        } catch (\Nette\Application\AbortException $e) {
            // Tuto výjimku vyhazuje redirect, musíme ji nechat projít dál
            throw $e;
        } catch (\Exception $e) {
            $this->flashMessage('Produkt se nepodařilo smazat: ' . $e->getMessage(), 'error');
            $this->redirect('this');
        }
    }

    /**
     * Akce pro nastavení obrázku jako hlavní
     * @param int $imageId
     * @param int $productId
     */
    public function handleSetMainImage(int $imageId, int $productId): void
    {
        $this->productsFacade->setMainImage($imageId, $productId);
        $this->flashMessage('Obrázek byl nastaven jako hlavní.', 'success');
        $this->redirect('this');
    }

    /**
     * Akce pro smazání obrázku
     * @param int $imageId
     * @param int $productId
 */
    public function handleDeleteImage(int $imageId, int $productId): void
    {
        $this->productsFacade->deleteProductImage($imageId, $productId);

        // pokud byl smazán hlavní obrázek, nastavíme první zbylý jako hlavní
        $images = $this->productsFacade->getProductImages($productId);
        if ($images && !array_filter($images, fn($img) => $img['is_main'])) {
            $this->productsFacade->setMainImage($images[0]['image_id'], $productId);
        }

        $this->flashMessage('Obrázek byl odstraněn.', 'success');
        $this->redirect('this');
    }


#region injections
    public
    function injectProductsFacade(ProductsFacade $productsFacade): void
    {
        $this->productsFacade = $productsFacade;
    }

    public
    function injectProductEditFormFactory(ProductEditFormFactory $productEditFormFactory): void
    {
        $this->productEditFormFactory = $productEditFormFactory;
    }
#endregion injections

}
