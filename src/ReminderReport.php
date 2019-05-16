<?php
/*******************************************************************************
 *
 *  filename    : ReminderReport.php
 *  last change : 2003-09-03
 *  description : form to invoke user access report
  *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use EcclesiaCRM\dto\SystemConfig;
use EcclesiaCRM\Utils\InputUtils;
use EcclesiaCRM\Utils\RedirectUtils;
use EcclesiaCRM\Utils\MiscUtils;
use EcclesiaCRM\SessionUser;


// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!SessionUser::getUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly')) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

// Set the page title and include HTML header
$sPageTitle = _('Pledge Reminder Report');
require 'Include/Header.php';

// Is this the second pass?
if (isset($_POST['Submit'])) {
    $iFYID = InputUtils::LegacyFilterInput($_POST['FYID'], 'int');
    $_SESSION['idefaultFY'] = $iFYID;
    RedirectUtils::Redirect('Reports/ReminderReport.php?FYID='.$_SESSION['idefaultFY']);
} else {
    $iFYID = $_SESSION['idefaultFY'];
}

?>

<div class="box box-body">
    <form class="form-horizontal" method="post" action="Reports/ReminderReport.php">
        <div class="form-group">
            <label class="control-label col-sm-2" for="FYID"><?= _('Fiscal Year') ?>:</label>
            <div class="col-sm-2">
                <?php MiscUtils::PrintFYIDSelect($iFYID, 'FYID') ?>
            </div>
        </div>

        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-8">
                <button type="submit" class="btn btn-primary" name="Submit"><?= _('Create Report') ?></button>
                <button type="button" class="btn btn-default" name="Cancel"
                        onclick="javascript:document.location='Menu.php';"><?= _('Cancel') ?></button>
            </div>
        </div>

    </form>
</div>
<?php
require 'Include/Footer.php';
?>
