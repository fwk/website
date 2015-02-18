<?php
namespace FwkIO\CMF;

use Nitronet\Fwk\CMF\DataSource;
use Fwk\Xml\XmlFile;
use Fwk\Xml\Map;
use Fwk\Xml\Path;
use Fwk\Cache\Manager;
use Symfony\Component\HttpFoundation\JsonResponse;

class FwkApiDocDataSource implements DataSource
{
    protected $buildDir;
    
    protected $classes;
    protected $interfaces;
    protected $cache;

    // for service as a controller
    public $package;
    public $version;

    public function __construct($fwkBuildDir, Manager $cache = null)
    {
        $this->buildDir = $fwkBuildDir;
        $this->cache    = $cache;
    }
    
    public function doc($package, $className, $type, $version = "master")
    {
        $version = (empty($version) ? 'master' : trim($version));

        $this->load($package, $version);
        
        $key = '\\' . str_replace('/', '\\', $className);
        $ref = ($type == "class" ? $this->classes : $this->interfaces);
        
        if (!isset($ref[$package . $version][$key])) {
            throw new \InvalidArgumentException('unknown '. $type .': '. $className);
        }
        
        return $ref[$package . $version][$key];
    }
    
    public function classes($package, $version = "master")
    {
        $version = (empty($version) ? 'master' : trim($version));

        $this->load($package, $version);
        
        return $this->classes[$package . $version];
    }
    
    public function interfaces($package, $version = "master")
    {
        $version = (empty($version) ? 'master' : trim($version));

        $this->load($package, $version);
        
        return $this->interfaces[$package . $version];
    }

    public function search($package = null, $version = null)
    {
        if (empty($package)) {
            $package = $this->package;
        }
        if (empty($version)) {
            $version = $this->version;
        }

        $version = (empty($version) ? 'master' : trim($version));

        $this->load($package, $version);

        $final = $this->interfaces[$package . $version] + $this->classes[$package . $version];

        $result = array();
        foreach ($final as $fullname => $infos) {
            $idx = array(
                'name'      => $infos['name'],
                'fullname'  => ltrim($fullname, '\\'),
                'clean'       => substr(ltrim($fullname, '\\'), strlen($package)+5),
                'description' => $infos['docblock']['description'],
                'url'       => str_replace('\\', '/', ltrim($fullname, '\\')),
                'type'      => (isset($this->interfaces[$package . $version][$fullname]) ? 'interface' : 'class')
            );
            $methods = array();
            foreach ($infos['methods'] as $method => $infosMethod) {
                $methods[] = array('name' => $method, 'description' => $infosMethod['docblock']['description']);
            }
            $idx['methods'] = $methods;
            array_push($result, $idx);
        }

        return new JsonResponse($result);
    }

    protected function load($package, $version = "master")
    {
        $version = (empty($version) ? 'master' : trim($version));

        if (isset($this->classes[$package . $version])) {
            return;
        }

        $fetch = function($package, $version) {
            $xmlFile = $this->buildDir
                . DIRECTORY_SEPARATOR
                . ucfirst(strtolower($package))
                . DIRECTORY_SEPARATOR
                . 'build'
                . DIRECTORY_SEPARATOR
                . $version
                . DIRECTORY_SEPARATOR
                . 'apidoc/structure.xml';

            $file = new XmlFile($xmlFile);
            $res = self::xmlMapFactory()->execute($file);

            ksort($res['classes'], SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($res['classes'] as &$data) {
                ksort($data['methods'], SORT_NATURAL);
            }

            ksort($res['interfaces'], SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($res['interfaces'] as &$data) {
                ksort($data['methods'], SORT_NATURAL);
            }

            return array('classes' => $res['classes'], 'interfaces' => $res['interfaces']);
        };

        if ($this->cache instanceof Manager) {
            $res = $this->cache->get('fwk:api:'. $package . ':'. $version, '1day', function() use ($package, $version, $fetch) {
                return $fetch($package, $version);
            })->getContents();
        } else {
            $res = $fetch($package, $version);
        }

        $this->interfaces[$package . $version] = $res['interfaces'];
        $this->classes[$package . $version] = $res['classes'];
    }
    
    /**
     * 
     * @return Map
     */
    protected static function xmlMapFactory()
    {
        $map = new Map();
        $map->add(
            Path::factory('/project/file/class', 'classes', array())
                ->loop(true, 'full_name')
                ->attribute('abstract')
                ->attribute('final')
                ->addChildren(Path::factory('name', 'name'))
                ->addChildren(Path::factory('extends', 'extends', null))
                ->addChildren(Path::factory('implements', 'implements', array())->loop(true))
                ->addChildren(self::docBlockPathFactory())
                ->addChildren(
                    Path::factory('constant', 'constants', array())
                    ->loop(true, 'name')
                    ->addChildren(Path::factory('full_name', 'full_name'))
                    ->addChildren(Path::factory('value', 'value'))
                    ->addChildren(self::docBlockPathFactory())
                )
                ->addChildren(
                    Path::factory('property', 'properties', array())
                    ->loop(true, 'name')
                    ->attribute('visibility')
                    ->attribute('static')
                    ->addChildren(Path::factory('full_name', 'full_name'))
                    ->addChildren(Path::factory('default', 'default'))
                    ->addChildren(self::docBlockPathFactory())
                )
                ->addChildren(
                    Path::factory('method', 'methods', array())
                    ->loop(true, 'name')
                    ->attribute('final')
                    ->attribute('abstract')
                    ->attribute('visibility')
                    ->attribute('static')
                    ->addChildren(
                        Path::factory('argument', 'arguments', array())
                        ->loop(true)
                        ->addChildren(Path::factory('name', 'name'))
                        ->addChildren(Path::factory('default', 'default'))
                        ->addChildren(Path::factory('type', 'type'))
                    )
                    ->addChildren(self::docBlockPathFactory())
                )
        );
        
        $map->add(
            Path::factory('/project/file/interface', 'interfaces', array())
                ->loop(true, 'full_name')
                ->attribute('final')
                ->addChildren(Path::factory('name', 'name'))
                ->addChildren(Path::factory('extends', 'extends', null))
                ->addChildren(self::docBlockPathFactory())
                ->addChildren(
                    Path::factory('constant', 'constants', array())
                    ->loop(true, 'name')
                    ->addChildren(Path::factory('full_name', 'full_name'))
                    ->addChildren(Path::factory('value', 'value'))
                    ->addChildren(self::docBlockPathFactory())
                )
                ->addChildren(
                    Path::factory('method', 'methods', array())
                    ->loop(true, 'name')
                    ->attribute('final')
                    ->attribute('abstract')
                    ->attribute('visibility')
                    ->attribute('static')
                    ->addChildren(
                        Path::factory('argument', 'arguments', array())
                        ->loop(true)
                        ->addChildren(Path::factory('name', 'name'))
                        ->addChildren(Path::factory('default', 'default'))
                        ->addChildren(Path::factory('type', 'type'))
                    )
                    ->addChildren(self::docBlockPathFactory())
                )
        );
        
        return $map;
    }
    
    private static function docBlockPathFactory()
    {
        return Path::factory('docblock', 'docblock')
            ->addChildren(
                Path::factory('description', 'description', null)
            )
            ->addChildren(
                Path::factory('long-description', 'longDescription', null)
            )
            // handle @param tag
            ->addChildren(
                Path::factory('tag[@name="param"]', 'params', array())
                    ->loop(true, '@variable')
                    ->attribute('description')
                    ->attribute('link')
                    ->attribute('type')
            )
            // handle @throws tag
            ->addChildren(
                Path::factory('tag[@name="throws"]', 'throws', array())
                    ->loop(true)
                    ->attribute('description')
                    ->attribute('link')
                    ->attribute('type')
            )
            // handle all tags
            ->addChildren(
                Path::factory('tag', 'tags', array())
                ->loop(true, '@name')
                ->attribute('description')
                ->attribute('link')
                ->attribute('type')
            )
        ;
    }
}