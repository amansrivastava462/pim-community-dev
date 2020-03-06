<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\MeasureBundle\tests\Acceptance;

use Akeneo\Test\Acceptance\Attribute\InMemoryAttributeRepository;
use Akeneo\Test\Acceptance\Attribute\InMemoryIsThereAtLeastOneAttributeConfiguredWithMeasurementFamilyStub;
use Akeneo\Test\Acceptance\MeasurementFamily\InMemoryMeasurementFamilyRepository;
use Akeneo\Tool\Bundle\MeasureBundle\Application\SaveMeasurementFamily\SaveMeasurementFamilyCommand;
use Akeneo\Tool\Bundle\MeasureBundle\Application\SaveMeasurementFamily\SaveMeasurementFamilyHandler;
use Akeneo\Tool\Bundle\MeasureBundle\Model\LabelCollection;
use Akeneo\Tool\Bundle\MeasureBundle\Model\MeasurementFamily;
use Akeneo\Tool\Bundle\MeasureBundle\Model\MeasurementFamilyCode;
use Akeneo\Tool\Bundle\MeasureBundle\Model\Operation;
use Akeneo\Tool\Bundle\MeasureBundle\Model\Unit;
use Akeneo\Tool\Bundle\MeasureBundle\Model\UnitCode;
use Akeneo\Tool\Bundle\MeasureBundle\Persistence\MeasurementFamilyRepositoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SaveMeasurementFamilyTest extends AcceptanceTestCase
{
    /** * @var ValidatorInterface */
    private $validator;

    /** @var InMemoryMeasurementFamilyRepository */
    private $measurementFamilyRepository;

    /** @var SaveMeasurementFamilyHandler */
    private $saveMeasurementFamilyHandler;

    /** @var InMemoryIsThereAtLeastOneAttributeConfiguredWithMeasurementFamilyStub */
    private $isThereAtLeastOneAttributeConfiguredWithMeasurementFamily;

    public function setUp(): void
    {
        parent::setUp();
        $this->validator = $this->get('validator');
        $this->measurementFamilyRepository = $this->get('akeneo_measure.persistence.measurement_family_repository');
        $this->measurementFamilyRepository->clear();
        $this->saveMeasurementFamilyHandler = $this->get('akeneo_measure.application.save_measurement_family_handler');
        $this->isThereAtLeastOneAttributeConfiguredWithMeasurementFamily = $this->get('akeneo.pim.structure.query.is_there_at_least_one_attribute_configured_with_measurement_family');
    }

    // TODO: Nominal cases
    // It can create
    // It can update
    // It can add & remove units
    // It can update units

    /**
     * @test
     */
    public function it_can_change_the_standard_unit_code(): void
    {
        $measurementFamilyCode = 'WEIGHT';
        $this->createMeasurementFamilyWithUnitsAndStandardUnit($measurementFamilyCode, ['KILOGRAM', 'GRAM'], 'KILOGRAM');
        $this->thereIsAProductAttributeLinkedToThisMeasurementFamily();

        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = $measurementFamilyCode;
        $saveFamilyCommand->labels = [];
        $saveFamilyCommand->standardUnitCode = 'KILOGRAM';
        $saveFamilyCommand->units = [
            [
                'code'                  => 'KILOGRAM',
                'labels'                => ['fr_FR' => 'Kilogrammes'],
                'convert_from_standard' => [['operator' => 'mul', 'value' => '0.000001']],
                'symbol' => 'km'
            ],
            [
                'code'                  => 'GRAM',
                'labels'                => [],
                'convert_from_standard' => [['operator' => 'mul', 'value' => '0.000001']],
                'symbol' => 'km'
            ]
        ];
        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(0, $violations->count());
    }

    /**
     * @test
     * @dataProvider invalidCodes
     */
    public function it_has_an_invalid_code($invalidCode, string $errorMessage): void
    {
        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = $invalidCode;
        $saveFamilyCommand->standardUnitCode = 'kilogram';
        $saveFamilyCommand->units = [
            [
                'code' => 'kilogram',
                'labels' => [],
                'convert_from_standard' => [['operator' => 'mul', 'value' => '153']],
                'symbol' => 'Km'
            ]
        ];

        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals($errorMessage, $violation->getMessage());
    }

    /**
     * @test
     * @dataProvider invalidLabels
     */
    public function it_has_an_invalid_label($invalidLabels, string $errorMessage): void
    {
        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = 'WEIGHT';
        $saveFamilyCommand->labels = $invalidLabels;
        $saveFamilyCommand->standardUnitCode = 'kilogram';
        $saveFamilyCommand->units = [
            [
                'code' => 'kilogram',
                'labels' => [],
                'convert_from_standard' => [['operator' => 'mul', 'value' => '153']],
                'symbol' => 'Km'
            ]
        ];

        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals($errorMessage, $violation->getMessage());
    }

    /**
     * @test
     */
    public function it_has_a_standard_unit_which_is_not_a_unit_of_the_measurement_family(): void
    {
        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = 'WEIGHT';
        $saveFamilyCommand->labels = [];
        $saveFamilyCommand->standardUnitCode = 'invalid_standard_unit_code';
        $saveFamilyCommand->units = [
            [
                'code' => 'kilogram',
                'labels' => [],
                'convert_from_standard' => [['operator' => 'mul', 'value' => '153']],
                'symbol' => 'Km'
            ]
        ];

        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals(
            'The "invalid_standard_unit_code" standard unit code does not exist in the list of units for the measurement family.',
            $violation->getMessage()
        );
    }

    /**
     * @test
     * @dataProvider invalidCodes
     */
    public function it_has_a_unit_with_an_invalid_code($invalidCodes, string $errorMessage): void
    {
        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = 'WEIGHT';
        $saveFamilyCommand->labels = [];
        $saveFamilyCommand->standardUnitCode = $invalidCodes;
        $saveFamilyCommand->units = [
            [
                'code' => $invalidCodes,
                'labels' => [],
                'convert_from_standard' => [['operator' => 'mul', 'value' => '153']],
                'symbol' => 'Km'
            ]
        ];

        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals($errorMessage, $violation->getMessage());
    }

    /**
     * @test
     * @dataProvider invalidLabels
     */
    public function it_has_a_unit_with_an_invalid_label($invalidLabels, string $errorMessage): void
    {
        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = 'WEIGHT';
        $saveFamilyCommand->labels = [];
        $saveFamilyCommand->standardUnitCode = 'kilogram';
        $saveFamilyCommand->units = [
            [
                'code' => 'kilogram',
                'labels' => $invalidLabels,
                'convert_from_standard' => [['operator' => 'mul', 'value' => '153']],
                'symbol' => 'Km'
            ]
        ];

        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals($errorMessage, $violation->getMessage());
    }

    /**
     * @test
     * @dataProvider invalidOperator
     */
    public function it_has_a_unit_with_an_invalid_convert_operator($invalidOperator, string $errorMessage): void
    {
        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = 'WEIGHT';
        $saveFamilyCommand->labels = [];
        $saveFamilyCommand->standardUnitCode = 'kilogram';
        $saveFamilyCommand->units = [
            [
                'code' => 'kilogram',
                'labels' => [],
                'convert_from_standard' => [['operator' => $invalidOperator, 'value' => '251']],
                'symbol' => 'Km'
            ]
        ];

        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals($errorMessage, $violation->getMessage());
    }

    /**
     * @test
     * @dataProvider invalidConvertValue
     */
    public function it_has_a_unit_with_an_invalid_convert_value($invalidConvertValue, string $errorMessage): void
    {
        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = 'WEIGHT';
        $saveFamilyCommand->labels = [];
        $saveFamilyCommand->standardUnitCode = 'kilogram';
        $saveFamilyCommand->units = [
            [
                'code' => 'kilogram',
                'labels' => [],
                'convert_from_standard' => [['operator' => 'mul', 'value' => $invalidConvertValue]],
                'symbol' => 'Km'
            ]
        ];

        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals($errorMessage, $violation->getMessage());
    }

    /**
     * @test
     * @dataProvider invalidUnitSymbol
     */
    public function it_has_a_unit_with_an_invalid_unit_symbol($invalidUnitSymbol, string $errorMessage): void
    {
        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = 'WEIGHT';
        $saveFamilyCommand->labels = [];
        $saveFamilyCommand->standardUnitCode = 'kilogram';
        $saveFamilyCommand->units = [
            [
                'code' => 'kilogram',
                'labels' => [],
                'convert_from_standard' => [['operator' => 'mul', 'value' => '255']],
                'symbol' => $invalidUnitSymbol
            ]
        ];

        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals($errorMessage, $violation->getMessage());
    }

    /**
     * @test
     * @dataProvider invalidOperationCount
     */
    public function it_has_an_invalid_amount_of_operations($invalidOperationCount, string $errorMessage): void
    {
        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = 'WEIGHT';
        $saveFamilyCommand->labels = [];
        $saveFamilyCommand->standardUnitCode = 'kilogram';
        $saveFamilyCommand->units = [
            [
                'code' => 'kilogram',
                'labels' => [],
                'convert_from_standard' => array_fill(0, $invalidOperationCount, ['operator' => 'mul', 'value' => '1']),
                'symbol' => 'Kg',
            ]
        ];

        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals($errorMessage, $violation->getMessage());
    }

    /**
     * @test
     * @dataProvider invalidUnitCount
     */
    public function it_has_an_invalid_amount_of_units($invalidUnitCount, string $errorMessage): void
    {
        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = 'WEIGHT';
        $saveFamilyCommand->labels = [];
        $saveFamilyCommand->standardUnitCode = 'unit_0';
        $saveFamilyCommand->units = array_map(function ($i) {
            return [
                'code' => sprintf('unit_%d', $i),
                'labels' => [],
                'convert_from_standard' => [['operator' => 'mul', 'value' => '1']],
                'symbol' => 'Kg',
            ];
        }, range(0, $invalidUnitCount - 1));

        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals($errorMessage, $violation->getMessage());
    }

    /**
     * @test
     */
    public function is_cannot_create_too_many_measurement_families(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $measurementFamily = MeasurementFamily::create(
                MeasurementFamilyCode::fromString(sprintf('unit_%d', $i)),
                LabelCollection::fromArray(['en_US' => 'Custom measurement']),
                UnitCode::fromString(sprintf('UNIT_%d', $i)),
                [
                    Unit::create(
                        UnitCode::fromString(sprintf('UNIT_%d', $i)),
                        LabelCollection::fromArray(['en_US' => 'Custom unit']),
                        [Operation::create('mul', '1')],
                        'mm²',
                    ),
                ]
            );

            $this->measurementFamilyRepository->save($measurementFamily);
        }

        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = 'WEIGHT';
        $saveFamilyCommand->labels = [];
        $saveFamilyCommand->standardUnitCode = 'kilogram';
        $saveFamilyCommand->units = [
            [
                'code' => 'kilogram',
                'labels' => [],
                'convert_from_standard' => [['operator' => 'mul', 'value' => '1']],
                'symbol' => 'Kg',
            ]
        ];

        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals('You’ve reached the limit of 100 measurement families.', $violation->getMessage());
    }

    /**
     * @test
     */
    public function it_does_not_allow_measurement_family_standard_unit_update_when_linked_to_a_product_attribute(): void
    {
        $measurementFamilyCode = 'WEIGHT';
        $this->createMeasurementFamilyWithUnitsAndStandardUnit($measurementFamilyCode, ['KILOGRAM', 'GRAM'], 'KILOGRAM');
        $this->thereIsAProductAttributeLinkedToThisMeasurementFamily();

        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = $measurementFamilyCode;
        $saveFamilyCommand->labels = [];
        $saveFamilyCommand->standardUnitCode = 'GRAM';
        $saveFamilyCommand->units = [
            [
                'code'                  => 'KILOGRAM',
                'labels'                => [],
                'convert_from_standard' => [['operator' => 'mul', 'value' => '0.000001']],
                'symbol' => 'km'
            ],
            [
                'code'                  => 'GRAM',
                'labels'                => [],
                'convert_from_standard' => [['operator' => 'mul', 'value' => '0.000001']],
                'symbol' => 'km'
            ]
        ];
        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals('The update of the "WEIGHT" measurement family standard unit code is not allowed because it is linked to a product attribute.', $violation->getMessage());
    }

    /**
     * @test
     */
    public function it_does_not_allow_to_remove_a_unit_when_linked_to_a_product_attribute(): void
    {
        $measurementFamilyCode = 'WEIGHT';
        $this->createMeasurementFamilyWithUnitsAndStandardUnit($measurementFamilyCode, ['KILOGRAM', 'GRAM'], 'KILOGRAM');
        $this->thereIsAProductAttributeLinkedToThisMeasurementFamily();

        $saveFamilyCommand = new SaveMeasurementFamilyCommand();
        $saveFamilyCommand->code = $measurementFamilyCode;
        $saveFamilyCommand->labels = [];
        $saveFamilyCommand->standardUnitCode = 'KILOGRAM';
        $saveFamilyCommand->units = [
            [
                'code'                  => 'KILOGRAM',
                'labels'                => [],
                'convert_from_standard' => [['operator' => 'mul', 'value' => '0.000001']],
                'symbol' => 'km'
            ],
            // Missing GRAM
        ];
        $violations = $this->validator->validate($saveFamilyCommand);

        self::assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        self::assertEquals('The removal of the GRAM unit(s) in the "WEIGHT" measurement family is not allowed because it is linked to a product attribute.', $violation->getMessage());
    }

    // Cannot edit convert operations

    public function invalidCodes(): array
    {
        return [
            'Should not be too long' => [
                str_repeat('a', 256),
                'This value is too long. It should have 255 characters or less.'
            ],
            'Should not blank' => [null, 'This value should not be blank.'],
            'Should not a string' => [123, 'This value should be of type string.'],
            'Should not have unsupported character' => ['--nice-', 'This field can only contain letters, numbers, and underscores.']
        ];
    }

    public function invalidLabels(): array
    {
        return [
            'Locale code should be a string' => [[123 => 'my label'], 'This value should be of type string.'],
            'Label should be a string' => [['fr_FR' => 12], 'This value should be of type string.']
        ];
    }

    public function invalidOperator(): array
    {
        return [
            'Operator cannot be blank' => [null, 'This value should not be blank.'],
            'Operator is not supported' => ['invalid_operator', 'The value you selected is not a valid choice.'],
        ];
    }

    public function invalidConvertValue(): array
    {
        return [
            'The convert value is not a valid number represented as a string' => ['1.24adv', 'The conversion value should be a number represented in a string (example: "0.2561")']
        ];
    }

    public function invalidUnitSymbol()
    {
        return [
            'Should not be too long' => [str_repeat('a', 256), 'This value is too long. It should have 255 characters or less.'],
            'Should be a string' => [123, 'This value should be of type string.'],
        ];
    }

    private function thereIsAProductAttributeLinkedToThisMeasurementFamily(): void
    {
        $this->isThereAtLeastOneAttributeConfiguredWithMeasurementFamily->setStub(true);
    }

    private function createMeasurementFamilyWithUnitsAndStandardUnit(string $measurementFamilyCode, array $unitCodes, string $standardUnitCode): void
    {
        $this->measurementFamilyRepository->save(
            MeasurementFamily::create(
                MeasurementFamilyCode::fromString($measurementFamilyCode),
                LabelCollection::fromArray([]),
                UnitCode::fromString($standardUnitCode),
                array_map(function (string $unitCode) {
                    return Unit::create(
                        UnitCode::fromString($unitCode),
                        LabelCollection::fromArray([]),
                        [
                            Operation::create("mul", "0.000001"),
                        ],
                        "km",
                        );
                }, $unitCodes)
            )
        );
    }


    public function invalidOperationCount()
    {
        return [
            'Should have at least one operation' => [0, 'A minimum of one conversion operation per unit is required.'],
            'Should have max 5 operations' => [6, 'You’ve reached the limit of 5 conversion operations per unit.'],
        ];
    }

    public function invalidUnitCount()
    {
        return [
            'Should have at least one operation' => [51, 'You’ve reached the limit of 50 conversion operations per unit.'],
        ];
    }
}
