$(document).ready(function () {
    function renderKioskAssignment(data) {

        if(data.Accepted && window.CRM.events.futureEventsLoaded == true){
            var options ='<option value="None">None</option>';
            var currentAssignment = data.KioskAssignments[0];
            for (var i=0; i < window.CRM.events.futureEvents.length; i++)
            {
                var event = window.CRM.events.futureEvents[i];
                if (currentAssignment !== undefined && currentAssignment.EventId === event.Id)
                {
                    options += '<option selected value="1-'+event.Id+'">Event - '+event.Title+'</option>';
                }
                else
                {
                    options += '<option value="1-'+event.Id+'">Event - '+event.Title+'</option>';
                }

            }

            return '<select class="assignmentMenu form-control form-control-sm" data-kioskid="'+data.Id+'">'+ options +'</select>';
        }
        else
        {
            return "Kiosk must be accepted";
        }
    }

    $('#isNewKioskRegistrationActive').change(function() {
        if ($("#isNewKioskRegistrationActive").prop('checked')){
            window.CRM.kiosks.enableRegistration().done(function(data) {
                window.CRM.secondsLeft = moment(data.visibleUntil.date).unix() - moment().unix();
                window.CRM.discoverInterval = setInterval(function(){
                    window.CRM.secondsLeft-=1;
                    if (window.CRM.secondsLeft > 0)
                    {
                        $("#isNewKioskRegistrationActive").next(".toggle-group").children(".toggle-on").html("Active for "+window.CRM.secondsLeft+" seconds");
                    }
                    else
                    {
                        clearInterval(window.CRM.discoverInterval);
                        $('#isNewKioskRegistrationActive').bootstrapToggle('off');
                    }

                },1000)
            });
        }

    })

    window.CRM.events.getFutureEventes();

    $(document).on("change",".assignmentMenu",function(event){
        var kioskId = $(event.currentTarget).data("kioskid");
        var selected = $(event.currentTarget).val();
        window.CRM.kiosks.setAssignment(kioskId,selected);
    })

    $(document).ready(function(){
        var kioskTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/kiosks/",
                dataSrc: "KioskDevices"
            },
            columns: [
                {
                    width: 'auto',
                    title: 'Id',
                    data: 'Id',
                    searchable: false
                },
                {
                    width: 'auto',
                    title: 'Kiosk Name',
                    data: 'Name',
                },
                {
                    width: 'auto',
                    title: 'Assignment',
                    data: function (row,type,set,meta){
                        if (row.KioskAssignments.length > 0)
                        {
                            return row.KioskAssignments[0];
                        }
                        else
                        {
                            return "None";
                        }

                    },
                    render: function (data,type,full,meta)
                    {
                        return renderKioskAssignment(full);
                    }

                },
                {
                    width: 'auto',
                    title: 'Last Heartbeat',
                    data: 'LastHeartbeat',
                    render: function (data, type, full, meta) {
                        return moment(full.LastHeartbeat).fromNow();
                    }
                },
                {
                    width: 'auto',
                    title: 'Accepted',
                    data: 'Accepted',
                    render: function (data, type, full, meta) {
                        if (full.Accepted)
                        {
                            return "True";
                        }
                        else {
                            return "False";
                        }

                    }
                },
                {
                    width: 'auto',
                    title: 'Actions',
                    render: function (data, type, full, meta) {
                        buttons = "<button class='btn btn-secondary reload reloadKiosk' data-id='" + full.Id + "' >Reload</button>" +
                            " <button class='btn btn-secondary identify identifyKiosk' data-id='" + full.Id + "' >Identify</button>";
                        if(!full.Accepted){
                            buttons += "<button class='btn btn-secondary accept acceptKiosk' data-id='" + full.Id + "' >Accept</button>";
                        }
                        return buttons;
                    }
                }
            ]
        };

        $.extend(kioskTableConfig,window.CRM.plugin.dataTable);

        window.CRM.kioskDataTable = $("#KioskTable").DataTable(kioskTableConfig);

        $('body').on('click','.reloadKiosk', function(){
            var id = $(this).data('id');
            window.CRM.kiosks.reload(id);
        });

        $('body').on('click','.identifyKiosk', function(){
            var id = $(this).data('id');
            window.CRM.kiosks.identify(id);
        });

        $('body').on('click','.acceptKiosk', function(){
            var id = $(this).data('id');
            window.CRM.kiosks.accept(id);
        });

        setInterval(function(){window.CRM.kioskDataTable.ajax.reload()},5000);
    })
});
