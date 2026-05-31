var thishref = window.location.href
var arrurl = thishref.split("//")
var arrurl2 = arrurl[1].split("/");
var thismodule = 'index.php?s=';//arrurl2[1];
var fileUploaderLayer;
fileUploader = {
	'supports' : ['browser','upload','remoteAudio', 'remoteImage','wximage','wxvoice','wxvideo','selecticon'],
	'defaultoptions' : {
		debug : false,
		global : false,
		callback : null, // 回调方法
		type : 'image', // 上传组件类型 
		direct : false, // 效果, 是否选择即返回, 单图可用.
		multi : false, // 返回结果是 object 还是 Array
		maxwidth:1080,
		maxheight:10080,
		dest_dir : '', // 自定义上传目录
		tabs : { // 选项卡, remote
			'browser' : 'active',
			//'upload': '',
			'selecticon' : ''
		}
	},
	'options' : {}, // 当前配置项
	
	'show' : function(callback, options){
		this.init(callback, options);
	},
	'hide' : function(){
		layer.close(fileUploaderLayer);
	},
	'uploader' : {},
	'modalobj' : null,
	'images' : [],
	'init' : function(callback, options) {
		$this = this;
		this.options = $.extend({}, this.defaultoptions, options);
		this.options.callback = callback;
		if(options.tabs){
			this.options.tabs = {};
			if(typeof(options.tabs.remote) != 'undefined'){
				if(this.options.type == 'image'){
					options.tabs['remoteImage'] = options.tabs.remote;
				} else {
					options.tabs['remoteAudio'] = options.tabs.remote;
				}
				delete options.tabs.remote;
			}
			for(i in options.tabs){
				if($.inArray(i, $this.supports) > -1){
					$this.options.tabs[i] = options.tabs[i];
				}
			}
		}
		if(this.options.global){
			this.options.global = 'global';
		} else {
			this.options.global = '';
		}
		
		if(this.options.other_param){
			var other_param = this.options.other_param;
		}else{
			var other_param = '';
		}
		document.cookie = "__fileupload_type="+ escape (this.options.type);
		document.cookie = "__fileupload_dest_dir="+ escape (this.options.dest_dir);
		document.cookie = "__fileupload_global="+ escape (this.options.global);
		if ($('#modal-fileUploader').length > 0 ) {
			$('#modal-fileUploader').remove()
		}
		$(document.body).append('<div id="modal-fileUploader" style="display:none"></div>');
		this.modalobj = $('#modal-fileUploader');
		this.modalobj.append(
			'	<div class="fileUploader-content">\n'+
			'		<div class="layui-tab layui-tab-brief" style="margin:0" lay-filter="webuploaderfilter">'+
			'			<ul class="layui-tab-title webuploadertitle">'+
			'			</ul>'+
			'			<div class="layui-tab-content webuploadercontent" style="padding:0"></div>\n' +
			'		</div>'+
			'	</div>\n'
		);
		for(i in this.options.tabs){
			eval("this.init_"+i+"('"+other_param+"');");
		}
		fileUploaderLayer = layer.open({
			title: false,
			type: 1,area: ['900px', '620px'],
			content: this.modalobj,
			shadeClose:true,
			cancel:function(){}
		});
		this.modalobj.find('.cancelbtn').click(function(){
			layer.close(fileUploaderLayer);
		})
		
		layui.element.on('tab(webuploaderfilter)', function(){
			if(this.getAttribute('lay-id') == 'selection'){
				iconsvgpicker.show();
			}
		});
	},
	
	'init_crawler' : function() {
		$this = this;
		
		if(this.modalobj.find('#crawler').length == 0){
			this.modalobj.find('.layui-tab-title.webuploadertitle').append('<li class="'+($this.options.tabs.crawler == 'active'?'layui-this':'')+'">提取网络文件</li>');
			this.modalobj.find('.layui-tab-content.webuploadertitle').append(this.template().crawler);
		}
		
		this.modalobj.find('#btnFetch').off('click');
		this.modalobj.find('#btnFetch').click(function(){
			var url = $('#crawlerUrl').val();
			if (url.length == 0){
				alert('请输入网络文件地址.');
			}
			if (url.length > 0){
				$.post('?s=upload/fetch', {'url':url}, function(data){
					var result = $.parseJSON(data);
					if(result.message){
						alert(result.message);
					} else {
						$this.images = [];
						$this.images.push(result);
						
						if ($this.options.direct == true){
							$this.modalobj.find('.crawler').find('button.confirmbtn').click();
						}
						
						if($this.options.type == 'image'){
							$this.modalobj.find('.crawler').find('.crawler-img-sizeinfo').text(result.width+'x'+result.height);
						} else {
							$this.modalobj.find('.crawler').find('.crawler-img-sizeinfo').text(result.size);
						}
						$this.modalobj.find('.crawler').find('.crawler-img').css("background-image","url("+result.url+")");
					}
				});
			}
		});
		
		this.modalobj.find('#crawler').find('button.confirmbtn').off('click');
		this.modalobj.find('#crawler').find('button.confirmbtn').on('click', function(){
			if ($this.images.length > 0){
				if($.isFunction($this.options.callback)){
					if($this.options.multi){
						$this.options.callback($this.images);
					} else {
						$this.options.callback($this.images[0]);
					}
					$this.hide();
				}
			} else {
				alert('未选择任何文件.');
			}
		});
	},
	'init_browser' : function(other_param='') {
		$this = this;

		if(this.modalobj.find('#browser').length == 0){
			this.modalobj.find('.layui-tab-title.webuploadertitle').append('<li class="'+($this.options.tabs.browser == 'active'?'layui-this':'')+'">上传图片</li>');
			this.modalobj.find('.layui-tab-content.webuploadercontent').append(this.template().browser);
			this.browser('',other_param);
		}

		this.modalobj.find('#browser').find('button.confirmbtn').off('click');
		this.modalobj.find('#browser').find('button.confirmbtn').on('click', function(){
			if(other_param == 'ffmpeg'){
				var ret = $('#ffmpeg').val()?$('#ffmpeg').val():'';
			}else{
				var ret = '';
			}
			console.log(ret);
			if ($this.images.length > 0){
				//$this.test('browser');
				if($.isFunction($this.options.callback)){
					if($this.options.multi){
						$this.options.callback($this.images,ret);
					} else {
						$this.options.callback($this.images[0],ret);
					}
					$this.hide();
				}
			} else {
				alert('未选择任何文件.');
			}
		});
	},
	'init_selecticon' : function() {
		$this = this;
		if(this.modalobj.find('#selecticon').length == 0){
			this.modalobj.find('.layui-tab-title.webuploadertitle').append('<li lay-id="selection" class="'+($this.options.tabs.selecticon == 'active'?'layui-this':'')+'">选择图标</li>');
			this.modalobj.find('.layui-tab-content.webuploadercontent').append(this.template().selecticon);

			//iconsvgpicker.show();
		}else{
			//iconsvgpicker.show();
		}
	},
	'init_posterbg' : function() {
		$this = this;
		if(this.modalobj.find('#posterbg').length == 0){
			this.modalobj.find('.layui-tab-title.webuploadertitle').append('<li class="'+($this.options.tabs.posterbg == 'active'?'layui-this':'')+'">选择背景</li>');
			this.modalobj.find('.layui-tab-content.webuploadercontent').append(this.template().posterbg);
			$.post('?s=upload/posterbg',{},function(res){
				var phtml = '';
				var imgs = res.list
				for(var i=0;i<imgs.length;i++){
					phtml+='<img src="'+imgs[i]+'" style="margin:3px;width:150px;box-shadow:6px 6px 3px #ccc;cursor:pointer" class="posterbg_img"/>';
				}
				$('#posterbg').append(phtml);
				$this.modalobj.find('.posterbg_img').off('click');
				$this.modalobj.find('.posterbg_img').on('click', function(){
					var imgurl = $(this).attr('src');
					if($.isFunction($this.options.callback)){
						if($this.options.multi){
							$this.options.callback([{url:imgurl}]);
						} else {
							$this.options.callback({url:imgurl});
						}
						$this.hide();
					}
				});
			})
		}else{
			this.modalobj.find('.posterbg_img').off('click');
			this.modalobj.find('.posterbg_img').on('click', function(){
				var imgurl = $(this).attr('src');
				if($.isFunction($this.options.callback)){
					if($this.options.multi){
						$this.options.callback([{url:imgurl}]);
					} else {
						$this.options.callback({url:imgurl});
					}
					$this.hide();
				}
			});
		}
	},
	'init_wximage' : function(imagepage) {
		console.log('init_wximage')
		if(!imagepage) var imagepage =1;
		$this = this;
		if(this.modalobj.find('#wximage').length == 0){
			this.modalobj.find('.layui-tab-title.webuploadertitle').append('<li class="'+($this.options.tabs.wximage == 'active'?'layui-this':'')+'">微信素材</li>');
			this.modalobj.find('.layui-tab-content.webuploadercontent').append(this.template().wximage);
		}
		$.post('?s=upload/material',{type:'image',page:imagepage},function(res){
			var phtml = '<div>';
			var datalist = res.data
			var imagepagecount = Math.ceil(res.count/18);
			for(var i=0;i<datalist.length;i++){
				phtml+='<img src="'+datalist[i].url+'" media_id="'+datalist[i].media_id+'" style="margin:3px;width:100px;cursor:pointer" class="wximage_img"/>';
			}
			phtml += '</div>';

			if(imagepagecount > 1){
				phtml += '<div style="margin-top:10px">';
				if(imagepage > 1){
				phtml += '<a href="javascript:void(0)" class="wximageprevpage">上一页</a>&nbsp;&nbsp;&nbsp;'
				}
				phtml += imagepage+' / '+imagepagecount;
				if(imagepage < imagepagecount){
				phtml += '&nbsp;&nbsp;&nbsp;<a href="javascript:void(0)" class="wximagenextpage">下一页</a>';
				}
				phtml += '</div>';
			}
			$('#wximage').html(phtml);
			$this.modalobj.find('.wximage_img').off('click');
			$this.modalobj.find('.wximage_img').on('click', function(){
				var imgurl = $(this).attr('src');
				var media_id = $(this).attr('media_id');
				if($.isFunction($this.options.callback)){
					if($this.options.multi){
						$this.options.callback([{url:imgurl,media_id:media_id}]);
					} else {
						$this.options.callback({url:imgurl,media_id:media_id});
					}
					$this.hide();
				}
			});
			$this.modalobj.find('.wximageprevpage').off('click');
			$this.modalobj.find('.wximageprevpage').on('click', function(){
				$this.init_wximage(imagepage-1);
			});
			$this.modalobj.find('.wximagenextpage').off('click');
			$this.modalobj.find('.wximagenextpage').on('click', function(){
				console.log(imagepage+1)
				$this.init_wximage(imagepage+1);
			});
		})
	},
	'init_wxvoice' : function(voicepage) {
		console.log('init_wxvoice')
		if(!voicepage) var voicepage =1;
		$this = this;
		if(this.modalobj.find('#wxvoice').length == 0){
			this.modalobj.find('.layui-tab-title.webuploadertitle').append('<li class="'+($this.options.tabs.wxvoice == 'active'?'layui-this':'')+'">微信素材</li>');
			this.modalobj.find('.layui-tab-content.webuploadercontent').append(this.template().wxvoice);
		}
		$.post('?s=upload/material',{type:'voice',page:voicepage},function(res){
			var phtml = '<div>';
			var datalist = res.data
			var voicepagecount = Math.ceil(res.count/18);
			for(var i=0;i<datalist.length;i++){
				phtml+='<div class="wxvoice_img" data_url="'+datalist[i].url+'" media_id="'+datalist[i].media_id+'" style="cursor:pointer;margin:3px;width:100px;height:100px;display:inline-block;border:1px solid #f5f5f5;text-align:center">';
				phtml+='	<img src="/static/admin/webuploader/images/media.jpg" style="width:50px;height:50px;margin-top:20px"/>';
				phtml+='	<div>'+datalist[i].name+'</div>';
				phtml+='</div>';
			}
			phtml += '</div>';

			if(voicepagecount > 1){
				phtml += '<div style="margin-top:10px">';
				if(voicepage > 1){
				phtml += '<a href="javascript:void(0)" class="wxvoiceprevpage">上一页</a>&nbsp;&nbsp;&nbsp;'
				}
				phtml += voicepage+' / '+voicepagecount;
				if(voicepage < voicepagecount){
				phtml += '&nbsp;&nbsp;&nbsp;<a href="javascript:void(0)" class="wxvoicenextpage">下一页</a>';
				}
				phtml += '</div>';
			}
			$('#wxvoice').html(phtml);
			$this.modalobj.find('.wxvoice_img').off('click');
			$this.modalobj.find('.wxvoice_img').on('click', function(){
				//var imgurl = $(this).attr('src');
				var media_id = $(this).attr('media_id');
				var data_url = $(this).attr('data_url');
				if($.isFunction($this.options.callback)){
					if($this.options.multi){
						$this.options.callback([{url:data_url,media_id:media_id}]);
					} else {
						$this.options.callback({url:data_url,media_id:media_id});
					}
					$this.hide();
				}
			});
			$this.modalobj.find('.wxvoiceprevpage').off('click');
			$this.modalobj.find('.wxvoiceprevpage').on('click', function(){
				$this.init_wxvoice(voicepage-1);
			});
			$this.modalobj.find('.wxvoicenextpage').off('click');
			$this.modalobj.find('.wxvoicenextpage').on('click', function(){
				console.log(voicepage+1)
				$this.init_wxvoice(voicepage+1);
			});
		})
	},
	'init_wxvideo' : function(videopage) {
		console.log('init_wxvideo')
		if(!videopage) var videopage =1;
		$this = this;
		if(this.modalobj.find('#wxvideo').length == 0){
			this.modalobj.find('.layui-tab-title.webuploadertitle').append('<li class="'+($this.options.tabs.wxvideo == 'active'?'layui-this':'')+'">微信素材</li>');
			this.modalobj.find('.layui-tab-content.webuploadercontent').append(this.template().wxvideo);
		}
		$.post('?s=upload/material',{type:'video',page:videopage},function(res){
			var phtml = '<div>';
			var datalist = res.data
			var videopagecount = Math.ceil(res.count/18);
			for(var i=0;i<datalist.length;i++){
				phtml+='<div class="wxvideo_img" data_url="'+datalist[i].url+'" media_id="'+datalist[i].media_id+'" style="cursor:pointer;margin:3px;width:100px;height:100px;display:inline-block;border:1px solid #f5f5f5;text-align:center">';
				phtml+='	<img src="/static/admin/webuploader/images/media.jpg" style="width:50px;height:50px;margin-top:20px"/>';
				phtml+='	<div>'+datalist[i].name+'</div>';
				phtml+='</div>';
			}
			phtml += '</div>';

			if(videopagecount > 1){
				phtml += '<div style="margin-top:10px">';
				if(videopage > 1){
				phtml += '<a href="javascript:void(0)" class="wxvideoprevpage">上一页</a>&nbsp;&nbsp;&nbsp;'
				}
				phtml += videopage+' / '+videopagecount;
				if(videopage < videopagecount){
				phtml += '&nbsp;&nbsp;&nbsp;<a href="javascript:void(0)" class="wxvideonextpage">下一页</a>';
				}
				phtml += '</div>';
			}
			$('#wxvideo').html(phtml);
			$this.modalobj.find('.wxvideo_img').off('click');
			$this.modalobj.find('.wxvideo_img').on('click', function(){
				//var imgurl = $(this).attr('src');
				var media_id = $(this).attr('media_id');
				var data_url = $(this).attr('data_url');
				if($.isFunction($this.options.callback)){
					if($this.options.multi){
						$this.options.callback([{url:data_url,media_id:media_id}]);
					} else {
						$this.options.callback({url:data_url,media_id:media_id});
					}
					$this.hide();
				}
			});
			$this.modalobj.find('.wxvideoprevpage').off('click');
			$this.modalobj.find('.wxvideoprevpage').on('click', function(){
				$this.init_wxvideo(videopage-1);
			});
			$this.modalobj.find('.wxvideonextpage').off('click');
			$this.modalobj.find('.wxvideonextpage').on('click', function(){
				console.log(videopage+1)
				$this.init_wxvideo(videopage+1);
			});
		})
	},
	'browserfiles' : {},
	'browser' : function(param='',other_param='') {
		var browser_gid = '-1';
		var browser_pagenum = 1;
		var browser_keyword = '';
		var browser_sort = '2';
		setCookie('browser_gid','-1');
		$this = this;
		var $browser = $this.modalobj.find('#browser');
		$.get('?s=upload/group', {}, function(res){
			var dataInfo = res;
			var html = '<ul>';
			html += '<li type="1" data-id="-1" class="browser-group-item active">全部</li>';
			html += '<li type="1" data-id="0" class="browser-group-item">未分组</li>';
			for(var i in res.data){
				if(res.data[i].children.length){
					html += '<div style="position: relative;"><i style="position: absolute;top: 8px;left: 5px;font-size:15px" class="fa fa-caret-right childShow"></i><i style="position: absolute;top: 8px;left: 2px;font-size:15px;display:none" class="fa fa-caret-down childHide"></i>' + '<li style="padding: 0 10px 0 30px;" type="2" child="1" data-id="'+res.data[i].id+'" class="browser-group-item">'+res.data[i].name+'</li>';
					html += "<div style='display:none' class='browser-set'  data-id="+res.data[i].id+">";
					for(var j in res.data[i].children){
						html += '<li style="padding: 0 10px 0 45px;" type="3" data-children="0" data-id="'+res.data[i].children[j].id+'" class="browser-group-item">'+res.data[i].children[j].name+'</li>';
					}
					html += "</div>"+ '</div>';
				}else{
					html += '<li type="2" data-id="'+res.data[i].id+'" class="browser-group-item">'+res.data[i].name+'</li>';
				}
			}
			html += '</ul>';
			html += '<div class="browser-group-op" style="display:flex;flex-wrap: wrap;line-height:1">';
			html += '	<div class="browser-group-add">新建分组&nbsp;&nbsp;&nbsp;</div>';
			html += '	<div class="browser-group-children" data-gid="" style="display:none">新建子分组&nbsp;&nbsp;&nbsp;</div>';
			html += '	<div class="browser-group-edit" data-gid="" style="display:none">修改&nbsp;&nbsp;&nbsp;</div>';
			html += '	<div class="browser-group-del" data-gid="" style="display:none">删除</div>';
			html += '</div>';
			$browser.find('.browser_group').empty();
			$browser.find('.browser_group').append(html);
			browser_pagenum = 1;
			browserfile(other_param);
			// 绑定事件

			$browser.find('.childShow').off('click');
			$browser.find('.childShow').on('click', function(){
				$(this).hide();
				$(this).siblings(".childHide").show();
				$(this).siblings(".browser-set").show();
			});

			$browser.find('.childHide').off('click');
			$browser.find('.childHide').on('click', function(){
				$(this).hide();
				$(this).siblings(".childShow").show();
				$(this).siblings(".browser-set").hide();
			});


			$browser.find('.browser-group-item').off('click');
			$browser.find('.browser-group-item').on('click', function(){
				$('.browser-group-item').removeClass('active');
				$(this).addClass('active');

				browser_gid = $(this).attr('data-id');
				setCookie('browser_gid',browser_gid);

				var type = $(this).attr('type');

				var index = '';

				if($(this).attr('child')=='1'){
					index = $(this).parent().index()
				}else{
					index = $(this).index()
				}

				if(type == '1'){
					$browser.find('.browser-group-children').attr('data-gid',browser_gid).attr('data-index',index).hide();
					$browser.find('.browser-group-edit').attr('data-gid',browser_gid).attr('data-index',index).hide();
					$browser.find('.browser-group-del').attr('data-gid',browser_gid).attr('data-index',index).hide();
				}

				if(type == '2'){
					$browser.find('.browser-group-children').attr('data-gid',browser_gid).attr('data-index',index).show();
					$browser.find('.browser-group-edit').attr('data-gid',browser_gid).attr('data-index',index).show();
					$browser.find('.browser-group-del').attr('data-gid',browser_gid).attr('data-index',index).show();
				}

				if(type == '3'){
					$browser.find('.browser-group-children').attr('data-gid',browser_gid).attr('data-index',$(this).index()).hide();
					$browser.find('.browser-group-edit').attr('data-gid',browser_gid).attr('data-index',$(this).index()).show();
					$browser.find('.browser-group-del').attr('data-gid',browser_gid).attr('data-index',$(this).index()).show();
				}

				browser_pagenum = 1;
				browserfile(other_param);
			});
			$browser.find('.browser-group-add').off('click');
			$browser.find('.browser-group-add').on('click', function(){
				var html = '';
				html+='	<div class="layui-form-item" style="margin-top:40px;margin-right:20px;">';
				html+='		<label class="layui-form-label" style="width:80px">分组名称：</label>';
				html+='		<div class="layui-input-inline" style="width:200px">';
				html+='			<input type="text" id="browser_groupname" class="layui-input"/>';
				html+='		</div>';
				html+='	</div>';
				var browsergroupaddLayer = layer.open({type:1,area:['400px','200px'],title:false,content:html,shadeClose:true,btn: ['确定', '取消'],
					yes:function(){
						if($('#browser_groupname').val() == ''){
							dialog('请输入分组名称');return;
						}
						var index = layer.load();
						$.post('?s=upload/addgroup',{name:$('#browser_groupname').val()},function(res){
							layer.close(index);
							dialog(res.msg,res.status);
							layer.close(browsergroupaddLayer);
							$browser.find('.browser_group>ul').append('<li type="2" data-id="'+res.data.id+'" class="browser-group-item">'+res.data.name+'</li>');
							let item = {
								name:res.data.name,
								id:res.data.id,
								children:[]
							}
							dataInfo.data.push(item)
							// 绑定事件
							
							$browser.find('.browser-group-item').off('click');
							$browser.find('.browser-group-item').on('click', function(){
								$('.browser-group-item').removeClass('active');
								$(this).addClass('active');

								browser_gid = $(this).attr('data-id');
								setCookie('browser_gid',browser_gid);

								var type = $(this).attr('type');

								var index = '';

								if($(this).attr('child')=='1'){
									index = $(this).parent().index()
								}else{
									index = $(this).index()
								}

								if(type == '1'){
									$browser.find('.browser-group-children').attr('data-gid',browser_gid).attr('data-index',index).hide();
									$browser.find('.browser-group-edit').attr('data-gid',browser_gid).attr('data-index',index).hide();
									$browser.find('.browser-group-del').attr('data-gid',browser_gid).attr('data-index',index).hide();
								}

								if(type == '2'){
									$browser.find('.browser-group-children').attr('data-gid',browser_gid).attr('data-index',index).show();
									$browser.find('.browser-group-edit').attr('data-gid',browser_gid).attr('data-index',index).show();
									$browser.find('.browser-group-del').attr('data-gid',browser_gid).attr('data-index',index).show();
								}

								if(type == '3'){
									$browser.find('.browser-group-children').attr('data-gid',browser_gid).attr('data-index',$(this).index()).hide();
									$browser.find('.browser-group-edit').attr('data-gid',browser_gid).attr('data-index',$(this).index()).show();
									$browser.find('.browser-group-del').attr('data-gid',browser_gid).attr('data-index',$(this).index()).show();
								}

								browser_pagenum = 1;
								browserfile();
							});
							$browser.find(".browser-group-item[data-id='"+res.data.id+"']").click();
						})
					}
				})
			});
			$browser.find('.browser-group-children').off('click');
			$browser.find('.browser-group-children').on('click', function(){
				var gid = $(this).attr('data-gid');
				var dataIndex = $(this).attr('data-index');
				var html = '';
				html+='	<div class="layui-form-item" style="margin-top:40px;margin-right:20px;">';
				html+='		<label class="layui-form-label" style="width:100px">子分组名称：</label>';
				html+='		<div class="layui-input-inline" style="width:200px">';
				html+='			<input type="text" id="browser_groupname" class="layui-input"/>';
				html+='		</div>';
				html+='	</div>';
				var browsergroupaddLayer = layer.open({type:1,area:['400px','200px'],title:false,content:html,shadeClose:true,btn: ['确定', '取消'],
					yes:function(){
						if($('#browser_groupname').val() == ''){
							dialog('请输入分组名称');return;
						}
						var index = layer.load();
						$.post('?s=upload/addgroup',{name:$('#browser_groupname').val(),pid:gid},function(res){
							layer.close(index);
							dialog(res.msg,res.status);
							layer.close(browsergroupaddLayer);
							let item = {
								id:res.data.id,
								name:res.data.name
							}
							let aryIndex = $.extend(true,[],dataIndex) - 2;
							dataInfo.data[aryIndex].children.push(item)

							var setHtml = '';
							setHtml += '<div style="position: relative;"><i style="position: absolute;top: 8px;left: 5px;font-size:15px;display:none" class="fa fa-caret-right childShow"></i><i style="position: absolute;top: 8px;left: 2px;font-size:15px" class="fa fa-caret-down childHide"></i>' + '<li style="padding: 0 10px 0 30px;" type="2" child="1" data-id="'+dataInfo.data[aryIndex].id+'" class="browser-group-item">'+dataInfo.data[aryIndex].name+'</li>';
							setHtml += "<div class='browser-set'  data-id="+dataInfo.data[aryIndex].id+">";
							for(var j in dataInfo.data[aryIndex].children){
								setHtml += '<li style="padding: 0 10px 0 45px;" type="3" data-children="0" data-id="'+dataInfo.data[aryIndex].children[j].id+'" class="browser-group-item">'+dataInfo.data[aryIndex].children[j].name+'</li>';
							}

							$browser.find('.browser_group>ul').children().eq(dataIndex).replaceWith(setHtml)

							$browser.find('.childShow').off('click');
							$browser.find('.childShow').on('click', function(){
								$(this).hide();
								$(this).siblings(".childHide").show();
								$(this).siblings(".browser-set").show();
							});

							$browser.find('.childHide').off('click');
							$browser.find('.childHide').on('click', function(){
								$(this).hide();
								$(this).siblings(".childShow").show();
								$(this).siblings(".browser-set").hide();
							});

							$browser.find('.browser-group-item').off('click');
							$browser.find('.browser-group-item').on('click', function(){
								$('.browser-group-item').removeClass('active');
								$(this).addClass('active');

								browser_gid = $(this).attr('data-id');
								setCookie('browser_gid',browser_gid);

								var type = $(this).attr('type');

								var index = '';

								if($(this).attr('child')=='1'){
									index = $(this).parent().index()
								}else{
									index = $(this).index()
								}

								if(type == '1'){
									$browser.find('.browser-group-children').attr('data-gid',browser_gid).attr('data-index',index).hide();
									$browser.find('.browser-group-edit').attr('data-gid',browser_gid).attr('data-index',index).hide();
									$browser.find('.browser-group-del').attr('data-gid',browser_gid).attr('data-index',index).hide();
								}

								if(type == '2'){
									$browser.find('.browser-group-children').attr('data-gid',browser_gid).attr('data-index',index).show();
									$browser.find('.browser-group-edit').attr('data-gid',browser_gid).attr('data-index',index).show();
									$browser.find('.browser-group-del').attr('data-gid',browser_gid).attr('data-index',index).show();
								}

								if(type == '3'){
									$browser.find('.browser-group-children').attr('data-gid',browser_gid).attr('data-index',$(this).index()).hide();
									$browser.find('.browser-group-edit').attr('data-gid',browser_gid).attr('data-index',$(this).index()).show();
									$browser.find('.browser-group-del').attr('data-gid',browser_gid).attr('data-index',$(this).index()).show();
								}

								browser_pagenum = 1;
								browserfile();
							});
							$browser.find(".browser-group-item[data-id='"+res.data.id+"']").click();
						})
					}
				})
			});
			$browser.find('.browser-group-edit').off('click');
			$browser.find('.browser-group-edit').on('click', function(){
				var gid = $(this).attr('data-gid');
				var html = '';
				html+='	<div class="layui-form-item" style="margin-top:40px;margin-right:20px;">';
				html+='		<label class="layui-form-label" style="width:80px">分组名称：</label>';
				html+='		<div class="layui-input-inline" style="width:200px">';
				html+='			<input type="text" id="browser_groupname" class="layui-input" value="'+$browser.find('.browser-group-item[data-id='+gid+']').text()+'"/>';
				html+='		</div>';
				html+='	</div>';
				var browsergroupaddLayer = layer.open({type:1,area:['400px','200px'],title:false,content:html,shadeClose:true,btn: ['确定', '取消'],
					yes:function(){
						if($('#browser_groupname').val() == ''){
							dialog('请输入分组名称');return;
						}
						var index = layer.load();
						$.post('?s=upload/editgroup',{gid:gid,name:$('#browser_groupname').val()},function(res){
							layer.close(index);
							dialog(res.msg,res.status);
							layer.close(browsergroupaddLayer);
							$browser.find(".browser-group-item[data-id='"+gid+"']").html($('#browser_groupname').val());
						})
					}
				})
			});
			$browser.find('.browser-group-del').off('click');
			$browser.find('.browser-group-del').on('click', function(){
				var gid = $(this).attr('data-gid');
				layer.confirm('确定要删除吗?',function(){
					var index = layer.load();
					$.post('?s=upload/delgroup',{gid:gid},function(res){
						let cutInfo = dataInfo.data;
						for(let i=0;i<cutInfo.length;i++){
							if(cutInfo[i].id == gid){
								if($browser.find(".browser-group-item[data-id='"+gid+"']").prev().children(".browser-group-item").attr("child")=='1'){
									$browser.find(".browser-group-item[data-id='"+gid+"']").prev().children(".browser-group-item").click();
								}else{
									$browser.find(".browser-group-item[data-id='"+gid+"']").prev().click();
								}
								$browser.find(".browser-group-item[data-id='"+gid+"']").remove();
							}
							if(cutInfo[i]){
								for(let j=0;j<cutInfo[i].children.length;j++){
									if(cutInfo[i].children[j].id == gid){
										cutInfo[i].children.splice(j,1);
										if(cutInfo[i].children.length){//有子数据
											$browser.find(".browser-group-item[data-id='"+gid+"']").prev().click();
											$browser.find(".browser-group-item[data-id='"+gid+"']").remove();
										}else{//无数据
											var setHtml = '<li style="padding: 0 10px 0 15px;" type="2" data-id="'+cutInfo[i].id+'" class="browser-group-item">'+cutInfo[i].name+'</li>';
											$browser.find('.browser_group>ul').children().eq(i+2).replaceWith(setHtml);
											$browser.find('.browser-group-item').off('click');
											$browser.find('.browser-group-item').on('click', function(){
												$('.browser-group-item').removeClass('active');
												$(this).addClass('active');
												browser_gid = $(this).attr('data-id');
												setCookie('browser_gid',browser_gid);
	
												var type = $(this).attr('type');
	
												var index = '';
	
												if($(this).attr('child')=='1'){
													index = $(this).parent().index()
												}else{
													index = $(this).index()
												}
	
												if(type == '1'){
													$browser.find('.browser-group-children').attr('data-gid',browser_gid).attr('data-index',index).hide();
													$browser.find('.browser-group-edit').attr('data-gid',browser_gid).attr('data-index',index).hide();
													$browser.find('.browser-group-del').attr('data-gid',browser_gid).attr('data-index',index).hide();
												}
	
												if(type == '2'){
													$browser.find('.browser-group-children').attr('data-gid',browser_gid).attr('data-index',index).show();
													$browser.find('.browser-group-edit').attr('data-gid',browser_gid).attr('data-index',index).show();
													$browser.find('.browser-group-del').attr('data-gid',browser_gid).attr('data-index',index).show();
												}
	
												if(type == '3'){
													$browser.find('.browser-group-children').attr('data-gid',browser_gid).attr('data-index',$(this).index()).hide();
													$browser.find('.browser-group-edit').attr('data-gid',browser_gid).attr('data-index',$(this).index()).show();
													$browser.find('.browser-group-del').attr('data-gid',browser_gid).attr('data-index',$(this).index()).show();
												}
	
												browser_pagenum = 1;
												browserfile();
											});
											$browser.find(".browser-group-item[data-id='"+cutInfo[i].id+"']").click();
										}
									}
								}
							}
						}
						layer.close(index);
						dialog(res.msg,res.status);
					})
				})
			})
			browserupadload(param,other_param);
		})
		function setCookie(key,value){
			var oDate=new Date();
			oDate.setDate(oDate.getDate()+10000);
			document.cookie=key+"="+value+"; expires="+oDate.toDateString();
		}
		function browserupadload(param='',other_param=''){
			if(other_param  == 'ffmpeg'){
				var go_url = '?s=upload/index/other_param/'+other_param;
			}else{
				var go_url = '?s=upload/index/issave/1/other_param/'+other_param;
			}
			//上传文件
			var index;
			var infoArr = [{},{},{},{},{},{},{},{},{}];
			layui.upload.render({
				elem: '.browser-upload',
				accept:'file',
				multiple:true,
				number:9,
				url: go_url,
				before:function(res){
					index = layer.load();
				},
				done: function(res,idx){
					console.log(idx);
					layer.close(index);
					if(res.status==0){
						dialog(res.msg,0);
					}else{
						idx = parseInt(idx.split('-')[1]);
						infoArr[idx] = res;
						console.log(res.url)
						/*
						if(res.info.extension == 'jpg' || res.info.extension == 'jpeg' || res.info.extension == 'png' || res.info.extension == 'gif' || res.info.extension == 'bmp' || res.info.extension == 'webp'){
							html = 
							'<div class="img-item img-item-selected" title="'+res.info.width+'*'+res.info.height+'" attachid="'+res.info.id+'" attachment="'+res.info.url+'">'+
							'	<div class="btnClose"><a href="javascript:;"><i class="fa fa-times"></i></a></div>'+
							'	<div class="img-container" style="background-image: url(\''+res.info.url+'\');">'+
							'		<div class="img-meta">'+res.info.name+'</div>'+
							'		<div class="select-status"><span></span></div>'+
							'	</div>'+
							'</div>';
						} else {
							html = 
							'<div class="img-item img-item-selected" title="" attachid="'+res.info.id+'" attachment="'+res.info.url+'">'+
							'	<div class="btnClose"><a href="javascript:;"><i class="fa fa-times"></i></a></div>'+
							'	<div class="img-container" style="background-image: url(\'/static/admin/webuploader/images/media.jpg\');">'+
							'		<div class="img-meta">'+res.info.name+'</div>'+
							'		<div class="select-status"><span></span></div>'+
							'	</div>'+
							'</div>';
						}
						if(other_param == 'ffmpeg'){
							html += "<input type='hidden' id='ffmpeg' value='"+res.ffmpeg_img+"'/>"; 
						}
						$browser.find('.file-browser .file-browser-filelist').prepend(html);
						$this.images = [];
						$.each($('.img-item-selected'), function(idx, ele){
							$this.images.push({'url':$(ele).attr('attachment')});
						});
						browserbind();
						*/
					}
				},
				allDone:function(obj){
					var html = '';
					for(var i in infoArr){
						var res = infoArr[i];
						if(!res.info) continue;
						if(res.info.extension == 'jpg' || res.info.extension == 'jpeg' || res.info.extension == 'png' || res.info.extension == 'gif' || res.info.extension == 'bmp' || res.info.extension == 'webp'){
							html += 
							'<div class="img-item img-item-selected" title="'+res.info.width+'*'+res.info.height+'" attachid="'+res.info.id+'" attachment="'+res.info.url+'">'+
							'	<div class="btnClose"><a href="javascript:;"><i class="fa fa-times"></i></a></div>'+
							'	<div class="img-container" style="background-image: url(\''+res.info.url+'\');">'+
							'		<div class="img-meta">'+res.info.name+'</div>'+
							'		<div class="select-status"><span></span></div>'+
							'	</div>'+
							'</div>';
						} else {
							html += 
							'<div class="img-item img-item-selected" title="" attachid="'+res.info.id+'" attachment="'+res.info.url+'">'+
							'	<div class="btnClose"><a href="javascript:;"><i class="fa fa-times"></i></a></div>'+
							'	<div class="img-container" style="background-image: url(\'/static/admin/webuploader/images/media.jpg\');">'+
							'		<div class="img-meta">'+res.info.name+'</div>'+
							'		<div class="select-status"><span></span></div>'+
							'	</div>'+
							'</div>';
						}
						if(other_param == 'ffmpeg'){
							html += "<input type='hidden' id='ffmpeg' value='"+res.ffmpeg_img+"'/>"; 
						}
					}
					$browser.find('.file-browser .file-browser-filelist').prepend(html);
					$this.images = [];
					$.each($('.img-item-selected'), function(idx, ele){
						$this.images.push({'url':$(ele).attr('attachment')});
					});
					browserbind(other_param);
				}
			});
		}
		function browserfile(other_param=''){
			//初始化数据
			var indexload = layer.load();
			$.get('?s=upload/browser',{gid:browser_gid,pagenum:browser_pagenum,keyword:browser_keyword,sort:browser_sort,other_param:other_param}, function(res){
				layer.close(indexload);
				if (res['msg']){
					alert(res.msg);return;
				}
				//$this.browserfiles = {};
				var html = '';
				var files = res.data;
				for(i in files){
					var file = files[i];
					if(file.type == 'jpg' || file.type == 'jpeg' || file.type == 'png' || file.type == 'gif' || file.type == 'bmp' || file.type == 'webp'){
						html += 
						'<div class="img-item" title="'+file.width+'*'+file.height+'" attachid="'+file.id+'" attachment="'+file.url+'">'+
						'	<div class="btnClose"><a href="javascript:;"><i class="fa fa-times"></i></a></div>'+
						'	<div class="img-container" style="background-image: url(\''+file.url+'\');">'+
						'		<div class="img-meta">'+file.name+'</div>'+
						'		<div class="select-status"><span></span></div>'+
						'	</div>'+
						'</div>';
					} else {
						html += 
						'<div class="img-item" title="" attachid="'+file.id+'" attachment="'+file.url+'">'+
						'	<div class="btnClose"><a href="javascript:;"><i class="fa fa-times"></i></a></div>'+
						'	<div class="img-container" style="background-image: url(\'/static/admin/webuploader/images/media.jpg\');">'+
						'		<div class="img-meta">'+file.name+'</div>'+
						'		<div class="select-status"><span></span></div>'+
						'	</div>'+
						'</div>';
					}
				}
				$browser.find('.file-browser-filelist').empty();
				$browser.find('.file-browser-filelist').append(html);
				var totalpage = res.totalpage
				if(totalpage > 1){
					html = '<div style="color:#666">';
					if(browser_pagenum > 1){
						html += '<a href="javascript:void(0)" class="browserprevpage" style="color:#428bca">上一页</a>&nbsp;&nbsp;&nbsp;'
					}
					html += browser_pagenum+' / '+totalpage;
					if(browser_pagenum < totalpage){
						html += '&nbsp;&nbsp;&nbsp;<a href="javascript:void(0)" class="browsernextpage" style="color:#428bca">下一页</a>';
					}
					html += '</div>';
					$browser.find('.file-browser-bottomop-pageinfo').empty();
					$browser.find('.file-browser-bottomop-pageinfo').append(html);
				}else{
					$browser.find('.file-browser-bottomop-pageinfo').empty();
				}
				browserbind(other_param);
			});
		}
		
		function browserbind(other_param){
			$browser.find('.browser-file-checkall').prop('checked',false);
			$browser.find('.btnClose').off('click');
			$browser.find('.btnClose').on('click', function(event){
				var $this_ = this;
				layer.confirm("确定要删除文件吗？删除后不可恢复！",function(){
					var attachid = $($this_).parent().attr('attachid');
					$.post('?s=upload/deletefile', {'ids':attachid}, function(res){
						dialog(res.msg,res.status);
						browserfile(other_param);
					});
				})
				event.stopPropagation();
			});
			
			$browser.find('.img-item').off('click');
			$browser.find('.img-item').on('click', function(){
				$(this).toggleClass('img-item-selected');
				$this.images = [];
				$.each($('.img-item-selected'), function(idx, ele){
					$this.images.push({'url':$(ele).attr('attachment')});
				});
				$browser.find('.browser-info').text('已选中 '+$this.images.length+' 个文件.');
			});
			$this.modalobj.find('.browserprevpage').off('click');
			$this.modalobj.find('.browserprevpage').on('click', function(){
				browser_pagenum--;
				browserfile();
			});
			$this.modalobj.find('.browsernextpage').off('click');
			$this.modalobj.find('.browsernextpage').on('click', function(){
				browser_pagenum++
				browserfile();
			});
			$browser.find('#browser-search-btn').off('click');
			$browser.find('#browser-search-btn').on('click',function(){
				browser_keyword = $browser.find('#browser-search-keyword').val();
				browser_pagenum = 1;
				browserfile();
			});
			$browser.find('#browser-search-sort').off('change');
			$browser.find('#browser-search-sort').on('change',function(){
				browser_sort = $browser.find('#browser-search-sort').val();
				browser_pagenum = 1;
				browserfile();
			});
			$browser.find('.browser-file-checkall').off('change');
			$browser.find('.browser-file-checkall').on('change',function(){
				if($(this).is(':checked')){
					$browser.find('.img-item').addClass('img-item-selected');
				}else{
					$browser.find('.img-item').removeClass('img-item-selected');
				}
			})
			$browser.find('.file-browser-bottomop-op3').off('click');
			$browser.find('.file-browser-bottomop-op3').on('click', function(){
				var images_selected = [];
				$.each($('.img-item-selected'), function(idx, ele){
					images_selected.push($(ele).attr('attachid'));
				});
				if(images_selected.length == 0){
					dialog('请选择要移动的文件');return;
				}
				layer.confirm('确定要删除文件吗？删除后不可恢复！',function(){
					var index = layer.load();
					$.post('?s=upload/deletefile',{ids:images_selected.join(',')},function(res){
						layer.close(index);
						dialog(res.msg,res.status);
						browserfile();
					});
				})
			});
			$browser.find('.file-browser-bottomop-op2').off('click');
			$browser.find('.file-browser-bottomop-op2').on('click', function(){
				var images_selected = [];
				$.each($('.img-item-selected'), function(idx, ele){
					images_selected.push($(ele).attr('attachid'));
				});
				if(images_selected.length == 0){
					dialog('请选择要移动的文件');return;
				}
				$.post('?s=upload/group', {}, function(res){
					var optionshtml = '<option value="0" selected>未分组</option>';
					for(var i in res.data){
						optionshtml += '<option value="'+res.data[i].id+'" class="browser-group-item">'+res.data[i].name+'</li>';
						for(var j in res.data[i].children){
							optionshtml += '<option value="'+res.data[i].children[j].id+'" class="browser-group-item">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'+res.data[i].children[j].name+'</li>';
						}
					}
					var html = '';
					html+='	<div class="layui-form-item" style="margin-top:40px;margin-right:20px;">';
					html+='		<label class="layui-form-label" style="width:80px">选择分组：</label>';
					html+='		<div class="layui-input-inline" style="width:200px">';
					html+='			<select id="browser_remove_groupid" name="browser_remove_groupid" style="width:150px;height:40px;line-height:40px;border:1px solid #C9C9C9;border-radius:2px;color:#555;padding:0 6px;">'+optionshtml+'</select>';
					html+='		</div>';
					html+='	</div>';
					var browsergroupaddLayer = layer.open({type:1,area:['400px','200px'],title:false,content:html,shadeClose:true,btn: ['确定', '取消'],
						yes:function(){
							var index = layer.load();
							$.post('?s=upload/changegroup',{ids:images_selected.join(','),gid:$('#browser_remove_groupid').val()},function(res){
								layer.close(index);
								dialog(res.msg,res.status);
								layer.close(browsergroupaddLayer);
								browser_pagenum = 1;
								$browser.find(".browser-group-item[data-id='"+browser_gid+"']").click();
							})
						}
					})
				})
			});
		}
	},
	'template' : function() {
		var template = {};
		template['crawler'] = 
			'<div role="tabpanel" class="tab-pane crawler layui-tab-item'+($this.options.tabs.crawler == 'active'?' layui-show':'')+'" id="crawler">'+
			'	<div style="margin-top: 10px;">'+
			'		<form>'+
			'			<div class="form-group">'+
			'				<div class="input-group">'+
			'					<input type="url" class="form-control" id="crawlerUrl" placeholder="请输入网络文件地址">'+
			'					<input type="hidden" value="" >'+
			'					<span class="input-group-btn">'+
			'						<button class="btn btn-default" type="button" id="btnFetch">提取</button>'+
			'					</span>'+
			'				</div>'+
			'				<div class="crawler-img" style="background-image:url(\'/static/admin/webuploader/images/nopic.jpg\')">'+
			'					<span class="crawler-img-sizeinfo"></span>'+
			'				</div>'+
			'			</div>'+
			'		</form>'+
			'	</div>'+
			'	<div class="modal-footer" style="padding: 12px 0px 0px;">'+
			'		<button type="button" class="layui-btn layui-btn-primary cancelbtn">取消</button>'+
			'		<button type="button" class="layui-btn layui-btn-normal confirmbtn">确认</button>'+
			'	</div>'+
			'</div>';

		template['browser'] = 
			'<div role="tabpanel" class="tab-pane browser layui-tab-item'+($this.options.tabs.browser == 'active'?' layui-show':'')+'" id="browser">'+
			'	<div style="display:flex;border-bottom:1px solid #e8e9eb;">'+
			'		<div class="browser_group"></div>'+
			'		<div class="clearfix file-browser">'+
			'			<div class="browser-top">'+
			'				<div class="browser-top-item" style="width:280px"><input type="text" id="browser-search-keyword" class="layui-input" style="width:210px"/><button id="browser-search-btn" class="layui-btn layui-btn-primary" >搜索</button></div>'+
			'				<div class="browser-top-item" style="width:340px"><select id="browser-search-sort" style="border:1px solid #C9C9C9;border-radius:2px;color:#555;padding:0 6px;width:130px"><option value="1">上传时间正序</option><option value="2" selected>上传时间倒序</option><option value="3">名称正序</option><option value="4">名称倒序</option></select></div>'+
			'				<div title="上传图片" class="layui-btn layui-btn-normal browser-upload">上传图片</div>'+
			'			</div>'+
			'			<div style="height:420px;overflow:hidden"><div class="file-browser-filelist"></div></div>'+
			'			<div class="file-browser-bottomop">'+
			'				<label class="file-browser-bottomop-op1 flex-y-center" style="margin-right:10px;cursor:pointer;color:#555"><input type="checkbox" class="browser-file-checkall"/>&nbsp;全选</label>'+
			'				<div class="file-browser-bottomop-op2" style="margin-right:10px;cursor:pointer">移动</div>'+
			'				<div class="file-browser-bottomop-op3" style="margin-right:10px;cursor:pointer">删除</div>'+
			'				<div class="file-browser-bottomop-op4" style="flex:1"></div>'+
			'				<div class="file-browser-bottomop-pageinfo"></div>'+
			'			</div>'+
			'		</div>'+
			'	</div>'+
			'	<div class="modal-footer" style="display:flex;padding:10px;justify-content:center">'+
			//'		<div style="flex:1"></div>'+
			'		<button type="button" class="layui-btn layui-btn-normal confirmbtn" style="width:120px">确定</button>'+
			'		<button type="button" class="layui-btn layui-btn-primary cancelbtn" style="width:120px">取消</button>'+
			'	</div>'+
			'</div>';
		template['selecticon'] = 
			'<div role="tabpanel" class="tab-pane selecticon layui-tab-item'+($this.options.tabs.selecticon == 'active'?' layui-show':'')+'" id="selecticon">'+
			'	'+
			'</div>';
		template['posterbg'] = 
			'<div role="tabpanel" class="tab-pane selecticon layui-tab-item'+($this.options.tabs.posterbg == 'active'?' layui-show':'')+'" id="posterbg" style="width:100%;height:428px;overflow-y:scroll">'+

			'</div>';
		template['wximage'] = 
			'<div role="tabpanel" class="tab-pane selecticon layui-tab-item'+($this.options.tabs.wximage == 'active'?' layui-show':'')+'" id="wximage" style="width:100%;height:428px;overflow-y:scroll">'+

			'</div>';
		template['wxvoice'] = 
			'<div role="tabpanel" class="tab-pane selecticon layui-tab-item'+($this.options.tabs.wxvoice == 'active'?' layui-show':'')+'" id="wxvoice" style="width:100%;height:428px;overflow-y:scroll">'+

			'</div>';
		template['wxvideo'] = 
			'<div role="tabpanel" class="tab-pane selecticon layui-tab-item'+($this.options.tabs.wxvideo == 'active'?' layui-show':'')+'" id="wxvideo" style="width:100%;height:428px;overflow-y:scroll">'+

			'</div>';
		
		return template;
	}
}


var iconsvgpicker = {
	iconslist : [],
	menu : [],
	show : function(){
		var THIS = this;
		$.post('/'+thismodule+'/upload/iconsvg',{op:'init'},function(res){
			THIS.menu = res.clist
			var iconslist = res.iconslist
			THIS.iconslist = iconslist
			var	iconsvgpickerhistory = res.historylist;
			var hhistoryhtml = '';
			if(iconsvgpickerhistory.length>0){
				hhistoryhtml = '<div style="float:left;">最近使用：</div> ';
				for(var i in iconsvgpickerhistory){
					hhistoryhtml += '<div style="float:left;cursor:pointer;height:38px;line-height:36px;font-size:30px" title="'+iconsvgpickerhistory[i]['name']+'" class="iconsvgsvg" iconid="'+iconsvgpickerhistory[i]['id']+'">'+iconsvgpickerhistory[i]['show_svg'].replace(/font-size\:[^\"]*\"/g,"\"")+'</div>';
				}
			}

			var menu = THIS.menu;
			var html = '<div class="iconsvgpicker" id="iconsvgpicker">';
			html+='<div style="text-align:right;margin:0 10px;"><div style="float:left;height:38px;line-height:38px">'+hhistoryhtml+'</div><div class="layui-input-inline" style="width: 200px;"><input type="text" autocomplete="off" class="layui-input" placeholder="已收集180,187个图标"></div>';
			html+='	<button class="layui-btn layui-btn-primary" id="iconsvgpickersearch"><i class="layui-icon layui-icon-search"></i></button>';
			html+='</div>'
			html+='<div class="layui-tab layui-tab-card" style="margin:5px 8px" lay-filter="iconsvgpickertab">'
			html+='<ul class="layui-tab-title">'
			for(var i in menu){
				html+='<li class="'+(i==0?'layui-this':'')+'" style="min-width:0;padding:0 7px" lay-id="'+i+'">'+menu[i]['name']+'</li>'
			}
			html+='<li style="min-width:0;padding:0 7px;max-width: 70px;overflow: hidden;" lay-id="100" class="iconsvgpicker-search-title">&nbsp;</li>'
			html+='</ul>'
			html+='<div class="layui-tab-content iconsvgpicker_content" style="height:418px;overflow:auto;">'
			for(var i in menu){
				var iconshtml = '<ul>';
				var iconlist = iconslist[i];
				if(iconlist){
					for(var j in iconlist){
						iconshtml += '<li style="float:left;cursor:pointer;" title="'+iconlist[j]['name']+'" class="iconsvgsvg" iconid="'+iconlist[j]['id']+'">'+iconlist[j]['show_svg']+'</li>';
					}
				}
				iconshtml += '</ul>';
				html+='<div class="layui-tab-item '+(i==0?'layui-show':'')+'">'+iconshtml+'</div>'
			}
			html+='<div class="layui-tab-item iconsvgpicker-search-data"></div>'
			html+='</div>'
			html+='</div>';
			html+='</div>';
			$('#selecticon').html(html);
			/*
			var iconsvgpickerdialog = layer.open({
				type: 1, 
				title:'选择图标',
				shadeClose:true,
				content: html,
				area: ['810px', '500px']
			});
			*/
			$('.iconsvgsvg').unbind('click');
			$('.iconsvgsvg').bind('click',function(){
				THIS.showsvgedit($(this).attr('iconid'),$(this).html(),$(this).attr('title'));
			})
			layui.element.on('tab(iconsvgpickertab)', function(){
				var layid = this.getAttribute('lay-id');
				if(layid==100) return
				if(!THIS.iconslist[layid]){
					var index = layer.load();
					$.post('/'+thismodule+'/upload/iconsvg',{op:'geticonlist',cid:THIS.menu[layid]['id']},function(res){
						layer.close(index);
						var iconlist = res.iconlist;
						THIS.iconslist[layid] = iconlist;
						var iconshtml = '<ul>';
						if(iconlist){
							for(var j in iconlist){
								iconshtml += '<li style="float:left;cursor:pointer;" title="'+iconlist[j]['name']+'" class="iconsvgsvg" iconid="'+iconlist[j]['id']+'">'+iconlist[j]['show_svg']+'</li>';
							}
						}
						iconshtml += '</ul>';
						$('.iconsvgpicker_content').find('.layui-show').html(iconshtml);
						$('.iconsvgsvg').unbind('click');
						$('.iconsvgsvg').bind('click',function(){
							THIS.showsvgedit($(this).attr('iconid'),$(this).html(),$(this).attr('title'));
						})
					});
				}
			});
			$('#iconsvgpickersearch').bind('click',function(){
				var keyword = $(this).parents('.iconsvgpicker').eq(0).find('input').val();
				var index = layer.load();
				$.post('/'+thismodule+'/upload/iconsvg',{op:'searchiconlist',keyword:keyword},function(res){
					layer.close(index);
					var iconlist = res.iconlist;
					THIS.iconslist[100] = iconlist;
					var iconshtml = '<ul>';
					if(iconlist){
						for(var j in iconlist){
							iconshtml += '<li style="float:left;cursor:pointer;" title="'+iconlist[j]['name']+'" class="iconsvgsvg" iconid="'+iconlist[j]['id']+'">'+iconlist[j]['show_svg']+'</li>';
						}
					}
					iconshtml += '</ul>';
					$('.iconsvgpicker-search-title').html(keyword);
					$('.iconsvgpicker-search-data').html(iconshtml);
					layui.element.tabChange('iconsvgpickertab', 100);
					$('.iconsvgsvg').unbind('click');
					$('.iconsvgsvg').bind('click',function(){
						THIS.showsvgedit($(this).attr('iconid'),$(this).html(),$(this).attr('title'));
					})
				});
			})
		})
	},
	showsvgedit:function(iconid,show_svg,iconname){
		var THIS = this;
		var index = layer.load();
		//$.post('/admin/iconsvg.php',{op:'geticon',id:iconid},function(res){
			//var icon = res.icon
			layer.close(index);
			var html = '<div class="svgeditdiv">';
			html+='	<div class="tbackground">';
			html+= show_svg;
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
			html+='	<div class="iconsvgsetdiv">';
			html+='		<input type="text" value="#666666" class="layui-input iconsvgcolorinput" style="width:80px">';
			html+='		<div class="iconsvgcolorpicker"></div>';
			html+='		<input id="iconsvgSizeInput" type="text" value="100" class="layui-input" style="margin-left:10px;width:60px">';
			html+='		<div style="font-size:16px;margin-left:5px">px</div>';
			html+='		<button style="margin-left:15px" class="layui-btn layui-btn-normal" id="selecticonsvgConfirm">确定</button>';
			html+='	</div>';
			html+='</div>';
			var showsvgeditlayer = layer.open({
				type: 1,
				title:false,
				shadeClose:false,
				content: html,
				area: ['300px', '320px']
			});
			if($('.svgeditdiv svg').find('path').attr('fill')){
				var fillcolor = $('.svgeditdiv svg').find('path').attr('fill')
				$('.iconsvgcolorinput').val(fillcolor);
				$('.iconsvgcolorpicker .layui-colorpicker-trigger-span').css('background-color',fillcolor);
			}else{
				$('.svgeditdiv svg').find('path').attr('fill','#666666');
			}
			$('.color-block').unbind('click');
			$('.color-block').bind('click',function(){
				var fillcolor = $(this).attr('fillcolor');
				$('.svgeditdiv svg').find('path').attr('fill',fillcolor);
				$('.iconsvgcolorinput').val(fillcolor);
				$('.iconsvgcolorpicker .layui-colorpicker-trigger-span').css('background-color',fillcolor);
			})
			$('svg>path').unbind('click');
			$('svg>path').bind('click',function(){
				$(this).addClass('selected');
			});
			$('#iconsvgSizeInput').unbind('keyup blur');
			$('#iconsvgSizeInput').bind('keyup blur',function(){
				$('.svgeditdiv svg').css('font-size',$(this).val());
			})
			$('.iconsvgcolorinput').unbind('keyup blur');
			$('.iconsvgcolorinput').bind('keyup blur',function(){
				var fillcolor = $(this).val();
				$('.svgeditdiv svg').find('path').attr('fill',fillcolor);
				$('.iconsvgcolorpicker .layui-colorpicker-trigger-span').css('background-color',fillcolor);
			})
			$('#selecticonsvgConfirm').unbind('click');
			$('#selecticonsvgConfirm').bind('click',function(){
				var canvas = document.createElement('canvas');  //准备空画布
				var svgXml = $('.svgeditdiv>.tbackground').html();
				var image = new Image();
				image.src = 'data:image/svg+xml;base64,' + window.btoa(unescape(encodeURIComponent(svgXml))); //给图片对象写入base64编码的svg流
				image.onload = function(){
					var width = $('.svgeditdiv>.tbackground svg').width();
					var height = $('.svgeditdiv>.tbackground svg').height()
					canvas.width = width
					canvas.height = height;
					canvas.getContext('2d').drawImage(image, 0, 0);
					var pngdata = canvas.toDataURL('image/png');  //将画布内的信息导出为png图片数据
				
					$.post('/'+thismodule+'/upload/iconsvg',{op:'geticonurl',iconid:iconid,name:iconname,show_svg:svgXml,pngdata:pngdata},function(res){
						if(res.status==0){
							dialog(res.msg,res.status);
						}else{
							layer.close(showsvgeditlayer);
							if($this.options.multi){
								$this.options.callback([{url:res.url,width:width,height:height}]);
							} else {
								$this.options.callback({url:res.url,width:width,height:height});
							}
							$this.hide();
						}
					})
				}
			});
			layui.colorpicker.render({
				elem: '.iconsvgcolorpicker',
				format:'hex',
				alpha: false,
				color:$('.iconsvgcolorinput').val(),
				predefine: false,
				colors: ['#ff4444','#e64340','#ec8b89','#ed3f14','#ff9900',
					'#06bf04','#179b16','#9ed99d','#19be6b',
					'#3388ff','#2b85e4','#5cadff',
					'#000000','#333333','#666666','#999999','#c9c9c9','#f7f7f8','#1c2438','#495060','#dddee1','#e9eaec'],
				change:function(color){
					//shadowcolorValue = color;
					$('.iconsvgcolorinput').val(color)
					$('.svgeditdiv svg').find('path').attr('fill',color);
					$('.iconsvgcolorpicker .layui-colorpicker-trigger-span').css('background-color',color);
				}
			});
		//})
	}
}