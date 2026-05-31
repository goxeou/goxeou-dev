<?php


namespace app\models;
use app\commons\Order;
use think\facade\Db;
use think\facade\Log;

class LuckyCollage
{
	//幸运拼团开奖
	static function kaijiang($order){
        $aid = $order['aid'];
        $mid = $order['mid'];
        //取出商品设置
        $product = Db::name('lucky_collage_product')->where(['id'=>$order['proid']])->find();
        $orderlist = Db::name('lucky_collage_order')->where(['teamid'=>$order['teamid'],'status'=>1])->select()->toArray();
        //处理机器开团的订单mid
        $teamarr1 = [];
        $set = Db::name("lucky_collage_sysset")->where('aid', $aid)->find();
        $bzjarr = [];
        $zjarr = [];
        $zjcount = 0;
        if($orderlist){
            foreach($orderlist as $ov){
                if($ov['isjiqiren'] == 1){
                    $teamarr1[] = -$ov['mid'];
                    if(getcustom('luckycollage_robot_get')){
                        if($set['robot_get']==0){
                            $bzjarr[] = -$ov['mid'];
                        }
                    }
                }else{
                    $teamarr1[] = $ov['mid'];

                    if (getcustom('luckycollage_zjmax')) {
                        $zjnum = Db::name("lucky_collage_order")->where("mid", $ov['mid'])
                            ->where("proid", $order['proid'])
                            ->where("iszj", 1)
                            ->where("isjiqiren", 0)
                            ->count();
                        if ($zjnum >= $product['zjmax'] && $product['zjmax'] > 0) {
                            $bzjarr[] = $ov['mid'];
                        }
                    }

                }
            }
            unset($ov);
        }

        if(getcustom('plug_luckycollage')){  //back定制的幸运拼团
            if($product['bzids']){
                $bzjarr = array_merge($bzjarr, explode(',',$product['bzids']));  //设置的不中奖的ids
            }
            //查看是否设置了中奖的会员id
            if($product['zjids']){
                $zjarr = explode(',',$product['zjids']);  //设置的中奖的ids
                $zjcount = count($zjarr);
            }
        }

        if(getcustom('member_tag')){
            //查看会员标签
            if($product['istag']==1 && $product['tags']){
                $protags = json_decode($product['tags'],true);
                $memberlist = Db::name('member')->field('id,tags')->where('id','in',$teamarr1)->select()->toArray();
                foreach($memberlist as $m){
                    if($protags['membertags']==-1){
                        //查看中奖次数
                        $zjdata = Db::name('lucky_collage_order')->field('id')->where('mid','=',$m['id'])->where('iszj','=',1)->where('proid',$product['id'])->where('isjiqiren',0)->order('id desc')->find();
                        if($zjdata['id']){
                            //查看大于次订单未中奖的数量
                            $wzjcount = 0 + Db::name('lucky_collage_order')->where('id','>',$zjdata['id'])->where('mid','=',$m['id'])->where('iszj','=',2)->where('proid',$product['id'])->where('isjiqiren',0)->count();
                            if($wzjcount<$protags['tagbzjnum']){
                                $bzjarr[] = $m['id'];
                            }
                        }
                    }elseif($protags['membertags']>0 && $m['tags']){
                        $memtags = explode(',',$m['tags']);
                        if(in_array($protags['membertags'],$memtags)){
                            //查看中奖次数
                            $zjdata = Db::name('lucky_collage_order')->field('id')->where('mid','=',$m['id'])->where('iszj','=',1)->where('proid',$product['id'])->where('isjiqiren',0)->order('id desc')->find();
                            if($zjdata['id']){
                                //查看大于次订单未中奖的数量
                                $wzjcount = 0 + Db::name('lucky_collage_order')->where('id','>',$zjdata['id'])->where('mid','=',$m['id'])->where('proid',$product['id'])->where('iszj','=',2)->where('isjiqiren',0)->count();
                                if($wzjcount<$protags['tagbzjnum']){
                                    $bzjarr[] = $m['id'];
                                }
                            }
                        }
                    }
                }
            }
        }

        $teamarr1 = array_diff($teamarr1, $zjarr);	//去掉中奖的id
        $teamarr  = array_diff($teamarr1, $bzjarr);  //去掉不中奖的id
        $syzjnum  = intval($product['gua_num']-$zjcount);
        $selected = array_rand($teamarr,$syzjnum);
        if($syzjnum>0 && $syzjnum==1){
            $newteam[] = $teamarr[$selected];	//中奖的
            unset($teamarr[$selected]);//不中奖
        }elseif($syzjnum>1){
            foreach($selected as $s){
                $newteam[] = $teamarr[$s]; //中奖的订单
                unset($teamarr[$s]);
            }
        }else{
            $newteam=[];
        }
        //中将的会员id = 设置好的中将id+其他随机出来的id
        $newteam = array_merge($zjarr,$newteam);
        //不中奖的会员id = 设置好的id+其他随机出来的id
        $teamarr = array_merge($bzjarr,$teamarr);
        if($newteam){
            $stock=0;
            //中奖的人数
            foreach($newteam as $v){
                $mid = abs($v);
                $where = [];
                $where[] = ['teamid','=',$order['teamid']];
                $where[] = ['mid','=',$mid];
                $where[] = ['status','=',1];
                if($v>0){
                    $where[] = ['isjiqiren','=',0];
                }else{
                    $where[] = ['isjiqiren','=',1];
                }
                $orderinfo = Db::name('lucky_collage_order')->where($where)->find();
                $guige = Db::name('lucky_collage_guige')->field('stock')->where('aid',$aid)->where('id',$orderinfo['ggid'])->find();
                Db::name('lucky_collage_guige')->where('aid',$aid)->where('id',$orderinfo['ggid'])->update(['stock'=>$guige['stock']-$orderinfo['num']]);
                $stock+=$orderinfo['num'];

                if($v>0){
                    $members = Db::name('member')->where('id',$mid)->find();
                    if($members){

                        if(getcustom('plug_luckycollage') || getcustom('luckycollage_zjjl')){   //中奖者 奖励积分/余额/佣金/优惠券
                            if($product['zjjl_type']==1){  //1 奖励余额
                                if(isset($product['zj_money_type']) && isset($product['zj_money_rate'])&& $product['zj_money_type']==1 && $product['zj_money_rate'] > 0){
                                    $product['zj_money'] = number_format($product['sell_price']*$product['zj_money_rate']*0.01,2);
                                } 
                                if($product['zj_money']>0){
                                    \app\commons\Member::addmoney($aid,$orderinfo['mid'],$product['zj_money'],'参加拼团中奖奖励'.t('余额'));
                                }                                
                            }else if($product['zjjl_type']==2 ){  //2 奖励积分
                                if(isset($product['zjjlscore_type']) && isset($product['zjjlscore_rate'])&& $product['zjjlscore_type']==1 && $product['zjjlscore_rate'] > 0){
                                    $product['zjscore'] = intval($product['sell_price']*$product['zjjlscore_rate']*0.01);
                                }
                                if($product['zjscore'] > 0){
                                    \app\commons\Member::addscore($aid,$orderinfo['mid'],$product['zjscore'],'参加拼团中奖奖励'.t('积分'));
                                }                                
                            }else if($product['zjjl_type']==3 && $product['zjcommission'] > 0){  //3 奖励佣金
                                \app\commons\Member::addcommission($aid,$orderinfo['mid'],$orderinfo['mid'],$product['zjcommission'],'参加拼团中奖奖励'.t('佣金'));
                            }elseif($product['zjjl_type']==4 && $product['zj_yhqids']){ //奖励优惠券
                                $yhqids = explode(',',$product['zj_yhqids']);
                                foreach($yhqids as $yhqid){
                                    \app\commons\Coupon::send($aid,$orderinfo['mid'],$yhqid);
                                }
                            }
                        }else if($orderinfo['givescore'] > 0){
                            \app\commons\Member::addscore($aid,$orderinfo['mid'],$orderinfo['givescore'],'购买产品奖励'.t('积分'));
                        }
                    }
                }else{
                    $members = Db::name('lucky_collage_jiqilist')->where('id',$mid)->find();
                }
                //同城配送
                if($order['freight_type'] == 2){
                    if(getcustom('express_maiyatian_autopush')) {
                        //麦芽田同城配送自动推送
                        \app\customs\MaiYaTianCustom::auto_push($aid,$orderinfo['id'],$orderinfo,'lucky_collage_order');
                    }
                }
                //自动发货
                if($orderinfo['freight_type']==3){
                    $freight_content = Db::name('lucky_collage_product')->where('id',$orderinfo['proid'])->value('freightcontent');
                    Db::name('lucky_collage_order')->where('id',$orderinfo['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);
                    //发货信息录入 微信小程序+微信支付
                    if($v>0 && $orderinfo['platform'] == 'wx' && $orderinfo['paytypeid'] == 2){
                        \app\commons\Order::wxShipping($orderinfo['aid'],$orderinfo,'lucky_collage');
                    }
                }
                //在线卡密
                if($orderinfo['freight_type']==4){
                    $codelist = Db::name('lucky_collage_codelist')->where('proid',$orderinfo['proid'])->where('status',0)->order('id')->limit($orderinfo['num'])->select()->toArray();
                    if($codelist && count($codelist) >= $orderinfo['num']){
                        $pscontent = [];
                        foreach($codelist as $codeinfo){
                            $pscontent[] = $codeinfo['content'];
                            Db::name('lucky_collage_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$orderinfo['id'],'ordernum'=>$orderinfo['ordernum'],'headimg'=>$members['headimg'],'nickname'=>$members['nickname'],'buytime'=>time(),'status'=>1]);
                        }
                        $pscontent = implode("\r\n",$pscontent);
                        Db::name('lucky_collage_order')->where('id',$orderinfo['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
                    }
                    //发货信息录入 微信小程序+微信支付
                    if($v>0 && $orderinfo['platform'] == 'wx' && $orderinfo['paytypeid'] == 2){
                        \app\commons\Order::wxShipping($orderinfo['aid'],$orderinfo,'lucky_collage');
                    }
                }
                $team = Db::name('lucky_collage_order_team')->where('aid',$aid)->where('id',$order['teamid'])->find();
                if($orderinfo['isjiqiren']!=1){
                    //公众号通知 拼团成功通知
                    $tmplcontent = [];
                    $tmplcontent['first'] = '有新拼团订单拼团成功';
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $order['title']; //商品名称
                    $tmplcontent['keyword2'] = $members['nickname'];//团长
                    $tmplcontent['keyword3'] = $team['teamnum'];//成团人数
                    $tmplcontent['first'] = '恭喜您拼团成功';
                    $rs = \app\commons\Wechat::sendtmpl($aid,$orderinfo['mid'],'tmpl_collagesuccess',$tmplcontent,m_url('activity/luckycollage/orderlist', $aid));
                    //订阅消息
                    $tmplcontent = [];
                    $tmplcontent['thing1'] = $order['title'];
                    $tmplcontent['thing10'] = $members['nickname'];
                    $tmplcontent['number12'] = $team['teamnum'];

                    $tmplcontentnew = [];
                    $tmplcontentnew['thing7'] = $order['title'];
                    $tmplcontentnew['thing12'] = $members['nickname'];
                    $tmplcontentnew['number2'] = $team['teamnum'];
                    \app\commons\Wechat::sendwxtmpl($aid,$orderinfo['mid'],'tmpl_collagesuccess',$tmplcontentnew,'activity/luckycollage/orderlist',$tmplcontent);
                    //短信通知
                    if($members['tel']){
                        $rs = \app\commons\Sms::send($aid,$members['tel']?$members['tel']:$orderinfo['tel'],'tmpl_collagesuccess',['ordernum'=>$orderinfo['ordernum']]);
                    }
                }
                Db::name('lucky_collage_order')->where('id',$orderinfo['id'])->update(['iszj'=>1]);
                //增加销量
                \app\models\Payorder::addSales($orderinfo['id'],'lucky_collage',$aid,$orderinfo['bid'],$orderinfo['num']);
            }
            //减掉库存
            Db::name('lucky_collage_product')->where('aid',$aid)->where('id',$product['id'])->update(['stock'=>$product['stock']-$stock]);
        }
        //未中奖
        foreach($teamarr as $t){
            $mid = abs($t);
            $where = [];
            $where[] = ['teamid','=',$order['teamid']];
            $where[] = ['mid','=',$mid];
            $where[] = ['status','=',1];
            $where[] = ['iszj','=',0];
            if($t>0){
                $where[] = ['isjiqiren','=',0];
            }else{
                $where[] = ['isjiqiren','=',1];
            }
            $orderinfo = Db::name('lucky_collage_order')->where($where)->find();
            if(!$orderinfo['isjiqiren']){
                //幸运拼团定制
                if(getcustom('plug_luckycollage')){//未中奖励
                    if($product['bzjl_type']==1 && $product['fy_money_val']>0){  //1 奖励余额
                        $money = $product['fy_money_val'];
                        \app\commons\Member::addmoney($aid,$orderinfo['mid'],$product['fy_money_val'],'参加拼团未中奖奖励'.t('余额'));
                    }else if($product['bzjl_type']==2 && $product['bzj_score'] > 0){  //2 奖励积分                                               
                        \app\commons\Member::addscore($aid,$orderinfo['mid'],$product['bzj_score'],'参加拼团未中奖奖励'.t('积分'));
                    }else if($product['bzjl_type']==3 && $product['bzj_commission'] > 0){  //3 奖励佣金
                        \app\commons\Member::addcommission($aid,$orderinfo['mid'],$orderinfo['mid'],$product['bzj_commission'],'参加拼团未中奖奖励'.t('佣金'));
                    }elseif($product['bzjl_type']==4 && $product['bzj_yhqids']){ //奖励优惠券
                        $yhqids = explode(',',$product['bzj_yhqids']);
                        foreach($yhqids as $yhqid){
                            \app\commons\Coupon::send($aid,$orderinfo['mid'],$yhqid);
                        }
                    }
                }else{
                    if(getcustom('luckycollage_bzjl')){
                        if($product['bzjl_type']==2){  //2 奖励积分
                            if(isset($product['bzjlscore_type']) && $product['bzjlscore_type']==1 && $product['bzjlscore_rate'] > 0){
                                $product['bzj_score'] = intval($product['sell_price']*$product['bzjlscore_rate']*0.01);
                            } 
                            if($product['bzj_score'] > 0){
                                \app\commons\Member::addscore($aid,$orderinfo['mid'],$product['bzj_score'],'参加拼团未中奖奖励'.t('积分'));
                            }
                            
                        }else if($product['bzjl_type']==3 && $product['bzj_commission'] > 0){  //3 奖励佣金
                            \app\commons\Member::addcommission($aid,$orderinfo['mid'],$orderinfo['mid'],$product['bzj_commission'],'参加拼团未中奖奖励'.t('佣金'));
                        }elseif($product['bzjl_type']==4 && $product['bzj_yhqids']){ //奖励优惠券
                            $yhqids = explode(',',$product['bzj_yhqids']);
                            foreach($yhqids as $yhqid){
                                \app\commons\Coupon::send($aid,$orderinfo['mid'],$yhqid);
                            }
                        }
                    }
                    //奖励红包  red_give_mode 1返到余额，2 是返到零钱 fy_type 1 按比例 fy_money，2 是按金额 fy_money_val
                    if($product['fy_type']==1) $money = number_format($product['sell_price']*$product['fy_money']/100,2);
                    if($product['fy_type']==2) $money = $product['fy_money_val'];
                    if($product['red_give_mode']==1){
                        \app\commons\Member::addmoney($orderinfo['aid'],$orderinfo['mid'],$money,'拼团未中奖奖励'.t('余额'));
                    }else if($product['red_give_mode']==2){
                        \app\commons\Wxpay::transfers($orderinfo['aid'],$orderinfo['mid'],$money,'',$orderinfo['platform'],'拼团未中奖奖励红包');
                    }
                }
                $tk_status = 1;
                if(getcustom('luckycollage_norefund') && $product['tklx']=='3'){
                    $tk_status = 0;
                }
                if($tk_status == 1){
                    if($product['tklx']=='1'){
                        $paytype = $orderinfo['paytypeid'];
                        if(getcustom('luckycollage_score_pay')){
                            if($orderinfo['is_score_pay'] == 1){
                                \app\commons\Member::addscore($orderinfo['aid'],$orderinfo['mid'],$orderinfo['totalscore'],'拼团未中奖退款返还');
                            }
                        }
                        \app\commons\Order::refund($orderinfo,$orderinfo['totalprice'],'拼团未中奖订单退款');
                    }else{
                        \app\commons\Member::addmoney($orderinfo['aid'],$orderinfo['mid'],$orderinfo['totalprice'],'拼团未中奖退款');
                    }
                    //积分抵扣的返还
                    if($orderinfo['scoredk'] > 0){
                        \app\commons\Member::addscore($orderinfo['aid'],$orderinfo['mid'],$orderinfo['scoredk'],'拼团未中奖退款返还');
                    }
                    //扣除消费赠送积分
                    \app\commons\Member::decscorein($orderinfo['aid'],'lucky_collage',$orderinfo['id'],$orderinfo['ordernum'],'拼团未中奖退款扣除消费赠送');
                    //优惠券抵扣的返还
                    if($orderinfo['coupon_rid'] > 0){
                        Db::name('coupon_record')->where('aid',$aid)->where('mid',$orderinfo['mid'])->where('id',$orderinfo['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
                    }
                    if($orderinfo['bid'] > 0){
                        $binfo = Db::name('business')->where('aid',$aid)->where('id',$orderinfo['bid'])->find();
                        if($binfo['money'] < $money){
                            $money = 0;
                        }else{
                            \app\commons\Business::addmoney($aid,$order['bid'],-$money,'给幸运拼团未中奖成员发放奖励：'.$order['ordernum']);
                        }
                    }
                
                

                    //模板消息
                    //退款成功通知
                    $tmplcontent = [];
                    if($product['tklx']=='1'){
                        $tmplcontent['first'] = '您的订单已经完成退款，¥'.$orderinfo['totalprice'].'已经退回您的付款账户，请留意查收。';
                    }else{
                        $tmplcontent['first'] = '您的订单已经完成退款，¥'.$orderinfo['totalprice'].'已经退回您的余额账户，请留意查收。';
                    }
                    $tmplcontent['remark'] = '拼团成功，未中奖订单退款'.($money > 0 ? '并奖励红包'.$money.'元' : '').'，请点击查看详情~';
                    $tmplcontent['orderProductPrice'] = $orderinfo['totalprice'];
                    $tmplcontent['orderProductName'] = $orderinfo['title'];
                    $tmplcontent['orderName'] = $orderinfo['ordernum'];
                    $tmplcontentNew = [];
                    $tmplcontentNew['character_string1'] = $orderinfo['ordernum'];//订单编号
                    $tmplcontentNew['thing2'] = $orderinfo['title'];//商品名称
                    $tmplcontentNew['amount3'] = $orderinfo['totalprice'];//退款金额
                    \app\commons\Wechat::sendtmpl($aid,$orderinfo['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('activity/luckycollage/orderlist'),$tmplcontentNew);
                    //订阅消息
                    $tmplcontent = [];
                    $tmplcontent['amount6'] = $orderinfo['totalprice'];
                    $tmplcontent['thing3'] = $orderinfo['title'];
                    $tmplcontent['character_string2'] = $orderinfo['ordernum'];

                    $tmplcontentnew = [];
                    $tmplcontentnew['amount3'] = $orderinfo['totalprice'];
                    $tmplcontentnew['thing6'] = $orderinfo['title'];
                    $tmplcontentnew['character_string4'] = $orderinfo['ordernum'];
                    \app\commons\Wechat::sendwxtmpl($aid,$orderinfo['mid'],'tmpl_tuisuccess',$tmplcontentnew,'activity/luckycollage/orderlist',$tmplcontent);

                    //短信通知
                    $member = Db::name('member')->where('id',$orderinfo['mid'])->find();
                    if($member['tel']){
                        $tel = $member['tel'];
                    }else{
                        $tel = $orderinfo['tel'];
                    }
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_tuisuccess',['ordernum'=>$orderinfo['ordernum'],'money'=>$orderinfo['totalprice']]);
                }
            }
            if(getcustom('luckycollage_norefund') && $product['tklx']=='3'){
                Db::name('lucky_collage_order')->where('id',$orderinfo['id'])->update(['iszj'=>2]);
            }else{
                Db::name('lucky_collage_order')->where('id',$orderinfo['id'])->update(['status'=>4,'refund_status'=>2,'refund_time'=>time(),'refund_money'=>$orderinfo['totalprice'],'refund_reason'=>'拼团未中奖退款','iszj'=>2]);
            }
            

            //拼团未中奖重新更新分销佣金奖励,并发放
            if (getcustom('luckycollage_fail_commission')) {

                $forder = Db::name('lucky_collage_order')->where('id', $orderinfo['id'])->where("iszj", 2)
                    ->where('iscommission', 0)
                    ->where('isjiqiren', 0)
                    ->find();
                $member_level = Db::name('member_level')->where("aid", $aid)->column("*", "id");
                $commission_totalprice = $forder['totalprice'];
                $parent1 = $forder['parent1'] > 0 ? Db::name("member")->where("id", $forder['parent1'])->where('aid', $aid)->field("id, levelid")->find() : '';
                $parent2 = $forder['parent2'] > 0 ? Db::name("member")->where("id", $forder['parent1'])->where('aid', $aid)->field("id, levelid")->find() : '';
                $parent3 = $forder['parent3'] > 0 ? Db::name("member")->where("id", $forder['parent1'])->where('aid', $aid)->field("id, levelid")->find() : '';
                $agleveldata1 = $member_level[$parent1['levelid']];
                $agleveldata2 = $member_level[$parent2['levelid']];
                $agleveldata3 = $member_level[$parent3['levelid']];
                $update_order = [];
                if ($product['fail_commissionset'] == 1) {//按商品设置的分销比例
                    $commissiondata = json_decode($product['fail_commissiondata1'], true);
                    if ($commissiondata) {
                        if ($agleveldata1) $update_order['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
                        if ($agleveldata2) $update_order['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
                        if ($agleveldata3) $update_order['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
                    }
                } elseif ($product['fail_commissionset'] == 2) {//按固定金额
                    $commissiondata = json_decode($product['fail_commissiondata2'], true);
                    if ($commissiondata) {
                        if ($agleveldata1) $update_order['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'];
                        if ($agleveldata2) $update_order['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'];
                        if ($agleveldata3) $update_order['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'];
                    }
                } elseif ($product['fail_commissionset'] == 3) {//提成是积分
                    $commissiondata = json_decode($product['fail_commissiondata3'], true);
                    if ($commissiondata) {
                        if ($agleveldata1) $update_order['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'];
                        if ($agleveldata2) $update_order['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'];
                        if ($agleveldata3) $update_order['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'];
                    }
                }elseif($product['fail_commissionset'] == -1){

                } else { //按会员等级设置的分销比例
                    if ($agleveldata1) {
                        if ($agleveldata1['commissiontype'] == 1) { //固定金额按单
                            $update_order['parent1commission'] = $agleveldata1['commission1'];
                        } else {
                            $update_order['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
                        }
                    }
                    if ($agleveldata2) {
                        if ($agleveldata2['commissiontype'] == 1) {
                            $update_order['parent2commission'] = $agleveldata2['commission2'];
                        } else {
                            $update_order['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
                        }
                    }
                    if ($agleveldata3) {
                        if ($agleveldata3['commissiontype'] == 1) {
                            $update_order['parent3commission'] = $agleveldata3['commission3'];
                        } else {
                            $update_order['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
                        }
                    }
                }

                Log::write([
                    'file' => __FILE__ . __LINE__,
                    'fun' => __FUNCTION__,
                    "msg" => "拼团失败分销2",
                    "data" => $update_order
                ]);
                //更新订单分销信息
                if ($update_order) {
                    $update_order['iscommission'] = 1;
                    Db::name("lucky_collage_order")->where("aid", $aid)
                        ->where("id", $forder['id'])
                        ->update($update_order);
                }
                //更新佣金记录信息
                if ($forder['parent1'] && ($update_order['parent1commission'] || $update_order['parent1score'])) {
                    $l = Db::name('member_commission_record')
                        ->where("aid", $aid)
                        ->where("orderid", $forder['id'])
                        ->where("mid", $forder['parent1'])
                        ->where("ogid", $product['id'])
                        ->where('type', 'lucky_collage')
                        ->find();
                    if($l){
                        Db::name('member_commission_record')
                            ->where("aid", $aid)
                            ->where("orderid", $forder['id'])
                            ->where("mid", $forder['parent1'])
                            ->where("ogid", $product['id'])
                            ->where('type', 'lucky_collage')
                            ->save([
                                "commission" => $update_order['parent1commission'],
                                "score" => $update_order['parent1score'],
                            ]);
                    }else{
                        Db::name('member_commission_record')->insert([
                           'aid'=>$aid,
                           'orderid'=>$forder['id'],
                           'mid'=>$forder['parent1'],
                           'frommid'=>$forder['mid'],
                           'ogid'=>$product['id'],
                            'type'=>'lucky_collage',
                            'commission'=>$update_order['parent1commission'],
                            'score'=>$update_order['parent1score'],
                            'remark'=>'下级购买商品奖励',
                            'createtime'=>time(),
                            'status'=>0
                        ]);
                    }
                }
                if ($forder['parent2'] && ($update_order['parent2commission'] || $update_order['parent2score'])) {
                    $l = Db::name('member_commission_record')
                        ->where("aid", $aid)
                        ->where("orderid", $forder['id'])
                        ->where("mid", $forder['parent2'])
                        ->where("ogid", $product['id'])
                        ->where('type', 'lucky_collage')
                        ->find();
                    if($l){
                        Db::name('member_commission_record')
                            ->where("aid", $aid)
                            ->where("orderid", $forder['id'])
                            ->where("mid", $forder['parent2'])
                            ->where("ogid", $product['id'])
                            ->where('type', 'lucky_collage')
                            ->save([
                                "commission" => $update_order['parent2commission'],
                                "score" => $update_order['parent2score'],
                            ]);
                    }else{
                        Db::name('member_commission_record')->insert([
                            'aid'=>$aid,
                            'orderid'=>$forder['id'],
                            'mid'=>$forder['parent2'],
                            'frommid'=>$forder['mid'],
                            'ogid'=>$product['id'],
                            'type'=>'lucky_collage',
                            'commission'=>$update_order['parent2commission'],
                            'score'=>$update_order['parent2score'],
                            'remark'=>'下二级购买商品奖励',
                            'createtime'=>time(),
                            'status'=>0
                        ]);
                    }
                }
                if ($forder['parent3'] && ($update_order['parent3commission'] || $update_order['parent3score'])) {
                    $l = Db::name('member_commission_record')
                        ->where("aid", $aid)
                        ->where("orderid", $forder['id'])
                        ->where("mid", $forder['parent3'])
                        ->where("ogid", $product['id'])
                        ->where('type', 'lucky_collage')
                        ->find();
                    if($l){
                        Db::name('member_commission_record')
                            ->where("aid", $aid)
                            ->where("orderid", $forder['id'])
                            ->where("mid", $forder['parent3'])
                            ->where("ogid", $product['id'])
                            ->where('type', 'lucky_collage')
                            ->save([
                                "commission" => $update_order['parent3commission'],
                                "score" => $update_order['parent3score'],
                            ]);
                    }else{
                        Db::name('member_commission_record')->insert([
                            'aid'=>$aid,
                            'orderid'=>$forder['id'],
                            'mid'=>$forder['parent3'],
                            'frommid'=>$forder['mid'],
                            'ogid'=>$product['id'],
                            'type'=>'lucky_collage',
                            'commission'=>$update_order['parent3commission'],
                            'score'=>$update_order['parent3score'],
                            'remark'=>'下三级购买商品奖励',
                            'createtime'=>time(),
                            'status'=>0
                        ]);
                    }
                }
                //发放分销佣金
                $forder = Db::name("lucky_collage_order")->where("aid", $aid)->where("id", $forder['id'])->find();
                Log::write([
                    'file' => __FILE__ . __LINE__,
                    'fun' => __FUNCTION__,
                    "msg" => "拼团失败分销1",
                    "data" => $update_order
                ]);
                Order::giveCommission($forder, 'lucky_collage');
            }
        }

        if(getcustom('plug_luckycollage')){
            //取出团长的订单
            $teamorder = Db::name('lucky_collage_order')->where('teamid',$order['teamid'])->where('buytype',2)->where('aid',$order['aid'])->field('mid,isjiqiren')->find();
            if($teamorder && $teamorder['isjiqiren'] == 0){
                if($product['tzjl_type'] ==1 && $product['member_money'] > 0){  //1 奖励余额
                    $rs = \app\commons\Member::addmoney($aid,$teamorder['mid'],$product['member_money'],'发起拼团团长奖励'.t('余额'));
                }else if($product['tzjl_type'] ==2 && $product['leaderscore'] > 0){  //2 奖励积分
                    $rs =  \app\commons\Member::addscore($aid,$teamorder['mid'],$product['leaderscore'],'发起拼团团长奖励'.t('积分'));
                }else if($product['tzjl_type'] ==3 && $product['zstzcommission'] > 0){  //3 奖励佣金
                    $rs = \app\commons\Member::addcommission($aid,$teamorder['mid'],$teamorder['mid'],$product['zstzcommission'],'发起拼团团长奖励'.t('佣金'));
                }elseif($product['tzjl_type']==4 && $product['zstz_yhqids']){
                    $yhqids = explode(',',$product['zstz_yhqids']);
                    foreach($yhqids as $yhqid){
                        $rs = 	\app\commons\Coupon::send($aid,$teamorder['mid'],$yhqid);
                    }
                }
            }
        }

	}
	static function membertag_collage($mid,$teamid,$product){
        if(getcustom('member_tag')){
            $aid = $product['aid'];
            //查看参与该团的人不中奖人
            $tags = json_decode($product['tags'],true);
            $cylist = Db::name('lucky_collage_order')->where('aid',aid)->where('teamid',$teamid)->where('mid','<>',$mid)->where('status','=',1)->column('mid');
            $bzjcount=0;
            $protags = json_decode($product['tags'],true);
            foreach($cylist as $c){
                $member = Db::name('member')->field('id,tags')->where(['id'=>$c])->find();

                if($protags['membertags']==-1){
                    $zjdata = Db::name('lucky_collage_order')->field('id')->where('mid','=',$c)->where('iszj','=',1)->where('proid',$product['id'])->order('id desc')->find();
                    if($zjdata){
                        $wzjcount = 0+Db::name('lucky_collage_order')->where('id','>',$zjdata['id'])->where('mid','=',$c)->where('iszj','=',2)->where('proid',$product['id'])->count();
                        if($wzjcount<$tags['tagbzjnum']){
                            $bzjcount++;
                        }
                    }
                }elseif($protags['membertags']>0 && $member['tags']){
                    $memtags = explode(',',$member['tags']);
                    if(in_array($protags['membertags'],$memtags)){	//查看是不是在标签内
                        $zjdata = Db::name('lucky_collage_order')->field('id')->where('mid','=',$member['id'])->where('iszj','=',1)->where('proid',$product['id'])->order('id desc')->find();
                        if($zjdata){
                            $wzjcount = 0+Db::name('lucky_collage_order')->where('id','>',$zjdata['id'])->where('mid','=',$member['id'])->where('iszj','=',2)->where('proid',$product['id'])->count();
                            if($wzjcount<$tags['tagbzjnum']){
                                $bzjcount++;
                            }
                        }
                    }
                }
            }


            if($bzjcount>=($product['teamnum']-$product['gua_num'])){
                //查看参与人数是否有中奖条件
                $zjdata = Db::name('lucky_collage_order')->field('id')->where('mid','=',$mid)->where('iszj','=',1)->where('proid',$product['id'])->order('id desc')->find();
                if($zjdata['id']){
                    //查看大于次订单未中奖的数量
                    $wzjcount2 = 0 + Db::name('lucky_collage_order')->where('id','>',$zjdata['id'])->where('mid','=',$mid)->where('iszj','=',2)->where('proid',$product['id'])->count();
                    if($wzjcount2<$tags['tagbzjnum']){
                        return ['status'=>0,'msg'=>'暂没有参加条件，请稍后再试'];
                    }
                }
            }
        }
	}
}