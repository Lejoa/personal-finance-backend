<?php

namespace App\Controller;

use App\DTO\DevTokenRequest;
use App\Service\DevAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/dev')]
class DevAuthController extends AbstractController
{
    public function __construct(
        private readonly DevAuthService $devAuthService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Generate JWT token for development
     */
    #[Route('/token', name: 'api_dev_token', methods: ['POST'])]
    public function generateToken(Request $request): JsonResponse
    {
        if ($this->getParameter('kernel.environment') !== 'dev') {
            return $this->json(
                ['error' => 'This endpoint is only available in development environment'],
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                DevTokenRequest::class,
                'json'
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validación fallida', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $result = $this->devAuthService->generateDevToken($dto);

            return $this->json($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al generar token', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get current user info for testing
     */
    #[Route('/me', name: 'api_dev_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(): JsonResponse
    {
        if ($this->getParameter('kernel.environment') !== 'dev') {
            return $this->json(
                ['error' => 'This endpoint is only available in development environment'],
                Response::HTTP_FORBIDDEN
            );
        }

        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName()
        ]);
    }

    /**
     * Format validation errors
     */
    private function formatErrors($errors): array
    {
        $formatted = [];
        foreach ($errors as $error) {
            $formatted[$error->getPropertyPath()] = $error->getMessage();
        }
        return $formatted;
    }
}
