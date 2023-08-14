<?php

declare(strict_types=1);

namespace App\Service;

use Exception;
use App\Entity\Product;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;
use App\Repository\ProductRepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ProductParser
{
    private ProductRepositoryInterface $productRepository;

    private HttpClientInterface $httpClient;

    private LoggerInterface $logger;

    private string $projectDir;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param LoggerInterface $logger
     * @param string $projectDir
     * @param HttpClientInterface $httpClient
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger,
        string $projectDir,
        HttpClientInterface $httpClient
    ) {
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
        $this->httpClient = $httpClient;
    }

    /**
     * @param string $url
     * @return int|null
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    public function parseProduct(string $url): ?int
    {
        try {
            // send an HTTP request and get the HTML code of the page
            $httpClient = $this->httpClient;
            $response = $httpClient->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Failed to fetch the product page. HTTP response code: '
                    . $response->getStatusCode());
                throw new Exception('Failed to fetch the product page. HTTP response code: '
                    . $response->getStatusCode());
            }
            $htmlContent = $response->getContent();

            // Initialize DOMCrawler for HTML Parsing
            $crawler = new Crawler($htmlContent);

            // set product name
            if (!$crawler->filter('h1')->count()) {
                throw new Exception("H1 tag not found!");
            }

            $name = $crawler->filter('h1')->text();

            $priceElement1 = $crawler->filter('#detailText div.price-detail div.buy-buttons div.pricecetelemnew')
                ->first();
            $priceElement2 = $crawler->filter('.js-price-box.price-box--Normal span')->eq(1);
            $priceElement3 = $crawler->filter('#detailText .price-box--News .price-box__prices span span');

            //take a valid price
            $price = match (true) {
                $priceElement1->count() > 0 => (float) $priceElement1->attr('data-price'),
                $priceElement2->count() > 0 || $priceElement3->count() > 0
                => (float) preg_replace('/[^\d.]/', '', $priceElement2->count()
                    ? $priceElement2->text() : $priceElement3->text()),
                default => 0.0,
            };

//        $imageElement = $crawler->filter('#tabs div.tabsStickyBg div div img');
            $imageElement = $crawler->filter('#tabs img');
            $imageSrc = $imageElement->attr('src');

            // Checking the availability of images in different sizes
            $imageSrcLarge = str_replace('/f1/', '/f16/', $imageSrc);
            $imageResponse = $httpClient->request('HEAD', $imageSrcLarge);

            //if a large image is available, use it
            if ($imageResponse->getStatusCode() === 200) {
                $imageSrc = $imageSrcLarge;
            }

            // product description
            $nameElement = $crawler->filter('#detailText > div.nameextc > span');

            if ($nameElement->count() > 0) {
                $description = $nameElement->text();
            } else {
                throw new Exception('Description element not found!');
            }

            //Create a new product and fill in the fields
            $product = new Product();
            $product->setName($name);
            $product->setPrice($price);
            $product->setDescription($description);

            //Add logic to load and save an image with Symfony Filesystem
            if ($imageSrc) {
                $imageUrl = $imageSrc;
//                $imageContent = @file_get_contents($imageUrl);
                $imageResponse = $httpClient->request('GET', $imageUrl);
                $imageContent = $imageResponse->getContent();

                if ($imageContent === false) {
                    $this->logger->error('Failed to fetch the image content from URL: ' . $imageUrl);
                    throw new Exception('Failed to fetch the image content');
                }

                $filesystem = new Filesystem();

                $imageDirectory = $this->projectDir . '/public/images/products';
                $lowercaseName = str_replace(' ', '_', strtolower($name));
                $imagePath = $imageDirectory . '/' . $lowercaseName . '.jpg';

                try {
                    $filesystem->dumpFile($imagePath, $imageContent);
                } catch (Exception $e) {
                    $this->logger->error('Failed to save the image to file system: ' . $e->getMessage());
                    throw new Exception('Failed to save the image to file system');
                }

                //set general image
                $product->setPhoto('/images/products/' . $lowercaseName . '.jpg');

                //save product
                $this->productRepository->save($product);

                if (!$product->getId()) {
                    throw new Exception("Product id not found!");
                }
            }
            $this->logger->info('product successfully saved. Product ID = ' . $product->getId());

            return $product->getId();
        } catch (Exception $e) {
            echo "Exception caught: " . $e->getMessage() . "\n";
            $this->logger->error('An error occurred during product parsing: ' . $e->getMessage());
            return null;
        }
    }
}