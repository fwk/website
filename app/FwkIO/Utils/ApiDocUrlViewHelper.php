<?php
namespace FwkIO\Utils;

use Fwk\Core\Components\UrlRewriter\UrlViewHelper;
use Fwk\Core\Components\ViewHelper\ViewHelper;
use Fwk\Core\Components\ViewHelper\ViewHelperService;
use Fwk\Xml\Exceptions\FileNotFound;
use FwkIO\CMF\FwkApiDocDataSource;

class ApiDocUrlViewHelper extends UrlViewHelper
{
    protected $apiDocDatasource;

    private static $cache = array();

    public function __construct($requestMatcherServiceName, $rewriterServiceName, $apiDocDatasource)
    {
        $this->apiDocDatasource = $apiDocDatasource;

        parent::__construct($requestMatcherServiceName, $rewriterServiceName);
    }

    /**
     * "PageView",
     *  {
     *      page: "package/apidoc-class",
     *      package: package,
     *      type: type,
     *      version: version,
     *      className: className|trim('\\')|replace({'\\': '/'})
     *  },
     *  true
     *
     * -->
     *
     * package
     * type
     * version
     * className
     *
     * @param array $arguments
     * @return string
     */
    public function execute(array $arguments)
    {
        $package    = $origin = (isset($arguments[0]) ? $arguments[0] : null);
        $type       = (isset($arguments[1]) ? strtolower($arguments[1]) : 'class');
        $version    = (isset($arguments[2]) ? $arguments[2] : 'master');
        $className  = (isset($arguments[3]) ? $arguments[3] : null);

        $fwkApi     = $this->getFwkApiDocDatasource();

        if (null === $className) {
            return null;
        }

        if (isset(static::$cache[$package . $type . $version . $className])) {
            return static::$cache[$package . $type . $version . $className];
        }

        // normalize className
        $className = '\\' . ltrim(str_replace('/', '\\', $className), '\\');

        $packages   = array('core', 'db', 'di', 'xml', 'form', 'events', 'cache');
        foreach ($packages as $pkg) {
            $test       = '\\Fwk\\'. ucfirst(strtolower($pkg)) .'\\';
            if (strpos($className, $test, 0) !== false) {
                $package = $pkg;
            }
        }

        try {
            // find
            switch($type)
            {
                case 'class':
                    $index = $fwkApi->classes($package, $version);
                    $index2 = array();
                    break;

                case 'interface':
                    $index = $fwkApi->interfaces($package, $version);
                    $index2 = array();
                    break;

                case 'type':
                    $index =  $fwkApi->classes($package, $version);
                    $index2 = $fwkApi->interfaces($package, $version);
                    break;

                default:
                    throw new \InvalidArgumentException('Unknown type: '. $type);
            }
        } catch (FileNotFound $exp) {
            // handle different versions
            $index = array();
            $index2 = array();
        }

        if ($this->isPhpInternal($className)) {
            $str = '<a href="%s" class="%s" title="%s %s">%s</a>';

            $result = sprintf($str,
                "http://php.net/". strtolower(ltrim($className, '\\')),
                strtolower($package) . " internal",
                ucfirst($type),
                htmlentities(ltrim($className, '\\')),
                htmlentities(ltrim($className, '\\'))
            );

            static::$cache[$package . $type . $version . $className] = $result;

            return $result;
        }

        if (!isset($index[$className]) && !isset($index2[$className])) {
            $result = '<i>'. ltrim($className, '\\') .'</i>';

            static::$cache[$package . $type . $version . $className] = $result;

            return $result;
        }

        $str = '<a href="%s" class="%s" title="%s %s">%s</a>';

        $classNamePrinted = ltrim($className, '\\');

        if ($package == $origin && $type == 'type') {
            $classNamePrinted = str_replace('\\Fwk\\'. ucfirst(strtolower($package)) .'\\', '', $className);
        }

        $cacheKey = $package . $type . $version . $className;
        $type = ($type === 'type' ? (isset($index[$className]) ? 'class' : 'interface') : $type);

        $result = sprintf($str,
            parent::execute(array(
                "PageView",
                array(
                    'page' => 'package/apidoc-class',
                    'package' => $package,
                    'type' => ($type == 'type' ? (isset($index[$className]) ? 'class' : 'interface') : $type),
                    'version' => $version,
                    'className' => ltrim(str_replace('\\', '/', $className), '/')
                ),
                true
            )),
            strtolower($package),
            ucfirst($type),
            htmlentities(ltrim($className, '\\')),
            htmlentities($classNamePrinted)
        );

        static::$cache[$cacheKey] = $result;

        return $result;
    }

    /**
     * @return FwkApiDocDataSource
     */
    public function getFwkApiDocDatasource()
    {
        return $this->getViewHelperService()->getApplication()->getServices()->get($this->apiDocDatasource);
    }

    /**
     * @param $className
     * @return bool
     */
    public function isPhpInternal($className)
    {
        if (!class_exists($className, false) && !interface_exists($className)) {
            return false;
        }

        $ref = new \ReflectionClass($className);
        if ($ref->isInternal()) {
            return true;
        }

        return false;
    }
}