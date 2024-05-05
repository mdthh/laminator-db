<?php

namespace DbTableInstigator\Code\Generator;

use Laminas\Code\Generator;
use Laminas\Db\Sql\TableIdentifier;

class EntityClassGenerator extends AbstractClassGenerator
{
    protected $autoCreateFilterTypes = true;

    public function generate()
    {
        $globalConfig = $this->config;
        $tableInfo = $globalConfig['table_info'];

        $this->addUse('DomainException')
            ->addUse('Laminas\InputFilter\InputFilterAwareInterface')
            ->addUse('Laminas\InputFilter\InputFilter')
            #->addUse('Laminas\InputFilter\InputFilterInterface')
            ->addUse('Laminas\Filter')
            ->addUse('Laminas\Validator');

        $this->setImplementedInterfaces(['Laminas\InputFilter\InputFilterAwareInterface']);

        // class documentation
        $this->setDocBlock($this->setClassDocblock());

        // "$inputFilter" property
        $this->addPropertyFromGenerator($this->setInputFilterClassProperty());

        foreach ($tableInfo->getColumns() as $column) {
            $property = new Generator\PropertyGenerator($column->getName(), null, Generator\PropertyGenerator::FLAG_PUBLIC);
            $property->omitDefaultValue();
            $this->addPropertyFromGenerator($property);
        }

        $this
            ->addMethodFromGenerator($this->setMethodSetInputFilter())
            ->addMethodFromGenerator($this->setMethodGetInputFilter())
            ->addMethodFromGenerator($this->setMethodExchangeArray())
            ->addMethodFromGenerator($this->setMethodGetInsertArray())
            ->addMethodFromGenerator($this->setMethodGetUpdateArray());

        return parent::generate();
    }



    public function setClassDocblock()
    {
        $globalConfig = $this->config;
        $tableInfo = $globalConfig['table_info'];

        $docBlock = new Generator\DocBlockGenerator();
        $docBlock->setShortDescription(
            sprintf('Entity-class for the database-table "%s"', $tableInfo->getName()),
        );

        $tags = [
            [
                'name' => 'todo',
                'description' => 'Modify class-properties to your needs'
            ],
            [
                'name' => 'todo',
                'description' => 'Modify method getInputFilter() to your needs'
            ],
            [
                'name' => 'todo',
                'description' => 'Modify method getSqlInsertArray() to your needs'
            ],
        ];

        $docBlock->setTags($tags);

        return $docBlock;

        $return = [
            'shortDescription' => sprintf('ArrayObject-Entity class for the database-table "%s"', $tableInfo->getName()),
            #  'longDescription' => '',
            'tags' => array_merge($this->prepareClassDocTags(), $tags)
        ];

        return $return;
    }

    protected function setMethodExchangeArray()
    {
        $globalConfig = $this->config;
        $tableInfo = $globalConfig['table_info'];

        $body = '';

        foreach ($tableInfo->getColumns() as $column) {
            $name = $column->getName();
            $body .= sprintf(
                '$this->%s = ! empty($data[\'%s\']) ? $data[\'%s\'] : null;%s',
                $name,
                $name,
                $name,
                PHP_EOL
            );
        }

        $method = new Generator\MethodGenerator('exchangeArray');
        $method
            ->setBody($body)
            ->setParameter(new Generator\ParameterGenerator('data', 'array', []));

        return $method;
    }

    protected function setInputFilterClassProperty()
    {
        $property = new Generator\PropertyGenerator('inputFilter', null, Generator\PropertyGenerator::FLAG_PROTECTED);
        $property->omitDefaultValue();
        $docBlock = new Generator\DocBlockGenerator();
        $docBlock
            ->setShortDescription('Input filter object')
            ->setLongDescription("For filtering and validation\nUseful for example in forms")
            ->setTags([
                [
                    'name' => 'var',
                    'description' => 'InputFilter'
                ]
            ]);

        $property->setDocBlock($docBlock);
        return $property;
    }
    public function setMethodSetInputFilter()
    {
        $body = <<<'EOD'
throw new DomainException(sprintf(
'%s does not allow injection of an alternate input filter',
__CLASS__
));
EOD;

        $method = new Generator\MethodGenerator('setInputFilter');

        $docBlock = new Generator\DocBlockGenerator();
        $docBlock
            ->setShortDescription('Not in use')
            ->setLongDescription(
                'This function must be set in order to comply with \Laminas\InputFilter\InputFilterAwareInterface'
            )
            ->setTags([
                [
                    'name' => 'return',
                    'description' => 'InputFilter'
                ]
            ]);

        $method->setDocBlock($docBlock);

        $parameter = new Generator\ParameterGenerator();
        $parameter->setType('Laminas\InputFilter\InputFilterInterface');
        $parameter->setName('inputFilter');

        $method->setParameter($parameter);

        $method->setBody($body);
        $method->setReturnType('Laminas\InputFilter\InputFilterInterface');
        return $method;
    }

    /**
     * 
     */
    public function setMethodGetInputFilter()
    {
        $globalConfig = $this->config;
        $tableInfo = $globalConfig['table_info'];

        $methodTemplate = <<<'EOD'
if ($this->inputFilter) {
    return $this->inputFilter;
}

$inputFilter = new InputFilter();

%s

$this->inputFilter = $inputFilter;
return $inputFilter;
EOD;

        $filterAddTemplate = <<<'EOD'
$inputFilter->add(%s);  
EOD;
        $methodBulk = '';

        foreach ($tableInfo->getColumns() as $column) {
            $config = [
                'name' => $column->getName(),
                'required' => !$column->isNullable(),
                'filters' => [],
                'validators' => [],
            ];

            $type = $this->mapField($column);


            if ($this->autoCreateFilterTypes) {
                $type = $this->mapField($column);
                switch (strtolower($type)) {
                    case 'int':
                        $config['filters'][] = [
                            'name' => new Generator\ValueGenerator(
                                "Filter\ToInt::class",
                                Generator\ValueGenerator::TYPE_CONSTANT
                            )
                        ];
                        break;
                    case 'float':
                        $config['filters'][] = [
                            'name' => new Generator\ValueGenerator(
                                "Filter\ToFloat::class",
                                Generator\ValueGenerator::TYPE_CONSTANT
                            )
                        ];
                        break;
                    case 'string':
                        $config['filters'][] = [
                            'name' => new Generator\ValueGenerator(
                                "Filter\StripTags::class",
                                Generator\ValueGenerator::TYPE_CONSTANT
                            )
                        ];
                        $config['filters'][] = [
                            'name' => new Generator\ValueGenerator(
                                "Filter\StringTrim::class",
                                Generator\ValueGenerator::TYPE_CONSTANT
                            )
                        ];
                    default:
                        break;
                }

                if (strtolower($column->getDataType()) == 'enum') {
                    $config['validators'][] = [
                        'name' => new Generator\ValueGenerator(
                            "Validator\InArray::class",
                            Generator\ValueGenerator::TYPE_CONSTANT
                        ),
                        'options' => ['haystack' => []]
                    ];
                }

                if ($column->getCharacterMaximumLength()) {
                    $config['validators'][] = [
                        'name' => new Generator\ValueGenerator(
                            'Validator\StringLength::class',
                            Generator\ValueGenerator::TYPE_CONSTANT
                        ),
                        'options' => ['max' => $column->getCharacterMaximumLength()]
                    ];
                }
                $valueGenerator = new Generator\ValueGenerator($config);

                $methodBulk .= sprintf($filterAddTemplate, $valueGenerator->generate()) . str_repeat(PHP_EOL, 2);
            }
        }

        $method = new Generator\MethodGenerator('getInputFilter');
        $docBlock = new Generator\DocBlockGenerator();
        $docBlock
            ->setShortDescription('Returns an InputFilter object with all column filters and validators')
            ->setLongDescription('Creates new InputFilter object if not yet exists and sets self::inputFilter')
            ->setTags([
                [
                    'name' => 'return',
                    'description' => '\\' . \Laminas\InputFilter\InputFilter::class
                ]
            ]);
        $method->setDocBlock($docBlock);
        $method->setBody(sprintf($methodTemplate, $methodBulk));
        $method->setReturnType(\Laminas\InputFilter\InputFilter::class);
        return $method;
    }



    public function setMethodGetInsertArray()
    {
        $globalConfig = $this->config;
        $tableInfo = $globalConfig['table_info'];

        $method = new Generator\MethodGenerator('getInsertArray');

        $method->setReturnType('array');

        $docBlock = new Generator\DocBlockGenerator();
        $docBlock
            ->setShortDescription('Returns array with values only that are set in the db-table')
            ->setLongDescription('Should be used for inserting new values into the db-table.')
            ->setTags([
                [
                    'name' => 'todo',
                    'description' => 'Adapt to your needs'
                ],
                [
                    'name' => 'return',
                    'description' => 'array'
                ]
            ]);
        $method->setDocBlock($docBlock);

        $returnArray = [];
        foreach ($tableInfo->getColumns() AS $column) {
            $name = $column->getName();
            $returnArray[$name] = new Generator\ValueGenerator("\$this->{$name}",
                    Generator\ValueGenerator::TYPE_CONSTANT);
        }

        $method->setBody('
// Remove values here that should not be used
// when inserting db-table rows, e.g. unique id        
return '
                . new Generator\ValueGenerator($returnArray)
                . ';'
        );

        return $method;
    }
    
    public function setMethodGetUpdateArray()
    {
        $globalConfig = $this->config;
        $tableInfo = $globalConfig['table_info'];

        $method = new Generator\MethodGenerator('getUpdateArray');

        $method->setReturnType('array');

        $docBlock = new Generator\DocBlockGenerator();
        $docBlock
            ->setShortDescription('Returns array with values only that are set in the db-table')
            ->setLongDescription('Should be used for updating new values into the db-table.')
            ->setTags([
                [
                    'name' => 'todo',
                    'description' => 'Adapt to your needs'
                ],
                [
                    'name' => 'return',
                    'description' => 'array'
                ]
            ]);
        $method->setDocBlock($docBlock);

        $returnArray = [];
        foreach ($tableInfo->getColumns() AS $column) {
            $name = $column->getName();
            $returnArray[$name] = new Generator\ValueGenerator("\$data['$name']",
                    Generator\ValueGenerator::TYPE_CONSTANT);
        }

        $method->setBody('$data = $this->getInsertArray();
// Remove values here that should not be used
// when updating db-table rows, e.g. unique id   
// unset($data[\'id\']); 
    
return '
                . new Generator\ValueGenerator($returnArray)
                . ';'
        );

        return $method;
    }
}
