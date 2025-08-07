<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\MessageBus;

use Monolog\ResettableInterface;
use PhoneBurner\Pinch\Container\ResettableService;
use PhoneBurner\Pinch\Framework\MessageBus\LongRunningProcessServiceResetter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ResetInterface;

final class LongRunningProcessServiceResetterTest extends TestCase
{
    #[Test]
    public function resetsAddedService(): void
    {
        $resetter = new LongRunningProcessServiceResetter();
        $reset_interface = $this->createMock(ResetInterface::class);

        $reset_interface->expects($this->once())->method('reset');

        $resetter->add($reset_interface);
        $resetter->reset();
    }

    #[Test]
    public function doesNotResetRemovedService(): void
    {
        $resetter = new LongRunningProcessServiceResetter();
        $reset_interface = $this->createMock(ResetInterface::class);

        $reset_interface->expects($this->never())->method('reset');

        $resetter->add($reset_interface);
        $resetter->remove($reset_interface);
        $resetter->reset();
    }

    #[Test]
    public function resetsDifferentTypesOfResettableServices(): void
    {
        $resetter = new LongRunningProcessServiceResetter();

        $reset_interface = $this->createMock(ResetInterface::class);
        $reset_interface->expects($this->exactly(2))->method('reset');

        $resettable_interface = $this->createMock(ResettableInterface::class);
        $resettable_interface->expects($this->exactly(2))->method('reset');

        $resettable_service = $this->createMock(ResettableService::class);
        $resettable_service->expects($this->exactly(2))->method('reset');

        $resetter->add($reset_interface);
        $resetter->add($resettable_interface);
        $resetter->add($resettable_service);

        $resetter->reset();
        $resetter->reset();
    }

    #[Test]
    public function handlesEmptySetWhenResetting(): void
    {
        $this->expectNotToPerformAssertions();
        // Should not cause any errors
        new LongRunningProcessServiceResetter()->reset();
    }

    #[Test]
    public function ignoresRemovingServiceNotInSet(): void
    {
        $resetter = new LongRunningProcessServiceResetter();
        $service = $this->createMock(ResetInterface::class);

        // Should not cause any errors
        $resetter->remove($service);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function handlesRemovingAddedServiceMultipleTimes(): void
    {
        $resetter = new LongRunningProcessServiceResetter();
        $service = $this->createMock(ResetInterface::class);

        $resetter->add($service);
        $resetter->remove($service);

        // Should not cause any errors when removing again
        $resetter->remove($service);

        $service->expects($this->never())->method('reset');
        $resetter->reset();
    }

    #[Test]
    public function resetsOnlyRemainingServices(): void
    {
        $resetter = new LongRunningProcessServiceResetter();

        $service1 = $this->createMock(ResetInterface::class);
        $service1->expects($this->once())->method('reset');

        $service2 = $this->createMock(ResetInterface::class);
        $service2->expects($this->never())->method('reset');

        $service3 = $this->createMock(ResetInterface::class);
        $service3->expects($this->exactly(2))->method('reset');

        $resetter->add($service1);
        $resetter->add($service2);
        $resetter->add($service3);

        $resetter->remove($service2);

        $resetter->reset();

        $resetter->remove($service1);

        $resetter->reset();
    }
}
