<?php

namespace App\Tests\Unit\Controller;

use App\Controller\CategoryController;
use App\DTO\CreateCategoryRequest;
use App\DTO\UpdateCategoryRequest;
use App\Service\CategoryService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CategoryControllerTest extends AbstractControllerTestCase
{
    private CategoryService&MockObject $categoryService;
    private SerializerInterface&MockObject $serializer;
    private ValidatorInterface&MockObject $validator;
    private CategoryController&MockObject $controller;

    protected function setUp(): void
    {
        $this->categoryService = $this->createMock(CategoryService::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->controller = $this->makeControllerMock(
            CategoryController::class,
            [$this->categoryService, $this->serializer, $this->validator],
        );
    }

    public function testCreateReturns201WhenValid(): void
    {
        $dto = new CreateCategoryRequest();
        $category = $this->makeCategory();

        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->categoryService->method('createCategory')->willReturn($category);

        $response = $this->controller->create($this->makeRequest(['name' => 'Food', 'type' => 'gasto']));

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('category', $data);
    }

    public function testCreateReturns400WhenValidationFails(): void
    {
        $this->serializer->method('deserialize')->willReturn(new CreateCategoryRequest());
        $this->validator->method('validate')->willReturn($this->makeViolations(['name' => 'Required.']));

        $response = $this->controller->create($this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testCreateReturns500WhenServiceThrows(): void
    {
        $this->serializer->method('deserialize')->willReturn(new CreateCategoryRequest());
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->categoryService->method('createCategory')->willThrowException(new \Exception('DB error'));

        $response = $this->controller->create($this->makeRequest([]));

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testGetAllReturnsDataAndTotal(): void
    {
        $this->categoryService->method('getAllCategories')->willReturn([
            $this->makeCategory(1, 'Food'),
            $this->makeCategory(2, 'Transport'),
        ]);

        $response = $this->controller->getAll(new Request());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['data']);
    }

    public function testGetAllWithTypeAndNameFilters(): void
    {
        $this->categoryService
            ->expects($this->once())
            ->method('getAllCategories')
            ->with('gasto', 'Food')
            ->willReturn([]);

        $request = new Request(['type' => 'gasto', 'name' => 'Food']);
        $this->controller->getAll($request);
    }

    public function testShowReturns200WhenFound(): void
    {
        $this->categoryService->method('getCategoryById')->willReturn($this->makeCategory());

        $response = $this->controller->show(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('category', $data);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $this->categoryService->method('getCategoryById')->willReturn(null);

        $response = $this->controller->show(99);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Category not found', $data['error']);
    }

    public function testUpdateReturns200WhenValid(): void
    {
        $category = $this->makeCategory();
        $dto = new UpdateCategoryRequest();

        $this->categoryService->method('getCategoryById')->willReturn($category);
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->categoryService->method('updateCategory')->willReturn($category);

        $response = $this->controller->update(1, $this->makeRequest(['name' => 'Updated']));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('category', $data);
    }

    public function testUpdateReturns404WhenNotFound(): void
    {
        $this->categoryService->method('getCategoryById')->willReturn(null);

        $response = $this->controller->update(99, $this->makeRequest([]));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testUpdateReturns400WhenValidationFails(): void
    {
        $this->categoryService->method('getCategoryById')->willReturn($this->makeCategory());
        $this->serializer->method('deserialize')->willReturn(new UpdateCategoryRequest());
        $this->validator->method('validate')->willReturn($this->makeViolations(['name' => 'Too short.']));

        $response = $this->controller->update(1, $this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testDeleteReturns200WhenFound(): void
    {
        $this->categoryService->method('getCategoryById')->willReturn($this->makeCategory());
        $this->categoryService->expects($this->once())->method('deleteCategory');

        $response = $this->controller->delete(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('deleted', $data['message']);
    }

    public function testDeleteReturns404WhenNotFound(): void
    {
        $this->categoryService->method('getCategoryById')->willReturn(null);

        $response = $this->controller->delete(99);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteReturns409WhenCategoryHasTransactions(): void
    {
        $this->categoryService->method('getCategoryById')->willReturn($this->makeCategory());
        $this->categoryService->method('deleteCategory')
            ->willThrowException(new \InvalidArgumentException('Cannot delete: category has transactions.'));

        $response = $this->controller->delete(1);

        $this->assertSame(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('transactions', $data['error']);
    }
}
