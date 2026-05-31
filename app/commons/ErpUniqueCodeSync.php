<?php
/**
 * 唯一码同步：从 ERP(Oracle) 拉取唯一码到本地 MySQL 缓存
 * 通过 docker exec 执行 sqlplus 查询
 */

namespace app\commons;

use think\facade\Db;

class ErpUniqueCodeSync
{
    /**
     * 为指定商户同步唯一码
     * @param int $aid 站点ID
     * @param int $bid 商户ID
     * @return array
     */
    public static function syncForBusiness($aid, $bid)
    {
        // 获取商户绑定的ERP仓库
        $business = Db::name('business')->where('aid', $aid)->where('id', $bid)->find();
        if (!$business || empty($business['erp_depot_id'])) {
            return ['status' => 0, 'msg' => '该商户未绑定ERP仓库，请先在编辑中设置'];
        }

        $depotId = $business['erp_depot_id'];

        // 从ERP查询该仓库的唯一码
        // 使用docker exec调用sqlplus
        $total = 0;
        $inserted = 0;
        $skipped = 0;

        // 分批查询，每次1000条
        $page = 0;
        $pageSize = 1000;

        do {
            $offset = $page * $pageSize;
            $sql = "SELECT * FROM (
                SELECT pu.SKU_UNICODE, pu.SKU_CODE, ROWNUM AS rn
                FROM SHUZAO.PRODUCT_UNICODE pu
                WHERE pu.SKU_CODE IS NOT NULL
                AND pu.SKU_UNICODE IS NOT NULL
                AND pu.SKU_CODE IN (SELECT SKU_CODE FROM SHUZAO.DEPOT_SKU_STOCK WHERE DEPOT_ID='{$depotId}' AND STOCK > 0)
                AND ROWNUM <= " . ($offset + $pageSize) . "
            ) WHERE rn > {$offset}";

            $cmd = "echo \"SET PAGESIZE 0 FEEDBACK OFF ECHO OFF HEADING OFF;
SELECT pu.SKU_UNICODE || '||' || pu.SKU_CODE
FROM SHUZAO.PRODUCT_UNICODE pu
WHERE pu.SKU_CODE IS NOT NULL AND pu.SKU_UNICODE IS NOT NULL
AND pu.ORDER_SN IS NULL
AND pu.PRINT_STATUS = 0
AND pu.SKU_CODE IN (SELECT SKU_CODE FROM SHUZAO.DEPOT_SKU_STOCK WHERE DEPOT_ID='{$depotId}' AND STOCK > 0)
AND ROWNUM <= 10000;\" | docker exec -i oracle-xe sqlplus -S SHUZAO/shuzao123@xepdb1 2>/dev/null";

            $output = shell_exec($cmd);
            if (empty($output)) break;

            $lines = explode("\n", trim($output));
            $batchData = [];
            $now = time();

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $parts = explode('||', $line);
                if (count($parts) < 2) continue;
                
                $uniqueCode = trim($parts[0]);
                $skuCode = trim($parts[1]);
                if (empty($uniqueCode) || empty($skuCode)) continue;

                $total++;
                
                // 检查是否已存在
                $exists = Db::name('shop_unique_code')
                    ->where('unique_code', $uniqueCode)
                    ->find();
                
                if ($exists) {
                    $skipped++;
                    continue;
                }

                $batchData[] = [
                    'aid' => $aid,
                    'bid' => $bid,
                    'unique_code' => $uniqueCode,
                    'sku_code' => $skuCode,
                    'erp_depot_id' => $depotId,
                    'status' => 0,
                    'createtime' => $now,
                ];
            }

            if (!empty($batchData)) {
                // 使用 INSERT IGNORE 避免重复唯一码冲突
                $sql = "INSERT IGNORE INTO ddwx_shop_unique_code (aid,bid,unique_code,sku_code,erp_depot_id,status,createtime) VALUES ";
                $rows = [];
                foreach ($batchData as $r) {
                    $rows[] = "({$r['aid']},{$r['bid']}," . var_export($r['unique_code'], true) . "," . var_export($r['sku_code'], true) . "," . var_export($r['erp_depot_id'], true) . ",{$r['status']},{$r['createtime']})";
                }
                Db::execute($sql . implode(',', $rows));
                $inserted += count($batchData);
            }

            $page++;
        } while (count($lines) >= $pageSize);

        return [
            'status' => 1,
            'msg' => "同步完成：总 {$total} 条，新增 {$inserted} 条，已存在跳过 {$skipped} 条"
        ];
    }

    /**
     * 查询商户唯一码统计
     */
    public static function getUniqueCodeStats($aid, $bid)
    {
        $total = Db::name('shop_unique_code')
            ->where('aid', $aid)
            ->where('bid', $bid)
            ->count();

        $sold = Db::name('shop_unique_code')
            ->where('aid', $aid)
            ->where('bid', $bid)
            ->where('status', 1)
            ->count();

        $available = Db::name('shop_unique_code')
            ->where('aid', $aid)
            ->where('bid', $bid)
            ->where('status', 0)
            ->count();

        return "当前总 {$total} 个唯一码，在库 {$available} 个，已售 {$sold} 个";
    }

    /**
     * 付款后处理：更新唯一码状态 + ERP扣库存
     * @param int $aid 站点ID
     * @param int $bid 商户ID
     * @param int $orderId 收银台订单ID
     */
    public static function afterOrderPaid($aid, $bid, $orderId)
    {
        // 1. 获取该订单中所有有唯一码的商品
        $goodsList = Db::name('cashier_order_goods')
            ->where('aid', $aid)
            ->where('bid', $bid)
            ->where('orderid', $orderId)
            ->whereNotNull('unique_code')
            ->where('unique_code', '<>', '')
            ->select()
            ->toArray();

        if (empty($goodsList)) return;

        $now = time();
        $uniqueCodes = [];

        foreach ($goodsList as $goods) {
            $codes = explode(',', $goods['unique_code']);
            foreach ($codes as $code) {
                $code = trim($code);
                if (!empty($code)) {
                    $uniqueCodes[] = $code;
                }
            }
        }

        if (empty($uniqueCodes)) return;

        // 2. 更新本地唯一码状态为已售
        Db::name('shop_unique_code')
            ->whereIn('unique_code', $uniqueCodes)
            ->update([
                'status' => 1,
                'order_id' => $orderId,
                'saletime' => $now,
            ]);

        // 3. 通知ERP扣库存（通过docker exec 调用 sqlplus）
        $business = Db::name('business')->where('aid', $aid)->where('id', $bid)->find();
        if (empty($business) || empty($business['erp_depot_id'])) return;

        $depotId = $business['erp_depot_id'];

        // 按SKU_CODE统计要扣减的数量
        $skuCounts = [];
        foreach ($uniqueCodes as $uc) {
            $record = Db::name('shop_unique_code')
                ->where('unique_code', $uc)
                ->find();
            if ($record && !empty($record['sku_code'])) {
                $skuCode = $record['sku_code'];
                if (!isset($skuCounts[$skuCode])) {
                    $skuCounts[$skuCode] = 0;
                }
                $skuCounts[$skuCode]++;
            }
        }

        // 执行ERP库存扣减
        foreach ($skuCounts as $skuCode => $qty) {
            $escapedSku = str_replace("'", "''", $skuCode);
            $sql = "UPDATE SHUZAO.DEPOT_SKU_STOCK 
                    SET STOCK = GREATEST(STOCK - {$qty}, 0),
                        FREE_STOCK = GREATEST(FREE_STOCK - {$qty}, 0),
                        UPDATE_TIME = {$now}
                    WHERE DEPOT_ID = '{$depotId}' 
                    AND SKU_CODE = '{$escapedSku}'";
            
            $cmd = "echo \"{$sql}\" | docker exec -i oracle-xe sqlplus -S SHUZAO/shuzao123@xepdb1 2>/dev/null";
            shell_exec($cmd);
        }

        // 4. 记录操作日志
        \app\commons\System::plog("收银订单{$orderId}：唯一码已售，更新ERP库存，扣减" . count($skuCounts) . "个SKU");
    }

    /**
     * 根据唯一码查找商品和规格
     * @param int $aid 站点ID
     * @param int $bid 商户ID
     * @param string $uniqueCode 唯一码
     * @return array
     */
    public static function lookupUniqueCode($aid, $bid, $uniqueCode)
    {
        // 先查本地缓存
        $code = Db::name('shop_unique_code')
            ->where('unique_code', $uniqueCode)
            ->where('status', 0)
            ->find();
        
        if (!$code) {
            return null;
        }

        // 根据 SKU_CODE 在商品规格中查找
        // 先从本商户的商品中查找
        $product = Db::name('shop_product')
            ->alias('p')
            ->join('shop_guige g', 'p.id = g.proid')
            ->where('p.aid', $aid)
            ->where('p.bid', $bid)
            ->where('g.barcode', $code['sku_code'])
            ->field('p.*, g.id as ggid, g.name as ggname, g.barcode as gbarcode, g.sell_price as gsell_price, g.pic as gpic, g.stock as gstock')
            ->find();

        if ($product) {
            return [
                'proid' => $product['id'],
                'ggid' => $product['ggid'],
                'ggname' => $product['ggname'],
                'name' => $product['name'],
                'sell_price' => $product['gsell_price'] ?: $product['sell_price'],
                'pic' => $product['gpic'] ?: $product['pic'],
                'stock' => $product['gstock'],
                'unique_code' => $uniqueCode,
            ];
        }

        // 如果未找到，可能 SKU_CODE 是 ERP 规格编码规则
        // 尝试从规格名称中匹配
        $guiges = Db::name('shop_guige')
            ->alias('g')
            ->join('shop_product p', 'g.proid = p.id')
            ->where('p.aid', $aid)
            ->where('p.bid', $bid)
            ->select();

        foreach ($guiges as $g) {
            // SKU_CODE 格式如 "djx24x12-1k-36"，最后一段是尺码
            $skuParts = explode('-', $code['sku_code']);
            $sizeCode = end($skuParts);
            
            // 检查规格名称是否包含这个尺码
            if (strpos($g['name'], $sizeCode) !== false) {
                return [
                    'proid' => $g['proid'],
                    'ggid' => $g['id'],
                    'ggname' => $g['name'],
                    'name' => $g['name'],
                    'sell_price' => $g['sell_price'],
                    'pic' => $g['pic'],
                    'stock' => $g['stock'],
                    'unique_code' => $uniqueCode,
                ];
            }
        }

        return null;
    }
}
