<?php

namespace DbTableInstigator\Command;

use DomainException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{
    InputInterface,
    InputArgument,
    InputOption
};
use Symfony\Component\Console\Question\{
    Question,
    ChoiceQuestion,
    ConfirmationQuestion
};

use Symfony\Component\Console\Output\OutputInterface;
use Laminas\Db\Metadata\Source\AbstractSource;

use DbTableInstigator\MvcTool;
use Laminas\Filter\FilterChain;
use Laminas\Filter;

abstract class AbstractCommand extends Command
{

    protected string $appDir;

    protected AbstractSource $dbMetadata;

    protected array $config = [];

    protected $classNameFilter;

    protected function configure(): void
    {
        $this->addArgument('table', InputArgument::OPTIONAL, 'The db-table.');

        $this->addOption(
            'module',
            'm',
            InputOption::VALUE_OPTIONAL,
            'In which module should we inject the file?'
        );

        $this->addOption(
            'author',
            'a',
            InputOption::VALUE_OPTIONAL,
            'Name of the author (this is probably You!)'
        );
        $this->addOption(
                'force-overwrite',
                'f',
                InputOption::VALUE_NONE,
                'Force overwriting of existing file.'
        );
    }


    protected function init(InputInterface $input, OutputInterface $output)
    {

        $metadata = $this->dbMetadata;
        $appDir = $this->appDir;
        $questionHelper = $this->getHelper('question');
        $allTables = $metadata->getTableNames();
        $config = $this->config;

        $result = [];

        if (empty($allTables)) {
            throw new DomainException("There are no tables in database");
        }

        // Get db table name
        $table = $input->getArgument('table');
        if (!$table) {
            $question = new ChoiceQuestion(
                'Please select the database table.',
                $allTables
            );
            $table = $questionHelper->ask($input, $output, $question);
        }

        if (!in_array($table, $allTables)) {

            throw new DomainException(sprintf(
                "There is no table '%s' in the database",
                $table
            ));
        }

        $result['table'] = $table;
        $result['table_info'] = $metadata->getTable($table);

        // Get module name in which to create code file

        $availableModules = MvcTool::getAllModules($appDir);
        $module = $input->getOption('module');

        if (null === $module) {
            $question = new ChoiceQuestion(
                'Please select the module:',
                $availableModules
            );
            $module = $questionHelper->ask($input, $output, $question);
        }

        if (!MvcTool::hasModule($module, $appDir)) {
            $output->writeln(sprintf("<comment>There is no module named '%s'<comment>", $module));
            $question = new ChoiceQuestion(
                'Please select the module from the list',
                $availableModules
            );
            $module = $questionHelper->ask($input, $output, $question);
            $result['module'] = $module;
        }

        // The class name:
        $className = 
        ucfirst($this->getClassNameFilter()->filter($table))
        .$this->keyName;

        $result['class_name'] = $className;

        // The namespace:
        $namespace = $module . '\\' . $this->keyName;
        $result['namespace'] = $namespace;


        // The path of the new file:
        $filePath = sprintf(
            '%s/module/%s/src/%s/%s.php',
            $appDir,
            $module,
            $this->keyName,
            $className
        );
        $result['file_path'] = $filePath;
        $fileDir = dirname($filePath);

        // Does the target file already exist?
        if ($input->getOption('force-overwrite') == false && file_exists($filePath)) {
            throw new \DomainException(sprintf(
                "File %s already exists."
                    . " Command '%s' cautiously refuses to overwrite existing files." . PHP_EOL
                    . "Use option -f|--force-overwrite to be more courages so as to overwrite existing files",
                $filePath,
                $this->getName()
            ));
        }
        if (file_exists($filePath)) {
            $helper = $this->getHelper('question');
            $output->writeln(sprintf('<fg=red>Warnung: File %s already exists.</>', $filePath));
            $question = new ConfirmationQuestion('Overwrite this file? The current file\'s content will be gone forever. [y/n]' . PHP_EOL, false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<fg=yellow>Aborted by user. Nothing happend.</>');
                return 0;
            }
        }
        // Get author name - used for PHP class comments
        $author = $this->config['tool']['author'] ?? $input->getOption('author');
        /*
        if (!$author) {
            $question = new Question(
                'Please enter the name of the author. This is probably You! ;-) Press Enter to leave empty. ' . PHP_EOL
            );
            $author = $questionHelper->ask($input, $output, $question);

            $result['author_name'] = $author;
        }
*/
        // If target dir does not exits, try to create it:
        if (!is_dir($fileDir)) {
            if (!mkdir($fileDir, recursive: true)) {
                throw new \DomainException(sprintf(
                    "Unable to create directory %s",
                    $fileDir
                ));
            }
        }

        return $result;
    }


    /**
     * Set the value of appDir
     *
     * @param string $appDir
     *
     * @return self
     */
    public function setAppDir(string $appDir): self
    {
        $this->appDir = $appDir;

        return $this;
    }


    /**
     * Set the value of dbMetadata
     *
     * @param AbstractSource $dbMetadata
     *
     * @return self
     */
    public function setDbMetadata(AbstractSource $dbMetadata): self
    {
        $this->dbMetadata = $dbMetadata;

        return $this;
    }



    /**
     * Set the value of config
     *
     * @param array $config
     *
     * @return self
     */
    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get the value of classNameFilter
     *
     * @return FilterChain
     */
    public function getClassNameFilter(): FilterChain
    {
        if (null !== $this->classNameFilter) {
            return $this->classNameFilter;
        }

        $filters = new FilterChain();
        $filters
            ->attach(new Filter\Word\DashToCamelCase())
            ->attach(new Filter\Word\UnderscoreToCamelCase());

        $this->classNameFilter = $filters;

        return $this->classNameFilter;
    }
}
