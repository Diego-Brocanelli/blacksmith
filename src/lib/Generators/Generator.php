<?php namespace Generators;

use Console\OptionReader;
use DateTime;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Parsers\FieldParser;
use Mustache_Engine;

/**
 * Class to handle the generation of files from
 * templates
 */
class Generator implements GeneratorInterface
{
    /**
     * Filesystem object used to write
     * oute files / make dirs
     * 
     * @var Illuminate\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * Mustache Template Engine used for
     * parsing templates
     * 
     * @var Mustache_Engine
     */
    protected $mustache;

    /**
     * Field parser for parsing 
     * field level entity data
     * 
     * @var Parsers\FieldParser
     */
    protected $fieldParser;

    /**
     * Name of the file that should be written
     * 
     * @var string
     */
    protected $destinationFileName = null;

    /**
     * Name of the entity the generator
     * is working with
     * 
     * @var string
     */
    protected $entity;

    /**
     * Name of the base directory the
     * generator is working with
     * 
     * @var string
     */
    protected $base;

    /**
     * Data about specific fields
     * belonging to the Entity
     *
     * @var $array
     */
    protected $fieldData = [];

    /**
     * Var to hold the parsed template
     * 
     * @var string
     */
    protected $parsedTemplate;

    /**
     * Var to hold the final filesystem
     * destination of the template
     *
     * @var string
     */
    protected $templateDestination;

    /**
     * Command line parameter option reader
     *
     * @var \Console\OptionReader
     */
    protected $optionReader;


    /**
     * Constructor to set member vars
     * 
     * @param Filesystem $filesystem
     * @param Mustache_Engine $mustache
     */
    public function __construct(Filesystem $filesystem, Mustache_Engine $mustache, FieldParser $fieldParser, OptionReader $optionReader)
    {
        $this->filesystem = $filesystem;
        $this->mustache = $mustache;
        $this->fieldParser = $fieldParser;
        $this->optionReader = $optionReader;
    }



    /**
     * Function to create and write out a parsed template
     * 
     * @param  string $entity         name of the entity being operated on
     * @param  string $sourceTemplate path to the raw template to work with
     * @param  string $destinationDir destination directory to write parsed template into
     * @param  string $fileName       templatized filename (not path) to write to
     * @return bool
     */
    public function make($entity, $sourceTemplate, $destinationDir, $fileName = null)
    {
        //set the entity we're creating for
        //later template parsing operations
        list($this->base, $this->entity) = $this->parseEntity($entity);

        //if any field data was given, parse it
        $fieldData = $this->optionReader->getFields();
        if ($fieldData) {
            $this->fieldData = $this->fieldParser->parse($fieldData);
        }

        //set local var for template vars used for subsequent calls
        $templateVars = $this->getTemplateVars();

        //set into a class var vor getFileName() to use if necessary
        if (! is_null($fileName)) {
            $this->destinationFileName = $this->mustache
                ->render($fileName, $templateVars);
        }

        //parse any template values in the destinationDir
        $destinationDir = $this->mustache->render($destinationDir, $templateVars);

        //get the compiled template
        $this->parsedTemplate = $this->getTemplate($sourceTemplate);

        //find out where to write the file
        $this->templateDestination = implode(DIRECTORY_SEPARATOR, [$destinationDir, $this->getFileName()]);

        //dir where template will be written
        $templateDestinationDir = pathinfo($this->templateDestination, PATHINFO_DIRNAME);

        //create directory rendered template will go into if necessary
        if (! $this->filesystem->exists($templateDestinationDir)) {
            $this->filesystem->makeDirectory($templateDestinationDir, 0777, true);
        }

        //actually do the write operation
        if (! $this->filesystem->exists($this->templateDestination) || $this->optionReader->isGenerationForced()) {
            return $this->filesystem->put($this->templateDestination, $this->parsedTemplate) !== false;
        }

        return false;
    }


    /**
     * Function to return the text of the 
     * final parsed template
     * 
     * @return string
     */
    public function getParsedTemplate()
    {
        return $this->parsedTemplate;
    }


    /**
     * Function to return the filesystem location
     * of the parsed template's final destination
     * 
     * @return string
     */
    public function getTemplateDestination()
    {
        return $this->templateDestination;
    }


    /**
     * Function to return data about 
     * fields for the entity
     * 
     * @return array
     */
    public function getFieldData()
    {
        return $this->fieldData;
    }


    /**
     * Function to get the minimum template variables
     * 
     * @return array
     */
    public function getTemplateVars()
    {
        $entity = $this->getEntityName();
        $base = $this->getBaseName();
        $namespace = $this->getNamespace();

        return [
            'Base' => $base,
            'Namespace' => $namespace,
            'Entity'     => Str::studly($entity),
            'Entities'   => Str::plural(Str::studly($entity)),
            'collection' => Str::plural(Str::snake($entity)),
            'instance'   => Str::singular(Str::snake($entity)),
            'fields'     => $this->getFieldData(),
            'year'       => (new DateTime())->format('Y')
        ];
    }


    /**
     * Function to get the name of the entity
     * we're working with
     * 
     * @return string
     */
    public function getEntityName()
    {
        return $this->entity;
    }


    /**
     * Function to get the name of the base
     * directory we're working with
     * 
     * @return string
     */
    public function getBaseName()
    {
        if (is_array($this->base)) {
            $base = array_map(function ($value) {
                return Str::studly($value);
            }, $this->base);

            return implode('/', $base);
        }

        return Str::studly($this->base);
    }


    /**
     * Function to get the namespace of the
     * file we're working with
     * 
     * @return string
     */
    public function getNamespace()
    {
        if (is_array($this->base)) {
            $base = array_map(function ($value) {
                return Str::studly($value);
            }, $this->base);

            return implode('\\', $base);
        }

        return Str::studly($this->base);
    }


    /**
     * Function to return the name of the file
     * that should be written
     * 
     * @return string
     */
    public function getFileName()
    {
        return $this->destinationFileName ?: $this->getEntityName() .".php";
    }


    /**
     * Read the template file from the Filesystem and compile
     *
     * @param  string $sourceTemplate
     * @param  $name Name of file
     * @return string
     */
    public function getTemplate($sourceTemplate)
    {
        $rawTemplate = $this->filesystem->get($sourceTemplate);
        return $this->mustache->render($rawTemplate, $this->getTemplateVars());
    }
    

    /**
     * Parse entity argument into Base and Entity
     * 
     * @param  string $entity
     * @return array
     */
    protected function parseEntity($entity)
    {
        if (Str::contains($entity, '.')) {
            $arguments = explode('.', $entity);
            $entity = array_pop($arguments);

            return [$arguments, $entity];
        }

        return [$entity, $entity];
    }
}
