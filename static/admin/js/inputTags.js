layui.define(['jquery','layer'],function(exports){
  "use strict";
  var $ = layui.jquery,layer = layui.layer
  

  //外部接口
  ,inputTags = {
    config: {}

    //设置全局项
    ,set: function(options){
      var that = this;
      that.config = $.extend({}, that.config, options);
      return that;
    }

    // 事件监听
    ,on: function(events, callback){
      return layui.onevent.call(this, MOD_NAME, events, callback)
    }
    
  }

   //操作当前实例
  ,thisinputTags = function(){
    var that = this
    ,options = that.config;

    return {
      config: options
    }
  }

  //字符常量
  ,MOD_NAME = 'inputTags'


  // 构造器
  ,Class = function(options){
    var that = this;
    that.config = $.extend({}, that.config, inputTags.config, options);
    that.render();
  };

   //默认配置
  Class.prototype.config = {
    close: false  //默认:不开启关闭按钮
    ,theme: ''   //背景:颜色
    ,content: [] //默认标签
    ,aldaBtn: false //默认配置
    ,num: 0//最多显示个数
  };

  // 初始化
  Class.prototype.init = function(){
    var that = this
    ,spans = ''
    ,options = that.config
    ,span = document.createElement("span"),
    spantext = $(span).text("获取全部数据").addClass('albtn');
    if(options.aldaBtn){
      $('body').append(spantext)
    }
    
    $.each(options.content,function(index,item){
      spans +='<span><em style="font-style: normal;">'+item+'</em><button type="button" class="close">×</button></span>';
      // $('<div class="layui-flow-more"><a href="javascript:;">'+ ELEM_TEXT +'</a></div>');
    })
    options.elem.before(spans)
    that.events()
  }

  Class.prototype.render = function(){
    var that = this
    ,options = that.config
    options.elem = $(options.elem);
    that.enter()
  };

  // 回车生成标签
  Class.prototype.enter = function(){
     
    var that = this
    ,spans = ''
    ,options = that.config;
    options.elem.focus();
    options.elem.keypress(function(event){  
      var keynum = (event.keyCode ? event.keyCode : event.which);  
      if(keynum == '13'){  
        var $val = options.elem.val().trim();       
        if(!$val) return false;        
        if(options.content.indexOf($val) == -1){  
          // 判断设置标签数量
          
          if(options.num != 0 && options.content.length >= options.num){
            layer.msg('最多只能设置'+options.num+'个标签');
            that.render()
            return false;
          }    
          options.content.push($val)
          that.render()
          spans ='<span><em style="font-style: normal;">'+$val+'</em><button type="button" class="close">×</button></span>';
          options.elem.before(spans)
        }
        options.done && typeof options.done === 'function' && options.done($val);
        options.elem.val('');
      }   
    })
  };
  
  //事件处理
  Class.prototype.events = function(){
     var that = this
    ,options = that.config;
    $('.albtn').on('click',function(){
      console.log(options.content)
    })
    $('.layui-tags').on('click','.close',function(){
      var Thisremov = $(this).parent('span').remove(),
      ThisText = $(Thisremov).find('em').text();
      options.content.splice($.inArray(ThisText,options.content),1)
			options.done && typeof options.done === 'function' && options.done(options.content);
    })
  };

  //核心入口
  inputTags.render = function(options){
    var inst = new Class(options);
    inst.init();
    return thisinputTags.call(inst);
  };
  exports('inputTags',inputTags);
})
