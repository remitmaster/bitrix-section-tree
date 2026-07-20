<?php

declare(strict_types=1);

namespace Yunaweb\SectionTree\Tests;

use PHPUnit\Framework\TestCase;
use Yunaweb\SectionTree\Exception\SectionTreeException;

final class SectionTreeTest extends TestCase
{
    public function testExceptionIsInvalidArgumentException(): void
    {
        $this->assertInstanceOf(\InvalidArgumentException::class, new SectionTreeException('test'));
    }
}
