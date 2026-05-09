<?php

namespace App\Tests\Unit\Controller;

use App\Controller\BudgetController;
use App\DTO\CreateBudgetRequest;
use App\DTO\UpdateBudgetRequest;
use App\Service\BudgetService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BudgetControllerTest extends AbstractControllerTestCase
{
    private BudgetService&MockObject $budgetService;
    private SerializerInterface&MockObject $serializer;
    private ValidatorInterface&MockObject $validator;
    private BudgetController&MockObject $controller;

    protected function setUp(): void
    {
        $this->budgetService = $this->createMock(BudgetService::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->controller = $this->makeControllerMock(
            BudgetController::class,
            [$this->budgetService, $this->serializer, $this->validator],
        );
    }

    public function testCreateReturns201WhenValid(): void
    {
        $user = $this->makeUser();
        $budget = $this->makeBudget($user);
        $dto = new CreateBudgetRequest();

        $this->controller->method('getUser')->willReturn($user);
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->budgetService->method('createBudget')->willReturn($budget);
        $this->budgetService->method('getUserBudgets')->willReturn([]);

        $response = $this->controller->create($this->makeRequest(['startDate' => '2025-01-01']));

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testCreateReturns400WhenValidationFails(): void
    {
        $dto = new CreateBudgetRequest();
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations(['startDate' => 'Required.']));

        $response = $this->controller->create($this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testCreateReturns400WhenServiceThrowsInvalidArgument(): void
    {
        $dto = new CreateBudgetRequest();
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->budgetService->method('createBudget')
            ->willThrowException(new \InvalidArgumentException('Category with ID 999 does not exist.'));

        $response = $this->controller->create($this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('999', $data['error']);
    }

    public function testCreateReturns500WhenServiceThrowsGenericException(): void
    {
        $dto = new CreateBudgetRequest();
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->budgetService->method('createBudget')->willThrowException(new \Exception('DB error'));

        $response = $this->controller->create($this->makeRequest([]));

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testListReturnsAllBudgets(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgets')->willReturn([
            $this->makeBudget($user, 1),
            $this->makeBudget($user, 2),
        ]);

        $response = $this->controller->list();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
    }

    public function testGetReturns200WhenFound(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($this->makeBudget($user));

        $response = $this->controller->get(1);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testGetReturns404WhenNotFound(): void
    {
        $this->budgetService->method('getUserBudgetById')->willReturn(null);

        $response = $this->controller->get(99);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Budget not found', $data['error']);
    }

    public function testUpdatePutReturns200WhenValid(): void
    {
        $user = $this->makeUser();
        $budget = $this->makeBudget($user);
        $dto = new UpdateBudgetRequest();

        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($budget);
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->budgetService->method('updateBudget')->willReturn($budget);

        $response = $this->controller->updatePut(1, $this->makeRequest([]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdatePatchReturns200WhenValid(): void
    {
        $user = $this->makeUser();
        $budget = $this->makeBudget($user);
        $dto = new UpdateBudgetRequest();

        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($budget);
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->budgetService->method('updateBudget')->willReturn($budget);

        $response = $this->controller->updatePatch(1, $this->makeRequest([]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateReturns404WhenBudgetNotFound(): void
    {
        $this->budgetService->method('getUserBudgetById')->willReturn(null);

        $response = $this->controller->updatePut(99, $this->makeRequest([]));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testUpdateReturns400WhenValidationFails(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($this->makeBudget($user));
        $this->serializer->method('deserialize')->willReturn(new UpdateBudgetRequest());
        $this->validator->method('validate')->willReturn($this->makeViolations(['startDate' => 'Invalid date.']));

        $response = $this->controller->updatePut(1, $this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testDeleteReturns200WhenFound(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($this->makeBudget($user));
        $this->budgetService->expects($this->once())->method('deleteBudget');

        $response = $this->controller->delete(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('deleted', $data['message']);
    }

    public function testDeleteReturns404WhenNotFound(): void
    {
        $this->budgetService->method('getUserBudgetById')->willReturn(null);

        $response = $this->controller->delete(99);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testUpdateCategoryReturns200WhenValid(): void
    {
        $user = $this->makeUser();
        $category = $this->makeCategory();
        $budget = $this->makeBudget($user);
        $bc = $this->makeBudgetCategory($budget, $category, 1, 300.0);

        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($budget);
        $this->budgetService->method('findCategoryInBudget')->willReturn($bc);
        $this->budgetService->method('updateBudgetCategoryAmount')->willReturn($bc);

        $response = $this->controller->updateCategory(1, 1, $this->makeRequest(['amount' => 300.0]));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('amount', $data);
    }

    public function testUpdateCategoryReturns404WhenBudgetNotFound(): void
    {
        $this->budgetService->method('getUserBudgetById')->willReturn(null);

        $response = $this->controller->updateCategory(99, 1, $this->makeRequest(['amount' => 100]));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testUpdateCategoryReturns404WhenCategoryNotFound(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($this->makeBudget($user));
        $this->budgetService->method('findCategoryInBudget')->willReturn(null);

        $response = $this->controller->updateCategory(1, 99, $this->makeRequest(['amount' => 100]));

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Budget category not found', $data['error']);
    }

    public function testUpdateCategoryReturns400WhenAmountIsMissing(): void
    {
        $user = $this->makeUser();
        $category = $this->makeCategory();
        $budget = $this->makeBudget($user);
        $bc = $this->makeBudgetCategory($budget, $category);

        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($budget);
        $this->budgetService->method('findCategoryInBudget')->willReturn($bc);

        $response = $this->controller->updateCategory(1, 1, $this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testUpdateCategoryReturns400WhenAmountIsZero(): void
    {
        $user = $this->makeUser();
        $category = $this->makeCategory();
        $budget = $this->makeBudget($user);
        $bc = $this->makeBudgetCategory($budget, $category);

        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($budget);
        $this->budgetService->method('findCategoryInBudget')->willReturn($bc);

        $response = $this->controller->updateCategory(1, 1, $this->makeRequest(['amount' => 0]));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testUpdateCategoryReturns400WhenAmountIsNegative(): void
    {
        $user = $this->makeUser();
        $category = $this->makeCategory();
        $budget = $this->makeBudget($user);
        $bc = $this->makeBudgetCategory($budget, $category);

        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($budget);
        $this->budgetService->method('findCategoryInBudget')->willReturn($bc);

        $response = $this->controller->updateCategory(1, 1, $this->makeRequest(['amount' => -50]));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testDeleteCategoryReturns200WhenValid(): void
    {
        $user = $this->makeUser();
        $category = $this->makeCategory();
        $budget = $this->makeBudget($user);
        $bc = $this->makeBudgetCategory($budget, $category);

        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($budget);
        $this->budgetService->method('findCategoryInBudget')->willReturn($bc);
        $this->budgetService->expects($this->once())->method('removeBudgetCategory');

        $response = $this->controller->deleteCategory(1, 1);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDeleteCategoryReturns404WhenBudgetNotFound(): void
    {
        $this->budgetService->method('getUserBudgetById')->willReturn(null);

        $response = $this->controller->deleteCategory(99, 1);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteCategoryReturns404WhenCategoryNotFound(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($this->makeBudget($user));
        $this->budgetService->method('findCategoryInBudget')->willReturn(null);

        $response = $this->controller->deleteCategory(1, 99);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testCategoryFeedbackReturns200(): void
    {
        $user = $this->makeUser();
        $category = $this->makeCategory();
        $budget = $this->makeBudget($user);
        $bc = $this->makeBudgetCategory($budget, $category);

        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($budget);
        $this->budgetService->method('findCategoryInBudget')->willReturn($bc);
        $this->budgetService->method('buildFormativeFeedback')->willReturn('You are within budget!');

        $response = $this->controller->categoryFeedback(1, 1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('feedback', $data);
    }

    public function testCategoryFeedbackReturns404WhenBudgetNotFound(): void
    {
        $this->budgetService->method('getUserBudgetById')->willReturn(null);

        $response = $this->controller->categoryFeedback(99, 1);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testCategoryFeedbackReturns404WhenCategoryNotFound(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->budgetService->method('getUserBudgetById')->willReturn($this->makeBudget($user));
        $this->budgetService->method('findCategoryInBudget')->willReturn(null);

        $response = $this->controller->categoryFeedback(1, 99);

        $this->assertSame(404, $response->getStatusCode());
    }
}
