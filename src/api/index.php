<?php
declare(strict_types=1);

require '../Include/Config.php';

// This file is generated by Composer
require_once dirname(__FILE__).'/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\HttpCache\CacheProvider;
use Slim\HttpCache\Cache;
use Tuupola\Middleware\JwtAuthentication;
use DI\Container;

use EcclesiaCRM\Slim\Middleware\VersionMiddleware;
use EcclesiaCRM\TokenQuery;

// Instantiate the app
$container = new Container();

$settings = require __DIR__.'/../Include/slim/settings.php';
$settings($container);

AppFactory::setContainer($container);

$app = AppFactory::create();

// Register the http cache middleware.
$app->add( new Cache('private', 0) );

// Create the cache provider.
$cacheProvider = new CacheProvider();

\EcclesiaCRM\Utils\LoggerUtils::getAppLogger()->info("cache enable : ".is_null($cacheProvider)?'false':'true');

$app->setBasePath("/api");

$app->add( new VersionMiddleware() );

$tokenJWT = null;

if ( !is_null (TokenQuery::Create()->findOneByType("secret")) ) {
    $tokenJWT = TokenQuery::Create()->findOneByType("secret")->getToken();

    $app->add(new JwtAuthentication([
        "secret" => $tokenJWT,
        "path" => "/api",
        "ignore" => ["/api/"],
        "algorithm" => ["HS256"],
        "error" => function ($response, $arguments) {
            $data["status"] = "error";
            $data["message"] = $arguments["message"];
            return $response
                ->withHeader("Content-Type", "application/json")
                ->write( json_encode($data, 'JSON_UNESCAPED_SLASHES' | 'JSON_PRETTY_PRINT') );
        }
    ]));
}

// Set up
require __DIR__.'/dependencies.php';
require __DIR__.'/../Include/slim/error-handler.php';

// calendar and events routes
require __DIR__.'/routes/calendar/calendar-calendarV2.php';
require __DIR__.'/routes/calendar/calendar-eventsV2.php';

// file manager documents routes
require __DIR__.'/routes/documents/documents-document.php';
require __DIR__.'/routes/documents/documents-ckeditor.php';
require __DIR__.'/routes/documents/documents-filemanager.php';
require __DIR__.'/routes/documents/documents-sharedocument.php';

// finance routes
require __DIR__.'/routes/finance/finance-deposits.php';
require __DIR__.'/routes/finance/finance-donationfunds.php';
require __DIR__.'/routes/finance/finance-payments.php';
require __DIR__.'/routes/finance/finance-pledges.php';

// people families and persons routes
require __DIR__.'/routes/people/people.php';
require __DIR__.'/routes/people/people-attendees.php';
require __DIR__.'/routes/people/people-families.php'; // Ok
require __DIR__.'/routes/people/people-groups.php';
require __DIR__.'/routes/people/people-persons.php';

// public routes
require __DIR__.'/routes/public/public-data.php';
require __DIR__.'/routes/public/public-register.php';

// system sidebar routes
require __DIR__.'/routes/sidebar/sidebar-mapicons.php'; // OK
require __DIR__.'/routes/sidebar/sidebar-menulinks.php'; // OK
require __DIR__.'/routes/sidebar/sidebar-properties.php';
require __DIR__.'/routes/sidebar/sidebar-roles.php';
require __DIR__.'/routes/sidebar/sidebar-general-roles.php';
require __DIR__.'/routes/sidebar/sidebar-volunteeropportunity.php';

// pastoral care
require __DIR__.'/routes/pastoralcare/pastoralcare.php';

// system routes
require __DIR__.'/routes/system/system.php'; // OK
require __DIR__.'/routes/system/system-custom-fields.php';
require __DIR__.'/routes/system/system-synchronize.php';
require __DIR__.'/routes/system/system-database.php';
require __DIR__.'/routes/system/system-gdrp.php';
require __DIR__.'/routes/system/system-issues.php';
require __DIR__.'/routes/system/system-system-upgrade.php';
require __DIR__.'/routes/system/system-timerjobs.php'; // OK

// users routes
require __DIR__.'/routes/user/user-users.php'; // ok
require __DIR__.'/routes/user/user-role.php'; // ok

// the rest
require __DIR__.'/routes/cart.php';
require __DIR__.'/routes/geocoder.php';
require __DIR__.'/routes/kiosks.php'; // OK
require __DIR__.'/routes/mailchimp.php'; // OK

require __DIR__.'/routes/search.php'; // OK
require __DIR__.'/routes/sundayschool.php'; // OK

// meeting route
require __DIR__.'/routes/meeting/meeting.php'; // OK

// fundraiser route
require __DIR__.'/routes/fundraiser/fundraiser.php'; // OK

// Run app
$app->run();
