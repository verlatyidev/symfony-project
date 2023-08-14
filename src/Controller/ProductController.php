<?php

declare(strict_types=1);

namespace App\Controller;

use Exception;
use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ProductParser as ProductParserService;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    private ProductRepositoryInterface $productRepository;

    private ProductParserService $productParserService;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ProductParserService $productParserService
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductParserService $productParserService
    ) {
        $this->productRepository = $productRepository;
        $this->productParserService = $productParserService;
    }

    /**
     * @Route("/", name="product_index", methods={"GET"})
     */
    public function index(): Response
    {
        $products = $this->productRepository->findAll();

        return $this->render('product/index.html.twig', ['products' => $products]);
    }

    /**
     * @Route("/new", name="product_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        //The process of adding a product IMAGE uses a direct link to an image from the Internet,
        //Important does not save to the project
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->productRepository->save($product);

            return $this->redirectToRoute('product_index');
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/custom_parse", name="custom", methods={"GET","POST"})
     */
    public function customForm(Request $request): Response
    {
        return $this->render('product/custom_parse.html.twig');
    }

    /**
     * @Route("/{id}", name="product_show", methods={"GET"})
     */
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', ['product' => $product]);
    }

    /**
     * @Route("/{id}/edit", name="product_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Product $product): Response
    {
        //The process of editing a product IMAGE uses a direct link to an image from the Internet,
        //Important does not save to the project
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->productRepository->update($product);

            return $this->redirectToRoute('product_index');
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="product_delete", methods={"DELETE", "POST"})
     */
    public function delete(Product $product): Response
    {
        $this->productRepository->delete($product);

        return $this->redirectToRoute('product_index');
    }

    /**
     * @Route("/{id}/delete", name="product_delete_confirmation", methods={"GET"})
     */
    public function deleteConfirmation(Product $product): Response
    {
        return $this->render('product/delete.html.twig', ['product' => $product]);
    }

    /**
     * @Route("/parse/process", name="parse_product_process", methods={"POST"})
     */
    public function parseProduct(Request $request): Response
    {
        $url = $request->request->get('product_url');

        try {
            $productId = $this->productParserService->parseProduct($url);
            return $this->redirectToRoute('product_show', ['id' => $productId]);
        } catch (Exception $e) {
            return $this->render('product/parse_error.html.twig', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @Route("/parse/error", name="parse_error", methods={"GET"})
     */
    public function parseError(): Response
    {
        return $this->render('product/parse_error.html.twig');
    }
}