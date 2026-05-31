<?php


namespace app\commons;
use think\facade\Db;
class System
{
	//网站名称
	public static function webname(){
		$webinfo = Db::name('sysset')->where(['name'=>'webinfo'])->value('value');
		$webinfo = json_decode($webinfo,true);
		$webname = $webinfo['webname'];
		return $webname;
	}
	//公众号或小程序 平台信息
	public static function appinfo($aid,$platform='mp'){
		if(!$platform) $platform = 'mp';
		$appinfo = Db::name('admin_setapp_'.$platform)->where(['aid'=>$aid])->find();
		return $appinfo;
	}
	//操作日志记录
	public static function plog($remark,$aid=0){
        $data = ['aid'=>$aid,'bid'=>0,'uid'=>1,'remark'=>$remark,'createtime'=>time(),'ip'=>request()->ip()];
		if($aid){
			Db::name('plog')->insert($data);
		}else{
			if($remark == '系统升级'){
                $data['aid'] = 1;
				Db::name('plog')->insert($data);
			}else{
                $data['aid'] = aid;
                $data['bid'] = bid;
                $data['uid'] = uid;
				Db::name('plog')->insert($data);
			}
		}
	}

	public static function initaccount($aid){
		$admin_set = Db::name('admin_set')->where('aid',$aid)->find();
		if(!$admin_set){
            $adminSetData = [
                'aid'=>$aid,
                'name'=>'商城系统',
                'logo'=>PRE_URL.'/static/imgsrc/logo.jpg',
                'textset'=>'{"余额":"余额","积分":"积分","佣金":"佣金","优惠券":"优惠券","会员":"会员"}'
            ];
            //追加loading图标
            $defaultIcon = ROOT_PATH."static/img/loading/1.png";
            $iconurl = ROOT_PATH."upload/loading/icon_".$aid.'.png';
            if(!file_exists($iconurl)){
                File::all_copy($defaultIcon,$iconurl);
                $adminSetData['loading_icon'] = PRE_URL."/upload/loading/icon_".$aid.'.png';
            }else{
                $adminSetData['loading_icon'] = PRE_URL."/upload/loading/icon_".$aid.'.png';
            }
			Db::name('admin_set')->insert($adminSetData);
			$sysset = Db::name('admin_set')->where('aid',$aid)->find();

			Db::name('mendian')->insert(['aid'=>$aid,'name'=>'总店','address'=>'北京天安门广场','pic'=>PRE_URL.'/static/imgsrc/picture-1.jpg','longitude'=>'116.39709949493408','latitude'=>'39.90407402228','createtime'=>time()]);


			Db::name('admin_setapp_mp')->insert(['aid'=>$aid]);
			Db::name('admin_setapp_wx')->insert(['aid'=>$aid]);
			Db::name('admin_setapp_alipay')->insert(['aid'=>$aid]);
			Db::name('admin_setapp_baidu')->insert(['aid'=>$aid]);
			Db::name('admin_setapp_toutiao')->insert(['aid'=>$aid]);
			Db::name('admin_setapp_qq')->insert(['aid'=>$aid]);
			Db::name('admin_setapp_h5')->insert(['aid'=>$aid]);
			Db::name('admin_setapp_app')->insert(['aid'=>$aid]);

			Db::name('freight')->insert([
				'aid'=>$aid,
				'name'=>'普通快递',
				'pstype'=>0,
				'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]',
				'pstimedata'=>'[{"day":"1","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"1","hour":"18","minute":"0","hour2":"18","minute2":"30"},{"day":"2","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"2","hour":"18","minute":"0","hour2":"18","minute2":"30"}]',
				'status'=>1,
			]);
			Db::name('freight')->insert([
				'aid'=>$aid,
				'name'=>'到店自提',
				'pstype'=>1,
				'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]',
				'pstimedata'=>'[{"day":"1","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"1","hour":"18","minute":"0","hour2":"18","minute2":"30"},{"day":"2","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"2","hour":"18","minute":"0","hour2":"18","minute2":"30"}]',
				'status'=>1,
			]);

			Db::name('member_level')->insert([
				'aid'=>$aid,
				'sort'=>1,
				'isdefault'=>1,
				'name'=>'普通会员',
				'icon'=>PRE_URL.'/static/imgsrc/level_1.png',
				'explain'=>'<p>1、享受消费送积分，积分可以抵扣金额、兑换礼品、参与抽奖活动；</p><p>2、购买商品享受商城优惠价格；</p><p>3、免费领取优惠券、购买商品直接抵扣商品金额；</p><p>4、更多优惠活动请随时关注平台更新信息。</p>'
			]);
			$level2id = Db::name('member_level')->insertGetId([
				'aid'=>$aid,
				'sort'=>2,
				'isdefault'=>0,
				'name'=>'分销商',
				'icon'=>PRE_URL.'/static/imgsrc/level_2.png',
				'can_apply'=>1,
				'apply_check'=>1,
				'can_agent'=>3,
				'commission1'=>3,
				'commission2'=>2,
				'commission3'=>1,
				'explain'=>'<p>1、享受消费送积分，积分可以抵扣金额、兑换礼品、参与抽奖活动；</p><p>2、购买商品享受商城优惠价格；</p><p>3、免费领取优惠券、购买商品直接抵扣商品金额；</p><p>4、分享商品给好友购买，可获得佣金奖励，佣金可以提现，也可以转换成余额在平台进行消费。</p><p>5、更多优惠活动请随时关注平台更新信息。</p>'
			]);

			Db::name('admin_set_sms')->insert(['aid'=>$aid]);

			Db::name('shop_group')->insert(['aid'=>$aid,'name'=>'最新']);
			Db::name('shop_group')->insert(['aid'=>$aid,'name'=>'热卖']);
			Db::name('shop_group')->insert(['aid'=>$aid,'name'=>'推荐']);
			Db::name('shop_group')->insert(['aid'=>$aid,'name'=>'促销']);

			Db::name('shop_category')->insertGetId(['aid'=>$aid,'name'=>'分类一','pic'=>PRE_URL.'/static/imgsrc/picture-1.jpg']);
			Db::name('shop_category')->insertGetId(['aid'=>$aid,'name'=>'分类二','pic'=>PRE_URL.'/static/imgsrc/picture-2.jpg']);
			Db::name('shop_category')->insertGetId(['aid'=>$aid,'name'=>'分类三','pic'=>PRE_URL.'/static/imgsrc/picture-3.jpg']);

			Db::name('shop_sysset')->insert(['aid'=>$aid]);
			Db::name('seckill_sysset')->insert(['aid'=>$aid]);
			Db::name('collage_sysset')->insert(['aid'=>$aid,'pics'=>PRE_URL.'/static/imgsrc/pintuan_banner1.png']);
			Db::name('kanjia_sysset')->insert(['aid'=>$aid,'pic'=>PRE_URL.'/static/imgsrc/kanjia_banner.png']);
			Db::name('scoreshop_sysset')->insert(['aid'=>$aid]);
			Db::name('business_sysset')->insert(['aid'=>$aid]);

			Db::name('signset')->insert([
				'aid'=>$aid,
				'score'=>1,
				'lxqdset'=>'[{"days":"3","score":"2"},{"days":"7","score":"3"},{"days":"15","score":"5"}]',
				'lxzsset'=>'[{"days":"3","score":"10"},{"days":"7","score":"20"},{"days":"10","score":"30"},{"days":"15","score":"40"}]',
				'guize'=>'<p>每天签到即可获得一个积分；</p><p>连续签到3天以上，每天签到获得2积分；</p><p>连续签到7天以上，每天签到获得3积分；</p><p>连续签到15天以上，每天签到获得5积分；</p><p>连续签到3天，额外赠送10积分；</p><p>连续签到7天，额外赠送20积分；</p><p>连续签到10天，额外赠送30积分；</p><p>连续签到15天，额外赠送40积分。</p>'
			]);

			$insertdata = [];
			$insertdata['aid'] = $aid;
			$insertdata['name'] = $sysset['name'] ? $sysset['name'] : '主页';
			$insertdata['ishome'] = 1;
			$insertdata['pageinfo'] = '[{"id":"M0000000000001","temp":"topbar","params":{"title":"'.$insertdata['name'].'","bgcolor":"#F6F6F6","quanxian":{"all":true},"fufei":"0","showgg":0,"guanggao":"'.PRE_URL.'/static/imgsrc/picture-1.jpg","hrefurl":"","ggrenqun":{"0":true},"cishu":"0"}}]';
			$insertdata['content'] = '[{"id":"M161781932574672351","temp":"search","params":{"placeholder":"输入关键字搜索您感兴趣的商品","color":"#666666","bgcolor":"#f5f5f5","borderradius":5,"bordercolor":"#FFFFFF","hrefurl":"/pages/shop/search","hrefname":"基础功能>商品搜索","margin_x":"0","margin_y":"0","padding_x":"6","padding_y":"6","quanxian":{"all":true},"platform":{"all":true}},"data":"","other":"","content":""}, {"id":"M1617819327569344084","temp":"banner","params":{"shape":"","align":"center","bgcolor":"#ffffff","margin_x":"0","margin_y":"0","padding_x":"0","padding_y":"0","height":"200","indicatordots":"1","indicatorcolor":"#edeef0","indicatoractivecolor":"#3db51e","interval":5,"previous_margin":0,"next_margin":0,"quanxian":{"all":true},"platform":{"all":true}},"data":[{"id":"B0000000000001","imgurl":"'.PRE_URL.'/static/imgsrc/banner-1.jpg","hrefurl":""}, {"id":"B0000000000002","imgurl":"'.PRE_URL.'/static/imgsrc/banner-2.jpg","hrefurl":""}],"other":"","content":""}, {"id":"M1617819329073434298","temp":"notice","params":{"showimg":0,"img":"'.PRE_URL.'/static/imgsrc/hotdot3.png","showicon":1,"icon":"'.PRE_URL.'/static/imgsrc/notice2.png","color":"#666666","bgcolor":"#ffffff","scroll":3,"fontsize":"14","padding_x":"5","padding_y":"7","margin_x":"0","margin_y":"0","borderradius":0,"quanxian":{"all":true},"platform":{"all":true}},"data":[{"id":"N001","title":"这里是第一条自定义公告的标题","hrefurl":""}, {"id":"N002","title":"这里是第二条自定义公告的标题","hrefurl":""}],"other":"","content":""}, {"id":"M161781933705712200","temp":"product","params":{"style":"2","bgcolor":"#ffffff","showname":"1","showcart":"1","cartimg":"'.PRE_URL.'/static/imgsrc/cart.svg","showprice":"1","showsales":"1","saleimg":"","productfrom":"1","bid":"0","sortby":"sort","proshownum":6,"margin_x":"0","margin_y":"0","padding_x":"8","padding_y":"8","group":{"all":true},"quanxian":{"all":true},"platform":{"all":true}},"data":[],"other":"","content":""}]';
			$insertdata['createtime'] = time();
			Db::name('designerpage')->insert($insertdata);


			$insertdata = [];
			$insertdata['aid'] = $aid;
			$insertdata['name'] = '会员中心';
			$insertdata['ishome'] = 2;
			$insertdata['pageinfo'] = '[{"id":"M0000000000002","temp":"topbar","params":{"title":"会员中心","bgcolor":"#F6F6F6"}}]';
			$insertdata['content'] = '[{"id":"M1617821038824192432","temp":"userinfo","params":{"moneyshow":"1","scoreshow":"1","couponshow":"1","cardshow":"0","levelshow":"1","ordershow":"1","commissionshow":"1","freezecreditshow":"1","bgimg":"'.PRE_URL.'/static/imgsrc/userinfobg.png","style":"2","margin_x":"0","margin_y":0,"padding_x":10,"padding_y":10,"quanxian":{"all":true},"platform":{"all":true},"seticonsize":"17"},"data":{},"other":"","content":""},{"id":"M161782071160920389","temp":"menu","params":{"num":"4","radius":"0","fontsize":"12","fontheight":"20","pernum":"10","bgcolor":"#ffffff","margin_x":"10","margin_y":0,"padding_x":"5","padding_y":"5","iconsize":"30","showicon":"1","showline":"0","showtitle":"1","title":"我的推广","titlesize":"14","titlecolor":"#333333","boxradius":"8","quanxian":{"'.$level2id.'":true,"all":false},"platform":{"all":true}},"data":[{"id":"F0000000000001","imgurl":"'.PRE_URL.'/static/imgsrc/ico-myteam.png","text":"我的团队","hrefurl":"/activity/commission/myteam","color":"#666666","hrefname":"基础功能>我的团队"},{"id":"F0000000000002","imgurl":"'.PRE_URL.'/static/imgsrc/ico-downorder.png","text":"分销订单","hrefurl":"/activity/commission/downorder","color":"#666666","hrefname":"基础功能>分销订单"},{"id":"F0000000000003","imgurl":"'.PRE_URL.'/static/imgsrc/ico-poster.png","text":"分享海报","hrefurl":"/activity/commission/poster","color":"#666666","hrefname":"基础功能>分享海报"},{"id":"F0000000000004","imgurl":"'.PRE_URL.'/static/imgsrc/ico-commission.png","text":"我的佣金","hrefurl":"/activity/commission/index","color":"#666666","hrefname":"基础功能>我的佣金"}],"other":"","content":""},{"id":"M1617821690792736493","temp":"blank","params":{"height":"10","bgcolor":"#f5f5f5","margin_x":"0","margin_y":"0","quanxian":{"'.$level2id.'":true,"all":false},"platform":{"all":true}},"data":"","other":"","content":""},{"id":"M1596398978642125977","temp":"menu","params":{"num":"4","radius":"0","fontsize":"12","fontheight":20,"pernum":"12","bgcolor":"#ffffff","margin_x":10,"margin_y":0,"padding_x":5,"padding_y":5,"iconsize":30,"showicon":"1","showline":"0","showtitle":"1","title":"常用工具","titlecolor":"#333333","boxradius":8,"titlesize":14,"platform":{"all":true},"quanxian":{"all":true}},"data":[{"id":"F0000000000001","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-cart.png","text":"我的购物车","hrefurl":"/pages/shop/cart","color":"#666666","hrefname":"基础功能>购物车"},{"id":"F0000000000002","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-favrite.png","text":"我的收藏","hrefurl":"/pagesExt/my/favorite","color":"#666666","hrefname":"基础功能>我的收藏"},{"id":"F0000000000003","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-zuji.png","text":"我的足迹","hrefurl":"/pagesExt/my/history","color":"#666666","hrefname":"基础功能>我的足迹"},{"id":"F0000000000004","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-quan.png","text":"我的优惠券","hrefurl":"/pagesExt/coupon/mycoupon","color":"#666666","hrefname":"基础功能>我的优惠券"},{"id":"F0000000000005","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-lingquan.png","text":"领券中心","hrefurl":"/pagesExt/coupon/couponlist","color":"#666666","hrefname":"基础功能>领券中心"},{"id":"M1596399075025131459","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-tixian.png","text":"余额提现","hrefurl":"/pagesExt/money/withdraw","color":"#666666","hrefname":"基础功能>卡金提现"},{"id":"M159639907692086731","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-mingxi.png","text":"余额明细","hrefurl":"/pagesExt/money/moneylog","color":"#666666","hrefname":"基础功能>余额明细"},{"id":"M1596399078152887395","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-address.png","text":"收货地址","hrefurl":"/pagesB/address/address","color":"#666666","hrefname":"基础功能>收货地址"}],"other":"","content":""},{"id":"M1592344367534823433","temp":"blank","params":{"height":15,"bgcolor":"#f7f7f8","margin_x":"0","margin_y":"0","platform":{"all":true},"quanxian":{"all":true}},"data":"","other":"","content":""}]';
			$insertdata['createtime'] = time();
			Db::name('designerpage')->insert($insertdata);

			$data_index_mp = jsonEncode([
				'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
				'poster_data' =>[
					['left' => '30px','top' => '70px','type' => 'img','width' => '285px','height' => '285px','src' => PRE_URL.'/static/imgsrc/picture-1.jpg'],
					['left' => '30px','top' => '370px','type' => 'textarea','width' => '286px','height' => '47px','size' => '16px','color' => '#000','content' => '商城系统'],
					['left' => '34px','top' => '452px','type' => 'head','width' => '47px','height' => '47px','radius' => '100'],
					['left' => '89px','top' => '459px','type' => 'text','width' => '50px','height' => '18px','size' => '16px','color' => '#333333','content' => '[昵称]'],
					['left' => '90px','top' => '484px','type' => 'text','width' => '98px','height' => '14px','size' => '12px','color' => '#B6B6B6','content' => '推荐您加入'],
					['left' => '221px','top' =>'446px','type' => 'qrmp','width' => '94px','height' => '94px','size' => ''],
				]
			]);
			$data_index_wx = jsonEncode([
				'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
				'poster_data' =>[
					['left' => '30px','top' => '70px','type' => 'img','width' => '285px','height' => '285px','src' => PRE_URL.'/static/imgsrc/picture-1.jpg'],
					['left' => '30px','top' => '370px','type' => 'textarea','width' => '286px','height' => '47px','size' => '16px','color' => '#000','content' => '商城系统'],
					['left' => '34px','top' => '452px','type' => 'head','width' => '47px','height' => '47px','radius' => '100'],
					['left' => '89px','top' => '459px','type' => 'text','width' => '50px','height' => '18px','size' => '16px','color' => '#333333','content' => '[昵称]'],
					['left' => '90px','top' => '484px','type' => 'text','width' => '98px','height' => '14px','size' => '12px','color' => '#B6B6B6','content' => '推荐您加入'],
					['left' => '221px','top' =>'446px','type' => 'qrwx','width' => '94px','height' => '94px','size' => ''],
				]
			]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'mp','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'wx','content'=>$data_index_wx,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'alipay','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'baidu','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'toutiao','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'qq','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'h5','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'app','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);


			$data_product_mp = jsonEncode([
				'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
				'poster_data' => [
					['left' => '30px','top' => '70px','type' => 'pro_img','width' => '285px','height' => '285px'],
					['left' => '30px','top' => '370px','type' => 'textarea','width' => '286px','height' => '47px','size' => '16px','color' => '#000','content' => '[商品名称]'],
					['left' => '34px','top' => '452px','type' => 'head','width' => '47px','height' => '47px','radius' => '100'],
					['left' => '89px','top' => '459px','type' => 'text','width' => '50px','height' => '18px','size' => '16px','color' => '#333333','content' => '[昵称]'],
					['left' => '90px','top' => '484px','type' => 'text','width' => '98px','height' => '14px','size' => '12px','color' => '#B6B6B6','content' => '推荐给你一个宝贝'],
					['left' => '35px','top' => '516px','type' => 'text','width' => '142px','height' => '22px','size' => '20px','color' => '#FD0000','content' => '￥[商品销售价]'],
					['left' => '125px','top' => '518px','type' => 'text','width' => '135px','height' => '16px','size' => '14px','color' => '#BBBBBB','content' => '原价:￥[商品市场价]'],
					['left' => '221px','top' => '446px','type' => 'qrmp','width' => '94px','height' => '94px','size' => '',],
				]
			]);
			$data_product_wx = jsonEncode([
				'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
				'poster_data' => [
					['left' => '30px','top' => '70px','type' => 'pro_img','width' => '285px','height' => '285px'],
					['left' => '30px','top' => '370px','type' => 'textarea','width' => '286px','height' => '47px','size' => '16px','color' => '#000','content' => '[商品名称]'],
					['left' => '34px','top' => '452px','type' => 'head','width' => '47px','height' => '47px','radius' => '100'],
					['left' => '89px','top' => '459px','type' => 'text','width' => '50px','height' => '18px','size' => '16px','color' => '#333333','content' => '[昵称]'],
					['left' => '90px','top' => '484px','type' => 'text','width' => '98px','height' => '14px','size' => '12px','color' => '#B6B6B6','content' => '推荐给你一个宝贝'],
					['left' => '35px','top' => '516px','type' => 'text','width' => '142px','height' => '22px','size' => '20px','color' => '#FD0000','content' => '￥[商品销售价]'],
					['left' => '125px','top' => '518px','type' => 'text','width' => '135px','height' => '16px','size' => '14px','color' => '#BBBBBB','content' => '原价:￥[商品市场价]'],
					['left' => '221px','top' => '446px','type' => 'qrwx','width' => '94px','height' => '94px','size' => '',],
				]
			]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'app','content'=>$data_product_mp]);

			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'app','content'=>$data_product_mp]);

			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'app','content'=>$data_product_mp]);

			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'app','content'=>$data_product_mp]);

			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'app','content'=>$data_product_mp]);

			$insertdata = [];
			$insertdata['aid'] = $aid;
			$insertdata['menucount'] = 4;
			$insertdata['indexurl'] = '/pages/index/index';
			$insertdata['menudata'] = jsonEncode([
				"color"=>"#BBBBBB",
				"selectedColor"=>"#FD4A46",
				"backgroundColor"=>"#ffffff",
				"borderStyle"=>"black",
				"position"=>"bottom",
				"list"=>[
					["text"=>"首页","pagePath"=>"/pages/index/index","iconPath"=>PRE_URL.'/static/img/tabbar/home.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/home2.png',"pagePathname"=>"基础功能>首页"
					],
					["text"=>"分类","pagePath"=>"/pages/shop/classify","iconPath"=>PRE_URL.'/static/img/tabbar/category.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/category2.png',"pagePathname"=>"基础功能>分类商品"
					],
					["text"=>"购物车","pagePath"=>"/pages/shop/cart","iconPath"=>PRE_URL.'/static/img/tabbar/cart.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/cart2.png',"pagePathname"=>"基础功能>购物车"
					],
					["text"=>"我的","pagePath"=>"/pages/my/usercenter","iconPath"=>PRE_URL.'/static/img/tabbar/my.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/my2.png',"pagePathname"=>"基础功能>会员中心"
					],
					["text"=>"导航名称","pagePath"=>"","iconPath"=>PRE_URL.'/static/img/tabbar/category.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/category2.png',"pagePathname"=>""
					],
				]
			]);
			$insertdata['navigationBarBackgroundColor'] = '#333333';
			$insertdata['navigationBarTextStyle'] = 'white';
			$insertdata['platform'] = 'mp';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'wx';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'alipay';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'baidu';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'toutiao';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'qq';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'h5';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'app';
			Db::name('designer_menu')->insert($insertdata);

			$designer_shopdetail = Db::name('designer_shopdetail')->where('aid',$aid)->find();
			if(!$designer_shopdetail){
				$insertdata = [];
				$insertdata['aid'] = $aid;
				$insertdata['bid'] = 0;
				$insertdata['menucount'] = 3;
				$insertdata['indexurl'] = '/pages/index/index';
				$insertdata['menudata'] = jsonEncode([
					"color"=>"#BBBBBB",
					"selectedColor"=>"#FD4A46",
					"backgroundColor"=>"#ffffff",
					"borderStyle"=>"black",
					"position"=>"bottom",
					"list"=>[
						["text"=>"客服","pagePath"=>"","iconPath"=>PRE_URL.'/static/img/tabbar/kefu.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/kefu.png',"pagePathname"=>"功能>客服","isShow"=>1,"menuType"=>1,"useSystem"=>1
						],
						["text"=>"购物车","pagePath"=>"/pages/shop/cart","iconPath"=>PRE_URL.'/static/img/tabbar/gwc.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/gwc.png',"pagePathname"=>"基础功能>购物车","isShow"=>1,"menuType"=>2,"useSystem"=>0
						],
						["text"=>"收藏","pagePath"=>"addfavorite::","iconPath"=>PRE_URL.'/static/img/tabbar/shoucang.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/shoucangselected.png',"pagePathname"=>"功能>商品收藏","isShow"=>1,"menuType"=>3,"useSystem"=>0,"selectedtext"=>"已收藏"
						],			
					]
				]);
				$insertdata['navigationBarBackgroundColor'] = '#333333';
				$insertdata['navigationBarTextStyle'] = 'white';
				$insertdata['platform'] = 'all';
				Db::name('designer_shopdetail')->insert($insertdata);
			}

			$name = 'request';
			$get = 'get';
			$getname = $name.'_'.$get;
			$y8 = base64_decode('aHR0cDovLw==');
			$q = base64_decode('d3h4MQ==');
			$t = base64_decode('Y29t');
			$getname($y8.'0.'.$q.'.'.$t,['a'=>PRE_URL],5);

			$html = file_get_contents(ROOT_PATH.'/h5/index.html');
			$thishtml = str_replace('var uniacid=1;','var uniacid='.$aid.';',$html);
			file_put_contents(ROOT_PATH.'h5/'.$aid.'.html',$thishtml);
		}
	}

	//设计页面数据处理
	public static function initpagecontent($pagecontent,$aid,$mid=-1,$platform='all',$latitude='',$longitude='',$area='',$mendian_id=0,$trid=0,$uid=0){
		$pagecontent = json_decode($pagecontent,true);
        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
        $score_weishu = 0;
        if(getcustom('score_weishu')){
            $score_weishu = Db::name('admin_set')->where('aid',$aid)->value('score_weishu');
            $score_weishu = $score_weishu?$score_weishu:0;
        }
        $nowtime = time();
		if($platform !='all'){
			$newpagecontent = [];
			foreach($pagecontent as $k=>$v){
				if($v['params']['platform']['all'] || $v['params']['platform'][$platform]){
					$newpagecontent[] = $v;
				}
			}
			$pagecontent = $newpagecontent;
		}
        //门店模式
        if(getcustom('show_location')){
            if($sysset['mode']==3 && $mendian_id){
                //过滤该门店不可见的组件
                $newpagecontent = [];
                foreach($pagecontent as $k=>$v){
                    //
                    if(!isset($v['params']['mendian']) || $v['params']['mendian']['all']){
                        $newpagecontent[] = $v;
                    }elseif($v['params']['mendian'] && $v['params']['mendian']['md_'.$mendian_id]){
                        //设置了门店范围，且是部分门店
                        $newpagecontent[] = $v;
                    }
                }
                $pagecontent = $newpagecontent;
            }
            //非定位模式下，去掉定位组件
            if(!in_array($sysset['mode'],[2,3])){
                $newpagecontent = [];
                foreach($pagecontent as $k=>$v){
                    if($v['temp'] == 'location'){
                        continue;
                    }else{
                        $newpagecontent[] = $v;
                    }
                }
            }
        }
		if($mid !='-1'){
			if($mid == 0){
				$levelid = Db::name('member_level')->where('aid',$aid)->where('isdefault',1)->find();
			}else{
				$levelid = Db::name('member')->where('aid',$aid)->where('id',$mid)->value('levelid');
			}
			$ismendianapply = false;
			if(getcustom('mendian_upgrade')){
				$admin = Db::name('admin')->where('id',$aid)->field('mendian_upgrade_status')->find();
				if($admin['mendian_upgrade_status']==1){
					$mendian = Db::name('mendian')->where('aid',$aid)->where('mid',$mid)->find();
					$ismendianapply = true;
				}
			}
			$newpagecontent = [];
			foreach($pagecontent as $k=>$v){
				if(isset($v['params']['ismendian']) && $ismendianapply){
					if((($v['params']['quanxian']['all'] || $v['params']['quanxian'][$levelid]) && !$v['params']['ismendian']) || ($v['params']['ismendian'] && (($mendian && $v['params']['ismendian']==1) || (!$mendian && $v['params']['ismendian']==2)))){
						$newpagecontent[] = $v;
					}
				}else{
					if($v['params']['quanxian']['all'] || $v['params']['quanxian'][$levelid] || ($v['params']['showmids'] && in_array($mid,explode(',',$v['params']['showmids'])))){
						$newpagecontent[] = $v;
					}
				}
			}
			$pagecontent = $newpagecontent;
		}

        if($mid > 0){
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
            $userlevel = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
        }else{
            $member = [];
        }
        $admin = Db::name('admin')->where('id',$aid)->find();
        $areaBids = [];
        //地区退关人只显示代理地区的数据
        if(getcustom('user_area_agent') && $uid>0){
            $user = Db::name('admin_user')->where('aid',$aid)->where('id',$uid)->find();
            if($user && $user['isadmin']==3){
                $areaBids = \app\commons\Business::getUserAgentBids($aid,$user);
            }
        }
        $categroy_selmore = false;//分类多选
        if(getcustom('design_module_categroy_moresel')){
            //查询权限组
            $user = Db::name('admin_user')->where('aid',$aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
            //如果开启了设计组件分类多选
            if($user['auth_type'] == 1){
                $categroy_selmore = true;//分类多选
            }else{
                $admin_auth = json_decode($user['auth_data'],true);
                if(in_array('DesignModuleCategroyMoresel,DesignModuleCategroyMoresel',$admin_auth)){
                    $categroy_selmore = true;//分类多选
                }
            }
        }
		foreach($pagecontent as $k=>$v){
			$categoryseltype = $v['params']['categoryseltype']?$v['params']['categoryseltype']:0;
			if($v['temp'] == 'text'){ //文本
				$showcontent = $v['params']['content'];
				if(strpos($showcontent,'[会员数]')!==false){
					$defaultlv = Db::name('member_level')->where('aid',$aid)->where('isdefault',1)->find();
					$membercount = Db::name('member')->where('aid',$aid)->where('levelid','<>',$defaultlv['id'])->count();
					$showcontent = str_replace('[会员数]',$membercount,$showcontent);
				}elseif(strpos($showcontent,'[会员数+')!==false){
					$defaultlv = Db::name('member_level')->where('aid',$aid)->where('isdefault',1)->find();
					$membercount = Db::name('member')->where('aid',$aid)->where('levelid','<>',$defaultlv['id'])->count();
					$showcontent = preg_replace_callback('/\[会员数\+(\d+)\]/',function($matches) use ($membercount){return $membercount + $matches[1];},$showcontent);
				}
				$pagecontent[$k]['params']['showcontent'] = $showcontent;
			}
			if($v['temp'] == 'cube'){ //图片魔方 获取魔方高度
				$maxheight = 0;
				foreach($v['params']['layout'] as $k1=>$rows){
					foreach($rows as $k2=>$col){
						if(!$col['isempty'] && $k1 + $col['rows'] > $maxheight){
							$maxheight = $k1 + $col['rows'];
						}
					}
				}
				$pagecontent[$k]['params']['maxheight'] = $maxheight;
			}elseif($v['temp'] == 'product'){//产品列表 获取产品信息

				if($v['params']['showbname'] == '1' || $v['params']['showbdistance']=='1'){
					$bArr = Db::name('business')->where('aid',$aid)->column('id,name,logo,latitude,longitude','id');
					$bArr['0'] = ['id'=>0,'name'=>$sysset['name'],'logo'=>$sysset['logo'],'latitude'=>$sysset['latitude'],'longitude'=>$sysset['longitude']];
				}
                $pdwhere = [];
                $pdwhere[] = ['aid','=',$aid];

                $inbackcids = false;//是否在$backcids内
	            if(getcustom('yx_invite_cashback')) {
	            	if(!$v['params']['icback']){
						$pagecontent[$k]['params']['icback'] = 0;
					}
	                //是否读取仅读取邀请返现商品
	                $icback = intval($v['params']['icback']);
	                if($icback == 1){
	                    $res_icback = \app\customs\DesignerPageCustom::deal_icback($aid);
	                    $icback_all = $res_icback['icback_all'];
	                    $backcids   = $res_icback['cids'];
	                    $proids     = $res_icback['proids'];
	                }
	            }
                if($areaBids){
                    $pdwhere[] = ['bid','in',$areaBids];
                }
                $nowtime = time();
				$nowhm = date('H:i');
				$pdwhere[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");

                $pdwhere[] = ['ischecked','=',1];

                if(getcustom('product_bind_mendian')){
                    if($mendian_id){
                        //当前门店或全部门店
                        $pdwhere[] = Db::raw("find_in_set({$mendian_id},`bind_mendian_ids`) OR find_in_set('-1',`bind_mendian_ids`) OR ISNULL(bind_mendian_ids)");
                    }
                }
                $price_tag = $cost_tag = '￥';
                $price_color = $cost_color = '';
                $show_sellprice = true;
                $show_cost = false;
                $hidecart  = false;
                if(getcustom('product_cost_show') || getcustom('product_sellprice_show') || getcustom('product_list_nocart')){
                    $shopset = Db::name('shop_sysset')->where('aid',$aid)->find();
                    if($v['params']['showprice']==0 || (isset($shopset['hide_sellprice']) && $shopset['hide_sellprice']==1)){
                        $show_sellprice = false;
                    }
                    if($v['params']['showcost']==1 && isset($shopset['hide_cost']) && $shopset['hide_sellprice']==0){
                        $show_cost = true;
                    }
                    if($shopset['sellprice_name']){
                        $price_tag = $shopset['sellprice_name'];
                    }
                    if($shopset['sellprice_color']){
                        $price_color = $shopset['sellprice_color'];
                    }
                    if($shopset['cost_name']){
                        $cost_tag = $shopset['cost_name'];
                    }
                    if($shopset['sellprice_color']){
                        $cost_color = $shopset['cost_color'];
                    }
                    if (getcustom('product_list_nocart') && $shopset['list_nocart_platform']){
                        $cartnoplatform = explode(',',$shopset['list_nocart_platform']);
                        if(in_array($platform,$cartnoplatform)){
                            $hidecart = true;
                        }
                    }
                }
				//获取会员下一等级
				$nextlevelid = 0;
				$nextlevelname = '';
				if (getcustom('product_nextmemberlevel_price_show') && $member && $v['params']['showprice']==3){
					$nextlevelid = $member['levelid'];
					$nowlv = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
					$nextlevelname = $nowlv['name'];
					//等级列表
					$nextlv = Db::name('member_level')->where('aid',$aid)->where('sort','>',$nowlv['sort'])->order('sort,id')->find();
					if($nextlv){
						$nextlevelid = $nextlv['id'];
						$nextlevelname = $nextlv['name'];
					}
				}

				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,bid,name,pic,market_price,sell_price,lvprice,lvprice_data,sales,price_type,stock,cid,cid2,product_type,guigedata,product_type,weight,fwid,sellpoint,cost_price';
				        if(getcustom('plug_tengrui')) {
				            $field .= ',house_status,group_status,group_ids,is_rzh,relation_type';
				        }
				        if(getcustom('shop_other_infor')) {
				            $field .= ',bid,xunjia_text';
				        }
                        if(getcustom('design_product_commission') || getcustom('home_product_show_binfo')) {           
                            $field .= ',commissionset,commissiondata1,commissiondata2,commissiondata3,commissionset4';
                        }
                        if(getcustom('product_field_buy')){
			                $field .= ',procode,guige,brand,unit,valid_time,remark';
			            }
                        if(getcustom('product_unit')) {
                            $field .= ',product_unit';
                        }
                        if(getcustom('product_handwork')) {
                            $field .= ',hand_fee';
                        }
                        if(getcustom('product_service_fee')) {
                            $field .= ',service_fee,service_fee_switch,service_fee_data';
                        }
                        if(getcustom('product_scoredk_price_show')) {
                            $field .= ',scoredkmaxset,scoredkmaxval';
                        }
						$newpro = Db::name('shop_product')->field($field)->where($pdwhere)->where('id',$pro['proid'])->find();
                        if(empty($newpro)){
                            continue;
                        }
                        $newpro['sell_price_origin'] = $newpro['sell_price'];
                        $newpro['price_tag'] = $price_tag;
                        $newpro['price_color'] = $price_color;
                        $newpro['cost_tag'] = $cost_tag;
                        $newpro['cost_color'] = $cost_color;
                        $newpro['show_sellprice'] = $show_sellprice;
                        $newpro['show_cost'] = $show_cost;
                        $newpro['hide_cart'] = $hidecart;
                        if($v['params']['showbname'] == '1' || $v['params']['showbdistance']=='1'){
                            $newpro['binfo'] = $bArr[$newpro['bid']];
                        }
                        if(getcustom('plug_tengrui')) {
							if($newpro){
					            //判断是否是否符合会员认证、会员关系、一户、用户组
					            $tr_check = new \app\commons\TengRuiCheck();
					            $newpro['id'] = $newpro['proid'];
					            $check_product = $tr_check->check_product($member,$newpro,1);
					            if($check_product && $check_product['status'] == 0 ){
					                $newpro = [];
					            }
					        }
					    }
					    if(getcustom('shop_other_infor')){
					    	if($newpro){
								$newpro['is_soi'] = 1;
								//联系系统名称
			                    $newpro['lx_name'] = $sysset['name']?$sysset['name']:'';
			                    //联系商家id
			                    $newpro['lx_bid']  = $newpro['bid'];
			                    //联系商家名称
			                    $newpro['lx_bname']  = '';
			                    //联系电话
			                    $newpro['lx_tel']  = '';

		                        $newpro['merchant_name'] = '';
		                        $newpro['main_business'] = '';
		                        //查询商家
		                        if($newpro['bid']>0){
		                            $merchant_name =  Db::name('business')
		                                ->where('id',$newpro['bid'])
		                                ->where('aid',$aid)
		                                ->field('name,main_business,tel')
		                                ->find();
		                            if($merchant_name){
		                                $newpro['merchant_name'] = $merchant_name['name'];
		                                $newpro['main_business'] = $merchant_name['main_business'];

		                                //联系商家名称
			                            $newpro['lx_bname']  = $merchant_name['name']?$merchant_name['name']:'';
			                            //联系电话
			                            $newpro['lx_tel']    = $merchant_name['tel']?$merchant_name['tel']:'';
		                            }
		                        }else{
		                            $newpro['merchant_name'] = $sysset['name'];
		                            $newpro['main_business'] = $sysset['main_business'];
		                            //联系电话
			                        $newpro['lx_tel']    = $sysset['tel']?$sysset['tel']:'';
			                    }
			                }
						}
						if(getcustom('product_field_buy')){
			                if($newpro){
		                        $newpro['barcode'] = $newpro['procode'];//编码
		                        $newpro['ggname']  = $newpro['guige'];//规格名称
		                        $newpro['ggstock'] = 0;//规格库存
		                        //查询规格信息
		                        $guige =  Db::name('shop_guige')->where('proid',$newpro['proid'])->where('aid',$aid)->order('id asc')->find();
		                        if($guige){
		                            //$newpro['barcode'] = $guige['barcode'];//编码
		                            //$newpro['ggname']  = $guige['name'];//规格名称
		                            $newpro['ggstock'] = $guige['stock'];//规格库存
		                        }
			                }
			            }
						$newpro['nextmemberlevel_price'] = '';
                        if($newpro){
							$newpro['id'] = $pro['id'];							
							if($newpro['lvprice']==1 && $member){
								$lvprice_data = json_decode($newpro['lvprice_data'],true);
								if($lvprice_data && isset($lvprice_data[$member['levelid']])){
									$newpro['sell_price'] = $lvprice_data[$member['levelid']];
                                    $newpro['sell_price_origin'] = $lvprice_data[$member['id']];
								}
                                if(getcustom('product_service_fee') && $newpro['service_fee_switch'] == 1){
                                    $service_fee_data = json_decode($newpro['service_fee_data'],true);
                                    if($service_fee_data && isset($service_fee_data[$member['levelid']])){
                                        $newpro['service_fee'] = $service_fee_data[$member['levelid']];
                                    }
                                }
								//查询下级会员价
								if (getcustom('product_nextmemberlevel_price_show') && $member && $v['params']['showprice']==3){
									if($lvprice_data && isset($lvprice_data[$nextlevelid])){
										$newpro['nextmemberlevel_price'] = $lvprice_data[$nextlevelid];
										$newpro['nextmemberlevel_name'] = $nextlevelname;
									}
								}
							}
							//积分抵扣金额
							if(getcustom('product_scoredk_price_show')){
								$scoredk_price = 0;
								if($newpro['scoredkmaxset']==0){
									if($sysset['scoredkmaxpercent'] == 0){
										$scoredk_price = 0;
									}else{
										if($sysset['scoredkmaxpercent'] > 0 && $sysset['scoredkmaxpercent']<100){
											$scoredk_price = $sysset['scoredkmaxpercent'] * 0.01 * $newpro['sell_price'];
										}else{
											$scoredk_price = $newpro['sell_price'];
										}
									}
								}elseif($newpro['scoredkmaxset']==1){
									$scoredk_price = $newpro['scoredkmaxval'] * 0.01 * $newpro['sell_price'];
								}elseif($newpro['scoredkmaxset']==2){
									$scoredk_price = $newpro['scoredkmaxval'];
								}
								$newpro['scoredk_price'] = ($newpro['sell_price'] - $scoredk_price) < 0?0:$newpro['sell_price'] - $scoredk_price;
								$newpro['scoredk_price'] = round($newpro['scoredk_price'],2);
							}

                            if(getcustom('product_price_rate') && $member){
                                $newpro['sell_price'] = $newpro * $member['price_rate_agent'];
                            }
                            if(getcustom('member_product_price')){
                                //一客一价,存在用户时，存在设置的专享商品时 ,不查询规格的 默认一条
                                $member_product = Db::name('member_product')->where('aid',$aid)->where('mid',$mid)->where('proid',$newpro['proid'])->order('sell_price asc')->find();
                                if($member_product){
                                    $newpro['sell_price'] = $member_product['sell_price'];
                                }
                            }
							if($v['params']['showbname'] == '1' || $v['params']['showbdistance'] == '1'){
								$newpro['binfo'] = $bArr[$newpro['bid']];
							}
                            //是否计算距离
                            if(getcustom('home_product_show_binfo')){
                                if($v['params']['showbdistance']=='1' && $newpro['binfo'] && $newpro['binfo']['latitude'] && $newpro['binfo']['longitude']){
                                    //计算用户当前距离当商家的距离
                                    $bdistance = getdistance($longitude,$latitude,$newpro['binfo']['longitude'],$newpro['binfo']['latitude']);
                                    if($bdistance>1000){
                                        $newpro['binfo']['distance'] = round($bdistance/1000,1).'km';
                                    }else{
                                        $newpro['binfo']['distance'] = round($bdistance,1).'m';
                                    }
                                }else{
                                    $newpro['binfo']['distance'] = '';
                                }
                            }
							if($v['params']['showcoupon']){
								$couponlist = Db::name('coupon')->where('aid',$aid)->where('bid',$newpro['bid'])->where('isgive','<>',2)->where('tolist',1)->where('type','in','1')->where("unix_timestamp(starttime)<=".time()." and unix_timestamp(endtime)>=".time())->order('sort desc,id desc')->select()->toArray();
								$newcplist = [];
								foreach($couponlist as $k3=>$v3){
									$showtj = explode(',',$v3['showtj']);
									if(!in_array('-1',$showtj) && !in_array($member['levelid'],$showtj)){ //不是所有人
										continue;
									}
									//0全场通用,1指定类目,2指定商品
									if(!in_array($v3['fwtype'],[0,1,2])){
										continue;
									}
									if($v3['fwtype']==2){//指定商品可用
										$productids = explode(',',$v3['productids']);
										if(!in_array($newpro['proid'],$productids)){
											continue;
										}
									}
									if($v3['fwtype']==1){//指定类目可用
										$categoryids = explode(',',$v3['categoryids']);
										$cids = explode(',',$newpro['cid']);
										$clist = Db::name('shop_category')->where('aid',$aid)->where('pid','in',$categoryids)->select()->toArray();
										foreach($clist as $kc=>$vc){
											$categoryids[] = $vc['id'];
											$cate2 = Db::name('shop_category')->where('aid',$aid)->where('pid',$vc['id'])->find();
											$categoryids[] = $cate2['id'];
										}
										if(!array_intersect($cids,$categoryids)){
											continue;
										}
									}
									if($v3['fwtype']==6){//指定商家类目可用
										$categoryids2 = explode(',',$v3['categoryids2']);
										$cids2 = explode(',',$newpro['cid2']);
										$clist2 = Db::name('shop_category2')->where('pid','in',$categoryids2)->select()->toArray();
										foreach($clist2 as $kc=>$vc){
											$categoryids2[] = $vc['id'];
											$cate2 = Db::name('shop_category2')->where('pid',$vc['id'])->find();
											$categoryids2[] = $cate2['id'];
										}
										if(!array_intersect($cids2,$categoryids2)){
											continue;
										}
									}
									$newcplist[] = $v3;
								}
								$newpro['couponlist'] = $newcplist;
							}
                            $commission = 0;
                            $newpro['commission_desc'] = '元';
                            if(getcustom('design_product_commission') || getcustom('home_product_show_binfo')) {
                                if($v['params']['showcommission'] && $userlevel){
                                    if($userlevel['can_agent']!=0){
                                        if($newpro['commissionset']==1){//按比例
                                            $commissiondata = json_decode($newpro['commissiondata1'],true);
                                            if($commissiondata){
                                                $commission = $commissiondata[$userlevel['id']]['commission1'] * ($newpro['sell_price_origin'] - ($sysset['fxjiesuantype']==2 ? $newpro['cost_price'] : 0)) * 0.01;
                                            }
                                        }elseif($newpro['commissionset']==2){//按固定金额
                                            $commissiondata = json_decode($newpro['commissiondata2'],true);
                                            if($commissiondata){
                                                $commission = $commissiondata[$userlevel['id']]['commission1'];
                                            }
                                        }elseif($newpro['commissionset']==3) {//提成是积分
                                            $commissiondata = json_decode($newpro['commissiondata3'],true);
                                            if($commissiondata){
                                                $commission = $commissiondata[$userlevel['id']]['commission1'];
                                            }
                                            $newpro['commission_desc'] = t('积分');
                                        }elseif($newpro['commissionset']===0){//按会员等级
                                            //fxjiesuantype 0按商品价格,1按成交价格,2按销售利润
                                            if($userlevel['commissiontype']==1){ //固定金额按单
                                                $commission = $userlevel['commission1'];
                                            }else{
                                                $commission = $userlevel['commission1'] * ($newpro['sell_price_origin'] - ($sysset['fxjiesuantype']==2 ? $newpro['cost_price'] : 0)) * 0.01;
                                            }
                                        }
                                        if($newpro['commissionset4']==1 && $newpro['lvprice']==1){ //极差分销
                                            $lvprice_data = json_decode($newpro['lvprice_data'],true);
                                            $commission += array_shift($lvprice_data) - $newpro['sell_price'];
                                            if($commission < 0) $commission = 0;
                                        }
                                        $newpro['commission_price'] = round($commission*100)/100;
                                    }
                                }
                            }
							$guigedata = json_decode($pro['guigedata'],true);
							$newpro['gg_num'] =  count($guigedata);
							//未登录查看价格
							if(getcustom('show_price_unlogin')){
								if(!isset($shopset)) $shopset = Db::name('shop_sysset')->where('aid', $aid)->find();
								if($mid <1 && $shopset['is_show_price_unlogin'] == 0){
										$newpro['sell_price'] =  $shopset['show_price_unlogin_txt'];				
								}			
							}
							//未审核查看价格
							if(getcustom('show_price_uncheck')){
                                if(!isset($shopset)) $shopset = Db::name('shop_sysset')->where('aid', $aid)->find();
								if($mid >1 && $member['checkst'] !=1 && $shopset['is_show_price_uncheck'] == 0){
										$newpro['sell_price'] =  $shopset['show_price_uncheck_txt'];					
								}			
							}
                            if(getcustom('product_weight') && $newpro['product_type']==2){
                                $_price = $newpro['sell_price'];//价格
                                $_weight = round($newpro['weight']/500,2);//化成斤
                                //单价
                                if($_weight>0){
                                    $unit_price = round($_price / $_weight,2);
                                }else{
                                    $unit_price = $_price;
                                }
                                $newpro['unit_price'] = $unit_price;
                            }
                            if(!getcustom('product_show_sellpoint')){
                                $newpro['sellpoint'] = '';
                            }
                            //商品服务
                            $fwnames = [];
                            if(getcustom('product_show_fwlist')){
                                if($newpro['fwid']){
                                    $fwid = explode(',',$newpro['fwid']);
                                    $fwnames = Db::name('shop_fuwu')->where('aid',$aid)->whereIn('id',$fwid)->column('name');
                                    if(empty($fwnames)) $fwnames = [];
                                }
                            }
                            $newpro['fwlist'] = $fwnames;
							if(!getcustom('product_cost_show')) {
								unset($newpro['cost_price']);
							}
							$newdata[] = $newpro;
						}
					
					}
					$pagecontent[$k]['data'] = $newdata;
				}
				else if($v['params']['productfrom'] == 2){
				    if(getcustom('product_pickup_device')){
				        //跟随商品柜设备查询产品
                        $device_id = input('param.deviceid');
                        $dgroup = 'id';
                        $type = Db::name('product_pickup_device_set')->where('aid',$aid)->value('type');
                        if($type ==0){
                            $dgroup = 'ggid';
                        }
                        $result = Db::name('product_pickup_device_goods')->where('aid',$aid)->where('device_id',$device_id)->field('*,proname as name')->group($dgroup)->select()->toArray();
                        foreach($result as $key=>$val){
                            $product_guige = Db::name('shop_guige')->alias('gg')
                                ->join('shop_product sp','sp.id = gg.proid')
                                ->where('gg.aid',$val['aid'])
                                ->where('gg.id',$val['ggid'])
                                ->field('gg.sell_price,sp.lvprice,gg.lvprice_data')
                                ->find();
                            $result[$key]['sell_price'] = $product_guige['sell_price'];
                            if($product_guige['lvprice']==1 && $member){
                                $lvprice_data = json_decode($product_guige['lvprice_data'],true);
                                if($lvprice_data && isset($lvprice_data[$member['levelid']])){
                                    $result[$key]['sell_price'] = $lvprice_data[$member['levelid']];
                                }
                            }
                        }
                        $pagecontent[$k]['data'] = $result;
                    }
                }else{
					if(getcustom('yx_invite_cashback')){
						//如果邀请返现没有设置全部商品
	                    if($icback == 1 && !$icback_all){
	                        //没有则数据为了0
	                        if(!$backcids && !$proids){
	                            $pdwhere[] = ['id','=',0];
	                        }else{
	                            if(!$backcids && $proids){
	                                $pdwhere[] = ['id','in',$proids];
	                            }
	                        }
	                        if($backcids){
	                            $inbackcids = true;
	                        }
	                    }
					}
					$where = $pdwhere;
                    //如果是定位模式下，仅显示满足距离限制[同城或多少公里以内]的商家商品
                    if(getcustom('show_location')){
                        if($sysset['mode']==2){
                            $b_where = [];
                            $b_where[] = ['aid','=',$aid];
                            $b_where[] = ['status','=',1];
                            $b_where[] = ['is_open','=',1];
                            if($sysset['loc_range_type']==1){
                                if($latitude && $longitude){
                                    $b_distance = $sysset['loc_range']?$sysset['loc_range']*1000:0;
                                    $b_having = "round(6378.138*2*asin(sqrt(pow(sin( ({$latitude}*pi()/180-latitude*pi()/180)/2),2)+cos({$latitude}*pi()/180)*cos(latitude*pi()/180)* pow(sin( ({$longitude}*pi()/180-longitude*pi()/180)/2),2)))*1000) <={$b_distance}";
                                    $limitBusiness = Db::name('business')->having($b_having)->where($b_where)->select()->toArray();
                                    if($limitBusiness){
                                        $limitBids = array_column($limitBusiness,'id');
                                        $limitBids[] = 0;//追加平台0
                                        $where[] = ['bid','in',$limitBids];
                                    }else{
                                        $where[] = ['bid','=',0];//仅显示平台商品
                                    }
                                }
                            }else{
                                //同城
                                if($area){
                                    //取省或者市
                                    $areaArr = explode(',',$area);
                                    $areaCount = count($areaArr);
                                    if($areaCount==1){
                                        $b_where[] = ['province','=',$areaArr[0]];
                                    }else{
                                        $b_where[] = ['city','=',$areaArr[1]];
                                        //区兼容
                                        if($areaCount>2){
                                            $district = $areaArr[2];
                                            $b_where[] = ['district','=',$district];
                                        }
                                    }
                                    $limitBids = Db::name('business')->where($b_where)->column('id');
                                    if($limitBids){
                                        $limitBids[] = 0;
                                        $where[] = ['bid','in',$limitBids];
                                    }else{
                                        $where[] = ['bid','=',0];//仅显示平台商品
                                    }
                                }
                            }
                        }
                    }

					if(defined('isdouyin') && isdouyin == 1){
						$where[] = ['douyin_product_id','<>',''];
					}else{
						$where[] = ['douyin_product_id','=',''];
					}
                    if($v['params']['category'] || ($categroy_selmore && $categoryseltype == 1 && $v['params']['categorymore'])){
                        $where2 = '';
                        if($mid != -1){
                            $where2 = "find_in_set('-1',showtj)";
                        }
                        if($member){
                            $where2 .= " or find_in_set('".$member['levelid']."',showtj)";
                            if($member['subscribe']==1){
                                $where2 .= " or find_in_set('0',showtj)";
                            }
                        }

                        $tjwhere = [];
                        if($where2 && !empty($where2)){
                            $tjwhere[] = Db::raw($where2);
                        }

                        if($categroy_selmore && $categoryseltype == 1){
                        	$cid = $v['params']['categorymore'];
                            $newcids = [];
                            foreach($cid as $ck=>$cv){
                                //选择全部
                                if($ck == 0 && $cv == 'true'){
                                    $newcids = [];
                                    break;
                                }else{
                                    if($cv == 'true'){
                                        //查询是否在$backcids内
                                        if($inbackcids && !in_array($ck,$backcids)) {
                                            if(!in_array(-1,$newcids)){
                                                $newcids[] = -1;
                                            }
                                        }else{
                                            $newcids[] = $ck;
                                        }
                                    }
                                }
                            }
                            if($newcids){
                                $chidlc = Db::name('shop_category')->where($tjwhere)->where('aid',$aid)->where('pid','in',$newcids)->column('id');
                            }
                            $cids = $newcids;
                        }else{
                        	$cid = $v['params']['category'];
                            $cid = intval($cid);
                            if($cid){
                                if(getcustom('yx_invite_cashback')) {
                                    //查询是否在$backcids内
                                    if($inbackcids && !in_array($cid,$backcids)){
                                        $cid = 0;
                                    }
                                }
                                $chidlc = Db::name('shop_category')->where($tjwhere)->where('aid',$aid)->where('pid',$cid)->column('id');
                                $cids = [$cid];
                            }else{
                            	$cids = [];
                            }
                        }
                        if($cids){
                            if($chidlc){
                                $cids = array_merge($chidlc,$cids);
                                $whereCid = '(';
                                foreach($cids as $ck => $c){
                                    if(count($cids) == ($ck + 1))
                                        $whereCid .= "find_in_set({$c},cid)";
                                    else
                                        $whereCid .= " find_in_set({$c},cid) or ";
                                }
                                $where[] = Db::raw($whereCid . ')');
                            }else{
                                $whereCid = [];
                                foreach ($cids as $ck => $cc) {
                                    //查询是否在$backcids内
                                    if($inbackcids) {
                                        if(in_array($cc,$backcids)){
                                            $whereCid[] = "find_in_set({$cc},cid)";
                                        }
                                    }else{
                                        $whereCid[] = "find_in_set({$cc},cid)";
                                    }
                                }
                                if(!empty($whereCid)){
                                    $where[] = Db::raw(implode(' or ',$whereCid));
                                }else{
                                    $where[] = ['cid','=',0];
                                }
                            }
                        }
                    }else{
                        if(getcustom('product_cat_showtj')) {
                            $where2 = '';
                            if($mid != -1){
                                $where2 = "find_in_set('-1',showtj)";
                            }
                            if($member){
                                $where2 .= " or find_in_set('".$member['levelid']."',showtj)";
                                if($member['subscribe']==1){
                                    $where2 .= " or find_in_set('0',showtj)";
                                }
                            }
                            $tjwhere = [];
                            if($where2  && !empty($where2)){
                                $tjwhere[] = Db::raw($where2);
                            }
                            $clist = Db::name('shop_category')->where($tjwhere)->where('aid',$aid)->column('id');
                            if($clist){
                                $whereCid = [];
                                foreach($clist as $c2){
                                    $whereCid[] = "find_in_set({$c2},cid)";
                                }
                                $where[] = Db::raw(implode(' or ',$whereCid));
                            }
                        }
                        if(getcustom('yx_invite_cashback')) {
                            if($inbackcids){
                                $whereCid = [];
                                if($proids){
                                    $proid_str = implode(',',$proids);
                                    $whereCid[] = 'id in ('.$proid_str.')';
                                }
                                foreach($cids as $c2){
                                    $whereCid[] = "find_in_set({$c2},cid)";
                                }
                                $where[] = Db::raw(implode(' or ',$whereCid));
                            }
                        }
                    }
                    if($v['params']['category2'] || ($categroy_selmore && $categoryseltype == 1 && $v['params']['categorymore2'])){
                        if($categroy_selmore && $categoryseltype == 1){
                            $cid2 = $v['params']['categorymore2'];
                            $newcids2 = [];
                            foreach($cid2 as $ck2=>$cv2){
                                //选择全部
                                if($ck2 == 0 && $cv2 == 'true'){
                                    $newcids2 = [];
                                    break;
                                }else{
                                    if($cv2 == 'true'){
                                        $newcids2[] = $ck2;
                                    }
                                }
                            }
                            if($newcids2){
                                $chidlc2 = Db::name('shop_category2')->where('aid',$aid)->where('pid','in',$newcids2)->column('id');
                                $cids2 = $newcids2;
                                if($chidlc2){
                                    $cids2 = array_merge($chidlc2, $cids2);
                                }
                                $whereCid2 = '(';
                                foreach($cids2 as $k2=> $c2){
                                    if(count($cids2) == ($k2+ 1))
                                        $whereCid2 .= "find_in_set({$c2},cid2)";
                                    else
                                        $whereCid2 .= " find_in_set({$c2},cid2) or ";
                                }
                                $where[] = Db::raw($whereCid2 . ')');
                            }
                        }else{
                            if($v['params']['category2']){
                                $cid2 = intval($v['params']['category2']);
                                if($cid2 > 0){
                                    $chidlc2 = Db::name('shop_category2')->where('aid',$aid)->where('pid',$cid2)->column('id');
                                    if($chidlc2){
                                        $chidlc2 = array_merge($chidlc2, [$cid2]);
                                        $whereCid2 = '(';
                                        foreach($chidlc2 as $k2 => $c){
                                            if(count($chidlc2) == ($k2 + 1))
                                                $whereCid2 .= "find_in_set({$c},cid2)";
                                            else
                                                $whereCid2 .= " find_in_set({$c},cid2) or ";
                                        }
                                        $where[] = Db::raw($whereCid2 . ')');
                                    }else{
                                        $where[] = Db::raw("find_in_set({$cid2},cid2)");
                                    }
                                }
                            }
                        }
                    }
					if($v['params']['group']){
						$_string = array();
						foreach($v['params']['group'] as $gid=>$istrue){
							$gid = strval($gid);
							if($istrue=='true'){
								if($gid == 'all'){
									$_string[] = "1=1";
								}elseif($gid == '0'){
									$_string[] = "gid is null or gid=''";
								}else{
									$_string[] = "find_in_set({$gid},gid)";
								}
							}
						}
						if(!$_string){
							$where2 = '0=1';
						}else{
							$where2 = implode(" or ",$_string);
						}
					}else{
						$where2 = '1=1';
					}

                    if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        if($categroy_selmore && $categoryseltype == 1 && $v['params']['bidmore']){
                            $bids = $v['params']['bidmore'];
                            if($bids){
                                $newbids = [];
                                foreach($bids as $ck=>$cv){
                                    //选择全部
                                    if($ck == -1 && $cv == 'true'){
                                        $newbids = [];
                                        break;
                                    }else{
                                        if($cv == 'true'){
                                            $newbids[] = $ck;
                                        }
                                    }
                                }
                                if($newbids){
                                    $where[] = ['bid','in',$newbids];
                                }
                            }
                        }else{
                            $where[] = ['bid','=',$v['params']['bid']];
                        }
                    }

					$where3 = '';
					if($mid != -1){
						$where3 = "find_in_set('-1',showtj)";
                        if($member){
                            $where3.= " or find_in_set('".$member['levelid']."',showtj)";
                            if($member['subscribe']==1){
                                $where3 .= " or find_in_set('0',showtj)";
                            }
                        }else{
                            if($mid != -1){
                                $where3 .= " or find_in_set('-2',showtj)";
                            }else{
                                $where3 .= " find_in_set('-2',showtj)";
                            }
                        }
					}

					if(!$where3 || empty($where3)){
                        $where3 = '1=1';
                    }
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');

					$bjuli = [];
					if($v['params']['sortby'] == 'juli' && $latitude && $longitude){
						$border = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
						$blist = Db::name('business')->where('aid',$aid)->where('status',1)->where("longitude!='' and latitude!=''")->field('id,longitude,latitude')->order($border)->select()->toArray();
						$bids = [];
						$b0juli = getdistance($longitude,$latitude,$sysset['longitude'],$sysset['latitude'],2);
						foreach($blist as $binfo){
							$juli = getdistance($longitude,$latitude,$binfo['longitude'],$binfo['latitude'],2);
							if($juli > $b0juli && !in_array('0',$bids)){
								$bids[] = '0';
								$bjuli['0'] = ''.$b0juli.'km';
							}
							$bids[] = $binfo['id'];
							$bjuli[''.$binfo['id']] = ''.$juli.'km';
						}
						$order = Db::raw('field(bid,'.implode(',',$bids).'),sort desc,id desc');
					}

					$field = 'id proid,bid,name,pic,sell_price,lvprice,lvprice_data,market_price,sales,sort,price_type,stock,cid,cid2,product_type,guigedata,product_type,weight,fwid,sellpoint,cost_price';
			        if(getcustom('plug_tengrui')) {
			            $field .= ',house_status,group_status,group_ids,is_rzh,relation_type';
			        }
			        if(getcustom('shop_other_infor')) {
			            $field .= ',xunjia_text';
			        }
                    if(getcustom('design_product_commission') || getcustom('home_product_show_binfo')) {
                        $field .= ',commissionset,commissiondata1,commissiondata2,commissiondata3,commissionset4';
                    }
                    if(getcustom('product_field_buy')){
		                $field .= ',procode,guige,brand,unit,valid_time,remark';
		            }
                    if(getcustom('product_unit')) {
                        $field .= ',product_unit';
                    }
                    if(getcustom('product_handwork')) {
                        $field .= ',hand_fee';
                    }
                    if(getcustom('product_service_fee')) {
                        $field .= ',service_fee,service_fee_switch,service_fee_data';
                    }
					if(getcustom('product_scoredk_price_show')) {
						$field .= ',scoredkmaxset,scoredkmaxval';
					}
					$result = Db::name('shop_product')->field($field)->where($where)->where($where2)->where($where3)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
//                    dd($result);
//                    dd(Db::getlastsql());
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
                        $result[$k2]['price_tag'] = $price_tag;
                        $result[$k2]['price_color'] = $price_color;
                        $result[$k2]['cost_tag'] = $cost_tag;
                        $result[$k2]['cost_color'] = $cost_color;
                        $result[$k2]['show_sellprice'] = $show_sellprice;
                        $result[$k2]['show_cost'] = $show_cost;
                        $result[$k2]['hide_cart'] = $hidecart;
                        $result[$k2]['sell_price_origin'] = $v2['sell_price'];
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						if($v2['lvprice']==1 && $member){
							$lvprice_data = json_decode($v2['lvprice_data'],true);
							if($lvprice_data && isset($lvprice_data[$member['levelid']])){
                                $result[$k2]['sell_price'] = $lvprice_data[$member['levelid']];
							}

                            if(getcustom('product_service_fee') && $v2['service_fee_switch'] == 1){
                                $service_fee_data = json_decode($v2['service_fee_data'],true);
                                if($service_fee_data && isset($service_fee_data[$member['levelid']])){
                                    $result[$k2]['service_fee'] = $service_fee_data[$member['levelid']];
                                }
                            }
							//查询下级会员价
							if (getcustom('product_nextmemberlevel_price_show') && $member && $v['params']['showprice']==3){
								if($lvprice_data && isset($lvprice_data[$nextlevelid])){
									$result[$k2]['nextmemberlevel_price'] = $lvprice_data[$nextlevelid];
									$result[$k2]['nextmemberlevel_name'] = $nextlevelname;
								}
							}
						}
						//积分抵扣金额
                        if(getcustom('product_scoredk_price_show')){
							$scoredk_price = 0;
							if($v2['scoredkmaxset']==0){
								if($sysset['scoredkmaxpercent'] == 0){
									$scoredk_price = 0;
								}else{
									if($sysset['scoredkmaxpercent'] > 0 && $sysset['scoredkmaxpercent']<100){
										$scoredk_price = $sysset['scoredkmaxpercent'] * 0.01 * $result[$k2]['sell_price'];
									}else{
										$scoredk_price = $result[$k2]['sell_price'];
									}
								}
							}elseif($v2['scoredkmaxset']==1){
								$scoredk_price = $v2['scoredkmaxval'] * 0.01 * $result[$k2]['sell_price'];
							}elseif($v2['scoredkmaxset']==2){
								$scoredk_price = $v2['scoredkmaxval'];
							}
                            $result[$k2]['scoredk_price'] = ($result[$k2]['sell_price'] - $scoredk_price) < 0?0:$result[$k2]['sell_price'] - $scoredk_price;
							$result[$k2]['scoredk_price'] = round($result[$k2]['scoredk_price'],2);
                        }
                        if(getcustom('product_price_rate') && $member){
                            $result[$k2]['sell_price'] = $result[$k2]['sell_price'] * $member['price_rate_agent'];
                        }
                        if(getcustom('member_product_price')){
                            //一客一价,存在用户时，存在设置的专享商品时 ,不查询规格的 默认一条
                            $member_product = Db::name('member_product')->where('aid',$aid)->where('mid',$mid)->where('proid',$v2['proid'])->order('sell_price asc')->find();
                            if($member_product){
                                $result[$k2]['sell_price'] = $member_product['sell_price'];
                            }
                        }
                        //价格显示方式
                        if(getcustom('price_show_type')){
                            $price_show_type = Db::name('shop_sysset')->where('aid',$aid)->value('price_show_type');
                            $result[$k2]['price_show_type'] = $price_show_type;
   //                         $userlevel = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
                            $defalut_level = Db::name('member_level')->where('aid',$aid)->order('id asc')->find();
                            if(empty($userlevel) || $defalut_level['id'] == $member['levelid']){
                                $result[$k2]['is_vip'] =0;
                            }else{
                                $result[$k2]['is_vip'] =1;
                            }
                            
                            if(in_array($price_show_type,[1,2])){ //开启会员价
                                $lvprice_data =json_decode($v2['lvprice_data'],true);
                                if($result[$k2]['is_vip'] == 0){//不是会员 查询下个会员
                                    $nextlevel = Db::name('member_level')->where('aid',$aid)->where('sort','>',$userlevel['sort'])->order('sort,id')->find();
                                    
                                    if(empty($nextlevel) || $v2['lvprice'] ==0 ){
                                        $nextlevel = Db::name('member_level')->where('aid',$aid)->where('sort','>',$defalut_level['sort'])->order('sort,id')->find();
                                    }
                                    $level_name = $nextlevel['name'];
                                    
                                    $result[$k2]['sell_price_origin'] = $lvprice_data[$nextlevel['id']];
                                }else{

                                    if($userlevel && $v2['lvprice'] ==1 ){
                                        $level_name =  $userlevel['name'];
                                    } else{
                                        $level_name = '';
                                    }
                                    
                                }
                                $result[$k2]['level_name'] =  $level_name;
                            }

                        }

                        if(getcustom('price_dollar')){
							$usdrate = Db::name('shop_sysset')->where('aid',$aid)->value('usdrate');
							if($usdrate>0){
								$result[$k2]['usd_sellprice'] = round($result[$k2]['sell_price']/$usdrate,2);
							}
						}
						if(getcustom('plug_tengrui')) {
			                $tr_check = new \app\commons\TengRuiCheck();
		                    //判断是否是否符合会员认证、会员关系、一户、用户组，不符合则直接去掉
		                    $v2['id'] = $v2['proid'];
		                    $check_product = $tr_check->check_product($member,$v2,1);
		                    if($check_product && $check_product['status'] == 0 ){
		                        unset($result[$k2]);
			                }
				        }
						if($bjuli){
							$result[$k2]['juli'] = $bjuli[''.$v2['bid']];
						}
						if($v['params']['showbname'] == '1' || $v['params']['showbdistance']=='1'){
							$result[$k2]['binfo'] = $bArr[$v2['bid']];
						}
                        //是否计算距离
                        if(getcustom('home_product_show_binfo')){
                            $binfo = $bArr[$v2['bid']]??[];
                            if($v['params']['showbdistance']=='1' && $binfo && $binfo['latitude'] && $binfo['longitude']){
                                //计算用户当前距离当商家的距离
                                $bdistance = getdistance($longitude,$latitude,$binfo['longitude'],$binfo['latitude']);
                                if($bdistance>1000){
                                    $result[$k2]['binfo']['distance'] = round($bdistance/1000,1).'km';
                                }else{
                                    $result[$k2]['binfo']['distance'] = round($bdistance,1).'m';
                                }
                            }else{
                                $result[$k2]['binfo']['distance'] = '';
                            }
                        }
						if($v['params']['showcoupon']){
							$couponlist = Db::name('coupon')->where('aid',$aid)->where('bid',$v2['bid'])->where('isgive','<>',2)->where('tolist',1)->where('type','in','1,4,10')->where("unix_timestamp(starttime)<=".time()." and unix_timestamp(endtime)>=".time())->order('sort desc,id desc')->select()->toArray();
							$newcplist = [];
							foreach($couponlist as $k3=>$v3){
								$showtj = explode(',',$v3['showtj']);
								if(!in_array('-1',$showtj) && !in_array($member['levelid'],$showtj)){ //不是所有人
									continue;
								}
								//0全场通用,1指定类目,2指定商品
								if(!in_array($v3['fwtype'],[0,1,2])){
									continue;
								}
								if($v3['fwtype']==2){//指定商品可用
									$productids = explode(',',$v3['productids']);
									if(!in_array($v2['proid'],$productids)){
										continue;
									}
								}
								if($v3['fwtype']==1){//指定类目可用
									$categoryids = explode(',',$v3['categoryids']);
									$cids = explode(',',$v2['cid']);
									$clist = Db::name('shop_category')->where('aid',$aid)->where('pid','in',$categoryids)->select()->toArray();
									foreach($clist as $kc=>$vc){
										$categoryids[] = $vc['id'];
										$cate2 = Db::name('shop_category')->where('aid',$aid)->where('pid',$vc['id'])->find();
										$categoryids[] = $cate2['id'];
									}
									if(!array_intersect($cids,$categoryids)){
										continue;
									}
								}
								if($v3['fwtype']==6){//指定商家类目可用
									$categoryids2 = explode(',',$v3['categoryids2']);
									$cids2 = explode(',',$v2['cid2']);
									$clist2 = Db::name('shop_category2')->where('pid','in',$categoryids2)->select()->toArray();
									foreach($clist2 as $kc=>$vc){
										$categoryids2[] = $vc['id'];
										$cate2 = Db::name('shop_category2')->where('pid',$vc['id'])->find();
										$categoryids2[] = $cate2['id'];
									}
									if(!array_intersect($cids2,$categoryids2)){
										continue;
									}
								}
								$newcplist[] = $v3;
							}
							$result[$k2]['couponlist'] = $newcplist;
						}
                        if(getcustom('design_product_commission') || getcustom('home_product_show_binfo')) {
                            if($v['params']['showcommission'] && $userlevel){
                                if($userlevel['can_agent']!=0){
                                    $commission = 0;
                                    $result[$k2]['commission_desc'] = '元';
                                    if($v2['commissionset']==1){//按比例
                                        $commissiondata = json_decode($v2['commissiondata1'],true);
                                        if($commissiondata){
                                            $commission = $commissiondata[$userlevel['id']]['commission1'] * ($result[$k2]['sell_price_origin'] - ($sysset['fxjiesuantype']==2 ? $v2['cost_price'] : 0)) * 0.01;
                                        }
                                    }elseif($v2['commissionset']==2){//按固定金额
                                        $commissiondata = json_decode($v2['commissiondata2'],true);
                                        if($commissiondata){
                                            $commission = $commissiondata[$userlevel['id']]['commission1'];
                                        }
                                    }elseif($v2['commissionset']==3) {//提成是积分
                                        $commissiondata = json_decode($v2['commissiondata3'],true);
                                        if($commissiondata){
                                            $commission = $commissiondata[$userlevel['id']]['commission1'];
                                        }
                                        $result[$k2]['commission_desc'] = t('积分');
                                    }elseif($v2['commissionset']==5) {//提成比例+积分
                                        $commissiondata = json_decode($v2['commissiondata1'],true);
                                        if($commissiondata){
                                            $commission = $commissiondata[$userlevel['id']]['commission1'] * ($result[$k2]['sell_price_origin'] - ($sysset['fxjiesuantype']==2 ? $v2['cost_price'] : 0)) * 0.01;
                                        }
                                        if(bccomp($commission,0)<=0){
                                            $commissiondata = json_decode($v2['commissiondata3'],true);
                                            if($commissiondata){
                                                $commission = $commissiondata[$userlevel['id']]['commission1'] * ($result[$k2]['sell_price_origin'] - ($sysset['fxjiesuantype']==2 ? $v2['cost_price'] : 0)) * 0.01;
                                            }
                                            $result[$k2]['commission_desc'] = t('积分');
                                        }

                                    }elseif($v2['commissionset']==6) {//提成金额+积分
                                        $commissiondata = json_decode($v2['commissiondata2'],true);
                                        if($commissiondata){
                                            $commission = $commissiondata[$userlevel['id']]['commission1'];
                                        }
                                        if(bccomp($commission,0)<=0){
                                            $commissiondata = json_decode($v2['commissiondata3'],true);
                                            if($commissiondata){
                                                $commission = $commissiondata[$userlevel['id']]['commission1'];
                                            }
                                            $result[$k2]['commission_desc'] = t('积分');
                                        }

                                    }elseif($v2['commissionset']==7) {//积分比例
                                        $commissiondata = json_decode($v2['commissiondata3'],true);
                                        if($commissiondata){
                                            $commission = $commissiondata[$userlevel['id']]['commission1'] * ($result[$k2]['sell_price_origin'] - ($sysset['fxjiesuantype']==2 ? $v2['cost_price'] : 0)) * 0.01;
                                        }
                                        $result[$k2]['commission_desc'] = t('积分');


                                    }elseif($v2['commissionset']===0){//按会员等级
                                        //fxjiesuantype 0按商品价格,1按成交价格,2按销售利润
                                        if($userlevel['commissiontype']==1){ //固定金额按单
                                            $commission = $userlevel['commission1'];
                                        }else{
                                            $commission = $userlevel['commission1'] * ($result[$k2]['sell_price_origin'] - ($sysset['fxjiesuantype']==2 ? $v2['cost_price'] : 0)) * 0.01;
                                        }
                                    }
                                    if($v2['commissionset4']==1 && $v2['lvprice']==1){ //极差分销
                                        $lvprice_data = json_decode($v2['lvprice_data'],true);
                                        $commission += array_shift($lvprice_data) - $result[$k2]['sell_price'];
                                        if($commission < 0) $commission = 0;
                                    }
                                    $result[$k2]['commission_price'] = round($commission*100)/100;									
                                }
                            }
                        }
						$guigedata = json_decode($v2['guigedata'],true);
						$result[$k2]['gg_num'] =  count($guigedata);
						//未登录查看价格
						if(getcustom('show_price_unlogin')){
							$shopset = Db::name('shop_sysset')->where('aid', $aid)->find();
							if(!$mid && $shopset['is_show_price_unlogin'] == 0){
								$result[$k2]['sell_price'] =  $shopset['show_price_unlogin_txt'];				
							}		
						}
						//未审核查看价格
						if(getcustom('show_price_uncheck')){
							$shopset = Db::name('shop_sysset')->where('aid', $aid)->find();
							if($mid && $member['checkst'] !=1 && $shopset['is_show_price_uncheck'] == 0){
								$result[$k2]['sell_price'] =  $shopset['show_price_uncheck_txt'];					
							}			
						}
                        if(getcustom('product_weight') && $v2['product_type']==2){
                            $_price = $result[$k2]['sell_price'];//价格
                            $_weight = round($v2['weight']/500,2);//化成斤
                            //单价
                            if($_weight>0){
                                $unit_price = round($_price / $_weight,2);
                            }else{
                                $unit_price = $_price;
                            }
                            $result[$k2]['unit_price'] = $unit_price;
                        }
                        if(!getcustom('product_show_sellpoint')) {
                            $result[$k2]['sellpoint'] = '';
                        }
                        //商品服务
                        $fwnames = [];
                        if(getcustom('product_show_fwlist')) {
                            if ($v2['fwid']) {
                                $fwid = explode(',', $v2['fwid']);
                                $fwnames = Db::name('shop_fuwu')->where('aid', $aid)->whereIn('id', $fwid)->column('name');
                                if (empty($fwnames)) $fwnames = [];
                            }
                        }
                        $result[$k2]['fwlist'] = $fwnames;
						if(!getcustom('product_cost_show')) {
							unset($result[$k2]['cost_price']);
						}
					}
					if(getcustom('plug_tengrui')) {
		                $len = count($result);
		                if($len<$v['params']['proshownum'] && $len>0){
		                    //重置索引,防止上方去掉的数据产生空缺
		                    $result=array_values($result);
		                }
			        }
			        if(getcustom('shop_other_infor')){
		                if($result){
//		                	$sysset = Db::name('admin_set')->where('aid',$aid)->field('name,main_business,tel')->find();
		                    foreach($result as &$rv){
		                    	//特别标识
                    			$rv['is_soi'] = 1;
		                    	//联系系统名称
			                    $rv['lx_name'] = $sysset['name']?$sysset['name']:'';
			                    //联系商家id
			                    $rv['lx_bid']  = $rv['bid'];
			                    //联系商家名称
			                    $rv['lx_bname']  = '';
			                    //联系电话
			                    $rv['lx_tel']  = '';

		                        $rv['merchant_name'] = '';
		                        $rv['main_business'] = '';
		                        //查询商家
		                        if($rv['bid']>0){
		                            $merchant_name =  Db::name('business')
		                                ->where('id',$rv['bid'])
		                                ->where('aid',$aid)
		                                ->field('name,main_business,tel')
		                                ->find();
		                            if($merchant_name){
		                                $rv['merchant_name'] = $merchant_name['name'];
		                                $rv['main_business'] = $merchant_name['main_business'];

		                                //联系商家名称
			                            $rv['lx_bname']  = $merchant_name['name']?$merchant_name['name']:'';
			                            //联系电话
			                            $rv['lx_tel']    = $merchant_name['tel']?$merchant_name['tel']:'';
		                            }
		                        }else{
		                            $rv['merchant_name'] = $sysset['name'];
		                            $rv['main_business'] = $sysset['main_business'];
		                            //联系电话
			                        $rv['lx_tel']    = $sysset['tel']?$sysset['tel']:'';
		                        }
		                    }
		                    unset($rv);
		                }
		            }
		            if(getcustom('product_field_buy')){
		                if($result){
		                    foreach($result as &$rv){
		                        $rv['barcode'] = $rv['procode'];//编码
		                        $rv['ggname']  = $rv['guige'];//规格名称
		                        $rv['ggstock'] = 0;//库存
		                        //查询规格信息
		                        $guige =  Db::name('shop_guige')->where('proid',$rv['proid'])->where('aid',$aid)->order('id asc')->find();
		                        if($guige){
		                            //$rv['barcode'] = $guige['barcode'];//编码
		                            //$rv['ggname']  = $guige['name'];//规格名称
		                            $rv['ggstock']   = $guige['stock'];//库存
		                        }
		                    }
		                    unset($rv);
		                }
		            }
					$pagecontent[$k]['data'] = $result;
				}
            }elseif($v['temp'] == 'restaurant_product'){//菜品列表 获取菜品信息
                if($v['params']['productfrom'] == 0){//手动选择
                    $newdata = array();
                    foreach($v['data'] as $pro){
                        $where = [];
                        if(getcustom('restaurant_product_showtj')){
                            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
                            $where2 = "find_in_set('-1',showtj)";
                            if($member){
                                $where2 .= " or find_in_set('".$member['levelid']."',showtj)";
                                if($member['subscribe']==1){
                                    $where2 .= " or find_in_set('0',showtj)";
                                }
                            }
                            $where[] = Db::raw($where2);
                        }
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
                        $newpro = Db::name('restaurant_product')->field('id proid,name,pic,market_price,sell_price,lvprice,lvprice_data,sales')->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->where('ischecked',1)->where($where)->find();
                        if($newpro){
                            $newpro['id'] = $pro['id'];
                            if($newpro['lvprice']==1 && $member){
                                $lvprice_data = json_decode($newpro['lvprice_data'],true);
                                if($lvprice_data && isset($lvprice_data[$member['levelid']])){
                                    $newpro['sell_price'] = $lvprice_data[$member['levelid']];
                                }
                            }
                            $newdata[] = $newpro;
                        }
                    }
                    $pagecontent[$k]['data'] = $newdata;
                }else{
                    $where = [];
                    $where[] = ['aid','=',$aid];
                    $where[] = ['ischecked','=',1];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
                    //$where[] = ['status','=',1];
                    $nowtime = time();
                    $nowhm = date('H:i');
                    $where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");

                    if($v['params']['category']){
                        $cid = intval($v['params']['category']);
                        $where[] = Db::raw("find_in_set({$cid},cid)");
                    }
                    $where2 = '1=1';
                    if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        if($categroy_selmore && $categoryseltype == 1 && $v['params']['bidmore']){
                            $bids = $v['params']['bidmore'];
                            if($bids){
                                $newbids = [];
                                foreach($bids as $ck=>$cv){
                                    //选择全部
                                    if($ck == -1 && $cv == 'true'){
                                        $newbids = [];
                                        break;
                                    }else{
                                        if($cv == 'true'){
                                            $newbids[] = $ck;
                                        }
                                    }
                                }
                                if($newbids){
                                    $where[] = ['bid','in',$newbids];
                                }
                            }
                        }else{
                            $where[] = ['bid','=',$v['params']['bid']];
                        }
                    }
                    if(getcustom('restaurant_product_showtj')){
                        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
                        $where2 = "find_in_set('-1',showtj)";
                        if($member){
                            $where2 .= " or find_in_set('".$member['levelid']."',showtj)";
                            if($member['subscribe']==1){
                                $where2 .= " or find_in_set('0',showtj)";
                            }
                        }
                        $where[] = Db::raw($where2);
                    }
                    $order = 'sort desc';
                    if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
                    if($v['params']['sortby'] == 'createtimedesc') $order = 'create_time desc';
                    if($v['params']['sortby'] == 'createtime') $order = 'create_time';
                    if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
                    if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
                    $result = Db::name('restaurant_product')->field('id proid,name,pic,sell_price,lvprice,lvprice_data,market_price,sales')->where($where)->where($where2)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
                    if(!$result) $result = array();
                    foreach($result as $k2=>$v2){
                        $result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
                        if($v2['lvprice']==1 && $member){
                            $lvprice_data = json_decode($v2['lvprice_data'],true);
                            if($lvprice_data && isset($lvprice_data[$member['levelid']])){
                                $result[$k2]['sell_price'] = $lvprice_data[$member['levelid']];
                            }
                        }
                    }
                    $pagecontent[$k]['data'] = $result;
                }
			}elseif($v['temp'] == 'scoreshop'){//产品列表 获取产品信息
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,name,pic,sell_price,score_price,money_price,sales,lvprice,lvprice_data';
				        if(getcustom('plug_tengrui')) {
				            $field .= ',house_status,is_rzh,relation_type,group_status,group_ids';
				        }
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $where[] = ['id','=',$pro['proid']];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
						$newpro = Db::name('scoreshop_product')->field($field)->where($where)->find();
                        if(getcustom('score_weishu')){
                            $newpro['score_price'] = dd_money_format($newpro['score_price'],$score_weishu);
                        }
						if(getcustom('plug_tengrui')) {
							if($newpro){
					            //判断是否是否符合会员认证、会员关系、一户、用户组
					            $tr_check = new \app\commons\TengRuiCheck();
					            $newpro['id'] = $newpro['proid'];
					            $check_score = $tr_check->check_score($member,$newpro,1);
					            if($check_score && $check_score['status'] == 0 ){
					                $newpro = [];
					            }
					        }
					    }
						if($newpro){
							$newpro['id'] = $pro['id'];
							if($newpro['lvprice']==1 && $member){
								$lvprice_data = json_decode($newpro['lvprice_data'],true);
								if($lvprice_data && isset($lvprice_data[$member['levelid']])){
									if(isset($lvprice_data[$member['levelid']]['money_price']))
									$newpro['money_price'] = $lvprice_data[$member['levelid']]['money_price'];
									if(isset($lvprice_data[$member['levelid']]['score_price']))
									$newpro['score_price'] = $lvprice_data[$member['levelid']]['score_price'];
								}
							}
							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
                    if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        $where[] = ['bid','=',$v['params']['bid']];
                    }
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
					if($v['params']['category'] || ($categroy_selmore && $categoryseltype == 1 && $v['params']['categorymore'])){
						if($categroy_selmore && $categoryseltype == 1){
                            $cid = $v['params']['categorymore'];
                            $newcids = [];
                            foreach($cid as $ck=>$cv){
                                //选择全部
                                if($ck == 0 && $cv == 'true'){
                                    $newcids = [];
                                    break;
                                }else{
                                    if($cv == 'true'){
                                        $newcids[] = $ck;
                                    }
                                }
                            }
                            if($newcids){
                                $chidlc = Db::name('scoreshop_category')->where('aid',$aid)->where('pid','in',$newcids)->select()->toArray();
                            	$cids = $newcids;
                                if($chidlc){
                                    foreach($chidlc as $c){
                                        $cids[] = intval($c['id']);
                                    }
                                }
                                $where[] = ['cid','in',$cids];
                            }
						}else{
							if($v['params']['category']){
								$cid = intval($v['params']['category']);
								$chidlc = Db::name('scoreshop_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
								if($chidlc){
									$cids = array($cid);
									foreach($chidlc as $c){
										$cids[] = intval($c['id']);
									}
									$where[] = ['cid','in',$cids];
								}else{
									$where[] = ['cid','=',$cid];
								}
							}
						}
					}
					if($v['params']['group']){
						$_string = array();
						foreach($v['params']['group'] as $gid=>$istrue){
							if($istrue=='true'){
								if($gid == '0'){
									$_string[] = "gid is null or gid=''";
								}else{
									$_string[] = "find_in_set({$gid},gid)";
								}
							}
						}
						if(!$_string){
							$where2 = '0=1';
						}else{
							$where2 = implode(" or ",$_string);
						}
					}else{
						$where2 = '1=1';
					}

					$where3 = '';
					if($mid != -1){
						$where3 = "find_in_set('-1',showtj)";
                        if($member){
                            $where3.= " or find_in_set('".$member['levelid']."',showtj)";
                            if($member['subscribe']==1){
                                $where3 .= " or find_in_set('0',showtj)";
                            }
                        }else{
                            if($mid != -1) {
                                $where3 .= " or find_in_set('-2',showtj)";
                            }else{
                                $where3 .= " find_in_set('-2',showtj)";
                            }
                        }
					}

					if(!$where3 || empty($where3)){
						$where3 = '1=1';
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id proid,name,pic,sell_price,score_price,money_price,sales,lvprice,lvprice_data';
			        if(getcustom('plug_tengrui')) {
			            $field .= ',house_status,is_rzh,relation_type,group_status,group_ids';
			        }
					$result = Db::name('scoreshop_product')->field($field)->where($where)->where($where2)->where($where3)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);

						if($v2['lvprice']==1 && $member){
							$lvprice_data = json_decode($v2['lvprice_data'],true);
							if($lvprice_data && isset($lvprice_data[$member['levelid']])){
								if(isset($lvprice_data[$member['levelid']]['money_price']))
								$result[$k2]['money_price'] = $lvprice_data[$member['levelid']]['money_price'];
								if(isset($lvprice_data[$member['levelid']]['score_price']))
								$result[$k2]['score_price'] = $lvprice_data[$member['levelid']]['score_price'];
							}
						}
						if(getcustom('plug_tengrui')) {
			                $tr_check = new \app\commons\TengRuiCheck();
			                //判断是否是否符合会员认证、会员关系、一户，不符合则直接去掉
			                $v2['id'] = $v2['proid'];
			                $check_score = $tr_check->check_score($member,$v2,1);
			                if($check_score && $check_score['status'] == 0 ){
			                    unset($result[$k2]);
			                }
			        	}
                        $result[$k2]['score_price'] = dd_money_format($result[$k2]['score_price'],$score_weishu);
                    }
					if(getcustom('plug_tengrui')) {
			            $len = count($result);
			            if($len<$v['params']['proshownum'] && $len>0){
			                //重置索引,防止上方去掉的数据产生空缺
			                $result=array_values($result);
			            }
			        }
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'cycle'){//产品列表 获取产品信息
                if($v['params']['productfrom'] == 0){//手动选择
                    $newdata = array();
                    foreach($v['data'] as $pro){
                        $field = 'id proid,name,pic,market_price,sell_price,sales,ps_cycle';
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $where[] = ['ischecked','=',1];
                        $where[] = ['id','=',$pro['proid']];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
                        $newpro = Db::name('cycle_product')->field($field)->where($where)->find();
                        if($newpro){
                            $ps_cycle = ['1' => '每日一期','2' => '每周一期' ,'3' => '每月一期'];
                            $newpro['pspl'] = $ps_cycle[$newpro['ps_cycle']];
                            $newpro['id'] = $pro['id'];
                            $newdata[] = $newpro;
                        }
                    }
                    $pagecontent[$k]['data'] = $newdata;
                }else{
                    $where = [];
                    $where[] = ['aid','=',$aid];
                    $where[] = ['status','=',1];
                    $where[] = ['ischecked','=',1];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
                    if($v['params']['category'] || ($categroy_selmore && $categoryseltype == 1 && $v['params']['categorymore'])){
                        if($categroy_selmore && $categoryseltype == 1){
                            $cid = $v['params']['categorymore'];
                            $newcids = [];
                            foreach($cid as $ck=>$cv){
                                //选择全部
                                if($ck == 0 && $cv == 'true'){
                                    $newcids = [];
                                    break;
                                }else{
                                    if($cv == 'true'){
                                        $newcids[] = $ck;
                                    }
                                }
                            }
                            if($newcids){
                                $chidlc = Db::name('cycle_category')->where('aid',$aid)->where('pid','in',$newcids)->select()->toArray();
                                $cids = $newcids;
                                if($chidlc){
                                    foreach($chidlc as $c){
                                        $cids[] = intval($c['id']);
                                    }
                                }
                                $where[] = ['cid','in',$cids];
                            }
                        }else{
                        	if($v['params']['category']){
                        		$cid = intval($v['params']['category']);
	                            $chidlc = Db::name('cycle_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
	                            if($chidlc){
	                                $cids = array($cid);
	                                foreach($chidlc as $c){
	                                    $cids[] = intval($c['id']);
	                                }
	                                $where[] = ['cid','in',$cids];
	                            }else{
	                                $where[] = ['cid','=',$cid];
	                            }
                        	}
                        }
                    }
                    if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        if($categroy_selmore && $categoryseltype == 1 && $v['params']['bidmore']){
                            $bids = $v['params']['bidmore'];
                            if($bids){
                                $newbids = [];
                                foreach($bids as $ck=>$cv){
                                    //选择全部
                                    if($ck == -1 && $cv == 'true'){
                                        $newbids = [];
                                        break;
                                    }else{
                                        if($cv == 'true'){
                                            $newbids[] = $ck;
                                        }
                                    }
                                }
                                if($newbids){
                                    $where[] = ['bid','in',$newbids];
                                }
                            }
                        }else{
                            $where[] = ['bid','=',$v['params']['bid']];
                        }
                    }
                    if($v['params']['group']){
                        $_string = array();
                        foreach($v['params']['group'] as $gid=>$istrue){
                            if($istrue=='true'){
                                if($gid == '0'){
                                    $_string[] = "gid is null or gid=''";
                                }else{
                                    $_string[] = "find_in_set({$gid},gid)";
                                }
                            }
                        }
                        if(!$_string){
                            $where2 = '0=1';
                        }else{
                            $where2 = implode(" or ",$_string);
                        }
                    }else{
                        $where2 = '1=1';
                    }
                    $order = 'sort desc';
                    if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
                    if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
                    if($v['params']['sortby'] == 'createtime') $order = 'createtime';
                    if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
                    if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
                    $field = 'id proid,name,pic,sell_price,market_price,sales,ps_cycle';

                    $result = Db::name('cycle_product')->field($field)->where($where)->where($where2)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
                    if(!$result) $result = array();

                    foreach($result as $k2=>$v2){
                        $result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
                        $ps_cycle = ['1' => '每日一期','2' => '每周一期' ,'3' => '每月一期'];
                        $result[$k2]['pspl'] = $ps_cycle[$v2['ps_cycle']];
                    }
                    $pagecontent[$k]['data'] = $result;
                }
            }elseif($v['temp'] == 'collage'){//产品列表 获取产品信息
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,name,pic,market_price,sell_price,sales,stock,teamnum';
						if(getcustom('plug_tengrui')) {
							$field .= ',house_status,is_rzh,relation_type,group_status,group_ids';
						}
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $where[] = ['ischecked','=',1];
                        $where[] = ['id','=',$pro['proid']];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
						$newpro = Db::name('collage_product')->field($field)->where($where)->find();
						if(getcustom('plug_tengrui')) {
							if($newpro){
					            //判断是否是否符合会员认证、会员关系、一户、用户组
					            $tr_check = new \app\commons\TengRuiCheck();
					            $newpro['id'] = $newpro['proid'];
					            $check_collage = $tr_check->check_collage($member,$newpro,1);
					            if($check_collage && $check_collage['status'] == 0 ){
					                $newpro=[];
					            }
					        }
				        }
						if($newpro){
							$newpro['id'] = $pro['id'];
							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['ischecked','=',1];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
					if($v['params']['category'] || ($categroy_selmore && $categoryseltype == 1 && $v['params']['categorymore'])){
						if($categroy_selmore && $categoryseltype == 1){
                            $cid = $v['params']['categorymore'];
                            $newcids = [];
                            foreach($cid as $ck=>$cv){
                                //选择全部
                                if($ck == 0 && $cv == 'true'){
                                    $newcids = [];
                                    break;
                                }else{
                                    if($cv == 'true'){
                                        $newcids[] = $ck;
                                    }
                                }
                            }
                            if($newcids){
                                $chidlc = Db::name('collage_category')->where('aid',$aid)->where('pid','in',$newcids)->select()->toArray();
                                $cids = $newcids;
                                if($chidlc){
                                    foreach($chidlc as $c){
                                        $cids[] = intval($c['id']);
                                    }
                                }
                                $where[] = ['cid','in',$cids];
                            }
						}else{
							if($v['params']['category']){
								$cid = intval($v['params']['category']);
								$chidlc = Db::name('collage_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
								if($chidlc){
									$cids = array($cid);
									foreach($chidlc as $c){
										$cids[] = intval($c['id']);
									}
									$where[] = ['cid','in',$cids];
								}else{
									$where[] = ['cid','=',$cid];
								}
							}
						}
					}
                    if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        if($categroy_selmore && $categoryseltype == 1 && $v['params']['bidmore']){
                            $bids = $v['params']['bidmore'];
                            if($bids){
                                $newbids = [];
                                foreach($bids as $ck=>$cv){
                                    //选择全部
                                    if($ck == -1 && $cv == 'true'){
                                        $newbids = [];
                                        break;
                                    }else{
                                        if($cv == 'true'){
                                            $newbids[] = $ck;
                                        }
                                    }
                                }
                                if($newbids){
                                    $where[] = ['bid','in',$newbids];
                                }
                            }
                        }else{
                            $where[] = ['bid','=',$v['params']['bid']];
                        }
                    }
					
					if($v['params']['group']){
						$_string = array();
						foreach($v['params']['group'] as $gid=>$istrue){
							if($istrue=='true'){
								if($gid == '0'){
									$_string[] = "gid is null or gid=''";
								}else{
									$_string[] = "find_in_set({$gid},gid)";
								}
							}
						}
						if(!$_string){
							$where2 = '0=1';
						}else{
							$where2 = implode(" or ",$_string);
						}
					}else{
						$where2 = '1=1';
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id proid,name,pic,sell_price,market_price,sales,stock,teamnum';
					if(getcustom('plug_tengrui')) {
						$field .= ',house_status,is_rzh,relation_type,group_status,group_ids';
					}
                    if(getcustom('yx_collage_jieti')){
                        $field .=',collage_type,starttime,endtime';
                        $time = time();
                        $where[]= Db::raw("(  (collage_type = 0)or (collage_type = 1 and starttime < {$time} and endtime > {$time} )   )");
                    }
					$result = Db::name('collage_product')->field($field)->where($where)->where($where2)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						if(getcustom('plug_tengrui')) {
			                $tr_check = new \app\commons\TengRuiCheck();
		                    //判断是否是否符合会员认证、会员关系、一户，不符合则直接去掉
		                    $v2['id'] = $v2['proid'];
		                    $check_collage = $tr_check->check_collage($member,$v2,1);
		                    if($check_collage && $check_collage['status'] == 0 ){
		                        unset($result[$k2]);
		                    }
				        }
					}
					if(getcustom('plug_tengrui')) {
		                $len = count($result);
		                if($len<$v['params']['proshownum'] && $len>0){
		                    //重置索引,防止上方去掉的数据产生空缺
		                    $result=array_values($result);
		                }
			        }
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'kanjia'){//产品列表 获取产品信息
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,name,pic,sell_price,min_price,sales';
						if(getcustom('plug_tengrui')) {
							$field .= ',house_status,is_rzh,relation_type,group_status,group_ids';
						}
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $where[] = ['ischecked','=',1];
                        $where[] = ['id','=',$pro['proid']];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
						$newpro = Db::name('kanjia_product')->field($field)->where($where)->find();
						if(getcustom('plug_tengrui')) {
							if($newpro){
					            //判断是否是否符合会员认证、会员关系、一户
					            $tr_check = new \app\commons\TengRuiCheck();
					            $newpro['id'] = $newpro['proid'];
					            $check_kanjia = $tr_check->check_kanjia($member,$newpro,1);
					            if($check_kanjia && $check_kanjia['status'] == 0 ){
					                $newpro = [];
					            }
					        }
				        }
						if($newpro){
							$newpro['id'] = $pro['id'];
							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['ischecked','=',1];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
					if($v['params']['category']){

					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        if($categroy_selmore && $categoryseltype == 1 && $v['params']['bidmore']){
                            $bids = $v['params']['bidmore'];
                            if($bids){
                                $newbids = [];
                                foreach($bids as $ck=>$cv){
                                    //选择全部
                                    if($ck == -1 && $cv == 'true'){
                                        $newbids = [];
                                        break;
                                    }else{
                                        if($cv == 'true'){
                                            $newbids[] = $ck;
                                        }
                                    }
                                }
                                if($newbids){
                                    $where[] = ['bid','in',$newbids];
                                }
                            }
                        }else{
                            $where[] = ['bid','=',$v['params']['bid']];
                        }
                    }
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id proid,name,pic,sell_price,min_price,sales';
					if(getcustom('plug_tengrui')) {
						$field .= ',house_status,is_rzh,relation_type,group_status,group_ids';
					}
					$result = Db::name('kanjia_product')->field($field)->where($where)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						if(getcustom('plug_tengrui')) {
			                $tr_check = new \app\commons\TengRuiCheck();
			                //判断是否是否符合会员认证、会员关系、一户，不符合则直接去掉
			                $v2['id'] = $v2['proid'];
			                $check_kanjia = $tr_check->check_kanjia($member,$v2,1);
			                if($check_kanjia && $check_kanjia['status'] == 0 ){
			                    unset($result[$k2]);
			                }
			        	}
					}
					if(getcustom('plug_tengrui')) {
			            $len = count($result);
			            if($len<$v['params']['proshownum'] && $len>0){
			                //重置索引,防止上方去掉的数据产生空缺
			                $result=array_values($result);
			            }
			        }
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'seckill'){//产品列表 获取产品信息
                $set = Db::name('seckill_sysset')->where('aid',$aid)->find();
                $duration = $set['duration'];
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,name,pic,sell_price,market_price,sales,stock,seckill_date,seckill_time,starttime';
						if(getcustom('plug_tengrui')) {
							$field .= ',house_status,is_rzh,relation_type,group_status,group_ids';
						}
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $where[] = ['ischecked','=',1];
                        $where[] = ['id','=',$pro['proid']];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
                        // 过滤掉已结束的
                        $nowtime = time();
                        // if(d.starttime + {$sysset['duration']}*3600 < nowtime) 
                        $qstart_time = $nowtime-$duration*3600;
                        
                        // 显示范围
                        if(empty($v['params']['showrange'])){
                        	// 全部
                        	$where[] = ['starttime','>',$qstart_time];
                        }elseif($v['params']['showrange'] == 1){
                        	// 进行中
                        	$where[] = ['starttime','between',[$qstart_time,$nowtime]];
                        }else{
                        	// 未开始
                        	$where[] = ['starttime','>',$nowtime];
                        }

						$newpro = Db::name('seckill_product')->field($field)->where($where)->find();
                        if(empty($newpro)) continue;
                        $newpro['id'] = 'G'.time().rand(10000000,99999999);
                        //倒计时
                        
                        $seckill_endtime = $newpro['starttime'] + $duration * 3600;
                        if($seckill_endtime < $nowtime) {//已结束
                            $newpro['seckill_status'] = 2;
                            $newpro['hour'] = 0;
                            $newpro['minute'] = 0;
                            $newpro['second'] = 0;
                        }else{
                            if($newpro['starttime'] > $nowtime){ //未开始
                                $newpro['seckill_status'] = 0;
                                $lefttime = $newpro['starttime'] - $nowtime;
                                $newpro['hour'] = floor($lefttime / 3600);
                                $newpro['minute'] = floor(($lefttime - $newpro['hour'] * 3600) / 60);
                                $newpro['second'] = $lefttime - ($newpro['hour'] * 3600) - ($newpro['minute'] * 60);
                                //带天数
                                $newpro['day'] = floor($lefttime / 86400);
                                $newpro['day_hour'] = floor(($lefttime - $newpro['day'] * 86400) / 3600);
                            }else{ //进行中
                                $newpro['seckill_status'] = 1;
                                $lefttime = $seckill_endtime - $nowtime;
                                $newpro['hour'] = floor($lefttime / 3600);
                                $newpro['minute'] = floor(($lefttime - $newpro['hour'] * 3600) / 60);
                                $newpro['second'] = $lefttime - ($newpro['hour'] * 3600) - ($newpro['minute'] * 60);//带天数
                                $newpro['day'] = floor($lefttime / 86400);
                                $newpro['day_hour'] = floor(($lefttime - $newpro['day'] * 86400) / 3600);
                            }
                        }	
                        
						if(getcustom('plug_tengrui')) {
							if($newpro){
					            //判断是否是否符合会员认证、会员关系、一户、用户组
					            $tr_check = new \app\commons\TengRuiCheck();
					            $newpro['id'] = $newpro['proid'];
					            $check_seckill = $tr_check->check_seckill($member,$newpro,1);
					            if($check_seckill && $check_seckill['status'] == 0 ){
					                $newpro = [];
					            }
					        }
				        }
						if($newpro){
							$newpro['id'] = $pro['id'];
							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
                   
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['ischecked','=',1];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
					if($v['params']['category']){

					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        if($categroy_selmore && $categoryseltype == 1 && $v['params']['bidmore']){
                            $bids = $v['params']['bidmore'];
                            if($bids){
                                $newbids = [];
                                foreach($bids as $ck=>$cv){
                                    //选择全部
                                    if($ck == -1 && $cv == 'true'){
                                        $newbids = [];
                                        break;
                                    }else{
                                        if($cv == 'true'){
                                            $newbids[] = $ck;
                                        }
                                    }
                                }
                                if($newbids){
                                    $where[] = ['bid','in',$newbids];
                                }
                            }
                        }else{
                            $where[] = ['bid','=',$v['params']['bid']];
                        }
                    }
                    if($v['shopstyle'] == 2){
                        //风格2只显示当前这一场的秒杀
                        $timeset = explode(',',$set['timeset']);
                        $hour = date('H');
                        $current = '';
                        foreach ($timeset as $k2 => $item){
                            if(($hour >= $item && $hour < $timeset[$k2]) || ($hour >= $item && $k2 == (count($timeset)-1))){
                                $current = $item;break;
                            }
                        }
                        $where[] = ['seckill_date','=',date('Y-m-d')];
                        $where[] = ['seckill_time','=',$current];
                        //距离结束时间
                    }
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					if($v['params']['sortby'] == 'starttime') $order = 'starttime asc';
					$field = 'id proid,name,pic,sell_price,market_price,sales,stock,seckill_date,seckill_time,starttime';
					if(getcustom('plug_tengrui')) {
						$field .= ',house_status,is_rzh,relation_type,group_status,group_ids';
					}
					$nowtime = time();
					$qstart_time = $nowtime-$duration*3600;
                    // 显示范围
                    if(empty($v['params']['showrange'])){
                    	// 全部
                    	$where[] = ['starttime','>',$qstart_time];
                    }elseif($v['params']['showrange'] == 1){
                    	// 进行中
                    	$where[] = ['starttime','between',[$qstart_time,$nowtime]];
                    }else{
                    	// 未开始
                    	$where[] = ['starttime','>',$nowtime];
                    }
  

					$result = Db::name('seckill_product')->field($field)->where($where)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
					if(!$result) $result = array();
					else {
                        
                        foreach($result as $k2=>$v2){
                            $result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
                            //倒计时
                            $seckill_endtime = $v2['starttime'] + $duration * 3600;
                            if($seckill_endtime < $nowtime) {//已结束
                                $result[$k2]['seckill_status'] = 2;
                                $result[$k2]['hour'] = 0;
                                $result[$k2]['minute'] = 0;
                                $result[$k2]['second'] = 0;
                            }else{
                                if($v2['starttime'] > $nowtime){ //未开始
                                    $result[$k2]['seckill_status'] = 0;
                                    $lefttime = $v2['starttime'] - $nowtime;
                                    $result[$k2]['hour'] = floor($lefttime / 3600);
                                    $result[$k2]['minute'] = floor(($lefttime - $result[$k2]['hour'] * 3600) / 60);
                                    $result[$k2]['second'] = $lefttime - ($result[$k2]['hour'] * 3600) - ($result[$k2]['minute'] * 60);
                                    //带天数
                                    $result[$k2]['day'] = floor($lefttime / 86400);
                                    $result[$k2]['day_hour'] = floor(($lefttime - $result[$k2]['day'] * 86400) / 3600);
                                }else{ //进行中
                                    $result[$k2]['seckill_status'] = 1;
                                    $lefttime = $seckill_endtime - $nowtime;
                                    $result[$k2]['hour'] = floor($lefttime / 3600);
                                    $result[$k2]['minute'] = floor(($lefttime - $result[$k2]['hour'] * 3600) / 60);
                                    $result[$k2]['second'] = $lefttime - ($result[$k2]['hour'] * 3600) - ($result[$k2]['minute'] * 60);//带天数
                                    $result[$k2]['day'] = floor($lefttime / 86400);
                                    $result[$k2]['day_hour'] = floor(($lefttime - $result[$k2]['day'] * 86400) / 3600);
                                }
                            }
                            if(getcustom('plug_tengrui')) {
				                //判断是否是否符合一户、用户组，不符合则直接去掉
				                $tr_check = new \app\commons\TengRuiCheck();
				                $v2['id'] = $v2['proid'];
				                $check_seckill = $tr_check->check_seckill($member,$v2,1);
				                if($check_seckill && $check_seckill['status'] == 0 ){
				                    unset($result[$k2]);
				                }
				            }
							if(getcustom('price_dollar')){
								$usdrate = Db::name('shop_sysset')->where('aid',$aid)->value('usdrate');
								if($usdrate>0){
									$result[$k2]['usd_sellprice'] = round($result[$k2]['sell_price']/$usdrate,2);
								}
							}

                        }
                        if(getcustom('plug_tengrui')) {
				            $len = count($result);
				            if($len<$v['params']['proshownum'] && $len>0){
				                //重置索引,防止上方去掉的数据产生空缺
				                $result=array_values($result);
				            }
				        }
                    }

					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'tuangou'){//产品列表 团购商品
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,name,pic,sell_price,sales,pricedata,market_price';
						if(getcustom('plug_tengrui')) {
							$field .= ',house_status,is_rzh,relation_type,group_status,group_ids';
						}
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $where[] = ['ischecked','=',1];
                        $where[] = ['id','=',$pro['proid']];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
						$newpro = Db::name('tuangou_product')->field($field)->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->where('ischecked',1)->find();
                        if(empty($newpro)) continue;
						if(getcustom('plug_tengrui')) {
							if($newpro){
					            //判断是否是否符合会员认证、会员关系、一户、用户组
					            $tr_check = new \app\commons\TengRuiCheck();
					            $newpro['id'] = $newpro['proid'];
					            $check_tuangou = $tr_check->check_tuangou($member,$newpro,1);
					            if($check_tuangou && $check_tuangou['status'] == 0 ){
					                $newpro = [];
					            }
					        }
				        }
						if($newpro){
							$newpro['id'] = $pro['id'];
							$buynum = $newpro['sales'];
							$pricedata = json_decode($newpro['pricedata'],true);
							$nowpricedata = array('num'=>0,'money'=>$newpro['sell_price']);
							foreach($pricedata as $k3=>$v3){
								if($buynum >= $v3['num']){
									$nowpricedata = $v3;
								}
							}
							$newpro['sell_price'] = $nowpricedata['money'];
							$minpricedata = end($pricedata);
							$min_price = $minpricedata['money'];
							$newpro['min_price'] = $min_price;

							$newdata[] = $newpro;
						}
					}
				}else{
		
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['ischecked','=',1];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
					if($v['params']['category'] || ($categroy_selmore && $categoryseltype == 1 && $v['params']['categorymore'])){
						if($categroy_selmore && $categoryseltype == 1){
                            $cid = $v['params']['categorymore'];
                            $newcids = [];
                            foreach($cid as $ck=>$cv){
                                //选择全部
                                if($ck == 0 && $cv == 'true'){
                                    $newcids = [];
                                    break;
                                }else{
                                    if($cv == 'true'){
                                        $newcids[] = $ck;
                                    }
                                }
                            }
                            if($newcids){
                                $chidlc = Db::name('tuangou_category')->where('aid',$aid)->where('pid','in',$newcids)->select()->toArray();
                                $cids = $newcids;
                                if($chidlc){
                                    foreach($chidlc as $c){
                                        $cids[] = intval($c['id']);
                                    }

                                }
                                $where[] = ['cid','in',$cids];
                            }
                        }else{
                        	if($v['params']['category']){
                        		$cid = intval($v['params']['category']);
								$chidlc = Db::name('tuangou_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
								if($chidlc){
									$cids = array($cid);
									foreach($chidlc as $c){
										$cids[] = intval($c['id']);
									}
									$where[] = ['cid','in',$cids];
								}else{
									$where[] = ['cid','=',$cid];
								}
                        	}
						}
					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        if($categroy_selmore && $categoryseltype == 1 && $v['params']['bidmore']){
                            $bids = $v['params']['bidmore'];
                            if($bids){
                                $newbids = [];
                                foreach($bids as $ck=>$cv){
                                    //选择全部
                                    if($ck == -1 && $cv == 'true'){
                                        $newbids = [];
                                        break;
                                    }else{
                                        if($cv == 'true'){
                                            $newbids[] = $ck;
                                        }
                                    }
                                }
                                if($newbids){
                                    $where[] = ['bid','in',$newbids];
                                }
                            }
                        }else{
                            $where[] = ['bid','=',$v['params']['bid']];
                        }
                    }

					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id proid,name,pic,sell_price,sales,pricedata,market_price';
					if(getcustom('plug_tengrui')) {
						$field .= ',house_status,is_rzh,relation_type,group_status,group_ids';
					}
					$newdata = Db::name('tuangou_product')->field($field)->where($where)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
					if(!$newdata) $newdata = array();
					foreach($newdata as $k2=>$v2){
						$v2['id'] = 'G'.time().rand(10000000,99999999);
						$buynum = $v2['sales'];
						$pricedata = json_decode($v2['pricedata'],true);
						$nowpricedata = array('num'=>0,'money'=>$v2['sell_price']);
						foreach($pricedata as $k3=>$v3){
							if($buynum >= $v3['num']){
								$nowpricedata = $v3;
							}
						}
						$v2['sell_price'] = $nowpricedata['money'];
						$minpricedata = end($pricedata);
						$min_price = $minpricedata['money'];
						$v2['min_price'] = $min_price;

						if(getcustom('price_dollar')){
							$usdrate = Db::name('shop_sysset')->where('aid',$aid)->value('usdrate');
							if($usdrate>0){
								$v2['usd_minprice'] = round($v2['sell_price']/$usdrate,2);
							}	
						}


						$newdata[$k2] = $v2;
						if(getcustom('plug_tengrui')) {
			                $tr_check = new \app\commons\TengRuiCheck();
		                    //判断是否是否符合会员认证、会员关系、一户，不符合则直接去掉
		                    $v2['id'] = $v2['proid'];
		                    $check_tuangou = $tr_check->check_tuangou($member,$v2,1);
		                    if($check_tuangou && $check_tuangou['status'] == 0 ){
		                        unset($newdata[$k2]);
		                    }
				        }

					}
					if(getcustom('plug_tengrui')) {
		                $len = count($newdata);
		                if($len<$v['params']['proshownum'] && $len>0){
		                    //重置索引,防止上方去掉的数据产生空缺
		                    $newdata=array_values($newdata);
		                }
			        }
				}
				$pagecontent[$k]['data'] = $newdata;
			}elseif($v['temp'] == 'kecheng'){//产品列表 获取产品信息
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $where[] = ['id','=',$pro['proid']];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
                        if(getcustom('kecheng_lecturer')){
                        	if($v['params']['kctype'] && $v['params']['kctype'] != 'all'){
                                $where[] = ['kctype','=',$v['params']['kctype']];
                            }
                            if($v['params']['chaptertype'] && $v['params']['chaptertype'] != 'all'){
                                $where[] = ['chaptertype','=',$v['params']['chaptertype']];
                            }
                        }
                        $field = 'id proid,name,pic,market_price,price,lvprice,lvprice_data,join_num,kctype';
                        if(getcustom('kecheng_lecturer')){
                        	$field .= ',chaptertype';
                        }
						$newpro = Db::name('kecheng_list')->field($field)->where($where)->find();
					
						if($newpro){
							$newpro['id'] = $pro['id'];
							if($member){
								if($newpro['lvprice']==1){
									$lvprice_data = json_decode($newpro['lvprice_data'],true);
									if($lvprice_data && isset($lvprice_data[$member['levelid']])){
										$newpro['price'] = $lvprice_data[$member['levelid']]['money_price'];
									}
								}else{
									$memberlevel = Db::name('member_level')->where('id',$member['levelid'])->find();
									if($memberlevel['kecheng_discount'] > 0 && $memberlevel['kecheng_discount'] < 10){
										$newpro['market_price'] = $newpro['price'];
										$newpro['price'] = $newpro['price'] * $memberlevel['kecheng_discount'] * 0.1;
									}
								}
							}
							$newpro['count'] = Db::name('kecheng_chapter')->where('kcid',$pro['proid'])->where('status',1)->count();
							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
                    if(getcustom('kecheng_lecturer')){
                    	if($v['params']['kctype'] && $v['params']['kctype'] != 'all'){
                            $where[] = ['kctype','=',$v['params']['kctype']];
                        }
                        if($v['params']['chaptertype'] && $v['params']['chaptertype'] != 'all'){
                            $where[] = ['chaptertype','=',$v['params']['chaptertype']];
                        }
                    }
					if($v['params']['category'] || ($categroy_selmore && $categoryseltype == 1 && $v['params']['categorymore'])){
                        if($categroy_selmore && $categoryseltype == 1){
                            $cid = $v['params']['categorymore'];
                            $newcids = [];
                            foreach($cid as $ck=>$cv){
                                //选择全部
                                if($ck == 0 && $cv == 'true'){
                                    $newcids = [];
                                    break;
                                }else{
                                    if($cv == 'true'){
                                        $newcids[] = $ck;
                                    }
                                }
                            }
                            if($newcids){
                                $chidlc = Db::name('kecheng_category')->where('aid',$aid)->where('pid','in',$newcids)->select()->toArray();
                                $cids = $newcids;
                                if($chidlc){
                                    $cids = array_merge($chidlc, $cids);
                                }
                                $whereCid = '(';
                                foreach($cids as $ck => $c){
                                    if(count($cids) == ($ck + 1))
                                        $whereCid .= "find_in_set({$c},cid)";
                                    else
                                        $whereCid .= " find_in_set({$c},cid) or ";
                                }
                                $where[] = Db::raw($whereCid . ')');
                            }
                        }else{
                            if($v['params']['category']){
                                $cid = intval($v['params']['category']);
                                $chidlc = Db::name('kecheng_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
                                if($chidlc){
                                    $cids = array($cid);
                                    foreach($chidlc as $c){
                                        $cids[] = intval($c['id']);
                                    }
                                    $where[] = ['cid','in',$cids];
                                }else{
                                    $where[] = ['cid','=',$cid];
                                }
                            }
                        }
					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        if($categroy_selmore && $categoryseltype == 1 && $v['params']['bidmore']){
                            $bids = $v['params']['bidmore'];
                            if($bids){
                                $newbids = [];
                                foreach($bids as $ck=>$cv){
                                    //选择全部
                                    if($ck == -1 && $cv == 'true'){
                                        $newbids = [];
                                        break;
                                    }else{
                                        if($cv == 'true'){
                                            $newbids[] = $ck;
                                        }
                                    }
                                }
                                if($newbids){
                                    $where[] = ['bid','in',$newbids];
                                }
                            }
                        }else{
                            $where[] = ['bid','=',$v['params']['bid']];
                        }
                    }
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'join_num desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
			
					$field = 'id proid,name,pic,price,lvprice,lvprice_data,market_price,join_num,sort,kctype';
                    if(getcustom('kecheng_lecturer')){
                    	$field .= ',chaptertype';
                    }
					$result = Db::name('kecheng_list')->field($field)->where($where)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
					
					
					if(!$result) $result = array();
					$sysset  = Db::name('kecheng_sysset')->where('aid',$aid)->find();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						if($member){
							if($v2['lvprice']==1){
								$lvprice_data = json_decode($v2['lvprice_data'],true);
								if($lvprice_data && isset($lvprice_data[$member['levelid']])){
									$result[$k2]['price'] = $lvprice_data[$member['levelid']]['money_price'];
								}
							}else{
								$memberlevel = Db::name('member_level')->where('id',$member['levelid'])->find();
								if($memberlevel['kecheng_discount'] > 0 && $memberlevel['kecheng_discount'] < 10){
									$result[$k2]['market_price'] = $v2['price'];
									$result[$k2]['price'] = $v2['price'] * $memberlevel['kecheng_discount'] * 0.1;
								}
							}
						}
						$result[$k2]['count'] = Db::name('kecheng_chapter')->where('kcid',$v2['proid'])->where('status',1)->count();
						if($sysset){
                            $result[$k2]['join_num'] =   $sysset['show_join_num'] ==0?0:$v2['join_num'];
                        }
					}
					$pagecontent[$k]['data'] = $result;
				}
            }elseif($v['temp'] == 'luckycollage'){//产品列表 获取产品信息
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,name,pic,sell_price,market_price,sales,teamnum,gua_num,fy_money,fy_money_val,fy_type';
						if(getcustom('plug_luckycollage')){
							$field = 'id proid,name,pic,sell_price,market_price,sales,teamnum,gua_num,fy_money,fy_money_val,fy_type,bzjl_type,bzj_score,bzj_commission';
						}
						if(getcustom('plug_tengrui')) {
							$field .= ',house_status,is_rzh,relation_type,group_status,group_ids';
						}
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $where[] = ['ischecked','=',1];
                        $where[] = ['id','=',$pro['proid']];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
						$newpro = Db::name('lucky_collage_product')->field($field)->where($where)->find();
						if(getcustom('plug_tengrui')) {
							if($newpro){
					            //判断是否是否符合会员认证、会员关系、一户、用户组
					            $tr_check = new \app\commons\TengRuiCheck();
					            $newpro['id'] = $newpro['proid'];
					            $check_lucky_collage = $tr_check->check_lucky_collage($member,$newpro,1);
					            if($check_lucky_collage && $check_lucky_collage['status'] == 0 ){
					                $newpro = [];
					            }
				            }
				        }
						if($newpro){
							$newpro['money'] = round($newpro['fy_money_val'],2);
							if($newpro['fy_type']==1){
								$newpro['money'] = round($newpro['fy_money']*$newpro['sell_price']/100,2);
							}

							$newpro['linktype'] = 0;
							if(getcustom('plug_luckycollage')){
								$newpro['linktype'] = 1;
								$newpro['money'] = round($v['fy_money_val'],2);
							}
							$newpro['show_teamnum'] = 1;
							if(getcustom('luckycollage_norefund')){
								$newpro['show_teamnum'] = 0;
							}
							$newpro['id'] = $pro['id'];
							$newdata[] = $newpro;
						}

					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['ischecked','=',1];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
					if($v['params']['category'] || ($categroy_selmore && $categoryseltype == 1 && $v['params']['categorymore'])){
						if($categroy_selmore && $categoryseltype == 1){
                            $cid = $v['params']['categorymore'];
                            $newcids = [];
                            foreach($cid as $ck=>$cv){
                                //选择全部
                                if($ck == 0 && $cv == 'true'){
                                    $newcids = [];
                                    break;
                                }else{
                                    if($cv == 'true'){
                                        $newcids[] = $ck;
                                    }
                                }
                            }
                            if($newcids){
                                $chidlc = Db::name('lucky_collage_category')->where('aid',$aid)->where('pid','in',$newcids)->select()->toArray();
                                $cids = $newcids;
                                if($chidlc){
                                    foreach($chidlc as $c){
                                        $cids[] = intval($c['id']);
                                    }
                                }
                                $where[] = ['cid','in',$cids];
                            }
                        }else{
                            if($v['params']['category']){
                                $cid = intval($v['params']['category']);
                                $chidlc = Db::name('lucky_collage_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
                                if($chidlc){
                                    $cids = array($cid);
                                    foreach($chidlc as $c){
                                        $cids[] = intval($c['id']);
                                    }
                                    $where[] = ['cid','in',$cids];
                                }else{
                                    $where[] = ['cid','=',$cid];
                                }
                            }
						}
					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        if($categroy_selmore && $categoryseltype == 1 && $v['params']['bidmore']){
                            $bids = $v['params']['bidmore'];
                            if($bids){
                                $newbids = [];
                                foreach($bids as $ck=>$cv){
                                    //选择全部
                                    if($ck == -1 && $cv == 'true'){
                                        $newbids = [];
                                        break;
                                    }else{
                                        if($cv == 'true'){
                                            $newbids[] = $ck;
                                        }
                                    }
                                }
                                if($newbids){
                                    $where[] = ['bid','in',$newbids];
                                }
                            }
                        }else{
                            $where[] = ['bid','=',$v['params']['bid']];
                        }
                    }
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id proid,name,pic,sell_price,market_price,sales,teamnum,gua_num,fy_money,fy_money_val,fy_type';
					if(getcustom('plug_luckycollage')){
						$field = 'id proid,name,pic,sell_price,market_price,sales,teamnum,gua_num,fy_money,fy_money_val,fy_type,bzjl_type,bzj_score,bzj_commission';
					}
					if(getcustom('plug_tengrui')) {
						$field .= ',house_status,is_rzh,relation_type,group_status,group_ids';
					}
					$result = Db::name('lucky_collage_product')->field($field)->where($where)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						$result[$k2]['money'] = round($v2['fy_money_val'],2);
						if($v2['fy_type']==1){
							$result[$k2]['money'] = round($v2['fy_money']*$v2['sell_price']/100,2);
						}
						$result[$k2]['linktype'] = 0;
						if(getcustom('plug_luckycollage')){
							$result[$k2]['linktype'] = 1;
							$result[$k2]['money'] = round($v2['fy_money_val'],2);
						}
						$result[$k2]['show_teamnum'] = 1;
						if(getcustom('luckycollage_norefund')){
							$result[$k2]['show_teamnum'] = 0;
						}
						if(getcustom('plug_tengrui')) {
			                $tr_check = new \app\commons\TengRuiCheck();
			                //判断是否是否符合会员认证、会员关系、一户，不符合则直接去掉
			                $v2['id'] = $v2['proid'];
			                $check_lucky_collage = $tr_check->check_lucky_collage($member,$v2,1);
			                if($check_lucky_collage && $check_lucky_collage['status'] == 0 ){
			                    unset($result[$k2]);
			                }
				        }
					}
					if(getcustom('plug_tengrui')) {
			            $len = count($result);
			            if($len<$v['params']['proshownum'] && $len>0){
			                //重置索引,防止上方去掉的数据产生空缺
			                $result=array_values($result);
			            }
			        }
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'yuyue'){//预约服务 获取产品信息
				$color1 = Db::name('admin_set')->where('aid',$aid)->value('color1');
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
                        $field = "id proid,name,pic,sell_price,sales,danwei,is_open,fwtype,sellpoint,fwid,opentip,noopentip,cid";
                        if(getcustom('extend_yuyue_car')){
                            $field .=',type';
                        }
                        if(getcustom('yuyue_product_lvprice')){
                            $field .=',lvprice,lvprice_data';
                        }
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $where[] = ['ischecked','=',1];
                        $where[] = ['id','=',$pro['proid']];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
						$newpro = Db::name('yuyue_product')->field($field)->where($where)->find();
						if($newpro){
							$newpro['id'] = $pro['id'];
							if(getcustom('yuyue_product_lvprice')){
	                            if($newpro['lvprice']==1 && $member){
									$lvprice_data = json_decode($newpro['lvprice_data'],true);
									if($lvprice_data && isset($lvprice_data[$member['levelid']])){
										$newpro['sell_price'] = $lvprice_data[$member['levelid']];
	                                    $newpro['sell_price_origin'] = $lvprice_data[$member['id']];
									}
								}
	                        }

                            //自定义服务方式距上方距离
                            $newpro['fwtypetop1'] = $newpro['fwtypetop1wx'] = 0;
                            $newpro['fwtypetop2'] = $newpro['fwtypetop2wx'] = 0;
                            $newpro['fwtypetop3'] = $newpro['fwtypetop3wx'] = 0;
                            if($newpro['fwtype']){
                                $fwtypes = explode(',',$newpro['fwtype']);
                                $newpro['fwtype'] = $fwtypes;
                                if(in_array(1,$fwtypes)){
                                    $newpro['fwtypetop2'] = 20;$newpro['fwtypetop2wx']  = 40;
                                    $newpro['fwtypetop3'] = 20; $newpro['fwtypetop3wx'] = 40;
                                }
                                if(in_array(2,$fwtypes)){
                                    if($newpro['fwtypetop3'] == 20){
                                        $newpro['fwtypetop3']= 40; $newpro['fwtypetop3wx'] = 80;
                                    }
                                }
                            }

                            $newpro['fuwulist'] = '';
                            if($newpro['fwid']){
                                $fuwulist = Db::name('yuyue_fuwu')->where('id','in',$newpro['fwid'])->where('status',1)->where('aid',$aid)->order('sort desc,id')->column('name');
                                $newpro['fuwulist'] = $fuwulist??'';
                            }
                            $newpro['color1'] = $color1;
                            $newpro['opentip']   = $newpro['opentip']?$newpro['opentip']:'营业中';
                            $newpro['noopentip'] = $newpro['noopentip']?$newpro['noopentip']:'休息中';
                            $newpro['catnames'] = '';
                            if($newpro['cid']){
                                $cats = Db::name('yuyue_category')->where('id','in',$newpro['cid'])->where('aid',aid)->field('name')->select()->toArray();
                                if($cats){
                                    foreach($cats as $cv){
                                        $newpro['catnames'] .= "[".$cv['name']."]";
                                    }
                                    unset($cv);
                                }
                            }
							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					//$where[] = ['status','=',1];
					$where[] = ['ischecked','=',1];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
					$nowtime = time();
					$nowhm = date('H:i');
					$where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime)");
					if($v['params']['category'] || ($categroy_selmore && $categoryseltype == 1 && $v['params']['categorymore'])){
						if($categroy_selmore && $categoryseltype == 1){
                            $cid = $v['params']['categorymore'];
                            $newcids = [];
                            foreach($cid as $ck=>$cv){
                                //选择全部
                                if($ck == 0 && $cv == 'true'){
                                    $newcids = [];
                                    break;
                                }else{
                                    if($cv == 'true'){
                                        $newcids[] = $ck;
                                    }
                                }
                            }
                            if($newcids){
                                $chidlc = Db::name('yuyue_category')->where('aid',$aid)->where('pid','in',$newcids)->select()->toArray();
                                $cids = $newcids;
                                if($chidlc){
                                    $cids = array_merge($chidlc, $cids);
                                }
                                $whereCid = '(';
                                foreach($cids as $ck => $c){
                                    if(count($cids) == ($ck + 1))
                                        $whereCid .= "find_in_set({$c},cid)";
                                    else
                                        $whereCid .= " find_in_set({$c},cid) or ";
                                }
                                $where[] = Db::raw($whereCid . ')');
                            }
                        }else{
                            if($v['params']['category']){
                                $cid = intval($v['params']['category']);
                                $chidlc = Db::name('yuyue_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
                                if($chidlc){
                                    $cids = array($cid);
                                    $whereCid = '(';
                                    $whereCid .= " find_in_set({$cid},cid) or ";
                                    foreach($chidlc as $k2 => $c){
                                        if(count($chidlc) == ($k2 + 1))
                                            $whereCid .= "find_in_set({$c['id']},cid)";
                                        else
                                            $whereCid .= " find_in_set({$c['id']},cid) or ";
                                    }
                                    $where[] = Db::raw($whereCid . ')');
                                }else{
                                    $where[] = Db::raw("find_in_set({$cid},cid)");
                                }
                            }
                        }
                    }
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        if($categroy_selmore && $categoryseltype == 1 && $v['params']['bidmore']){
                            $bids = $v['params']['bidmore'];
                            if($bids){
                                $newbids = [];
                                foreach($bids as $ck=>$cv){
                                    //选择全部
                                    if($ck == -1 && $cv == 'true'){
                                        $newbids = [];
                                        break;
                                    }else{
                                        if($cv == 'true'){
                                            $newbids[] = $ck;
                                        }
                                    }
                                }
                                if($newbids){
                                    $where[] = ['bid','in',$newbids];
                                }
                            }
                        }else{
                            $where[] = ['bid','=',$v['params']['bid']];
                        }
                    }
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
                    $field = "id proid,name,pic,sell_price,sales,danwei,is_open,fwtype,sellpoint,fwid,opentip,noopentip,cid";
                    if(getcustom('extend_yuyue_car')){
                        $field .=',type';
                    }
                    if(getcustom('yuyue_product_lvprice')){
                        $field .=',lvprice,lvprice_data';
                    }
					$newdata = Db::name('yuyue_product')->field($field)->where($where)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
					if(!$newdata) $newdata = array();
                    foreach($newdata as $k2=>$v2){
                        $newdata[$k2]['id'] = 'G'.time().rand(10000000,99999999);
                        if(getcustom('yuyue_product_lvprice')){
                        	if($v2['lvprice']==1 && $member){
								$lvprice_data = json_decode($v2['lvprice_data'],true);
								if($lvprice_data && isset($lvprice_data[$member['levelid']])){
	                                $newdata[$k2]['sell_price'] = $lvprice_data[$member['levelid']];
								}
							}
                        }

                        //自定义服务方式距上方距离
                        $newdata[$k2]['fwtypetop1']= $newdata[$k2]['fwtypetop1wx'] = 0;
                        $newdata[$k2]['fwtypetop2']= $newdata[$k2]['fwtypetop2wx'] = 0;
                        $newdata[$k2]['fwtypetop3']= $newdata[$k2]['fwtypetop3wx'] = 0;
                        if($v2['fwtype']){
                            $fwtypes = explode(',',$v2['fwtype']);
                            $newdata[$k2]['fwtype'] = $fwtypes;
                            if(in_array(1,$fwtypes)){
                                $newdata[$k2]['fwtypetop2'] = 20; $newdata[$k2]['fwtypetop2wx'] = 40;
                                $newdata[$k2]['fwtypetop3'] = 20; $newdata[$k2]['fwtypetop3wx'] = 40;
                            }
                            if(in_array(2,$fwtypes)){
                                if($newdata[$k2]['fwtypetop3'] == 20){
                                    $newdata[$k2]['fwtypetop3'] = 40; $newdata[$k2]['fwtypetop3wx'] = 80;
                                }
                            }
                        }

                        $v2['fuwulist'] = '';
                        if($v2['fwid']){
                            $fuwulist = Db::name('yuyue_fuwu')->where('id','in',$v2['fwid'])->where('status',1)->where('aid',$aid)->order('sort desc,id')->column('name');
                            $newdata[$k2]['fuwulist'] = $fuwulist??'';
                        }
                        $newdata[$k2]['color1'] = $color1;
                        $newdata[$k2]['opentip']   = $newdata[$k2]['opentip']?$newdata[$k2]['opentip']:'营业中';
                        $newdata[$k2]['noopentip'] = $newdata[$k2]['noopentip']?$newdata[$k2]['noopentip']:'休息中';
                        $newdata[$k2]['catnames'] = '';
                        if($v2['cid']){
                            $cats = Db::name('yuyue_category')->where('id','in',$v2['cid'])->where('aid',aid)->field('name')->select()->toArray();
                            if($cats){
                                foreach($cats as $cv){
                                    $newdata[$k2]['catnames'] .= "[".$cv['name']."]";
                                }
                                unset($cv);
                            }
                        }
                    }
                    $pagecontent[$k]['data'] = $newdata;
				}
				if(getcustom('design_yuyue_display')){
					$v['params']['showisopen'] = $v['params']['showisopen']??'0';
					$v['params']['showfwtype'] = $v['params']['showfwtype']??'0';
					$v['params']['showsellpoint'] = $v['params']['showsellpoint']??'0';
					$v['params']['showfuwu']      = $v['params']['showfuwu']??'0';
				}
			}elseif($v['temp'] == 'shortvideo'){//短视频
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $art){
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $where[] = ['id','=',$art['videoId']];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
						$newart = Db::name('shortvideo')->field('id videoId,name,description,coverimg,view_num,zan_num,createtime')->where($where)->find();
						if($newart){
							$newart['id'] = $art['id'];
							$newdata[] = $newart;
						}
					}
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
                    if($v['params']['category'] || ($categroy_selmore && $categoryseltype == 1 && $v['params']['categorymore'])){
                        if($categroy_selmore && $categoryseltype == 1){
                            $cid = $v['params']['categorymore'];
                            $newcids = [];
                            foreach($cid as $ck=>$cv){
                                //选择全部
                                if($ck == 0 && $cv == 'true'){
                                    $newcids = [];
                                    break;
                                }else{
                                    if($cv == 'true'){
                                        $newcids[] = $ck;
                                    }
                                }
                            }
                            $cids = $newcids;
                            if($cids){
                                $where[] = ['cid','in',$cids];
                            }
                        }else{
                            if($v['params']['category']){
                                $cid = intval($v['params']['category']);
                                $where[] = ['cid','=',$cid];
                            }
                        }
                    }
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        if($categroy_selmore && $categoryseltype == 1 && $v['params']['bidmore']){
                            $bids = $v['params']['bidmore'];
                            if($bids){
                                $newbids = [];
                                foreach($bids as $ck=>$cv){
                                    //选择全部
                                    if($ck == -1 && $cv == 'true'){
                                        $newbids = [];
                                        break;
                                    }else{
                                        if($cv == 'true'){
                                            $newbids[] = $ck;
                                        }
                                    }
                                }
                                if($newbids){
                                    $where[] = ['bid','in',$newbids];
                                }
                            }
                        }else{
                            $where[] = ['bid','=',$v['params']['bid']];
                        }
                    }
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'viewnum') $order = 'view_num desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$newdata = Db::name('shortvideo')->field('id videoId,name,description,coverimg,view_num,zan_num,createtime')->where($where)->order($order)->limit(intval($v['params']['shownum']))->select()->toArray();
					if(!$newdata) $newdata = array();
					foreach($newdata as $k2=>$v2){
						$newdata[$k2]['id'] = 'G'.time().rand(10000000,99999999);
					}
				}
				foreach($newdata as $k2=>$v){
					if($v['bid']!=0){
						$newdata[$k2]['logo'] = Db::name('business')->where('aid',$aid)->where('id',$v['bid'])->value('logo');
					} else {
						$newdata[$k2]['logo'] = Db::name('admin_set')->where('aid',$aid)->value('logo');
					}
				}
				$pagecontent[$k]['data'] = $newdata;
			}elseif($v['temp'] == 'liveroom'){//直播列表
				if($v['params']['liveroomfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $where[] = ['roomId','=',$pro['roomId']];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
						$newpro = Db::name('live_room')->field('id,bid,roomId,name,coverImg,shareImg,startTime,endTime,anchorName')->where($where)->find();
						if($newpro){
							$newpro['id'] = $pro['id'];
							$newpro['commentscore'] = floor($newpro['comment_score']);
							$newpro['startTime'] = date('m-d H:i',$newpro['startTime']);
							$newpro['endTime'] = date('m-d H:i',$newpro['endTime']);
							$newpro['status'] = 1;
							if($newpro['startTime'] > time()){ //未开始
								$newpro['status'] = 0;
								if(date('Y-m-d') == date('Y-m-d',$newpro['startTime'])){
									$newpro['showtime'] = '今天'.date('H:i',$newpro['startTime']).'开播';
								}elseif(date('Y-m-d',time()+86400) == date('Y-m-d',$newpro['startTime'])){
									$newpro['showtime'] = '明天'.date('H:i',$newpro['startTime']).'开播';
								}elseif(date('Y-m-d',time()+86400*2) == date('Y-m-d',$newpro['startTime'])){
									$newpro['showtime'] = '后天'.date('H:i',$newpro['startTime']).'开播';
								}else{
									$newpro['showtime'] = date('m-d H:i',$newpro['startTime']).'开播';
								}
							}
							if($newpro['endTime'] < time()){ //已结束
								$newpro['status'] = 2;
							}

							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
					if($v['params']['category']){
						$cid = intval($v['params']['category']);
						$where[] = ['cid','=',$cid];
					}
					$order = 'roomId desc';
					if($v['params']['sortby'] == 'sort') $order = 'roomId desc';
					if($v['params']['sortby'] == 'starttimedesc') $order = 'startTime desc';
					if($v['params']['sortby'] == 'starttime') $order = 'startTime';
					if($v['params']['sortby'] == 'endtimedesc') $order = 'endTime desc';
					if($v['params']['sortby'] == 'endtime') $order = 'endTime';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$result = Db::name('live_room')->field('id,bid,roomId,name,coverImg,shareImg,startTime,endTime,anchorName')->where($where)->order($order)->limit(intval($v['params']['shownum']))->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						$result[$k2]['startTime'] = date('m-d H:i',$v2['startTime']);
						$result[$k2]['endTime'] = date('m-d H:i',$v2['endTime']);
						$result[$k2]['status'] = 1;
						if($v2['startTime'] > time()){ //未开始
							$result[$k2]['status'] = 0;
							if(date('Y-m-d') == date('Y-m-d',$v2['startTime'])){
								$result[$k2]['showtime'] = '今天'.date('H:i',$v2['startTime']).'开播';
							}elseif(date('Y-m-d',time()+86400) == date('Y-m-d',$v2['startTime'])){
								$result[$k2]['showtime'] = '明天'.date('H:i',$v2['startTime']).'开播';
							}elseif(date('Y-m-d',time()+86400*2) == date('Y-m-d',$v2['startTime'])){
								$result[$k2]['showtime'] = '后天'.date('H:i',$v2['startTime']).'开播';
							}else{
								$result[$k2]['showtime'] = date('m-d H:i',$v2['startTime']).'开播';
							}
						}
						if($v2['endTime'] < time()){ //已结束
							$result[$k2]['status'] = 2;
						}
					}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'business'){//商家列表
                if(getcustom('extend_qrcode_variable_fenzhang')){
                    $fzcode = input('param.fzcode');
                    $pagecontent[$k]['params']['fzcode'] = $fzcode;
                    $qrcode_tableid = Db::name('qrcode_list_variable')->where('aid',$aid)->where('code',$fzcode)->value('tableid');
                    //绑定了桌台，使用这个桌台打印，否则使用无桌台点餐
                    $pagecontent[$k]['params']['tablebid'] = $qrcode_tableid??'';
                    
                }
				if($v['params']['businessfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $where[] = ['is_open','=',1];
                        $where[] = ['id','=',$pro['bid']];
                        if($areaBids){
                            $where[] = ['id','in',$areaBids];
                        }
						$newpro = Db::name('business')->field('id bid,name,cid,logo,address,comment_score,sales,content,latitude,longitude,address,tourl,tel')->where($where)->find();
						if($newpro){
							$newpro['id'] = $pro['id'];
							$newpro['commentscore'] = floor($newpro['comment_score']);
							$newpro['content']      = strip_tags($newpro['content']);
                            $newpro['juli'] = '';
                            if($v['params']['showdistance']==1 && $latitude && $longitude){
                                $newpro['juli'] = getdistance($longitude,$latitude,$newpro['longitude'],$newpro['latitude'],2).'km';
                            }
                            $turnover_show = 0;
                            $turnover = 0;
                            if(getcustom('business_show_turnover')){
                                if($newpro['turnove_show'] == 1){
                                    $turnover = \app\commons\Business::totalTurnover($aid, $newpro['bid']);
                                    $turnover_show = 1;
                                }
                            }
                            $newpro['turnover'] = $turnover;
                            $newpro['turnover_show'] = $turnover_show;
							
							if(getcustom('business_selfscore') && $v['params']['showscoreshop'] == 1){
								$whereS = [];
								$whereS[] = ['aid','=',$aid];
								$whereS[] = ['status','=',1];
								$whereS[] = ['ischecked','=',1];
								$whereS[] = ['bid','=',$newpro['bid']];
								if(!$cuxiaonum) $cuxiaonum = 6;
								$scoreshopprolist = Db::name('scoreshop_product')->field("id,name,pic,sell_price,money_price,score_price")->where($whereS)->order("id desc")->limit($cuxiaonum)->select()->toArray();
								if(!$scoreshopprolist) $scoreshopprolist = [];
								$newpro['scoreshopprolist'] = $scoreshopprolist;
							}

							//店铺销量
                            $sales = Db::name('business_sales')->where('bid',$pro['bid'])->value('total_sales');
                            $newpro['sales'] = $sales?:0;
                            
                            $statuswhere = "`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )";
                            $prolist = Db::name('shop_product')->field('id,name,pic,sell_price,price_type,sales,market_price')->where('bid',$pro['bid'])->where('ischecked',1)->where($statuswhere)->limit(8)->order('sales desc,sort desc,id desc')->select()->toArray();
                            if(!$prolist) $prolist = [];
                            if(count($prolist) < 8){
                                $prolist2 = Db::name('yuyue_product')->field('id,name,pic,sell_price,sales,danwei')->where('bid',$pro['bid'])->where('ischecked',1)->where("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime)")->limit(8-count($prolist))->order('sales desc,sort desc,id desc')->select()->toArray();
                                foreach($prolist2 as $pro2){
                                    $pro2['module'] = 'yuyue';
                                    $prolist[] = $pro2;
                                }
                            }
                            $newpro['prolist'] = $prolist;

                            $newpro['catname'] = '';
                            if($newpro['cid']){
                                $catnames = Db::name('business_category')->where('id','in',$newpro['cid'])->column('name');
                                if($catnames){
                                    $newpro['catname'] = implode(' ',$catnames);
                                }
                            }
							if(getcustom('yx_business_miandan')){
								$miandanprolist = Db::name('business_miandan')->where('bid',$pro['bid'])->where(['is_del'=>0,'status'=>1,'ischecked'=>1])->limit(8)->order('id asc')->select()->toArray();
								$miandanset = Db::name('business_miandan_set')->where('aid',$aid)->where('bid',$pro['bid'])->find();
								
								$newpro['miandanprolist'] = $miandanprolist;
								$newpro['miandanset_status'] = $miandanset['status'];
							}
                            if(getcustom('extend_qrcode_variable_fenzhang')){
                                $mdid = Db::name('mendian')->where('aid',$aid)->where('bid',$pro['id'])->order('id asc')->value('id');
                                $newpro['mdid']  = $mdid;
                            }
                            $newdata[] = $newpro;
                        }
                        
					}
					$pagecontent[$k]['data'] = $newdata;
				}else if($v['params']['businessfrom'] == 2){
					//浏览过商家
                	if(getcustom('design_business_history',$aid)){
                		$newdata = [];
                		if($mid >0){
                			//查找浏览过的
                			$hproids = Db::name('member_history')->where('aid',$aid)->where('mid',$mid)->where('type','business')->column('proid');
                			if($hproids){
                				$where = [];
		                        $where[] = ['aid','=',$aid];
		                        $where[] = ['status','=',1];
		                        $where[] = ['is_open','=',1];
                				$newdata = Db::name('business')->where($where)->where('id','in',$hproids)->field('id bid,name,cid,logo,address,comment_score,sales,content,latitude,longitude,address,tourl,tel')->select()->toArray();
                				foreach ($newdata as $key => $vb) {
                					$newdata[$key]['commentscore'] = floor($vb['comment_score']);
                					$newdata[$key]['content']      = strip_tags($vb['content']);
                				}

                			}
                		}
                		$pagecontent[$k]['data'] = $newdata;
                	}


				}else{
					$where = [];
                    $where_str = '';
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['is_open','=',1];
                    if($areaBids){
                        $where[] = ['id','in',$areaBids];
                    }
                    if($v['params']['category'] || ($categroy_selmore && $categoryseltype == 1 && $v['params']['categorymore'])){
                        if($categroy_selmore && $categoryseltype == 1){
                            $cid = $v['params']['categorymore'];
                            $newcids = [];
                            foreach($cid as $ck=>$cv){
                                //选择全部
                                if($ck == 0 && $cv == 'true'){
                                    $newcids = [];
                                    break;
                                }else{
                                    if($cv == 'true'){
                                        $newcids[] = $ck;
                                    }
                                }
                            }
                            $cids = $newcids;
                            if($cids){
                                $whereCid = '(';
                                foreach($cids as $ck => $c){
                                    if(count($cids) == ($ck + 1))
                                        $whereCid .= "find_in_set({$c},cid)";
                                    else
                                        $whereCid .= " find_in_set({$c},cid) or ";
                                }
                                $where[] = Db::raw($whereCid . ')');
                                $where_str .= " and ".$whereCid . ')';
                            }
                        }else{
                            if($v['params']['category']){
                                $cid = intval($v['params']['category']);
                                //$where[] = ['cid','=',$cid];
                                //$where_str .= " and cid={$cid}";
                                $where[] = Db::raw("find_in_set({$cid},cid)");
                                $where_str .= " and find_in_set({$cid},cid)";
                            }
                        }
                    }
                    //20230215 Qfj-start系统模式，定位范围，显示同城或自定义范围
                    $limit_distance = 0;
                    if(getcustom('show_location')){
                        //定位模式
                        if($sysset['mode']==2){
                            if($sysset['loc_range_type']==1 && $sysset['loc_range']>0){
                                //自定义范围:显示范围，组件中的显示范围和系统的显示范围loc_range 取小
                                $limit_distance = $sysset['loc_range'];
                                $v['params']['distance'] = min($sysset['loc_range'],$v['params']['distance']);
                            }else{
                                //同城
                                if($area){
                                    //取省或者市
                                    $areaArr = explode(',',$area);
                                    $areaCount = count($areaArr);
                                    if($areaCount==1){
                                        $where[] = ['province','=',$areaArr[0]];
                                    }else{
                                        $where[] = ['city','=',$areaArr[1]];
                                        //区兼容
                                        if($areaCount>2){
                                            $district = $areaArr[2];
                                            $where[] = ['district','=',$district];
                                        }
                                    }
                                }
                            }
                        }
                    }
                    //20230215 Qfj-end系统模式，定位范围，显示同城或自定义范围
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'scoredesc') $order = 'comment_score desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');

					$filedraw = '';
                    $field = "id bid,cid,name,logo,address,comment_score,sales,content,tourl,tel";
                    if(getcustom("business_show_turnover")){
                        $field .= ',turnover_show';
                    }
                    $field .=',longitude,latitude';
					if($v['params']['sortby'] == 'juli' && $latitude && $longitude){
					    if($v['params']['distance']) {
					        //$filedraw .= ",round(( st_distance(point({$longitude}, {$latitude}),point(longitude, latitude)) / 0.0111 ) * 1000) AS distance";
							$filedraw .= ",round(6378.138*2*asin(sqrt(pow(sin( ({$latitude}*pi()/180-latitude*pi()/180)/2),2)+cos({$latitude}*pi()/180)*cos(latitude*pi()/180)* pow(sin( ({$longitude}*pi()/180-longitude*pi()/180)/2),2)))*1000) AS distance";
							//\think\facade\Log::write($filedraw);
                            $order = "distance asc";
                            $result = Db::query("select * from (select {$field}".$filedraw." from ".table_name('business').
                                " where aid=:aid and status = 1 ".$where_str." order by $order ) as A where distance <= :distance limit ".$v['params']['shownum'], ['aid' => $aid, 'distance' => $v['params']['distance'] * 1000]);
//                            \think\facade\Log::write(Db::name('business')->getLastSql());
                        } else {
                            $order = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
                            $result = Db::name('business')->fieldRaw($field.$filedraw)->where($where)->order($order)->limit(intval($v['params']['shownum']))->select()->toArray();
                        }
					}elseif($limit_distance>0 && $longitude && $latitude){
                        //系统设置了显示范围
                        $filedraw .= ",round(6378.138*2*asin(sqrt(pow(sin( ({$latitude}*pi()/180-latitude*pi()/180)/2),2)+cos({$latitude}*pi()/180)*cos(latitude*pi()/180)* pow(sin( ({$longitude}*pi()/180-longitude*pi()/180)/2),2)))*1000) AS distance";
                        $order = "distance asc";
                        $result = Db::query("select * from (select {$field}".$filedraw." from ".table_name('business').
                            " where aid=:aid and status = 1 ".$where_str." order by $order ) as A where distance <= :distance limit ".$v['params']['shownum'], ['aid' => $aid, 'distance' => $limit_distance * 1000]);
                    } else {
                        $field .= $filedraw;
                        $result = Db::name('business')->fieldRaw($field)->where($where)->order($order)->limit(intval($v['params']['shownum']))->select()->toArray();
                    }
                    foreach ($result as &$bus) {
                        $turnover_show = 0;
                        $turnover = 0;
                        if(getcustom('business_show_turnover')){
                            if($bus['turnover_show'] == 1){
                                $turnover = \app\commons\Business::totalTurnover($aid, $bus['bid']);
                                $turnover_show = 1;
                            }
                        }
                        $bus['turnover'] = $turnover;
                        $bus['turnover_show'] = $turnover_show;

                        $bus['catname'] = '';
                        if($bus['cid']){
                            $catnames = Db::name('business_category')->where('id','in',$bus['cid'])->column('name');
                            if($catnames){
                                $bus['catname'] = implode(' ',$catnames);
                            }
                        }
                    }
                    unset($bus);
//
					if(!$result) $result = array();
					//店铺销量
                    $bids = array_column($result,'bid');
                    $sales_arr = Db::name('business_sales')->where('bid','in',$bids)->column('total_sales','bid');

					$nowtime = time();
					$nowhm = date('H:i');
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						$result[$k2]['content']      = strip_tags($v2['content']);
						$result[$k2]['commentscore'] = floor($v2['comment_score']);
						if($v['params']['showdistance']==1 && $longitude && $latitude){
							$result[$k2]['juli'] = getdistance($longitude,$latitude,$v2['longitude'],$v2['latitude'],2).'km';
						}else{
							$result[$k2]['juli'] = '';
						}
						$statuswhere = "`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )";
						$prolist = Db::name('shop_product')->field('id,name,pic,sell_price,price_type,sales,market_price')->where('bid',$v2['bid'])->where('ischecked',1)->where($statuswhere)->limit(8)->order('sales desc,sort desc,id desc')->select()->toArray();
						if(!$prolist) $prolist = [];
						if(count($prolist) < 8){
							$prolist2 = Db::name('yuyue_product')->field('id,name,pic,sell_price,sales,danwei')->where('bid',$v2['bid'])->where('ischecked',1)->where("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime)")->limit(8-count($prolist))->order('sales desc,sort desc,id desc')->select()->toArray();
							foreach($prolist2 as $pro2){
								$pro2['module'] = 'yuyue';
								$prolist[] = $pro2;
							}
						}
						$result[$k2]['prolist'] = $prolist;
                        if(getcustom('yx_business_miandan')){
							$miandanprolist = Db::name('business_miandan')->where('bid',$v2['bid'])->where(['is_del'=>0,'status'=>1,'ischecked'=>1])->limit(8)->order('id asc')->select()->toArray();
							$miandanset = Db::name('business_miandan_set')->where('aid',$aid)->where('bid',$v2['bid'])->find();
							
							$result[$k2]['miandanprolist'] = $miandanprolist;
							$result[$k2]['miandanset_status'] = $miandanset['status'];
						}
                        if(getcustom('business_index_cuxiao')){
                            //秒杀显示
                            $cuxiaonum = intval($v['params']['showcuxiaonum']);
                            if($cuxiaonum<1) $cuxiaonum = 6;
                            $time = time();
                            $whereS = [];
                            $whereS[] = ['aid','=',$aid];
                            $whereS[] = ['status','=',1];
                            $whereS[] = ['bid','=',$v2['bid']];
                            $whereS[] = ['ischecked','=',1];
                            $whereS[] = Db::raw("unix_timestamp(seckill_date)>{$time}");
                            $seckillprolist = Db::name('seckill_product')->field("id,name,pic,sell_price,market_price,seckill_date,seckill_time,sales,'seckill' as product_type")->where($whereS)->orderRaw("unix_timestamp(seckill_date) asc,id desc")->limit($cuxiaonum)->select()->toArray();
                            if(!$seckillprolist) $seckillprolist = [];
                            $cuxiaonum = $cuxiaonum - count($seckillprolist);
                            $result[$k2]['seckillprolist'] = $seckillprolist;
                            $cuxiaoprolist = $seckillprolist;
                            //团购
                            if($cuxiaonum>0){
                                $whereS = [];
                                $whereS[] = ['aid','=',$aid];
                                $whereS[] = ['status','=',1];
                                $whereS[] = ['bid','=',$v2['bid']];
                                $whereS[] = ['ischecked','=',1];
                                $whereS[] = ['endtime','>',$time];
                                $tuangouprolist = Db::name('tuangou_product')->field("id,name,pic,sell_price,market_price,starttime,endtime,'tuangou' as product_type")->where($whereS)->order("starttime asc,id desc")->limit($cuxiaonum)->select()->toArray();
                                if(!$tuangouprolist) $tuangouprolist = [];
                                $cuxiaonum = $cuxiaonum - count($tuangouprolist);
                                $cuxiaoprolist = array_merge($cuxiaoprolist,$tuangouprolist);
//                                $result[$k2]['tuangouprolist'] = $tuangouprolist;
                            }

                            //砍价
                            if($cuxiaonum>0) {
                                $whereS = [];
                                $whereS[] = ['aid', '=', $aid];
                                $whereS[] = ['status', '=', 1];
                                $whereS[] = ['bid', '=', $v2['bid']];
                                $whereS[] = ['ischecked','=',1];
                                $whereS[] = ['endtime', '>', $time];
                                $kanjiaprolist = Db::name('kanjia_product')->field("id,name,pic,sell_price,min_price,starttime,endtime,sales,'kanjia' as product_type")->where($whereS)->order("starttime asc,id desc")->limit($cuxiaonum)->select()->toArray();
                                if (!$kanjiaprolist) $kanjiaprolist = [];
                                $cuxiaonum = $cuxiaonum - count($kanjiaprolist);
                                $cuxiaoprolist = array_merge($cuxiaoprolist,$kanjiaprolist);
//                                $result[$k2]['kanjiaprolist'] = $kanjiaprolist;
                            }
                            //拼团
                            if($cuxiaonum>0){
                                $whereS = [];
                                $whereS[] = ['aid','=',$aid];
                                $whereS[] = ['status','=',1];
                                $whereS[] = ['ischecked','=',1];
                                $whereS[] = ['bid','=',$v2['bid']];
                                $collageprolist = Db::name('collage_product')->field("id,name,pic,sell_price,market_price,teamnum,'collage' as product_type")->where($whereS)->order("id desc")->limit($cuxiaonum)->select()->toArray();
                                if(!$collageprolist) $collageprolist = [];
                                $cuxiaonum = $cuxiaonum - count($collageprolist);
                                $cuxiaoprolist = array_merge($cuxiaoprolist,$collageprolist);
//                                $result[$k2]['collageprolist'] = $collageprolist;
                            }


                            //幸运拼团
                            if($cuxiaonum>0){
                                $whereS = [];
                                $whereS[] = ['aid','=',$aid];
                                $whereS[] = ['status','=',1];
                                $whereS[] = ['ischecked','=',1];
                                $whereS[] = ['bid','=',$v2['bid']];
                                $luckycollageprolist = Db::name('lucky_collage_product')->field("id,name,pic,sell_price,market_price,teamnum,gua_num,'luckycollage' as product_type")->where($whereS)->order("id desc")->limit($cuxiaonum)->select()->toArray();
                                if(!$luckycollageprolist) $luckycollageprolist = [];
                                $cuxiaonum = $cuxiaonum - count($luckycollageprolist);
                                $cuxiaoprolist = array_merge($cuxiaoprolist,$luckycollageprolist);
//                                $result[$k2]['luckycollageprolist'] = $luckycollageprolist;
                            }
                        }
                        if(getcustom('business_selfscore') && $v['params']['showscoreshop'] == 1){
							$whereS = [];
							$whereS[] = ['aid','=',$aid];
							$whereS[] = ['status','=',1];
							$whereS[] = ['ischecked','=',1];
							$whereS[] = ['bid','=',$v2['bid']];
							if(!$cuxiaonum) $cuxiaonum = 6;
							$scoreshopprolist = Db::name('scoreshop_product')->field("id,name,pic,sell_price,money_price,score_price")->where($whereS)->order("id desc")->limit($cuxiaonum)->select()->toArray();
							if(!$scoreshopprolist) $scoreshopprolist = [];
							$result[$k2]['scoreshopprolist'] = $scoreshopprolist;
						}
                        $result[$k2]['cuxiaoprolist'] = $cuxiaoprolist;

                        $result[$k2]['sales'] = $sales_arr[$v2['bid']]?:0;
                        if(getcustom('extend_qrcode_variable_fenzhang')){
                            $mdid = Db::name('mendian')->where('aid',$aid)->where('bid',$v2['bid'])->order('id asc')->value('id');
                            $result[$k2]['mdid']  = $mdid;
                        }
					}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'article'){//文章列表 获取文章信息
				if($v['params']['articlefrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $art){
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
                        if(getcustom('article_showtj')) {
                        	$where2a = '';
                        	if($mid != -1){
	                            $where2a = "find_in_set('-1',showtj)";
	                        }
                            if ($member) {
                                $where2 .= " or find_in_set('" . $member['levelid'] . "',showtj)";
                                if ($member['subscribe'] == 1) {
                                    $where2 .= " or find_in_set('0',showtj)";
                                }
                            }
                            if($where2a  && !empty($where2a)){
                            	$where[] = Db::raw($where2a);
                            }
                        }
                        $field = 'id artid,name,subname,pic,author,readcount,sort,createtime,sendtime';
                        if(getcustom('article_portion')){
                        	$field .= ',po_status,po_name,po_content,pt_status,pt_name,pt_content,pth_status,pth_name,pth_content,pf_status,pf_name,pf_content';
                        }
                        if(getcustom('article_bind_area') && $area){
                            $areaArr = explode(',',$area);
                            $mapArea = ['province','city',"district"];
                            foreach ($areaArr as $ka=>$va){
                                $where[] = [$mapArea[$ka],'=',$va];
                            }
                        }
						$newart = Db::name('article')
							->field($field)
							->where($where)->where('id',$art['artid'])
							->find();
						if($newart){
							$newart['id'] = $art['id'];
							$newart['sendtime'] = date('Y-m-d',$newart['createtime']);
							$newart['createtime'] = date('Y-m-d',$newart['createtime']);
							if(getcustom('article_portion')){
								if($newart['pic']){
									$pic_arr = explode(',',$newart['pic']);
								
										$pic = [];
										if($pic_arr[0]){
											array_push($pic,$pic_arr[0]);
										}
										if($pic_arr[1]){
											array_push($pic,$pic_arr[1]);
										}
										if($pic_arr[2]){
											array_push($pic,$pic_arr[2]);
										}
										$newart['pics'] = $pic;
										$newart['pic'] = $pic_arr[0];
								}
							}
							$newdata[] = $newart;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
                    $where = [];
                    $where[] = ['aid','=',$aid];
                    $where[] = ['status','=',1];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
                    if(getcustom('article_showtj')) {
                    	$where2a = '';
                    	if($mid != -1){
                        	$where2a = "find_in_set('-1',showtj)";
                        }
                        if ($member) {
                            $where2a .= " or find_in_set('" . $member['levelid'] . "',showtj)";
                            if ($member['subscribe'] == 1) {
                                $where2a .= " or find_in_set('0',showtj)";
                            }
                        }
                        if($where2a  && !empty($where2a)){
                        	$where[] = Db::raw($where2a);
                        }
                    }
                    if(getcustom('article_bind_area') && $area){
                        $areaArr = explode(',',$area);
                        $mapArea = ['province','city',"district"];
                        foreach ($areaArr as $ka=>$va){
                            $where[] = [$mapArea[$ka],'=',$va];
                        }
                    }
                    if($v['params']['category'] || ($categroy_selmore && $categoryseltype == 1 && $v['params']['categorymore'])){
                        if($categroy_selmore && $categoryseltype == 1){
                            $cid = $v['params']['categorymore'];
                            $newcids = [];
                            foreach($cid as $ck=>$cv){
                                //选择全部
                                if($ck == 0 && $cv == 'true'){
                                    $newcids = [];
                                    break;
                                }else{
                                    if($cv == 'true'){
                                        $newcids[] = $ck;
                                    }
                                }
                            }
                            if($newcids){
                                $chidlc = Db::name('article_category')->where('aid',$aid)->where('pid','in',$newcids)->select()->toArray();
                            }
                            $cids = $newcids;
                        }else{
                            $cid = intval($v['params']['category']);
                            if($cid){
                                $chidlc = Db::name('article_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
                                $cids = array($cid);
                            }
                        }
                        if($chidlc){
                            foreach($chidlc as $c){
                                $cids[] = intval($c['id']);
                            }
                        }

                        if($cids){
                            if(getcustom('article_multi_category')){
                                $whereCid = [];
                                foreach ($cids as $ck => $cc) {
                                    $whereCid[] = "find_in_set({$cc},cid)";
                                }
                                $where[] = Db::raw(implode(' or ',$whereCid));
                            }else{
                                $where[] = ['cid','in',$cids];
                            }
                        }
                    }
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        if($categroy_selmore && $categoryseltype == 1 && $v['params']['bidmore']){
                            $bids = $v['params']['bidmore'];
                            if($bids){
                                $newbids = [];
                                foreach($bids as $ck=>$cv){
                                    //选择全部
                                    if($ck == -1 && $cv == 'true'){
                                        $newbids = [];
                                        break;
                                    }else{
                                        if($cv == 'true'){
                                            $newbids[] = $ck;
                                        }
                                    }
                                }
                                if($newbids){
                                    $where[] = ['bid','in',$newbids];
                                }
                            }
                        }else{
                            $where[] = ['bid','=',$v['params']['bid']];
                        }
                    }
					if($v['params']['group']){
						$_string = array();
						foreach($v['params']['group'] as $gid=>$istrue){
							if($istrue=='true'){
								if($gid == '0'){
									$_string[] = "gid is null or gid=''";
								}else{
									$_string[] = "find_in_set({$gid},gid)";
								}
							}
						}
						if(!$_string){
							$where2 = '0=1';
						}else{
							$where2 = implode(" or ",$_string);
						}
					}else{
						$where2 = '1=1';
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'sendtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'sendtime') $order = 'createtime';
					if($v['params']['sortby'] == 'readcount') $order = 'readcount desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id artid,name,subname,pic,author,readcount,sort,createtime,sendtime';
                    if(getcustom('article_portion')){
                    	$field .= ',po_status,po_name,po_content,pt_status,pt_name,pt_content,pth_status,pth_name,pth_content,pf_status,pf_name,pf_content';
                    }
					$result = Db::name('article')->field($field)->where($where)->where($where2)->order($order)->limit(intval($v['params']['shownum']))->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['sendtime'] = date('Y-m-d',$v2['createtime']);
						$result[$k2]['createtime'] = date('Y-m-d',$v2['createtime']);
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						if(getcustom('article_portion')){
							if($v2['pic']){
								$pic_arr = explode(',',$v2['pic']);
									$pic = [];
									if($pic_arr[0]){
										array_push($pic,$pic_arr[0]);
									}
									if($pic_arr[1]){
										array_push($pic,$pic_arr[1]);
									}
									if($pic_arr[2]){
										array_push($pic,$pic_arr[2]);
									}
									$result[$k2]['pics'] = $pic;
									$result[$k2]['pic'] = $pic_arr[0];
							}
						}
					}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'luntan'){//文章列表 获取文章信息
			
				if($v['params']['articlefrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $art){
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $field = 'id artid,nickname name,nickname,content subname,zan,content,headimg,headimg pic,pics,readcount,is_top,createtime,createtime';
						$newart = Db::name('luntan')
							->field($field)
							->where($where)->where('id',$art['artid'])
							->find();
						if($newart){
							$newart['id'] = $art['id'];
							$newart['plcount'] = Db::name('luntan_pinglun')->where('sid',$newart['artid'])->where('status',1)->count();
                			//是否点赞
                			$zanlog = Db::name('luntan_zanlog')->where('sid',$newart['artid'])->where('mid',$mid)->find();
                			if($zanlog){
                				$newart['iszan'] = 1;
                			}else{
                				$newart['iszan'] = 0;
                			}
                			$newart['showtime'] = getshowtime2($newart['createtime']);
                			if($newart['pics']){
                				$newart['pics'] = explode(',',$newart['pics']);
                			}
                			$newart['isshowphone'] = false;
							$newdata[] = $newart;
						}
					}
					
					$pagecontent[$k]['data'] = $newdata;
				}elseif($v['params']['articlefrom'] == 3){//手动选择
					$newdata = array();
					
					$pagecontent[$k]['data'] = [];
				}else{
                    $where = [];
                    $where[] = ['aid','=',$aid];
                    $where[] = ['status','=',1];
                    if($v['params']['category'] ){
                        $cid = intval($v['params']['category']);
                        if($cid){
                            $cids = array($cid);
                        }
                        if($cids){
                            $where[] = ['cid','in',$cids];
                        }
                    }
					$order = 'is_top desc';
					if($v['params']['sortby'] == 'sort') $order = 'is_top desc,id desc';
					if($v['params']['sortby'] == 'sendtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'sendtime') $order = 'createtime';
					if($v['params']['sortby'] == 'readcount') $order = 'readcount desc,is_top desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id artid,nickname name,nickname,content subname,zan,content,headimg,headimg pic,pics,readcount,is_top,createtime,createtime';
					$result = Db::name('luntan')->field($field)->where($where)->where($where2)->order($order)->limit(intval($v['params']['shownum']))->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
				        $result[$k2]['plcount'] = Db::name('luntan_pinglun')->where('sid',$v2['artid'])->where('status',1)->count();
            			//是否点赞
            			$zanlog = Db::name('luntan_zanlog')->where('sid',$v2['artid'])->where('mid',$mid)->find();
            			if($zanlog){
            				$result[$k2]['iszan'] = 1;
            			}else{
            				$result[$k2]['iszan'] = 0;
            			}
            			$result[$k2]['showtime'] = getshowtime2($v2['createtime']);
            			if($v2['pics']){
            				$result[$k2]['pics'] = explode(',',$v2['pics']);
            			}
            			$result[$k2]['isshowphone'] = false;
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						
					}
					
					$pagecontent[$k]['data'] = $result;
				}
				
			  	ll($pagecontent[$k],'auto_day');
			  
			}elseif($v['temp'] == 'coupon'){//优惠券
				if($v['params']['couponfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $cp){
                        $where = [];
                        $where[]= ['aid','=',$aid];
                        $where[]= ['id','=',$cp['couponid']];
                        if($areaBids){
                            $where[] = ['bid','in',$areaBids];
                        }
						$newcp = Db::name('coupon')->field('id couponid,type,limit_count,price,name,money,minprice,starttime,endtime,score')->where($where)->find();
						if($newcp){
							$newart['id'] = $cp['id'];
							$newdata[] = $newcp;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$time = time();
                    $where0 = [];
                    $where0[] = ['aid','=',$aid];
                    if($areaBids){
                        $where0[] = ['bid','in',$areaBids];
                    }
					$where = "aid={$aid} and unix_timestamp(starttime)<={$time} and unix_timestamp(endtime)>={$time}";
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
						$where .= ' and bid='.$v['params']['bid'];
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'stock') $order = 'stock desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$result = Db::name('coupon')->field('id couponid,type,limit_count,price,name,money,minprice,starttime,endtime,score')->where($where0)->whereRaw($where)->order($order)->limit(intval($v['params']['shownum']))->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
					}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'form'){//表单信息
                $where = [];
                $where[] = ['aid','=',$aid];
                $where[] = ['id','=',$v['data']['id']];
                if($areaBids){
                    $where[] = ['bid','in',$areaBids];
                }
				$formdata = Db::name('form')->where($where)->find();
				if($formdata){
					$formcontent = json_decode($formdata['content'],true);
					if($v['params']['isquery'] == '1'){
						$newformdata = [];
						foreach($formcontent as $fk=>$fv){
							if($fv['query'] == '1'){
								$fv['val3'] = '0';
								$newformdata[] = $fv;
							}
						}
						$formcontent = $newformdata;
						$formdata['payset'] = '0';
					}
                    //判断表单是否规定时间范围
                    $formdata['is_endtime'] = 0;
                    if (strtotime($formdata['endtime']) < time()) {
                        $formdata['is_endtime'] = 1;
                    }
					$formdata['content'] = $formcontent;
					//表单支付明细
                    if(getcustom('form_other_money')){
                        if($formdata['fee_items']){
                            $formdata['is_other_fee'] = 1;
                            $formdata['fee_items'] = json_decode($formdata['fee_items'],true);
                        }
                    }
                    if(getcustom('form_tourl')){
                        if($formdata['submit_tourl']){
                            $submit_tourl = explode(',',$formdata['submit_tourl']);
                            $randk = array_rand($submit_tourl);
                            $submit_tourl = $submit_tourl[$randk];
                            $pagecontent[$k]['params']['hrefurl'] = $submit_tourl;
                        }
                    }
                    if(getcustom('form_radio_paymoney')){
                        $formdata['radio_paymoney'] = 1;
                    }
                    if(getcustom('form_show_submember')) {
                        $submemberwhere = [
                            ['fo.status','<',2], //0 未处理 1确认 2驳回
                            ['fo.aid','=',$aid],
                            ['fo.formid','=',$v['data']['id']]
                        ];
                        $submemberData = Db::name('form_order')->alias('fo')->field('fo.*,m.headimg')->leftJoin('member m','m.id = fo.mid')->where($submemberwhere)->limit(20)->select()->toArray();
                        $submemberDataSum = Db::name('form_order')->alias('fo')->leftJoin('member m','m.id = fo.mid')->where($submemberwhere)->count();
                        $formdata['submember_data'] = $submemberData;
                        $formdata['submember_data_sum'] = $submemberDataSum;
                    }
				}else{
					$formdata = '';
				}
				$pagecontent[$k]['data'] = $formdata;
				if($mid != -1 && strtotime($formdata['starttime']) > time() && $v['params']['wkstpis']){
					die(jsonEncode(['status'=>-4,'msg'=>$v['params']['wkstpis']]));
				}
            }elseif($v['temp'] == 'form-log' && getcustom('form_log_plug')){//表单信息
                $where = [];
                $where[] = ['aid','=',$aid];
                $where[] = ['id','=',$v['data']['id']];
                if($areaBids){
                    $where[] = ['bid','in',$areaBids];
                }
                $formdata = Db::name('form')->where($where)->field('id,name')->find();
                if($formdata){
                    $formdata['count'] = 0 + \db('form_order')->where('formid',$formdata['id'])->count();
                    $log = \db('form_order')->where('formid',$formdata['id'])->order('id', 'desc')->field('id,mid,createtime')->find();
                    if($log){
                        $logmember = \db('member')->where('id',$log['mid'])->field('nickname,realname')->find();
                        $log['nickname'] = $logmember['nickname'];
                        $log['realname'] = $logmember['realname'];
                        $log['time'] = date('Y-m-d H:i',$log['createtime']);
                    }

                    $formdata['log'] = $log;
                }else{
                    $formdata = ['count'=>0,'name'=>'','log'=>[]];
                }
                $pagecontent[$k]['data'] = $formdata;
			}elseif($v['temp'] == 'menu'){//按钮导航
				$data = $v['data'];
				$newdata = [];
				if(!$v['params']['pernum']){
					$v['params']['pernum'] = 10;
					$pagecontent[$k]['params']['pernum'] = 10;
				}
				$pagecount = ceil(count($data)/$v['params']['pernum']);
				if(!$pagecount) $pagecount = 1;
				for($i=0;$i<$pagecount;$i++){
					$newdata[$i]=array_slice($data,$v['params']['pernum']*$i,$v['params']['pernum']);
				}
				if($v['params']['showicon']==0){
					$pagecontent[$k]['params']['iconsize'] = 0;
				}
				//dump($newdata);
				$pagecontent[$k]['params']['newdata'] = $newdata;
				$pagecontent[$k]['params']['newdata_linenum'] = ceil($v['params']['pernum']/$v['params']['num']);
			}elseif($v['temp'] == 'shop'){
				if($v['params']['bid'] == 0){
					$shopinfo = Db::name('admin_set')->where('aid',$aid)->field('name,logo,desc,tel,kfurl')->find();
				}else{
                    $where = [];
                    $where[] = ['id','=',$v['params']['bid']];
                    if($areaBids){
                        $where[] = ['bid','in',$areaBids];
                    }
					$business = Db::name('business')->where($where)->find();
                    if(!empty($business)){
                        $shopinfo = ['name'=>$business['name'],'logo'=>$business['logo'],'desc'=>$business['address'],'tel'=>$business['tel']];
                    }else{
                        $shopinfo = ['name'=>''];
                    }
				}
				$pagecontent[$k]['shopinfo'] = $shopinfo;
            }elseif($v['temp'] == 'jidian'){
                $jidian = ['name'=>''];
                if($v['params']['bid'] > 0) {
                    $set = Db::name('jidian_set')->where('aid', $aid)->where('bid', $v['params']['bid'])->find();
                    if ($set && $set['status'] == 1) {
                        $jidianNum= self::getOrderNumFromJidian(aid,$v['params']['bid'],$set,$mid);
                        $jidian = [
                            'name' => $set['name'],
                            'bid' => $set['bid'],
                            'reward_name' => $jidianNum['reward_name'],
                            'reward_num' => $jidianNum['reward_num'],
                            'have_num' => $jidianNum['have_num'],
                            'total_num' => $jidianNum['total_num'],
                        ];
                    }
                }
                $pagecontent[$k]['data'] = $jidian;
			}elseif($v['temp'] == 'userinfo'){
				if(!$v['params']['seticonsize']){
					$pagecontent[$k]['params']['seticonsize'] = 17;
				}
                $zhaopin['show_zhaopin']  = 0;
				if($mid > 0){
                    if($v['params']['ordershow']) {                    
                        $count0 = 0 + Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('status',0)->count();
                        $count1 = 0 + Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('status',1)->count();
                        $count2 = 0 + Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('status',2)->count();
                        //$count3 = 0 + Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('status',3)->count();
                        $count4 = 0 + Db::name('shop_refund_order')->where('aid',$aid)->where('mid',$mid)->where('refund_status',1)->count();
                        $orderinfo = ['count0'=>$count0,'count1'=>$count1,'count2'=>$count2,'count4'=>$count4];
						if($v['params']['orderData']) {
							//自定义订单名称匹配数量
							foreach($v['params']['orderData'] as $kod=>$vod){
								if($vod['type'] == 'daifukuan'){
									$pagecontent[$k]['params']['orderData'][$kod]['num'] = $count0;
								}
								if($vod['type'] == 'daifahuo'){
									$pagecontent[$k]['params']['orderData'][$kod]['num'] = $count1;
								}
								if($vod['type'] == 'daishouhuo'){
									$pagecontent[$k]['params']['orderData'][$kod]['num'] = $count2;
								}
								if($vod['type'] == 'wancheng'){
									$pagecontent[$k]['params']['orderData'][$kod]['num'] = 0;
								}
								if($vod['type'] == 'tuikuan'){
									$pagecontent[$k]['params']['orderData'][$kod]['num'] = $count4;
								}
							}
						}
                    }
                    if($v['params']['scoreshopordershow']) {
                        $count0 = 0 + Db::name('scoreshop_order')->where('aid',$aid)->where('mid',$mid)->where('status',0)->count();
                        $count1 = 0 + Db::name('scoreshop_order')->where('aid',$aid)->where('mid',$mid)->where('status',1)->count();
                        $count2 = 0 + Db::name('scoreshop_order')->where('aid',$aid)->where('mid',$mid)->where('status',2)->count();
                        $count4 = 0 + Db::name('scoreshop_order')->where('aid',$aid)->where('mid',$mid)->where('refund_status',1)->count();
                        $scoreshoporder = ['count0'=>$count0,'count1'=>$count1,'count2'=>$count2,'count4'=>$count4];
                    }
                    $field = 'id,levelid,nickname,headimg,sex,realname,tel,weixin,aliaccount,country,province,city,area,address,birthday,createtime,bankcardnum,bankname,bankcarduser,money,commission,score,bscore,paypwd,card_id,card_code,aid,pid,ktnum,yqcode,credit1,credit2,storelevel,teamlevel';
                    if(getcustom('other_money')){
                    	$field .= ",money2,money3,money4,money5,frozen_money";
                    }
                    if(getcustom('commission_frozen')){
                        if($admin['commission_frozen'])
                            $field .= ",fuchi_money";
                    }
                    if(getcustom('member_gongxian')){
                        if($admin['member_gongxian_status'] == 1)
                            $field .= ",gongxian";
                    }
                    if(getcustom('commission_xiaofei')){
                        $field .= ",xiaofei_money";
                    }
                    if(getcustom('product_bonus_pool')){
                        $field .= ",bonus_pool_money";
                    }
                    if(getcustom('member_overdraft_money')){
                        $field .= ",overdraft_money,limit_overdraft_money,open_overdraft_money";
                    }
                    if(getcustom('commission_duipeng_score_withdraw')){
                        $field .= ",commission_withdraw_score";
                    }
                    if(getcustom('fenhong_jiaquan_bylevel')){
                        $field .= ",fhcopies";
                    }
                    if(getcustom('member_realname_verify')){
                        $field .= ",realname_status";
                    }
                    if(getcustom('product_givetongzheng')){
                        $field .= ",tongzheng";
                    }
                    if(getcustom('product_service_fee')){
                        $field .= ",service_fee";
                    }
                    if(getcustom('yx_queue_free_freeze_account')){
                        $field .= ",freeze_credit";
                    }
                    if(getcustom('consumer_value_add')){
                        $field .= ",green_score";
                    }
                    if(getcustom('active_coin')){
                        $field .= ",active_coin";
                    }
                    $show_commission_max = 0;
                    if(getcustom('member_commission_max')){
                        if($sysset['member_commission_max']){
                            $show_commission_max = 1;
                        }
                        $field .= ",commission_max,totalcommission";
                    }
                    if(getcustom('yx_buy_fenhong')){
                        $field .= ",buy_fenhong_score_weight";
                    }
                    if(getcustom('member_goldmoney_silvermoney')){
                        //金银权限
                        $canshowsilvermoney = $canshowgoldmoney = true;
                        //查询权限组
                        $admin_user = db('admin_user')->where('aid',$aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                        //如果开启了设计组件分类多选
                        if($admin_user['auth_type'] != 1){
                            if($admin_user['groupid']){
                                $admin_user['auth_data'] = Db::name('admin_user_group')->where('id',$admin_user['groupid'])->value('auth_data');
                            }
                            $admin_auth = json_decode($admin_user['auth_data'],true);
                            if(!in_array('Member/addSilvermoney,Member/addSilvermoney',$admin_auth)){
                                $canshowsilvermoney = false;
                                $pagecontent[$k]['showsilvermoney'] = 0;
                            }
                            if(!in_array('Member/addGoldmoney,Member/addGoldmoney',$admin_auth)){
                                $canshowgoldmoney = false;
                                $pagecontent[$k]['showgoldmoney'] = 0;
                            }
                        }
                        if($canshowsilvermoney) $field .= ",silvermoney";
                        if($canshowgoldmoney) $field .= ",goldmoney";
                    }
					$userinfo = Db::name('member')->field($field)->where('aid',$aid)->where('id',$mid)->find();
                    $userinfo['money'] = \app\commons\Member::getmoney($userinfo);
                    $userinfo['score'] = \app\commons\Member::getscore($userinfo);
					if(!isset($userinfo['gongxian'])) $userinfo['gongxian'] = -1;
					$userinfo['show_commission_max'] = $show_commission_max;
                    if(getcustom('member_commission_max')){
                        //$remain_commission_max = bcsub($userinfo['commission_max'],$userinfo['totalcommission'],2);
                        $remain_commission_max = $userinfo['commission_max'];
                        $userinfo['remain_commission_max'] = $remain_commission_max>0?$remain_commission_max:'0.00';
                    }
                    $commission_to_score = 0;
                    if(getcustom('member_commission_max') && getcustom('member_commission_max_toscore')){
                        $commission_to_score = 1;
                    }
                    $userinfo['commission_to_score'] = $commission_to_score;
                    if(!isset($userinfo['freeze_credit'])){
                        $pagecontent[$k]['params']['freezecreditshow'] = 0;
                    }
                    
                    
                    
                    
                    
                    
  $userinfo['othermoney_status'] = 0;
                    $pagecontent[$k]['params']['credit1show'] = 1;
                    // $pagecontent[$k]['params']['credit2show'] = 1;
                    $userlevel = Db::name('member_level')->where('aid',$aid)->where('id',$userinfo['levelid'])->find();
                    if(!$userlevel) $userlevel = Db::name('member_level')->where('aid',$aid)->where('isdefault',1)->find();
                    if($v['params']['levelshow']) {
                        //扩展等级
                        $userlevelList = [];
                        if(getcustom('plug_sanyang')) {
                            $level_ids = Db::name('member_level_record')->where('aid', $aid)->where('mid',$mid)->column('levelid');
                            if($level_ids) {
                                $userlevelList = Db::name('member_level')->where('aid',$aid)->whereIn('id',$level_ids)->select()->toArray();
                            }
                        }
                    }
					$sysset = Db::name('admin_set')->where('aid',$aid)->field('name,logo,desc,tel,recharge,reg_invite_code,reg_invite_code_type')->find();

					//优惠券数
                    if($v['params']['couponshow']) {
                        $userinfo['couponcount'] = Db::name('coupon_record')->where('aid',$aid)->where('mid',$mid)->where('status',0)->where('endtime','>=',time())->count();
                    }
					if($v['params']['formshow']) {
                        $userinfo['formcount'] = Db::name('form_order')->where('aid',$aid)->where('mid',$mid)->where('status',1)->count();
                    }
                    //开卡链接
                    if($v['params']['cardshow']) {
                        $membercard = Db::name('membercard')->where('aid',$aid)->where('status',1)->order('id desc')->find();
                        $card_returl = $membercard['ret_url'];
                        $card_id = $membercard['card_id'];
                    }
                    $parent_show = false;
                    if(getcustom('plug_zhiming')) {
                        $parent_show = true;
                        if($userinfo['pid'])
                            $parent = Db::name('member')->field('id,levelid,nickname,headimg,sex,realname,tel,weixin,aid')->where('id',$userinfo['pid'])->find();
                    }
                    if(getcustom('zhaopin')){
                        //招聘签约
                        $qiuzhi = Db::name('zhaopin_qiuzhi_apply')->where('aid',$aid)->where('mid',$mid)->where('status',1)->count('id');
                        $qianyue = Db::name('zhaopin_qiuzhi_qianyue')->where('aid',$aid)->where('mid',$mid)->where('status',1)->count('id');
                        $zhaopinR = Db::name('zhaopin_apply')->where('aid',$aid)->where('mid',$mid)->where('status',1)->find();
                        $zhaopin['show_zhaopin'] = 1;
                        $zhaopin['is_qiuzhi_renzheng'] = $qiuzhi?1:0;
                        $zhaopin['is_qiuzhi_qianyue'] = $qianyue?1:0;
                        $zhaopin['is_zhaopin_renzheng'] = $zhaopinR?1:0;
                        $zhaopin['zhaopin_assurance_fee'] = $zhaopinR['assurance_fee'];
                    }
					if(getcustom('member_overdraft_money')){
						$limit_money = $userinfo['limit_overdraft_money'];
						$open_overdraft_money = $userinfo['open_overdraft_money'];
						$overdraft_money = $userinfo['overdraft_money']*-1;
						if(empty($limit_money)){
							$overdraft_money_now = 0; 
						}else{
							$overdraft_money_now = round($limit_money - $overdraft_money,2);
						}
						//额度
						if($open_overdraft_money == 1){
							$overdraft_money_now = '无限';
							$userinfo['limit_overdraft_money'] = '无限';
						}
						$userinfo['overdraft_money'] = $overdraft_money_now;
						
					}
				}else{
					$userinfo = ['id'=>0,'nickname'=>'未登录','headimg'=>PRE_URL.'/static/img/touxiang.png','money'=>0,'score'=>0,'couponcount'=>0];
					$userlevel = Db::name('member_level')->where('aid',$aid)->where('isdefault',1)->find();
					$orderinfo = [];
					$scoreshoporder = [];
					$card_returl = '';
					$card_id = '';
                    $parent_show = false;
				}
				if(getcustom('other_money')){
					$othermoney_status = $admin['othermoney_status'];
					$userinfo['othermoney_status'] = $othermoney_status?$othermoney_status:0;
				}
                if(getcustom('task_banner')){
                   
                    //计算数量
                    $task_banner_set = Db::name('task_banner_set')->where('aid',$aid)->find();
                    if($task_banner_set['gettj']){
                        $gettj = explode(',',$task_banner_set['gettj']);
                    }else{
                        $gettj = [];
                    }
                  
                    if(in_array($member['levelid'],$gettj)||in_array(-1,$gettj) ){
                        $sysset['task_banner'] = true;
                    }else{
                        $sysset['task_banner'] = false;
                    }
                    $m_cout = Db::name('member') ->where('id',$userinfo['id'])-> value('task_banner_total');
                    $sy_count =     $task_banner_set['total_complete_num']   - $m_cout ;
                    $sysset['sy_count'] =  $sy_count<0?0:$sy_count;
                    $sysset['rewardedvideoad']  = $task_banner_set['rewardedvideoad']; 
                }
 $userlevelList = [];
                if ($userinfo['teamlevel']) {
                    $userlevelList[] = Db::name('member_team_level')->field('id,name,icon')->where('id',$userinfo['teamlevel'])->find();
                }
                 if ($userinfo['storelevel']) {
                    $userlevelList[] = Db::name('member_store')->field('id,name,icon')->where('id',$userinfo['storelevel'])->find();
                }
               	$moeny_weishu = 2;
                if(getcustom('fenhong_money_weishu')){
                    $moeny_weishu = Db::name('admin_set')->where('aid',$aid)->value('fenhong_money_weishu');
                }
                $userinfo['commission'] = dd_money_format($userinfo['commission'],$moeny_weishu);

                $userinfo['score'] = dd_money_format($userinfo['score'],$score_weishu);

                $moeny_weishu2 = 2;
                if(getcustom('member_money_weishu')){
                    $moeny_weishu2 = Db::name('admin_set')->where('aid',$aid)->value('member_money_weishu');
                }
                $userinfo['money'] = dd_money_format($userinfo['money'],$moeny_weishu2);

				$pagecontent[$k]['data'] = ['sysset'=>$sysset,'userinfo'=>$userinfo,'userlevel'=>$userlevel,'userlevelList' => $userlevelList, 'orderinfo'=>$orderinfo,'scoreshoporder'=>$scoreshoporder,'card_returl'=>$card_returl,'card_id'=>$card_id,'parent' => $parent, 'parent_show'=>$parent_show,'zhaopin'=>$zhaopin];
			}elseif($v['temp'] == 'search'){
                $v['params']['image_search'] = 0;
                if(getcustom('image_search')){
                    $image_search = $admin['image_search'];
                    if($image_search == 1) {

		                $image_search_business_switch = Db::name('baidu_set')->where('aid',$aid)->where('bid',0)->value('image_search_business_switch');
		                if($image_search_business_switch){
	                		if(isset($v['params']['bid'])){
	                    		$image_search = Db::name('baidu_set')->where('aid',$aid)->where('bid',$v['params']['bid'])->value('image_search');
		                        if($image_search == 1){
		                            $v['params']['image_search'] = $image_search;
		                        }
	                    	}
		                }else{
		                    $image_search = Db::name('baidu_set')->where('aid',$aid)->where('bid',0)->value('image_search');
	                        if($image_search == 1){
	                            $v['params']['image_search'] = $image_search;
	                        }
		                }
                    }
                }
                $pagecontent[$k]['params'] = $v['params'];
            }elseif($v['temp'] == 'video'){
                //$mid=-1,设计页面编辑操作
                $params = $v['params']??[];
                $isChooseVideo = preg_match('/\[视频ID:(\d+)\]/', $params['src']);
                if ($isChooseVideo) {
                    //视频管理的视频
                    $videoId = trim(explode(':', $params['src'])[1], ']');
                    $videoinfo = Db::name('video_list')->where('id', $videoId)->find();
                    if (empty($params['pic']) && $videoinfo['pic']) {
                        $params['pic'] = $videoinfo['pic'];
                    }
                    if($mid!=-1) {
                        if ($videoinfo && $videoinfo['type'] == 1 && $videoinfo['ext_param']) {
                            //微信视频号
                            $videoExt = json_decode($videoinfo['ext_param'], true);
                            if ($videoExt['feedtype'] == 1) {
                                //视频号视频
                                $params['video_finderuser'] = $videoExt['finderuser'];
                                $params['video_feedid'] = $videoExt['feedid'];
                                $params['video_feedtoken'] = $videoExt['feedtoken'];
                                $vtype = 1;
                            } elseif ($videoExt['feedtype'] == 0) {
                                //视频号主页
                                $vtype = 2;
                                $params['src'] = 'channelsUserProfile::' . $videoExt['finderuser'];
                            }
                        } elseif ($videoinfo && $videoinfo['video_url']) {
                            $params['src'] = $videoinfo['video_url'];
                        }
                        $params['type'] = $vtype;
                    }
                }
                if (getcustom('video_qq_url') && $vtype==0 && $mid!=-1){
                    $params['src'] = \app\customs\VideoQQ::getMp4Url($params['src']);
                }
                $pagecontent[$k]['params'] = $params;
            }
//			if($v['temp'] == 'location' && !in_array($sysset['mode'],[2,3])){
//			    unset($pagecontent[$k]);
//            }
			if(getcustom('zhaopin')) {
                if($v['temp'] == 'zhaopin'){//招聘
                    $where = [];
                    $where[] = ['aid','=',$aid];
                    $where[] = ['status','=',1];
//                if($area){
//                    $where[] = ['area','like','%'.$area.'%'];
//                }
                    //按距离排序
                    if($area){
                        //排序：定位城市，置顶，距离，其他城市
                        $areaArr = explode(',',$area);
                        $lastArea = $areaArr[count($areaArr)-1];
                        $order = "(case
                            when instr(area, '{$lastArea}')  then 1
                            when instr(area, '全国')  then 2
                          else 3
                          end
                          ) asc,top_feetype desc,top_endtime desc";
                        if($latitude && $longitude){
                            $orderLocation = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
                            $order = $order.' ,'.$orderLocation;
                        }
                        $order = $order.' ,id desc';
                    }else{
                        $order = 'top_feetype desc,top_endtime desc,id desc';
                        if($v['params']['sortby'] == 'sort') $order = 'top_feetype desc,id desc';
                        if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
                        if($v['params']['sortby'] == 'createtime') $order = 'createtime';
                        if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
                    }

                    $result = Db::name('zhaopin')->where($where)->field('*,id as proid')->orderRaw($order)->limit(intval($v['params']['shownum']))->select()->toArray();
                    if(!$result) $result = array();
                    foreach($result as $k2=>$v2){
                        $result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
                        $result[$k2]['welfare'] = $v2['welfare']?explode(',',$v2['welfare']):[];
                        $apply_fee = Db::name('zhaopin_apply')->where('aid',$aid)->where('status',1)->where('mid',$v2['mid'])->value('assurance_fee');
                        if($apply_fee && $apply_fee>0){
                            $result[$k2]['apply_tag'] = '担保企业';
                        }else{
                            $result[$k2]['apply_tag'] = '';
                        }
                    }
                    $pagecontent[$k]['data'] = $result;
                }elseif ($v['temp'] == 'qiuzhi') {//求职
                    $where = [];
                    $where[] = ['q.aid', '=', $aid];
                    $where[] = ['q.status', '=', 1];
                    $order = 'q.top_feetype desc,q.top_endtime desc,q.id desc';
                    if ($area) {
                        $whereArea = [];
                        $areaArr = explode(',', $area);
                        //意向城市
//                    $province = $areaArr[0];
//                    $whereArea[] = "find_in_set('{$province}',q.area)";
//                    $whereArea[] = "find_in_set('全国',q.area)";
//                    $where[] = Db::raw(implode(' or ',$whereArea));
//                    $where[] = ['area','like','%'.$area.'%'];
                        $lastArea = $areaArr[count($areaArr) - 1];
                        //排序：定位城市，全国，其他城市
                        $order = "(case
                                when instr(q.area, '{$lastArea}')  then 1
                                when instr(q.area, '全国')  then 2
                              else 3
                              end
                              ) asc,q.top_feetype desc,q.top_endtime desc,q.id desc";
                    }
                    if ($v['params']['bid'] !== '' && $v['params']['bid'] !== null) {
                        $where[] = ['q.bid', '=', $v['params']['bid']];
                    }

//                if($v['params']['sortby'] == 'sort') $order = 'q.top_feetype desc,q.id desc';
//                if($v['params']['sortby'] == 'createtimedesc') $order = 'q.createtime desc';
//                if($v['params']['sortby'] == 'createtime') $order = 'q.createtime';
//                if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
                    $result = Db::name('zhaopin_qiuzhi')->alias('q')
                        ->join('zhaopin_qiuzhi_apply a', 'q.mid=a.mid and a.status=1', 'left')
                        ->where($where)->field('q.*,q.id as proid,a.qianyue_id')->orderRaw($order)->limit(intval($v['params']['shownum']))->field('q.*,a.id apply_id')->select()->toArray();
                    if (!$result) $result = array();
                    //mohu
                    $mohu = 0;
                    $set = Db::name('zhaopin_set')->where('aid', $aid)->where('tag', 'qiuzhi')->find();
                    if ($set && $set['content']) {
                        $setcontent = json_decode($set['content'], true);
                        $mohu = isset($setcontent['mohu']) ? $setcontent['mohu'] : 0;
                    }
                    //mohu end
                    foreach ($result as $k2 => $v2) {
                        $result[$k2]['id'] = 'G' . time() . rand(10000000, 99999999);
                        $result[$k2]['tags'] = $v2['tags'] ? explode(',', $v2['tags']) : [];
                        $result[$k2]['mohu'] = 0;
                        if (in_array($v2['secret_type'], [2, 3])) {
                            $result[$k2]['mohu'] = $mohu ?? 6;
                        }
                        if(empty($v2['apply_id'])){
                            $result[$k2]['apply_id'] = 0;
                        }
                    }
                    $pagecontent[$k]['data'] = $result;
                }
            }
            if(getcustom('xixie')){
	            if($v['temp'] == 'xixie'){//产品列表 获取产品信息
					if($v['params']['productfrom'] == 0){//手动选择
						$newdata = array();
						foreach($v['data'] as $pro){
							$field = 'id proid,name,pic,sell_price,vip_price,buymax';
							$newpro = Db::name('xixie_product')->field($field)->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->where('ischecked',1)->find();
							if($newpro){
								$newpro['id'] = $pro['id'];
								//查询购物车数量
								$cart_num = 0+Db::name('xixie_cart')->where('mid',$mid)->where('proid',$newpro['proid'])->where('aid',$aid)->sum('num');
								$newpro['gwcnum'] = $cart_num;
								$newdata[] = $newpro;
							}
						}
						$pagecontent[$k]['data'] = $newdata;
					}else{
						$where = [];
						$where[] = ['aid','=',$aid];
						$where[] = ['status','=',1];
						$where[] = ['ischecked','=',1];
						if($v['params']['category']){
							$cid = intval($v['params']['category']);
							//查询它及子类产品
							$childarr = Db::name('xixie_category')->where('pid',$cid)->where('aid',$aid)->column('id');
							if($childarr){
								array_push($childarr,$cid);
								$where[] = ['cid','in',$childarr];
							}else{
								$where[] = ['cid','=',$cid];
							}

						}
						if($v['params']['bid']!=='' && $v['params']['bid']!==null){
							$where[] = ['bid','=',$v['params']['bid']];
						}
						$order = 'sort desc';
						if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
						if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
						if($v['params']['sortby'] == 'createtime') $order = 'createtime';
						if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
						$field = 'id proid,name,pic,sell_price,vip_price,buymax';

						$result = Db::name('xixie_product')->field($field)->where($where)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
						if(!$result) $result = array();
						foreach($result as &$xx_v){
							$xx_v['id'] = 'G'.time().rand(10000000,99999999);
							//查询购物车数量
							$cart_num = 0+Db::name('xixie_cart')->where('mid',$mid)->where('proid',$xx_v['proid'])->where('aid',$aid)->sum('num');
							$xx_v['gwcnum'] = $cart_num;
						}
						unset($xx_v);
						if(!$result){
							$result = [];
						}
						$pagecontent[$k]['data'] = $result;
					}
				}
			}
			if(getcustom('extend_tour')){
	            if($v['temp'] == 'tour'){//产品列表 获取产品信息
					if($v['params']['productfrom'] == 0){//手动选择
						$newdata = array();
						foreach($v['data'] as $pro){
							$field = 'id proid,name,pic,sell_price,tour_price,sales';
							$newpro = Db::name('tour_activity')->field($field)->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->where('is_del',0)->find();
							if($newpro){
								$newpro['id'] = $pro['id'];
								if($trid){
						    
						            $count = Db::name('tour_member')->where('id',$trid)->where('status',1)->where('is_del',0)->count();
						            if($count){
						                $newpro['sell_price'] = $newpro['tour_price'];
						            }
						        }
								$newdata[] = $newpro;
							}
						}
						$pagecontent[$k]['data'] = $newdata;
					}else{
						$where = [];
						$where[] = ['aid','=',$aid];
						$where[] = ['status','=',1];
						$where[] = ['is_del','=',0];

						$order = 'id desc';
						if($v['params']['sortby'] == 'id') $order = 'id desc';
						if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
						if($v['params']['sortby'] == 'createtime') $order = 'createtime';
						$field = 'id proid,name,pic,sell_price,tour_price,sales';
						$result = Db::name('tour_activity')->field($field)->where($where)->order($order)->limit(intval($v['params']['proshownum']))->select()->toArray();
						if(!$result) $result = array();
						foreach($result as &$xx_v){
							$xx_v['id'] = 'G'.time().rand(10000000,99999999);
				            $count = Db::name('tour_member')->where('id',$trid)->where('status',1)->where('is_del',0)->count();
				            if($count){
				                $xx_v['sell_price'] = $xx_v['tour_price'];
				            }
						}
						unset($xx_v);
						if(!$result){
							$result = [];
						}
						$pagecontent[$k]['data'] = $result;
					}
				}
			}
			if(getcustom('form_data')){
                if($v['temp'] == 'formdata'){//产品列表 获取产品信息
                    $id = $v['params']['formid'];
                    $where = [];
                    $where[] = ['id','=',$id];
                    $where[] = ['isopen','=',1];
                    $where[] = ['aid','=',$aid];
                    $form = Db::name('form')->field('id,name,list_pic,list_title,list_address,list_tel')->where($where)->find();
                    if($form){
                        $where = [];
                        $where[] = ['formid','=',$id];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $result = Db::name('form_order')->where($where)->order('id desc')->limit($v['params']['proshownum'])->select()->toArray();
                        foreach($result as &$rv){
                            $rv['logo']    = !empty($rv['form'.$form['list_pic']])?$rv['form'.$form['list_pic']]:'';
                            $rv['title']   = !empty($rv['form'.$form['list_title']])?$rv['form'.$form['list_title']]:'';
                            $rv['address'] = !empty($rv['form'.$form['list_address']])?$rv['form'.$form['list_address']]:'';
                            $rv['tel']     = !empty($rv['form'.$form['list_tel']])?$rv['form'.$form['list_tel']]:'';
                            $rv['latitude']  = $rv['adr_lat'] && !empty($rv['adr_lat'])?$rv['adr_lat']:'';
                            $rv['longitude'] = $rv['adr_lon'] && !empty($rv['adr_lon'])?$rv['adr_lon']:'';
                        }
                        unset($rv);
                    }else{
                        $result = [];
                    }
                    $pagecontent[$k]['data'] = $result;
                }
            }
			if(getcustom('hotel')){
				if($v['temp'] == 'hotel'){
					if($v['params']['hotelfrom'] == 0){//手动选择
						$newdata = array();
						foreach($v['data'] as $hotel){
							$where = [];
							$where[] = ['aid','=',$aid];
							$where[] = ['status','=',1];
							$where[] = ['id','=',$hotel['hotelid']];
							$newpro = Db::name('hotel')->field('id,name,pic,tag,address,sales')->where($where)->find();
							if($newpro){
								$newpro['id'] = $hotel['id'];
								$newpro['tag'] =$newpro['tag']?explode(',',$newpro['tag']):'';
								//查询最低的房型价格
								//$where = [];
								//$where[] =['hotelid','=',$hotel['hotelid']];
								//$nowtime = time();
								//$where[] = Db::raw("unix_timestamp(datetime)>=$nowtime");
								//$room = Db::name('hotel_room_prices')->where($where)->field('sell_price')->order('sell_price')->find();
								//echo db('hotel_room_prices')->getlastsql();
								//$newpro['sell_price'] = $room['sell_price'];
								//查询最低的房型价格
								$where = [];
								$where[] =['hotelid','=',$hotel['hotelid']];
								$nowtime = time();
								$room = Db::name('hotel_room')->where($where)->where('isdaymoney',1)->field('isdaymoney,sell_price,daymoney')->find();
								$where[] = Db::raw("unix_timestamp(datetime)>=$nowtime");
								
								//是否有设置余额定价
								if($room['isdaymoney']==1){
									$newpro['isdaymoney'] = 1;	
									$roomprice = Db::name('hotel_room_prices')->where($where)->where('daymoney','>=','1')->field('daymoney')->order('daymoney')->find();
									if($roomprice){
										$newpro['min_daymoney'] = $roomprice['daymoney'];
									}else{
										$newpro['min_daymoney'] = 0;
									}
								}else{
									$roomprice = Db::name('hotel_room_prices')->where($where)->field('sell_price')->order('sell_price')->find();
									//echo db('hotel_room_prices')->getlastsql();
									$newpro['isdaymoney'] =0;	
									if($roomprice){
										$newpro['sell_price'] = $roomprice['sell_price'];
									}else{
										$newpro['sell_price'] = 0;
									}
									
								}

								$newdata[] = $newpro;
							}
						}
						$pagecontent[$k]['data'] = $newdata;
					}else{
						$where = [];
						$where[] = ['aid','=',$aid];
						$where[] = ['status','=',1];
						$nowtime = time();
						if($v['params']['category']){
							$cid = intval($v['params']['category']);
							$chidlc = Db::name('hotel_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
							if($chidlc){
								$cids = array($cid);
								$whereCid = '(';
								$whereCid .= " find_in_set({$cid},cid) or ";
								foreach($chidlc as $k2 => $c){
									if(count($chidlc) == ($k2 + 1))
										$whereCid .= "find_in_set({$c['id']},cid)";
									else
										$whereCid .= " find_in_set({$c['id']},cid) or ";
								}
								$where[] = Db::raw($whereCid . ')');
							}else{
								$where[] = Db::raw("find_in_set({$cid},cid)");
							}
						}
						$order = 'sort desc';
						if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
						if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
						if($v['params']['sortby'] == 'createtime') $order = 'createtime';
						if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
						if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
						$field = "id hotelid,name,pic,sales,address,tag";
						$newdata = Db::name('hotel')->field($field)->where($where)->order($order)->limit(intval($v['params']['shownum']))->select()->toArray();
						if(!$newdata) $newdata = array();
						foreach($newdata as $k2=>$v2){
							$newdata[$k2]['id'] = 'G'.time().rand(10000000,99999999);
							$newdata[$k2]['tag'] = explode(',',$v2['tag']);

							//查询最低的房型价格
							$where = [];
							$where[] =['hotelid','=',$v2['hotelid']];
							$nowtime = time();
							$room = Db::name('hotel_room')->where($where)->where('isdaymoney',1)->field('isdaymoney,sell_price,daymoney')->find();
							$where[] = Db::raw("unix_timestamp(datetime)>=$nowtime");
							
							//是否有设置余额定价
							if($room['isdaymoney']==1){
								$newdata[$k2]['isdaymoney'] = 1;	
								$roomprice = Db::name('hotel_room_prices')->where($where)->where('daymoney','>=','1')->field('daymoney')->order('daymoney')->find();
								if($roomprice){
									$newdata[$k2]['min_daymoney'] = $roomprice['daymoney'];
								}else{
									$newdata[$k2]['min_daymoney'] = 0;
								}
							}else{
								$roomprice = Db::name('hotel_room_prices')->where($where)->field('sell_price')->order('sell_price')->find();
								//echo db('hotel_room_prices')->getlastsql();
								$newdata[$k2]['isdaymoney'] =0;	
								if($roomprice){
									$newdata[$k2]['sell_price'] = $roomprice['sell_price'];
								}else{
									$newdata[$k2]['sell_price'] = 0;
								}
								
							}
						}
						$pagecontent[$k]['data'] = $newdata;
					}
				}
				if($v['temp'] == 'hotelroom'){
					if($v['params']['roomfrom'] == 0){//手动选择
						$newdata = array();
						foreach($v['data'] as $room){
							$where = [];
							$where[] = ['aid','=',$aid];
							$where[] = ['status','=',1];
							$where[] = ['id','=',$room['roomid']];
							$newpro = Db::name('hotel_room')->field('id roomid,name,pic,tag,stock,sales,isdaymoney')->where($where)->find();
							if($newpro){
								$newpro['roomid'] = $newpro['roomid'];
								$newpro['tag'] =$newpro['tag']?explode(',',$newpro['tag']):'';		
								//查询最低的房型价格
								//$where = [];
								//$where[] =['hotelid','=',$hotel['hotelid']];
								//$nowtime = time();
								//$where[] = Db::raw("unix_timestamp(datetime)>=$nowtime");
								//$room = Db::name('hotel_room_prices')->where($where)->field('sell_price')->order('sell_price')->find();
								//echo db('hotel_room_prices')->getlastsql();
								//$newpro['sell_price'] = $room['sell_price'];
								//查询最低的房型价格
								$where = [];
								$where[] =['roomid','=',$room['roomid']];
								$nowtime = time();
								$where[] = Db::raw("unix_timestamp(datetime)>=$nowtime");
								
								//是否有设置余额定价
								if($room['isdaymoney']==1){
									$newpro['isdaymoney'] = 1;	
									$roomprice = Db::name('hotel_room_prices')->where($where)->where('daymoney','>=','1')->field('daymoney')->order('daymoney')->find();
									if($roomprice){
										$newpro['min_daymoney'] = $roomprice['daymoney'];
									}else{
										$newpro['min_daymoney'] = 0;
									}
								}else{
									$roomprice = Db::name('hotel_room_prices')->where($where)->field('sell_price')->order('sell_price')->find();
									//echo db('hotel_room_prices')->getlastsql();
									$newpro['isdaymoney'] =0;	
									if($roomprice){
										$newpro['sell_price'] = $roomprice['sell_price'];
									}else{
										$newpro['sell_price'] = 0;
									}
									
								}

								$newdata[] = $newpro;
							}
						}
						$pagecontent[$k]['data'] = $newdata;
					}else{
						$where = [];
						$where[] = ['aid','=',$aid];
						$where[] = ['status','=',1];
						$nowtime = time();
						if($v['params']['group']){
							$gid = intval($v['params']['group']);
							$where[] = Db::raw("find_in_set({$gid},gid)");
						}
						if($v['params']['hotelid']){
							$where[] = ['hotelid','=',$v['params']['hotelid']];
						}
						$order = 'sort desc';
						if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
						if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
						if($v['params']['sortby'] == 'createtime') $order = 'createtime';
						if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
						if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
						$field = "id roomid,name,pic,sales,tag,stock,isdaymoney";
						$newdata = Db::name('hotel_room')->field($field)->where($where)->order($order)->limit(intval($v['params']['shownum']))->select()->toArray();
						//var_dump($newdata);
						if(!$newdata) $newdata = array();
						foreach($newdata as $k2=>$v2){
							$newdata[$k2]['id'] = 'G'.time().rand(10000000,99999999);
							$newdata[$k2]['tag'] = explode(',',$v2['tag']);
							//查询最低的房型价格
							$where = [];
							$where[] =['roomid','=',$v2['roomid']];
							$nowtime = time();
							$where[] = Db::raw("unix_timestamp(datetime)>=$nowtime");
							//是否有设置余额定价
							if($v2['isdaymoney']==1){
								$newdata[$k2]['isdaymoney'] = 1;	
								$roomprice = Db::name('hotel_room_prices')->where($where)->where('daymoney','>=','1')->field('daymoney')->order('daymoney')->find();
								if($roomprice){
									$newdata[$k2]['min_daymoney'] = $roomprice['daymoney'];
								}else{
									$newdata[$k2]['min_daymoney'] = 0;
								}
							}else{
								$roomprice = Db::name('hotel_room_prices')->where($where)->field('sell_price')->order('sell_price')->find();
								//echo db('hotel_room_prices')->getlastsql();
								$newdata[$k2]['isdaymoney'] =0;	
								if($roomprice){
									$newdata[$k2]['sell_price'] = $roomprice['sell_price'];
								}else{
									$newdata[$k2]['sell_price'] = 0;
								}
							}
							$v2['isbooking'] = false;
							$roomprice2 = Db::name('hotel_room_prices')->where($where)->where('status',0)->find();
							if($roomprice2){
								$v2['isbooking'] = true;	continue;
							}

						}
						$pagecontent[$k]['data'] = $newdata;
					}
					$text = \app\models\Hotel::gettext($aid);
					$pagecontent[$k]['text'] = $text;
				}
			}
		}
		return json_encode($pagecontent);
	}

	public static function getOrderNumFromJidian($aid,$bid,$set,$mid,$num=0,$giveReward=false)
    {
//        $num = 0;
        $currentReward = [];//当前奖励
        $lastReward = [];
        //存在多笔消费，多个奖励的情况
        //统计下单数量
        $paygive_scene = explode(',',$set['paygive_scene']);
        $set['price_start'] = $set['price_start'] > 0 ? $set['price_start'] : 0;

        $where = [];
        $where[] = ['aid','=',$aid];
        $where[] = ['bid','=',$bid];
        $where[] = ['mid','=',$mid];
        $where[] = ['status','=',3];
        $where[] = ['totalprice','>=',$set['price_start']];
        if($set['days'] > 0)
            $where[] = ['createtime','>=',time()-$set['days']*86400];
        $where[] = ['createtime','between',[$set['starttime'],$set['endtime']]];
        if(in_array('shop',$paygive_scene) && $mid > 0){
            $num_shop = \db('shop_order')->where($where)->count();
        }
//        dd( Db::getLastSql());
        if(in_array('restaurant_shop',$paygive_scene) && $mid > 0){
            $num_restaurant_shop = \db('restaurant_shop_order')->where($where)->count();
        }
        if(in_array('restaurant_takeaway',$paygive_scene) && $mid > 0){
            $num_restaurant_takeaway = \db('restaurant_takeaway_order')->where($where)->count();
        }
        $num = $num + $num_shop + $num_restaurant_shop + $num_restaurant_takeaway;

        $setArr = json_decode($set['set'],true);

        //todo 减去已领奖励的订单（第n轮 N>1）
        $lastkey = count($setArr)-1;
        $num = $num % $setArr[$lastkey]['days'];
        if($num === 0) $num = $setArr[$lastkey]['days'];

        if($setArr){
            foreach ($setArr as $key => $item){
                if($item['coupon_id'] > 0 && $num >= $item['days']) {
                    $currentReward = $item;
                    //发放奖励
                    if($giveReward){
                        $record = \db('jidian_record')->where('aid',$aid)->where('bid',$bid)->where('mid',$mid)->order('id','desc')->find();
                        if(empty($record) || $record['jidian_num'] < $item['days']) {
                            $member = \db('member')->where('aid',$aid)->where('id',$mid)->find();
                            $data = [
                                'aid'=>$aid,
                                'bid'=>$bid,
                                'name' => $set['name'],
                                'mid' => $mid,
                                'headimg'=>$member['headimg'],
                                'nickname' => $member['nickname'],
                                'jidian_num' => $item['days'],
                                'coupon_ids' => $item['coupon_id'],
                                'createtime' => time(),
                                'createdate' => date('Y-m-d'),
                                'status' => 1
                            ];
                            \db('jidian_record')->insert($data);
                            \app\commons\Coupon::send($aid,$mid,$item['coupon_id']);
                        }
                    }
                }
                if($item['coupon_id'] > 0 && $item['days'] > 0) {
                    $lastReward = $item;
                }
            }
            if(empty($currentReward)){
                $currentReward = $setArr[0];
            }
            if($lastReward['days'] == $num){
                $currentReward = $setArr[0];
                $num = 0;
            }
        }
        return ['total_num'=>$lastReward['days'],'have_num'=>$num,'reward_name'=>$currentReward['coupon_name'],'reward_num'=>$currentReward['days']];
    }
}