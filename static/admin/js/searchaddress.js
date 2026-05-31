var searchService, map,markers = [],marker = null;
var jd = $('#mapjd').val();
var wd = $('#mapwd').val();
window.onload=function(){
    function init(){
        var center = new qq.maps.LatLng(wd,jd);
        map = new qq.maps.Map(document.getElementById("l-map"), {
            center: center,      // 地图的中心地理坐标。
            zoom:15              // 地图的中心地理坐标。
        });
        var marker = new qq.maps.Marker({ position:center, map: map});
        markers.push(marker);
        //绑定单击事件添加参数
        qq.maps.event.addListener(map, 'click', function(event) {
            console.log(event);
            clearOverlays(markers);
            $("#mapjd").val(event.latLng.getLng());
            $("#mapwd").val(event.latLng.getLat());
            var marker = new qq.maps.Marker({ position: new qq.maps.LatLng(event.latLng.getLat(), event.latLng.getLng()) , map: map
            });
            markers.push(marker);
        });
        //实例化自动完成
        //设置Poi检索服务，用于本地检索、周边检索
        searchService = new qq.maps.SearchService({
            //设置搜索范围为北京
            location: center.lat+"," +center.lng,
            //设置搜索页码为1
            // pageIndex: 1,
            //设置每页的结果数为5
            // pageCapacity: 5,
            //设置展现查询结构到infoDIV上
            // panel: document.getElementById('infoDiv'),
            //设置动扩大检索区域。默认值true，会自动检索指定城市以外区域。
            autoExtend: true,
            //检索成功的回调函数
            complete: function(results) {
                //设置回调函数参数
                var pois = results.detail.pois;
                var infoWin = new qq.maps.InfoWindow({
                    map: map
                });
                var latlngBounds = new qq.maps.LatLngBounds();
                for (var i = 0, l = pois.length; i < l; i++) {
                    var poi = pois[i];
                    //扩展边界范围，用来包含搜索到的Poi点
                    latlngBounds.extend(poi.latLng);
                    (function(n) {
                        var marker = new qq.maps.Marker({
                            map: map
                        });
                        marker.setPosition(pois[n].latLng);
                        marker.setTitle(pois[n].name);
                        markers.push(marker);
                        qq.maps.event.addListener(marker, 'click', function() {
                            $("#mapjd").val(pois[n].latLng.lng);
                            $("#mapwd").val(pois[n].latLng.lat);
                            infoWin.setPosition(pois[n].latLng);
                            $("#address").val(pois[n].name);
                        });
                    })(i);
                }
                //调整地图视野
                map.fitBounds(latlngBounds);
                $("#mapjd").val(pois[0].latLng.lng);
                $("#mapwd").val(pois[0].latLng.lat);
            },
            //若服务请求失败，则运行以下函数
            error: function() {
                dialog("未搜索到有效地址");
            }
        });

    }
    init();
}
//清除地图上的marker
function clearOverlays(overlays) {
    var overlay;
    while (overlay = overlays.pop()) {
        overlay.setMap(null);
    }
}
//搜索地图
function searchMap(address) {
    var currentDomain = window.location.protocol + "//" + window.location.host;
    $.post(currentDomain + "/?s=map/searchFormMap", {keywords:address,lat:wd,lng:jd},function(results){
        if(results.status == 1) {
            var pois = results.data;
            if(pois.length == 0){
                layer.msg("未找到相关地址！");
                return ;
            }
            var infoWin = new qq.maps.InfoWindow({
                map: map
            });
            var latlngBounds = new qq.maps.LatLngBounds();
            for (var i = 0, l = pois.length; i < l; i++) {
                var poi = pois[i];
                //扩展边界范围，用来包含搜索到的Poi点
                var location = new qq.maps.LatLng(poi.location.lat, poi.location.lng)
                latlngBounds.extend(location);
                (function(n) {
                    var LatLng = new qq.maps.LatLng(pois[n].location.lat,pois[n].location.lng)
                    var marker = new qq.maps.Marker({
                        map: map,
                        position: LatLng
                    });
                    marker.setTitle(i + 1);
                    markers.push(marker);
                    qq.maps.event.addListener(marker, 'click', function() {
                        $("#mapjd").val(pois[n].location.lng);
                        $("#mapwd").val(pois[n].location.lat);
                        infoWin.setPosition(LatLng);
                        $("#address").val(pois[n].title);
                    });
                })(i);
            }
            //调整地图视野
            map.fitBounds(latlngBounds);
            $("#mapjd").val(pois[0].location.lng);
            $("#mapwd").val(pois[0].location.lat);
        }else{
            dialog(results.msg);
        }
    })
}

$("#searchbtn").click(function(){
    var province = $('#province').val();
    var city = $('#city').val();
    var address = $("#address").val();
    if(address == ""){
        dialog("请输地址！");
        return ;
    }
    clearOverlays(markers);
    //根据输入的关键字在搜索范围内检索
    searchMap(province+city+address);
    // searchService.search(province+city+address);
    // $('.search-keywords').hide();
});

$('#address').on('focusin',function () {
    $('.search-keywords').show();
})

$('#address').on("input propertychange",function() {
    var keyword = $(this).val();
    var region = $('#city').val();
    searchKeywords(keyword,region)
})
layui.form.on('select(city)',function (e){
    console.log(e);
})
function searchKeywords(keyword,region) {
    $.ajax({
        async: true,
        url: 'https://apis.map.qq.com/ws/place/v1/suggestion?key=ABLBZ-4BIKU-GFTVB-BK7IK-OLQ35-QCBFF&output=jsonp&page_index=1&page_size=10&policy=11' +
            '&region='+region+'&keyword='+keyword,
        type: 'GET',
        dataType: 'jsonp',
        timeout: 30000,
        success: function (res) {
            console.log(res);
            console.log(res.data);
            var data = res.data;
            var html = '';
            if(!data || data.length<=0){
                $('.search-keywords').hide();
                return;
            }
            for (var i = 0; i < data.length; i++) {
                var result = data[i];
                var value = result.title+'-'+'('+result.province+'-'+result.city+')';
                html += '<input type="text" class="layui-input map-search-res" value="'+value+'" readonly onclick="changekeyword(\''+result.title+'\',\''+result.city+'\')">';
            }
            html += '<div style="width: 500px;background: #e5dddd;height: 44px;line-height: 44px;color: #2a6496">' +
                '<div style="width: 50%;text-align: left;float: left;padding-left: 2%;">单击选择地点</div>' +
                '<div style="width: 40px;text-align: right;float: right;text-align: center;" onclick="closesearch()"><a href="javascript:void(0)">X</a></div>' +
                '</div>';
            $('.search-keywords').show();
            $('.search-keywords').html(html)

        },
        error: function () {

        },

    })
}
function changekeyword(title,city) {
    $("#address").val(title);
    $('.search-keywords').hide();
    searchService.setLocation(city);
    region = city;
    searchService.search(title);
}
function closesearch(){
    console.log('clode');
    $('.search-keywords').hide();
}