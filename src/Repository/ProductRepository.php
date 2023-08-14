<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Repository\ProductRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

class ProductRepository extends ServiceEntityRepository implements ProductRepositoryInterface
{
    private EntityManagerInterface $entityManager;

    /**
     * @param ManagerRegistry $registry
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        ManagerRegistry $registry,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($registry, Product::class);
        $this->entityManager = $entityManager;
    }

    /**
     * @param Product $product
     * @return void
     */
    public function save(Product $product): void
    {
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    /**
     * @param Product $product
     * @return void
     */
    public function update(Product $product): void
    {
        // Updating a product should be done automatically by Doctrine
        // since it's being managed by the entity manager
        $this->entityManager->flush();
    }

    /**
     * @param Product $product
     * @return void
     */
    public function delete(Product $product): void
    {
        $this->entityManager->remove($product);
        $this->entityManager->flush();
    }
}
