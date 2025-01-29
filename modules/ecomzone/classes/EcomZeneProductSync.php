<?php

class EcomZoneProductSync
{
    private $client;

    public function __construct()
    {
        $this->client = new EcomZoneClient();
    }

    private function resizeImage($src, $dest, $width, $height): void
    {
        list($srcWidth, $srcHeight, $type) = getimagesize($src);
        $srcImage = $this->createImageFromType($src, $type);

        $destImage = imagecreatetruecolor($width, $height);
        imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);

        $this->saveImageFromType($destImage, $dest, $type);

        imagedestroy($srcImage);
        imagedestroy($destImage);
    }

    private function createImageFromType($filename, $type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_JPG:
                return imagecreatefromjpeg($filename);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filename);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filename);
            default:
                throw new Exception("Unsupported image type: " . $type);
        }
    }

    private function saveImageFromType($image, $filename, $type): void
    {
        $this->createDirectoryIfNotExists(dirname($filename));

        switch ($type) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_JPG:
                imagejpeg($image, $filename);
                break;
            case IMAGETYPE_PNG:
                imagepng($image, $filename);
                break;
            case IMAGETYPE_GIF:
                imagegif($image, $filename);
                break;
            default:
                throw new Exception("Unsupported image type: " . $type);
        }
    }

    public function importProducts($perPage = 100): array
    {
        $page = 1;
        $totalImported = 0;
        $totalAvailable = 0;

        EcomZoneLogger::log("Starting product import - perPage: $perPage");

        try {
            do {
                $catalog = $this->client->getCatalog($page, $perPage);

                if (!is_array($catalog) || !isset($catalog['data']) || !is_array($catalog['data']) || !isset($catalog['current_page']) || !isset($catalog['total'])) {
                    throw new Exception('Invalid catalog data received from API: ' . json_encode($catalog));
                }

                $importedCount = 0;
                foreach ($catalog['data'] as $product) {
                    if ($this->importSingleProduct($product)) {
                        $importedCount++;
                    }
                }

                $totalImported += $importedCount;
                $totalAvailable = $catalog['total'];
                $page++;

                EcomZoneLogger::log("Imported page $page", 'INFO', [
                    'total_imported' => $totalImported,
                    'page' => $page,
                    'total_available' => $totalAvailable
                ]);

            } while (isset($catalog['next_page_url']) && $catalog['next_page_url'] !== null);

            return [
                'success' => true,
                'imported' => $totalImported,
                'total' => $totalAvailable
            ];

        } catch (Exception $e) {
            EcomZoneLogger::log("Error importing products: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    private function importSingleProduct($productData): bool
    {
        // Extract data from nested structure
        $data = $productData['data'] ?? $productData;

        if (!isset($data['sku']) || !isset($data['product_name']) || !isset($data['description']) || !isset($data['product_price'])) {
            EcomZoneLogger::log("Invalid product data", 'ERROR', ['data' => $productData]);
            return false;
        }

        try {
            // Check if product already exists by reference
            $productId = Db::getInstance()->getValue('
                SELECT id_product 
                FROM ' . _DB_PREFIX_ . 'product 
                WHERE reference = "' . pSQL($data['sku']) . '"
            ');

            $product = $productId ? new Product($productId) : new Product();

            $defaultLangId = (int)Configuration::get('PS_LANG_DEFAULT');

            $product->reference = $data['sku'];
            $product->name[$defaultLangId] = $data['product_name'];
            $product->description[$defaultLangId] = $data['long_description'] ?? $data['description'];
            $product->description_short[$defaultLangId] = $data['description'];
            $product->price = $data['product_price'];
            $product->active = true;
            $product->quantity = (int)$data['stock'];
            $homeCategoryId = (int)Configuration::get('PS_HOME_CATEGORY');
            $product->id_category_default = $homeCategoryId;
            $product->addToCategories([$homeCategoryId]);

            // Save product first to get ID
            if (!$product->id) {
                $product->add();
            } else {
                $product->update();
            }

            // Handle image import if URL is provided
            if (isset($data['image']) && !empty($data['image'])) {
                $this->importProductImage($product, $data['image']);
            }

            StockAvailable::setQuantity($product->id, 0, (int)$data['stock']);

            EcomZoneLogger::log("Imported product", 'INFO', [
                'sku' => $data['sku'],
                'id' => $product->id,
                'name' => $data['product_name']
            ]);

            return true;

        } catch (Exception $e) {
            EcomZoneLogger::log("Error importing product", 'ERROR', [
                'sku' => $data['sku'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function importProductImage($product, $imageUrl): void
    {
        try {
            // Create temporary file
            $tmpFile = tempnam(_PS_TMP_IMG_DIR_, 'ecomzone_');

            // Download image
            if (!copy($imageUrl, $tmpFile)) {
                throw new Exception("Failed to download image from: " . $imageUrl);
            }

            // Get image info
            $imageInfo = getimagesize($tmpFile);
            if (!$imageInfo) {
                unlink($tmpFile);
                throw new Exception("Invalid image file");
            }

            // Validate image dimensions and file size
            if ($imageInfo[0] > 2000 || $imageInfo[1] > 2000 || filesize($tmpFile) > 5000000) {
                unlink($tmpFile);
                throw new Exception("Image dimensions or file size exceed limits");
            }

            // Generate unique name
            $imageName = $product->reference . '-' . time() . '.' . pathinfo($imageUrl, PATHINFO_EXTENSION);

            // Delete existing images if any
            $product->deleteImages();

            // Add new image
            $image = new Image();
            $image->id_product = $product->id;
            $image->position = 1;
            $image->cover = true;

            // Save the image to the correct directory
            $imagePath = _PS_PROD_IMG_DIR_ . $image->getImgPath() . '.' . $image->image_format;
            $this->createDirectoryIfNotExists(dirname($imagePath));
            if (!copy($tmpFile, $imagePath)) {
                unlink($tmpFile);
                throw new Exception("Failed to save image to: " . $imagePath);
            }

            // Associate the image with the product
            if (!$image->add()) {
                unlink($tmpFile);
                throw new Exception("Failed to add image to product");
            }

            // Manually resize the image and generate thumbnails
            $this->resizeImage($imagePath, _PS_PROD_IMG_DIR_ . $image->getImgPath() . '-home_default.' . $image->image_format, 250, 250);
            $this->resizeImage($imagePath, _PS_PROD_IMG_DIR_ . $image->getImgPath() . '-large_default.' . $image->image_format, 800, 800);

            // Cleanup
            unlink($tmpFile);
            EcomZoneLogger::log("Imported product image", 'INFO', [
                'sku' => $product->reference,
                'image' => $imageUrl
            ]);

        } catch (Exception $e) {
            EcomZoneLogger::log("Error importing product image", 'ERROR', [
                'sku' => $product->reference,
                'image' => $imageUrl,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function createDirectoryIfNotExists($directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
