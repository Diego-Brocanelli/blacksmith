<?php namespace Generators;

use Illuminate\Filesystem\Filesystem;
use Parsers\FieldParser;
use Mustache_Engine;
use Mockery as m;

class FormTest extends \BlacksmithTest
{

    public function testParentClass()
    {
        $instance = new Form(
            new Filesystem,
            new Mustache_Engine,
            new FieldParser
        );
        $this->assertInstanceOf("Generators\Generator", $instance);
    }



    public function testGetTemplateVars()
    {
        $generator = m::mock('Generators\Form');
        $generator->shouldDeferMissing();

        $generator->shouldReceive('getEntityName')->once()
            ->andReturn('Order');

        $fieldData = [
            'name' => ['type' => 'string']
        ];

        $generator->shouldReceive('getFieldData')->once()
            ->andReturn($fieldData);

        $form_rows = [
            [
                'label' => "{{ Form::label('name', 'Name:') }}",
                'element' => "{{ Form::text('name') }}"
            ]
        ];

        $expected = [
            'Entity'     => 'Order',
            'Entities'   => 'Orders',
            'collection' => 'orders',
            'instance'   => 'order',
            'fields'     => $fieldData,
            'form_rows'  => $form_rows
        ];

        $this->assertEquals($expected, $generator->getTemplateVars());
    }
}
