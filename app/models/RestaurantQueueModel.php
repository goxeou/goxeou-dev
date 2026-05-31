<?php



namespace app\models;

use think\Model;

class RestaurantQueueModel extends Model
{

    protected $name = 'restaurant_queue';
    protected $autoWriteTimestamp = true;

    public function getList($where = [], $page = 1, $limit = 15, $order = '')
    {
        $order = $order ? $order : 'id desc';
        $page = $page ? intval($page) : 1;
        $limit = $limit ? intval($limit) : 15;

        $model = $this;
        if ($where) {
            $model = $model->where($where);
        }
        $count = $model->count();
        if ($limit != 'all') {
            $model = $model->page($page, $limit);
        }

        $data = $model->with(['member'])->order($order)->select();

        return ['count' => $count, 'list' => $data];

    }

    public function member()
    {
        return $this->hasOne(Member::class, 'id', 'mid');
    }

}