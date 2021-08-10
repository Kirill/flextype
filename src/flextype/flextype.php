<?php

declare(strict_types=1);

/**
 * Flextype (https://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

namespace Flextype;

use Atomastic\Csrf\Csrf;
use Atomastic\Session\Session;
use Cocur\Slugify\Slugify;
use DateTimeZone;
use RuntimeException;
use Flextype\Content\Content;
use Flextype\Media\Media;
use Flextype\Tokens\Tokens;
use Flextype\Handlers\HttpErrorHandler;
use Flextype\Handlers\ShutdownHandler;
use Flextype\Parsers\Parsers;
use Flextype\Serializers\Serializers;
use Intervention\Image\ImageManager;
use League\Event\Emitter;
use League\Flysystem\Local\LocalFilesystemAdapter as Local;
use League\Flysystem\Filesystem as Flysystem;
use League\Glide\Api\Api;
use League\Glide\Manipulators\Background;
use League\Glide\Manipulators\Blur;
use League\Glide\Manipulators\Border;
use League\Glide\Manipulators\Brightness;
use League\Glide\Manipulators\Contrast;
use League\Glide\Manipulators\Crop;
use League\Glide\Manipulators\Encode;
use League\Glide\Manipulators\Filter;
use League\Glide\Manipulators\Gamma;
use League\Glide\Manipulators\Orientation;
use League\Glide\Manipulators\Pixelate;
use League\Glide\Manipulators\Sharpen;
use League\Glide\Manipulators\Size;
use League\Glide\Manipulators\Watermark;
use League\Glide\Responses\PsrResponseFactory;
use League\Glide\ServerFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phpfastcache\Drivers\Apcu\Config;
use Phpfastcache\Helper\Psr16Adapter as Cache;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Middleware\ContentLengthMiddleware;
use Slim\Middleware\OutputBufferingMiddleware;
use Slim\Middleware\RoutingMiddleware;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Stream;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

use function app;
use function array_replace_recursive;
use function container;
use function count;
use function date;
use function date_default_timezone_set;
use function dd;
use function dump;
use function emitter;
use function content;
use function extension_loaded;
use function file_exists;
use function filemtime;
use function filesystem;
use function flextype;
use function function_exists;
use function implode;
use function in_array;
use function mb_internal_encoding;
use function mb_language;
use function mb_regex_encoding;
use function md5;
use function parsers;
use function register_shutdown_function;
use function registry;
use function session;
use function strings;
use function sys_get_temp_dir;
use function trim;
use function var_export;

// Init Flextype Instance
// Creates $app Application and $container Container objects
flextype();

// Add Registry Service
container()->set('registry', registry());

// Init Flextype config (manifest and settings)
$flextypeManifestFilePath        = ROOT_DIR . '/src/flextype/flextype.yaml';
$defaultFlextypeSettingsFilePath = ROOT_DIR . '/src/flextype/settings.yaml';
$customFlextypeSettingsFilePath  = PATH['project'] . '/config/flextype/settings.yaml';
$preflightFlextypePath           = PATH['tmp'] . '/config/flextype/';
$customFlextypeSettingsPath      = PATH['project'] . '/config/flextype/';

! filesystem()->directory($preflightFlextypePath)->exists() and filesystem()->directory($preflightFlextypePath)->create(0755, true);
! filesystem()->directory($customFlextypeSettingsPath)->exists() and filesystem()->directory($customFlextypeSettingsPath)->create(0755, true);

$f1 = file_exists($flextypeManifestFilePath) ? filemtime($flextypeManifestFilePath) : '';
$f2 = file_exists($defaultFlextypeSettingsFilePath) ? filemtime($defaultFlextypeSettingsFilePath) : '';
$f3 = file_exists($customFlextypeSettingsFilePath) ? filemtime($customFlextypeSettingsFilePath) : '';

// Create Unique Cache ID
$cacheID = md5($flextypeManifestFilePath . $defaultFlextypeSettingsFilePath . $customFlextypeSettingsFilePath . $f1 . $f2 . $f3);

if (filesystem()->file($preflightFlextypePath . '/' . $cacheID . '.php')->exists()) {
    $flextypeData = include $preflightFlextypePath . '/' . $cacheID . '.php';
} else {
    // Set settings if Flextype Default settings config files exist
    if (! filesystem()->file($defaultFlextypeSettingsFilePath)->exists()) {
        throw new RuntimeException('Flextype Default settings config file does not exist.');
    }

    if (($defaultFlextypeSettingsContent = filesystem()->file($defaultFlextypeSettingsFilePath)->get()) === false) {
        throw new RuntimeException('Load file: ' . $defaultFlextypeSettingsFilePath . ' - failed!');
    } else {
        if (trim($defaultFlextypeSettingsContent) === '') {
            $defaultFlextypeSettings['settings'] = [];
        } else {
            $defaultFlextypeSettings['settings'] = SymfonyYaml::parse($defaultFlextypeSettingsContent);
        }
    }

    // Create flextype custom settings file
    ! filesystem()->file($customFlextypeSettingsFilePath)->exists() and filesystem()->file($customFlextypeSettingsFilePath)->put($defaultFlextypeSettingsContent);

    if (($customFlextypeSettingsContent = filesystem()->file($customFlextypeSettingsFilePath)->get()) === false) {
        throw new RuntimeException('Load file: ' . $customFlextypeSettingsFilePath . ' - failed!');
    } else {
        if (trim($customFlextypeSettingsContent) === '') {
            $customFlextypeSettings['settings'] = [];
        } else {
            $customFlextypeSettings['settings'] = SymfonyYaml::parse($customFlextypeSettingsContent);
        }
    }

    if (($flextypeManifestContent = filesystem()->file($flextypeManifestFilePath)->get()) === false) {
        throw new RuntimeException('Load file: ' . $flextypeManifestFilePath . ' - failed!');
    } else {
        if (trim($flextypeManifestContent) === '') {
            $flextypeManifest['manifest'] = [];
        } else {
            $flextypeManifest['manifest'] = SymfonyYaml::parse($flextypeManifestContent);
        }
    }

    // Merge flextype default settings with custom project settings.
    $flextypeData = array_replace_recursive($defaultFlextypeSettings, $customFlextypeSettings, $flextypeManifest);

    filesystem()->file($preflightFlextypePath . $cacheID . '.php')->put("<?php\n return " . var_export($flextypeData, true) . ";\n");
}

// Store flextype merged data in the flextype registry.
registry()->set('flextype', $flextypeData);

// Set Flextype base path
app()->setBasePath(registry()->get('flextype.settings.url'));

// Add Routing Middleware
app()->add(new RoutingMiddleware(app()->getRouteResolver(), app()->getRouteCollector()->getRouteParser()));

// Add Content Length Middleware
if (registry()->get('flextype.settings.add_content_length_header')) {
    app()->add(new ContentLengthMiddleware());
}

// Add Body Parsing Middleware
app()->addBodyParsingMiddleware();

// Add Output Buffering Middleware
if (registry()->get('flextype.settings.output_buffering')) {
    switch (registry()->get('flextype.settings.output_buffering')) {
        case 'prepend':
            app()->add(new OutputBufferingMiddleware(new StreamFactory(), OutputBufferingMiddleware::PREPEND));
            break;

        case 'append':
        default:
            app()->add(new OutputBufferingMiddleware(new StreamFactory(), OutputBufferingMiddleware::APPEND));
            break;
    }
}

// Add Router Cache
if (registry()->get('flextype.settings.cache.routes')) {
    app()->getRouteCollector()->setCacheFile(PATH['tmp'] . '/routes/routes.php');
}

$callableResolver     = app()->getCallableResolver();
$responseFactory      = app()->getResponseFactory();
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request              = $serverRequestCreator->createServerRequestFromGlobals();

$errorHandler    = new HttpErrorHandler($callableResolver, $responseFactory);
$shutdownHandler = new ShutdownHandler($request, $errorHandler, registry()->get('flextype.settings.errors.display'));
register_shutdown_function($shutdownHandler);

// Add Session Service
container()->set('session', new Session());

// Add Logger Service
container()->set('logger', (new Logger('flextype'))->pushHandler(new StreamHandler(PATH['tmp'] . '/logs/' . date('Y-m-d') . '.log')));

// Add Emitter Service
container()->set('emitter', new Emitter());

// Add Slugify Service
container()->set('slugify', new Slugify([
    'separator' => registry()->get('flextype.settings.slugify.separator'),
    'lowercase' => registry()->get('flextype.settings.slugify.lowercase'),
    'trim' => registry()->get('flextype.settings.slugify.trim'),
    'regexp' => registry()->get('flextype.settings.slugify.regexp'),
    'lowercase_after_regexp' => registry()->get('flextype.settings.slugify.lowercase_after_regexp'),
    'strip_tags' => registry()->get('flextype.settings.slugify.strip_tags'),
]));

// Add Cache Service
container()->set('cache', function () {
    $driverName = registry()->get('flextype.settings.cache.driver');

    $config = [];

    function getDriverConfig(string $driverName): array
    {
        $config = [];

        foreach (registry()->get('flextype.settings.cache.drivers.' . $driverName) as $key => $value) {
            if ($key === 'path' && in_array($driverName, ['files', 'sqlite', 'leveldb', 'phparray'])) {
                $config['path'] = ! empty($value) ? PATH['tmp'] . '/' . $value : sys_get_temp_dir();
            } else {
                $config[strings($key)->camel()->toString()] = $value;
            }
        }

        return $config;
    }

    if (! $driverName || $driverName === 'auto') {
        if (extension_loaded('apcu')) {
            $driverName = 'apcu';
        } elseif (extension_loaded('wincache')) {
            $driverName = 'wincache';
        } else {
            $driverName = 'phparray';
        }
    }

    if (registry()->get('flextype.settings.cache.enabled') === false) {
        $driverName = 'devnull';
    }

    switch ($driverName) {
        case 'apcu':
            $config = new Config(getDriverConfig($driverName));
            break;
        case 'cassandra':
            $config = new \Phpfastcache\Drivers\Cassandra\Config(getDriverConfig($driverName));
            break;
        case 'cookie':
            $config = new \Phpfastcache\Drivers\Cookie\Config(getDriverConfig($driverName));
            break;
        case 'couchbase':
            $config = new \Phpfastcache\Drivers\Couchbase\Config(getDriverConfig($driverName));
            break;
        case 'couchdb':
            $config = new \Phpfastcache\Drivers\Couchdb\Config(getDriverConfig($driverName));
            break;
        case 'devfalse':
            $config = new \Phpfastcache\Drivers\Devfalse\Config(getDriverConfig($driverName));
            break;
        case 'devnull':
            $config = new \Phpfastcache\Drivers\Devnull\Config(getDriverConfig($driverName));
            break;
        case 'devtrue':
            $config = new \Phpfastcache\Drivers\Devtrue\Config(getDriverConfig($driverName));
            break;
        case 'files':
            $config = new \Phpfastcache\Drivers\Files\Config(getDriverConfig($driverName));
            break;
        case 'phparray':
            $config = new \Phpfastcache\Drivers\Phparray\Config(getDriverConfig($driverName));
            break;
        case 'leveldb':
            $config = new \Phpfastcache\Drivers\Leveldb\Config(getDriverConfig($driverName));
            break;
        case 'memcache':
            $config = new \Phpfastcache\Drivers\Memcache\Config(getDriverConfig($driverName));
            break;
        case 'memcached':
            $config = new \Phpfastcache\Drivers\Memcached\Config(getDriverConfig($driverName));
            break;
        case 'memstatic':
            $config = new \Phpfastcache\Drivers\Memstatic\Config(getDriverConfig($driverName));
            break;
        case 'mongodb':
            $config = new \Phpfastcache\Drivers\Mongodb\Config(getDriverConfig($driverName));
            break;
        case 'predis':
            $config = new \Phpfastcache\Drivers\Predis\Config(getDriverConfig($driverName));
            break;
        case 'redis':
            $config = new \Phpfastcache\Drivers\Redis\Config(getDriverConfig($driverName));
            break;
        case 'riak':
            $config = new \Phpfastcache\Drivers\Riak\Config(getDriverConfig($driverName));
            break;
        case 'sqlite':
            $config = new \Phpfastcache\Drivers\Sqlite\Config(getDriverConfig($driverName));
            break;
        case 'ssdb':
            $config = new \Phpfastcache\Drivers\Ssdb\Config(getDriverConfig($driverName));
            break;
        case 'wincache':
            $config = new \Phpfastcache\Drivers\Wincache\Config(getDriverConfig($driverName));
            break;
        case 'zenddisk':
            $config = new \Phpfastcache\Drivers\Zenddisk\Config(getDriverConfig($driverName));
            break;
        case 'zendshm':
            $config = new \Phpfastcache\Drivers\Zendshm\Config(getDriverConfig($driverName));
            break;
        default:
            // code...
            break;
    }

    return new Cache($driverName, $config);
});

// Add Parsers Service
container()->set('parsers', new Parsers());

// Init Shortcodes
parsers()->shortcodes()->initShortcodes();

// Add Serializers Service
container()->set('serializers', new Serializers());

// Add Images Service
container()->set('images', function () {
    // Get images settings
    $imagesSettings = ['driver' => registry()->get('flextype.settings.media.image.driver')];

    // Set source filesystem
    $source = new Flysystem(
        new Local(PATH['project'] . '/uploads/')
    );

    // Set cache filesystem
    $cache = new Flysystem(
        new Local(PATH['tmp'] . '/glide')
    );

    // Set watermarks filesystem
    $watermarks = new Flysystem(
        new Local(PATH['project'] . '/watermarks')
    );

    // Set image manager
    $imageManager = new ImageManager($imagesSettings);

    // Set manipulators
    $manipulators = [
        new Orientation(),
        new Crop(),
        new Size(2000 * 2000),
        new Brightness(),
        new Contrast(),
        new Gamma(),
        new Sharpen(),
        new Filter(),
        new Blur(),
        new Pixelate(),
        new Watermark($watermarks),
        new Background(),
        new Border(),
        new Encode(),
    ];

    // Set API
    $api = new Api($imageManager, $manipulators);

    // Setup Glide server
    $server = ServerFactory::create([
        'source' => $source,
        'cache' => $cache,
        'api' => $api,
    ]);

    $server->setResponseFactory(
        new PsrResponseFactory(
            new \Slim\Psr7\Response(),
            function ($stream) {
                return new Stream($stream);
            }
        )
    );

    return $server;
});

// Add Content Service
container()->set('content', new Content(registry()->get('flextype.settings.entries.content')));

// Add Media Service
container()->set('media', new Media(registry()->get('flextype.settings.entries.media')));

// Add Tokens Service
container()->set('tokens', new Tokens(registry()->get('flextype.settings.entries.tokens')));

// Add Plugins Service
container()->set('plugins', new Plugins());

// Set session options before you start the session
// Standard PHP session configuration options
// https://secure.php.net/manual/en/session.configuration.php
session()->setOptions(registry()->get('flextype.settings.session'));

// Start the session
session()->start();

// Add CSRF (cross-site request forgery) protection service to Flextype container
container()->set('csrf', new Csrf('__csrf_token', '', 128));

// Set internal encoding
function_exists('mb_language') and mb_language('uni');
function_exists('mb_regex_encoding') and mb_regex_encoding(registry()->get('flextype.settings.charset'));
function_exists('mb_internal_encoding') and mb_internal_encoding(registry()->get('flextype.settings.charset'));

// Set default timezone
if (in_array(registry()->get('flextype.settings.timezone'), DateTimeZone::listIdentifiers())) {
    date_default_timezone_set(registry()->get('flextype.settings.timezone'));
}

// Init Plugins
plugins()->init();

/**
 * Include API ENDPOINTS
 */
require_once ROOT_DIR . '/src/flextype/routes/endpoints/utils.php';
//include_once ROOT_DIR . '/src/flextype/Endpoints/errors.php';
//include_once ROOT_DIR . '/src/flextype/Endpoints/images.php';
//include_once ROOT_DIR . '/src/flextype/Endpoints/utils.php';

// Enable lazy CORS
//
// CORS (Cross-origin resource sharing) allows JavaScript web apps to make HTTP requests to other domains.
// This is important for third party web apps using Flextype, as without CORS, a JavaScript app hosted on example.com
// couldn't access our APIs because they're hosted on another.com which is a different domain.
if (registry()->get('flextype.settings.cors.enabled')) {
    // Allow preflight requests for all routes.
    app()->options('/{routes:.+}', function (ServerRequestInterface $request, ResponseInterface $response) {
        return $response;
    });

    // Add headers
    app()->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $response = $handler->handle($request);

        // Set variables
        $origin      = registry()->get('flextype.settings.cors.origin');
        $headers     = count(registry()->get('flextype.settings.cors.headers')) ? implode(', ', registry()->get('flextype.settings.cors.headers')) : '';
        $methods     = count(registry()->get('flextype.settings.cors.methods')) ? implode(', ', registry()->get('flextype.settings.cors.methods')) : '';
        $expose      = count(registry()->get('flextype.settings.cors.expose')) ? implode(', ', registry()->get('flextype.settings.cors.expose')) : '';
        $credentials = registry()->get('flextype.settings.cors.credentials') ? 'true' : 'false';

        return $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Headers', $headers)
                ->withHeader('Access-Control-Allow-Methods', $methods)
                ->withHeader('Access-Control-Allow-Expose', $expose)
                ->withHeader('Access-Control-Allow-Credentials', $credentials);
    });
}

// Add Routing Middleware
app()->addRoutingMiddleware();

// Add Error Handling Middleware
app()->addErrorMiddleware(registry()->get('flextype.settings.errors.display'), false, false)->setDefaultErrorHandler($errorHandler);

// Run high priority event: onFlextypeBeforeRun before Flextype Application starts.
emitter()->emit('onFlextypeBeforeRun');

// Run Flextype Application
app()->run();