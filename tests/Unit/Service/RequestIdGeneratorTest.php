<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Macpaw\SymfonyOtelBundle\Service\RequestIdGenerator;
use PHPUnit\Framework\TestCase;

class RequestIdGeneratorTest extends TestCase
{
    private const REQUEST_ID_PATTERN = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/';

    public function testGenerateReturnsUniqueRequestId(): void
    {
        $requestId1 = RequestIdGenerator::generate();
        $requestId2 = RequestIdGenerator::generate();

        $this->assertNotEquals($requestId1, $requestId2);
        $this->assertMatchesRegularExpression(self::REQUEST_ID_PATTERN, $requestId1);
        $this->assertMatchesRegularExpression(self::REQUEST_ID_PATTERN, $requestId2);
    }

    public function testGenerateCreatesDifferentPrefixesForDifferentHostnames(): void
    {
        $requestId1 = RequestIdGenerator::generate();
        $requestId2 = RequestIdGenerator::generate();

        $this->assertNotEquals($requestId1, $requestId2);
        $this->assertMatchesRegularExpression(self::REQUEST_ID_PATTERN, $requestId1);
        $this->assertMatchesRegularExpression(self::REQUEST_ID_PATTERN, $requestId2);
    }

    public function testGenerateFormatIsCorrect(): void
    {
        $requestId = RequestIdGenerator::generate();

        $this->assertMatchesRegularExpression(self::REQUEST_ID_PATTERN, $requestId);
    }
}
