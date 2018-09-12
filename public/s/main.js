$(function(){
    let $areas = $('#areas')
    let $subareas = $('#subareas')
    let $parking = $('#parking')
    let $age = $('#age')
    let $type = $('#type')
    let $tt = $('#tt')
    let $input = $('#input')
    let $container = $('#container')

    for (let area in areas) {
        $('<option></option>')
            .attr('value', area)
            .text(area)
            .appendTo($areas)
    }

    $areas.on('change', function(){
        $subareas.children().remove();
        $('<option></option>')
            .attr('value', '請選區域')
            .attr('disabled', 'disabled')
            .text('請選區域')
            .appendTo($subareas)

        let area = $areas.val();
        areas[area].forEach(function(subarea){
            $('<option></option>')
                .attr('value', subarea)
                .text(subarea)
                .appendTo($subareas)
        });
    })

    $areas.trigger('change')

    $('.submit').on('click', function(){
        let area = $subareas.val()
        let hasParking = $parking.val()
        let ageRange = $age.val()
        let type = $type.val()
        let target = $(this).data('target')
        let tt = $tt.val()

        $.get({
            'dataType': 'json',
            'url': '/api',
            'data': {
                area: area,
                has_parking: hasParking,
                age_range: ageRange,
                type: type,
                target: target,
            },
        })
        .done(function(d){
            if (!d.data || 0 === d.data.length) {
                notify(`${area} - ${hasParking} - ${ageRange} - ${type} 查無 ${target} 資料`)
                return;
            }

            let $e = $('<section></section>')
            renderDataTo(d, $e, target, tt)
            addCloseButton($e)
                .hide()
                .prependTo($container)
                .slideDown('fast')
        })
    })

    let renderDataTo = function(data, $e, target, tt){
        rows = transformData(data.data, target)

        let e = $e[0];
        let input = document.getElementById('input')

        let c = document.createElement('canvas');
        input.appendChild(c);
        c.setAttribute('id', 'c1')
        c.setAttribute('width', c.offsetWidth)
        c.setAttribute('height', '100')
        e.appendChild(c);

        var myChart = new Chart(c, {
            type: 'bar',
            data: {
                labels: rows.labels,
                datasets: [{
                    type: 'line',
                    label: '後 5% 平均',
                    data: rows.b5,
                    borderColor: 'rgba(33, 99, 255, 0.2)',
                    backgroundColor: 'rgba(33, 99, 255, 0.1)',
                    borderDash: [2, 2],
                    borderWidth:1,
                    pointRadius:1,
                    fill:false,
                    yAxisID: 'y-1',
                    hidden: true,
                },
                {
                    type: 'line',
                    label: '後 50% 平均',
                    data: rows.b50,
                    borderColor: 'rgba(33, 99, 255, 0.3)',
                    backgroundColor: 'rgba(33, 99, 255, 0.15)',
                    borderWidth:1,
                    pointRadius:1,
                    fill:false,
                    yAxisID: 'y-1',
                    hidden: true,
                },
                {
                    type: 'line',
                    label: '中位數',
                    data: rows.m,
                    borderColor: 'rgba(132, 99, 255, 1)',
                    backgroundColor: 'rgba(132, 99, 255, 0.3)',
                    borderWidth:1,
                    pointRadius:2,
                    fill:false,
                    yAxisID: 'y-1',
                },
                {
                    type: 'line',
                    label: '前 50% 平均',
                    data: rows.t50,
                    borderColor: 'rgba(235, 54, 162, 0.3)',
                    backgroundColor: 'rgba(235, 54, 162, 0.15)',
                    borderWidth:1,
                    pointRadius:1,
                    fill:false,
                    yAxisID: 'y-1',
                    hidden: true,
                },
                {
                    type: 'line',
                    label: '前 5% 平均',
                    data: rows.t5,
                    borderColor: 'rgba(235, 54, 162, 0.2)',
                    backgroundColor: 'rgba(235, 54, 162, 0.1)',
                    borderDash: [2, 2],
                    borderWidth:1,
                    pointRadius:1,
                    fill:false,
                    yAxisID: 'y-1',
                    hidden: true,
                },
                {
                    type: 'bar',
                    label: `總${tt}`,
                    data: ('金額' === tt) ? rows.tt : rows.count,
                    borderColor: 'rgba(200, 200, 200, 1)',
                    backgroundColor: 'rgba(200, 200, 200, 0.3)',
                    fill:true,
                    yAxisID: 'y-2'
                },
                ]
            },
            options: {
                title:{
                    display:true,
                    text: `${data.area} - ${data.has_parking} - ${data.age_range} - ${data.type} - ${target}走勢`
                },
                scales: {
                    yAxes: [{
                        id: 'y-1',
                        display: true,
                        ticks: {
                            beginAtZero:true,
                            callback: function(value, index, values){
                                let foo = ['', 'K', 'M', 'G', 'T', 'P'];
                                let idx = 0;
                                while (value >= 1000) {
                                    value /= 1000;
                                    idx++;
                                }
                                return value + foo[idx];
                            }
                        },
                        gridLines: {
                            drawOnChartArea: false,
                        },
                    },{
                        id: 'y-2',
                        display: true,
                        position: 'right',
                        ticks: {
                            beginAtZero:true,
                            callback: function(value, index, values){
                                let foo = ['', 'K', 'M', 'G', 'T', 'P'];
                                let idx = 0;
                                while (value >= 1000) {
                                    value /= 1000;
                                    idx++;
                                }
                                return value + foo[idx];
                            }
                        },
                        gridLines: {
                            drawOnChartArea: false,
                        },
                    }]
                }
            }
        });
    }

    let transformData = function (data, target){
        let yy = Math.floor(dates.startYM / 100)
        let mm = Math.floor(dates.startYM % 100)
        let yEnd = Math.floor(dates.endYM / 100)
        let mEnd = Math.floor(dates.endYM % 100)

        let labels = [], t5 = [], t50 = [], m = [], b50 = [], b5 = [], count = [], tt = [];
        while (yy < yEnd || mm < mEnd) {
            mm++
            if (mm >= 13) {
                mm = 1;yy++
            }
            let ym = yy * 100 + mm

            let u = ('單價' == target)
                ? (data[ym] ? data[ym].u : {})
                : (data[ym] ? data[ym].t : {})

            labels.push(yy+'/'+mm)
            t5.push(u.t5)
            t50.push(u.t50)
            m.push(u.m)
            b50.push(u.b50)
            b5.push(u.b5)
            count.push(data[ym] ? data[ym].c : null)
            tt.push(data[ym] ? data[ym].tt : null)
        }

        return {
            labels:labels,
            t5:t5,
            t50:t50,
            m:m,
            b50:b50,
            b5:b5,
            count:count,
            tt:tt,
        }
    }


    let addCloseButton = function($e){
        $e.append($('<div></div>')
            .addClass('close')
            .text('x')
            .on('click', function(){
                $(this).parent().slideUp("fast", function() { $(this).remove() })
            })
        )
        return $e
    }
})
