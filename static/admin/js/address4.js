// 纯JS省市区二级联动
var address4 = function(_cmbProvince, _cmbCity, defaultProvince, defaultCity,provinceNameId= '', cityNameId=''){
	var cmbProvince = document.getElementById(_cmbProvince);
	var cmbCity = document.getElementById(_cmbCity);
	function cmbSelect(cmb, val,type =1){
		for(var i=0; i<cmb.options.length; i++){
			if(cmb.options[i].value == val){
				cmb.selectedIndex = i;
				break;
			}
		}
		try{
			layui.form.render('select');
		}catch(e){}
	}
	function cmbAddOption(cmb, str,val, obj,type=1){
		
		var option = document.createElement("OPTION");
		cmb.options.add(option);
		option.innerHTML = str;
		if(str == '省份' || str == '城市'){
			option.value = '';
		}else{
			option.value = val;
		}
		option.obj = obj;
	}
	function changeCity()
	{
		if(cmbCity.selectedIndex == -1)return;
		var item = cmbCity.options[cmbCity.selectedIndex].obj;
		if(cityNameId){
			var cityName = document.getElementById(cityNameId);
			cityName.value = item.name;
		}
	}
	function changeProvince(type = 1){
		cmbCity.options.length = 0;
		cmbCity.onchange = null;
		if(cmbProvince.selectedIndex == -1)return;
		var item = cmbProvince.options[cmbProvince.selectedIndex].obj;

		if(provinceNameId){
			var provinceName = document.getElementById(provinceNameId);
			provinceName.value = item.name;
		}
		for(var i=0; i<item.cityList.length; i++){
			if(type == 1){
				if(item.cityList[i].value == defaultCity ){
					if(cityNameId){
						var cityName = document.getElementById(cityNameId);
						cityName.value = item.cityList[i].name;
					}
				}
			}else if(type == 2){
				if(i == 0 && cityNameId){
					var cityName = document.getElementById(cityNameId);
					cityName.value = item.cityList[i].name;
				}
			}
			cmbAddOption(cmbCity, item.cityList[i].name,item.cityList[i].value,2);
		}
		cmbSelect(cmbCity, defaultCity,2);
		try{
			if(cityNameId){
				layui.form.on('select('+_cmbCity+')', function(data){
					var val = data.value;
					for(var i=0; i<item.cityList.length; i++){
						if(item.cityList[i].value == val){
							var cityName = document.getElementById(cityNameId);
							cityName.value = item.cityList[i].name;
							console.log(document.getElementById(cityNameId).value)
						}
					}
				});
			}
		}catch(e){

		}
	}

	for(var i=0; i<provinceList.length; i++){
		cmbAddOption(cmbProvince, provinceList[i].name,provinceList[i].value, provinceList[i],1);
	}
	cmbSelect(cmbProvince, defaultProvince,1);
	changeProvince(1);

	cmbProvince.onchange = changeProvince;
	try{
		layui.form.on('select('+_cmbProvince+')', function(data){
		  changeProvince(2);
		});
	}catch(e){

	}
}

var provinceList = [
{"name":"省份","value":0,"cityList":[{"name":"城市","value":0}]},
{"name":"北京","value":110000,"cityList":[{"name":"北京","value":110100}]},
{"name":"天津","value":120000,"cityList":[{"name":"天津","value":120100}]},
{"name":"河北","value":130000,"cityList":[{"name":"石家庄","value":130100},{"name":"唐山","value":130200},{"name":"秦皇岛","value":130300},{"name":"邯郸","value":130400},{"name":"邢台","value":130500},{"name":"保定","value":130600},{"name":"张家口","value":130700},{"name":"承德","value":130800},{"name":"沧州","value":130900},{"name":"廊坊","value":131000},{"name":"衡水","value":131100}]},
{"name":"山西","value":140000,"cityList":[{"name":"太原","value":140100},{"name":"大同","value":140200},{"name":"阳泉","value":140300},{"name":"长治","value":140400},{"name":"晋城","value":140500},{"name":"朔州","value":140600},{"name":"晋中","value":140700},{"name":"运城","value":140800},{"name":"忻州","value":140900},{"name":"临汾","value":141000},{"name":"吕梁","value":141100}]},
{"name":"内蒙古","value":150000,"cityList":[{"name":"呼和浩特","value":150100},{"name":"包头","value":150200},{"name":"乌海","value":150300},{"name":"赤峰","value":150400},{"name":"通辽","value":150500},{"name":"鄂尔多斯","value":150600},{"name":"呼伦贝尔","value":150700},{"name":"巴彦淖尔","value":150800},{"name":"乌兰察布","value":150900},{"name":"兴安","value":152200},{"name":"锡林郭勒","value":152500},{"name":"阿拉善","value":152900}]},
{"name":"辽宁","value":210000,"cityList":[{"name":"沈阳","value":210100},{"name":"大连","value":210200},{"name":"鞍山","value":210300},{"name":"抚顺","value":210400},{"name":"本溪","value":210500},{"name":"丹东","value":210600},{"name":"锦州","value":210700},{"name":"营口","value":210800},{"name":"阜新","value":210900},{"name":"辽阳","value":211000},{"name":"盘锦","value":211100},{"name":"铁岭","value":211200},{"name":"朝阳","value":211300},{"name":"葫芦岛","value":211400}]},
{"name":"吉林","value":220000,"cityList":[{"name":"长春","value":220100},{"name":"吉林","value":220200},{"name":"四平","value":220300},{"name":"辽源","value":220400},{"name":"通化","value":220500},{"name":"白山","value":220600},{"name":"松原","value":220700},{"name":"白城","value":220800},{"name":"延边朝鲜族","value":222400}]},
{"name":"黑龙江","value":230000,"cityList":[{"name":"哈尔滨","value":230100},{"name":"齐齐哈尔","value":230200},{"name":"鸡西","value":230300},{"name":"鹤岗","value":230400},{"name":"双鸭山","value":230500},{"name":"大庆","value":230600},{"name":"伊春","value":230700},{"name":"佳木斯","value":230800},{"name":"七台河","value":230900},{"name":"牡丹江","value":231000},{"name":"黑河","value":231100},{"name":"绥化","value":231200},{"name":"大兴安岭","value":232700}]},
{"name":"上海","value":310000,"cityList":[{"name":"上海","value":310100}]},
{"name":"江苏","value":320000,"cityList":[{"name":"南京","value":320100},{"name":"无锡","value":320200},{"name":"徐州","value":320300},{"name":"常州","value":320400},{"name":"苏州","value":320500},{"name":"昆山","value":320583},{"name":"南通","value":320600},{"name":"连云港","value":320700},{"name":"淮安","value":320800},{"name":"盐城","value":320900},{"name":"扬州","value":321000},{"name":"镇江","value":321100},{"name":"泰州","value":321200},{"name":"宿迁","value":321300},{"name":"昆山","value":320583},{"name":"常熟","value":320581},{"name":"宜兴","value":320282}]},
{"name":"浙江","value":330000,"cityList":[{"name":"杭州","value":330100},{"name":"宁波","value":330200},{"name":"温州","value":330300},{"name":"嘉兴","value":330400},{"name":"湖州","value":330500},{"name":"绍兴","value":330600},{"name":"金华","value":330700},{"name":"衢州","value":330800},{"name":"舟山","value":330900},{"name":"台州","value":331000},{"name":"丽水","value":331100},{"name":"义乌","value":330782}]},
{"name":"安徽","value":340000,"cityList":[{"name":"合肥","value":340100},{"name":"芜湖","value":340200},{"name":"蚌埠","value":340300},{"name":"淮南","value":340400},{"name":"马鞍山","value":340500},{"name":"淮北","value":340600},{"name":"铜陵","value":340700},{"name":"安庆","value":340800},{"name":"黄山","value":341000},{"name":"滁州","value":341100},{"name":"阜阳","value":341200},{"name":"宿州","value":341300},{"name":"六安","value":341500},{"name":"亳州","value":341600},{"name":"池州","value":341700},{"name":"宣城","value":341800}]},
{"name":"福建","value":350000,"cityList":[{"name":"福州","value":350100},{"name":"厦门","value":350200},{"name":"莆田","value":350300},{"name":"三明","value":350400},{"name":"泉州","value":350500},{"name":"漳州","value":350600},{"name":"南平","value":350700},{"name":"龙岩","value":350800},{"name":"宁德","value":350900}]},
{"name":"江西","value":360000,"cityList":[{"name":"南昌","value":360100},{"name":"景德镇","value":360200},{"name":"萍乡","value":360300},{"name":"九江","value":360400},{"name":"新余","value":360500},{"name":"鹰潭","value":360600},{"name":"赣州","value":360700},{"name":"吉安","value":360800},{"name":"宜春","value":360900},{"name":"抚州","value":361000},{"name":"上饶","value":361100}]},
{"name":"山东","value":370000,"cityList":[{"name":"济南","value":370100},{"name":"青岛","value":370200},{"name":"淄博","value":370300},{"name":"枣庄","value":370400},{"name":"东营","value":370500},{"name":"烟台","value":370600},{"name":"潍坊","value":370700},{"name":"寿光","value":370783},{"name":"济宁","value":370800},{"name":"泰安","value":370900},{"name":"威海","value":371000},{"name":"日照","value":371100},{"name":"莱芜","value":371200},{"name":"临沂","value":371300},{"name":"德州","value":371400},{"name":"聊城","value":371500},{"name":"滨州","value":371600},{"name":"菏泽","value":371700}]},
{"name":"河南","value":410000,"cityList":[{"name":"郑州","value":410100},{"name":"开封","value":410200},{"name":"洛阳","value":410300},{"name":"平顶山","value":410400},{"name":"安阳","value":410500},{"name":"鹤壁","value":410600},{"name":"新乡","value":410700},{"name":"焦作","value":410800},{"name":"济源","value":410881},{"name":"濮阳","value":410900},{"name":"许昌","value":411000},{"name":"漯河","value":411100},{"name":"三门峡","value":411200},{"name":"南阳","value":411300},{"name":"商丘","value":411400},{"name":"信阳","value":411500},{"name":"周口","value":411600},{"name":"驻马店","value":411700}]},
{"name":"湖北","value":420000,"cityList":[{"name":"武汉","value":420100},{"name":"黄石","value":420200},{"name":"十堰","value":420300},{"name":"宜昌","value":420500},{"name":"襄阳","value":420600},{"name":"鄂州","value":420700},{"name":"荆门","value":420800},{"name":"孝感","value":420900},{"name":"荆州","value":421000},{"name":"黄冈","value":421100},{"name":"咸宁","value":421200},{"name":"随州","value":421300},{"name":"恩施","value":422800},{"name":"仙桃","value":429004},{"name":"潜江","value":429005},{"name":"天门","value":429006},{"name":"神农架","value":429021}]},
{"name":"湖南","value":430000,"cityList":[{"name":"长沙","value":430100},{"name":"株洲","value":430200},{"name":"湘潭","value":430300},{"name":"衡阳","value":430400},{"name":"邵阳","value":430500},{"name":"岳阳","value":430600},{"name":"常德","value":430700},{"name":"张家界","value":430800},{"name":"益阳","value":430900},{"name":"沅江","value":430981},{"name":"郴州","value":431000},{"name":"永州","value":431100},{"name":"怀化","value":431200},{"name":"娄底","value":431300},{"name":"湘西","value":433100},{"name":"宁乡","value":430182}]},
{"name":"广东","value":440000,"cityList":[{"name":"广州","value":440100},{"name":"韶关","value":440200},{"name":"深圳","value":440300},{"name":"珠海","value":440400},{"name":"汕头","value":440500},{"name":"佛山","value":440600},{"name":"江门","value":440700},{"name":"湛江","value":440800},{"name":"茂名","value":440900},{"name":"肇庆","value":441200},{"name":"惠州","value":441300},{"name":"梅州","value":441400},{"name":"汕尾","value":441500},{"name":"河源","value":441600},{"name":"阳江","value":441700},{"name":"清远","value":441800},{"name":"东莞","value":441900},{"name":"中山","value":442000},{"name":"东沙","value":442101},{"name":"潮州","value":445100},{"name":"揭阳","value":445200},{"name":"云浮","value":445300}]},
{"name":"广西","value":450000,"cityList":[{"name":"南宁","value":450100},{"name":"柳州","value":450200},{"name":"桂林","value":450300},{"name":"梧州","value":450400},{"name":"北海","value":450500},{"name":"防城港","value":450600},{"name":"钦州","value":450700},{"name":"贵港","value":450800},{"name":"玉林","value":450900},{"name":"百色","value":451000},{"name":"贺州","value":451100},{"name":"河池","value":451200},{"name":"来宾","value":451300},{"name":"崇左","value":451400}]},
{"name":"海南","value":460000,"cityList":[{"name":"临高","value":469028},{"name":"海口","value":460100},{"name":"三亚","value":460200},{"name":"三沙","value":460300},{"name":"五指山","value":469001},{"name":"琼海","value":469002},{"name":"儋州","value":469003},{"name":"文昌","value":469005},{"name":"万宁","value":469006},{"name":"东方","value":469007},{"name":"定安","value":469025},{"name":"屯昌","value":469026},{"name":"澄迈","value":469027},{"name":"白沙","value":469030},{"name":"昌江","value":469031},{"name":"乐东","value":469033},{"name":"陵水","value":469034},{"name":"保亭","value":469035},{"name":"琼中","value":469036}]},
{"name":"重庆","value":500000,"cityList":[{"name":"重庆","value":500100}]},
{"name":"四川","value":510000,"cityList":[{"name":"成都","value":510100},{"name":"自贡","value":510300},{"name":"攀枝花","value":510400},{"name":"泸州","value":510500},{"name":"德阳","value":510600},{"name":"绵阳","value":510700},{"name":"广元","value":510800},{"name":"遂宁","value":510900},{"name":"内江","value":511000},{"name":"乐山","value":511100},{"name":"南充","value":511300},{"name":"眉山","value":511400},{"name":"宜宾","value":511500},{"name":"广安","value":511600},{"name":"达州","value":511700},{"name":"雅安","value":511800},{"name":"巴中","value":511900},{"name":"资阳","value":512000},{"name":"阿坝","value":513200},{"name":"甘孜","value":513300},{"name":"凉山","value":513400}]},
{"name":"贵州","value":520000,"cityList":[{"name":"贵阳","value":520100},{"name":"六盘水","value":520200},{"name":"盘州","value":520281},{"name":"遵义","value":520300},{"name":"安顺","value":520400},{"name":"铜仁","value":522200},{"name":"凯里","value":522601},{"name":"黔西南","value":522300},{"name":"毕节","value":522400},{"name":"都匀","value":522701},{"name":"黔东南","value":522600},{"name":"黔南","value":522700}]},
{"name":"云南","value":530000,"cityList":[{"name":"昆明","value":530100},{"name":"曲靖","value":530300},{"name":"玉溪","value":530400},{"name":"保山","value":530500},{"name":"昭通","value":530600},{"name":"丽江","value":530700},{"name":"普洱","value":530800},{"name":"临沧","value":530900},{"name":"楚雄","value":532300},{"name":"红河","value":532500},{"name":"文山","value":532600},{"name":"西双版纳","value":532800},{"name":"大理","value":532900},{"name":"德宏","value":533100},{"name":"怒江","value":533300},{"name":"迪庆","value":533400}]},
{"name":"西藏","value":540000,"cityList":[{"name":"拉萨","value":540100},{"name":"昌都","value":542100},{"name":"山南","value":542200},{"name":"日喀则","value":542300},{"name":"那曲","value":542400},{"name":"阿里","value":542500},{"name":"林芝","value":542600}]},
{"name":"陕西","value":610000,"cityList":[{"name":"西安","value":610100},{"name":"铜川","value":610200},{"name":"宝鸡","value":610300},{"name":"咸阳","value":610400},{"name":"渭南","value":610500},{"name":"延安","value":610600},{"name":"汉中","value":610700},{"name":"榆林","value":610800},{"name":"安康","value":610900},{"name":"商洛","value":611000}]},
{"name":"甘肃","value":620000,"cityList":[{"name":"兰州","value":620100},{"name":"嘉峪关","value":620200},{"name":"金昌","value":620300},{"name":"白银","value":620400},{"name":"天水","value":620500},{"name":"武威","value":620600},{"name":"张掖","value":620700},{"name":"平凉","value":620800},{"name":"酒泉","value":620900},{"name":"庆阳","value":621000},{"name":"定西","value":621100},{"name":"陇南","value":621200},{"name":"临夏","value":622900},{"name":"甘南","value":623000}]},
{"name":"青海","value":630000,"cityList":[{"name":"西宁","value":630100},{"name":"海东","value":632100},{"name":"海北","value":632200},{"name":"黄南","value":632300},{"name":"海南藏族","value":632500},{"name":"果洛","value":632600},{"name":"玉树","value":632700},{"name":"海西","value":632800}]},
{"name":"宁夏","value":640000,"cityList":[{"name":"银川","value":640100},{"name":"石嘴山","value":640200},{"name":"吴忠","value":640300},{"name":"固原","value":640400},{"name":"中卫","value":640500}]},
{"name":"新疆","value":650000,"cityList":[{"name":"乌鲁木齐","value":650100},{"name":"克拉玛依","value":650200},{"name":"吐鲁番","value":652100},{"name":"哈密","value":652200},{"name":"昌吉","value":652300},{"name":"博尔塔拉","value":652700},{"name":"巴音郭楞","value":652800},{"name":"阿克苏","value":652900},{"name":"克孜勒苏柯尔克孜","value":653000},{"name":"喀什","value":653100},{"name":"和田","value":653200},{"name":"伊犁哈萨克自治州","value":654000},{"name":"塔城","value":654200},{"name":"阿勒泰","value":654300},{"name":"石河子","value":659001},{"name":"阿拉尔","value":659002},{"name":"图木舒克","value":659003},{"name":"五家渠","value":659004}]},
{"name":"台湾","value":710000,"cityList":[{"name":"台北","value":710100},{"name":"高雄","value":710200},{"name":"台南","value":710300},{"name":"台中","value":710400},{"name":"金门","value":710500},{"name":"南投","value":710600},{"name":"基隆","value":710700},{"name":"新竹","value":710800},{"name":"嘉义","value":710900},{"name":"新北","value":711100},{"name":"宜兰","value":711200},{"name":"新竹","value":711300},{"name":"桃园","value":711400},{"name":"苗栗","value":711500},{"name":"彰化","value":711700},{"name":"嘉义","value":711900},{"name":"云林","value":712100},{"name":"屏东","value":712400},{"name":"台东","value":712500},{"name":"花莲","value":712600},{"name":"澎湖","value":712700},{"name":"连江","value":712800}]},
{"name":"香港","value":810000,"cityList":[{"name":"香港岛","value":810100},{"name":"九龙","value":810200},{"name":"新界","value":810300}]},
{"name":"澳门","value":820000,"cityList":[{"name":"澳门半岛","value":820100},{"name":"离岛","value":820200}]}
];