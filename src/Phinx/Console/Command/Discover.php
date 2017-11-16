<?php

namespace Phinx\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Discover extends Command
{
	protected $dir;
	protected $output;
	protected $phinx_config;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('discover')
            ->setDescription('Import all phinx directories in composer packages')
            ->addOption(
                'composer-file',
                null,
                InputOption::VALUE_REQUIRED,
                'composer.json file to check for packages containing Phinx migrations',
                'composer.json'
                )
            ->setHelp(sprintf(
                '%sWill look in all installed vendor directories for a "phinx" folder to configuration file.  Configuration file must be PHP.%s',
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
    	$this->output = $output;
    	$this->dir = getcwd();

        $composer_file = $this->dir . '/' . $input->getOption('composer-file');
        $this->phinx_config = $this->dir . '/phinx.php';

        //Check for PHP config.
        if (!file_exists($this->phinx_config)) {
            $this->output->writeln('<error>Phinx needs to have a configuration file in PHP format for this command.</error>');
            return;
        }

        if (!file_exists($composer_file)) {
            $this->output->writeln('<error>' . $composer_file . ' does not exist.</error>');
            return;
        }

        //Read composer json and load packages
        $packages = json_decode(file_get_contents($composer_file),true);
        $packages = $packages['require'];

        //Loop through packages and see if they should be added to phinx
        $migrations = array();
        $seeds = array();
        foreach ($packages as $package => $branch) {
            if (file_exists($this->dir . '/vendor/' . $package . '/phinx/migrations/')) {
                $migrations[] = '%%PHINX_CONFIG_DIR%%/vendor/' . $package . '/phinx/migrations';
            }
            if (file_exists($this->dir . '/vendor/' . $package . '/phinx/seeds')) {
                $seeds[] = '%%PHINX_CONFIG_DIR%%/vendor/' . $package . '/phinx/seeds';
            }
        }

        //Add to PHP config
        if (empty($migrations) && empty($seeds)) {
            $this->output->writeln('<info>No phinx directories found to add.</info>');
            return;
        }

	    $phinx = include $this->phinx_config;

        //Remove any packages already defined in the config
        $migrations = array_filter($migrations, function($migration) use ($phinx) {
            return !in_array($migration, $phinx['paths']['migrations']);
        });

        $seeds = array_filter($seeds, function($seed) use ($phinx) {
            return !in_array($seed, $phinx['paths']['seeds']);
        });

	    $this->_updateConfig($migrations, 'migrations');
	    $this->_updateConfig($seeds, 'seeds');

	    $this->output->writeln('<info>Subdirectories imported.</info>');
    }

	/**
	 * @param $paths - An array of paths to be added to the config at the specified config key
	 * @param $config_key - The key in the config array to append the paths after
	 *
	 * @internal param OutputInterface $output
	 * @internal param $dir
	 * @internal param $this ->>phinx_config
	 */
	protected function _updateConfig($paths, $config_key)
	{
		//Write back out new phinx configuration
		$tmp_file = $this->dir . "/temp.txt";

		//copy file to prevent double entry
		copy($this->phinx_config, $tmp_file) or exit("failed to copy $this->phinx_config");

		//load file into $lines array
		$fc = fopen($this->phinx_config, "r");
		$lines = array();
		while (!feof($fc))
		{
			$buffer  = fgets($fc, 4096);
			$lines[] = $buffer;
		}

		fclose($fc);

		//open same file and use "w" to clear file
		$f = fopen($tmp_file, "w") or die("couldn't open $this->phinx_config");

		//loop through array using foreach
		foreach ($lines as $line)
		{
			fwrite($f, $line); //place $line back in file
			if (strstr($line, '"' . $config_key . '" => [') && !empty($paths))
			{
				foreach ($paths as $path)
				{
					fwrite($f, str_repeat(" ", 12) . "\"" . $path . "\",\n");
					$this->output->writeln('<info>' . $path . ' has been added.</info>');
				}
			}
		}
		fclose($f);

		copy($tmp_file, $this->phinx_config) or exit("failed to copy $tmp_file");
		unlink($tmp_file);
	}
}
