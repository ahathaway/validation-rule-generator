<?php
/*
 * Copyright (c) Portland Web Design, Inc 2023.
 */

namespace ahathaway\ValidationRuleGenerator;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;


/**
 * Class MakeValidationCommand
 */
class MakeValidationCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:validation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate validation rules for a given model';


    /**
     * @var Generator
     */
    protected $generator;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Generator $generator)
    {
        parent::__construct();

        $this->generator = $generator;
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        if ($this->option('all')) {
            var_export($this->generator->getAllTableRules());
            return;
        }

        $table = $this->option('table');
        if ($table) {
            echo $table . ' ' . str_repeat('-', min(0, 60 - strlen($table))) . PHP_EOL;
            var_export($this->generator->getTableRules($table));
            return;
        }

        $model = $this->option('model');
        if ($model) {
            echo $model . ' ' . str_repeat('-', min(0, 60 - strlen($model))) . PHP_EOL;
            var_export($this->generator->getModelRules($model));
            return;
        }

        $this->error('Please specify table or model to generate');
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return array(
            array(
                'model',
                null,
                InputOption::VALUE_REQUIRED,
                'Model for which to generate rules (include overrides)'
            ),
            array(
                'table',
                null,
                InputOption::VALUE_REQUIRED,
                'Table for which to generate rules'
            ),
            array(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Generate rules for all tables'
            ),
        );
    }


}

