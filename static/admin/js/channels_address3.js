// 纯JS省市区三级联动
var address3 = function(_cmbProvince, _cmbCity, _cmbArea, defaultProvince, defaultCity, defaultArea,disabledArr=[])
{
	var cmbProvince = document.getElementById(_cmbProvince);
	var cmbCity = document.getElementById(_cmbCity);
	var cmbArea = document.getElementById(_cmbArea);
	console.log(defaultProvince, defaultCity, defaultArea);
	function cmbSelect(cmb, str)
	{
		for(var i=0; i<cmb.options.length; i++)
		{
			if(cmb.options[i].value == str)
			{
				cmb.selectedIndex = i;
				break;
			}
		}
		try{
			layui.form.render('select');
		}catch(e){}
	}
	function cmbAddOption(cmb, str, obj)
	{
		var option = document.createElement("OPTION");
		cmb.options.add(option);
		option.innerHTML = str;
		if(str == '请选择省' || str == '请选择市' || str == '请选择地区'){
			option.value = '';
		}else{
			option.value = str;
		}
		option.obj = obj;

		//禁止选择地区
		if (Array.isArray(disabledArr) && disabledArr.length > 0) {
			if (disabledArr.includes(str)) {
				option.disabled = true;
			}
		}
	}
	
	function changeCity()
	{
		cmbArea.options.length = 0;
		if(cmbCity.selectedIndex == -1)return;
		var item = cmbCity.options[cmbCity.selectedIndex].obj;
		for(var i=0; i<item.areaList.length; i++)
		{
			cmbAddOption(cmbArea, item.areaList[i], null);
		}
		cmbSelect(cmbArea, defaultArea);
		console.log(_cmbProvince);
		console.log(_cmbCity);
		if(_cmbProvince == 'jiesuan_bank_province' && _cmbCity == 'jiesuan_bank_city'){
			try{
				getjiesuanbanklist();
			}catch(e){}
		}
	}
	function changeProvince()
	{
		cmbCity.options.length = 0;
		cmbCity.onchange = null;
		if(cmbProvince.selectedIndex == -1)return;
		var item = cmbProvince.options[cmbProvince.selectedIndex].obj;
		for(var i=0; i<item.cityList.length; i++)
		{
			cmbAddOption(cmbCity, item.cityList[i].name, item.cityList[i]);
		}
		cmbSelect(cmbCity, defaultCity);
		changeCity();
		cmbCity.onchange = changeCity;
		try{
			layui.form.on('select('+_cmbCity+')', function(data){
                changemap();
			  changeCity();
			});
		}catch(e){}
	}
	
	for(var i=0; i<provinceList.length; i++)
	{
		cmbAddOption(cmbProvince, provinceList[i].name, provinceList[i]);
	}
	cmbSelect(cmbProvince, defaultProvince);
	changeProvince();
	cmbProvince.onchange = changeProvince;
	try{
		layui.form.on('select('+_cmbProvince+')', function(data){
            changemap();
		  changeProvince();
		});
	}catch(e){}

    try{
        layui.form.on('select('+_cmbArea+')', function(data){
            changemap();
        });
    }catch(e){}
    //高德地址转经纬度
    function changemap() {
	    console.log('changeddress进入');
        try{
            AMap.plugin('AMap.Geocoder', function() {
                var province = $('#province').val();
                var city = $('#city').val();
                var district = $('#district').val();
                var address = province+city+district
                var geocoder = new AMap.Geocoder({
                    city: address // city 指定进行编码查询的城市，支持传入城市名、adcode 和 citycode
                })

                console.log(address);
                geocoder.getLocation(address, function(status, result) {
                    if (status === 'complete' && result.info === 'OK') {
                        var lnglat = result.geocodes[0].location
                        console.log(lnglat);
                        var lnglat = [lnglat.lng,lnglat.lat];
                        var rangetype = $("input[name='info[peisong_rangetype]']:checked").val();
                        console.log(rangetype);
                        if(rangetype==0 ){
                            addcircle(lnglat);
                        }else{
                            console.log(lnglat);
                            addpoly(lnglat);
                        }
                    }
                })
            })
        }catch(e){
            console.log(e);
        }
    }
}