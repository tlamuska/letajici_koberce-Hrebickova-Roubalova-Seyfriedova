<?php


namespace App\Model\Facades;

use App\Model\Entities\Product;
use App\Model\Repositories\ProductRepository;
use Nette\Http\FileUpload;
use Nette\Utils\Strings;
use LeanMapper\Connection;
use Nette\Utils\Image;


/**
 * Class ProductsFacade
 * @package App\Model\Facades
 */
class ProductsFacade
{

    private ProductRepository $productRepository;


    /** @var Connection */
    private Connection $connection;

    public function __construct(ProductRepository $productRepository, Connection $connection)
    {
        $this->productRepository = $productRepository;
        $this->connection = $connection;
    }

    /**
     * Metoda pro získání jednoho produktu
     * @param int $id
     * @return Product
     * @throws \Exception
     */
    public function getProduct(int $id): Product
    {
        return $this->productRepository->find($id);
    }

    /**
     * Metoda pro získání produktu podle URL
     * @param string $url
     * @return Product
     * @throws \Exception
     */
    public function getProductByUrl(string $url): Product
    {
        return $this->productRepository->findBy(['url' => $url]);
    }

    /**
     * Metoda pro vyhledání produktů
     * @param array|null $params = null
     * @param int $offset = null
     * @param int $limit = null
     * @return Product[]
     */
    public function findProducts(array $params = null, int $offset = null, int $limit = null): array
    {
        return $this->productRepository->findAllBy($params, $offset, $limit);
    }

    /**
     * Metoda pro zjištění počtu produktů
     * @param array|null $params
     * @return int
     */
    public function findProductsCount(array $params = null): int
    {
        return $this->productRepository->findCountBy($params);
    }

    /**
     * Metoda pro uložení produktu
     * @param Product &$product
     */
    public function saveProduct(Product &$product): void
    {
        #region URL produktu
        if (empty($product->url)) {
            //pokud je URL prázdná, vygenerujeme ji podle názvu produktu
            $baseUrl = Strings::webalize($product->title);
        } else {
            $baseUrl = $product->url;
        }

        #region vyhledání produktů se shodnou URL (v případě shody připojujeme na konec URL číslo)
        $urlNumber = 1;
        $url = $baseUrl;
        $productId = isset($product->productId) ? $product->productId : null;
        try {
            while ($existingProduct = $this->getProductByUrl($url)) {
                if ($existingProduct->productId == $productId) {
                    //ID produktu se shoduje => je v pořádku, že je URL stejná
                    $product->url = $url;
                    break;
                }
                $urlNumber++;
                $url = $baseUrl . $urlNumber;
            }
        } catch (\Exception $e) {
            //produkt nebyl nalezen => URL je použitelná
        }
        $product->url = $url;
        #endregion vyhledání produktů se shodnou URL (v případě shody připojujeme na konec URL číslo)
        #endregion URL produktu

        $this->productRepository->persist($product);
    }


    /**
     * Smaže produkt a jeho fyzické soubory.
     * O smazání řádků v product_images se postará ON DELETE CASCADE v databázi.
     * @param int $productId
     */
    public function deleteProduct(int $productId): void
    {
        // 1. Získáme obrázky, abychom znali jejich názvy předtím, než se smažou z DB
        $images = $this->getProductImages($productId);

        // 2. Smažeme fyzické soubory z disku
        foreach ($images as $image) {
            // Předpokládám, že $image je objekt (ActiveRow), pokud je to pole, použij $image['filename']
            $filename = is_object($image) ? $image->filename : $image['filename'];

            $filePath = __DIR__ . '/../../../www/img/products/' . $filename;

            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        // 3. Smažeme samotný produkt
        // Díky ON DELETE CASCADE se automaticky smažou i záznamy v tabulce product_images
        $product = $this->productRepository->find($productId);
        if ($product) {
            $this->productRepository->delete($product);
        }
    }

    /**
     * Metoda pro uložení fotky produktu
     * @param FileUpload $fileUpload
     * @param Product $product
     * @throws \Exception
     */
    public function saveProductPhoto(FileUpload $fileUpload, Product &$product): void
    {
        if ($fileUpload->isOk() && $fileUpload->isImage()) {
            $fileExtension = strtolower($fileUpload->getImageFileExtension());
            $fileUpload->move(__DIR__ . '/../../../www/img/products/' . $product->productId . '.' . $fileExtension);
            $product->photoExtension = $fileExtension;
            $this->saveProduct($product);
        }
    }

    /**
     * Metoda pro uložení fotky produktu
     * @param array $files
     * @param int $productId
     * @throws \Exception
     * @throws \Nette\Utils\ImageException
     */
    public function saveProductImages(int $productId, array $files): void
    {
        $uploadDir = __DIR__ . '/../../../www/img/products/';

        // zjistíme, zda už existuje nějaký hlavní obrázek
        $hasMain = (bool) $this->connection->query(
            'SELECT COUNT(*) FROM product_images WHERE product_id = %i AND is_main = 1',
            $productId
        )->fetchSingle();

        // zjistíme aktuální počet obrázků pro generování názvů
        $row = $this->connection->query(
            'SELECT COUNT(*) AS cnt FROM product_images WHERE product_id = %i',
            $productId
        )->fetch();

        $currentCount = (int) $row->cnt;

        foreach ($files as $file) {
            if (!$file->isOk() || !$file->isImage()) {
                continue;
            }

            $currentCount++;
            $extension = strtolower($file->getImageFileExtension());
            $filename = $productId . '_' . $currentCount . '.' . $extension;

            while (file_exists($uploadDir . $filename)) {
                $currentCount++;
                $filename = $productId . '_' . $currentCount . '.' . $extension;
            }

            // načteme obrázek
            $img = Image::fromFile($file->getTemporaryFile());

            // ořízneme a změníme velikost na 600x400
            $img->resize(600, 400, Image::EXACT);

            // uložíme na disk
            $img->save($uploadDir . $filename);

            // vložíme do DB
            $this->connection->query(
                'INSERT INTO product_images',
                [
                    'product_id' => $productId,
                    'filename' => $filename,
                    'original_name' => $file->getName(),
                    'extension' => $extension,
                    'is_main' => $hasMain ? 0 : 1
                ]
            );
        }
    }

    /**
     * Metoda pro načtení obrázků
     * @param int $productId
     * @return array
     */
    public function getProductImages(int $productId): array{
        return $this->connection->query(
            'SELECT * FROM product_images WHERE product_id = %i ORDER BY image_id ASC',
            $productId
        )->fetchAll();
    }

    /**
     * Metoda pro odstranění obrázku
     * @param int $imageId
     * @param int $productId
     * @return void
     */
    public function deleteProductImage(int $imageId, int $productId): void
    {
        // načtení obrázku z DB
        $image = $this->connection->query(
            'SELECT * FROM product_images WHERE image_id = %i',
            $imageId
        )->fetch();

        if (!$image) {
            return;
        }

        if ($image->product_id != $productId) {
            return;
        }

        $wasMain = (bool) $image->is_main;

        // smazání souboru z uložistě
        $filePath = __DIR__ . '/../../../www/img/products/' . $image->filename;
        if (is_file($filePath)) {
            unlink($filePath);
        }

        // smazání z DB
        $this->connection->query('DELETE FROM product_images WHERE image_id = %i', $imageId);

        // pokud byl hlavní, nastaví jako hlavní jiný obrázek
        if ($wasMain) {
            $another = $this->connection->query(
                'SELECT * FROM product_images WHERE product_id = %i ORDER BY image_id ASC LIMIT 1',
                $productId
            )->fetch();

            if ($another) {
                $this->connection->query(
                    'UPDATE product_images SET is_main = 1 WHERE image_id = %i',
                    $another->image_id
                );
            }
        }
    }


    /**
     * Metoda pro nastavení obrázku jako hlavní
     * @param int $imageId
     * @param int $productId
     * @return void
     */
    public function setMainImage(int $imageId, int $productId): void {
        // všechny obrázky produktu = is_main = 0
        $this->connection->query(
            'UPDATE product_images SET is_main = 0 WHERE product_id = %i',
            $productId
        );

        // zvolený obrázek = is_main = 1
        $this->connection->query(
            'UPDATE product_images SET is_main = 1 WHERE image_id = %i',
            $imageId
        );
    }




}




