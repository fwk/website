<?php
namespace FwkIO\CMF;

use Fwk\Core\Components\ViewHelper\ViewHelperService;

class FwkDocumentationDataSource extends FwkPackagesDataSource
{
    const INTRO_FILE = 'package.html';

    protected $buildDir;

    public function __construct($fwkBuildDir)
    {
        $this->buildDir = $fwkBuildDir;
    }

    public function doc($page, $package, $version = "master")
    {
        $version = (empty($version) ? 'master' : trim($version));

        $docFile = $this->buildDir
            . DIRECTORY_SEPARATOR
            . ucfirst(strtolower($package))
            . DIRECTORY_SEPARATOR
            . 'build'
            . DIRECTORY_SEPARATOR
            . $version
            . DIRECTORY_SEPARATOR
            . 'docs'
            . DIRECTORY_SEPARATOR
            . $page .'.json';

        if (!is_file($docFile) || !is_readable($docFile)) {
            return false;
        }

        return json_decode(file_get_contents($docFile));
    }

    public function summary($package, $version = "master")
    {
        return $this->doc('SUMMARY', $package, $version);
    }

    public function intro($package, $version = "master")
    {
        $docFile = $this->buildDir
            . DIRECTORY_SEPARATOR
            . ucfirst(strtolower($package))
            . DIRECTORY_SEPARATOR
            . 'build'
            . DIRECTORY_SEPARATOR
            . $version
            . DIRECTORY_SEPARATOR
            . 'docs'
            . DIRECTORY_SEPARATOR
            . self::INTRO_FILE;

        if (!is_file($docFile)) {
            return "NO PACKAGE INTRO :(";
        }

        return file_get_contents($docFile);
    }

    public function restoreDocLinks($contents, $package, $version, ViewHelperService $viewHelper)
    {
        if (preg_match_all('#href="DOCKLINK:(.[^\"]*)"#', $contents, $matches)) {
            $finds = array();
            $repl = array();
            foreach ($matches[0] as $idx => $value) {
                $finds[] = $value;
                $repl[] = 'href="'. $viewHelper->url('PageView', array(
                    'page'      => 'package/doc',
                    'package'   => $package,
                    'version'   => $version,
                    'docPage'   => $matches[1][$idx]
                )) .'"';
            }

            $contents = str_replace($finds, $repl, $contents);
        }

        return $contents;
    }
}