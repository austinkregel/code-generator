<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToThrough;
use Nette\PhpGenerator\PhpNamespace;

class MakeModelCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'make:model {name} {serviceOrFeature} {--belongsTo=} {--belongsToMany=} {--hasMany=} {--hasOne=} {--table=}';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $newModelFile = new PhpFile();
        if (config('generator.use_strict_types')) {
            $newModelFile->setStrictTypes(true);
        }      
        $namespace = $newModelFile->addNamespace(config('generator.default_model_namespace').$this->argument('serviceOrFeature'));
        if ($extends = config('generator.default_model_extends')) {
            $namespace->addUse($extends);
        }
       
        // Imports
        array_map(fn ($import) => $namespace->addUse($import), config('generator.default_model_traits'));
        
        $newClass = $namespace->addClass($this->argument('name'));
        if($extends) {
            $newClass->addExtend($extends);
        }

        array_map(fn ($import) => $newClass->addTrait($import), config('generator.default_model_traits'));

        $this->addRelationMethodsAndDocumentation($namespace, $newClass);

        $this->addModelAttributesFromTable($namespace, $newClass);

        file_put_contents(getcwd().'/app/Models/' . $this->argument('serviceOrFeature') .'/'. $this->argument('name') .'.php', $newModelFile);
    }

    protected function addModelAttributesFromTable(PhpNamespace $namespace, ClassType $newClass) 
    {
        $table = $this->hasOption('table') ? $this->option('table') : config('generator.model_to_table')($this->argument('name'));

        $tableColumns = \DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->select([
                'COLUMN_NAME',
                'DATA_TYPE',
                'CHARACTER_MAXIMUM_LENGTH',
                'IS_NULLABLE',
                'NUMERIC_PRECISION',
                'NUMERIC_SCALE'
            ])
            ->where('table_name', $table)
            ->get()
            ->map(fn ($model) => [
                'name' => $model->COLUMN_NAME,
                'type' => $model->DATA_TYPE,
                'length' => $model->CHARACTER_MAXIMUM_LENGTH,
                'nullable' => ($model->IS_NULLABLE !== 'NO'),
                'precision' => $model->NUMERIC_PRECISION,
                'scale' => $model->NUMERIC_SCALE
            ])->unique('name');

        $columnNames = $tableColumns->map(fn ($row) => $row['name']);

        if (!$columnNames->contains('id')) {
            $this->addTypeAsCommentToSchema('bool', $newClass->addProperty('incrementing')->setVisibility('public')->setValue(false));
        }
        if (!$columnNames->contains('created_at') && !$columnNames->contains('updated_at')) {
            $this->addTypeAsCommentToSchema('bool', $newClass->addProperty('timestamps')->setVisibility('public')->setValue(false));
        }
        if ($columnNames->contains('deleted_at')) {
            $namespace->addUse(\Illuminate\Database\Eloquent\SoftDeletes::class);
            $newClass->addTrait(\Illuminate\Database\Eloquent\SoftDeletes::class);
        }

        $tableProperty = $newClass->addProperty('table', $table);
        $this->addTypeAsCommentToSchema('string', $tableProperty);

        $neededCasts = $tableColumns->filter(fn ($column) => match ($column['type']) {
            'float', 'double', 'decimal' => true,
            'text', 'mediumtext', 'longtext' => true,
            'tinyblob', 'blob', 'mediumblob', 'longblob' => true,
         
            default => false,
        })->reduce(fn ($columns, $column) => array_merge($columns, [
            $column['name'] => match ($column['type']) {
                'float','double','decimal' => 'float',
                'json','text','longtext' => 'json',
                default => 'string',
            }
        ]), []);
        $castsProperty = $newClass->addProperty('casts', $neededCasts);
        $this->addTypeAsCommentToSchema('string[]', $castsProperty);

        $datesProperty = $newClass->addProperty('dates', $tableColumns->filter(fn ($column) => match ($column['type']) {
            'date', 'datetime', 'timestamp' => true,
            default => false,
        })->map(fn ($column) => $column['name'])->values()->toArray());

        $this->addTypeAsCommentToSchema('string[]', $datesProperty);
        
        $fillableProperty = $newClass->addProperty('fillable', $tableColumns->filter(fn ($column) => $column['name'] != 'id' && !Str::endsWith($column['name'], '_id'))
            ->values()
            ->map(fn ($column) => $column['name'])
            ->toArray());
        $this->addTypeAsCommentToSchema('string[]', $fillableProperty);

        $tableColumns->map(fn ($column) =>$this->addOpenAPIDocumentation(
            $fillableProperty,
            $column['name'], 
            ' Description of field', 
            $column['type'],
            $column['length'] ?? $column['precision'] ?? null,
            in_array($column['name'], [
                'id', 'created_at', 'updated_at', 'deleted_at',
            ]) || Str::endsWith($column['name'], '_id'),
        ));

        $newClass->addComment('@OA\Schema(');
        $newClass->addComment('  type="object",');
        $newClass->addComment(sprintf('  required={%s}', $tableColumns->map(fn ($field) => sprintf('"%s"', $field['name']))->implode(', ')));
        $newClass->addComment(')');

        $tableColumns->unique('name')->map(fn ($field) => $newClass->addComment('@property '.$this->filterTypeToPhpType($field['type']) . ($field['nullable'] ?? false ? "|null":'') . ' $'.$field['name']));

    }

    protected function addRelationMethodsAndDocumentation(PhpNamespace $namespace, ClassType $newClass) 
    {
        $newUseStatementsBasedOnRelations = array_values(array_filter(array_merge(
            $belongsTo = array_values(array_filter(explode(',', $this->option('belongsTo') ?? ''))),
            $belongsToMany = array_values(array_filter(explode(',', $this->option('belongsToMany') ?? ''))),
            $hasMany = array_values(array_filter(explode(',', $this->option('hasMany') ?? ''))),
            $hasOne = array_values(array_filter(explode(',', $this->option('hasOne') ?? '')))
        ), fn ($value) => !empty($value)));
        
        if (!empty($belongsTo)) {
            $namespace->addUse(BelongsTo::class);
        }
        if (!empty($belongsToMany)) {
            $namespace->addUse(BelongsToThrough::class);
        }
        if (!empty($hasMany)) {
            $namespace->addUse(HasMany::class);
        }
        if (!empty($hasOne)) {
            $namespace->addUse(HasOne::class);
        }
        array_map(fn ($import) => $namespace->addUse($import), $newUseStatementsBasedOnRelations);
        
        array_map(function ($modelClassWithNamespaceToImport) use ($newClass) {
            $methodName = Str::camel($classBasename = class_basename($modelClassWithNamespaceToImport));
            $method = $newClass->addMethod($methodName);
            $method->setBody("return \$this->belongsTo({$classBasename}::class);");
            $method->setReturnType(BelongsTo::class);
        }, $belongsTo);

        array_map(function ($modelClassWithNamespaceToImport) use ($newClass) {
            $methodName = Str::camel($classBasename = class_basename($modelClassWithNamespaceToImport));
            $method = $newClass->addMethod($methodName);
            $method->addComment("@OA\\Property(");
            $method->addComment("  property=\"$methodName\",");
            $method->addComment(sprintf("  description=\"%s\",", sprintf(config('generator.belongs_to_many_description'), $classBasename)));
            $method->addComment("  type=\"array\",");
            $method->addComment("  @OA\\Items(ref=\"#components/shcemas/$classBasename\")");
            $method->addComment(")");
            $method->setBody("return \$this->belongsToMany({$classBasename}::class);");
            $method->setReturnType("BelongsToMany");
        }, $belongsToMany);
        array_map(function ($modelClassWithNamespaceToImport) use ($newClass) {

            $methodName = Str::camel($classBasename = class_basename($modelClassWithNamespaceToImport));
            $method = $newClass->addMethod($methodName);
            $method->addComment("@OA\\Property(");
            $method->addComment("  property=\"$methodName\",");
            $method->addComment(sprintf("  description=\"%s\",", sprintf(config('generator.has_many_description'), $classBasename)));
            $method->addComment("  type=\"array\",");
            $method->addComment("  @OA\\Items(ref=\"#components/shcemas/$classBasename\")");
            $method->addComment(")");
            $method->setBody("return \$this->hasMany({$classBasename}::class);");
            $method->setReturnType("HasMany");

        }, $hasMany);
        array_map(function ($modelClassWithNamespaceToImport) use ($newClass) {
            $methodName = Str::camel($classBasename = class_basename($modelClassWithNamespaceToImport));
            $method = $newClass->addMethod($methodName);
            $method->addComment("@OA\\Property(");
            $method->addComment("  property=\"$methodName\",");
            $method->addComment(sprintf("  description=\"%s\",", sprintf(config('generator.has_one_description'), $classBasename)));
            $method->addComment("  type=\"object\",");
            $method->addComment("  @OA\\Items(ref=\"#components/shcemas/$classBasename\")");
            $method->addComment(")");
            $method->setBody("return \$this->hasOne({$classBasename}::class);");
            $method->setReturnType("HasOne");
        }, $hasOne);
    }

    public function filterTypeToPhpType($type)
    {
        return match ($type) {
            'bigint', 'mediumint', 'int', 'tinyint','smallint' => 'integer',
            'float', 'double', 'decimal' => 'float',
            'char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext' => 'string',
            'tinyblob', 'blob', 'mediumblob', 'longblob' => 'string',
            'datetime', 'timestamp' => 'string',
            'date' => 'string',
            default => 'mixed',
        };
    }

    public function filterTypeToPhpFormat($type)
    {
        return match ($type) {
            'tinyint','smallint' => 'int16',
            'mediumint', 'int' => 'int16',
            'bigint' => 'int64',

            'float', 'double', 'decimal' => 'float',
            'char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext' => 'string',
            'tinyblob', 'blob', 'mediumblob', 'longblob' => 'string',
            'datetime', 'timestamp' => 'date-time',
            'date' => 'date',
            default => 'mixed',
        };
    }

    public function filterNumberType($type)
    {
        return match ($type) {
            'tinyint','smallint' => 'int16',
            'mediumint', 'int' => 'int16',
            'bigint' => 'int64',
        };
    }

    public function addTypeAsCommentToSchema($type, $schema)
    {
        $schema->addComment("@var $type");
    }

    protected function addOpenAPIDocumentation(
        $newClass,
        string $property, 
        string $description, 
        string $type,
        ?string $precision = null,
        ?bool $readonly = false
    ) {
        
        $newClass->addComment("@OA\\Property(");
        $newClass->addComment("  property=\"$property\",");
        $newClass->addComment(sprintf("  description=\"%s\",", 'The '.str_replace('_', ' ', $property)));

        if ($precision) {
            $newClass->addComment("  maxLength=$precision,");
        }
        
        if (!in_array($type, ['string', 'varchar', 'float'])) {
            $newClass->addComment(sprintf("  format=\"%s\","  , $this->filterTypeToPhpFormat($type)));
        } 
        $newClass->addComment(sprintf("  type=\"%s\"" . ($readonly ? ',' : ''), $this->filterTypeToPhpType($type)));
        
        
        if ($readonly) {
            $newClass->addComment("  readOnly=true");
        }
        $newClass->addComment(")");
    }
}
