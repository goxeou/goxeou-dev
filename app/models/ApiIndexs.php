<?php



namespace app\models;
use think\facade\Db;
class ApiIndexs
{

	public static function loginset($aid,$sysset){
        $loginset_type = 0;
        $loginset_data = '';

        //读取登录设置信息
        $loginset = Db::name('designer_login')->where('aid',$aid)->field('id,data,type')->find();
        if($loginset){
            if(!empty($loginset['data'])){
                $loginset_data = json_decode($loginset['data'],true);
            }
            if(!empty($loginset['type'])){
                $loginset_type = $loginset['type'];
            }
        }else{

            $logo    = PRE_URL.'/static/imgsrc/logo.jpg';
            $sysname = '';
            $bgcolor = '';
            if($sysset){
                if(!empty($sysset['logo'])){
                    $logo = $sysset['logo'];
                }
                $sysname = !empty($sysset['name'])?$sysset['name']:'';
                $bgcolor = !empty($sysset['color1'])?$sysset['color1']:'';
            }

            $loginset = array(
                'aid'=>$aid,
                'type'=>1,
                'updatetime'=>time(),
                'data'=>jsonEncode([
                    "logo"      => $logo,
                    "bgtype"    => 1,
                    "bgcolor"   => $bgcolor,
                    "bgimg"     => PRE_URL.'/static/admin/img/login/bg1.png',
                    "cardcolor" => '#FFFFFF',
                    "titletype" => 'center',
                    "title"     => '欢迎使用'.$sysname,
                    "titlecolor"=> '#000000',
                    "subhead"   => '',
                    "subheadcolor" => '#A8B5D3',
                    "btntype"   => 1,
                    "btncolor"   => '#0256FF',
                    "btnwordcolor" => '#FFFFFF',
                    "codecolor" => '#0256FF',
                    "regtipcolor"  => '#666666',
                    "regpwdbtncolor"  => '#666666',
                    "xytipword"    => '我已阅读并同意',
                    "xytipcolor"   => '#D8D8D8',
                    "xycolor"  => '#0256FF'
                ])
            );
            Db::name('designer_login')->insertGetId($loginset);

            $loginset_type = 1;
            $loginset_data = $loginset_data = json_decode($loginset['data'],true);
        }

        return ['loginset_type'=>$loginset_type,'loginset_data'=>$loginset_data];
    }
}