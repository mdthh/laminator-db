<?php

namespace DbTableInstigator\Command;

use DbTableInstigator\Code\Generator\FormClassGenerator;
use Symfony\Component\Console\Command\Command;

use Symfony\Component\Console\Input\{
    InputInterface,
    InputArgument,
    InputOption
};
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Attribute\AsCommand;

use DbTableInstigator\App;
use DbTableInstigator\Code\Generator\EntityClassGenerator;

#[AsCommand(
    name: 'entity',
    description: 'Creates a new entity.',
    hidden: false,
)]
class EntityCommand extends AbstractCommand
{


    protected $keyName = 'Entity';

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'inputfilter',
            'i',
            InputOption::VALUE_NONE,
            'Add input-filter'
        );

    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = parent::init($input, $output);

        if (!is_array($config)) {
            return 0;
        }

        $config['inputfilter'] = $input->getOption('inputfilter');

        // Generate Code!
        $generator = new EntityClassGenerator($config);
        $code = "<?php\n"
            . App::getTemplateHeader() . str_repeat(PHP_EOL, 2)
            . $generator->generate();

        file_put_contents($config['file_path'], $code);
        $output->writeln(sprintf('<fg=green>Created file %s</>', $config['file_path']));


        return Command::SUCCESS;
    }
}
