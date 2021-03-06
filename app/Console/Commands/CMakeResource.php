<?php

namespace App\Console\Commands;


use App\Requests\Request;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Composer;
use Illuminate\View\Factory;

class CMakeResource extends Command
{
    /** @var Composer */
    private $composer;

    /** @var Factory $view */
    private $view;

    /** @var string */
    private $pascalCase = '';

    /** @var string */
    private $camelCase = '';

    /** @var string */
    private $plural = '';

    /** @var Collection */
    private $dataTypes = [];

    /** @var Collection */
    private $primaryIdDataTypes = [];

    /** @var bool $implementSoftDeletes */
    private $implementSoftDeletes = false;

    private $useUuidAsPrimaryKey = false;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cmake:resource
        {name : The name of the resource}
        {plural : The plural name of the resource}
        {fields* : The fields that the resource should have}
        {--softDeletes : Implement soft deletes for the resource}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate an API resource including its model, resource, controller, factory and migrations';

    public function __construct(Composer $composer)
    {
        parent::__construct();

        $this->view = app('view');
        $this->composer = $composer;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->implementSoftDeletes = $this->option('softDeletes');

        $name = $this->argument('name');
        $this->camelCase = camel_case($name);
        $this->pascalCase = ucfirst($this->camelCase);
        $this->plural = $this->argument('plural');

        $this->view->addLocation(implode(DIRECTORY_SEPARATOR, [__DIR__, 'stubs']));

        $this->dataTypes = collect($this->argument('fields'))->map(function ($definition) {
            return DataType::fromString($definition);
        });

        $this->primaryIdDataTypes = $this->dataTypes->filter(function (DataType $dataType) {
            return $dataType->isPrimaryKey();
        });

        $this->primaryIdDataTypes->each(function (DataType $dataType) {
            if ($dataType->getType() === 'uuid' && $dataType->isPrimaryKey()) {
                $this->useUuidAsPrimaryKey = true;
            }
        });

        $this->createMigration();
        $this->createFactory();
        $this->createTest();
        $this->createModel();
        $this->createViewResource();
        $this->createCollectionViewResource();
        $this->createController();
        $this->createRoutes();

        $this->line('<info>Dumping composer autoload</info>');
        $this->composer->dumpAutoloads();
    }

    public function createMigration()
    {
        $now = Carbon::now();
        $fileName = $now->format('Y_m_d_His') . '_' . "create_{$this->plural}.php";

        $viewParams = $this->getViewParams();
        $viewParams['shouldRenderPrimaryKeys'] =
            $this->primaryIdDataTypes->first(function (DataType $dataType) {
                return $dataType->isIncrements();
            }) === null;

        $contents = $this->view->make('migration', $viewParams)->render();
        $dest = base_path(implode(DIRECTORY_SEPARATOR,
            ['database', 'migrations', $fileName]));

        $this->writeFile($dest, '<?php' . PHP_EOL . PHP_EOL . $contents);
    }

    public function createFactory()
    {
        $viewParams = $this->getViewParams();
        $fileName = $this->pascalCase . 'Factory.php';
        $contents = $this->view->make('factory', $viewParams)->render();
        $dest = base_path(implode(DIRECTORY_SEPARATOR,
            ['database', 'factories', $fileName]));

        $this->writeFile($dest, '<?php' . PHP_EOL . PHP_EOL . $contents);
    }

    public function createTest()
    {
        $viewParams = $this->getViewParams();
        $fileName = $this->pascalCase . 'Test.php';
        $contents = $this->view->make('test', $viewParams)->render();
        $dest = base_path(implode(DIRECTORY_SEPARATOR,
            ['tests', $fileName]));

        $this->writeFile($dest, '<?php' . PHP_EOL . PHP_EOL . $contents);
    }

    public function createViewResource()
    {
        $viewParams = $this->getViewParams();
        $modelContents = $this->view->make('resource', $viewParams)->render();
        $dest = base_path(implode(DIRECTORY_SEPARATOR,
            ['app', 'Resources', "{$this->pascalCase}.php"]));

        $this->writeFile($dest, '<?php' . PHP_EOL . PHP_EOL . $modelContents);
    }

    public function createCollectionViewResource()
    {
        $modelContents = $this->view->make('resources', $this->getViewParams())->render();
        $dest = base_path(implode(DIRECTORY_SEPARATOR,
            ['app', 'Resources', "{$this->pascalCase}Collection.php"]));

        $this->writeFile($dest, '<?php' . PHP_EOL . PHP_EOL . $modelContents);
    }

    public function createModel()
    {
        $viewParams = $this->getViewParams();

        $modelContents = $this->view->make('model', $viewParams)->render();
        $dest = base_path(implode(DIRECTORY_SEPARATOR,
            ['app', "{$this->pascalCase}.php"]));

        $this->writeFile($dest, '<?php' . PHP_EOL . PHP_EOL . $modelContents);
    }

    public function createController()
    {
        $inlineValidate = true;
        $inlineSnakeCase = true;
        if (class_exists('App\Http\Controllers\Controller')) {
            $controller = new \App\Http\Controllers\Controller();
            $attributes = $controller->validate(app(Request::class), []);
            $inlineValidate = $attributes === null;
            $inlineSnakeCase = !method_exists($controller, 'keysToSnakeCase');
        }

        $viewParams = array_merge(compact('inlineValidate', 'inlineSnakeCase'),
            $this->getViewParams());

        $contents = $this->view->make('controller', $viewParams)->render();
        $dest = base_path(implode(DIRECTORY_SEPARATOR,
            ['app', 'Http', 'Controllers', "{$this->pascalCase}Controller.php"]));

        $this->writeFile($dest, '<?php' . PHP_EOL . PHP_EOL . $contents);
    }

    public function createRoutes()
    {
        $viewParams = $this->getViewParams();
        $contents = $this->view->make('routes', $viewParams)->render();
        $web = base_path(implode(DIRECTORY_SEPARATOR, [
            'routes', 'web.php'
        ]));

        file_put_contents($web, PHP_EOL . $contents . PHP_EOL, FILE_APPEND);
    }

    public function getViewParams()
    {
        return [
            'pascalCase' => $this->pascalCase,
            'camelCase' => $this->camelCase,
            'plural' => $this->plural,
            'dataTypes' => $this->dataTypes,
            'primaryIdDataTypes' => $this->primaryIdDataTypes,
            'implementSoftDeletes' => $this->implementSoftDeletes,
            'useUuidAsPrimaryKey' => $this->useUuidAsPrimaryKey
        ];
    }

    private function writeFile($dest, $contents)
    {
        if (file_exists($dest)) {
            $question = "File $dest already exists. Should I override this file? (Y/N)";
            $shouldOverride = null;
            while ($shouldOverride === null) {
                $answer = strtolower($this->ask($question));
                $shouldOverride = $answer === 'y';
            }

            if ($shouldOverride === false) {
                return;
            }
        }

        $handle = fopen($dest, 'w');
        fwrite($handle, $contents);
        fclose($handle);

        $this->line("<info>Created:</info> {$dest}");
    }
}
