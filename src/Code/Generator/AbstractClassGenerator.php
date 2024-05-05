<?php

namespace DbTableInstigator\Code\Generator;

use Laminas\Code\Generator;

use Laminas\Db\Metadata\Object\{
    TableObject,
    ColumnObject
};

class AbstractClassGenerator extends Generator\ClassGenerator
{

    public function __construct(
        protected array $config
    ) {
        parent::__construct();
    }

    public function generate()
    {
        $config = $this->config;
        $this
            ->setName($config['class_name'])
            ->setNamespaceName($config['namespace']);

        return parent::generate();
    }

    protected function retrieveFormInputType(string|ColumnObject $type): string
    {
        if ($type instanceof ColumnObject) {
            $type = $type->getDataType();
        }
        switch (strtolower($type)) {
            case 'int':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
            case 'decimal':
            case 'float':
            case 'double':
                return 'Element\Number::class';
            case 'text':
                return 'Element\Textarea::class';
            case 'enum':
                return 'Element\Select::class';
            default:
                return 'Element\Text::class';
        }
    }
    /**
     * 
     * @param string|ColumnObject $type
     * @return string
     */
    public function mapField(string|ColumnObject $type): string
    {
        if ($type instanceof ColumnObject) {
            $type = $type->getDataType();
        }
        /*
          if(str_contains($type, 'mail')) {
          return 'email';
          }
          if($type == 'email') {
          return 'hmmm-email';
          }
         */
        switch (strtolower($type)) {
            case 'int':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
                return 'int';
            case 'decimal':
            case 'float':
            case 'double':
                return 'float';
                break;
            case 'varchar':
            case 'text':
            case 'datetime':
            case 'enum':
            case 'char':
            case 'varchar':
            case 'tinytext':
            case 'longtext':
            case 'text':
                return 'string';
                break;
            default:
                return 'unknown';
                break;
        }
    }

    public function generateElementInputFilterConfig(ColumnObject $column)
    {
        $globalConfig = $this->config;
        $type = $this->mapField($column);

        $config = [
            'name' => $column->getName(),
            'required' => !$column->isNullable(),
            'filters' => [],
            'validators' => [],
        ];

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

        return $config;
    }
}
