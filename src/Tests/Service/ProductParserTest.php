<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ProductParser;
use App\Entity\Product;
use App\Repository\ProductRepositoryInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpClient\MockHttpClient;

class ProductParserTest extends TestCase
{
    /**
     * @return void
     * @throws Exception
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function testParseProduct(): void
    {
        // create mock objects
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        // setting behavior mock objects
        $productRepository->expects($this->once())
            ->method('save');

        $logger->expects($this->once())
            ->method('info');

        // test example HTML response
        $fakeHtml = '
            <html>
                <head></head>
                <body>
                    <h1>Test Product</h1>
                    <div id="detailText">
                        <div class="price-detail">
                            <div class="buy-buttons">
                                <div class="pricecetelemnew" data-price="99.99"></div>
                            </div>
                        </div>
                        <div class="js-price-box price-box--Normal">
                            <span></span>
                            <span>199.99</span>
                        </div>
                        <div class="price-box--News">
                            <div class="price-box__prices">
                                <span></span>
                                <span>299.99</span>
                            </div>
                        </div>
                        <div class="nameextc">
                            <span>Product Description</span>
                        </div>
                    </div>
                    <div id="tabs">
                        <div class="tabsStickyBg">
                            <div>
                                <img src="image.jpg" />
                            </div>
                        </div>
                    </div>
                </body>
            </html>
        ';

        $fakeImageContent = 'some_binary_image_data';

        $httpClient->expects($this->exactly(3))
        ->method('request')
            ->willReturnCallback(function ($method, $url) use ($response, $fakeHtml, $fakeImageContent) {
                if ($method === 'GET' && $url === 'https://www.alza.cz/EN/gaming/game-console-d7806461.htm') {
                    return $response;
                }

                if ($method === 'GET' && $url === 'image.jpg') {
                    $imageResponse = $this->createMock(ResponseInterface::class);
                    $imageResponse->method('getContent')->willReturn($fakeImageContent);
                    return $imageResponse;
                }

                if ($method === 'HEAD' && str_contains($url, 'image.jpg')) {
                    return $response;
                }
            });

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn($fakeHtml);

        // Create ProductParser with mock objects
        $productParser = new ProductParser(
            $productRepository,
            $logger,
            '/home/user/sites/symfony-project',
            $httpClient
        );

        $productRepository->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Product $product) {
                $this->assertInstanceOf(Product::class, $product);
                $product->setId(1);  //set test ID for product
                return $product;
            });

        // Call method for test
        $productId = $productParser->parseProduct('https://www.alza.cz/EN/gaming/game-console-d7806461.htm');

        // End up check result
        $this->assertNotNull($productId);
        $this->assertIsInt($productId);
    }
}
