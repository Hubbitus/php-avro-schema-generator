<?php

declare(strict_types=1);

namespace PhpKafka\PhpAvroSchemaGenerator\Command;

use PhpKafka\PhpAvroSchemaGenerator\Optimizer\FieldOrderOptimizer;
use PhpKafka\PhpAvroSchemaGenerator\Optimizer\FullNameOptimizer;
use PhpKafka\PhpAvroSchemaGenerator\Optimizer\PrimitiveSchemaOptimizer;
use PhpKafka\PhpAvroSchemaGenerator\Registry\SchemaRegistry;
use PhpKafka\PhpAvroSchemaGenerator\Merger\SchemaMerger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SubSchemaMergeCommand extends Command
{
    /** @var string[] */
    protected $optimizerOptionMapping = [
        'optimizeFieldOrder' => FieldOrderOptimizer::class,
        'optimizeFullNames' => FullNameOptimizer::class,
        'optimizePrimitiveSchemas' => PrimitiveSchemaOptimizer::class,
    ];
    protected function configure(): void
    {
        $this
            ->setName('avro:subschema:merge')
            ->setDescription('Merges subschema')
            ->setHelp('Merges all schema template files and creates schema files')
            ->addArgument('templateDirectory', InputArgument::REQUIRED, 'Schema template directory')
            ->addArgument('outputDirectory', InputArgument::REQUIRED, 'Output directory')
            ->addOption('prefixWithNamespace', null, InputOption::VALUE_NONE, 'Prefix output files with namespace')
            ->addOption(
                'useFilenameAsSchemaName',
                null,
                InputOption::VALUE_NONE,
                'Use template filename as schema filename'
            )
            ->addOption(
                'optimizeFullNames',
                null,
                InputOption::VALUE_NONE,
                'Remove namespaces if they are enclosed in the same namespace'
            )
            ->addOption(
                'optimizeFieldOrder',
                null,
                InputOption::VALUE_NONE,
                'Remove namespaces if they are enclosed in the same namespace'
            )
            ->addOption(
                'optimizePrimitiveSchemas',
                null,
                InputOption::VALUE_NONE,
                'Optimize primitive schemas with using just type as a schema'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Merging schema files');

        /** @var string $templateDirectoryArg */
        $templateDirectoryArg = $input->getArgument('templateDirectory');
        /** @var string $outputDirectoryArg */
        $outputDirectoryArg = $input->getArgument('outputDirectory');

        $templateDirectory = $this->getPath($templateDirectoryArg);
        $outputDirectory = $this->getPath($outputDirectoryArg);

        $registry = (new SchemaRegistry())
            ->addSchemaTemplateDirectory($templateDirectory)
            ->load();

        $merger = new SchemaMerger($registry, $outputDirectory);

        foreach ($this->optimizerOptionMapping as $optionName => $optimizerClass) {
            if (true === (bool) $input->getOption($optionName)) {
                $merger->addOptimizer(new $optimizerClass());
            }
        }

        $result = $merger->merge(
            (bool) $input->getOption('prefixWithNamespace'),
            (bool) $input->getOption('useFilenameAsSchemaName')
        );

        // retrieve the argument value using getArgument()
        $output->writeln(sprintf('Merged %d root schema files', $result));

        return 0;
    }

    /**
     * @param string $path
     * @return string
     */
    private function getPath(string $path): string
    {
        $result = realpath($path);

        if (false === $result || false === is_dir($result)) {
            throw new \RuntimeException(sprintf('Directory not found %s', $path));
        }

        return $result;
    }
}
