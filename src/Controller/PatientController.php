<?php

namespace App\Controller;

use App\Repository\PatientRepository;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PatientController extends AbstractController
{
    #[Route('/patient/{id}/qrcode', name: 'app_patient_qrcode', methods: ['GET'])]
    public function qrcode(int $id, PatientRepository $patientRepository): Response
    {
        $patient = $patientRepository->find($id);
        if (!$patient) {
            throw $this->createNotFoundException('Patient introuvable');
        }

        // Contenu du QR code : simple identifiant PATIENT-{id}
        $qrData = sprintf('PATIENT-%d', $patient->getIdPatient());

        $qrCode = new QrCode(data: $qrData, size: 250, margin: 10);
        $writer = new SvgWriter();
        $result = $writer->write($qrCode);

        return new Response(
            $result->getString(),
            Response::HTTP_OK,
            [
                'Content-Type' => $result->getMimeType(),
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }
}
