<?php

namespace DbTableInstigator\Code\Generator;

use Laminas\Code\Generator;

class FormClassGenerator extends AbstractClassGenerator
{
    public function generateMethodConstruct()
    {
        $globalConfig = $this->config;
        $method = new Generator\MethodGenerator('__construct');
        $method->setParameter([
            'name' => 'name',
            'type' => 'string',
            'defaultvalue' => null
        ]);

        $body = "parent::__construct(\$name);" . str_repeat(PHP_EOL, 2);

        foreach ($globalConfig['table_info']->getColumns() as $column) {
            $elementType = $this->retrieveFormInputType($column);
            $config = [
                'name' => $column->getName(),
                'type' => new Generator\ValueGenerator(
                    $this->retrieveFormInputType($column),
                    Generator\ValueGenerator::TYPE_CONSTANT
                ),
                'options' => [
                    'label' => ucfirst($column->getName()),
                ],
                'attributes' => [
                    'id' => strtolower($column->getName())
                ]
            ];
            if ($column->isNullable() === false) {
                $config['attributes']['required'] = true;
            }
            if ($globalConfig['bootstrap']) {
                $config['options']['label_attributes']['class'] =  'form-label';
                $config['attributes']['class'] = 'form-control';
            }

            if (strtolower($column->getDataType()) == 'enum') {
                $config['options']['value_options'] = [];
            }

            $valueGenerator = new Generator\ValueGenerator($config);
            $body .= sprintf('$this->add(%s);%s', $valueGenerator->generate(), str_repeat(PHP_EOL, 2));
        }

        $method->setBody($body);
        return $method;
    }

    public function generateMethodGetInputFilterSpecification()
    {

        $globalConfig = $this->config;
        $method = new Generator\MethodGenerator('getInputFilterSpecification');

        $this->addUse('Laminas\Filter')->addUse('Laminas\Validator');

        $body = 'return [' . PHP_EOL;

        foreach ($globalConfig['table_info']->getColumns() as $column) {
            $valueGenerator = new Generator\ValueGenerator($this->generateElementInputFilterConfig($column));
            $body .= sprintf('%s,%s', $valueGenerator->generate(), PHP_EOL);
        }
        $body .= PHP_EOL . '];';
        $method->setBody($body);
        return $method;
    }
    
    public function generate()
    {
        $this->addMethodFromGenerator($this->generateMethodConstruct())
            ->addUse('Laminas\Form\Form')
            ->addUse('Laminas\Form\Element')
            ->setExtendedClass('Laminas\Form\Form');


        if ($this->config['inputfilter']) {
            $this->setImplementedInterfaces(['Laminas\InputFilter\InputFilterProviderInterface']);
            $this->addMethodFromGenerator($this->generateMethodGetInputFilterSpecification());
        }

        return parent::generate();
    }
}
