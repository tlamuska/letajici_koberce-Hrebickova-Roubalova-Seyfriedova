<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Components\ProductEditForm\ProductEditForm;
use App\AdminModule\Components\ProductEditForm\ProductEditFormFactory;
use App\Model\Facades\ProductsFacade;
use App\Model\Facades\CategoriesFacade;
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
    private CategoriesFacade $categoriesFacade;

    /** @persistent */
    public $page = 1;

    /** @persistent */
    public $search = '';

    /** @persistent */
    public $categoryId = null;

    /** @persistent */
    public $available = null;
    /** @persistent */
    public $type = null;

    /**
     * Akce pro vykreslení seznamu produktů
     */
    public function renderDefault(): void
    {
        $criteria = [];
        $categories = $this->categoriesFacade->findAllCategories();

        // 2. Filtrování seznamu kategorií podle zvoleného typu pro SELECT
        if ($this->type === 'Prislusenstvi') {
            $categories = array_filter($categories, function($cat) {
                return $cat->title === 'Příslušenství';
            });
        } elseif ($this->type === 'Koberec') {
            $categories = array_filter($categories, function($cat) {
                return $cat->title !== 'Příslušenství';
            });
        }

        // 3. Logika resetu kategorie, pokud neodpovídá typu
        if (!empty($this->type) && !empty($this->categoryId)) {
            $categoryExists = false;
            foreach ($categories as $cat) {
                if ($cat->categoryId == $this->categoryId) {
                    $categoryExists = true;
                    break;
                }
            }
            if (!$categoryExists) {
                $this->categoryId = null;
                $this->redirect('this', ['categoryId' => null]);
            }
        }

        // 4. Sestavení kritérií pro vyhledávání produktů
        if (!empty($this->search) && Strings::length($this->search) >= 3) {
            $criteria['search'] = $this->search;
        }
        if (!empty($this->categoryId)) {
            $criteria['category_id'] = $this->categoryId;
        }
        if ($this->available !== null && $this->available !== '') {
            $criteria['available'] = (bool)$this->available;
        }
        if (!empty($this->type)) {
            $criteria['type'] = $this->type;
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
        $this->template->categories = $categories;
        $this->template->search = $this->search;
        $this->template->categoryId = $this->categoryId;
        $this->template->available = $this->available;
        $this->template->type = $this->type;

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

            $product->available = FALSE;
            $this->productsFacade->saveProduct($product);

            $this->flashMessage('Produkt byl úspěšně vyřazen z prodeje (skryt).', 'success');
            $this->redirect('default');

        } catch (\Nette\Application\AbortException $e) {
            // Tuto výjimku vyhazuje redirect, musíme ji nechat projít dál
            throw $e;
        } catch (\Exception $e) {
            $this->flashMessage('Chyba při změně stavu produktu: ' . $e->getMessage(), 'error');
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

    public
    function injectCategoriesFacade(CategoriesFacade $categoriesFacade): void
    {
        $this->categoriesFacade = $categoriesFacade;
    }
#endregion injections

}
