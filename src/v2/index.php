<?php

require '../Include/Config.php';
require '../Include/Functions.php';

// This file is generated by Composer
require_once dirname(__FILE__).'/../vendor/autoload.php';

use EcclesiaCRM\Slim\Middleware\VersionMiddleware;
use Slim\Middleware\JwtAuthentication;
use Slim\Container;
use Slim\App;

use Slim\HttpCache\Cache;
use Slim\HttpCache\CacheProvider;

use EcclesiaCRM\TokenQuery;

// Instantiate the app
$settings = require __DIR__.'/../Include/slim/settings.php';

$container = new Container;
$container['cache'] = function () {
    return new CacheProvider();
};

// Add middleware to the application
$app = new App($container);

$app->add(new VersionMiddleware());

$app->add(new JwtAuthentication([
    "secret" => TokenQuery::Create()->findOneByType("secret")->getToken(),
    "path" => "/v2",
    "algorithm" => ["HS256"],
    "error" => function ($response, $arguments) {
        $data["status"] = "error";
        $data["message"] = $arguments["message"];
        return $response
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
]));

// Set up
require __DIR__.'/../Include/slim/error-handler.php';

// the main dashboard
require __DIR__ . '/routes/dashboard.php';

// the routes
require __DIR__ . '/routes/user/user.php';
require __DIR__ . '/routes/calendar/calendar.php';
require __DIR__ . '/routes/gdpr/gdpr.php';
require __DIR__ . '/routes/map/map.php';


// the sidebar routes
require __DIR__ . '/routes/sidebar/menulinklist.php';
require __DIR__ . '/routes/sidebar/pastoralcarelist.php';
require __DIR__ . '/routes/sidebar/fundlist.php';
require __DIR__ . '/routes/sidebar/volunteeropportunityeditor.php';
require __DIR__ . '/routes/sidebar/propertylist.php';
require __DIR__ . '/routes/sidebar/propertytypelist.php';
require __DIR__ . '/routes/sidebar/kioskmanager.php';

// people
require __DIR__ . '/routes/people/people.php';
require __DIR__ . '/routes/people/familylist.php';
require __DIR__ . '/routes/people/personlist.php';

// pastoralcare
require __DIR__ . '/routes/pastoralcare/pastoralcare.php';

require __DIR__ . '/routes/group/groups.php';

// backup route
require __DIR__ . '/routes/backup/backup.php';
require __DIR__ . '/routes/backup/restore.php';

// email routes
// mailchimp
require __DIR__ . '/routes/email/mailchimp/mailchimp.php';

// sunday school route
require __DIR__ . '/routes/sundayschool/sundayschool.php';


// sunday school route
require __DIR__ . '/routes/cart/cart.php';

// meeting
require __DIR__ . '/routes/meeting/meeting.php';

// fundraiser
require __DIR__ . '/routes/fundraiser/fundraiser.php';



$app->run();
