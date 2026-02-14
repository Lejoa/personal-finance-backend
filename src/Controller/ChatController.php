<?php

namespace App\Controller;

use App\DTO\ChatRequestDTO;
use App\Entity\User;
use App\Service\ChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/chat')]
#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer
    ) {}

    /**
     *  Send a message and get LLM response
     */
    #[Route('', name: 'api_chat_send', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                ChatRequestDTO::class,
                'json'
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(
                    [   'error' => 'Validación fallida', 
                        'violations' => $this->formatErrors($errors)
                    ], 
                    Response::HTTP_BAD_REQUEST
                );
            }

            $response = $this->chatService->processMessage($dto, $user);

            return $this->json([
                'id' => $response->id,
                'message' => $response->message,
                'role' => $response->role,
                'timestamp' => $response->timestamp,
                'conversationId' => $response->conversationId,
                'metadata' => $response->metadata,
            ]);

        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al procesar mensaje', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * List all conversations for the authenticated user
     */
    #[Route('/conversations', name: 'api_chat_conversations', methods: ['GET'])]
    public function conversations(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $conversations = $this->chatService->getUserConversations($user);

        return $this->json([
            'data' => array_map(
                fn($conv) => $this->serializeConversation($conv),
                $conversations
            ),
            'total' => count($conversations)
        ]);
    }

    /**
     * Get a specific conversation with its messages
     */
    #[Route('/conversations/{id}', name:'api_chat_conversation_show', methods: ['GET'])]
    public function getConversation(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $conversation = $this->chatService->getConversation($id, $user);

        if(!$conversation) {
            return $this->json(
                ['error' => 'Conversación no encontrada'], 
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json([
            'conversation' => $this->serializeConversation($conversation),
            'messages' => array_map(
                fn($msg) => $this->serializeMessage($msg),
                $conversation->getMessages()->toArray()
            )
        ]);
    }

    /**
     * Delete a conversation and all its messages
     */
    #[Route('/conversations/{id}', name: 'api_chat_conversation_delete', methods: ['DELETE'])]
    public function deleteConversation(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $conversation = $this->chatService->getConversation($id, $user);

        if (!$conversation) {
            return $this->json(
                ['error' => 'Conversación no encontrada'],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $this->chatService->deleteConversation($conversation);

            return $this->json([
                'message' => 'Conversación eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al eliminar conversación', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
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

    /**
     * Serialize conversation to array
     */
    private function serializeConversation($conversation): array
    {
        return [
            'id' => $conversation->getId(),
            'title' => $conversation->getTitle(),
            'createdAt' => $conversation->getCreatedAt()->format('c'),
            'updatedAt' => $conversation->getUpdatedAt()->format('c'),
        ];
    }

    /**
     * Serialize message to array
     */
    private function serializeMessage($message): array
    {
        return [
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'role' => $message->getRole(),
            'metadata' => $message->getMetadata(),
            'createdAt' => $message->getCreatedAt()->format('c'),
        ];
    }
}