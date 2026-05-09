<?php

namespace App\Tests\Unit\Controller;

use App\Entity\Budget;
use App\Entity\BudgetCategory;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

abstract class AbstractControllerTestCase extends TestCase
{
    protected function makeUser(int $id = 1, string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName('Test User');

        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);

        return $user;
    }

    protected function makeCategory(int $id = 1, string $name = 'Food', string $type = 'gasto'): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setType($type);

        $ref = new \ReflectionProperty(Category::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($category, $id);

        return $category;
    }

    protected function makeTransaction(User $user, int $id = 1): Transaction
    {
        $transaction = new Transaction();
        $transaction->setName('Test Transaction');
        $transaction->setType('gasto');
        $transaction->setAmount(100.0);
        $transaction->setUser($user);

        $ref = new \ReflectionProperty(Transaction::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($transaction, $id);

        return $transaction;
    }

    protected function makeBudget(User $user, int $id = 1): Budget
    {
        $budget = new Budget();
        $budget->setUser($user);

        $ref = new \ReflectionProperty(Budget::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($budget, $id);

        return $budget;
    }

    protected function makeBudgetCategory(Budget $budget, Category $category, int $id = 1, float $amount = 500.0): BudgetCategory
    {
        $bc = new BudgetCategory();
        $bc->setBudget($budget);
        $bc->setCategory($category);
        $bc->setAmount($amount);

        $ref = new \ReflectionProperty(BudgetCategory::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($bc, $id);

        return $bc;
    }

    protected function makeViolations(array $fieldsAndMessages = []): ConstraintViolationList
    {
        $list = new ConstraintViolationList();
        foreach ($fieldsAndMessages as $field => $message) {
            $list->add(new ConstraintViolation($message, '', [], null, $field, null));
        }
        return $list;
    }

    protected function makeRequest(array $body = [], array $query = []): Request
    {
        $request = new Request(
            $query,
            [],
            [],
            [],
            [],
            [],
            json_encode($body)
        );
        $request->headers->set('Content-Type', 'application/json');
        return $request;
    }

    /**
     * Creates a partial mock of a controller that stubs getUser(), json(),
     * denyAccessUnlessGranted(), and getParameter().
     */
    protected function makeControllerMock(
        string $class,
        array $constructorArgs,
        ?User $user = null,
        string $environment = 'test'
    ): MockObject {
        $mock = $this->getMockBuilder($class)
            ->setConstructorArgs($constructorArgs)
            ->onlyMethods(['getUser', 'json', 'denyAccessUnlessGranted', 'getParameter'])
            ->getMock();

        $mock->method('getUser')->willReturn($user ?? $this->makeUser());

        $mock->method('json')->willReturnCallback(
            fn ($data, int $status = 200, array $headers = []) => new JsonResponse($data, $status, $headers)
        );

        $mock->method('denyAccessUnlessGranted')->willReturnCallback(function () {});

        $mock->method('getParameter')->willReturnCallback(
            fn (string $name) => $name === 'kernel.environment' ? $environment : null
        );

        return $mock;
    }
}
