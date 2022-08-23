<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    #[Route('/products', name: 'products_index', methods: ["GET"])]
    public function index(): Response
    {
        return $this->render('products/index.html.twig');
    }

    #[Route('/product/{id}', name: 'products_show', methods: ["GET"])]
    public function show(int $id): Response
    {
        return $this->render('products/show.html.twig', [
            'productId' => $id
        ]);
    }
}