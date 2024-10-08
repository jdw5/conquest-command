<?php

declare(strict_types=1);

namespace Conquest\Command\Database\Migrations;

use Conquest\Command\Concerns\HasSchemaColumns;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'conquest:migration', description: 'Create a new migration file.')]
class ConquestMigrationCommand extends Command implements PromptsForMissingInput
{
    use HasSchemaColumns;

    /**
     * Required base column for the schema.
     *
     * @var string
     */
    protected $id = '$table->id();';

    /**
     * The migration creator instance.
     *
     * @var \Illuminate\Database\Migrations\ConquestMigrationCreator
     */
    protected $creator;

    /**
     * Create a new migration install command instance.
     *
     * @param  \Illuminate\Database\Migrations\MigrationCreator  $creator
     * @return void
     */
    public function __construct(ConquestMigrationCreator $creator)
    {
        parent::__construct();
        $this->creator = $creator;
    }

    public function handle()
    {
        $this->creator->setContent($this->getColumns());
        $file = $this->creator->create(
            $this->getFileName(), base_path('migrations'), $this->getClassName(), true
        );

        $this->components->info(sprintf('Migration [%s] created successfully.', $file));
    }

    protected function getColumns()
    {
        if (! $this->option('columns')) {
            return $this->id;
        }

        return str($this->getSchemaColumns()
            ->map(fn (array $column) => "\t\t\t".$column[0]->blueprint($column[1]))
            ->implode("\n")
        )->prepend($this->id."\n")
            ->value();
    }

    protected function getClassName(): string
    {
        return str($this->getNameInput())
            ->plural()
            ->snake()
            ->value();
    }

    protected function getFileName(): string
    {
        return str($this->getNameInput())
            ->snake()
            ->prepend('create_')
            ->append('_table')
            ->value();
    }

    protected function getNameInput()
    {
        $name = trim($this->argument('name'));

        if (Str::endsWith($name, '.php')) {
            return Str::substr($name, 0, -4);
        }

        return $name;
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the migration.'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the migration even if it already exists.'],
            ['columns', 'c', InputOption::VALUE_REQUIRED, 'The columns of the migration.'],
            ['suppress', 's', InputOption::VALUE_NONE, 'Suppress the confirmation prompts.'],
        ];
    }

    protected function promptForMissingArgumentsUsing()
    {
        return [
            'name' => [
                'What should the migration be named?',
                'E.g. create_users_table',
            ],
        ];
    }

    /**
     * Interact further with the user if they were prompted for missing arguments.
     *
     * @return void
     */
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output)
    {
        if ($this->didReceiveOptions($input)) {
            return;
        }

        $columns = $this->promptForSchemaColumns();

        $input->setOption('columns', $columns);
        $this->confirmedDuringPrompting = true;
    }
}
