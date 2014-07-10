<?php

namespace Jma\BackupBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Export\ClassMetadataExporter;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Class SqlExportCommand
 *
 * Lets make a backup of the database
 *
 * @package Jma\BackupBundle\Command
 * @author Maarek Joseph <josephmaarek@gmail.com>
 */
class BackupSqlCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName("backup:sql")
            ->addOption('bin', 'b', InputOption::VALUE_OPTIONAL, 'The path to binary mysqldump', 'mysqldump')
            ->setDescription("Create a backup file of the database in the backup directory")
            ->setHelp("The path to binary mysqldump on Mac OSX can be \"/Applications/MAMP/Library/bin/mysqldump\"");
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException If the driver is not pdo_mysql
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = $this->getContainer()->get('doctrine')->getConnection();
        /** @var $connection Connection */

        $driver = $connection->getDriver()->getName();
        $bin = $input->getOption('bin');
        $database = $connection->getDatabase();
        $host = $connection->getHost();
        $port = $connection->getPort();
        $pass = $connection->getPassword();
        $username = $connection->getUsername();

        if ($driver != "pdo_mysql") {
            throw new \InvalidArgumentException('Le driver doit être pdo_mysql.');
        }

        $fs = new Filesystem();
        $dir = $this->getContainer()->getParameter("jma_backup.dir");
        if (false === $fs->exists($dir)) {
            $fs->mkdir($dir);
        }

        $filename = strftime("%Y%m%d-%H%M%S") . ".sql";
        $out = $dir . DIRECTORY_SEPARATOR . $filename;

        $verboseOpt = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE ? "-v" : "";
        $command = "$bin -h $host -P $port -u $username -p$pass $database $verboseOpt > $out";
        $output->writeln($command);
        $process = new Process($command);
        $process->run(function ($type, $buffer) use ($output, $out) {
            if ('err' === $type) {
                $output->write(sprintf('<error>%s</error>', $buffer));
            } else {
                $output->write(sprintf('<info>%s</info>', $buffer));
            }
        });

        if ($process->isSuccessful() && $output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
            $output->writeln("<info>" . file_get_contents($out) . "</info>");
        }
    }
} 