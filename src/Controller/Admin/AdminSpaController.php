<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminSpaController extends AbstractController
{
    #[Route('/admin', name: 'admin_root', methods: ['GET'])]
    #[Route('/admin/feeds', name: 'admin_feed_index', methods: ['GET'])]
    #[Route('/admin/feeds/new', name: 'admin_feed_new_page', methods: ['GET'])]
    #[Route('/admin/feeds/{id}/edit', name: 'admin_feed_edit_page', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function index(): Response
    {
        return $this->render('spa/index.html.twig');
    }
}
