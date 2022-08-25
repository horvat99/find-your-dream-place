<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AdminController extends AbstractController
{
    private HttpClientInterface $client;
    private SluggerInterface $slugger;
    private array $allowedExtensions = [
        'image/jpeg', 'image/png'
    ];

    public function __construct(HttpClientInterface $client, SluggerInterface $slugger)
    {
        $this->client = $client;
        $this->slugger = $slugger;
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        $productsResponse = $this->client->request("GET", "{$this->getParameter('websiteUrl')}/api/products");

        if ($productsResponse->getStatusCode() === 200) {
            $products = $productsResponse->toArray();
        }

        return $this->render('/admin/index.html.twig', [
            'products' => $products['hydra:member']
        ]);
    }

    #[Route('/admin/insert', name: 'admin_insert')]
    public function insert(Request $request): Response
    {
        if ($request->getMethod() === "POST") {

            $name = $request->request->get('name');
            $price = $request->request->get('price');
            $speed = $request->request->get('speed');
            $file = $request->files->get('file');

            // Validate picture
            if (!isset($file)) {
                $this->addFlash('success', 'Picture is mandatory');
                return $this->redirectToRoute('admin_insert');
            }

            // Validate fields
            if (empty($name) || empty($price) || empty($speed)) {
                $this->addFlash('success', 'Name, price and speed fields are mandatory');
                return $this->redirectToRoute('admin_insert');
            }

            // Validate extensions
            if (!in_array($file->getMimeType(), $this->allowedExtensions, true)) {
                $this->addFlash('success', 'Only png and jpg are allowed');
                return $this->redirectToRoute('admin_insert');
            }

            // move to public folder
            $newFilename = $this->upload($file);

            // Upload to database
            $this->client->request("POST", "{$this->getParameter('websiteUrl')}/api/products", [
                'json' => [
                    'name' => $name,
                    'price' => (int)$price,
                    'maxSpeed' => (int)$speed,
                    'filename' => $newFilename,
                ]
            ]);

            return $this->redirectToRoute('admin');
        }

        return $this->render('/admin/insert.html.twig');
    }

    #[Route('/admin/edit/{id}', name:'admin_edit')]
    public function edit(int $id, Request $request ): Response
    {
        $productResponse = $this->client->request("GET", "{$this->getParameter('websiteUrl')}/api/products/{$id}");
        $product = $productResponse->toArray();

        if ($request->getMethod() === "POST") {
            $name = $request->request->get('name');
            $price = $request->request->get('price');
            $speed = $request->request->get('speed');

            $file = $request->files->get('file');

            // Validate fields
            if (empty($name) || empty($price) || empty($speed)) {
                $this->addFlash('success', 'Name, price and speed fields are mandatory');
                return $this->redirectToRoute('admin_edit', [
                    'id' => $id
                ]);
            }

            if ($file) {
                if (!in_array($file->getMimeType(), $this->allowedExtensions, true)) {
                    $this->addFlash('success', 'Only png and jpg are allowed');

                    return $this->redirectToRoute('admin_edit', [
                        'id' => $id
                    ]);
                }
                $newFilename = $this->upload($file);

                $filesystem = new Filesystem();
                $filesystem->remove("uploads/images/{$product['filename']}");
            }

            $this->client->request("PUT", "{$this->getParameter('websiteUrl')}/api/products/$id", [
                'json' => [
                    'name' => $name,
                    'price' => (int)$price,
                    'maxSpeed' => (int)$speed,
                    'filename' => $file ? $newFilename : $product['filename'],
                ]
            ]);

            return $this->redirectToRoute('admin');
        }

        return $this->render('/admin/edit.html.twig', [
            'product' => $product
        ]);
    }

    private function upload($file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        // this is needed to safely include the file name as part of the URL
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $file->guessExtension();

        // Move the file to the directory where pictures are stored
        try {
            $file->move(
                $this->getParameter('images_directory'),
                $newFilename
            );
        } catch (FileException $e) {
            dd($e);
        }

        return $newFilename;
    }

    #[Route('/admin/delete', methods: ["POST"])]
    public function delete(Request $request): JsonResponse
    {
        $id = $request->request->get('id');

        $productResponse = $this->client->request("GET", "{$this->getParameter('websiteUrl')}/api/products/{$id}");
        $product = $productResponse->toArray();

        $filesystem = new Filesystem();
        $filesystem->remove("uploads/images/{$product['filename']}");

        $this->client->request("DELETE", "{$this->getParameter('websiteUrl')}/api/products/{$id}");

        return new JsonResponse('ok');
    }
}
