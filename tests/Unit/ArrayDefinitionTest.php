<?php

declare(strict_types=1);

namespace Yiisoft\Definitions\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Definitions\ArrayDefinition;
use Yiisoft\Definitions\Exception\InvalidConfigException;
use Yiisoft\Definitions\Reference;
use Yiisoft\Definitions\Tests\Support\Car;
use Yiisoft\Definitions\Tests\Support\ColorInterface;
use Yiisoft\Definitions\Tests\Support\ColorPink;
use Yiisoft\Definitions\Tests\Support\EngineInterface;
use Yiisoft\Definitions\Tests\Support\EngineMarkOne;
use Yiisoft\Definitions\Tests\Support\EngineMarkTwo;
use Yiisoft\Definitions\Tests\Support\Phone;
use Yiisoft\Test\Support\Container\SimpleContainer;

final class ArrayDefinitionTest extends TestCase
{
    public function testClass(): void
    {
        $container = new SimpleContainer();

        $class = Phone::class;

        $definition = ArrayDefinition::fromConfig([
            ArrayDefinition::CLASS_NAME => $class,
        ]);

        self::assertInstanceOf(Phone::class, $definition->resolve($container));
    }

    public function dataConstructor(): array
    {
        return [
            [null, null, []],
            ['Kiradzu', null, ['Kiradzu']],
            ['Kiradzu', null, ['name' => 'Kiradzu']],
            ['Kiradzu', '2.0', ['Kiradzu', '2.0']],
            ['Kiradzu', '2.0', ['name' => 'Kiradzu', 'version' => '2.0']],
        ];
    }

    /**
     * @dataProvider dataConstructor
     */
    public function testConstructor(?string $name, ?string $version, array $constructorArguments): void
    {
        $container = new SimpleContainer();

        $definition = ArrayDefinition::fromConfig([
            ArrayDefinition::CLASS_NAME => Phone::class,
            ArrayDefinition::CONSTRUCTOR => $constructorArguments,
        ]);

        /** @var Phone $phone */
        $phone = $definition->resolve($container);

        self::assertSame($name, $phone->getName());
        self::assertSame($version, $phone->getVersion());
    }

    public function testConstructorWithVariadicAndIntKeys(): void
    {
        $container = new SimpleContainer();

        $colors = ['red', 'green', 'blue'];
        $definition = ArrayDefinition::fromConfig([
            ArrayDefinition::CLASS_NAME => Phone::class,
            ArrayDefinition::CONSTRUCTOR => [
                null,
                null,
                $colors[0],
                $colors[1],
                $colors[2],
            ],
        ]);

        /** @var Phone $phone */
        $phone = $definition->resolve($container);

        self::assertSame($colors, $phone->getColors());
    }

    public function testConstructorWithVariadicArrayAndIntKeys(): void
    {
        $container = new SimpleContainer();

        $colors = ['red', 'green', 'blue'];
        $definition = ArrayDefinition::fromConfig([
            ArrayDefinition::CLASS_NAME => Phone::class,
            ArrayDefinition::CONSTRUCTOR => [
                null,
                null,
                $colors,
            ],
        ]);

        /** @var Phone $phone */
        $phone = $definition->resolve($container);


        self::assertSame([$colors], $phone->getColors());
    }

    public function testConstructorWithVariadicAndNamedKeys(): void
    {
        $container = new SimpleContainer();

        $colors = ['red', 'green', 'blue'];
        $definition = ArrayDefinition::fromConfig([
            ArrayDefinition::CLASS_NAME => Phone::class,
            ArrayDefinition::CONSTRUCTOR => [
                'name' => null,
                'version' => null,
                'colors' => $colors,
            ],
        ]);

        /** @var Phone $phone */
        $phone = $definition->resolve($container);

        self::assertSame($colors, $phone->getColors());
    }

    public function testConstructorWithWrongVariadicArgument(): void
    {
        $container = new SimpleContainer();

        $colors = 'red';
        $definition = ArrayDefinition::fromConfig([
            ArrayDefinition::CLASS_NAME => Phone::class,
            ArrayDefinition::CONSTRUCTOR => [
                'name' => null,
                'version' => null,
                'colors' => $colors,
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Named argument for a variadic parameter should be an array, "string" given.');

        $definition->resolve($container);
    }

    public function dataSetProperties(): array
    {
        return [
            [false, null, []],
            [true, null, ['$dev' => true]],
            [true, 'Radar', ['$dev' => true, '$codeName' => 'Radar']],
        ];
    }

    /**
     * @dataProvider dataSetProperties
     */
    public function testSetProperties(bool $dev, ?string $codeName, array $setProperties): void
    {
        $container = new SimpleContainer();

        $definition = ArrayDefinition::fromConfig(array_merge([
            ArrayDefinition::CLASS_NAME => Phone::class,
        ], $setProperties));

        /** @var Phone $phone */
        $phone = $definition->resolve($container);

        self::assertSame($dev, $phone->dev);
        self::assertSame($codeName, $phone->codeName);
    }

    public function dataCallMethods(): array
    {
        return [
            [null, [], []],
            ['s43g23456', [], ['setId()' => ['s43g23456']]],
            ['777', [], ['setId777()' => []]],
            [
                '777',
                [['Browser', null]],
                [
                    'addApp()' => ['Browser'],
                    'setId777()' => [],
                ],
            ],
            [
                '42',
                [['Browser', '7']],
                [
                    'setId()' => ['42'],
                    'addApp()' => ['Browser', '7'],
                ],
            ],
            [
                null,
                [['Browser', '7']],
                [
                    'addApp()' => ['name' => 'Browser', 'version' => '7'],
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataCallMethods
     */
    public function testCallMethods(?string $id, array $apps, array $callMethods): void
    {
        $container = new SimpleContainer();

        $definition = ArrayDefinition::fromConfig(array_merge(
            [
                ArrayDefinition::CLASS_NAME => Phone::class,
            ],
            $callMethods
        ));

        /** @var Phone $phone */
        $phone = $definition->resolve($container);

        self::assertSame($id, $phone->getId());
        self::assertSame($apps, $phone->getApps());
    }

    public function testCallFluentMethod(): void
    {
        $container = new SimpleContainer();

        $author = 'Sergei';
        $country = 'Russia';
        $definition = ArrayDefinition::fromConfig([
            ArrayDefinition::CLASS_NAME => Phone::class,
            'withAuthor()' => [$author],
            'withCountry()' => [$country],
        ]);

        /** @var Phone $phone */
        $phone = $definition->resolve($container);

        self::assertSame($author, $phone->getAuthor());
        self::assertSame($country, $phone->getCountry());
    }

    public function testMerge(): void
    {
        $a = ArrayDefinition::fromConfig([
            ArrayDefinition::CLASS_NAME => Phone::class,
            ArrayDefinition::CONSTRUCTOR => ['name' => 'Retro', 'version' => '1.0'],
            '$codeName' => 'a',
            'setColors()' => ['red', 'green'],
        ]);
        $b = ArrayDefinition::fromConfig([
            ArrayDefinition::CLASS_NAME => Phone::class,
            ArrayDefinition::CONSTRUCTOR => ['version' => '2.0'],
            '$dev' => true,
            '$codeName' => 'b',
            'setId()' => [42],
            'setColors()' => ['yellow'],
        ]);
        $c = $a->merge($b);

        $this->assertSame(Phone::class, $c->getClass());
        $this->assertSame(['name' => 'Retro', 'version' => '2.0'], $c->getConstructorArguments());
        $this->assertSame(
            [
                '$codeName' => [ArrayDefinition::TYPE_PROPERTY, 'codeName', 'b'],
                'setColors()' => [ArrayDefinition::TYPE_METHOD, 'setColors', ['yellow', 'green']],
                '$dev' => [ArrayDefinition::TYPE_PROPERTY, 'dev', true],
                'setId()' => [ArrayDefinition::TYPE_METHOD, 'setId', [42]],
            ],
            $c->getMethodsAndProperties(),
        );
    }

    public function testMergeImmutability(): void
    {
        $a = ArrayDefinition::fromPreparedData(Phone::class);
        $b = ArrayDefinition::fromPreparedData(Phone::class);
        $c = $a->merge($b);
        $this->assertNotSame($a, $c);
        $this->assertNotSame($b, $c);
    }

    public function testArgumentsIndexedBothByNameAndByPosition(): void
    {
        $definition = ArrayDefinition::fromConfig([
            'class' => Phone::class,
            '__construct()' => ['name' => 'Taruto', '1.0'],
        ]);
        $container = new SimpleContainer();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Arguments indexed both by name and by position are not allowed in the same array.'
        );
        $definition->resolve($container);
    }

    public function testReferenceContainer(): void
    {
        $container = new SimpleContainer([
            EngineInterface::class => new EngineMarkOne(),
        ]);
        $referenceContainer = new SimpleContainer([
            ColorInterface::class => new ColorPink(),
            EngineInterface::class => new EngineMarkTwo(),
        ]);

        $definition = ArrayDefinition::fromConfig([
            'class' => Car::class,
            'setColor()' => [Reference::to(ColorInterface::class)],
        ]);
        $definition->setReferenceContainer($referenceContainer);

        /** @var Car $object */
        $object = $definition->resolve($container);

        $this->assertInstanceOf(Car::class, $object);
        $this->assertInstanceOf(EngineMarkOne::class, $object->getEngine());
    }
}
