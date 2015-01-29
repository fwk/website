<?php
namespace App;

require_once __DIR__ .'/../vendor/autoload.php';

use Fwk\Core\Components\Descriptor\Descriptor;
use Fwk\Core\Plugins\RequestMatcherPlugin;
use Fwk\Core\Plugins\ResultTypePlugin;
use Fwk\Core\Plugins\UrlRewriterPlugin;
use Fwk\Core\Plugins\ViewHelperPlugin;
use Nitronet\Fwk\Assetic\AsseticPlugin;
use Nitronet\Fwk\Twig\TwigPlugin;
use Nitronet\Fwk\Comments\CommentsPlugin;
use Symfony\Component\HttpFoundation\Response;
use Nitronet\Fwk\CMF\CmfPlugin;
use Nitronet\Fwk\CMF\Providers\Pages\TwigFilesystemProvider;

$desc = new Descriptor(__DIR__ .'/../app/fwk.xml');
$app = $desc->execute('FwkIO');

$services = $app->getServices();

$app->plugin(new RequestMatcherPlugin())
    ->plugin(new UrlRewriterPlugin())
    ->plugin(new ResultTypePlugin())
    ->plugin(new ViewHelperPlugin());

$app->plugin(new AsseticPlugin(array(
        'directory' => $services->getProperty('assetic.assets.directory'),
        'debug' => $services->getProperty('assetic.debug', true),
        'action' => $services->getProperty('assetic.action.name'),
        'cache' => (bool)$services->getProperty('assetic.use.cache', true),
        'cacheDir' => $services->getProperty('assetic.cache.directory'),
        'cacheStrategy' => $services->getProperty('assetic.cache.strategy'),
        'helperName' => 'asset'
    ),
    array(
        'bower' => __DIR__ .'/../app/site/assets'
    )));

$app->plugin(new TwigPlugin(array(
    'directory' => $services->getProperty('twig.templates.dir'),
    'debug' => true,
    'twig' => array(
        'debug' => $services->getProperty('twig.debug', false),
        'cache' => $services->getProperty('twig.cache.dir', null)
    )
)));

$app->plugin(new CommentsPlugin(array(
    'db' => $services->getProperty('comments.services.database', 'db'),
    'sessionService'    => $services->getProperty('comments.services.session', 'session'),
    'rendererService'   => $services->getProperty('comments.services.renderer', 'formRenderer'),
    'threadsTable'  => $services->getProperty('comments.tables.threads', 'comments_threads'),
    'threadEntity'  => $services->getProperty('comments.entities.thread', 'Nitronet\Fwk\Comments\Model\Thread'),
    'commentsTable' => $services->getProperty('comments.tables.comments', 'comments'),
    'commentEntity' => $services->getProperty('comments.entities.comment', 'Nitronet\Fwk\Comments\Model\Comment'),
    'commentForm'   => $services->getProperty('comments.form', 'Nitronet\Fwk\Comments\Forms\AnonymousCommentForm'),
    'autoThread'    => $services->getProperty('comments.auto.thread', false),
    'autoApprove'   => $services->getProperty('comments.auto.approve', true),
    'dateFormat'    => $services->getProperty('comments.date.format', 'Y-m-d H:i:s'),
    'serviceName'   => $services->getProperty('comments.service', 'comments')
)));

$app->plugin(new CmfPlugin(array(
    'config'    => __DIR__ .'/../app/site/site.yml',
    'consoleService' => 'consoleApp'
),
array(
    new TwigFilesystemProvider(__DIR__ .'/../app/site', 'twig')
),
array(
    new \Nitronet\Fwk\CMF\Providers\Routes\ConfigRoutesProvider()
)));

$response = $app->run();
if ($response instanceof Response) {
    $response->send();
}
