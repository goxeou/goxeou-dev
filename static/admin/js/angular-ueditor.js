
/**
Created by Dio on #288 17-9.
http://inhu.net
 */

(function() {
  "use strict";
  (function() {
    var NGUeditor;
    NGUeditor = angular.module("ng.ueditor", []);
    NGUeditor.directive("ueditor", [
      function() {
        return {
          restrict: "C",
          require: "ngModel",
          scope: {
            config: "=",
            ready: "="
          },
          link: function($S, element, attr, ctrl) {
            var _NGUeditor, _updateByRender;
            _updateByRender = false;
            _NGUeditor = (function() {
              function _NGUeditor() {
                this.bindRender();
                this.initEditor();
                return;
              }


              /**
               * 初始化编辑器
               * @return {[type]} [description]
               */

              _NGUeditor.prototype.initEditor = function() {
                var _UEConfig, _editorId, _self;
                _self = this;
                if (typeof UE === 'undefined') {
                  console.error("Please import the local resources of ueditor!");
                  return;
                }
                _UEConfig = $S.config ? $S.config : {
                    'autoClearinitialContent' : false,
                    //'toolbars' : [['fullscreen', 'source', 'preview', '|', 'bold', 'italic', 'underline', 'strikethrough', 'forecolor', 'backcolor', '|',
                    //        'justifyleft', 'justifycenter', 'justifyright', '|', 'insertorderedlist', 'insertunorderedlist', 'blockquote', 'emotion', 'insertvideo',
                    //        'link', 'removeformat', '|', 'rowspacingtop', 'rowspacingbottom', 'lineheight','indent', 'paragraph', 'fontsize', '|',
                    //        'inserttable', 'deletetable', 'insertparagraphbeforetable', 'insertrow', 'deleterow', 'insertcol', 'deletecol',
                    //        'mergecells', 'mergeright', 'mergedown', 'splittocells', 'splittorows', 'splittocols', '|', 'anchor', 'map', 'print', 'drafts']],
                    'elementPathEnabled' : false,
                    'initialFrameHeight':480,
                    'focus' : false,
										'autoHeightEnabled':false,
										'imageScaleEnabled':true
                    //'maximumWords' : 9999999999999,
                    //'highlightJsUrl':'./resource/components/ueditor/third-party/SyntaxHighlighter/shCore.js',
                    //'highlightCssUrl':'./resource/components/ueditor/third-party/SyntaxHighlighter/shCoreDefault.css',
                };
                _editorId = attr.id ? attr.id : "_editor" + (Date.now()) + parseInt(Math.random()*100000000);
                element[0].id = _editorId;
                this.editor = new UE.ui.Editor(_UEConfig);
                this.editor.render(_editorId);
                return this.editor.ready(function() {
                  _self.editorReady = true;
                  _self.editor.addListener("contentChange", function() {
                    ctrl.$setViewValue(_self.editor.getContent());
                    if (!_updateByRender) {
                      if (!$S.$$phase) {
                        $S.$apply();
                      }
                    }
                    _updateByRender = false;
                  });
                  if (_self.modelContent && _self.modelContent.length > 0) {
                    _self.setEditorContent();
                  }
                  if (typeof $S.ready === "function") {
                    $S.ready(_self.editor);
                  }
                  $S.$on("$destroy", function() {
                    if (!attr.id && UE.delEditor) {
                      UE.delEditor(_editorId);
                    }
                  });
                });
              };

              _NGUeditor.prototype.setEditorContent = function(content) {
                if (content == null) {
                  content = this.modelContent;
                }
                if (this.editor && this.editorReady) {
                  this.editor.setContent(content);
                }
              };

              _NGUeditor.prototype.bindRender = function() {
                var _self;
                _self = this;
                ctrl.$render = function() {
                  _self.modelContent = (ctrl.$isEmpty(ctrl.$viewValue) ? "" : ctrl.$viewValue);
                  _updateByRender = true;
                  _self.setEditorContent();
                };
              };

              return _NGUeditor;

            })();
            new _NGUeditor();
          }
        };
      }
    ]);
		NGUeditor.directive("slider",[
			function(){
        return {
          restrict: "C",
          require: "ngModel",
          scope: {
            config: "=",
            ready: "="
          },
          link: function($S, element, attr, ctrl) {
						var _sliderId = attr.id ? attr.id : "_slider" + (Date.now()) + parseInt(Math.random()*100000000);
            element[0].id = _sliderId;
						var min = parseInt(attr.min ? attr.min : 0);
						var max = parseInt(attr.max ? attr.max : 100);
						ctrl.$render = function() {
							layui.slider.render({
								elem: '#'+_sliderId,min:min,max:max,
								value:(ctrl.$isEmpty(ctrl.$viewValue) ? "0" : ctrl.$viewValue),
								change:function(radius){
									ctrl.$setViewValue(radius);
								}
							});
						}
					}
				}
		}]);
		
		NGUeditor.directive("colorpicker",[
			function(){
        return {
          restrict: "C",
          require: "ngModel",
          scope: {
            config: "=",
            ready: "="
          },
          link: function($S, element, attr, ctrl) {
						ctrl.$render = function() {
							var _colorpickerId = attr.id ? attr.id : "_colorpicker" + (Date.now()) + parseInt(Math.random()*100000000);
							element[0].id = _colorpickerId;
							layui.colorpicker.render({
								elem: '#'+_colorpickerId,
								format:(attr.coloralpha=='1'? 'rgb':'hex'),
								alpha: (attr.coloralpha=='1'? true:false),
								color:(ctrl.$isEmpty(ctrl.$viewValue) ? "#FFFFFF" : ctrl.$viewValue),
								predefine: true,
								colors: ['#ff4444','#e64340','#ec8b89','#ed3f14','#ff9900',
									'#06bf04','#179b16','#9ed99d','#19be6b',
									'#3388ff','#2b85e4','#5cadff',
									'#000000','#333333','#666666','#999999','#c9c9c9','#f7f7f8','#1c2438','#495060','#dddee1','#e9eaec'],
								change:function(color){
									if(attr.coloralpha!='1') color = color.toUpperCase()
									ctrl.$setViewValue(color);
								}
							});
						}
					}
				}
		}])
		NGUeditor.directive("switch",[
			function(){
        return {
          restrict: "C",
          require: "ngModel",
          scope: {
            config: "=",
            ready: "="
          },
          link: function($S, element, attr, ctrl) {
						var _switchId = attr.filter ? attr.filter : "_switch" + (Date.now()) + parseInt(Math.random()*100000000);
            $(element[0]).attr('lay-filter',_switchId);
						ctrl.$render = function() {
							$(element[0]).prop('checked',ctrl.$viewValue);
							if(ngisfinish==1){
								layui.form.render('checkbox');
							}
							layui.form.on('switch('+_switchId+')', function(data){
								ctrl.$setViewValue(data.elem.checked);
							})
						}
					}
				}
		}]);
		NGUeditor.directive("checkbox",[
			function(){
        return {
          restrict: "C",
          require: "ngModel",
          scope: {
            config: "=",
            ready: "="
          },
          link: function($S, element, attr, ctrl) {
						var _checkboxId = attr.filter ? attr.filter : "_checkbox" + (Date.now()) + parseInt(Math.random()*100000000);
            $(element[0]).attr('lay-filter',_checkboxId);
						ctrl.$render = function() {
							$(element[0]).prop('checked',ctrl.$viewValue);
							if(ngisfinish==1){
								layui.form.render('checkbox');
							}
							layui.form.on('checkbox('+_checkboxId+')', function(data){
								ctrl.$setViewValue(data.elem.checked);
							})
						}
					}
				}
		}]);
		NGUeditor.directive("radio",[
			function(){
        return {
          restrict: "C",
          require: "ngModel",
          scope: {
            config: "=",
            ready: "="
          },
          link: function($S, element, attr, ctrl) {
						var _radioId = attr.filter ? attr.filter : "_radio" + (Date.now()) + parseInt(Math.random()*100000000);
            $(element[0]).attr('lay-filter',_radioId);
						ctrl.$render = function() {
							//console.log(ctrl.$viewValue)
							if(ctrl.$viewValue == $(element[0]).attr('value')){
								$(element[0]).prop('checked',true);
							}
							if(ngisfinish==1){
								//console.log('1234567890')
								layui.form.render('radio');
							}
							layui.form.on('radio('+_radioId+')', function(data){
								ctrl.$setViewValue(data.value);
								$(data.elem).siblings("input[type=radio]").prop('checked',false);
								//layui.form.render('radio');
							})
						}
					}
				}
		}]);
  })();

}).call(this);

//# sourceMappingURL=angular-ueditor.js.map
