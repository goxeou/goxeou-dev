// 纯JS省市区三级联动（微信数据）
var address3Wechat = function(_cmbProvince, _cmbCity, _cmbArea, defaultProvince, defaultCity, defaultArea, provinceListParam)
{
	var cmbProvince = document.getElementById(_cmbProvince);
	var cmbCity = document.getElementById(_cmbCity);
	var cmbArea = document.getElementById(_cmbArea);
    if(provinceListParam) provinceList = provinceList1.concat(provinceListParam);;

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
			option.value = obj.value;
		}
		option.obj = obj;
	}

	function changeCity()
	{
		cmbArea.options.length = 0;
		if(cmbCity.selectedIndex == -1)return;
		var item = cmbCity.options[cmbCity.selectedIndex].obj;
		for(var i=0; i<item.sub_list.length; i++)
		{
			cmbAddOption(cmbArea, item.sub_list[i].text, item.sub_list[i]);
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
		for(var i=0; i<item.sub_list.length; i++)
		{
			cmbAddOption(cmbCity, item.sub_list[i].text, item.sub_list[i]);
		}
		cmbSelect(cmbCity, defaultCity);
		changeCity();
		cmbCity.onchange = changeCity;
		try{
			layui.form.on('select('+_cmbCity+')', function(data){
			    changeCity();
			});
		}catch(e){}
	}

	for(var i=0; i<provinceList.length; i++)
	{
		cmbAddOption(cmbProvince, provinceList[i].text, provinceList[i]);
	}
	cmbSelect(cmbProvince, defaultProvince);
	changeProvince();
	cmbProvince.onchange = changeProvince;
	try{
		layui.form.on('select('+_cmbProvince+')', function(data){
            changeProvince();
		});
	}catch(e){}
}

var provinceList1 = [
	{
		"text":"请选择省","sub_list":[
		   {"text":"请选择市","sub_list":[{"text":"请选择地区"}]}
		]
    }
]
// var provinceList2;
// var provinceList;
// fetch("/static/area_wechat.json")
//     .then(response => response.json())
//     .then(data => {
        // 对接收到的JSON数据进行操作
        // console.log(data);
        // provinceList =  provinceList1.concat(provinceList);
        // console.log(provinceList);

    // })
