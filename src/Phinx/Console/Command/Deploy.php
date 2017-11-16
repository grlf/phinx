<?php
namespace Phinx\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Deploy extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('deploy')
            ->setDescription('Update Phinx paths and migrate')
	        ->addOption(
		        'composer-file',
		        null,
		        InputOption::VALUE_REQUIRED,
		        'composer.json file to check for packages containing Phinx migrations',
		        'composer.json'
	        )
            ->setHelp(sprintf(
                '%sUpdates the phinx paths using `discover` and the migrates.%s',
                PHP_EOL,
                PHP_EOL
            ));
    }

    /**
     * Initializes the application.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
	    //$input->getOption('composer-file');
	    $command = $this->getApplication()->find('discover');

	    $arguments = array(
	    	'command' => 'discover',
		    '--composer-file' => $input->getOption('composer-file')
	    );

	    $commandInput = new ArrayInput($arguments);
	    $returnCode = $command->run($commandInput, $output);

	    $command = $this->getApplication()->find('migrate');
	    $command->run(new ArrayInput(['command' => 'migrate']), $output);

    }
}
