<?php

namespace EcclesiaCRM\Dashboard;
use EcclesiaCRM\Dashboard\DashboardItemInterface;
use EcclesiaCRM\PersonQuery;

class PersonDashboardItem implements DashboardItemInterface {

  public static function getDashboardItemName() {
    return "PersonCount";
  }

  public static function getDashboardItemValue() {
     $personCount = PersonQuery::Create('per')
            ->filterByDateDeactivated(null)
            ->useFamilyQuery('fam','left join')
                ->filterByDateDeactivated(null)
            ->endUse()
            ->count();

     $data = ['personCount' => $personCount,
         'LatestPersons' => self::getLatestMembers()->toArray(),
         'UpdatedPerson' => self::getUpdatedMembers()->toArray()];

     return $data;
  }
   /**
     * Return last edited members. Only from active families selected
     * @param int $limit
     * @return array|\EcclesiaCRM\Person[]|mixed|\Propel\Runtime\ActiveRecord\ActiveRecordInterface[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public static function getUpdatedMembers($limit = 12)
    {
        return PersonQuery::create()
            ->filterByDateDeactivated(null)// GDRP, when a person is completely deactivated
            ->leftJoinWithFamily()
            ->where('Family.DateDeactivated is null')
            ->orderByDateLastEdited('DESC')
            ->limit($limit)
            ->find();
    }

    /**
     * Newly added members. Only from Active families selected
     * @param int $limit
     * @return array|\EcclesiaCRM\Person[]|mixed|\Propel\Runtime\ActiveRecord\ActiveRecordInterface[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public static function getLatestMembers($limit = 12)
    {
        return PersonQuery::create()
            ->filterByDateDeactivated(null)// GDRP, when a person is completely deactivated
            ->leftJoinWithFamily()
            ->where('Family.DateDeactivated is null')
            ->filterByDateLastEdited(null)
            ->orderByDateEntered('DESC')
            ->limit($limit)
            ->find();
    }


  public static function shouldInclude($PageName) {
    return $PageName=="/Menu.php" || $PageName == "/menu"; // this ID would be found on all pages.
  }

}
