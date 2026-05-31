//开关 radio联动下方div显示隐藏
layui.form.on('radio(diandaSwitch)', function(data){
    // console.log(data.elem.name);
    if(data.value == '1'){
        $("div[diandaSwitch='"+data.elem.name+"']").show();
    }else{
        $("div[diandaSwitch='"+data.elem.name+"']").hide();
    }
})

//多开关切换 radio联动下方div显示隐藏，1,2,3等多个
layui.form.on('radio(diandaSwitchMulti)', function(data){
    // console.log(data.elem.name);
    // console.log(data.value);
    $("div[diandaSwitchMulti='"+data.elem.name+"']").hide();
    $("div[diandaSwitchMulti='"+data.elem.name+"'][data-val='"+data.value+"']").show();
})

//全局回车搜索
document.onkeydown=function(event){
    var e = event || window.event || arguments.callee.caller.arguments[0];
    if(e && e.keyCode==13){
        if(!$(".layui-layer-shade").length){ //有弹窗不能搜索
            $(".layuiadmin-button-btn").click();
        }
    }
}

//阻止回车出现多个弹窗
$('body').off('click').on('click','button',function () {
    $(this).blur();
});