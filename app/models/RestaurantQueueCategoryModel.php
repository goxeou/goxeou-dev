<?php




namespace app\models;

use think\Model;

class RestaurantQueueCategoryModel extends Model
{

    protected $name = 'restaurant_queue_category';
    protected $autoWriteTimestamp = true;

    public function getList($where = [], $page = 1, $limit = 100, $order = '')
    {
        $order = $order ? $order : 'sort desc,id';
        $page = $page ? intval($page) : 1;
        $limit = $limit ? intval($limit) : 100;

        $model = $this;
        if ($where) {
            $model = $model->where($where);
        }
        if ($limit != 'all') {
            $model = $model->page($page, $limit);
        }

        $data = $model->order($order)->select();

        return $data;

    }

}