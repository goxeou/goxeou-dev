var iconfontpicker = {
	iconslist : [],
	menu : [],
	addhistory : function(obj){
		var icon = $(obj).attr('class');
		var iconfontpickerdata = layui.data('iconfontpickerdata')
		var	iconfontpickerhistory = iconfontpickerdata.history;
		var newdata = [];
		var newdatastr = '';
		if(iconfontpickerhistory){
			iconfontpickerhistory = icon + ',' + iconfontpickerhistory;
			var historyarr = iconfontpickerhistory.split(',');
			var hasthis = 0;
			for(var i in historyarr){
				if(historyarr[i] != icon || i==0){
					newdata.push(historyarr[i]);
				}
			}
			var newdata2 = [];
			for(var i in newdata){
				if(i<20){
					newdata2.push(newdata[i]);
				}
			}
			newdatastr = newdata2.join(',');
		}else{
			newdatastr = icon
		}
		layui.data('iconfontpickerdata', {
		  key: 'history'
		  ,value: newdatastr
		});
	},
	show : function(){
		var THIS = this;
		$.post('/admin/iconfont.php',{op:'init'},function(res){
			THIS.menu = res.clist
			var iconslist = res.iconslist
			THIS.iconslist = iconslist
			var iconfontpickerdata = layui.data('iconfontpickerdata')
			var	iconfontpickerhistory = iconfontpickerdata.history;
			var hhistoryhtml = '';
			if(iconfontpickerhistory){
				hhistoryhtml = '最近使用: ';
				var historyarr = iconfontpickerhistory.split(',');
				for(var i in historyarr){
					if(i<15)
					hhistoryhtml += '<i class="'+historyarr[i]+'" style="font-size:20px;padding:3px;margin:2px;cursor:pointer"  iconp="1"></i>';
				}
			}

			var menu = THIS.menu;
			var html = '<div class="iconfontpicker" id="iconfontpicker">';
			html+='<div style="text-align:right;margin:0 10px;"><div style="float:left;height:38px;line-height:38px">'+hhistoryhtml+'</div><div class="layui-input-inline" style="width: 200px;"><input type="text" autocomplete="off" class="layui-input" placeholder="已收集180,187个图标"></div>';
			html+='	<button class="layui-btn layui-btn-primary" id="iconfontpickersearch"><i class="layui-icon layui-icon-search"></i></button>';
			html+='</div>'
			html+='<div class="layui-tab layui-tab-card" style="margin:5px 8px" lay-filter="iconfontpickertab">'
			html+='<ul class="layui-tab-title">'
			for(var i in menu){
				html+='<li class="'+(i==0?'layui-this':'')+'" style="min-width:0;padding:0 7px" lay-id="'+i+'">'+menu[i]['name']+'</li>'
			}
			html+='<li style="min-width:0;padding:0 7px;max-width: 70px;overflow: hidden;" lay-id="100" class="iconfontpicker-search-title">&nbsp;</li>'
			html+='</ul>'
			html+='<div class="layui-tab-content iconfontpicker_content" style="height:318px;overflow:auto;">'
			for(var i in menu){
				var iconshtml = '<ul>';
				var iconlist = iconslist[i];
				if(iconlist){
					for(var j in iconlist){
						iconshtml += '<li style="float:left;cursor:pointer;" title="'+iconlist[j]['name']+'" class="iconfontsvg" iconid="'+iconlist[j]['id']+'">'+iconlist[j]['show_svg']+'</li>';
					}
				}
				iconshtml += '</ul>';
				html+='<div class="layui-tab-item '+(i==0?'layui-show':'')+'">'+iconshtml+'</div>'
			}
			html+='<div class="layui-tab-item iconfontpicker-search-data"></div>'
			html+='</div>'
			html+='</div>';
			html+='</div>';
			$('#selecticon').html(html);
			/*
			var iconfontpickerdialog = layer.open({
				type: 1, 
				title:'选择图标',
				shadeClose:true,
				content: html,
				area: ['810px', '500px']
			});
			*/
			$('.iconfontsvg').unbind('click');
			$('.iconfontsvg').bind('click',function(){
				THIS.showsvgedit($(this).attr('iconid'));
			})
			layui.element.on('tab(iconfontpickertab)', function(){
				var layid = this.getAttribute('lay-id');
				if(layid==100) return
				if(!THIS.iconslist[layid]){
					var index = layer.load();
					$.post('/admin/iconfont.php',{op:'geticonlist',cid:THIS.menu[layid]['id']},function(res){
						layer.close(index);
						var iconlist = res.iconlist;
						THIS.iconslist[layid] = iconlist;
						var iconshtml = '<ul>';
						if(iconlist){
							for(var j in iconlist){
								iconshtml += '<li style="float:left;cursor:pointer;" title="'+iconlist[j]['name']+'" class="iconfontsvg" iconid="'+iconlist[j]['id']+'">'+iconlist[j]['show_svg']+'</li>';
							}
						}
						iconshtml += '</ul>';
						$('.iconfontpicker_content').find('.layui-show').html(iconshtml);
						$('.iconfontsvg').unbind('click');
						$('.iconfontsvg').bind('click',function(){
							THIS.showsvgedit($(this).attr('iconid'));
						})
					});
				}
			});
			$('#iconfontpickersearch').bind('click',function(){
				var keyword = $(this).parents('.iconfontpicker').eq(0).find('input').val();
				var index = layer.load();
				$.post('/admin/iconfont.php',{op:'searchiconlist',keyword:keyword},function(res){
					layer.close(index);
					var iconlist = res.iconlist;
					THIS.iconslist[100] = iconlist;
					var iconshtml = '<ul>';
					if(iconlist){
						for(var j in iconlist){
							iconshtml += '<li style="float:left;cursor:pointer;" title="'+iconlist[j]['name']+'" class="iconfontsvg" iconid="'+iconlist[j]['id']+'">'+iconlist[j]['show_svg']+'</li>';
						}
					}
					iconshtml += '</ul>';
					$('.iconfontpicker-search-title').html(keyword);
					$('.iconfontpicker-search-data').html(iconshtml);
					layui.element.tabChange('iconfontpickertab', 100);
					$('.iconfontsvg').unbind('click');
					$('.iconfontsvg').bind('click',function(){
						THIS.showsvgedit($(this).attr('iconid'));
					})
				});
			})
		})
	},
	bindiconclick : function(obj,iconfontpickerdialog){
		$('i[iconp=1]').bind('click',function(){
			iconfontpicker.addhistory(this);
			var icon = $(this).attr('class');
			$(obj).parent().find('i').attr('class',icon);
			$(obj).parent().find('input').val(icon).trigger('change');
			layer.close(iconfontpickerdialog)
		})
	},
	showsvgedit:function(iconid){
		var THIS = this;
		var index = layer.load();
		$.post('/admin/iconfont.php',{op:'geticon',id:iconid},function(res){
			var icon = res.icon
			layer.close(index);
			var html = '<div class="svgeditdiv">';
			html+='	<div class="tbackground">';
			html+=icon.show_svg;
			html+='	</div>';
			html+='	<div class="color-block-lists">';
			html+='		<div class="color-block" style="background:#d81e06;" fillcolor="#d81e06"> </div>';
			html+='		<div class="color-block" style="background:#f4ea2a;" fillcolor="#f4ea2a"> </div>';
			html+='		<div class="color-block" style="background:#1afa29;" fillcolor="#1afa29"> </div>';
			html+='		<div class="color-block" style="background:#1296db;" fillcolor="#1296db"> </div>';
			html+='		<div class="color-block" style="background:#d4237a;" fillcolor="#d4237a"> </div>';
			html+='		<div class="color-block" style="background:#ffffff;" fillcolor="#ffffff"> </div>';
			html+='		<div class="color-block" style="background:#e6e6e6;" fillcolor="#e6e6e6"> </div>';
			html+='		<div class="color-block" style="background:#dbdbdb;" fillcolor="#dbdbdb"> </div>';
			html+='		<div class="color-block" style="background:#cdcdcd;" fillcolor="#cdcdcd"> </div>';
			html+='		<div class="color-block" style="background:#bfbfbf;" fillcolor="#bfbfbf"> </div>';
			html+='		<div class="color-block" style="background:#8a8a8a;" fillcolor="#8a8a8a"> </div>';
			html+='		<div class="color-block" style="background:#707070;" fillcolor="#707070"> </div>';
			html+='		<div class="color-block" style="background:#515151;" fillcolor="#515151"> </div>';
			html+='		<div class="color-block" style="background:#2c2c2c;" fillcolor="#2c2c2c"> </div>';
			html+='	</div>';
			html+='	<div class="iconfontsetdiv">';
			html+='		<input type="text" value="#666666" class="layui-input iconfontcolorinput" style="width:70px">';
			html+='		<div class="iconfontcolorpicker"></div>';
			html+='		<input type="text" value="100" class="layui-input " style="margin-left:10px;width:60px">';
			html+='		<div style="font-size:16px;margin-left:5px">px</div>';
			html+='		<button style="margin-left:25px" class="layui-btn layui-btn-normal" id="selecticonfontConfirm">确定</button>';
			html+='	</div>';
			html+='</div>';
			var showsvgeditlayer = layer.open({
				type: 1,
				title:false,
				shadeClose:false,
				content: html,
				area: ['300px', '320px']
			});
			$('.color-block').unbind('click');
			$('.color-block').bind('click',function(){
				var fillcolor = $(this).attr('fillcolor');
				$('.svgeditdiv svg').find('path').attr('fill',fillcolor);
				$('.iconfontcolorinput').val(fillcolor);
				$('.iconfontcolorpicker .layui-colorpicker-trigger-span').css('background-color',fillcolor);
			})
			$('svg>path').unbind('click');
			$('svg>path').bind('click',function(){
				$(this).addClass('selected');
			});
			$('#selecticonfontConfirm').unbind('click');
			$('#selecticonfontConfirm').bind('click',function(){
				$this.options.callback({url:'1213'});
				$this.hide();
			});
			layui.colorpicker.render({
				elem: '.iconfontcolorpicker',
				format:'hex',
				alpha: false,
				color:$('.iconfontcolorinput').val(),
				predefine: false,
				colors: ['#ff4444','#e64340','#ec8b89','#ed3f14','#ff9900',
					'#06bf04','#179b16','#9ed99d','#19be6b',
					'#3388ff','#2b85e4','#5cadff',
					'#000000','#333333','#666666','#999999','#c9c9c9','#f7f7f8','#1c2438','#495060','#dddee1','#e9eaec'],
				change:function(color){
					//shadowcolorValue = color;
					$('.iconfontcolorinput').val(color)
				}
			});
		})
	}
}
