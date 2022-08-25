<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProductController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    #[Route('/products', name: 'products_index', methods: ["GET"])]
    public function index(Request $request): Response
    {
        $productsResponse = $this->client->request("GET", "{$this->getParameter('websiteUrl')}/api/products");

        if ($request->query->count()) {
            $name = $request->query->get('name');
            $sort = $request->query->get('sort');
            $order = match ($sort) {
                'speed_desc' => '&order[maxSpeed]=desc',
                'speed_asc' => '&order[maxSpeed]=asc',
                'price_desc' => '&order[price]=desc',
                'price_asc' => '&order[price]=asc',
                'default', null => '',
            };

            $productsResponse = $this->client->request("GET", "{$this->getParameter('websiteUrl')}/api/products?name={$name}{$order}");
        }

        if ($productsResponse->getStatusCode() === 200) {
            $products = $productsResponse->toArray();
        }

        return $this->render('products/index.html.twig', [
            'products' => $products['hydra:member'],
            'name' => $name ?? null,
            'sort' => $sort ?? null
        ]);
    }

    #[Route('/product/{id}', name: 'product_show', methods: ["GET"])]
    public function show(int $id): Response
    {
        $productResponse = $this->client->request("GET", "{$this->getParameter('websiteUrl')}/api/products/{$id}");

        if ($productResponse->getStatusCode() === 200) {
            $product = $productResponse->toArray();
        }

        return $this->render('products/show.html.twig', [
            'product' => $product
        ]);
    }
}