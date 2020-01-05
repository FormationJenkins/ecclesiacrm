$(document).ready(function () {
    $("#myWish").click(function showAlert() {
        $("#Menu_Banner").alert();
        window.setTimeout(function () {
            $("#Menu_Banner").alert('close');
        }, window.CRM.timeOut);
    });

    $("#Menu_Banner").fadeTo(window.CRM.timeOut, 500).slideUp(500, function () {
        $("#Menu_Banner").slideUp(500);
    });

    window.CRM.renderMailchimpLists();

    if (window.CRM.depositData && window.CRM.bEnabledFinance) {
        //---------------
        //- LINE CHART  -
        //---------------
        var lineDataRaw = window.CRM.depositData;

        var lineData = {
            labels: [],
            datasets: [
                {
                    data: [],
                    backgroundColor: [],
                    borderColor: []
                }
            ]
        };


        $(document).ready(function () {
            $.each(lineDataRaw.Deposits, function (i, val) {
                lineData.labels.push(moment(val.Date).format(window.CRM.datePickerformat.toUpperCase()));
                lineData.datasets[0].data.push(val.totalAmount);
                lineData.datasets[0].backgroundColor.push("rgba(189, 245, 109, 0.8)");
                lineData.datasets[0].borderColor.push("rgba(108, 139, 65, 0.8)");
            });

            lineData.datasets[0].label = i18next.t("Tracking");

            options = {
                responsive: true,
                maintainAspectRatio: false
            };


            var lineChartCanvas = $("#deposit-lineGraph").get(0).getContext("2d");
            var lineChart = new Chart(lineChartCanvas, {
                type: 'line',
                data: lineData,
                options: {
                    scales: {
                        yAxes: [{
                            stacked: true
                        }]
                    }
                }
            });
        });
    }
});
