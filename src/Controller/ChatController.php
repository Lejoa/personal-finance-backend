<?php

namespace App\Controller;

use App\DTO\ChatRequestDTO;
use App\Entity\ChatConversation;
use App\Entity\ChatMessage;
use App\Entity\User;
use App\Service\ChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/chat')]
#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    /**
     * Injects service and infrastructure dependencies via constructor promotion.
     */
    public function __construct(
        private readonly ChatService $chatService,
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Processes a user message and returns the LLM-generated assistant response.
     * Returns 422 if the message is rejected by safety guardrails.
     * Returns 400 if the request body fails DTO validation.
     */
    #[Route('', name: 'api_chat_send', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $dto = $this->serializer->deserialize($request->getContent(), ChatRequestDTO::class, 'json');

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validation failed', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $response = $this->chatService->processMessage($dto, $user);

            return $this->json([
                'id'             => $response->id,
                'message'        => $response->message,
                'role'           => $response->role,
                'timestamp'      => $response->timestamp,
                'conversationId' => $response->conversationId,
                'metadata'       => $response->metadata,
            ]);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 422) {
                return $this->json(
                    ['error' => 'message_rejected', 'message' => $e->getMessage()],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            return $this->json(
                ['error' => 'Error processing message', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error processing message', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Returns all conversations for the authenticated user.
     * The response envelope follows the project-standard "data / total" shape.
     */
    #[Route('/conversations', name: 'api_chat_conversations', methods: ['GET'])]
    public function conversations(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $conversations = $this->chatService->getUserConversations($user);

        return $this->json([
            'data'  => array_map(fn(ChatConversation $conv) => $this->serializeConversation($conv), $conversations),
            'total' => count($conversations),
        ]);
    }

    /**
     * Returns a single conversation with all its messages for the authenticated user.
     * Returns 404 if the conversation does not exist or belongs to a different user.
     */
    #[Route('/conversations/{id}', name: 'api_chat_conversation_show', methods: ['GET'])]
    public function getConversation(int $id): JsonResponse
    {
        /** @var User $user */
        $user         = $this->getUser();
        $conversation = $this->chatService->getConversation($id, $user);

        if ($conversation === null) {
            return $this->json(['error' => 'Conversation not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'conversation' => $this->serializeConversation($conversation),
            'messages'     => array_map(
                fn(ChatMessage $msg) => $this->serializeMessage($msg),
                $conversation->getMessages()->toArray()
            ),
        ]);
    }

    /**
     * Deletes a conversation and all its messages.
     * Returns 404 if the conversation does not exist or belongs to a different user.
     */
    #[Route('/conversations/{id}', name: 'api_chat_conversation_delete', methods: ['DELETE'])]
    public function deleteConversation(int $id): JsonResponse
    {
        /** @var User $user */
        $user         = $this->getUser();
        $conversation = $this->chatService->getConversation($id, $user);

        if ($conversation === null) {
            return $this->json(['error' => 'Conversation not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->chatService->deleteConversation($conversation);

            return $this->json(['message' => 'Conversation deleted successfully']);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error deleting conversation', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Converts a ChatConversation entity into a plain array for JSON serialization.
     * The response shape is a stable public API contract — do not change field names.
     */
    private function serializeConversation(ChatConversation $conversation): array
    {
        return [
            'id'        => $conversation->getId(),
            'title'     => $conversation->getTitle(),
            'createdAt' => $conversation->getCreatedAt()->format('c'),
            'updatedAt' => $conversation->getUpdatedAt()->format('c'),
        ];
    }

    /**
     * Converts a ChatMessage entity into a plain array for JSON serialization.
     * The response shape is a stable public API contract — do not change field names.
     */
    private function serializeMessage(ChatMessage $message): array
    {
        return [
            'id'        => $message->getId(),
            'content'   => $message->getContent(),
            'role'      => $message->getRole(),
            'metadata'  => $message->getMetadata(),
            'createdAt' => $message->getCreatedAt()->format('c'),
        ];
    }

    /**
     * Formats a Symfony ConstraintViolationList into a key-value map of field → message.
     * Used to produce structured 400 responses for invalid request bodies.
     */
    private function formatErrors(ConstraintViolationListInterface $errors): array
    {
        $formatted = [];
        foreach ($errors as $error) {
            $formatted[$error->getPropertyPath()] = $error->getMessage();
        }
        return $formatted;
    }
}
