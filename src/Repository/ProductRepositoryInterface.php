<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;

interface ProductRepositoryInterface
{
    /**
     * @param Product $product
     * @return void
     */
    public function save(Product $product): void;

    /**
     * @param Product $product
     * @return void
     */
    public function update(Product $product): void;

    /**
     * @param Product $product
     * @return void
     */
    public function delete(Product $product): void;
}