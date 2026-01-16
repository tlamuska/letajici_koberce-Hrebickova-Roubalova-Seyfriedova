<?php

namespace App\FrontModule\Presenters;

use App\Model\Facades\ProductsFacade;
use App\Model\Facades\CategoriesFacade;

class HomepagePresenter extends BasePresenter{

    private ProductsFacade $productsFacade;
//    private CategoriesFacade $categoriesFacade;

    public function renderDefault(): void
    {
//// Načtení kategorií
//        $categories = $this->categoriesFacade->findAllCategories();
//        $this->template->categories = $categories;
//
//        // Mapování pro výběr kategorie
//        $catMap = [
//            'Základní koberce' => null,
//            'Speciální koberce' => null,
//            'Koberce na míru' => null,
//        ];
//
//        foreach ($categories as $cat) {
//            if ($cat->title === 'Základní') $catMap['Základní koberce'] = $cat->categoryId;
//            if ($cat->title === 'Speciální') $catMap['Speciální koberce'] = $cat->categoryId;
//            if ($cat->title === 'Na míru') $catMap['Koberce na míru'] = $cat->categoryId;
//        }
//
//        $this->template->catMap = $catMap;

        // Novinky (produkty)
        $criteria = [
            'available' => true,
            'order' => 'product_id DESC',
        ];

        $products = $this->productsFacade->findProducts($criteria, 0, 8);
        $this->template->products = $products ?? [];

        // Obrázky produktů
        $productImages = [];
        foreach ($products ?? [] as $product) {
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

    public function injectProductsFacade(ProductsFacade $productsFacade): void
    {
        $this->productsFacade = $productsFacade;
    }
//    public function injectCategoriesFacade(CategoriesFacade $categoriesFacade): void
//    {
//        $this->categoriesFacade = $categoriesFacade;
//    }
}
