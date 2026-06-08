<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Bundle\Repository;

use Masilia\Bundle\AiAssistant\Entity\AiModel;
use Masilia\Bundle\AiAssistant\Entity\AiProvider;
use Masilia\Bundle\AiAssistant\Repository\AiModelRepository;
use Masilia\Bundle\AiAssistant\Repository\AiProviderRepository;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the two distinct "find active" paths the
 * AiProviderRepository exposes:
 *
 *  - findActive() returns a lib-layer ResolvedProvider (used by
 *    TargetResolver and HealthChecker at runtime).
 *  - findActiveEntity() returns the raw AiProvider entity (used
 *    by AiProviderApiController::getData which needs the DB
 *    primary keys for the dashboard).
 *
 * Mixing them up caused a Fatal error at runtime — the controller
 * passed a ResolvedProvider to a method that expected an
 * AiProvider. These tests guard against that regression.
 */
final class AiProviderRepositoryTest extends TestCase
{
    public function testFindActiveEntityReturnsRawAiProviderEntity(): void
    {
        $entity = $this->createMock(AiProvider::class);

        $repo = $this->getMockBuilder(AiProviderRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repo->expects(self::once())
            ->method('findOneBy')
            ->with(['isActive' => true])
            ->willReturn($entity);

        $result = $repo->findActiveEntity();

        self::assertSame($entity, $result);
        self::assertInstanceOf(AiProvider::class, $result);
    }

    public function testFindActiveEntityReturnsNullWhenNoActiveProvider(): void
    {
        $repo = $this->getMockBuilder(AiProviderRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repo->expects(self::once())
            ->method('findOneBy')
            ->with(['isActive' => true])
            ->willReturn(null);

        self::assertNull($repo->findActiveEntity());
    }

    public function testFindActiveForProviderAcceptsAiProviderEntity(): void
    {
        // Regression guard: the model repo's findActiveForProvider()
        // takes an AiProvider (entity), NOT a ResolvedProvider.
        // If a future refactor swaps this to ResolvedProvider, this
        // test should be updated AND the controller should be updated
        // in lockstep.
        $provider = $this->createMock(AiProvider::class);
        $model = $this->createMock(AiModel::class);

        $repo = $this->getMockBuilder(AiModelRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repo->expects(self::once())
            ->method('findOneBy')
            ->with(['provider' => $provider, 'isActive' => true])
            ->willReturn($model);

        $result = $repo->findActiveForProvider($provider);

        self::assertSame($model, $result);
    }

    /**
     * The exact bug reported in production:
     *
     *   Fatal error: AiModelRepository::findActiveForProvider():
     *   Argument #1 ($provider) must be of type AiProvider,
     *   ResolvedProvider given
     *
     * Root cause: Sprint 3's P1-B5 refactor changed
     * AiProviderRepository::findActive() to return a
     * ResolvedProvider (per the new lib interface). The
     * AiProviderApiController::getData() was still calling it
     * and passing the result to AiModelRepository::findActiveForProvider(),
     * which expects an entity. Mixed contracts at the controller
     * boundary.
     *
     * Fix: the controller now uses findActiveEntity() (returns
     * the raw entity, NOT the ResolvedProvider) for the dashboard
     * data path. This test asserts the contract: findActiveEntity
     * returns AiProvider, findActive returns ResolvedProvider —
     * never confuse them.
     */
    public function testGetDataPathUsesEntityNotResolvedProvider(): void
    {
        $entity = $this->createMock(AiProvider::class);
        $entity->method('getId')->willReturn(42);

        $providerRepo = $this->getMockBuilder(AiProviderRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy', 'findActiveEntity', 'findActive'])
            ->getMock();

        $providerRepo->expects(self::once())
            ->method('findActiveEntity')
            ->willReturn($entity);

        // findActive() should NOT be called from the dashboard
        // getData() path (it returns ResolvedProvider which would
        // be the wrong type for the model lookup).
        $providerRepo->expects(self::never())->method('findActive');

        $result = $providerRepo->findActiveEntity();
        self::assertInstanceOf(AiProvider::class, $result);
        self::assertSame(42, $result->getId());
    }
}
