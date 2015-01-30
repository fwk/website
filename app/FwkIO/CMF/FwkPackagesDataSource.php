<?php
namespace FwkIO\CMF;

use Fwk\Cache\Manager;
use Nitronet\Fwk\CMF\DataSource;
use Fwk\Xml\Map;
use Fwk\Xml\Path;
use Fwk\Xml\XmlFile;

class FwkPackagesDataSource implements DataSource
{
    protected $xmlFile;
    protected $packages;
    protected $cache;
    
    public function __construct($xmlFile, Manager $cache = null)
    {
        $this->xmlFile = $xmlFile;
        $this->cache = $cache;
    }
    
    public function one($name)
    {
        $this->load();
        
        if (!isset($this->packages[ucfirst(strtolower($name))])) {
            throw new \RuntimeException('Package not found');
        }
        
        return $this->packages[ucfirst(strtolower($name))];
    }
    
    public function packages()
    {
        $this->load();
        
        return $this->packages;
    }

    public function versions($package)
    {
        $one = $this->one($package);

        $versions = $one['versions'];
        if (strpos($versions, ',') !== false) {
            return explode(',', $versions);
        }

        return array($versions);
    }
    
    protected function load()
    {
        if (isset($this->packages)) {
            return;
        }

        $fetch = function() {
            $file = new XmlFile($this->xmlFile);
            $res = self::xmlMapFactory()->execute($file);

            return $res['packages'];
        };

        if ($this->cache instanceof Manager) {
            $this->packages = $this->cache->get('fwk:packages', '1day', $fetch)->getContents();
        } else {
            $this->packages = $fetch();
        }
    }

    public function getRandomGist()
    {
        $gists = array();
        $pkgs = $this->packages();

        foreach ($pkgs as $pkgId => $pkg) {
            foreach ($pkg['gists'] as $gist) {
                $gists[] = array(
                    'package' => $pkg['name'],
                    'pkgId' => $pkgId,
                    'title' => $gist['title'],
                    'doc'   => $gist['doc'],
                    'embed' => $gist['embed']
                );
            }
        }

        return $gists[rand(0, count($gists)-1)];
    }

    /**
     * 
     * @return Map
     */
    protected static function xmlMapFactory()
    {
        $map = new Map();
        $map->add(
            Path::factory('/packages/package', 'packages', array())
                ->loop(true, '@id')
                ->addChildren(Path::factory('name', 'name'))
                ->addChildren(Path::factory('description', 'description'))
                ->addChildren(Path::factory('repository', 'repository'))
                ->addChildren(Path::factory('intro', 'intro'))
                ->addChildren(Path::factory('docs', 'docs'))
                ->addChildren(Path::factory('icon', 'icon'))
                ->addChildren(Path::factory('versions', 'versions'))
                ->addChildren(
                    Path::factory('gists/gist', 'gists', array())
                    ->loop(true)
                    ->addChildren(Path::factory('title', 'title'))
                    ->addChildren(Path::factory('embed', 'embed', false))
                    ->addChildren(Path::factory('doc', 'doc', false))
                )
                ->addChildren(Path::factory('url', 'url', null))
        );
        
        return $map;
    }
}