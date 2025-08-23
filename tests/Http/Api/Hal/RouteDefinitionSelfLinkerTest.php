<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\Api\Hal;

use Crell\AttributeUtils\Analyzer;
use Crell\AttributeUtils\ClassAnalyzer;
use PhoneBurner\LinkTortilla\Link;
use PhoneBurner\Pinch\Component\AttributeAnalysis\AttributeAnalyzer;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\DefinitionList;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\RouteDefinition;
use PhoneBurner\Pinch\Component\PhoneNumber\AreaCode\AreaCode;
use PhoneBurner\Pinch\Framework\Http\Api\Domain\SelfLinkRoute;
use PhoneBurner\Pinch\Framework\Http\Api\Domain\SelfLinkRouteParameter;
use PhoneBurner\Pinch\Framework\Http\Api\Domain\StandardRel;
use PhoneBurner\Pinch\Framework\Http\Api\Hal\RouteDefinitionSelfLinker;
use PhoneBurner\Pinch\Framework\Tests\Fixtures\NpaInventory;
use PhoneBurner\Pinch\Time\Standards\AnsiSql;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class RouteDefinitionSelfLinkerTest extends TestCase
{
    #[Test]
    public function linksMatchesResourceToUri(): void
    {
        $definition_list = $this->createMock(DefinitionList::class);
        $request = $this->createMock(ServerRequestInterface::class);

        $npa = new NpaInventory(
            AreaCode::make(484),
            3,
        );

        $definition_list->expects($this->once())
            ->method('getNamedRoute')
            ->with('api.inventory.npa.resource')
            ->willReturn(
                RouteDefinition::make('/api/inventory/npa/{npa}')->withName('api.inventory.npa.resource'),
            );

        $self_link = new SelfLinkRoute(route_name: 'api.inventory.npa.resource');
        $self_link->setProperties([
            'area_code' => new SelfLinkRouteParameter('npa'),
        ]);

        $attribute_analyzer = $this->createMock(ClassAnalyzer::class);
        $attribute_analyzer->expects($this->once())
            ->method('analyze')
            ->with($npa, SelfLinkRoute::class)
            ->willReturn($self_link);

        $sut = new RouteDefinitionSelfLinker($definition_list, $attribute_analyzer);
        self::assertEquals(
            [
                Link::make(StandardRel::SELF, '/api/inventory/npa/484'),
            ],
            $sut->links($npa, $request),
        );
    }

    #[Test]
    #[DataProvider('providesDateTimeFormatCases')]
    public function linksFormatsDateTimeInstances(object $resource, string $expected): void
    {
        $definition_list = $this->createMock(DefinitionList::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $definition_list->method('getNamedRoute')
            ->with('api.some-resource')
            ->willReturn(RouteDefinition::make('/api/inventory/npa/{date}')->withName('api.some-resource'));

        $sut = new RouteDefinitionSelfLinker($definition_list, new AttributeAnalyzer(new Analyzer()));

        self::assertEquals(
            [Link::make(StandardRel::SELF, $expected)],
            $sut->links($resource, $request),
        );
    }

    public static function providesDateTimeFormatCases(): \Generator
    {
        yield [
            new #[SelfLinkRoute('api.some-resource')] class () {
                public function __construct(
                    #[SelfLinkRouteParameter(name: 'date')]
                    public \DateTimeInterface $date = new \DateTimeImmutable('2023-10-01 23:59:59'),
                ) {
                }
            },
            '/api/inventory/npa/2023-10-01T23:59:59+00:00',
        ];
        yield [
            new #[SelfLinkRoute('api.some-resource')] class () {
                public function __construct(
                    #[SelfLinkRouteParameter(name: 'date', format: AnsiSql::DATE)]
                    public \DateTimeInterface $date = new \DateTimeImmutable('2023-10-01 23:59:59'),
                ) {
                }
            },
            '/api/inventory/npa/2023-10-01',
        ];
    }
}
