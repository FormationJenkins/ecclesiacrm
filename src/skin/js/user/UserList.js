$(document).ready(function () {
  var click_checkbox = false;
  
  $(".checkbox_users").click(function() {
    click_checkbox = true;
  });
  
  $(".check_all").click(function() {
    var state = this.checked;
    $(".checkbox_users").each(function() {
      $(this)[0].checked=state;
    });
  });

  
  $('#user-listing-table').on('click', 'tr', function () {
    if (click_checkbox == true) {
      click_checkbox = false;
      return;        
    }
    
    var table = $('#user-listing-table').DataTable();
    var data = table.row( this ).data();
    
    if (data != undefined) {
      click_tr = true;
      var userID = $(data[0]).data("id");
      var state = $('.checkbox_user'+userID).prop('checked');
      $('.checkbox_user'+userID).prop('checked', !state);
      click_tr = false;
    }
  });
  
  $(".changeRole").click(function() {
    var roleID = $(this).data("id");
    var roleName = this.innerText;
    var userID = -1;
    
    $(".checkbox_users").each(function() {
        if (this.checked) {
          userID = $(this).data("id");
          _val = $(this).val();

          window.CRM.APIRequest({
             method: 'POST',
             path: 'users/applyrole',
             data: JSON.stringify({"userID": userID,"roleID" : roleID})
          }).done(function(data) {
            if (data.success == true) {
               // à terminer !!!
               $('.role'+data.userID).html(data.roleName);
            }
          });
        }
    });
    
    if (userID == -1) {
      window.CRM.DisplayAlert(i18next.t("Error"),i18next.t("You've to check at least one user."));
    }
  });

  
  $("#user-listing-table").on('click','.webdavkey', function() {
    var userID = $(this).data("userid");
    
    window.CRM.APIRequest({
       method: 'POST',
       path: 'users/webdavKey',
       data: JSON.stringify({"userID": userID})
    }).done(function(data) {
      if (data.status == 'success') {
        var message = i18next.t("The WebDav Key is")+" : ";
        if (data.token != null) {
          message += data.token;
        } else {
          message += i18next.t("None");
        }
        
        message += "<br>"+i18next.t("The public WebDav Key is")+" : ";
        
        if (data.token2 != null) {
          message += data.token2;
        } else {
          message += i18next.t("None");
        }
        window.CRM.DisplayAlert(i18next.t("WebDav key"),message);
      }
    });
  });
  
  $("#user-listing-table").on('click','.lock-unlock', function() {
    var userID     = $(this).data("userid");
    var userName   = $(this).data("username");
    var button     = $(this)
    var content    = $(this).find('i');
    var lock       = content.hasClass('fa-lock');
    
    window.CRM.APIRequest({
       method: 'POST',
       path: 'users/lockunlock',
       data: JSON.stringify({"userID": userID})
    }).done(function(data) {
      if (data.success == true) {
         if (lock == false) {
           content.removeClass('fa-unlock');
           content.addClass('fa-lock');
           button.css('color','red');
           window.CRM.showGlobalMessage(i18next.t("User") + ' ' + userName + ' ' + i18next.t("is now locked") , "warning");
         } else {
           content.removeClass('fa-lock');
           content.addClass('fa-unlock');
           button.css('color','green');
           window.CRM.showGlobalMessage(i18next.t("User") + ' ' + userName + ' ' + i18next.t("is now unlocked"), "success");
         }
      }
    });
  });
  
  
  $.fn.dataTable.moment = function ( format, locale ) {
    var types = $.fn.dataTable.ext.type;

    // Add type detection
    types.detect.unshift( function ( d ) {
        // Removed true as the last parameter of the following moment
        return moment( d, format, locale ).isValid() ?
            'moment-'+format :
        null;
    });

    // Add sorting method - use an integer for the sorting
    types.order[ 'moment-'+format+'-pre' ] = function ( d ) {
       console.log("d");
        return moment ( d, format, locale, true ).unix();
    };
  };
    

  $.fn.dataTable.moment(window.CRM.datePickerformat.toUpperCase(),window.CRM.shortLocale);
  
  $("#user-listing-table").DataTable({
     "language": {
       "url": window.CRM.plugin.dataTable.language.url
     },
     responsive: true
  });

  $("#user-listing-table").on('click','.deleteUser', function() {
      var userId   = $(this).data('id');
      var userName = $(this).data('name');

      bootbox.confirm({
        title: i18next.t("User Delete Confirmation"),
        message: '<p style="color: red">' +
        i18next.t("Please confirm removal of user status from:")+'<b>' + userName + '</b><br><br>'+
        i18next.t("Be carefull, You are about to lose the home folder and the associated files, the Calendars, the Share calendars and all the events too, for")+':<b> ' + userName + '</b><br><br>'+
        i18next.t("This can't be undone")+'</p>',
        callback: function (result) {
          if (result) {
            $.ajax({
                method: "POST",
                url: window.CRM.root + "/api/users/" + userId,
                dataType: "json",
                encode: true,
                data: {"_METHOD": "DELETE"}
            }).done(function (data) {
                if (data.status == "success")
                  $("#row-"+userId).remove();
            });
          }
        }
      });
  });

  $("#user-listing-table").on('click','.restUserLoginCount', function() {
      var userId   = $(this).data('id');
      var userName = $(this).data('name');
      var parentTd = $(this).parent();
      
      bootbox.confirm({
        title: i18next.t("Action Confirmation"),
        message: '<p style="color: red">' +
        i18next.t("Please confirm reset failed login count")+": <b>" + userName + "</b></p>",
        callback: function (result) {
          if (result) {
            $.ajax({
              method: "POST",
              url: window.CRM.root + "/api/users/" + userId + "/login/reset",
              dataType: "json",
              encode: true,
            }).done(function (data) {
              if (data.status == "success")
                parentTd.html('0');
                window.CRM.showGlobalMessage(i18next.t("Reset failed login count for") + ' ' + userName + ' ' + i18next.t('done.'), "info");
            });
          }
        }
      });
  });

  $("#user-listing-table").on('click','.resetUserPassword', function() {
      var userId   = $(this).data('id');
      var userName = $(this).data('name');
    
      bootbox.confirm({
        title: i18next.t("Action Confirmation"),
        message: '<p style="color: red">' +
        i18next.t("Please confirm the password reset of this user")+": <b>" + userName + "</b></p>",
        callback: function (result) {
          if (result) {
            $.ajax({
                method: "POST",
                url: window.CRM.root + "/api/users/" + userId + "/password/reset",
                dataType: "json",
                encode: true,
            }).done(function (data) {
              if (data.status == "success")
                window.CRM.showGlobalMessage(i18next.t("Password reset for") + userName, "info");
            });
          }
        }
      });
  });
      
});

