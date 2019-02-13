<?php
/*******************************************************************************
 *
 *  filename    : userlist.php
 *  last change : 2019-02-07
 *  description : displays a list of all users
 *
 *  http://www.ecclesiacrm.com/
 *  Cpoyright 2019 Philippe Logel all tight reserved not MIT
 *
 ******************************************************************************/

require $sRootDocument . '/Include/Header.php';
?>
<!-- Default box -->
<div class="box">
    <div class="box-header">
        <a href="<?= $sRootPath ?>/UserEditor.php" class="btn btn-app"><i class="fa fa-user-plus"></i><?= _('New User') ?></a>
    
      <div class="btn-group pull-right">
        <a class="btn btn-app changeRole" id="mainbuttonRole" data-id="<?= $first_roleID ?>"><i class="fa fa-arrow-circle-o-down"></i><?= _("Add Role to Selected User(s)") ?></a>
        <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown">
          <span class="caret"></span>
          <span class="sr-only">Toggle Dropdown</span>
        </button>
        <ul class="dropdown-menu" role="menu" id="AllRoles">
            <?php 
               foreach ($userRoles as $userRole) {
            ?>               
               <li> <a href="#" class="changeRole" data-id="<?= $userRole->getId() ?>"><i class="fa fa-arrow-circle-o-down"></i><?= $userRole->getName() ?></a></li>
            <?php
               }
            ?>
        </ul>
      </div>
      <div class="pull-right" style="margin-right:15px;margin-top:10px">
        <h4><?= _("Apply Roles") ?></h4>
      </div>
    </div>
</div>
<div class="box">
    <div class="box-body">
        <table class="table table-hover dt-responsive" id="user-listing-table" style="width:100%;">
            <thead>
            <tr>
                <th align="center"></th>
                <th><?= _('Actions') ?></th>
                <th><?= _('Name') ?></th>
                <th><?= _('First Name') ?></th>
                <th align="center"><?= _('User Role') ?></th>
                <th align="center"><?= _('Last Login') ?></th>
                <th align="center"><?= _('Total Logins') ?></th>
                <th align="center"><?= _('Failed Logins') ?></th>
                <th align="center"><?= _('Password') ?></th>
                <th align="center"><?= _('Status') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rsUsers as $user) { //Loop through the person?>
                <tr id="row-<?= $user->getId() ?>">
                    <td>
                      <?php 
                         if ( $user->getPersonId() != 1 && $user->getId() != $sessionUserId) {
                      ?>
                        <input type="checkbox" class="checkbox_users checkbox_user<?= $user->getPersonId()?>" name="AddRecords" data-id="<?= $user->getPersonId() ?>">
                      <?php
                         }
                      ?>
                    </td>
                    <td>
                        <?php 
                          if ( $user->getPersonId() != 1 || $user->getId() == $sessionUserId && $user->getPersonId() == 1) {
                        ?>
                            <a href="<?= $sRootPath ?>/UserEditor.php?PersonID=<?= $user->getId() ?>"><i class="fa fa-pencil"
                                                                                   aria-hidden="true"></i></a>&nbsp;&nbsp;
                        <?php
                          } else {
                        ?>
                           <span style="color:red"><?= _("Not modifiable") ?></span>
                        <?php
                          }
                        ?>
                         <?php 
                           if ( $user->getPersonId() != 1) {
                         ?>
                      
                          <a class="webdavkey" data-userid="<?= $user->getId()?>">
                             <i class="fa fa-eye" aria-hidden="true"></i>
                          </a>
                         <?php
                           }
                          ?>
                        <?php 
                          if ( $user->getId() != $sessionUserId && $user->getPersonId() != 1 ) {
                        ?>
                            <a href="#" class="deleteUser" data-id="<?= $user->getId() ?>" data-name="<?= $user->getPerson()->getFullName() ?>"><i
                                        class="fa fa-trash-o" aria-hidden="true"></i></a>
                        <?php
                          } 
                        ?>
                      </td>
                    <td>
                        <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $user->getId() ?>"> <?= $user->getPerson()->getLastName() ?></a>
                    </td>
                    <td>
                        <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $user->getId() ?>"> <?= $user->getPerson()->getFirstName() ?></a>
                    </td>
                    <td class="role<?=$user->getPersonId()?>">
                        <?php 
                          if (!is_null($user->getUserRole())) { 
                        ?>
                          <?= $user->getUserRole()->getName() ?>
                        <?php 
                          } else {
                        ?>
                           <?= _("Undefined") ?>
                        <?php
                          }
                        ?>
                    </td>
                    <td align="center"><?= $user->getLastLogin($dateFormatLong) ?></td>
                    <td align="center"><?= $user->getLoginCount() ?></td>
                    <td align="center">
                      <?php 
                        if ($user->isLocked()) {
                      ?>
                            <span class="text-red"><?= $user->getFailedLogins() ?></span>
                      <?php
                        } else {
                            echo $user->getFailedLogins();
                        }
                        if ($user->getFailedLogins() > 0) {
                      ?>
                            <a href="#" class="restUserLoginCount" data-id="<?= $user->getId() ?>" data-name="<?= $user->getPerson()->getFullName() ?>"><i
                                        class="fa fa-eraser" aria-hidden="true"></i></a>
                      <?php
                        } 
                      ?>
                    </td>
                    <td>
                        <a href="<?= $sRootPath ?>/UserPasswordChange.php?PersonID=<?= $user->getId() ?>&FromUserList=True"><i
                                    class="fa fa-wrench" aria-hidden="true"></i></a>&nbsp;&nbsp;
                        <?php 
                          if ($user->getId() != $sessionUserId && !empty($user->getEmail())) {
                        ?>
                            <a href="#" class="resetUserPassword" data-id="<?= $user->getId() ?>" data-name="<?= $user->getPerson()->getFullName() ?>"><i
                                class="fa fa-send-o" aria-hidden="true"></i></a>
                        <?php
                          } 
                        ?>
                    </td>
                    <td  align="center">
                      <?php 
                        if ( $user->getPersonId() != 1 && $user->getId() != $sessionUserId) {
                      ?>
                          <a href="#" class="lock-unlock" data-userid="<?= $user->getId()?>" data-userName = "<?= $user->getPerson()->getFullName() ?>" data-locktype="<?= ($user->getIsDeactivated() == false)?'unlock':'lock' ?>" style="color:<?= ($user->getIsDeactivated() == false)?'green':'red'?>" data-userid="<?= $user->getId()?>">
                             <i class="fa <?= ($user->getIsDeactivated() == false)?'fa-unlock':'fa-lock' ?>" aria-hidden="true"></i>
                          </a>
                      <?php
                        }
                      ?>
                    </td>
                </tr>
              <?php
                } 
              ?>
            </tbody>
        </table>
        
        <input type="checkbox" class="check_all"> <?= _("Check all") ?>
    </div>
    <!-- /.box-body -->
</div>
<!-- /.box -->

<?php
require $sRootDocument . '/Include/Footer.php';
?>

<script src="<?= $sRootPath ?>/skin/js/user/UserList.js" ></script>