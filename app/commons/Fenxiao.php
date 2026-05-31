<?php


namespace app\commons;
use think\facade\Db;
class Fenxiao
{
    public static function fenxiao($sysset,$member,$product,$num,$commission_totalprice,$isfg=0,$istc1=0,$istc2=0,$istc3=0){
        $aid = $sysset['aid'];
        $ogupdate = [];
        if($product['commissionset']!=-1){
            //开启紧缩，团队分红极差作为开关
            if(getcustom('commission_jinsuo') && $sysset['fx_jinsuo']==1){
                //var_dump($member['path']);
                if($member['path']){
                    $parentList = Db::name('member')->where('id','in',$member['path'])->order(Db::raw('field(id,'.$member['path'].')'))->select()->toArray();
                    if($parentList){
                        $parentList = array_reverse($parentList);
                        $level_lists = Db::name('member_level')->where('aid',$aid)->column('*','id');
                        $parent_arr = [];
                        $agleveldata_arr = [];
                        //循环推荐网体，按代数查找提成比例大于0的上级，提成为0的紧缩掉不算一代
                        $dai = 1;
                        foreach($parentList as $k=>$parent){
                            if($dai>3){
                                break;
                            }
                            $level_data = $level_lists[$parent['levelid']]??[];
                            //没级别 紧缩掉
                            if(!$level_data){
                                //var_dump($parent['id'].'紧缩1');
                                continue;
                            }
                            //未开启分销权限，或下单会员不在指定等级ID中紧缩掉
                            if($level_data['can_agent']==0 || ($level_data['commission_appointlevelid'] && !in_array($member['levelid'],explode(',',$level_data['commission_appointlevelid'])))){
                                //var_dump($parent['id'].'紧缩2');
                                continue;
                            }
                            //提成比例为0的紧缩掉(应客户要求，只按一代比例做判断)
                            //$commission_dai = $level_data['commission'.$dai];//(此处为按会员当前所处层级的拿奖比例做判断)
                            $commission_dai = $level_data['commission1'];
                            $commission_socre = 0;
                            if(getcustom('maidan_commission_score')){
                                $commission_socre = $level_data['maidan_commission_score1'];
                            }

                            if($commission_dai<=0 && $commission_socre<=0){
                                //var_dump($parent['id'].'紧缩3');
                                continue;
                            }

                            $parent_arr[$dai] = $parent;
                            $agleveldata_arr[$dai] = $level_data;
                            $dai++;
                        }
                        //上一代会员
                        $parent1 = $parent_arr[1]??'';
                        $agleveldata1 = $agleveldata_arr[1]??'';
                        $ogupdate['parent1'] = $parent1['id']??0;
                        //上二代会员
                        $parent2 = $parent_arr[2]??'';
                        $agleveldata2 = $agleveldata_arr[2]??'';
                        $ogupdate['parent2'] = $parent2['id']??0;
                        //上三代会员
                        $parent3 = $parent_arr[3]??'';
                        $agleveldata3 = $agleveldata_arr[3]??'';
                        $ogupdate['parent3'] = $parent3['id']??0;

                    }
                }
            }else{
                if($member['pid']){
                    $parent1 = Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->find();
                    if($parent1){
                        $agleveldata1 = Db::name('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
                        if($agleveldata1['can_agent']!=0 && (!$agleveldata1['commission_appointlevelid'] || in_array($member['levelid'],explode(',',$agleveldata1['commission_appointlevelid'])))){
                            $ogupdate['parent1'] = $parent1['id'];
                        }
                    }
                }
                if($parent1['pid']){
                    $parent2 = Db::name('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
                    if($parent2){
                        $agleveldata2 = Db::name('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
                        if(($agleveldata2['can_agent']>1 || $agleveldata2['commission_parent']>0) && (!$agleveldata2['commission_appointlevelid'] || in_array($member['levelid'],explode(',',$agleveldata2['commission_appointlevelid'])))){
                            $ogupdate['parent2'] = $parent2['id'];
                        }
                    }
                }
                if($parent2['pid']){
                    $parent3 = Db::name('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
                    if($parent3){
                        $agleveldata3 = Db::name('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
                        if(($agleveldata3['can_agent']>2 || $agleveldata3['commission_parent']>0) && (!$agleveldata3['commission_appointlevelid'] || in_array($member['levelid'],explode(',',$agleveldata3['commission_appointlevelid'])))){
                            $ogupdate['parent3'] = $parent3['id'];
                        }
                    }
                }
                if(getcustom('agent_to_origin')){
                    //一级分销发放给原推荐人
                    $member_level = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
                    if($member['pid_origin']>0 && $member_level['agent_to_origin']==1){
                        $parent1 = Db::name('member')->where('aid',$aid)->where('id',$member['pid_origin'])->find();
                        if($parent1){
                            $agleveldata1 = Db::name('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
                            if($agleveldata1['can_agent']!=0 && (!$agleveldata1['commission_appointlevelid'] || in_array($member['levelid'],explode(',',$agleveldata1['commission_appointlevelid'])))){
                                $ogupdate['parent1'] = $parent1['id'];
                            }
                        }
                    }
                }
            }

            if($parent3['pid']){
                $parent4 = Db::name('member')->where('aid',$aid)->where('id',$parent3['pid'])->find();
                if($parent4){
                    $agleveldata4 = Db::name('member_level')->where('aid',$aid)->where('id',$parent4['levelid'])->find();
                    if($product['commissionpingjiset'] != 0){
                        if($product['commissionpingjiset'] == 1){
                            $commissionpingjidata1 = json_decode($product['commissionpingjidata1'],true);
                            $agleveldata4['commission_parent_pj'] = $commissionpingjidata1[$agleveldata4['id']];
                        }elseif($product['commissionpingjiset'] == 2){
                            $commissionpingjidata2 = json_decode($product['commissionpingjidata2'],true);
                            $agleveldata4['commission_parent_pj'] = $commissionpingjidata2[$agleveldata4['id']];
                        }else{
                            $agleveldata4['commission_parent_pj'] = 0;
                        }
                    }
                    //持续推荐奖励
                    if($agleveldata4['can_agent'] > 0 && ($agleveldata4['commission_parent'] > 0 || ($parent4['levelid']==$parent3['levelid'] && $agleveldata4['commission_parent_pj'] > 0))){
                        $ogupdate['parent4'] = $parent4['id'];
                    }
                }
            }
            if($product['commissionset']==1){//按商品设置的分销比例
                $commissiondata = json_decode($product['commissiondata1'],true);
                if($commissiondata){
                    if($agleveldata1) $ogupdate['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
                    if($agleveldata2) $ogupdate['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
                    if($agleveldata3) $ogupdate['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
                    if(getcustom('commission_butie')){
                        $commissionbutie = json_decode($product['commissionbutie2'],true);
                        if($agleveldata1) $ogupdate['parent1commission_butie'] = $commissionbutie[$agleveldata1['id']]['commission1'] * $ogupdate['parent1commission'] * 0.01;
                        if($agleveldata2) $ogupdate['parent2commission_butie'] = $commissionbutie[$agleveldata2['id']]['commission2'] * $ogupdate['parent2commission'] * 0.01;
                        if($agleveldata3) $ogupdate['parent3commission_butie'] = $commissionbutie[$agleveldata3['id']]['commission3'] * $ogupdate['parent3commission'] * 0.01;
                    }
                }
            }elseif($product['commissionset']==2){//按固定金额
                $commissiondata = json_decode($product['commissiondata2'],true);
                if($commissiondata){
                    if(getcustom('fengdanjiangli') && $product['fengdanjiangli']){

                    }else{
                        if($agleveldata1) $ogupdate['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
                        if($agleveldata2) $ogupdate['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
                        if($agleveldata3) $ogupdate['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
                        if(getcustom('commission_butie')){
                            $commissionbutie = json_decode($product['commissionbutie'],true);
                            if($agleveldata1) $ogupdate['parent1commission_butie'] = $commissionbutie[$agleveldata1['id']]['commission1'];
                            if($agleveldata2) $ogupdate['parent2commission_butie'] = $commissionbutie[$agleveldata2['id']]['commission2'];
                            if($agleveldata3) $ogupdate['parent3commission_butie'] = $commissionbutie[$agleveldata3['id']]['commission3'];
                        }
                    }
                }
            }elseif($product['commissionset']==3){//提成是积分
                $commissiondata = json_decode($product['commissiondata3'],true);
                if($commissiondata){
                    if($agleveldata1) $ogupdate['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
                    if($agleveldata2) $ogupdate['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
                    if($agleveldata3) $ogupdate['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
                }
            }elseif($product['commissionset']==5){//提成比例+积分
                $commissiondata = json_decode($product['commissiondata1'],true);
                if($commissiondata){
                    if($agleveldata1) $ogupdate['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
                    if($agleveldata2) $ogupdate['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
                    if($agleveldata3) $ogupdate['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
                    if(getcustom('commission_butie')){
                        $commissionbutie = json_decode($product['commissionbutie2'],true);
                        if($agleveldata1) $ogupdate['parent1commission_butie'] = $commissionbutie[$agleveldata1['id']]['commission1'] * $ogupdate['parent1commission'] * 0.01;
                        if($agleveldata2) $ogupdate['parent2commission_butie'] = $commissionbutie[$agleveldata2['id']]['commission2'] * $ogupdate['parent2commission'] * 0.01;
                        if($agleveldata3) $ogupdate['parent3commission_butie'] = $commissionbutie[$agleveldata3['id']]['commission3'] * $ogupdate['parent3commission'] * 0.01;
                    }
                }
                $commissiondata = json_decode($product['commissiondata3'],true);
                if($commissiondata){
                    if($agleveldata1) $ogupdate['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
                    if($agleveldata2) $ogupdate['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
                    if($agleveldata3) $ogupdate['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
                }
            }elseif($product['commissionset']==6){//提成金额+积分
                // $commissiondata = json_decode($product['commissiondata2'],true);
                // if($commissiondata){
                //     if(getcustom('fengdanjiangli') && $product['fengdanjiangli']){

                //     }else{
                //         if($agleveldata1) $ogupdate['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
                //         if($agleveldata2) $ogupdate['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
                //         if($agleveldata3) $ogupdate['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
                //         if(getcustom('commission_butie')){
                //             $commissionbutie = json_decode($product['commissionbutie'],true);
                //             if($agleveldata1) $ogupdate['parent1commission_butie'] = $commissionbutie[$agleveldata1['id']]['commission1'];
                //             if($agleveldata2) $ogupdate['parent2commission_butie'] = $commissionbutie[$agleveldata2['id']]['commission2'];
                //             if($agleveldata3) $ogupdate['parent3commission_butie'] = $commissionbutie[$agleveldata3['id']]['commission3'];
                //         }
                //     }
                // }
                // $commissiondata = json_decode($product['commissiondata3'],true);
                // if($commissiondata){
                //     if($agleveldata1) $ogupdate['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
                //     if($agleveldata2) $ogupdate['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
                //     if($agleveldata3) $ogupdate['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
                // }
            }elseif($product['commissionset']==7){//提成积分比例
                // $commissiondata = json_decode($product['commissiondata4'],true);
                // if($commissiondata){
                //     if($agleveldata1) $ogupdate['parent1score'] = round($commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01);
                //     if($agleveldata2) $ogupdate['parent2score'] = round($commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01);
                //     if($agleveldata3) $ogupdate['parent3score'] = round($commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01);
                // }
            }else{ //按会员等级设置的分销比例
                if ($agleveldata1) {
                    if (getcustom('plug_ttdz') && $isfg == 1) {
                        $agleveldata1['commission1'] = $agleveldata1['commission4'];
                    }
                    if ($agleveldata1['commissiontype'] == 1) { //固定金额按单
                        if ($istc1 == 0) {
                            $ogupdate['parent1commission'] = $agleveldata1['commission1'];
                            $istc1 = 1;
                        }
                    } else {
                        $ogupdate['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
                    }
                }
                if ($agleveldata2) {
                    if (getcustom('plug_ttdz') && $isfg == 1) {
                        $agleveldata2['commission2'] = $agleveldata2['commission5'];
                    }
                    if ($agleveldata2['commissiontype'] == 1) {
                        if ($istc2 == 0) {
                            $ogupdate['parent2commission'] = $agleveldata2['can_agent']>1?$agleveldata2['commission2']:0;
                            $istc2 = 1;
                        }
                    } else {
                        $ogupdate['parent2commission'] = $agleveldata2['can_agent']>1?($agleveldata2['commission2'] * $commission_totalprice * 0.01):0;
                    }

                }
                if ($agleveldata3) {
                    if (getcustom('plug_ttdz') && $isfg == 1) {
                        $agleveldata3['commission3'] = $agleveldata3['commission6'];
                    }

                    if ($agleveldata3['commissiontype'] == 1) {
                        if ($istc3 == 0) {
                            $ogupdate['parent3commission'] = $agleveldata3['can_agent']>2?$agleveldata3['commission3']:0;
                            $istc3 = 1;
                        }
                    } else {
                        $ogupdate['parent3commission'] = $agleveldata3['can_agent']>2?($agleveldata3['commission3'] * $commission_totalprice * 0.01):0;
                    }
                }

            }
            //级差
            if((($product['fx_differential'] == -1 && $sysset['fx_differential'] == 1) || $product['fx_differential'] == 1)  && in_array($product['commissionset'],[0,1,2,5,6])){
                if($ogupdate['parent2commission'] > 0) {
                    $cha2_1 = $ogupdate['parent2commission'] - $ogupdate['parent1commission'];
                    $ogupdate['parent2commission'] = $cha2_1 > 0 ? $cha2_1 : 0;
                }
                if($ogupdate['parent3commission'] > 0) {
                    $cha3_1 = $ogupdate['parent3commission'] - $ogupdate['parent2commission'] - $ogupdate['parent1commission'];
                    $ogupdate['parent3commission'] = $cha3_1 > 0 ? $cha3_1 : 0;
                }
                if(getcustom('commission_butie')){
                    //分销补贴也跟随级差
                    if($ogupdate['parent2commission_butie'] > 0) {
                        $cha2_1_butie = $ogupdate['parent2commission_butie'] - $ogupdate['parent1commission_butie'];
                        $ogupdate['parent2commission_butie'] = $cha2_1_butie > 0 ? $cha2_1_butie : 0;
                    }
                    if($ogupdate['parent3commission_butie'] > 0) {
                        $cha3_1_butie = $ogupdate['parent3commission_butie'] - $ogupdate['parent2commission_butie'] - $ogupdate['parent1commission_butie'];
                        $ogupdate['parent3commission_butie'] = $cha3_1_butie > 0 ? $cha3_1_butie : 0;
                    }
                }
            }
            //计算完级差之后再计算持续推荐奖
            if($product['commissionset']==0){
                if ($agleveldata2) {
                    if ($agleveldata2['commissiontype'] == 1) {
                        //持续推荐奖励
                        if ($agleveldata2['commission_parent'] > 0 && $ogupdate['parent1']) {
                            //持续推荐奖单独放一个参数，再上一级计算持续推荐奖时只用下级的分销提成
                            $ogupdate['parent2commission_parent'] = $agleveldata2['commission_parent'];
                        }
                    } else {
                        //持续推荐奖励
                        if ($agleveldata2['commission_parent'] > 0 && $ogupdate['parent1commission'] > 0 && $ogupdate['parent1']) {
                            //持续推荐奖单独放一个参数，再上一级计算持续推荐奖时只用下级的分销提成
                            $ogupdate['parent2commission_parent'] = $ogupdate['parent1commission'] * $agleveldata2['commission_parent'] * 0.01;
                        }
                    }

                }
                if ($agleveldata3) {
                    if ($agleveldata3['commissiontype'] == 1) {
                        //持续推荐奖励
                        if ($agleveldata3['commission_parent'] > 0 && $ogupdate['parent2']) {
                            $ogupdate['parent3commission_parent'] = $agleveldata3['commission_parent'];
                        }
                    } else {
                        //持续推荐奖励
                        if ($agleveldata3['commission_parent'] > 0 && $ogupdate['parent2commission'] > 0 && $ogupdate['parent2']) {
                            $ogupdate['parent3commission_parent'] = $ogupdate['parent2commission'] * $agleveldata3['commission_parent'] * 0.01;
                        }
                    }
                }
                //持续推荐奖励
                if ($agleveldata4['commission_parent'] > 0 && $ogupdate['parent3']) {
                    if ($agleveldata3['commissiontype'] == 1) {
                        $ogupdate['parent4commission'] = $agleveldata4['commission_parent'];
                    } else {
                        $ogupdate['parent4commission'] = $ogupdate['parent3commission'] * $agleveldata4['commission_parent'] * 0.01;
                    }
                }
            }

            //平级奖
            if(getcustom('commission_parent_pj') && !getcustom('commission_parent_pj_stop')){
                if($agleveldata4 && $ogupdate['parent3'] && $ogupdate['parent3commission'] > 0 && $agleveldata3['id'] == $agleveldata4['id']){
                    $agleveldata4['commissionpingjitype'] = $agleveldata4['commissiontype'];
                    if($product['commissionpingjiset'] != 0){
                        if($product['commissionpingjiset'] == 1){
                            $commissionpingjidata1 = json_decode($product['commissionpingjidata1'],true);
                            $agleveldata4['commission_parent_pj'] = $commissionpingjidata1[$agleveldata4['id']]['commission'];
                        }elseif($product['commissionpingjiset'] == 2){
                            $commissionpingjidata2 = json_decode($product['commissionpingjidata2'],true);
                            $agleveldata4['commission_parent_pj'] = $commissionpingjidata2[$agleveldata4['id']]['commission'];
                            $agleveldata4['commissionpingjitype'] = 1;
                        }else{
                            $agleveldata4['commission_parent_pj'] = 0;
                        }
                    }
                    if($agleveldata4['commission_parent_pj'] > 0) {
                        if($agleveldata4['commissionpingjitype']==0){
                            $ogupdate['parent4commission'] = $ogupdate['parent3commission'] * $agleveldata4['commission_parent_pj'] * 0.01;
                        } else {
                            $ogupdate['parent4commission'] = $agleveldata4['commission_parent_pj'];
                        }
                        $ogupdate['parent4'] = $parent4['id'];
                    }
                }
                if($agleveldata3 && $ogupdate['parent2'] && $ogupdate['parent2commission'] > 0 && $agleveldata2['id'] == $agleveldata3['id']){
                    $agleveldata3['commissionpingjitype'] = $agleveldata3['commissiontype'];
                    if($product['commissionpingjiset'] != 0){
                        if($product['commissionpingjiset'] == 1){
                            $commissionpingjidata1 = json_decode($product['commissionpingjidata1'],true);
                            $agleveldata3['commission_parent_pj'] = $commissionpingjidata1[$agleveldata3['id']]['commission'];
                        }elseif($product['commissionpingjiset'] == 2){
                            $commissionpingjidata2 = json_decode($product['commissionpingjidata2'],true);
                            $agleveldata3['commission_parent_pj'] = $commissionpingjidata2[$agleveldata3['id']]['commission'];
                            $agleveldata3['commissionpingjitype'] = 1;
                        }else{
                            $agleveldata3['commission_parent_pj'] = 0;
                        }
                    }
                    if($agleveldata3['commission_parent_pj'] > 0){
                        if(!$ogupdate['parent3']){
                            $ogupdate['parent3commission'] = 0;
                            $ogupdate['parent3'] = $parent3['id'];
                        }
                        if($agleveldata3['commissionpingjitype'] == 0){
                            $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $ogupdate['parent2commission'] * $agleveldata3['commission_parent_pj'] * 0.01;
                        }else{
                            $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $agleveldata3['commission_parent_pj'];
                        }
                    }
                }
                if($agleveldata2 && $ogupdate['parent1'] && $ogupdate['parent1commission'] > 0 && $agleveldata1['id'] == $agleveldata2['id']){
                    $agleveldata2['commissionpingjitype'] = $agleveldata2['commissiontype'];
                    if($product['commissionpingjiset'] != 0){
                        if($product['commissionpingjiset'] == 1){
                            $commissionpingjidata1 = json_decode($product['commissionpingjidata1'],true);
                            $agleveldata2['commission_parent_pj'] = $commissionpingjidata1[$agleveldata2['id']]['commission'];
                        }elseif($product['commissionpingjiset'] == 2){
                            $commissionpingjidata2 = json_decode($product['commissionpingjidata2'],true);
                            $agleveldata2['commission_parent_pj'] = $commissionpingjidata2[$agleveldata2['id']]['commission'];
                            $agleveldata2['commissionpingjitype'] = 1;
                        }else{
                            $agleveldata2['commission_parent_pj'] = 0;
                        }
                    }
                    if($agleveldata2['commission_parent_pj'] > 0){
                        if(!$ogupdate['parent2']){
                            $ogupdate['parent2commission'] = 0;
                            $ogupdate['parent2'] = $parent2['id'];
                        }
                        if($agleveldata2['commissionpingjitype'] == 0){
                            $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $ogupdate['parent1commission'] * $agleveldata2['commission_parent_pj'] * 0.01;
                        }else{
                            $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $agleveldata2['commission_parent_pj'];
                        }
                    }
                }
            }
            /******************************平级后只拿平级奖，不再向上发放分销奖金 start **************************************/
            $commission_parent_pj_stop_product = getcustom('commission_parent_pj_stop_product');//产品单独设置平级奖参数
            if(getcustom('commission_parent_pj_stop') && $member['path']){
                //重新计算平级奖
                $pids = $member['path'];
                $parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
                $parentList = array_reverse($parentList);
                $last_level_data = Db::name('member_level')->where('id',$member['levelid'])->find();
                $level_arr = Db::name('member_level')->where('aid',$sysset['aid'])->column('*','id');
                //产品单独设置平级奖参数
                if($commission_parent_pj_stop_product){
                    if($product['commission_parent_pj_status']==1){
                        //单独设置
                        foreach($level_arr as $k=>$v){
                            $level_arr[$k]['commission_parent_pj_status'] = 1;
                            $level_arr[$k]['commission_parent_pj_lv'] = $product['commission_parent_pj_lv'];
                            $level_arr[$k]['commission_parent_pj'] = $product['commission_parent_pj']*$num;
                            $level_arr[$k]['commission_parent_pj_order'] = $product['commission_parent_pj_order'];
                        }
                    }elseif ($product['commission_parent_pj_status']==-1){
                        //关闭平级奖
                        foreach($level_arr as $k=>$v){
                            $level_arr[$k]['commission_parent_pj_status'] = 0;
                        }
                    }
                }
                $i = 0;
                $pjlevelids = [];//已拿平级奖的会员
                foreach($parentList as $parentpj){
                    $i++;
                    if($i==1){
                        $commission_field = 'parent1commission';
                        $parent_field = 'parent1';
                    }elseif($i==2){
                        $commission_field = 'parent2commission';
                        $parent_field = 'parent2';
                    }elseif($i==3){
                        $commission_field = 'parent3commission';
                        $parent_field = 'parent3';
                    }else{
                        $commission_field = 'parent4commission';
                        $parent_field = 'parent4';
                    }
                    $level_data = $level_arr[$parentpj['levelid']];
                    if($level_data['commission_parent_pj_lv']>0 && $level_data['commission_parent_pj_lv']<$i){
                        //超出层级限制
                        if($level_data['id']==$last_level_data['id']){
                            $ogupdate[$commission_field] = 0;
                        }
                        //超出层级限制，跳过
                        continue;
                    }
//                    dump($i.'=>'.$parentpj['id'].'开始,级别'.$last_level_data['id'].'=>'.$level_data['id']);
                    if($level_data['commission_parent_pj_status']==1 && !in_array($level_data['id'],$pjlevelids)){
                        if($level_data['id']==$last_level_data['id']){
                            $pj_bonus = $level_data['commission_parent_pj'];//固定金额
                            $pj_bonus_order = bcmul($level_data['commission_parent_pj_order']/100,$commission_totalprice,2);
                            $pj_bonus = bcadd($pj_bonus,$pj_bonus_order,2);
                            $ogupdate[$commission_field] = $pj_bonus;
                            $ogupdate[$parent_field] = $parentpj['id'];
                            $pjlevelids = [$level_data['id']];
                            if($i>=4){
                                //平级奖只发最近的一个
                                break;
                            }
                        }
                        if($level_data['sort']<$last_level_data['sort']){
                            //设置了平级奖，如果上级会员级别小于下级，那么不发放分销佣金
                            $ogupdate[$commission_field] = 0;
                            continue;
                        }
                    }
                    if($i<4){
                        $last_level_data = $level_data;
                    }
                }
            }
            /******************************平级后只拿平级奖，不再向上发放分销奖金 stop **************************************/
//                    dump($ogupdate);exit;
        }
        if(isset($ogupdate['parent2commission_parent'])){
            $ogupdate['parent2commission'] = bcadd($ogupdate['parent2commission'],$ogupdate['parent2commission_parent'],2);
            $ogupdate['parent2commission_parent'] = 0;
        }
        if(isset($ogupdate['parent3commission_parent'])){
            $ogupdate['parent3commission'] = bcadd($ogupdate['parent3commission'],$ogupdate['parent3commission_parent'],2);
            $ogupdate['parent3commission_parent'] = 0;
        }
        if(getcustom('commission_max_times')){
            //分销份数限制
            if($product['commission_max_times_status']==1){
                $commission_max_times = json_decode($product['commission_max_times'],true);
                if($ogupdate['parent1'] && $agleveldata1){
                   $max_times1 = $commission_max_times[$agleveldata1['id']]['commission1']??0;
                   if($max_times1>0){
                       $where = [];
                       $where[] = ['mid','=',$ogupdate['parent1']];
                       $where[] = ['proid','=',$product['id']];
                       $where[] = ['level','=',1];
                       $where[] = ['status','in',[0,1]];
                       $have_count = Db::name('member_commission_record')->where($where)->count();
                       if($have_count>=$max_times1){
                           $ogupdate['parent1'] = 0;
                           $ogupdate['parent1commission'] = 0;
                           $ogupdate['parent1score'] = 0;
                       }
                   }
                }
                if($ogupdate['parent2'] && $agleveldata2){
                    $max_times2 = $commission_max_times[$agleveldata2['id']]['commission2']??0;
                    if($max_times2>0){
                        $where = [];
                        $where[] = ['mid','=',$ogupdate['parent2']];
                        $where[] = ['proid','=',$product['id']];
                        $where[] = ['level','=',2];
                        $where[] = ['status','in',[0,1]];
                        $have_count = Db::name('member_commission_record')->where($where)->count();
                        if($have_count>=$max_times2){
                            $ogupdate['parent2'] = 0;
                            $ogupdate['parent2commission'] = 0;
                            $ogupdate['parent2score'] = 0;
                        }
                    }
                }
            }
        }
        //分销伯乐奖
        if(getcustom('commission_bole')){
            $bole_parent2 = [];
            $bole_parent3 = [];
            $bole_parent4 = [];
            if($parent1 && $agleveldata1){
                if($agleveldata1['commission_bole_origin'] && $parent1['pid_origin']){
                    $bole_parent2 = Db::name('member')->where('aid',$aid)->where('id',$parent1['pid_origin'])->find();
                }else{
                    $bole_parent2 = Db::name('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
                }
                $bole_agleveldata2 = Db::name('member_level')->where('id',$bole_parent2['levelid'])->find();
            }
            if($parent2 && $agleveldata2){
                $parent2 = Db::name('member')->where('aid',$aid)->where('id',$ogupdate['parent2'])->find();
                if($agleveldata2['commission_bole_origin'] && $parent2['pid_origin']){
                    $bole_parent3 = Db::name('member')->where('aid',$aid)->where('id',$parent2['pid_origin'])->find();
                }else{
                    $bole_parent3 = Db::name('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
                }
                $bole_agleveldata3 = Db::name('member_level')->where('id',$bole_parent3['levelid'])->find();
            }
            if($parent3 && $agleveldata3){
                if($agleveldata3['commission_bole_origin'] && $parent3['pid_origin']){
                    $bole_parent4 = Db::name('member')->where('aid',$aid)->where('id',$parent3['pid_origin'])->find();
                }else{
                    $bole_parent4 = Db::name('member')->where('aid',$aid)->where('id',$parent3['pid'])->find();
                }
                $bole_agleveldata4 = Db::name('member_level')->where('id',$bole_parent4['levelid'])->find();
            }
            if($product['commissionboleset']==1){//按商品设置的分销比例
                $commissionboledata1 = json_decode($product['commissionboledata1'],true);
                if($commissionboledata1){
                    if($bole_agleveldata2){
                        $bole_agleveldata2['giveup_percent'] = $commissionboledata1[$bole_agleveldata2['id']]['commission']??0;
                        $bole_agleveldata2['giveup_commission'] = 0;
                    }
                    if($bole_agleveldata3){
                        $bole_agleveldata3['giveup_percent'] = $commissionboledata1[$bole_agleveldata3['id']]['commission']??0;
                        $bole_agleveldata3['giveup_commission'] = 0;
                    }
                    if($bole_agleveldata4){
                        $bole_agleveldata4['giveup_percent'] = $commissionboledata1[$bole_agleveldata4['id']]['commission']??0;
                        $bole_agleveldata4['giveup_commission'] = 0;
                    }
                }
            }elseif($product['commissionboleset']==2){
                $commissionboledata2 = json_decode($product['commissionboledata2'],true);
                if($commissionboledata2){
                    if($bole_agleveldata2) {
                        $bole_agleveldata2['giveup_percent'] = 0;
                        $bole_agleveldata2['giveup_commission'] = $commissionboledata2[$bole_agleveldata2['id']]['commission']??0;
                    }
                    if($bole_agleveldata3){
                        $bole_agleveldata3['giveup_percent'] = 0;
                        $bole_agleveldata3['giveup_commission'] = $commissionboledata2[$bole_agleveldata3['id']]['commission']??0;
                    }
                    if($bole_agleveldata4){
                        $bole_agleveldata4['giveup_percent'] = 0;
                        $bole_agleveldata4['giveup_commission'] = $commissionboledata2[$bole_agleveldata4['id']]['commission']??0;
                    }
                }
            }
            $commossionbole2 = 0;
            $commossionbole3 = 0;
            $commossionbole4 = 0;
            if($product['commissionset']!=-1) {
                if ($bole_agleveldata2 && $bole_parent2) {
                    $commossionbole2 = $bole_agleveldata2['giveup_commission'];
                    if ($bole_agleveldata2['giveup_percent'] > 0 && $ogupdate['parent1commission'] > 0) {
                        $commossionbole2 = $commossionbole2 + $ogupdate['parent1commission'] * $bole_agleveldata2['giveup_percent'] * 0.01;
                    }
                }
                if ($bole_agleveldata3 && $bole_parent3) {
                    $commossionbole3 = $bole_agleveldata3['giveup_commission'];
                    if ($bole_agleveldata3['giveup_percent'] > 0 && $ogupdate['parent2commission'] > 0 ) {
                        $commossionbole3 = $commossionbole3 + $ogupdate['parent2commission'] * $bole_agleveldata3['giveup_percent'] * 0.01;
                    }
                }
                if ($bole_agleveldata4 > 0 && $bole_parent4) {
                    $commossionbole4 = $bole_agleveldata4['giveup_commission'];
                    if ($bole_agleveldata4['giveup_percent'] > 0 && $ogupdate['parent3commission'] > 0 ) {
                        $commossionbole4 = $commossionbole4 + $ogupdate['parent3commission'] * $bole_agleveldata4['giveup_percent'] * 0.01;
                    }
                }
            }
            $ogupdate['parent2_bole'] = $bole_parent2['id']??0;
            $ogupdate['parent3_bole'] = $bole_parent3['id']??0;
            $ogupdate['parent4_bole'] = $bole_parent4['id']??0;
            $ogupdate['parent2commission_bole'] = $commossionbole2??0;
            $ogupdate['parent3commission_bole'] = $commossionbole3??0;
            $ogupdate['parent4commission_bole'] = $commossionbole4??0;
        }
        $ogupdate['istc1'] = $istc1;
        $ogupdate['istc2'] = $istc2;
        $ogupdate['istc3'] = $istc3;
        return $ogupdate;
    }

    public static function fenxiao_jicha($sysset,$member,$product,$num,$commission_totalprice){
        if(getcustom('fenxiao_manage')){
            $aid = $sysset['aid'];
        $fenxiao_set = Db::name('fenxiao_manage')->where('aid',$aid)->column('*','key');
        $commission1_bili = 0;
        $commission2_bili = 0;
        $commission3_bili = 0;
        $ogupdate = [];
        if($member['pid']){
            $parent1 = Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->find();
            if($parent1){
                $agleveldata1 = Db::name('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
                if($agleveldata1['can_agent']!=0 ){
                    $ogupdate['parent1'] = $parent1['id'];
                    $set_key = $parent1['levelid'].','.$member['levelid'];
                    $commission1_bili = $fenxiao_set[$set_key]['commission1'];
                    //dump($set_key.'=>'.$commission1_bili);
                }
            }
        }
        if($parent1['pid']){
            $parent2 = Db::name('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
            if($parent2){
                $agleveldata2 = Db::name('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
                if($agleveldata2['can_agent']>1){
                    $ogupdate['parent2'] = $parent2['id'];
                    $set_key = $parent2['levelid'].','.$member['levelid'];
                    $commission2_bili = $fenxiao_set[$set_key]['commission2'];
                    //dump($set_key.'=>'.$commission2_bili);
                }
            }
        }
        if($parent2['pid']){
            $parent3 = Db::name('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
            if($parent3){
                $agleveldata3 = Db::name('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
                if($agleveldata3['can_agent']>2 ){
                    $ogupdate['parent3'] = $parent3['id'];
                    $set_key = $parent3['levelid'].','.$member['levelid'];
                    $commission3_bili = $fenxiao_set[$set_key]['commission3'];
                    //dump($set_key.'=>'.$commission3_bili);
                }
            }
        }
        if($parent3['pid']){
            $parent4 = Db::name('member')->where('aid',$aid)->where('id',$parent3['pid'])->find();
            if($parent4){
                $agleveldata4 = Db::name('member_level')->where('aid',$aid)->where('id',$parent4['levelid'])->find();
                if($product['commissionpingjiset'] != 0){
                    if($product['commissionpingjiset'] == 1){
                        $commissionpingjidata1 = json_decode($product['commissionpingjidata1'],true);
                        $agleveldata4['commission_parent_pj'] = $commissionpingjidata1[$agleveldata4['id']];
                    }elseif($product['commissionpingjiset'] == 2){
                        $commissionpingjidata2 = json_decode($product['commissionpingjidata2'],true);
                        $agleveldata4['commission_parent_pj'] = $commissionpingjidata2[$agleveldata4['id']];
                    }else{
                        $agleveldata4['commission_parent_pj'] = 0;
                    }
                }
                //持续推荐奖励
                if($agleveldata4['can_agent'] > 0){
                    $ogupdate['parent4'] = $parent4['id'];
                }
            }
        }
        $last_commission_bili = 0;//记录上一次分销奖金比例，计算级差使用
        //按会员等级设置的分销比例
        if ($agleveldata1) {
            $commission_bili = bcsub($commission1_bili,$last_commission_bili,2);
            $last_commission_bili = $commission_bili;
            //dump('parent1commission'.'=>'.$commission_bili);
            $ogupdate['parent1commission'] = bcmul($commission_bili/100,$commission_totalprice,2);
        }
        if ($agleveldata2) {
            $commission_bili = bcsub($commission2_bili,$last_commission_bili,2);
            if($commission_bili<0){
                $commission_bili = 0;
            }else{
                $last_commission_bili = $commission2_bili;
            }
            //dump('parent2commission'.'=>'.$commission2_bili.'=>'.$commission_bili);
            $ogupdate['parent2commission'] = bcmul($commission_bili/100,$commission_totalprice,2);

        }
        if ($agleveldata3) {
            $commission_bili = bcsub($commission3_bili,$last_commission_bili,2);
            if($commission_bili<0){
                $commission_bili = 0;
            }else{
                $last_commission_bili = $commission3_bili;
            }
            //dump('parent2commission'.'=>'.$commission3_bili.'=>'.$commission_bili);
            $ogupdate['parent3commission'] = bcmul($commission_bili/100,$commission_totalprice,2);
        }

            //级差
            if((($product['fx_differential'] == -1 && $sysset['fx_differential'] == 1) || $product['fx_differential'] == 1)  && in_array($product['commissionset'],[0,1,2,5,6])){
                if($ogupdate['parent2commission'] > 0) {
                    $cha2_1 = $ogupdate['parent2commission'] - $ogupdate['parent1commission'];
                    $ogupdate['parent2commission'] = $cha2_1 > 0 ? $cha2_1 : 0;
                }
                if($ogupdate['parent3commission'] > 0) {
                    $cha3_1 = $ogupdate['parent3commission'] - $ogupdate['parent2commission'] - $ogupdate['parent1commission'];
                    $ogupdate['parent3commission'] = $cha3_1 > 0 ? $cha3_1 : 0;
                }
                if(getcustom('commission_butie')){
                    //分销补贴也跟随级差
                    if($ogupdate['parent2commission_butie'] > 0) {
                        $cha2_1_butie = $ogupdate['parent2commission_butie'] - $ogupdate['parent1commission_butie'];
                        $ogupdate['parent2commission_butie'] = $cha2_1_butie > 0 ? $cha2_1_butie : 0;
                    }
                    if($ogupdate['parent3commission_butie'] > 0) {
                        $cha3_1_butie = $ogupdate['parent3commission_butie'] - $ogupdate['parent2commission_butie'] - $ogupdate['parent1commission_butie'];
                        $ogupdate['parent3commission_butie'] = $cha3_1_butie > 0 ? $cha3_1_butie : 0;
                    }
                }
            }

        //平级奖
        if(getcustom('commission_parent_pj') && !getcustom('commission_parent_pj_stop')){
            if($agleveldata4 && $ogupdate['parent3'] && $ogupdate['parent3commission'] > 0 && $agleveldata3['id'] == $agleveldata4['id']){
                $agleveldata4['commissionpingjitype'] = $agleveldata4['commissiontype'];
                if($product['commissionpingjiset'] != 0){
                    if($product['commissionpingjiset'] == 1){
                        $commissionpingjidata1 = json_decode($product['commissionpingjidata1'],true);
                        $agleveldata4['commission_parent_pj'] = $commissionpingjidata1[$agleveldata4['id']]['commission'];
                    }elseif($product['commissionpingjiset'] == 2){
                        $commissionpingjidata2 = json_decode($product['commissionpingjidata2'],true);
                        $agleveldata4['commission_parent_pj'] = $commissionpingjidata2[$agleveldata4['id']]['commission'];
                        $agleveldata4['commissionpingjitype'] = 1;
                    }else{
                        $agleveldata4['commission_parent_pj'] = 0;
                    }
                }
                if($agleveldata4['commission_parent_pj'] > 0) {
                    if($agleveldata4['commissionpingjitype']==0){
                        $ogupdate['parent4commission'] = $ogupdate['parent3commission'] * $agleveldata4['commission_parent_pj'] * 0.01;
                    } else {
                        $ogupdate['parent4commission'] = $agleveldata4['commission_parent_pj'];
                    }
                    $ogupdate['parent4'] = $parent4['id'];
                }
            }
            if($agleveldata3 && $ogupdate['parent2'] && $ogupdate['parent2commission'] > 0 && $agleveldata2['id'] == $agleveldata3['id']){
                $agleveldata3['commissionpingjitype'] = $agleveldata3['commissiontype'];
                if($product['commissionpingjiset'] != 0){
                    if($product['commissionpingjiset'] == 1){
                        $commissionpingjidata1 = json_decode($product['commissionpingjidata1'],true);
                        $agleveldata3['commission_parent_pj'] = $commissionpingjidata1[$agleveldata3['id']]['commission'];
                    }elseif($product['commissionpingjiset'] == 2){
                        $commissionpingjidata2 = json_decode($product['commissionpingjidata2'],true);
                        $agleveldata3['commission_parent_pj'] = $commissionpingjidata2[$agleveldata3['id']]['commission'];
                        $agleveldata3['commissionpingjitype'] = 1;
                    }else{
                        $agleveldata3['commission_parent_pj'] = 0;
                    }
                }
                if($agleveldata3['commission_parent_pj'] > 0){
                    if(!$ogupdate['parent3']){
                        $ogupdate['parent3commission'] = 0;
                        $ogupdate['parent3'] = $parent3['id'];
                    }
                    if($agleveldata3['commissionpingjitype'] == 0){
                        $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $ogupdate['parent2commission'] * $agleveldata3['commission_parent_pj'] * 0.01;
                    }else{
                        $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $agleveldata3['commission_parent_pj'];
                    }
                }
            }
            if($agleveldata2 && $ogupdate['parent1'] && $ogupdate['parent1commission'] > 0 && $agleveldata1['id'] == $agleveldata2['id']){
                $agleveldata2['commissionpingjitype'] = $agleveldata2['commissiontype'];
                if($product['commissionpingjiset'] != 0){
                    if($product['commissionpingjiset'] == 1){
                        $commissionpingjidata1 = json_decode($product['commissionpingjidata1'],true);
                        $agleveldata2['commission_parent_pj'] = $commissionpingjidata1[$agleveldata2['id']]['commission'];
                    }elseif($product['commissionpingjiset'] == 2){
                        $commissionpingjidata2 = json_decode($product['commissionpingjidata2'],true);
                        $agleveldata2['commission_parent_pj'] = $commissionpingjidata2[$agleveldata2['id']]['commission'];
                        $agleveldata2['commissionpingjitype'] = 1;
                    }else{
                        $agleveldata2['commission_parent_pj'] = 0;
                    }
                }
                if($agleveldata2['commission_parent_pj'] > 0){
                    if(!$ogupdate['parent2']){
                        $ogupdate['parent2commission'] = 0;
                        $ogupdate['parent2'] = $parent2['id'];
                    }
                    if($agleveldata2['commissionpingjitype'] == 0){
                        $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $ogupdate['parent1commission'] * $agleveldata2['commission_parent_pj'] * 0.01;
                    }else{
                        $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $agleveldata2['commission_parent_pj'];
                    }
                }
            }
        }
        /******************************平级后只拿平级奖，不再向上发放分销奖金 start **************************************/
        if(getcustom('commission_parent_pj_stop') && $member['path']){
            //重新计算平级奖
            $pids = $member['path'];
            $parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
            $parentList = array_reverse($parentList);
            $last_level_data = Db::name('member_level')->where('id',$member['levelid'])->find();
            $i = 0;
            $pjlevelids = [];//已拿平级奖的会员
            foreach($parentList as $parentpj){
                $i++;
                if($i==1){
                    $commission_field = 'parent1commission';
                    $parent_field = 'parent1';
                }elseif($i==2){
                    $commission_field = 'parent2commission';
                    $parent_field = 'parent2';
                }elseif($i==3){
                    $commission_field = 'parent3commission';
                    $parent_field = 'parent3';
                }else{
                    $commission_field = 'parent4commission';
                    $parent_field = 'parent4';
                }
                $level_data = Db::name('member_level')->where('id',$parentpj['levelid'])->find();
                if($level_data['commission_parent_pj_lv']>0 && $level_data['commission_parent_pj_lv']<$i){
                    //超出层级限制
                    if($level_data['id']==$last_level_data['id']){
                        $ogupdate[$commission_field] = 0;
                    }
                    //超出层级限制，跳过
                    continue;
                }
                //dump($i.'=>'.$parentpj['id'].'开始,级别'.$last_level_data['id'].'=>'.$level_data['id']);
                if($level_data['commission_parent_pj_status']==1 && !in_array($level_data['id'],$pjlevelids)){
                    if($level_data['id']==$last_level_data['id']){
                        $pj_bonus = $level_data['commission_parent_pj'];//固定金额
                        $pj_bonus_order = bcmul($level_data['commission_parent_pj_order']/100,$commission_totalprice,2);
                        $pj_bonus = bcadd($pj_bonus,$pj_bonus_order,2);
                        $ogupdate[$commission_field] = $pj_bonus;
                        $ogupdate[$parent_field] = $parentpj['id'];
                        $pjlevelids = [$level_data['id']];
                        if($i>=4){
                            //平级奖只发最近的一个
                            break;
                        }
                    }
                    if($level_data['sort']<$last_level_data['sort']){
                        //设置了平级奖，如果上级会员级别小于下级，那么不发放分销佣金
                        $ogupdate[$commission_field] = 0;
                        continue;
                    }
                }
                if($i<4){
                    $last_level_data = $level_data;
                }
            }
        }
        /******************************平级后只拿平级奖，不再向上发放分销奖金 stop **************************************/
//                    dump($ogupdate);exit;
        return $ogupdate;
        }
    }
    //退款退还分销佣金
    public static function refundFenxiao($aid,$orderid,$type){
        $open_commission_orderrefund_deduct = Db::name('admin_set')->where('aid',$aid)->value('open_commission_orderrefund_deduct');
        if($open_commission_orderrefund_deduct !=1){
            return;
        }
        writeLog('订单退款扣除分销佣金orderid:'.$orderid.'type:'.$type,'commissionrefund');
        $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('orderid',$orderid)->where('status',1)->where('type',$type)->select()->toArray();
        foreach($commission_record_list as $k=>$commission_record){
            if($commission_record['commission'] > 0){
                \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],-1*$commission_record['commission'],'订单退款扣除'.$commission_record['remark']);
                Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>2,'endtime'=>time()]);
                    // $tmplcontent = [];
                    // $tmplcontent['first'] = '分销佣金退回'.t('佣金').'：￥'.$commission_record['commission'];
                    // $tmplcontent['remark'] = '点击进入查看~';
                    // $tmplcontent['keyword1'] = '订单退款'; //商品信息
                    // $tmplcontent['keyword2'] = 0;//商品单价
                    // $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    // $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    // $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
            }
        }
        
    }

    //升级给上级发放佣金
    public static function tuiguang_bonus($orderid){
        if(getcustom('ganer_fenxiao')) {
            $order = Db::name('member_levelup_order')->where('id', $orderid)->find();
            $aid = $order['aid'];
            $mid = $order['mid'];
            $member = Db::name('member')->where('id', $order['mid'])->find();
            $parent = [];
            if ($member['pid']) {
                $parent = Db::name('member')->where('id', $member['pid'])->find();
            }
            if (!$parent) {
                return ['status' => 1, 'msg' => '不存在上级'];
            }
            /******************************推广奖 start ***********************************/
            $commission = Db::name('tuiguang_manage')->where('aid', $aid)->where('key', $parent['levelid'] . ',' . $member['levelid'])->value('commission1');
            if ($commission > 0) {
                $data = [
                    'aid' => $aid,
                    'mid' => $parent['id'],
                    'frommid' => $mid,
                    'orderid' => $orderid,
                    'ogid' => $orderid,
                    'type' => 'member_levelup',
                    'commission' => $commission,
                    'score' => 0,
                    'remark' => '下级升级奖励',
                    'createtime' => time(),
                    'status' => 1
                ];
                Db::name('member_commission_record')->insert($data);
                \app\commons\Member::addcommission($aid, $parent['id'], $mid, $commission, '下级升级奖励');
            }
            /******************************推广奖 end ***********************************/

            /******************************区域开店奖励 start ***********************************/
            $province = $order['areafenhong_province'];
            $city = $order['areafenhong_city'];
            $area = $order['areafenhong_area'];
            if ($area) {
                //区县代理，查找市级代理
                $levelids = Db::name('member_level')->where('aid', $aid)->where('areafenhong', 'in', [1, 2])->column('id');
                $map = [];
                $map[] = ['areafenhong_province', '=', $province];
                $map[] = ['areafenhong_city', '=', $city];
                //$map[] = ['areafenhong_area','=',$area];
                $map[] = ['levelid', 'in', $levelids];
                $area_members = Db::name('member')->where($map)->select()->toArray();
                if ($area_members) {
                    foreach ($area_members as $area_member) {
                        //dump($area_member['id']);
                        $commission = Db::name('tuiguang_manage')->where('aid', $aid)->where('key', $area_member['levelid'] . ',' . $member['levelid'])->value('commission2');
                        //echo Db::getLastSql();
                        if ($commission > 0) {
                            $data = [
                                'aid' => $aid,
                                'mid' => $area_member['id'],
                                'frommid' => $mid,
                                'orderid' => $orderid,
                                'ogid' => $orderid,
                                'type' => 'member_levelup',
                                'commission' => $commission,
                                'score' => 0,
                                'remark' => '区域邀请奖励',
                                'createtime' => time(),
                                'status' => 1
                            ];
                            Db::name('member_commission_record')->insert($data);
                            \app\commons\Member::addcommission($aid, $area_member['id'], $mid, $commission, '区域邀请奖励');
                        }
                    }
                }
            }
            /******************************区域开店奖励 end ***********************************/

            /******************************团队开店奖励 start ***********************************/
            $pids = $member['path'];
            $levelids = Db::name('member_level')->where('aid', $aid)->where('areafenhong', 'in', [1, 2])->column('id');
            $map = [];
            $map[] = ['id', 'in', $pids];
            //$map[] = ['areafenhong_province', '=', $province];
            //$map[] = ['areafenhong_city', '=', $city];
            //$map[] = ['levelid', 'in', $levelids];
            $parentList = Db::name('member')
                ->where($map)
                ->order(Db::raw('field(id,' . $pids . ')'))->select()->toArray();
            $parentList = array_reverse($parentList);
            if ($parentList) {
                $jicha = 0;
                foreach ($parentList as $parent2) {
                    $old_commission = Db::name('tuiguang_manage')->where('aid', $aid)->where('key', $parent2['levelid'] . ',' . $member['levelid'])->value('commission3');
                    $commission = bcsub($old_commission,$jicha,2);
                    if ($commission > 0) {
                        $data = [
                            'aid' => $aid,
                            'mid' => $parent2['id'],
                            'frommid' => $mid,
                            'orderid' => $orderid,
                            'ogid' => $orderid,
                            'type' => 'member_levelup',
                            'commission' => $commission,
                            'score' => 0,
                            'remark' => '团队邀请奖励',
                            'createtime' => time(),
                            'status' => 1
                        ];
                        Db::name('member_commission_record')->insert($data);
                        \app\commons\Member::addcommission($aid, $parent2['id'], $mid, $commission, '团队邀请奖励');
                        $jicha = $old_commission;
                    }
                }
            }
            /******************************团队开店奖励 end ***********************************/
            return ['status' => 1, 'msg' => '发放成功！'];
        }
    }

    //统计奖金池
    public static function bonus_poul($orderid,$type){
        if(getcustom('ganer_fenxiao')){
            if($type=='shop'){
                $order = Db::name('shop_order')->where('id',$orderid)->find();
            }else{
                return true;
            }
            $aid = $order['aid'];
            $mid = $order['mid'];

            $have = Db::name('prize_pool_log')->where('aid',$aid)->where('orderid',$orderid)->where('type',$type)->find();
            if($have){
                //已经统计过了
                return true;
            }
            $type = $type;
            $oglist = Db::name('shop_order_goods')->where('orderid',$orderid)->select()->toArray();
            $pool_num = 0;
            foreach($oglist as $og){
                $og_pool_num = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                $pool_num = bcadd($pool_num,$og_pool_num,2);
            }
            //奖金池总记录
            $set =  Db::name('prize_pool_set')->where('aid',$aid)->find();
            if($set){
                Db::name('prize_pool_set')->where('id',$set['id'])->inc('pool_num',$pool_num)->update();
            }else{
                $data = [];
                $data['aid'] = $aid;
                $data['pool_num'] = $pool_num;
                Db::name('prize_pool_set')->insert($data);
            }
            //奖金池明细
            $log = [];
            $log['num'] = $pool_num;
            $log['mid'] = $mid;
            $log['orderid'] = $orderid;
            $log['type'] = $type;
            $log['aid'] = $aid;
            $log['createtime'] = time();
            $log['remark'] = '商城订单新增';
            Db::name('prize_pool_log')->insert($log);
        }

    }
    //发放奖金
    public function send_prize_pool($levelids,$send_bili,$aid,$send_type=0){
        if(getcustom('ganer_fenxiao')) {
            $pool_set = Db::name('prize_pool_set')->where('aid', $aid)->find();
            if (!$pool_set || $pool_set['pool_num'] <= 0) {
                return ['status' => 0, 'msg' => '奖金池金额为0'];
            }
            $prize_total = bcmul($pool_set['pool_num'], $send_bili / 100, 2);
            if ($prize_total <= 0) {
                return ['status' => 0, 'msg' => '发放奖金为0'];
            }
            foreach($levelids as $levelid=>$bonus_bili){
                $level_prize = bcmul($prize_total,$bonus_bili/100,4);
                $map = [];
                $map[] = ['levelid', '=', $levelid];
                $map[] = ['aid', '=', $aid];
                $member_lists = Db::name('member')->where($map)->select()->toArray();
                if (count($member_lists) <= 0) {
                    //return ['status' => 0, 'msg' => '会员数量为0'];
                    continue;
                }
                $member_count = count($member_lists);
                $avg_prize = bcdiv($level_prize, $member_count, 2);
                if ($avg_prize <= 0) {
                    //return ['status' => 0, 'msg' => '平均奖金为0'];
                    continue;
                }
                //扣除奖金池总量
                Db::name('prize_pool_set')->where('id', $pool_set['id'])->dec('pool_num', $prize_total)->update();

                //发放奖金
                foreach ($member_lists as $member) {
                    \app\commons\Member::addcommission($aid, $member['id'], 0, $avg_prize, '奖金池分红');
                    //奖金池明细
                    $log = [];
                    $log['num'] = -$avg_prize;
                    $log['mid'] = $member['id'];
                    $log['orderid'] = 0;
                    $log['type'] = '';
                    $log['aid'] = $aid;
                    $log['levelid'] = $levelid;
                    $log['createtime'] = time();
                    $log['remark'] = '奖金池发放';
                    Db::name('prize_pool_log')->insert($log);
                }
                //记录奖金池发放日志
                $send_log = [];
                $send_log['aid'] = $aid;
                $send_log['pool_num'] = $pool_set['pool_num'];
                $send_log['send_bili'] = $send_bili;
                $send_log['prize_total'] = $prize_total;
                $send_log['levelid'] = $levelid;
                $send_log['level_bili'] = $bonus_bili;
                $send_log['level_prize_total'] = $level_prize;
                $send_log['member_count'] = $member_count;
                $send_log['send_type'] = $send_type;//0手动发放 1自动发放
                $send_log['createtime'] = time();
                Db::name('prize_pool_send_log')->insert($send_log);
            }

            return ['status' => 1, 'msg' => '发放成功'];
        }
    }
}