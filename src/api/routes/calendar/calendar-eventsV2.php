<?php

/*******************************************************************************
 *
 *  filename    : events.php
 *  last change : 2018-05-1
 *  description : manage the full calendar with events
 *
 *  http://www.ecclesiacrm.com/
 *  This code is under copyright not under MIT Licence
 *  copyright   : 2018 Philippe Logel all right reserved not MIT licence
 *                This code can't be incoprorated in another software without authorizaion
 *  Updated : 2018/05/13
 *
 ******************************************************************************/

use Slim\Http\Request;
use Slim\Http\Response;


use EcclesiaCRM\dto\SystemConfig;
use EcclesiaCRM\Base\EventQuery;
use EcclesiaCRM\Base\EventTypesQuery;
use EcclesiaCRM\EventCountsQuery;
use EcclesiaCRM\EventCounts;
use EcclesiaCRM\Person2group2roleP2g2rQuery;
use EcclesiaCRM\FamilyQuery;
use EcclesiaCRM\dto\MenuEventsCount;
use EcclesiaCRM\Utils\InputUtils;
use EcclesiaCRM\EventCountNameQuery;
use EcclesiaCRM\EventAttend;
use EcclesiaCRM\Utils\GeoUtils;
use EcclesiaCRM\SessionUser;
use EcclesiaCRM\UserQuery;

use EcclesiaCRM\CalendarinstancesQuery;

use Sabre\VObject;

use EcclesiaCRM\MyPDO\CalDavPDO;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;

use EcclesiaCRM\utils\LoggerUtils;


$app->group('/events', function () {

    /*
      * @! Get all events for all calendars for a specified range
      */
    $this->get('/', "getAllEvents" );
    /*
      * @! Get all events after now
      */
    $this->get('/notDone', "getNotDoneEvents" );
    /*
     * @! Get all events from today
     */
    $this->get('/numbers', "numbersOfEventOfToday") ;
    /*
     * @! Get all event type
     */
    $this->get('/types', "getEventTypes" );
    /*
     * @! Get all event names
     */
    $this->get('/names', "eventNames" );
    /*
     * @! delete event type
     * #! param: id->int  :: type ID
     */
    $this->post('/deleteeventtype', "deleteeventtype" );
    /*
     * @! get event info
     * #! param: id->int  :: event ID
     */
    $this->post('/info', "eventInfo" );
    /*
    * @! Set a person for the event + check
    * #! param: id->int  :: event ID
    * #! param: id->int  :: person ID
    */
    $this->post('/person', "personCheckIn" );
    /*
    * @! Set the group persons for the event + check
    * #! param: id->int  :: event ID
    * #! param: id->int  :: group ID
    */
    $this->post('/group', "groupCheckIn" );
    /*
    * @! Set the family persons for the event + check
    * #! param: id->int  :: event ID
    * #! param: id->int  :: family ID
    */
    $this->post('/family', "familyCheckIn" );
    /*
    * @! get event count
    * #! param: id->int  :: event ID
    * #! param: id->int  :: type ID
    */
    $this->post('/attendees', "eventCount" );
    /*
    * @! manage an event eventAction, [createEvent,moveEvent,resizeEvent,attendeesCheckinEvent,suppress,modifyEvent]
    * #! param: id->int       :: eventID
    * #! param: id->int       :: type ID
    * #! param: ref->array    :: calendarID
    * #! param: id->int       :: reccurenceID
    * #! param: ref->start    :: the start date : YYYY-MM-DD
    * #! param: ref->start    :: the end date : YYYY-MM-DD
    * #! param: ref->location :: location
    */
    $this->post('/', "manageEvent" );

});

function getAllEvents(Request $request, Response $response, array $args)
{
    $Events = EventQuery::create()
        ->find();
    return $response->write($Events->toJSON());
}

function getNotDoneEvents(Request $request, Response $response, array $args)
{
    $Events = EventQuery::create()
        ->filterByEnd(new DateTime(), Criteria::GREATER_EQUAL)
        ->find();
    return $response->write($Events->toJSON());
}

function numbersOfEventOfToday(Request $request, Response $response, array $args)
{
    $response->withJson(MenuEventsCount::getNumberEventsOfToday());
}

function getEventTypes(Request $request, Response $response, array $args)
{
    $eventTypes = EventTypesQuery::Create()
        ->orderByName()
        ->find();

    $return = [];

    foreach ($eventTypes as $eventType) {
        $values['eventTypeID'] = $eventType->getID();
        $values['name'] = $eventType->getName();

        array_push($return, $values);
    }

    return $response->withJson($return);
}

function eventNames(Request $request, Response $response, array $args)
{
    $ormEvents = EventQuery::Create()->orderByTitle()->find();

    $return = [];
    foreach ($ormEvents as $ormEvent) {
        $values['eventTypeID'] = $ormEvent->getID();
        $values['name'] = $ormEvent->getTitle() . " (" . $ormEvent->getDesc() . ")";

        array_push($return, $values);
    }

    return $response->withJson($return);
}


function deleteeventtype(Request $request, Response $response, array $args)
{
    $input = (object)$request->getParsedBody();

    if (isset ($input->typeID)) {
        $eventType = EventTypesQuery::Create()
            ->filterById(InputUtils::LegacyFilterInput($input->typeID))
            ->limit(1)
            ->findOne();

        if (!empty($eventType)) {
            $eventType->delete();
        }

        $eventCountNames = EventCountNameQuery::Create()
            ->findByTypeId(InputUtils::LegacyFilterInput($input->typeID));

        if (!empty($eventCountNames)) {
            $eventCountNames->delete();
        }


        return $response->withJson(['status' => "success"]);

    }

    return $response->withJson(['status' => "failed"]);
}

function eventInfo(Request $request, Response $response, array $args)
{
    $input = (object)$request->getParsedBody();

    if (isset ($input->eventID)) {
        $event = EventQuery::Create()->findOneById($input->eventID);

        if (is_null($event)) {
            return $response->withJson(["status" => "failed"]);
        }

        $arr['eventID'] = $event->getId();
        $arr['Title'] = $event->getTitle();
        $arr['Desc'] = $event->getDesc();
        $arr['Text'] = $event->getText();
        $arr['start'] = $event->getStart('Y-m-d H:i:s');
        $arr['end'] = $event->getEnd('Y-m-d H:i:s');
        $arr['calendarID'] = [$event->getEventCalendarid(), 0];
        $arr['eventTypeID'] = $event->getType();
        $arr['inActive'] = $event->getInActive();
        $arr['location'] = $event->getLocation();
        $arr['latitude'] = $event->getLatitude();
        $arr['longitude'] = $event->getLongitude();

        return $response->withJson($arr);

    }

    return $response->withJson(['status' => "failed"]);
}

function personCheckIn(Request $request, Response $response, array $args)
{
    $params = (object)$request->getParsedBody();

    try {
        $eventAttent = new EventAttend();

        $eventAttent->setEventId($params->EventID);
        $eventAttent->setCheckinId(SessionUser::getUser()->getPersonId());
        $date = new DateTime('now', new DateTimeZone(SystemConfig::getValue('sTimeZone')));
        $eventAttent->setCheckinDate($date->format('Y-m-d H:i:s'));
        $eventAttent->setPersonId($params->PersonId);
        $eventAttent->save();
    } catch (\Exception $ex) {
        $errorMessage = $ex->getMessage();
        return $response->withJson(['status' => $errorMessage]);
    }

    return $response->withJson(['status' => "success"]);
}

function groupCheckIn(Request $request, Response $response, array $args)
{
    $params = (object)$request->getParsedBody();

    $persons = Person2group2roleP2g2rQuery::create()
        ->usePersonQuery()
        ->filterByDateDeactivated(null)// GDRP, when a person is completely deactivated
        ->endUse()
        ->filterByGroupId($params->GroupID)
        ->find();

    foreach ($persons as $person) {
        try {
            if ($person->getPersonId() > 0) {
                $eventAttent = new EventAttend();

                $eventAttent->setEventId($params->EventID);
                $eventAttent->setCheckinId(SessionUser::getUser()->getPersonId());
                $date = new DateTime('now', new DateTimeZone(SystemConfig::getValue('sTimeZone')));
                $eventAttent->setCheckinDate($date->format('Y-m-d H:i:s'));
                $eventAttent->setPersonId($person->getPersonId());
                $eventAttent->save();
            }
        } catch (\Exception $ex) {
            $errorMessage = $ex->getMessage();
            //return $response->withJson(['status' => $errorMessage]);
        }
    }

    return $response->withJson(['status' => "success"]);
}

function familyCheckIn(Request $request, Response $response, array $args)
{
    $params = (object)$request->getParsedBody();

    $family = FamilyQuery::create()
        ->findPk($params->FamilyID);

    foreach ($family->getPeople() as $person) {
        //return $response->withJson(['person' => $person->getId(),"eventID" => $params->EventID]);
        try {
            if ($person->getId() > 0 && $person->getDateDeactivated() == null) {// GDRP, when a person is completely deactivated
                $eventAttent = new EventAttend();

                $eventAttent->setEventId($params->EventID);
                $eventAttent->setCheckinId(SessionUser::getUser()->getPersonId());
                $date = new DateTime('now', new DateTimeZone(SystemConfig::getValue('sTimeZone')));
                $eventAttent->setCheckinDate($date->format('Y-m-d H:i:s'));
                $eventAttent->setPersonId($person->getId());
                $eventAttent->save();
            }
        } catch (\Exception $ex) {
            $errorMessage = $ex->getMessage();
            //return $response->withJson(['status' => $errorMessage]);
        }
    }

    return $response->withJson(['status' => "success"]);
}


function eventCount(Request $request, Response $response, array $args)
{
    $params = (object)$request->getParsedBody();

    // Get a list of the attendance counts currently associated with thisevent type
    $eventCountNames = EventCountNameQuery::Create()
        ->filterByTypeId($params->typeID)
        ->orderById()
        ->find();

    $numCounts = count($eventCountNames);

    $return = [];

    if ($numCounts) {
        foreach ($eventCountNames as $eventCountName) {
            $values['countID'] = $eventCountName->getId();
            $values['countName'] = $eventCountName->getName();
            $values['typeID'] = $params->typeID;

            $values['count'] = 0;
            $values['notes'] = "";

            if ($params->eventID > 0) {
                $eventCounts = EventCountsQuery::Create()->filterByEvtcntCountid($eventCountName->getId())->findOneByEvtcntEventid($params->eventID);

                if (!empty($eventCounts)) {
                    $values['count'] = $eventCounts->getEvtcntCountcount();
                    $values['notes'] = $eventCounts->getEvtcntNotes();
                }
            }

            array_push($return, $values);
        }
    }

    return $response->withJson($return);
}


function manageEvent(Request $request, Response $response, array $args)
{
    $input = (object)$request->getParsedBody();

    if (!strcmp($input->eventAction, 'createEvent')) {
        // new way to manage events
        // we get the PDO for the Sabre connection from the Propel connection
        $pdo = Propel::getConnection();

        // We set the BackEnd for sabre Backends
        $calendarBackend = new CalDavPDO($pdo->getWrappedConnection());

        $uuid = strtoupper(\Sabre\DAV\UUIDUtil::getUUID());

        $vcalendar = new EcclesiaCRM\MyVCalendar\VCalendarExtension();

        $calIDs = explode(",", $input->calendarID);

        // We move to propel, to find the calendar
        $calendarId = $calIDs[0];
        $Id = $calIDs[1];
        $calendar = CalendarinstancesQuery::Create()->filterByCalendarid($calendarId)->findOneById($Id);

        $coordinates = "";
        $location = '';

        if (isset($input->location)) {
            $location = str_replace("\n", " ", $input->location);
            $latLng = GeoUtils::getLatLong($input->location);
            if (!empty($latLng['Latitude']) && !empty($latLng['Longitude'])) {
                $coordinates = $latLng['Latitude'] . ' commaGMAP ' . $latLng['Longitude'];
            }
        }

        // we remove to Sabre
        if (!empty($input->recurrenceValid)) {

            $vevent = [
                'CREATED' => (new \DateTime('Now'))->format('Ymd\THis'),
                'DTSTAMP' => (new \DateTime('Now'))->format('Ymd\THis'),
                'DTSTART' => (new \DateTime($input->start))->format('Ymd\THis'),
                'DTEND' => (new \DateTime($input->end))->format('Ymd\THis'),
                'LAST-MODIFIED' => (new \DateTime('Now'))->format('Ymd\THis'),
                'DESCRIPTION' => $input->EventDesc,
                'SUMMARY' => $input->EventTitle,
                'LOCATION' => $input->location,
                'UID' => $uuid,
                'RRULE' => $input->recurrenceType . ';' . 'UNTIL=' . (new \DateTime($input->endrecurrence))->format('Ymd\THis'),
                'SEQUENCE' => '0',
                'TRANSP' => 'OPAQUE',
                'X-APPLE-TRAVEL-ADVISORY-BEHAVIOR' => 'AUTOMATIC',
                "X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-RADIUS=49.91307587029686;X-TITLE=\"" . $location . "\"" => "geo:" . $coordinates
            ];

        } else {

            $vevent = [
                'CREATED' => (new \DateTime('Now'))->format('Ymd\THis'),
                'DTSTAMP' => (new \DateTime('Now'))->format('Ymd\THis'),
                'DTSTART' => (new \DateTime($input->start))->format('Ymd\THis'),
                'DTEND' => (new \DateTime($input->end))->format('Ymd\THis'),
                'LAST-MODIFIED' => (new \DateTime('Now'))->format('Ymd\THis'),
                'DESCRIPTION' => $input->EventDesc,
                'SUMMARY' => $input->EventTitle,
                'LOCATION' => $input->location,
                'UID' => $uuid,
                'SEQUENCE' => '0',
                'X-APPLE-TRAVEL-ADVISORY-BEHAVIOR' => 'AUTOMATIC',
                "X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-RADIUS=49.91307587029686;X-TITLE=\"" . $location . "\"" => "geo:" . $coordinates
                //'X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-MAPKIT-HANDLE=CAESvAEaEglnaQKg5U5IQBFCfLuA8gIfQCJdCgZGcmFuY2USAkZSGgZBbHNhY2UqCEJhcy1SaGluMglCaXNjaGhlaW06BTY3ODAwUhJSdWUgUm9iZXJ0IEtpZWZmZXJaATFiFDEgUnVlIFJvYmVydCBLaWVmZmVyKhQxIFJ1ZSBSb2JlcnQgS2llZmZlcjIUMSBSdWUgUm9iZXJ0IEtpZWZmZXIyDzY3ODAwIEJpc2NoaGVpbTIGRnJhbmNlODlAAA==;X-APPLE-RADIUS=70.58736571013601;X-TITLE="1 Rue Robert Kieffer\nBischheim, France":geo' => '48.616383,7.752878'
            ];

        }

        $realVevent = $vcalendar->add('VEVENT', $vevent);

        //$res = '';

        if ($calendar->getGroupId() && $input->addGroupAttendees) {// add Attendees with sabre connection
            $persons = Person2group2roleP2g2rQuery::create()
                ->filterByGroupId($calendar->getGroupId())
                ->find();

            $res = $persons->count();

            if ($persons->count() > 0) {

                $realVevent->add('ORGANIZER', 'mailto:' . SessionUser::getUser()->getEmail());

                //$res .= SessionUser::getUser()->getEmail();

                foreach ($persons as $person) {
                    $user = UserQuery::Create()->findOneByPersonId($person->getPersonId());
                    if (!empty($user)) {
                        $vevent = array_merge($vevent, ['ATTENDEE;CN=' . $user->getFullName() . ';CUTYPE=INDIVIDUAL;EMAIL=' . $user->getEmail() . ';PARTSTAT=ACCEPTED;SCHEDULE-STATUS=3.7:mailto' => $user->getEmail()]);
                        $realVevent->add('ATTENDEE', 'mailto:' . $user->getEmail());
                        $res .= " " . $user->getEmail();
                    }
                }
            }
        }

        if ($input->alarm != _("NONE")) {
            $realVevent->add('VALARM', ['TRIGGER' => $input->alarm, 'DESCRIPTION' => 'Event reminder', 'ACTION' => 'DISPLAY']);
        }
        //return $response->withJson(["status" => $vcalendar->serialize(),"grpId" => $calendar->getGroupId(), "cocuou" => $input->addGroupAttendees,"res" => $res]);


        // Now we move to propel, to finish the put extra infos
        $etag = $calendarBackend->createCalendarObject($calIDs, $uuid, $vcalendar->serialize());

        // we get the real event in th DB
        $event = EventQuery::Create()->findOneByEtag(str_replace('"', "", $etag));
        $eventTypeName = "";

        if ($input->eventTypeID) {
            $type = EventTypesQuery::Create()
                ->findOneById($input->eventTypeID);
            $eventTypeName = $type->getName();
        }

        $event->setType($input->eventTypeID);
        $event->setText($input->eventNotes);
        $event->setTypeName($eventTypeName);
        $event->setInActive($input->eventInActive);

        // we set the groupID to manage correctly the attendees : Historical
        $event->setGroupId($calendar->getGroupId());
        $event->setLocation($input->location);
        $event->setCoordinates($coordinates);

        $event->save();

        if (!empty($input->Fields)) {
            foreach ($input->Fields as $field) {
                $eventCount = new EventCounts;
                $eventCount->setEvtcntEventid($event->getID());
                $eventCount->setEvtcntCountid($field['countid']);
                $eventCount->setEvtcntCountname($field['name']);
                $eventCount->setEvtcntCountcount($field['value']);
                $eventCount->setEvtcntNotes($input->EventCountNotes);
                $eventCount->save();
            }
        }

        $event->save();

        if ($event->getGroupId() && $input->addGroupAttendees) {// add Attendees
            $persons = Person2group2roleP2g2rQuery::create()
                ->filterByGroupId($event->getGroupId())
                ->find();

            if ($persons->count() > 0) {
                foreach ($persons as $person) {
                    try {
                        if ($person->getPersonId() > 0) {
                            $eventAttent = new EventAttend();

                            $eventAttent->setEventId($event->getID());
                            $eventAttent->setCheckinId(SessionUser::getUser()->getPersonId());
                            $date = new DateTime('now', new DateTimeZone(SystemConfig::getValue('sTimeZone')));
                            $eventAttent->setCheckinDate($date->format('Y-m-d H:i:s'));
                            $eventAttent->setPersonId($person->getPersonId());
                            $eventAttent->save();
                        }
                    } catch (\Exception $ex) {
                        $errorMessage = $ex->getMessage();
                        //return $response->withJson(['status' => $errorMessage]);
                    }
                }

                //
                $_SESSION['Action'] = 'Add';
                $_SESSION['EID'] = $event->getID();
                $_SESSION['EName'] = $input->EventTitle;
                $_SESSION['EDesc'] = $input->EventDesc;
                $_SESSION['EDate'] = $date->format('Y-m-d H:i:s');

                $_SESSION['EventID'] = $event->getID();
            }
        }

        return $response->withJson(["status" => "success"]);

    } else if (!strcmp($input->eventAction, 'moveEvent')) {

        $pdo = Propel::getConnection();

        // We set the BackEnd for sabre Backends
        $calendarBackend = new CalDavPDO($pdo->getWrappedConnection());

        $event = $calendarBackend->getCalendarObjectById($input->calendarID, $input->eventID);

        $vcalendar = VObject\Reader::read($event['calendardata']);

        if (isset($input->allEvents) && isset($input->reccurenceID)) {

            if ($input->allEvents == true) { // we'll move all the events

                $exdates = [];

                $oldStart = new \DateTime ($vcalendar->VEVENT->DTSTART->getDateTime()->format('Y-m-d H:i:s'));
                $oldEnd = new \DateTime ($vcalendar->VEVENT->DTEND->getDateTime()->format('Y-m-d H:i:s'));

                $oldSubStart = new \DateTime($input->reccurenceID);
                $newSubStart = new \DateTime($input->start);

                if ($newSubStart < $oldSubStart) {
                    $interval = $oldSubStart->diff($newSubStart);

                    $newStart = $oldStart->add($interval);
                    $newEnd = $oldEnd->add($interval);

                    $action = +1;
                } else {
                    $interval = $newSubStart->diff($oldSubStart);

                    $newStart = $oldStart->sub($interval);
                    $newEnd = $oldEnd->sub($interval);

                    $action = -1;
                }


                $oldrule = $vcalendar->VEVENT->RRULE;
                $oldruleSplit = explode("UNTIL=", $oldrule);

                $oldRuleFreq = $oldruleSplit[0];
                $oldRuleFinishDate = new \DateTime($oldruleSplit[1]);

                if ($action == +1) {
                    $newrule = $oldRuleFreq . "UNTIL=" . $oldRuleFinishDate->add($interval)->format('Ymd\THis');
                } else {
                    $newrule = $oldRuleFreq . "UNTIL=" . $oldRuleFinishDate->sub($interval)->format('Ymd\THis');
                }

                foreach ($vcalendar->VEVENT->EXDATE as $exdate) {

                    $ex_date = new \DateTime ($exdate);

                    if ($action == +1) {
                        $new_ex_date = $ex_date->add($interval);
                    } else {
                        $new_ex_date = $ex_date->sub($interval);
                    }

                    array_push($exdates, $new_ex_date->format('Y-m-d H:i:s'));
                }

                $vcalendar->VEVENT->remove('EXDATE');

                foreach ($exdates as $exdate) {
                    $vcalendar->VEVENT->add('EXDATE', (new \DateTime($exdate))->format('Ymd\THis'));
                }

                //$i = 0;
                foreach ($vcalendar->VEVENT as $sevent) {
                    $old_recID = new \DateTime ($sevent->{'RECURRENCE-ID'});

                    if ($action == +1) {
                        $new_recID = $old_recID->add($interval);
                    } else {
                        $new_recID = $old_recID->sub($interval);
                    }

                    //if ($i++ > 0) {// the first event is the main event and must not have the RECURRENCE-ID !!!!
                    $sevent->{'RECURRENCE-ID'} = $new_recID->format('Ymd\THis');
                    //}
                }

                // we remove only the first one in the main event.
                $vcalendar->VEVENT->remove('RECURRENCE-ID');

                $vcalendar->VEVENT->remove('RRULE');

                $vcalendar->VEVENT->add('RRULE', $newrule);

                $vcalendar->VEVENT->DTSTART = $newStart->format('Ymd\THis');
                $vcalendar->VEVENT->DTEND = $newEnd->format('Ymd\THis');
                $vcalendar->VEVENT->{'LAST-MODIFIED'} = (new \DateTime('Now'))->format('Ymd\THis');

                $calendarBackend->updateCalendarObject($input->calendarID, $event['uri'], $vcalendar->serialize());

                return $response->withJson(["status" => "success"]);

            } else {
                // new code :
                // we've to search the dates
                $old_RECURRENCE_ID = '';
                $old_SUMMARY = '';
                $old_LOCATION = '';
                $old_UID = '';

                $returnValues = $calendarBackend->extractCalendarData($event['calendardata']);

                foreach ($returnValues as $key => $value) {
                    if ($key == 'freqEvents') {
                        foreach ($value as $sevent) {
                            if ($sevent['RECURRENCE-ID'] == (new \DateTime($input->reccurenceID))->format('Y-m-d H:i:s')) {
                                $old_RECURRENCE_ID = $sevent['RECURRENCE-ID'];
                                $old_SUMMARY = $sevent['SUMMARY'];
                                $old_DESCRIPTION = $sevent['DESCRIPTION'];
                                $old_LOCATION = $sevent['DESCRIPTION'];
                                $old_UID = $sevent['UID'];

                                // we have to delete the last occurence
                                $calendarBackend->searchAndDeleteOneEvent($vcalendar, $old_RECURRENCE_ID);
                                break;
                            }
                        }
                    }
                }

                if (!empty($old_UID)) {
                    //first we have to exclude the date
                    //$vcalendar->VEVENT->add('EXDATE', (new \DateTime($input->reccurenceID))->format('Ymd\THis'));

                    // only in the case we've found something
                    // the location
                    $coordinates = "";
                    $location = '';

                    if (isset($input->location)) {
                        $location = str_replace("\n", " ", $old_LOCATION);
                        $latLng = GeoUtils::getLatLong($old_LOCATION);
                        if (!empty($latLng['Latitude']) && !empty($latLng['Longitude'])) {
                            $coordinates = $latLng['Latitude'] . ' commaGMAP ' . $latLng['Longitude'];
                        }
                    }

                    $new_vevent = [
                        'DTSTAMP' => (new \DateTime('Now'))->format('Ymd\THis'),
                        'DTSTART' => (new \DateTime($input->start))->format('Ymd\THis'),
                        'DTEND' => (new \DateTime($input->end))->format('Ymd\THis'),
                        'LAST-MODIFIED' => (new \DateTime('Now'))->format('Ymd\THis'),
                        'DESCRIPTION' => $old_DESCRIPTION,
                        'SUMMARY' => $old_SUMMARY,
                        'LOCATION' => $old_LOCATION,
                        'UID' => $old_UID,
                        'SEQUENCE' => '0',
                        'RECURRENCE-ID' => (new \DateTime($input->reccurenceID))->format('Ymd\THis'),
                        'X-APPLE-TRAVEL-ADVISORY-BEHAVIOR' => 'AUTOMATIC',
                        "X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-RADIUS=49.91307587029686;X-TITLE=\"" . $location . "\"" => "geo:" . $coordinates
                        //'X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-MAPKIT-HANDLE=CAESvAEaEglnaQKg5U5IQBFCfLuA8gIfQCJdCgZGcmFuY2USAkZSGgZBbHNhY2UqCEJhcy1SaGluMglCaXNjaGhlaW06BTY3ODAwUhJSdWUgUm9iZXJ0IEtpZWZmZXJaATFiFDEgUnVlIFJvYmVydCBLaWVmZmVyKhQxIFJ1ZSBSb2JlcnQgS2llZmZlcjIUMSBSdWUgUm9iZXJ0IEtpZWZmZXIyDzY3ODAwIEJpc2NoaGVpbTIGRnJhbmNlODlAAA==;X-APPLE-RADIUS=70.58736571013601;X-TITLE="1 Rue Robert Kieffer\nBischheim, France":geo' => '48.616383,7.752878'
                    ];

                    $vcalendar->add('VEVENT', $new_vevent);

                    $calendarBackend->updateCalendarObject($input->calendarID, $event['uri'], $vcalendar->serialize());

                    return $response->withJson(["status" => "success"]);
                } else {
                    return $response->withJson(["status" => "failed"]);
                }
            }

        } else {

            $vcalendar->VEVENT->DTSTART = (new \DateTime($input->start))->format('Ymd\THis');
            $vcalendar->VEVENT->DTEND = (new \DateTime($input->end))->format('Ymd\THis');
            $vcalendar->VEVENT->{'LAST-MODIFIED'} = (new \DateTime('Now'))->format('Ymd\THis');

            $calendarBackend->updateCalendarObject($input->calendarID, $event['uri'], $vcalendar->serialize());

            return $response->withJson(["status" => "success"]);
        }

        return $response->withJson(["status" => "failed"]);
    } else if (!strcmp($input->eventAction, 'resizeEvent')) {
        $pdo = Propel::getConnection();

        // We set the BackEnd for sabre Backends
        $calendarBackend = new CalDavPDO($pdo->getWrappedConnection());

        $event = $calendarBackend->getCalendarObjectById($input->calendarID, $input->eventID);

        $vcalendar = VObject\Reader::read($event['calendardata']);

        if (isset($input->allEvents) && isset($input->reccurenceID) && isset($input->start) && isset($input->end)) {
            if ($input->allEvents == true) { // we'll resize all the events

                $oldStart = new \DateTime ($vcalendar->VEVENT->DTSTART->getDateTime()->format('Y-m-d H:i:s'));
                $oldEnd = new \DateTime ($vcalendar->VEVENT->DTEND->getDateTime()->format('Y-m-d H:i:s'));

                $newSubStart = new \DateTime($input->start);
                $newSubEnd = new \DateTime($input->end);

                $interval = $newSubStart->diff($newSubEnd);

                $vcalendar->VEVENT->DTSTART = ($oldStart)->format('Ymd\THis');
                $vcalendar->VEVENT->DTEND = ($oldStart->add($interval))->format('Ymd\THis');
                $vcalendar->VEVENT->{'LAST-MODIFIED'} = (new \DateTime('Now'))->format('Ymd\THis');

                $calendarBackend->updateCalendarObject($input->calendarID, $event['uri'], $vcalendar->serialize());

                return $response->withJson(["status" => "success"]);
            } else {
                // new code : first we have to exclude the date
                // we've to search the dates
                $old_RECURRENCE_ID = '';
                $old_SUMMARY = '';
                $old_LOCATION = '';
                $old_UID = '';

                $returnValues = $calendarBackend->extractCalendarData($event['calendardata']);

                foreach ($returnValues as $key => $value) {
                    if ($key == 'freqEvents') {
                        foreach ($value as $sevent) {
                            if ($sevent['RECURRENCE-ID'] == (new \DateTime($input->reccurenceID))->format('Y-m-d H:i:s')) {
                                $old_RECURRENCE_ID = $sevent['RECURRENCE-ID'];
                                $old_SUMMARY = $sevent['SUMMARY'];
                                $old_DESCRIPTION = $sevent['DESCRIPTION'];
                                $old_LOCATION = $sevent['DESCRIPTION'];
                                $old_UID = $sevent['UID'];

                                // we have to delete the last occurence
                                $calendarBackend->searchAndDeleteOneEvent($vcalendar, $old_RECURRENCE_ID);
                                break;
                            }
                        }
                    }
                }

                if (!empty($old_UID)) {
                    // only in the case we've found something
                    // the location
                    $coordinates = "";
                    $location = '';

                    if (isset($input->location)) {
                        $location = str_replace("\n", " ", $old_LOCATION);
                        $latLng = GeoUtils::getLatLong($old_LOCATION);
                        if (!empty($latLng['Latitude']) && !empty($latLng['Longitude'])) {
                            $coordinates = $latLng['Latitude'] . ' commaGMAP ' . $latLng['Longitude'];
                        }
                    }

                    $new_vevent = [
                        'DTSTAMP' => (new \DateTime('Now'))->format('Ymd\THis'),
                        'DTSTART' => (new \DateTime($input->start))->format('Ymd\THis'),
                        'DTEND' => (new \DateTime($input->end))->format('Ymd\THis'),
                        'LAST-MODIFIED' => (new \DateTime('Now'))->format('Ymd\THis'),
                        'DESCRIPTION' => $old_DESCRIPTION,
                        'SUMMARY' => $old_SUMMARY,
                        'LOCATION' => $old_LOCATION,
                        'UID' => $old_UID,
                        'SEQUENCE' => '0',
                        'RECURRENCE-ID' => (new \DateTime($input->reccurenceID))->format('Ymd\THis'),
                        'X-APPLE-TRAVEL-ADVISORY-BEHAVIOR' => 'AUTOMATIC',
                        "X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-RADIUS=49.91307587029686;X-TITLE=\"" . $location . "\"" => "geo:" . $coordinates
                        //'X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-MAPKIT-HANDLE=CAESvAEaEglnaQKg5U5IQBFCfLuA8gIfQCJdCgZGcmFuY2USAkZSGgZBbHNhY2UqCEJhcy1SaGluMglCaXNjaGhlaW06BTY3ODAwUhJSdWUgUm9iZXJ0IEtpZWZmZXJaATFiFDEgUnVlIFJvYmVydCBLaWVmZmVyKhQxIFJ1ZSBSb2JlcnQgS2llZmZlcjIUMSBSdWUgUm9iZXJ0IEtpZWZmZXIyDzY3ODAwIEJpc2NoaGVpbTIGRnJhbmNlODlAAA==;X-APPLE-RADIUS=70.58736571013601;X-TITLE="1 Rue Robert Kieffer\nBischheim, France":geo' => '48.616383,7.752878'
                    ];

                    $vcalendar->add('VEVENT', $new_vevent);

                    $calendarBackend->updateCalendarObject($input->calendarID, $event['uri'], $vcalendar->serialize());

                    return $response->withJson(["status" => "success"]);
                } else {
                    return $response->withJson(["status" => "failed"]);
                }
            }
        } else {

            $vcalendar->VEVENT->DTSTART = (new \DateTime($input->start))->format('Ymd\THis');
            $vcalendar->VEVENT->DTEND = (new \DateTime($input->end))->format('Ymd\THis');
            $vcalendar->VEVENT->{'LAST-MODIFIED'} = (new \DateTime('Now'))->format('Ymd\THis');

            $calendarBackend->updateCalendarObject($input->calendarID, $event['uri'], $vcalendar->serialize());

            return $response->withJson(['status' => "success1"]);
        }

        return $response->withJson(['status' => "failed"]);
    } else if (!strcmp($input->eventAction, 'attendeesCheckinEvent')) {
        $event = EventQuery::Create()
            ->findOneById($input->eventID);

        // for the CheckIn and to add attendees
        $_SESSION['Action'] = 'Add';
        $_SESSION['EID'] = $event->getID();
        $_SESSION['EName'] = $event->getTitle();
        $_SESSION['EDesc'] = $event->getDesc();
        $_SESSION['EDate'] = $event->getStart()->format('Y-m-d H:i:s');

        $_SESSION['EventID'] = $event->getID();

        return $response->withJson(['status' => "success"]);
    } else if (!strcmp($input->eventAction, 'suppress')) {
        // new way to manage events
        // we get the PDO for the Sabre connection from the Propel connection
        $pdo = Propel::getConnection();

        // We set the BackEnd for sabre Backends
        $calendarBackend = new CalDavPDO($pdo->getWrappedConnection());
        $event = $calendarBackend->getCalendarObjectById($input->calendarID, $input->eventID);

        if (isset ($input->reccurenceID)) {

            try {

                $vcalendar = VObject\Reader::read($event['calendardata']);

                $calendarBackend->searchAndDeleteOneEvent($vcalendar, $input->reccurenceID);

                $vcalendar->VEVENT->add('EXDATE', (new \DateTime($input->reccurenceID))->format('Ymd\THis'));
                $vcalendar->VEVENT->{'LAST-MODIFIED'} = (new \DateTime('Now'))->format('Ymd\THis');

                $calendarBackend->updateCalendarObject($input->calendarID, $event['uri'], $vcalendar->serialize());

            } catch (\Exception $ex) {
                $calendarBackend->deleteCalendarObject($input->calendarID, $event['uri']);
            }

        } else {// we delete only one event

            // We have to use the sabre way to ensure the event is reflected in external connection : CalDav
            $calendarBackend->deleteCalendarObject($input->calendarID, $event['uri']);

        }

        return $response->withJson(['status' => "success"]);
    } else if (!strcmp($input->eventAction, 'modifyEvent')) {
        $old_event = EventQuery::Create()->findOneById($input->eventID);

        if (is_null($old_event)) {
            return $response->withJson(["status" => "failed"]);
        }

        $oldCalendarID = [$old_event->getEventCalendarid(), 0];

        $pdo = Propel::getConnection();

        // We set the BackEnd for sabre Backends
        $calendarBackend = new CalDavPDO($pdo->getWrappedConnection());

        $event = $calendarBackend->getCalendarObjectById($oldCalendarID, $input->eventID);

        $vcalendar = VObject\Reader::read($event['calendardata']);

        if (isset($input->reccurenceID) && $input->reccurenceID != '') {// we're in a recursive event

            $date = new \DateTime ($vcalendar->VEVENT->DTSTART->getDateTime()->format('Y-m-d H:i:s'));

            if ($input->reccurenceID != '') {
                $date = $input->reccurenceID;
            }

            // we have to delete the old event from the reccurence event
            $vcalendar->VEVENT->add('EXDATE', (new \DateTime($input->reccurenceID))->format('Ymd\THis'));

            $i = 0;

            foreach ($vcalendar->VEVENT as $sevent) {
                if ($sevent->{'RECURRENCE-ID'} == (new \DateTime($input->reccurenceID))->format('Ymd\THis')) {
                    $vcalendar->remove($vcalendar->VEVENT[$i]);
                    break;
                }
                $i++;
            }

            $vcalendar->VEVENT->{'LAST-MODIFIED'} = (new \DateTime('Now'))->format('Ymd\THis');

            $calendarBackend->updateCalendarObject($oldCalendarID, $event['uri'], $vcalendar->serialize());
        } /*else {
            // We have to use the sabre way to ensure the event is reflected in external connection : CalDav
            $calendarBackend->deleteCalendarObject($oldCalendarID, $event['uri']);
        }*/

        // Now we start to work with the new calendar
        $calIDs = explode(",", $input->calendarID);

        $calendarId = $calIDs[0];
        $Id = $calIDs[1];

        $calendar = CalendarinstancesQuery::Create()->filterByCalendarid($calendarId)->findOneById($Id);

        $coordinates = "";
        $location = '';

        if (isset($input->location)) {
            $location = str_replace("\n", " ", $input->location);

            $latLng = GeoUtils::getLatLong($input->location);
            if (!empty($latLng['Latitude']) && !empty($latLng['Longitude'])) {
                $coordinates = $latLng['Latitude'] . ' commaGMAP ' . $latLng['Longitude'];
            }
        }

        $uuid = $vcalendar->VEVENT->UID;

        unset($vcalendar->VEVENT);
        if (!empty($input->recurrenceValid)) {
            $vevent = [
                'CREATED' => (new \DateTime('Now'))->format('Ymd\THis'),
                'DTSTAMP' => (new \DateTime('Now'))->format('Ymd\THis'),
                'DTSTART' => (new \DateTime($input->start))->format('Ymd\THis'),
                'DTEND' => (new \DateTime($input->end))->format('Ymd\THis'),
                'LAST-MODIFIED' => (new \DateTime('Now'))->format('Ymd\THis'),
                'DESCRIPTION' => $input->EventDesc,
                'SUMMARY' => $input->EventTitle,
                'UID' => $uuid,//'CE4306F2-8CC0-41DF-A971-1ED88AC208C7',// attention tout est en majuscules
                'RRULE' => $input->recurrenceType . ';' . 'UNTIL=' . (new \DateTime($input->endrecurrence))->format('Ymd\THis'),
                'SEQUENCE' => '0',
                'LOCATION' => $input->location,
                'TRANSP' => 'OPAQUE',
                'X-APPLE-TRAVEL-ADVISORY-BEHAVIOR' => 'AUTOMATIC',
                "X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-RADIUS=49.91307587029686;X-TITLE=\"" . $location . "\"" => "geo:" . $coordinates
            ];

        } else {
            $vevent = [
                'CREATED' => (new \DateTime('Now'))->format('Ymd\THis'),
                'DTSTAMP' => (new \DateTime('Now'))->format('Ymd\THis'),
                'DTSTART' => (new \DateTime($input->start))->format('Ymd\THis'),
                'DTEND' => (new \DateTime($input->end))->format('Ymd\THis'),
                'LAST-MODIFIED' => (new \DateTime('Now'))->format('Ymd\THis'),
                'DESCRIPTION' => $input->EventDesc,
                'SUMMARY' => $input->EventTitle,
                'UID' => $uuid,
                'SEQUENCE' => '0',
                'LOCATION' => $input->location,
                'TRANSP' => 'OPAQUE',
                'X-APPLE-TRAVEL-ADVISORY-BEHAVIOR' => 'AUTOMATIC',
                "X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-RADIUS=49.91307587029686;X-TITLE=\"" . $location . "\"" => "geo:" . $coordinates
            ];
        }

        $realVevent = $vcalendar->add('VEVENT', $vevent);

        unset($vcalendar->ORGANIZER);
        unset($vcalendar->ATTENDEE);

        if ($calendar->getGroupId() && $input->addGroupAttendees) {// add Attendees with sabre connection
            $persons = Person2group2roleP2g2rQuery::create()
                ->filterByGroupId($calendar->getGroupId())
                ->find();

            $res = $persons->count();

            if ($persons->count() > 0) {

                $realVevent->add('ORGANIZER', 'mailto:' . SessionUser::getUser()->getEmail());

                foreach ($persons as $person) {
                    $user = UserQuery::Create()->findOneByPersonId($person->getPersonId());
                    if (!empty($user)) {
                        $realVevent->add('ATTENDEE', 'mailto:' . $user->getEmail());
                    }
                }
            }
        }

        if ($input->alarm != _("NONE")) {
            $realVevent->add('VALARM', ['TRIGGER' => $input->alarm, 'DESCRIPTION' => 'Event reminder', 'ACTION' => 'DISPLAY']);
        }

        $calendarBackend->updateCalendarObject($calIDs, $event['uri'], $vcalendar->serialize());

        // Now we move to propel, to finish the put extra infos
        $eventTypeName = "";

        if ($input->eventTypeID) {
            $type = EventTypesQuery::Create()
                ->findOneById($input->eventTypeID);
            $eventTypeName = $type->getName();
        }

        $old_event->setType($input->eventTypeID);
        $old_event->setText($input->eventNotes);
        $old_event->setTypeName($eventTypeName);
        $old_event->setInActive($input->eventInActive);

        $old_event->setLocation($input->location);
        $old_event->setCoordinates($coordinates);


        // we set the groupID to manage correctly the attendees : Historical
        $old_event->setGroupId($calendar->getGroupId());

        // we first delete the old attendences
        $eventCountsOld = EventCountsQuery::create()->findByEvtcntEventid($old_event->getID());
        $eventCountsOld->delete();

        if (!empty($input->Fields)) {
            foreach ($input->Fields as $field) {
                $eventCount = new EventCounts;
                $eventCount->setEvtcntEventid($old_event->getID());
                $eventCount->setEvtcntCountid($field['countid']);
                $eventCount->setEvtcntCountname($field['name']);
                $eventCount->setEvtcntCountcount($field['value']);
                $eventCount->setEvtcntNotes($input->EventCountNotes);
                $eventCount->save();
            }
        }

        $old_event->save();

        if ($old_event->getGroupId() && $input->addGroupAttendees) {// add Attendees
            $persons = Person2group2roleP2g2rQuery::create()
                ->filterByGroupId($old_event->getGroupId())
                ->find();

            if ($persons->count() > 0) {
                foreach ($persons as $person) {
                    try {
                        if ($person->getPersonId() > 0) {
                            $eventAttent = new EventAttend();

                            $eventAttent->setEventId($old_event->getID());
                            $eventAttent->setCheckinId(SessionUser::getUser()->getPersonId());
                            $date = new DateTime('now', new DateTimeZone(SystemConfig::getValue('sTimeZone')));
                            $eventAttent->setCheckinDate($date->format('Y-m-d H:i:s'));
                            $eventAttent->setPersonId($person->getPersonId());
                            $eventAttent->save();
                        }
                    } catch (\Exception $ex) {
                        $errorMessage = $ex->getMessage();
                        //return $response->withJson(['status' => $errorMessage]);
                    }
                }

                //
                $_SESSION['Action'] = 'Add';
                $_SESSION['EID'] = $old_event->getID();
                $_SESSION['EName'] = $input->EventTitle;
                $_SESSION['EDesc'] = $input->EventDesc;
                $_SESSION['EDate'] = $date->format('Y-m-d H:i:s');

                $_SESSION['EventID'] = $old_event->getID();
            }
        }

        LoggerUtils::getAppLogger()->debug("Le calendrier " . $realVevent->serialize());

        return $response->withJson(["status" => "success", "res2" => print_r($realVevent->serialize(), true)]);
    }
}
