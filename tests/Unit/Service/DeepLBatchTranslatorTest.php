<?php

declare(strict_types=1);

/*
 * This file is part of the "composer-translation-deepl" Composer package.
 *
 * (c) 2025 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoveElevator\ComposerTranslationDeepl\Tests\Unit\Service;

use DeepL\DeepLException;
use MoveElevator\ComposerTranslationDeepl\Service\DeepLBatchTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeepLBatchTranslator::class)]
/**
 * DeepLBatchTranslatorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
class DeepLBatchTranslatorTest extends TestCase
{
    public function testTranslateBatchWithEmptyArrayReturnsEmptyArray(): void
    {
        // This test doesn't need API key as it returns early
        $deepLBatchTranslator = new DeepLBatchTranslator('test-key:fx');
        $result = $deepLBatchTranslator->translateBatch([], 'en', 'de');

        self::assertEmpty($result);
    }

    public function testTranslateThrowsExceptionWithInvalidApiKey(): void
    {
        $deepLBatchTranslator = new DeepLBatchTranslator('invalid-key');

        $this->expectException(DeepLException::class);

        // This will fail because the API key is invalid
        $deepLBatchTranslator->translate('Test', 'en', 'de');
    }

    public function testTranslateBatchThrowsExceptionWithInvalidApiKey(): void
    {
        $deepLBatchTranslator = new DeepLBatchTranslator('invalid-key');

        $this->expectException(DeepLException::class);

        $deepLBatchTranslator->translateBatch(['key' => 'Test'], 'en', 'de');
    }

    public function testGetUsageThrowsExceptionWithInvalidApiKey(): void
    {
        $deepLBatchTranslator = new DeepLBatchTranslator('invalid-key');

        $this->expectException(DeepLException::class);

        $deepLBatchTranslator->getUsage();
    }
}
