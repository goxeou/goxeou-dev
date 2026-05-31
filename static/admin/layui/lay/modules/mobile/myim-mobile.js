/**

 @Name：layim mobile 2.0.0
 @Author：贤心
 @Site：http://layim.layui.com
 @License：LGPL
    
 */
 
layui.define(['laytpl', 'upload-mobile', 'layer-mobile', 'zepto'], function(exports){
  
  var v = '2.0.0';
  var $ = layui.zepto;
  var laytpl = layui.laytpl;
  var layer = layui['layer-mobile'];
  var upload = layui['upload-mobile'];
  var device = layui.device();
  
  var SHOW = 'layui-show', THIS = 'layim-this', MAX_ITEM = 20;

  //回调
  var call = {};
  
  //对外API
  var LAYIM = function(){
    this.v = v;
    touch($('body'), '*[layim-event]', function(e){
      var othis = $(this), methid = othis.attr('layim-event');
      events[methid] ? events[methid].call(this, othis, e) : '';
    });
  };
  
  //避免tochmove触发touchend
  var touch = function(obj, child, fn){
    var move, type = typeof child === 'function', end = function(e){
      var othis = $(this);
      if(othis.data('lock')){
        return;
      }
      move || fn.call(this, e);
      move = false;
      othis.data('lock', 'true');
      setTimeout(function(){
        othis.removeAttr('data-lock');
      }, othis.data('locktime') || 0);
    };

    if(type){
      fn = child;
    }

    obj = typeof obj === 'string' ? $(obj) : obj;

    if(!/Android|iPhone|SymbianOS|Windows Phone|iPad|iPod/.test(navigator.userAgent)){
       if(type){
         obj.on('click', end);
       } else {
          obj.on('click', child, end);
       }
       return;
    }

    if(type){
      obj.on('touchmove', function(){
        move = true;
      }).on('touchend', end);
    } else {
      obj.on('touchmove', child, function(){
        move = true;
      }).on('touchend', child, end);
    }
  };
  
  //底部弹出
  layer.popBottom = function(options){
    layer.close(layer.popBottom.index);
    layer.popBottom.index = layer.open($.extend({
      type: 1,
      content: options.content || '',
      shade: false,
      className: 'layim-layer'
    }, options));
  };
  
  //基础配置
  LAYIM.prototype.config = function(options){
    options = options || {};
    options = $.extend({
      title: '我的IM',
      isgroup: 0,
      isNewFriend: !0,
      voice: 'default.mp3',
      chatTitleColor: '#36373C'
    }, options);
    init(options);
  };
  
  //监听事件
  LAYIM.prototype.on = function(events, callback){
    if(typeof callback === 'function'){
      call[events] ? call[events].push(callback) : call[events] = [callback];
    }
    return this;
  };
  
  //打开一个自定义的会话界面
  LAYIM.prototype.chat = function(data){
    if(!window.JSON || !window.JSON.parse) return;
    return popchat(data, -1), this;
  };
  
  //打开一个自定义面板
  LAYIM.prototype.panel = function(options){
    return popPanel(options);
  };

  //获取所有缓存数据
  LAYIM.prototype.cache = function(){
    return cache;
  };
  //设置历史记录
  LAYIM.prototype.setHistory = function(data){
    setHistory(data);
  };
  
  //接受消息
  LAYIM.prototype.getMessage = function(data){
    return getMessage(data), this;
  };
  
  //添加好友/群
  LAYIM.prototype.addList = function(data){
    return addList(data), this;
  };
  
  //删除好友/群
  LAYIM.prototype.removeList = function(data){
    return removeList(data), this;
  };
  
  //设置好友在线/离线状态
  LAYIM.prototype.setFriendStatus = function(id, type){
    var list = $('.layim-friend'+ id);
    list[type === 'online' ? 'removeClass' : 'addClass']('layim-list-gray');
  };
  
  //设置当前会话状态
  LAYIM.prototype.setChatStatus = function(str){
    var thatChat = thisChat(), status = thatChat.elem.parents('.layim-panel').find('.layim-chat-status');
    return status.html(str), this;
  };
  
  //标记新动态
  LAYIM.prototype.showNew = function(alias, show){
    showNew(alias, show);
  };
  
  //解析聊天内容
  LAYIM.prototype.content = function(content){
    return layui.data.content(content);
  };
  
  //列表内容模板
  var listTpl = function(options){
    var nodata = {
      friend: "该分组下暂无好友",
      group: "暂无群组",
      history: "暂无任何消息"
    };

    options = options || {};
    
    //如果是历史记录，则读取排序好的数据
    if(options.type === 'history'){
      options.item = options.item || 'd.sortHistory';
    }
    
    return [
		'{{# var length = 0; layui.each('+ options.item +', function(i, data){ length++; }}',
      '<li layim-event="chat" data-type="'+ options.type +'" data-index="'+ (options.index ? '{{'+ options.index +'}}' : '{{data.type}}{{data.mid}}') +'" class="layim-'+ (options.type === 'history' ? '{{data.type}}' : options.type) +'{{data.mid}} {{ data.status === "offline" ? "layim-list-gray" : "" }}"><div><img src="{{data.headimg}}"></div><span>{{ data.nickname||data.groupname||data.name||"暂无" }}</span><p>{{ data.remark||data.content||"" }}</p><span class="layim-msg-status {{ data.unreadnum == 0 ? "" : "layui-show" }}">{{data.unreadnum}}</span></li>',
    '{{# }); if(length === 0){ }}',
      '<li class="layim-null">'+ (nodata[options.type] || "暂无数据") +'</li>',
    '{{# } }}'].join('');
  };
  
  //公共面板
  var comTpl = function(tpl, anim, back){
    return ['<div class="layim-panel'+ (anim ? ' layui-m-anim-left' : '') +'">',
      '<div class="layim-title" style="background-color: {{d.base.chatTitleColor}};">',
        '<p>',
          (back ? '<i class="layui-icon layim-chat-back" layim-event="back">&#xe603;</i>' : '') ,
					'{{ d.title || d.base.title }}<span class="layim-chat-status"></span>',
        '</p>',
      '</div>',
      '<div class="layui-unselect layim-content">',
        tpl,
      '</div>',
    '</div>'].join('');
  };
  
  //主界面模版
  var elemTpl = ['<div class="layui-layim">',
    '<div class="layim-tab-content layui-show">',
      '<ul class="layim-list-friend">',
        '<ul class="layui-layim-list layim-list-history">',
        listTpl({
          type: 'history'
        }),
        '</ul>',
      '</ul>',
    '</div>',
    '<div class="layim-tab-content">',
      '<ul class="layim-list-top">',
        '{{# if(d.base.isNewFriend){ }}',
        '<li layim-event="newFriend"><i class="layui-icon">&#xe654;</i>新的朋友<i class="layim-new" id="LAY_layimNewFriend"></i></li>',
        '{{# } if(d.base.isgroup){ }}',
        '<li layim-event="group"><i class="layui-icon">&#xe613;</i>群聊<i class="layim-new" id="LAY_layimNewGroup"></i></li>',
        '{{# } }}',
      '</ul>',
      '<ul class="layim-list-kefu" style="background-color:#fff">',
        '{{# layui.each(d.kefu, function(index, item){ }}',
        '<li>',
          '<ul class="layui-layim-list layui-show">',
            '<li layim-event="chat" data-type="kefu" data-index="{{index}}" class="layim-kefu{{item.id}}"><div><img src="{{item.headimg}}"></div><span>{{item.nickname}}<i style="font-style:normal;color:#666">{{mine.id==item.id?"(我)":""}}</i></span><p>{{item.remark}}</p><span class="layim-msg-status {{ item.unreadnum == 0 ? "" : "layui-show" }}">{{item.unreadnum}}</span></li>',
          '</ul>',
        '</li>',
        '{{# }); if(d.kefu.length === 0){ }}',
        '<li><ul class="layui-layim-list layui-show"><li class="layim-null">暂无客服人员</li></ul>',
      '{{# } }}',
      '</ul>',
    '</div>',
    '<div class="layim-tab-content">',
        '<ul class="">',
					'<li style="height:50px;line-height:50px;margin:5px 0;padding:5px 10px;background:#fff;display:flex;"><span style="flex:1">头像</span><img src="{{mine.headimg}}" style="width: 50px;height: 50px;border-radius: 100%;text-align:right"></li>',
					'<li style="height:30px;line-height:30px;margin:5px 0;padding:5px 10px;background:#fff;display:flex;"><span style="flex:1">昵称 </span>{{mine.nickname}}</li>',
					'<li style="height:30px;line-height:30px;margin:5px 0;padding:5px 10px;background:#fff;display:flex;"><span style="flex:1">备注</span>{{mine.remark||"暂无"}}</li>',
				'</ul>',
    '</div>',
  '</div>',
  '<ul class="layui-unselect layui-layim-tab">',
    '<li title="消息" layim-event="tab" lay-type="message" class="layim-this"><i class="layui-icon">&#xe611;</i><span>消息</span><i class="layim-new" id="LAY_layimNewMsg"></i></li>',
    '<li title="客服" layim-event="tab" lay-type="kefu"><i class="layui-icon">&#xe770;</i><span>客服</span><i class="layim-new" id="LAY_layimNewList"></i></li>',
    '<li title="我的" layim-event="tab" lay-type="more"><i class="layui-icon">&#xe612;</i><span>我的</span><i class="layim-new" id="LAY_layimNewMore"></i></li>',
  '</ul>'].join('');
  
  //聊天主模板
  var elemChatTpl = ['<div class="layim-chat layim-chat-{{d.data.type}}">',
    '<div class="layim-chat-main">',
      '<ul></ul>',
    '</div>',
    '<div class="layim-chat-footer">',
      '<div class="layim-chat-send"><input type="text" autocomplete="off"><button class="layim-send layui-disabled" layim-event="send">发送</button></div>',
      '<div class="layim-chat-tool" data-json="{{encodeURIComponent(JSON.stringify(d.data))}}">',
        '<span class="layui-icon layim-tool-face" title="选择表情" layim-event="face">&#xe60c;</span>',
        '{{# if(d.base && d.base.uploadImage){ }}',
        '<span class="layui-icon layim-tool-image" title="上传图片" layim-event="image">&#xe60d;<input type="file" name="file" accept="image/*"></span>',
        '{{# }; }}',
        '{{# if(d.base && d.base.uploadFile){ }}',
        '<span class="layui-icon layim-tool-image" title="发送文件" layim-event="image" data-type="file">&#xe61d;<input type="file" name="file"></span>',
         '{{# }; }}',
         '{{# layui.each(d.base.tool, function(index, item){ }}',
        '<span class="layui-icon  {{item.iconClass||\"\"}} layim-tool-{{item.alias}}" title="{{item.title}}" layim-event="extend" lay-filter="{{ item.alias }}">{{item.iconUnicode||""}}</span>',
         '{{# }); }}',
      '</div>',
    '</div>',
  '</div>'].join('');
  
  //补齐数位
  var digit = function(num){
    return num < 10 ? '0' + (num|0) : num;
  };
  
  //转换时间
  layui.data.date = function(timestamp){
    var d = new Date(timestamp||new Date());
    return digit(d.getMonth() + 1) + '-' + digit(d.getDate())
    + ' ' + digit(d.getHours()) + ':' + digit(d.getMinutes());
  };
  
  //转换内容
  layui.data.content = function(content,msgtype){
		if(msgtype!=undefined && msgtype == 'image'){
			content = '<img class="layui-layim-photos" src="'+content+'"/>';
			return content;
		}
		if(msgtype!=undefined && msgtype == 'miniprogrampage'){
			var conobj = JSON.parse(content);
			content = conobj.Title + '<br>' + '<img src="'+conobj.ThumbUrl+'">';
			return content;
		}
    //支持的html标签
    var html = function(end){
      return new RegExp('\\n*\\['+ (end||'') +'(pre|div|p|table|thead|th|tbody|tr|td|ul|li|ol|li|dl|dt|dd|h2|h3|h4|h5)([\\s\\S]*?)\\]\\n*', 'g');
    };
    content = (content||'').replace(/&(?!#?[a-zA-Z0-9]+;)/g, '&amp;')
    .replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/'/g, '&#39;').replace(/"/g, '&quot;') //XSS
    .replace(/@(\S+)(\s+?|$)/g, '@<a href="javascript:;">$1</a>$2') //转义@
    
    .replace(/\[([^\s\[\]]+?)\]/g, function(face){  //转义表情
      //var alt = face.replace(/^face/g, '');
			if(faces[face]){
				return '<img class="face" alt="'+ face +'" title="'+ face +'" src="' + faces[face] + '">';
			}else{
				return face;
			}
    })
    .replace(/img\[([^\s]+?)\]/g, function(img){  //转义图片
      return '<img class="layui-layim-photos" src="' + img.replace(/(^img\[)|(\]$)/g, '') + '">';
    })
    .replace(/file\([\s\S]+?\)\[[\s\S]*?\]/g, function(str){ //转义文件
      var href = (str.match(/file\(([\s\S]+?)\)\[/)||[])[1];
      var text = (str.match(/\)\[([\s\S]*?)\]/)||[])[1];
      if(!href) return str;
      return '<a class="layui-layim-file" href="'+ href +'" download target="_blank"><i class="layui-icon">&#xe61e;</i><cite>'+ (text||href) +'</cite></a>';
    })
    .replace(/audio\[([^\s]+?)\]/g, function(audio){  //转义音频
      return '<div class="layui-unselect layui-layim-audio" layim-event="playAudio" data-src="' + audio.replace(/(^audio\[)|(\]$)/g, '') + '"><i class="layui-icon">&#xe652;</i><p>音频消息</p></div>';
    })
    .replace(/video\[([^\s]+?)\]/g, function(video){  //转义音频
      return '<div class="layui-unselect layui-layim-video" layim-event="playVideo" data-src="' + video.replace(/(^video\[)|(\]$)/g, '') + '"><i class="layui-icon">&#xe652;</i></div>';
    })
    
    .replace(/a\([\s\S]+?\)\[[\s\S]*?\]/g, function(str){ //转义链接
      var href = (str.match(/a\(([\s\S]+?)\)\[/)||[])[1];
      var text = (str.match(/\)\[([\s\S]*?)\]/)||[])[1];
      if(!href) return str;
      return '<a href="'+ href +'" target="_blank">'+ (text||href) +'</a>';
    }).replace(html(), '\<$1 $2\>').replace(html('/'), '\</$1\>') //转移HTML代码
    .replace(/\n/g, '<br>') //转义换行 
		return content;
  };
  
  var elemChatMain = ['<li class="layim-chat-li{{ d.isreply=="1" ? " layim-chat-mine" : "" }}" data-msgid="{{d.msgid}}">',
    '<div class="layim-chat-user"><img src="{{ d.headimg }}"><cite>',
      '{{ d.nickname||"暂无" }}',
    '</cite></div>',
    '<div class="layim-chat-text">{{ layui.data.content(d.content||"&nbsp;",d.msgtype) }}</div>',
		'{{ d.fail ? "<div style=\'color:red;font-size:12px\'>发送失败!</div>" : "" }}',
  '</li>'].join('');
  
  //处理初始化信息
  var cache = {message: message, chat: []}, init = function(options){
    var init = options.init || {}
    mine = init.mine || {},
    local = layui.data('layim-mobile')[mine.id] || {},
    obj = {
      base: options,
      local: local,
      mine: mine,
      history: local.history || []
    }, create = function(data){
      var mine = data.mine || {};
      var local = layui.data('layim-mobile')[mine.id] || {}, obj = {
        base: options, //基础配置信息
        local: local, //本地数据
        mine:  mine, //我的用户信息
        friend: data.friend || [], //联系人信息
        kefu: data.kefu || [], //联系人信息
        group: data.group || [], //群组信息
        history: local.history || [] //历史会话信息
      };
      obj.sortHistory = sort(obj.history, 'historyTime');
      cache = $.extend(cache, obj);
      popim(laytpl(comTpl(elemTpl)).render(obj));
      layui.each(call.ready, function(index, item){
        item && item(obj);
      });
			showNew('Msg');
			showNew('List');
    };
    cache = $.extend(cache, obj);
		//console.log(cache)
    if(options.brief){
      return layui.each(call.ready, function(index, item){
        item && item(obj);
      });
    };
    create(init)
  };

  //显示好友列表面板
  var layimMain, popim = function(content){
    return layer.open({
			type: 1,
      shade: false,
      shadeClose: false,
      anim: -1,
      content: content,
      success: function(elem){
        layimMain = $(elem);
        fixIosScroll(layimMain.find('.layui-layim'));
      }
    });
  };
  
  //弹出公共面板
  var popPanel = function(options, anim){
    options = options || {};
    var data = {
      base: cache.base,
      local: cache.local,
      title: options.title||'',
      data: options.data
    };
    return layer.open({
      type: 1,
      shade: false,
      shadeClose: false,
      anim: -1,
      content: laytpl(comTpl(options.tpl, anim === -1 ? false : true, true)).render(data),
      success: function(elem){
        var othis = $(elem);
        othis.prev().find('.layim-panel').addClass('layui-m-anim-lout');
        options.success && options.success(elem);
        options.isChat || fixIosScroll(othis.find('.layim-content'));
      },
      end: options.end
    });
  }
  
  //显示聊天面板
  var layimChat, layimMin, To = {}, popchat = function(data, anim, back){
    data = data || {};
		console.log(data)
    if(!data.id){
			return;
      //return layer.msg('非法用户');
    }
		if(data.type=='kefu' && data.mid == mine.id){
			return layer.msg('不能给自己发消息');
		}
		if(data.type=='friend'){
			$.post('',{op:'getmsgkf',id:data.id},function(res){
				if(res.kfid!='' && res.kfid!=mine.id){
					layer.msg('其他客服已接入');
				}else{
					popc(data, anim, back);
				}
			})
		}else{
			popc(data, anim, back);
		}
		function popc(data, anim, back){
			layer.close(popchat.index);

			return popchat.index = popPanel({
				tpl: elemChatTpl,
				data: data,
				title: data.name,
				isChat: !0,
				success: function(elem){
					layimChat = $(elem);
					hotkeySend();
					viewChatlog();
					
					delete cache.message[data.type + data.mid]; //剔除缓存消息
					showNew('Msg');
					showNew('List');
					
					//聊天窗口的切换监听
					var thatChat = thisChat(), chatMain = thatChat.elem.find('.layim-chat-main');
					layui.each(call.chatChange, function(index, item){
						item && item(thatChat);
					});
					
					fixIosScroll(chatMain);
					
					//输入框获取焦点
					thatChat.textarea.on('focus', function(){
						setTimeout(function(){
							chatMain.scrollTop(chatMain[0].scrollHeight + 1000);
						}, 500);
					});
				},
				end: function(){
					layimChat = null;
					sendMessage.time = 0;
				}
			}, anim);
		}

  };
  
  //修复IOS设备在边界引发无法滚动的问题
  var fixIosScroll = function(othis){
    if(device.ios){
      othis.on('touchmove', function(e){
        var top = othis.scrollTop();
        if(top <= 0){ 
          othis.scrollTop(1);
          e.preventDefault(e);
        }
        if(this.scrollHeight - top - othis.height() <= 0){
          othis.scrollTop(othis.scrollTop() - 1);
          e.preventDefault(e);
        }
      });
    }
  };
  
  //同步置灰状态
  var syncGray = function(data){
    $('.layim-'+data.type+data.id).each(function(){
      if($(this).hasClass('layim-list-gray')){
        layui.layim.setFriendStatus(data.id, 'offline'); 
      }
    });
  };
  
  //获取当前聊天面板
  var thisChat = function(){
    if(!layimChat) return {};
    var cont = layimChat.find('.layim-chat');
    var to = JSON.parse(decodeURIComponent(cont.find('.layim-chat-tool').data('json')));
    return {
      elem: cont,
      data: to,
      textarea: cont.find('input')
    };
  };
  
  //将对象按子对象的某个key排序
  var sort = function(data, key, asc){
    var arr = [],
    compare = function (obj1, obj2) { 
      var value1 = obj1[key]; 
      var value2 = obj2[key]; 
      if (value2 < value1) { 
        return -1; 
      } else if (value2 > value1) { 
        return 1; 
      } else { 
        return 0; 
      } 
    };
    layui.each(data, function(index, item){
      arr.push(item);
    });
    arr.sort(compare);
    if(asc) arr.reverse();
    return arr;
  };
  
  //记录历史会话
  var setHistory = function(data){
    var local = layui.data('layim-mobile')[cache.mine.id] || {};
    var obj = {}, history = local.history || {};
		//if(data.type == 'kefu') data.mid = data.id;
    var is = history[data.type + data.mid];
    
    if(!layimMain) return;
    if(data.type == 'kefu'){
			var historyElem = layimMain.find('.layim-list-kefu');
			var msgItem = historyElem.find('.layim-'+ data.type + data.mid);
			var msgNums = (cache.message[data.type+data.mid]||[]).length; //未读消息数
			msgItem.find('p').html(data.content);
			if(msgNums > 0){
				msgItem.find('.layim-msg-status').html(msgNums).addClass(SHOW);
				//$('#LAY_layimNewList').show();
			}
			showNew('List');
		}else{
			var historyElem = layimMain.find('.layim-list-history');

			data.historyTime = data.createtime*1000;
			history[data.type + data.mid] = data;
			local.history = history;
			//console.log(local.history)
			
			layui.data('layim-mobile', {
				key: cache.mine.id,value: local
			});
			
			var msgItem = historyElem.find('.layim-'+ data.type + data.mid),
			msgNums = (cache.message[data.type+data.mid]||[]).length, //未读消息数
			showMsg = function(){
				msgItem = historyElem.find('.layim-'+ data.type + data.mid);
				msgItem.find('p').html(data.content);
				if(msgNums > 0){
					msgItem.find('.layim-msg-status').html(msgNums).addClass(SHOW);
				}
			};

			if(msgItem.length > 0){
				showMsg();
				historyElem.prepend(msgItem.clone());
				msgItem.remove();
			} else {
				obj[data.type + data.mid] = data;
				var historyList = laytpl(listTpl({
					type: 'history',
					item: 'd.data'
				})).render({data: obj});
				historyElem.prepend(historyList);
				showMsg();
				historyElem.find('.layim-null').remove();
			}
			showNew('Msg');
		}
  };
  
  //标注底部导航新动态徽章
  var showNew = function(alias, show){
    if(!show){
      var show = false;
			//console.log('xxxxxxxxxxxxxxxxxx')
			//console.log(cache.message)
			let adaxdsd = cache.message;
			for(var i in adaxdsd){
				if(alias=='Msg' && i.indexOf('friend') ===0){
					show = true;
				}
				if(alias=='List' && i.indexOf('kefu') ===0){
					show = true;
				}
			}
    }
		//console.log(alias)
    $('#LAY_layimNew'+alias)[show ? 'addClass' : 'removeClass'](SHOW);
  };
  
  //发送消息
  var sendMessage = function(){
    var data = {
      nickname: cache.mine ? cache.mine.nickname : '暂无',
      headimg: cache.mine ? cache.mine.headimg : (layui.cache.dir+'css/pc/layim/skin/logo.jpg'),
      id: cache.mine ? cache.mine.id : null,
      isreply: 1,
      mine: true
    };
    var thatChat = thisChat(), ul = thatChat.elem.find('.layim-chat-main ul');
    var To = thatChat.data, maxLength = cache.base.maxLength || 3000;
    var time =  new Date().getTime(), textarea = thatChat.textarea;
    
    data.content = textarea.val();
    
    if(data.content === '') return;

    if(data.content.length > maxLength){
      return layer.msg('内容最长不能超过'+ maxLength +'个字符')
    }
    
    if(time - (sendMessage.time||0) > 60*1000){
      ul.append('<li class="layim-chat-system"><span>'+ layui.data.date() +'</span></li>');
      sendMessage.time = time;
    }
    var msgid = Date.now() + '' + parseInt(Math.random()*1000000);
		data.msgid = msgid;
    ul.append(laytpl(elemChatMain).render(data));
    var param = {
			msgid:msgid,
      mine: data,
      to: To
    }
		if(To.type == 'kefu'){
			var message = {
				msgid:msgid,
				mid: To.mid,
				fromid: data.id,
				toid: To.id,
				type: To.type,
				content: param.mine.content,
				timestamp: time
			};
			//console.log(message)
		}else{
			var message = {
				msgid:msgid,
				mid: To.mid,
				nickname: To.nickname,
				headimg: To.headimg,
				type: To.type,
				content: param.mine.content,
				timestamp: time,
				isreply: 1,
				kfid:param.mine.id,
				mine: true
			};
		}
    pushChatlog(message);
    
    layui.each(call.sendMessage, function(index, item){
      item && item(param);
    });
    
    To.content = data.content;
		//if(To.type!='kefu'){
			setHistory(To);
		//}
    chatListMore();
    textarea.val('');
    
    textarea.next().addClass('layui-disabled');
  };
  
  //消息声音提醒
  var voice = function() {
    var audio = document.createElement("audio");
    audio.src = layui.cache.dir+'css/modules/layim/voice/'+ cache.base.voice;
    audio.play();
  };
  
  //接受消息
  var messageNew = {}, getMessage = function(data){
    data = data || {};
    
    var group = {}, thatChat = thisChat(), thisData = thatChat.data || {},
    isThisData = thisData.mid == data.mid && thisData.type == data.type; //是否当前打开联系人的消息
		//console.log(thisData)
		//console.log(data)
    
    data.timestamp = data.timestamp || new Date().getTime();
    data.system || pushChatlog(data);
    messageNew = JSON.parse(JSON.stringify(data));
    
    if(!data.system && cache.base.voice && !data.novice){
      voice();
    }
    
    if((!layimChat && data.content) || !isThisData){
      if(cache.message[data.type + data.mid]){
        cache.message[data.type + data.mid].push(data)
      } else {
        cache.message[data.type + data.mid] = [data];
        
        //记录聊天面板队列
        if(data.type === 'friend'){
          var friend;
          layui.each(cache.friend, function(index1, item1){
            layui.each(item1.list, function(index, item){
              if(item.mid == data.mid){
                item.type = 'friend';
                item.name = item.nickname;
                cache.chat.push(item);
                return friend = true;
              }
            });
            if(friend) return true;
          });
          if(!friend){
            data.name = data.nickname;
            data.temporary = true; //临时会话
            cache.chat.push(data);
          }
        } else if(data.type === 'group'){
          var isgroup;
          layui.each(cache.group, function(index, item){
            if(item.id == data.id){
              item.type = 'group';
              item.name = item.groupname;
              cache.chat.push(item);
              return isgroup = true;
            }
          });
          if(!isgroup){
            data.name = data.groupname;
            cache.chat.push(data);
          }
        } else {
          data.name = data.name || data.nickname || data.groupname;
          cache.chat.push(data);
        }
      }
      if(data.type === 'group'){
        layui.each(cache.group, function(index, item){
          if(item.id == data.id){
            group.avatar = item.avatar;
            return true;
          }
        });
      }
      if(!data.system){
        //显示消息提示区域
      }
    }
    if(!data.system){
			setHistory(data);
		}
    
    if(!layimChat || !isThisData) return;

    var cont = layimChat.find('.layim-chat');
    var ul = cont.find('.layim-chat-main ul');
    
    //系统消息
    if(data.system){
      ul.append('<li class="layim-chat-system"><span>'+ data.content +'</span></li>');
    } else if(data.content.replace(/\s/g, '') !== ''){
			if(data.type == 'kefu'){
				data.nickname = kfdata[data.fromid]['nickname'];
				data.headimg = kfdata[data.fromid]['headimg'];
			}else{
			if(data.isreply == 1){
				data.nickname = kfdata[data.kfid]['nickname'];
				data.headimg = kfdata[data.kfid]['headimg'];
			}
			}
      if(data.timestamp - (sendMessage.time||0) > 60*1000){
        ul.append('<li class="layim-chat-system"><span>'+ layui.data.date(data.timestamp) +'</span></li>');
        sendMessage.time = data.timestamp;
      }
      ul.append(laytpl(elemChatMain).render(data));
    }
    chatListMore();
  };
  
  //存储最近MAX_ITEM条聊天记录到本地
  var pushChatlog = function(message){
    var local = layui.data('layim-mobile')[cache.mine.id] || {};
    var chatlog = local.chatlog || {};
    if(chatlog[message.type + message.mid]){
			if(message.id){
				for(var i=0;i<chatlog[message.type + message.mid].length;i++){
					if(chatlog[message.type + message.mid][i].id == message.id){
						return;
					}
				}
			}
      chatlog[message.type + message.mid].push(message);
      if(chatlog[message.type + message.mid].length > MAX_ITEM){
        chatlog[message.type + message.mid].shift();
      }
    } else {
      chatlog[message.type + message.mid] = [message];
    }
    local.chatlog = chatlog;
    layui.data('layim-mobile', {
      key: cache.mine.id,value: local
    });
  };
  
  //渲染本地最新聊天记录到相应面板
  var viewChatlog = function(){
    var local = layui.data('layim-mobile')[cache.mine.id] || {};
    var thatChat = thisChat(), chatlog = local.chatlog || {};
    var ul = thatChat.elem.find('.layim-chat-main ul');
		if(thatChat.data.type == 'kefu'){
			layui.each(chatlog[thatChat.data.type + thatChat.data.id], function(index, item){
				if(item.fromid == mine.id){
					item.isreply = 1;
				}
				item.nickname = kfdata[item.fromid]['nickname'];
				item.headimg = kfdata[item.fromid]['headimg'];

				item.timestamp = item.timestamp || item.createtime * 1000;
				if(new Date().getTime() > item.timestamp && item.timestamp - (sendMessage.time||0) > 60*1000){
					ul.append('<li class="layim-chat-system"><span>'+ layui.data.date(item.timestamp) +'</span></li>');
					sendMessage.time = item.timestamp;
				}
				ul.append(laytpl(elemChatMain).render(item));
			});
		}else{
			layui.each(chatlog[thatChat.data.type + thatChat.data.mid], function(index, item){
				if(item.isreply == 1){
					item.nickname = kfdata[item.kfid]['nickname'];
					item.headimg = kfdata[item.kfid]['headimg'];
				}
				item.timestamp = item.timestamp || item.createtime * 1000;
				if(new Date().getTime() > item.timestamp && item.timestamp - (sendMessage.time||0) > 60*1000){
					ul.append('<li class="layim-chat-system"><span>'+ layui.data.date(item.timestamp) +'</span></li>');
					sendMessage.time = item.timestamp;
				}
				ul.append(laytpl(elemChatMain).render(item));
			});
		}
    chatListMore();
  };
  
  //添加好友或群
  var addList = function(data){
    var obj = {}, has, listElem = layimMain.find('.layim-list-'+ data.type);
    
    if(cache[data.type]){
      if(data.type === 'friend'){
        layui.each(cache.friend, function(index, item){
          if(data.groupid == item.id){
            //检查好友是否已经在列表中
            layui.each(cache.friend[index].list, function(idx, itm){
              if(itm.id == data.id){
                return has = true
              }
            });
            if(has) return layer.msg('好友 ['+ (data.nickname||'') +'] 已经存在列表中',{anim: 6});
            cache.friend[index].list = cache.friend[index].list || [];
            obj[cache.friend[index].list.length] = data;
            data.groupIndex = index;
            cache.friend[index].list.push(data); //在cache的friend里面也增加好友
            return true;
          }
        });
      } else if(data.type === 'group'){
        //检查群组是否已经在列表中
        layui.each(cache.group, function(idx, itm){
          if(itm.id == data.id){
            return has = true
          }
        });
        if(has) return layer.msg('您已是 ['+ (data.groupname||'') +'] 的群成员',{anim: 6});
        obj[cache.group.length] = data;
        cache.group.push(data);
      }
    }
    
    if(has) return;

    var list = laytpl(listTpl({
      type: data.type,
      item: 'd.data',
      index: data.type === 'friend' ? 'data.groupIndex' : null
    })).render({data: obj});

    if(data.type === 'friend'){
      var li = listElem.children('li').eq(data.groupIndex);
      li.find('.layui-layim-list').append(list);
      li.find('.layim-count').html(cache.friend[data.groupIndex].list.length); //刷新好友数量
      //如果初始没有好友
      if(li.find('.layim-null')[0]){
        li.find('.layim-null').remove();
      }
    } else if(data.type === 'group'){
      listElem.append(list);
      //如果初始没有群组
      if(listElem.find('.layim-null')[0]){
        listElem.find('.layim-null').remove();
      }
    }
  };
  
  //移出好友或群
  var removeList = function(data){
    var listElem = layimMain.find('.layim-list-'+ data.type);
    var obj = {};
    if(cache[data.type]){
      if(data.type === 'friend'){
        layui.each(cache.friend, function(index1, item1){
          layui.each(item1.list, function(index, item){
            if(data.id == item.id){
              var li = listElem.children('li').eq(index1);
              var list = li.find('.layui-layim-list').children('li');
              li.find('.layui-layim-list').children('li').eq(index).remove();
              cache.friend[index1].list.splice(index, 1); //从cache的friend里面也删除掉好友
              li.find('.layim-count').html(cache.friend[index1].list.length); //刷新好友数量  
              //如果一个好友都没了
              if(cache.friend[index1].list.length === 0){
                li.find('.layui-layim-list').html('<li class="layim-null">该分组下已无好友了</li>');
              }
              return true;
            }
          });
        });
      } else if(data.type === 'group'){
        layui.each(cache.group, function(index, item){
          if(data.id == item.id){
            listElem.children('li').eq(index).remove();
            cache.group.splice(index, 1); //从cache的group里面也删除掉数据
            //如果一个群组都没了
            if(cache.group.length === 0){
              listElem.html('<li class="layim-null">暂无群组</li>');
            }
            return true;
          }
        });
      }
    }
  };
  
  //查看更多记录
  var chatListMore = function(){
    var thatChat = thisChat(), chatMain = thatChat.elem.find('.layim-chat-main');
    var ul = chatMain.find('ul'), li = ul.children('.layim-chat-li'); 
    
    if(li.length >= MAX_ITEM){
      var first = li.eq(0);
      first.prev().remove();
      if(!ul.prev().hasClass('layim-chat-system')){
        ul.before('<div class="layim-chat-system"><span layim-event="chatLog">查看更多记录</span></div>');
      }
      first.remove();
    }
    chatMain.scrollTop(chatMain[0].scrollHeight + 1000);
  };
  
  //快捷键发送
  var hotkeySend = function(){
    var thatChat = thisChat(), textarea = thatChat.textarea;
    var btn = textarea.next();
    textarea.off('keyup').on('keyup', function(e){
      var keyCode = e.keyCode;
      if(keyCode === 13){
        e.preventDefault();
        sendMessage();
      }
      btn[textarea.val() === '' ? 'addClass' : 'removeClass']('layui-disabled');
    });
  };
  
  //表情库
  var faces = function(){
    var alt = ["[微笑]", "[撇嘴]", "[色]", "[发呆]", "[得意]", "[流泪]", "[害羞]", "[闭嘴]", "[睡]", "[大哭]", "[尴尬]", "[发怒]", "[调皮]", "[呲牙]", "[惊讶]", "[难过]", "[酷]", "[囧]", "[抓狂]", "[吐]", "[偷笑]", "[愉快]", "[白眼]", "[傲慢]", "[撇嘴]", "[困]", "[惊恐]", "[流汗]", "[憨笑]", "[悠闲]", "[奋斗]", "[咒骂]", "[疑问]", "[嘘]", "[晕]", "[折磨]", "[衰]", "[骷髅]", "[敲打]", "[再见]", "[擦汗]", "[抠鼻]", "[鼓掌]","[糗大了]", "[坏笑]", "[左哼哼]", "[右哼哼]", "[哈欠]", "[鄙视]", "[委屈]", "[快哭了]", "[阴险]", "[亲亲]","[吓]", "[可怜]", "[菜刀]", "[西瓜]", "[啤酒]", "[篮球]", "[乒乓]", "[咖啡]", "[饭]", "[猪头]", "[玫瑰]", "[凋谢]", "[嘴唇]", "[爱心]", "[心碎]", "[蛋糕]","[闪电]", "[炸弹]", "[刀]" ,"[足球]", "[瓢虫]", "[便便]", "[月亮]", "[太阳]","[礼物]", "[拥抱]", "[强]", "[弱]", "[握手]", "[胜利]", "[抱拳]", "[勾引]", "[拳头]", "[差劲]","[爱你]","[NO]","[OK]", "[跳跳]", "[发抖]", "[怄火]", "[转圈]"], arr = {};
    layui.each(alt, function(index, item){
      arr[item] = layui.cache.dir + 'images/wxface/'+ index + '.png';
    });
    return arr;
  }();
  
  
  var stope = layui.stope; //组件事件冒泡
  
  //在焦点处插入内容
  var focusInsert = function(obj, str, nofocus){
    var result, val = obj.value;
    nofocus || obj.focus();
    if(document.selection){ //ie
      result = document.selection.createRange(); 
      document.selection.empty(); 
      result.text = str; 
    } else {
      result = [val.substring(0, obj.selectionStart), str, val.substr(obj.selectionEnd)];
      nofocus || obj.focus();
      obj.value = result.join('');
    }
  };
  
  //事件
  var anim = 'layui-anim-upbit', events = { 
    //弹出聊天面板
    chat: function(othis){
      var local = layui.data('layim-mobile')[cache.mine.id] || {};
      var type = othis.data('type'), index = othis.data('index');
      var list = othis.attr('data-list') || othis.index(), data = {};
      if(type === 'friend'){
        data = cache[type][index];
      } else if(type === 'group'){
        data = cache[type][list];
      } else if(type === 'history'){
        data = (local.history || {})[index] || {};
      } else if(type === 'kefu'){
        data = cache[type][index];
      }
      data.name = data.name || data.nickname || data.groupname;
      if(type !== 'history' ){
      //  data.type = type;
      }
      popchat(data, true);
      $('.layim-'+ data.type + data.mid).find('.layim-msg-status').removeClass(SHOW);
    },
    
    //展开联系人分组
    spread: function(othis){
      var type = othis.attr('lay-type');
      var spread = type === 'true' ? 'false' : 'true';
      var local = layui.data('layim-mobile')[cache.mine.id] || {};
      othis.next()[type === 'true' ? 'removeClass' : 'addClass'](SHOW);
      local['spread' + othis.parent().index()] = spread;
      layui.data('layim-mobile', {
        key: cache.mine.id
        ,value: local
      });
      othis.attr('lay-type', spread);
      othis.find('.layui-icon').html(spread === 'true' ? '&#xe61a;' : '&#xe602;');
    },
    
    //底部导航切换
    tab: function(othis){
      var index = othis.index(), main = '.layim-tab-content';
      othis.addClass(THIS).siblings().removeClass(THIS);
      layimMain.find(main).eq(index).addClass(SHOW).siblings(main).removeClass(SHOW);
    },
    
    //返回到上一个面板
    back: function(othis){
      var layero = othis.parents('.layui-m-layer').eq(0)
      ,index = layero.attr('index')
      ,PANEL = '.layim-panel';
      setTimeout(function(){
        layer.close(index);
      }, 300);
      othis.parents(PANEL).eq(0).removeClass('layui-m-anim-left').addClass('layui-m-anim-rout');
      layero.prev().find(PANEL).eq(0).removeClass('layui-m-anim-lout').addClass('layui-m-anim-right');
      layui.each(call.back, function(index, item){
        setTimeout(function(){
          item && item();
        }, 200);
      });
    },
    
    //发送聊天内容
    send: function(){
      sendMessage();
    },
    
    //表情
    face: function(othis, e){
      var content = '', thatChat = thisChat(), input = thatChat.textarea;
      layui.each(faces, function(key, item){
				if(key != '[折磨]')
         content += '<li title="'+ key +'" ><img class="face" src="'+ item +'"></li>';
      });
      content = '<ul class="layui-layim-face">'+ content +'</ul>';
      layer.popBottom({
        content: content,
        success: function(elem){
          var list = $(elem).find('.layui-layim-face').children('li')
          touch(list, function(){
            focusInsert(input[0], '' +  this.title + '', true);
            input.next()[input.val() === '' ? 'addClass' : 'removeClass']('layui-disabled');
            return false;
          });
        }
      });
      
      $(document).off('touchend', events.faceHide).on('touchend', events.faceHide);
      stope(e);
      
    } ,faceHide: function(){
      layer.close(layer.popBottom.index);
    },
    
    //图片或一般文件
    image: function(othis){
      var type = othis.data('type') || 'images', api = {
        images: 'uploadImage'
        ,file: 'uploadFile'
      }
      ,thatChat = thisChat(), conf = cache.base[api[type]] || {};
      upload({
        url: conf.url || ''
        ,method: conf.type
        ,elem: othis.find('input')[0]
        ,unwrap: true
        ,type: type
        ,success: function(res){
          if(res.code == 0){
            res.data = res.data || {};
            if(type === 'images'){
              focusInsert(thatChat.textarea[0], 'img['+ (res.data.src||'') +']');
            } else if(type === 'file'){
              focusInsert(thatChat.textarea[0], 'file('+ (res.data.src||'') +')['+ (res.data.name||'下载文件') +']');
            }
            sendMessage();
          } else {
            layer.msg(res.msg||'上传失败');
          }
        }
      });
    },
    
    //扩展工具栏
    extend: function(othis){
      var filter = othis.attr('lay-filter'),
      thatChat = thisChat();
      
      layui.each(call['tool('+ filter +')'], function(index, item){
        item && item.call(othis, function(content){
          focusInsert(thatChat.textarea[0], content);
        }, sendMessage, thatChat);
      });
    },
    
    //弹出新的朋友面板
    newFriend: function(){
      layui.each(call.newFriend, function(index, item){
        item && item();
      });
    },
    
    //弹出群组面板
    group: function(){
      popPanel({
        title: '群聊'
        ,tpl: '<div style="padding: 20px 0; text-align: center; color: #666;">尚未开放，请先采用私聊</div>'
        ,data: {}
      });
    },
    
    //播放音频
    playAudio: function(othis){
      var audioData = events.playAudio.audio,
      audio = audioData || document.createElement('audio'),
      pause = function(){
        audio.pause();
        othis.removeAttr('status');
        othis.find('i').html('&#xe652;');
      };
      if(!audio.play){
        return layer.msg('您的浏览器不支持audio');
      }
      if(othis.attr('status')){   
        pause();
      } else {
        audioData || (audio.src = othis.data('src'));
        audio.play();
        othis.attr('status', 'pause');
        events.playAudio.audio = audio;
        othis.find('i').html('&#xe651;');
        //播放结束
        audio.onended = function(){
          pause();
        };
        //播放异常
        audio.onerror = function(){
          layer.msg('播放音频源异常');
        };
      } 
    },
    
    //播放视频
    playVideo: function(othis){
      var videoData = othis.data('src')
      ,video = document.createElement('video');
      if(!video.play){
        return layer.msg('您的浏览器不支持video');
      }
      layer.close(events.playVideo.index);
      events.playVideo.index = layer.open({
        type: 1
        ,style: 'width: 100%; height: 50%;'
        ,content: '<div style="background-color: #000; height: 100%;"><video style="position: absolute; width: 100%; height: 100%;" src="'+ videoData +'" autoplay="autoplay"></video></div>'
      });
    },
    
    //聊天记录
    chatLog: function(othis){
			console.log(call.chatlog)
      var thatChat = thisChat();
      layui.each(call.chatlog, function(index, item){
        item && item(thatChat.data, thatChat.elem.find('.layim-chat-main>ul'));
				var chatdata = thatChat.data;var ul = thatChat.elem.find('.layim-chat-main>ul');
				var lastmsgid = ul.children(".layim-chat-li").eq(0).attr('data-msgid');
				console.log(lastmsgid);
				$.post('',{op:'gethistory',type:chatdata.type,mid:chatdata.mid,lastmsgid:lastmsgid},function(data){
					if(data && data.length > 0){
						var lastmsgtime = 9999999999999999999;
						for(var i=0;i<data.length;i++){
							if(lastmsgtime*1 - data[i].createtime*1> 60 && i>0){
								ul.prepend('<li class="layim-chat-system"><span>'+ layui.data.date(data[i-1].createtime*1000) +'</span></li>');
								lastmsgtime = data[i].createtime*1;
							}
							if(chatdata.type == 'kefu'){
								if(data[i].fromid == mine.id){
									data[i].isreply = 1;
								}
								data[i].nickname = kfdata[data[i].fromid]['nickname'];
								data[i].headimg = kfdata[data[i].fromid]['headimg'];
							}
							ul.prepend(laytpl(elemChatMain).render(data[i]));
							//console.log(data[i].createtime + '--' + lastmsgtime)
						}
						
					}else{
						layer.msg('没有更多记录了');
						$(ul).prev('.layim-chat-system').remove();
					}
				})
      });
    },
    
    //更多列表
    moreList: function(othis){
      var filter = othis.attr('lay-filter');
      layui.each(call.moreList, function(index, item){
        item && item({
          alias: filter
        });
      });
    },
    
    //关于
    about: function(){
      layer.open({
        content: '<p style="padding-bottom: 5px;">LayIM属于付费产品，欢迎通过官网获得授权，促进良性发展！</p><p>当前版本：layim mobile v'+ v + '</p><p>版权所有：<a href="http://layim.layui.com" target="_blank">layim.layui.com</a></p>'
        ,className: 'layim-about'
      });
    }
    
  };
  
  //暴露接口
  exports('myim-mobile', new LAYIM());

}).addcss(
  'modules/myim/mobile/layim.css?v=2.00',
  'skinlayim-mobilecss'
);