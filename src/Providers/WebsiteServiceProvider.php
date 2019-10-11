<?php
namespace Akali\Postgrenerator\Providers;

use Akali\Postgrenerator\Support\Helpers;
use File;
use Illuminate\Support\ServiceProvider;

class WebsiteServiceProvider extends ServiceProvider
{
    public function boot()
    {
        dd('loadaded service provider of package');
        $dir = __DIR__ . '/../';

        // publish the config base file
        $this->publishes([
            $dir . 'config/laravel-code-generator.php' => config_path('laravel-code-generator.php'),
        ], 'config');

        // publish the default-template
        $this->publishes([
            $dir . 'templates/default' => $this->codeGeneratorBase('templates/default'),
        ], 'default-template');

        // publish the defaultcollective-template
        $this->publishes([
            $dir . 'templates/default-collective' => $this->codeGeneratorBase('templates/default-collective'),
        ], 'default-collective-template');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $commands =
            [
                'Akali\Postgrenerator\Commands\Framework\CreateControllerCommand',
                'Akali\Postgrenerator\Commands\Framework\CreateModelCommand',
                'Akali\Postgrenerator\Commands\Framework\CreateLanguageCommand',
                'Akali\Postgrenerator\Commands\Framework\CreateFormRequestCommand',
                'Akali\Postgrenerator\Commands\Framework\CreateRoutesCommand',
                'Akali\Postgrenerator\Commands\Framework\CreateMigrationCommand',
                'Akali\Postgrenerator\Commands\Framework\CreateScaffoldCommand',
                'Akali\Postgrenerator\Commands\Framework\CreateResourcesCommand',
                'Akali\Postgrenerator\Commands\Framework\CreateMappedResourcesCommand',
                'Akali\Postgrenerator\Commands\Resources\ResourceFileFromDatabaseCommand',
                'Akali\Postgrenerator\Commands\Resources\ResourceFileCreateCommand',
                'Akali\Postgrenerator\Commands\Resources\ResourceFileDeleteCommand',
                'Akali\Postgrenerator\Commands\Resources\ResourceFileAppendCommand',
                'Akali\Postgrenerator\Commands\Resources\ResourceFileReduceCommand',
                'Akali\Postgrenerator\Commands\Views\CreateIndexViewCommand',
                'Akali\Postgrenerator\Commands\Views\CreateCreateViewCommand',
                'Akali\Postgrenerator\Commands\Views\CreateFormViewCommand',
                'Akali\Postgrenerator\Commands\Views\CreateEditViewCommand',
                'Akali\Postgrenerator\Commands\Views\CreateShowViewCommand',
                'Akali\Postgrenerator\Commands\Views\CreateViewsCommand',
                'Akali\Postgrenerator\Commands\Views\CreateViewLayoutCommand',
                'Akali\Postgrenerator\Commands\Views\CreateLayoutCommand',
                'Akali\Postgrenerator\Commands\Api\CreateApiControllerCommand',
                'Akali\Postgrenerator\Commands\Api\CreateApiScaffoldCommand',
                'Akali\Postgrenerator\Commands\ApiDocs\CreateApiDocsControllerCommand',
                'Akali\Postgrenerator\Commands\ApiDocs\CreateApiDocsScaffoldCommand',
                'Akali\Postgrenerator\Commands\ApiDocs\CreateApiDocsViewCommand',
            ];

        if (Helpers::isNewerThanOrEqualTo()) {
            $commands = array_merge($commands,
                [
                    'Akali\Postgrenerator\Commands\Migrations\MigrateAllCommand',
                    'Akali\Postgrenerator\Commands\Migrations\RefreshAllCommand',
                    'Akali\Postgrenerator\Commands\Migrations\ResetAllCommand',
                    'Akali\Postgrenerator\Commands\Migrations\RollbackAllCommand',
                    'Akali\Postgrenerator\Commands\Migrations\StatusAllCommand',
                ]);
        }

        if (Helpers::isApiResourceSupported()) {
            $commands = array_merge($commands,
                [
                    'Akali\Postgrenerator\Commands\Api\CreateApiResourceCommand',
                ]);
        }

        $this->commands($commands);
    }

    /**
     * Create a directory if one does not already exists
     *
     * @param string $path
     *
     * @return void
     */
    protected function createDirectory($path)
    {
        if (!File::exists($path)) {
            File::makeDirectory($path, 0777, true);
        }
    }

    /**
     * Get the laravel-code-generator base path
     *
     * @param string $path
     *
     * @return string
     */
    protected function codeGeneratorBase($path = null)
    {
        return base_path('resources/laravel-code-generator/') . $path;
    }
}
