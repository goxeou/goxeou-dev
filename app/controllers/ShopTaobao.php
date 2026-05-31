<?php


// +----------------------------------------------------------------------
// | 淘宝助手
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ShopTaobao extends Common
{
    public function index(){
		if(request()->isAjax()){
			$apikey = input('post.apikey');
			cache('99apikey_'.aid.'_'.bid,$apikey);
			if(!$apikey) return json(['status'=>0,'msg'=>'请填写apikey']);
			if(input('param.type')==0 || input('param.type')==1){
				$result = $this->taobaourl($apikey,input('post.taobaourl'));
			}
			if(input('param.type')==2){
				$result = $this->jdurl($apikey,input('post.taobaourl'));
			}
			if(input('param.type')==3){
				$result = $this->pddurl($apikey,input('post.taobaourl'));
			}
			if(input('param.type')==4){
				$result = $this->alibabaurl($apikey,input('post.taobaourl'));
			}
			return $result;
		}
		View::assign('apikey',cache('99apikey_'.aid.'_'.bid));
		return View::fetch();
    }
	public function taobaourl($apikey,$taobaourl){
		if(is_numeric($taobaourl)){
			$taobaoid = $taobaourl;
		}else{
			$taobaourl = str_replace('&id=','?id=',$taobaourl);
			$urlArr = explode('?id=',$taobaourl);
			$taobaoid = explode('&',$urlArr[1])[0];
		}
		if(input('param.type')==0){
			$rs = request_get('https://api03.6bqb.com/taobao/detail?apikey='.$apikey.'&itemid='.$taobaoid);
		}
		if(input('param.type')==1){
			$rs = request_get('https://api03.6bqb.com/taobao/detail?apikey='.$apikey.'&itemid='.$taobaoid);
		}
		$rs = json_decode($rs,true);
		if(!$rs) return json(['status'=>0,'msg'=>'采集失败']);
		//dump($rs);
		if($rs['retcode']!='0000') return json(['status'=>0,'msg'=>$rs['message'] ? $rs['message'] : $rs['data']]);
		$rsdata = $rs['data']['item'];
		//dump($rsdata);
		
		$pics = $rsdata['images'];
		foreach($pics as $k=>$pic){
			if(strpos($pic,'//')===0) $pic = 'https:'.$pic;
			$pics[$k] = \app\commons\Pic::uploadoss($pic);
		}
		$data = [];
		$data['aid'] = aid;
		$data['bid'] = bid;
		$data['name'] = $rsdata['title'];
		$data['sellpoint'] = $rsdata['subTitle'];
		$data['pic'] = $pics[0];
		$data['pics'] = implode(',',$pics);
		$descImgs = $rsdata['descImgs'];
		
		$content = '';
		foreach($descImgs as $k=>$pic){
			if(strpos($pic,'//')===0) $pic = 'https:'.$pic;
			$pic = \app\commons\Pic::uploadoss($pic);
			$content.= '<img src="'.$pic.'" width="100%"/>';
		}

		$data['detail'] = jsonEncode([[
			'id'=>'M0000000000000',
			'temp'=>'richtext',
			'params'=>['bgcolor'=>'#FFFFFF','margin_x'=>0,'margin_y'=>0,'padding_x'=>0,'padding_y'=>0,'quanxian'=>['all'=>true],'platform'=>['all'=>true]],
			'data'=>'',
			'other'=>'',
			'content'=>$content
		]]);

		$pros = $rsdata['props'];
		$guigedata = [];
		$ggnames = [];
		foreach($pros as $k=>$v){
			$items = [];
			foreach($v['values'] as $k2=>$v2){
				$items[] = ['k'=>$k2,'title'=>$v2['name']];
				$ggnames[$v['pid'].':'.$v2['vid']] = ['k'=>$k2,'title'=>$v2['name'],'pid'=>$v['pid'],'vid'=>$v2['vid'],'path'=>$v['pid'].':'.$v2['vid']];
			}
			$guigedata[] = ['k'=>$k,'title'=>$v['name'],'items'=>$items,'pid'=>$v['pid']];
		}

		$sku = $rsdata['sku'];

		$sell_price = 0;
		$gglist = [];
		$countSku = count($sku);
		foreach($sku as $k=>$v){
			if($countSku > 1 && $k==0) {
                continue;
            }
            $gg = [];
			$propPath = explode(';',$v['propPath']);
			foreach($propPath as $v2){
				$gg['ks'][] = $ggnames[$v2]['k'];
				$gg['name'][] = $ggnames[$v2]['title'];
			}
			$gg['ks'] = implode(',',$gg['ks']);
			$gg['name'] = implode(',',$gg['name']);
			$gg['stock'] = $v['quantity'];
			$gg['sell_price'] = $v['price'];
			$gg['pic'] = $v['image'];
			$gglist[] = $gg;
			if($sell_price==0 || $v['price'] < $sell_price){
				$sell_price = floatval($v['price']);
			}
		}
		$data['sell_price'] = $sell_price;
		$data['guigedata'] = jsonEncode($guigedata);
		$data['status'] = 0;
		$data['createtime'] = time();
		$proid = Db::name('shop_product')->insertGetId($data);
		foreach($gglist as $v){
			$ggdata = array();
			$ggdata['aid'] = aid;
			$ggdata['proid'] = $proid;
			$ggdata['name'] = $v['name'];
			if(strpos($v['pic'],'//')===0) $v['pic'] = 'https:'.$v['pic'];
			if($v['pic']){
				$ggdata['pic'] = \app\commons\Pic::uploadoss($v['pic']);
			}
			$ggdata['sell_price'] = $v['sell_price'];
			$ggdata['stock'] = $v['stock'];
			$ggdata['ks'] = $v['ks'];
			Db::name('shop_guige')->insert($ggdata);
		}
		\app\commons\System::plog('商城商品采集'.$proid);
		return json(['status'=>1,'msg'=>'添加完成','proid'=>$proid]);
	}

	public function jdurl($apikey,$taobaourl){
		if(is_numeric($taobaourl)){
			$taobaoid = $taobaourl;
		}else{
			$urlArr = explode('item.jd.com/',$taobaourl);
			$taobaoid = explode('.html',$urlArr[1])[0];
		}
		$rs = request_get('https://api03.6bqb.com/jd/detail?apikey='.$apikey.'&itemid='.$taobaoid);
		$rs = json_decode($rs,true);
		//if(!$rs) return json(['status'=>0,'msg'=>'采集失败']);

		$rsdata = $rs['data']['item'];
		
		$pics = $rsdata['images'];
		foreach($pics as $k=>$pic){
			if(strpos($pic,'//')===0) $pic = 'https:'.$pic;
			$pics[$k] = \app\commons\Pic::uploadoss($pic);
		}
		$data = [];
		$data['aid'] = aid;
		$data['bid'] = bid;
		$data['name'] = $rsdata['name'];
		$data['pic'] = $pics[0];
		$data['pics'] = implode(',',$pics);
		$descImgs = $rsdata['descImgs'];
		
		$content = '';
		foreach($descImgs as $k=>$pic){
			if(strpos($pic,'//')===0) $pic = 'https:'.$pic;
			$pic = \app\commons\Pic::uploadoss($pic);
			$content.= '<img src="'.$pic.'" width="100%"/>';
		}

		$data['detail'] = jsonEncode([[
			'id'=>'M0000000000000',
			'temp'=>'richtext',
			'params'=>['bgcolor'=>'#FFFFFF','margin_x'=>0,'margin_y'=>0,'padding_x'=>0,'padding_y'=>0,'quanxian'=>['all'=>true],'platform'=>['all'=>true]],
			'data'=>'',
			'other'=>'',
			'content'=>$content
		]]);

		$pros = $rsdata['sku'];
		if($pros){
            $guigedata = [];
            $ggnames = [];
            $items = [];
            foreach($pros as $k=>$v){
                $items[] = ['k'=>$k,'title'=>$v[1]];
            }
            $guigedata[] = ['k'=>0,'title'=>$rsdata['saleProp'][1],'items'=>$items];

            $sku = $rsdata['sku'];

            $sell_price = 0;
            $gglist = [];
            foreach($sku as $k=>$v){
                $gg = [];
                $gg['ks'] = $k;
                $gg['name'] = $v[1];
                $gg['stock'] = $v['stockState'];
                $gg['sell_price'] = $v['price'];
                $gg['market_price'] = $v['originalPrice'];
                $gg['pic'] = '';
                $gglist[] = $gg;
                if($sell_price==0 || $v['price'] < $sell_price){
                    $sell_price = floatval($v['price']);
                }
            }
        }else{
		    //采集到的商品没有规格
            $sell_price = $rsdata['price'];
            $guigedata = [
                [
                    'k' => 0,
                    'title' => '规格',
                    'items' => [
                        [
                            'k' => 0,
                            'title' => '默认规格'
                        ]
                    ]
                ]
            ];
            $gg = [];
            $gg['ks'] = 0;
            $gg['name'] = '默认规格';
            $gg['stock'] = 0;
            $gg['sell_price'] = $sell_price;
            $gg['market_price'] = $rsdata['originalPrice'];
            $gg['pic'] = '';
            $gglist = [];
            $gglist[] = $gg;
        }

		$data['sell_price'] = $sell_price;
		$data['guigedata'] = jsonEncode($guigedata);
		$data['status'] = 0;
		$data['createtime'] = time();
		$proid = Db::name('shop_product')->insertGetId($data);
		foreach($gglist as $v){
			$ggdata = array();
			$ggdata['aid'] = aid;
			$ggdata['proid'] = $proid;
			$ggdata['name'] = $v['name'];
			if(strpos($v['pic'],'//')===0) $v['pic'] = 'https:'.$v['pic'];
			if($v['pic']){
				$ggdata['pic'] = \app\commons\Pic::uploadoss($v['pic']);
			}
			$ggdata['sell_price'] = $v['sell_price'];
			$ggdata['stock'] = $v['stock'];
			$ggdata['ks'] = $v['ks'];
			Db::name('shop_guige')->insert($ggdata);
		}
		\app\commons\System::plog('商城商品采集'.$proid);
		return json(['status'=>1,'msg'=>'添加完成','proid'=>$proid]);
	}


	public function alibabaurl($apikey,$taobaourl){
		if(is_numeric($taobaourl)){
			$taobaoid = $taobaourl;
		}else{
			$urlArr = explode('detail.1688.com/offer/',$taobaourl);
			$taobaoid = explode('.html',$urlArr[1])[0];
		}
		$rs = request_get('https://api03.6bqb.com/alibaba/detail?apikey='.$apikey.'&itemid='.$taobaoid);
		$rs = json_decode($rs,true);
		if(!$rs) return json(['status'=>0,'msg'=>'采集失败']);
		//dump($rs);
		if($rs['retcode']!='0000') return json(['status'=>0,'msg'=>$rs['message'] ? $rs['message'] : $rs['data']]);
		$rsdata = $rs['data'];
		//dump($rsdata);
		
		$pics = $rsdata['images'];
		foreach($pics as $k=>$pic){
			if(strpos($pic,'//')===0) $pic = 'https:'.$pic;
			$pics[$k] = \app\commons\Pic::uploadoss($pic);
		}
		$data = [];
		$data['aid'] = aid;
		$data['bid'] = bid;
		$data['name'] = $rsdata['title'];
		$data['pic'] = $pics[0];
		$data['pics'] = implode(',',$pics);
		$descImgs = $rsdata['descImgs'];
		
		$content = '';
		foreach($descImgs as $k=>$pic){
			if(strpos($pic,'//')===0) $pic = 'https:'.$pic;
			$pic = \app\commons\Pic::uploadoss($pic);
			$content.= '<img src="'.$pic.'" width="100%"/>';
		}

		$data['detail'] = jsonEncode([[
			'id'=>'M0000000000000',
			'temp'=>'richtext',
			'params'=>['bgcolor'=>'#FFFFFF','margin_x'=>0,'margin_y'=>0,'padding_x'=>0,'padding_y'=>0,'quanxian'=>['all'=>true],'platform'=>['all'=>true]],
			'data'=>'',
			'other'=>'',
			'content'=>$content
		]]);
		
		$pros = $rsdata['skuProps'];

		$guigedata = [];
		$ggnames = [];
		$ggimgs = [];
		foreach($pros as $k=>$v){
			$items = [];
			foreach($v['value'] as $k2=>$v2){
				$items[] = ['k'=>$k2,'title'=>$v2['name']];
				$ggnames[$k][] = $v2['name'];
				if(isset($v2['imageUrl'])){
					$ggimgs[$v2['name']] = $v2['imageUrl'];
				}
				
			}
			$guigedata[] = ['k'=>$k,'title'=>$v['prop'],'items'=>$items];
		}
		//dump($ggnames);
		$ksnames = array();
		foreach($ggnames as $k =>$v) {
			$ksnames = $this->getCombinationToString($v);
		}
		//dump($ksnames);
		$namesks = array_flip($ksnames);
		//dump($namesks);

		$sku = $rsdata['skuMap'];
		$sell_price = 0;
		$gglist = [];
		foreach($ksnames as $k=>$v){
			$gg = [];
			$gg['ks'] = $k;
			$gg['name'] = str_replace('&gt;',',',$v);
			$gg['stock'] = $sku[$v]['canBookCount'];
			$gg['market_price'] = $sku[$v]['price'];
			$gg['sell_price'] = $sku[$v]['discountPrice'] ? $sku[$v]['discountPrice'] : $sku[$v]['price'];
			$gg['pic'] = '';
			$arr_img = explode(',',$gg['name']);
			foreach($arr_img as $vn){
				if(isset($ggimgs[$vn])){
					$gg['pic'] = $ggimgs[$vn];
				}
			}			
			$gglist[] = $gg;
			if($sell_price==0 || $gg['sell_price'] < $sell_price){
				$sell_price = floatval($gg['sell_price']);
			}
		}

		$data['sell_price'] = $sell_price;
		$data['guigedata'] = jsonEncode($guigedata);
		$data['status'] = 0;
		$data['createtime'] = time();
		$proid = Db::name('shop_product')->insertGetId($data);
		foreach($gglist as $v){
			$ggdata = array();
			$ggdata['aid'] = aid;
			$ggdata['proid'] = $proid;
			$ggdata['name'] = $v['name'];
			$ggdata['sell_price'] = $v['sell_price'];
			$ggdata['stock'] = $v['stock'];
			$ggdata['ks'] = $v['ks'];
			if(strpos($v['pic'],'//')===0) $v['pic'] = 'https:'.$v['pic'];
			if($v['pic']){
				$ggdata['pic'] = \app\commons\Pic::uploadoss($v['pic']);
			}
			Db::name('shop_guige')->insert($ggdata);
		}
		\app\commons\System::plog('商城商品采集'.$proid);
		return json(['status'=>1,'msg'=>'添加完成','proid'=>$proid]);
	}

	// * 获取组合的结果
	function getCombinationToString($val){
		// 保存上一个的值
		static $res = array();
		if(empty($res)){
			$res = $val;
		}else{
			// 临时数组保存结合的结果
			$list = array();
			foreach ($res as $k => $v) {
				foreach ($val as $key => $value) {
				   $list[$k.','.$key] = $v.'&gt;'.$value;     
				}
			}
			$res = $list;
		}
		return $res;
	}
	public function decodeUnicode($str){
		return preg_replace_callback('/\\\\u([0-9a-f]{4})/i',
			create_function(
				'$matches',
				'return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UCS-2BE");'
			),
			$str);
	}
}