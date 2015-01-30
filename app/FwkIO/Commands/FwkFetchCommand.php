<?php
namespace FwkIO\Commands;

use FwkIO\CMF\FwkDocumentationDataSource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Fwk\Core\ServicesAware;
use Fwk\Di\Container;
use Symfony\Component\Process\Process;
use Symfony\Component\Finder\Finder;
use Michelf\MarkdownExtra;
use Nitronet\Fwk\CMF\DataSourceFactory;

class FwkFetchCommand extends Command implements ServicesAware
{
    protected $services;

    protected function configure()
    {
        $this->setDescription('Fetches Fwk Package(s) and build documenation');
        $this->setName('fwk:fetch');
        $this->addArgument(
            'packages',
            InputArgument::OPTIONAL,
            'Packages to be fetched, separated by coma. All packages if not specified'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pkgs = $input->getArgument('packages');

        if (!empty($pkgs)) {
            $pkgs = (strpos($pkgs, ',') > 0 ? explode(',', $pkgs) : array($pkgs));
        } else {
            $pkgs = false;
        }

        $cms = $this->getServices()->get('cmf');
        $cfg = $cms->getPageConfig('home');

        $dsf = new DataSourceFactory($this->getServices());
        $dsf->load((isset($cfg['datasources']) ? $cfg['datasources'] : array()));

        $pkgDs  = $dsf->factory('packages');
        $all    = $pkgDs->packages();
        $buildDir = $this->getServices()->getProperty('fwk.build.dir');

        if (!is_dir($buildDir)) {
            if ($output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln('Directory '. $buildDir .' not found. Creating...', true);
            }
            if (!mkdir($buildDir)) {
                throw new \Exception('Unable to make build directory: '. $buildDir);
            }
        }

        $phpDocBin = $this->getServices()->getProperty('phpdoc.bin');
        foreach ($all as $pkgName => $data) {
            if ($pkgs !== false && !in_array($pkgName, $pkgs)) {
                if ($output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('Skipping '. $pkgName .'.', true);
                }
                continue;
            }
            $output->writeln('Building package <info>'. $pkgName .'</info> ...', true);
            $versions = $data['versions'];
            if (strpos($versions, ',') !== false) {
                $versions = explode(',', $versions);
            } else {
                $versions = array($versions);
            }

            $this->fetchOrUpdatePackage($pkgName, $data, $buildDir, $output);
            foreach ($versions as $version) {
                $output->writeln('+ building version <info>'. $version .'</info>', true);
                $this->doCheckout($pkgName, $version, $buildDir, $output);
                $this->buildApiDoc($pkgName, $data, $version, $buildDir, $phpDocBin, $output);
                $this->buildDocumentation($pkgName, $data, $version, $buildDir, $output);
            }
        }
    }

    protected function buildDocumentation($pkg, $data, $version, $buildDir, OutputInterface $output)
    {
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln('    - Building Documentation ...');
        }

        $docsDir = $data['docs'];
        $fullDir = realpath($buildDir . DIRECTORY_SEPARATOR . $pkg . DIRECTORY_SEPARATOR . $docsDir);
        if (false === $fullDir) {
            if ($output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln('    <error>No documentation found in '. $fullDir .'</error>');
            }
            return;
        }
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln('    - documentation found in '. $fullDir .'! Building...');
        }

        $finder = new Finder();
        $finder->files()->in($fullDir)->name('*.md');
        foreach ($finder as $file) {
            if ($output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
                $output->write('        = Processing file: '. $file->getRelativePathname() . ' ... ');
            }
            $finalFile = realpath($buildDir)
                . DIRECTORY_SEPARATOR
                . $pkg
                . DIRECTORY_SEPARATOR
                . 'build'
                . DIRECTORY_SEPARATOR
                . $version
                . DIRECTORY_SEPARATOR
                . 'docs'
                . DIRECTORY_SEPARATOR
                . str_replace('.md', '.json', $file->getRelativePathname());

            $infos = pathinfo($finalFile, PATHINFO_DIRNAME);
            if (!is_dir($infos)) {
                mkdir($infos);
            }

            $html   = MarkdownExtra::defaultTransform($file->getContents());
            $encode = array(
                'content'   => $this->replaceDocLinks($html, $file->getRelativePathname()),
                'title'     => $this->extractPageTitle($html)
            );

            file_put_contents($finalFile, json_encode($encode));

            if ($output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln('OK');
            }
        }

        $introFile = $fullDir . DIRECTORY_SEPARATOR . FwkDocumentationDataSource::INTRO_FILE;
        if (is_file($introFile)) {
            $finalFile = realpath($buildDir)
                . DIRECTORY_SEPARATOR
                . $pkg
                . DIRECTORY_SEPARATOR
                . 'build'
                . DIRECTORY_SEPARATOR
                . $version
                . DIRECTORY_SEPARATOR
                . 'docs'
                . DIRECTORY_SEPARATOR
                . FwkDocumentationDataSource::INTRO_FILE;

            file_put_contents($finalFile, file_get_contents($introFile));
        }
    }

    private function extractPageTitle($html)
    {
        if (preg_match('#<h1>(.[^<]*)</h1>#i', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function replaceDocLinks($html, $pagePath)
    {
        if (preg_match_all('#href="(\.|\.\.)/(.[^\"]*).md"#', $html, $matches)) {
            $finds = array();
            $repl  = array();

            foreach ($matches[0] as $idx => $value) {
                $finds[] = $value;
                $repl[] = 'href="DOCKLINK:'. $matches[2][$idx] .'"';
            }

            $html = str_replace($finds, $repl, $html);
        }

        return $html;
    }

    protected function buildApiDoc($pkg, $data, $version, $buildDir, $phpDocBin, OutputInterface $output)
    {
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln('    - Building API documentation ...');
        }

        $proc = $this->proc(sprintf('%s -d . -t ./build/'. $version .'/apidoc --template="xml" --ignore="*vendor*,*docs*,*Tests*"', $phpDocBin), $buildDir . DIRECTORY_SEPARATOR . $pkg, $output, 200);
        if (!$proc->isSuccessful()) {
            throw new \Exception('Unable to build API documentation: '. $proc->getErrorOutput());
        }
    }

    protected function fetchOrUpdatePackage($pkg, $data, $buildDir, OutputInterface $output)
    {
        if (!is_dir($buildDir . DIRECTORY_SEPARATOR . $pkg)) {
            if ($output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln('+ Package previous installation not found. Cloning repository ...');
            }
            $proc = $this->proc('git clone '. $data['repository'], $buildDir, $output);
            if (!$proc->isSuccessful()) {
                throw new \Exception('Unable to clone package repository: '. $pkg .'/'. $data['repository'] .': '. $proc->getErrorOutput());
            }
        }

        if ($output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln('+ Previous installation found. Updating ...');
        }

        $proc = $this->proc('git fetch --all && git pull --all -ff', $buildDir . DIRECTORY_SEPARATOR . $pkg, $output);
        if (!$proc->isSuccessful()) {
            throw new \Exception('Unable to update package repository: '. $pkg .'/'. $data['repository'] .': '. $proc->getErrorOutput());
        }
    }

    protected function doCheckout($pkg, $version, $buildDir, OutputInterface $output)
    {
        $proc = $this->proc('git checkout '. $version, $buildDir . DIRECTORY_SEPARATOR . $pkg, $output);
        if (!$proc->isSuccessful()) {
            throw new \Exception('Unable to checkout version: '. $pkg .'/'. $version .': '. $proc->getErrorOutput());
        }
    }

    /**
     * @param $cmd
     * @param $cwd
     * @param OutputInterface $output
     * @param int $timeout
     * @return Process
     */
    private function proc($cmd, $cwd, OutputInterface $output, $timeout = 60)
    {
        $proc = new Process($cmd, $cwd);
        if (null !== $timeout) {
            $proc->setTimeout($timeout);
        }
        $proc->run(function ($type, $buffer) use ($output) {
            if ('err' === $type) {
                $output->write('<error>ERROR</error>: '. $buffer);
            } elseif ($output->getVerbosity() === OutputInterface::VERBOSITY_VERY_VERBOSE) {
                $output->write('<info>INFO</info>: '. $buffer);
            }
        });

        return $proc;
    }

    /**
     *
     * @return Container
     */
    public function getServices()
    {
        return $this->services;
    }

    public function setServices(Container $container)
    {
        $this->services = $container;
    }
}
