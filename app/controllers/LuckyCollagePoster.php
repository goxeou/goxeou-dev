<?php


// +----------------------------------------------------------------------
// | 拼团海报
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class LuckyCollagePoster extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	public function index(){
        $this->defaultSet();
		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$field = input('param.field') ? input('param.field') : 'lucky_collage';
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('platform',$type)->where('type',$field)->order('id')->find();
		if(!$posterset){
			$data_product_mp = jsonEncode([
				'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
				'poster_data' => [
					['left' => '221px','top' => '446px','type' => 'qrmp','width' => '94px','height' => '94px','size' => '',],
					['left' => '30px','top' => '70px','type' => 'pro_img','width' => '285px','height' => '285px'],
					['left' => '30px','top' => '370px','type' => 'textarea','width' => '286px','height' => '47px','size' => '16px','color' => '#000','content' => '[商品名称]'],
					['left' => '34px','top' => '452px','type' => 'head','width' => '47px','height' => '47px','radius' => '100'],
					['left' => '89px','top' => '459px','type' => 'text','width' => '50px','height' => '18px','size' => '16px','color' => '#333333','content' => '[昵称]'],
					['left' => '90px','top' => '484px','type' => 'text','width' => '98px','height' => '14px','size' => '12px','color' => '#B6B6B6','content' => '推荐给你一个宝贝'],
					['left' => '35px','top' => '516px','type' => 'text','width' => '142px','height' => '22px','size' => '20px','color' => '#FD0000','content' => '￥[商品销售价]'],
					['left' => '125px','top' => '518px','type' => 'text','width' => '135px','height' => '16px','size' => '14px','color' => '#BBBBBB','content' => '原价:￥[商品市场价]']
				]
			]);
			$data_product_wx = jsonEncode([
				'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
				'poster_data' => [
					['left' => '221px','top' => '446px','type' => 'qrwx','width' => '94px','height' => '94px','size' => '',],
					['left' => '30px','top' => '70px','type' => 'pro_img','width' => '285px','height' => '285px'],
					['left' => '30px','top' => '370px','type' => 'textarea','width' => '286px','height' => '47px','size' => '16px','color' => '#000','content' => '[商品名称]'],
					['left' => '34px','top' => '452px','type' => 'head','width' => '47px','height' => '47px','radius' => '100'],
					['left' => '89px','top' => '459px','type' => 'text','width' => '50px','height' => '18px','size' => '16px','color' => '#333333','content' => '[昵称]'],
					['left' => '90px','top' => '484px','type' => 'text','width' => '98px','height' => '14px','size' => '12px','color' => '#B6B6B6','content' => '推荐给你一个宝贝'],
					['left' => '35px','top' => '516px','type' => 'text','width' => '142px','height' => '22px','size' => '20px','color' => '#FD0000','content' => '￥[商品销售价]'],
					['left' => '125px','top' => '518px','type' => 'text','width' => '135px','height' => '16px','size' => '14px','color' => '#BBBBBB','content' => '原价:￥[商品市场价]']
				]
			]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'lucky_collage','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'lucky_collage','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'lucky_collage','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'lucky_collage','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'lucky_collage','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'lucky_collage','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'lucky_collage','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'lucky_collage','platform'=>'app','content'=>$data_product_mp]);
			$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','lucky_collage')->where('platform',$type)->order('id')->find();
		}

		$posterdata = json_decode($posterset['content'],true);

		$poster_bg = $posterdata['poster_bg'];
		$poster_data = $posterdata['poster_data'];

		View::assign('type',$type);
		View::assign('field',$field);
		View::assign('poster_bg',$poster_bg);
		View::assign('poster_data',$poster_data);
		return View::fetch();
    }
	public function save(){
		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$field = input('param.field') ? input('param.field') : 'lucky_collage';
		$poster_bg = input('post.poster_bg');
		$poster_data = input('post.poster_data');
		$data_index = ['poster_bg'=>$poster_bg,'poster_data'=>json_decode($poster_data)];
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('platform',$type)->where('type',$field)->order('id')->find();
		Db::name('admin_set_poster')->where('id',$posterset['id'])->update(['content'=>json_encode($data_index)]);
		if(input('post.clearhistory') == 1){
			Db::name('member_poster')->where('aid',aid)->where('posterid',$posterset['id'])->delete();
			$msg = '保存成功';
		}else{
			$msg ='保存成功';
		}
		\app\commons\System::plog('编辑拼团分享海报');
		return json(['status'=>1,'msg'=>$msg,'url'=>true]);
	}
    function defaultSet(){
        $set = Db::name('lucky_collage_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('lucky_collage_sysset')->insert(['aid'=>aid]);
        }
    }
}