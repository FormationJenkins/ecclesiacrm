<?php

use Slim\Routing\RouteCollectorProxy;
use EcclesiaCRM\VIEWControllers\VIEWUserController;

$app->group('/users', function (RouteCollectorProxy $group) {
    $group->get('', VIEWUserController::class . ':renderUserList' );
    $group->get('/', VIEWUserController::class . ':renderUserList' );
    
    $group->get('/settings', VIEWUserController::class . ':renderUserSettings' );
    $group->post('/settings', VIEWUserController::class . ':renderUserSettings' );

    $group->get('/editor', VIEWUserController::class . ':renderUserEditor' );
    $group->post('/editor', VIEWUserController::class . ':renderUserEditor' );

    $group->get('/editor/{PersonID:[0-9]+}', VIEWUserController::class . ':renderUserEditor' );

    $group->get('/editor/{PersonID:[0-9]+}/errormessage/{errorMsg}', VIEWUserController::class . ':renderUserEditorErrorMsg' );

    $group->get('/editor/new', VIEWUserController::class . ':renderNewUserEditorErrorMsg' );
    $group->get('/editor/new/{NewPersonID:[0-9]+}', VIEWUserController::class . ':renderNewUserEditorErrorMsg' );
    $group->get('/editor/new/{NewPersonID:[0-9]+}/errormessage/{errorMsg}', VIEWUserController::class . ':renderNewUserEditorErrorMsg' );
});
