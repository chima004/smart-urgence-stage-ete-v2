<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin');
        }
        if ($this->isGranted('ROLE_COORDINATEUR')) {
            return $this->redirectToRoute('app_coordinateur');
        }
        if ($this->isGranted('ROLE_SOIGNANT')) {
            return $this->redirectToRoute('app_soignant');
        }
        if ($this->isGranted('ROLE_TRIAGE')) {
            return $this->redirectToRoute('app_triage');
        }
        if ($this->isGranted('ROLE_ACCUEIL')) {
            return $this->redirectToRoute('app_accueil');
        }

        return $this->redirectToRoute('app_login');
    }
}
