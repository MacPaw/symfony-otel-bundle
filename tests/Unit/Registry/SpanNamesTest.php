<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use Macpaw\SymfonyOtelBundle\Registry\SpanNames;
use PHPUnit\Framework\TestCase;

class SpanNamesTest extends TestCase
{
    public function testRequestStartConstant(): void
    {
        $this->assertSame('request_start', SpanNames::REQUEST_START);
    }
}
