<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class Notifier
{
    public static function send(?int $userId,string $title,string $message,?string $type=null,array $data=[]):void
    {
        if(!$userId) return;
        DB::table('app_notifications')->insert(['user_id'=>$userId,'title'=>$title,'message'=>$message,'type'=>$type,'data'=>json_encode($data),'is_read'=>false,'created_at'=>now(),'updated_at'=>now()]);
    }
    public static function order(Order $order,string $status):void
    {
        $labels=['pending'=>'Natanggap ang order','confirmed'=>'Nakumpirma ang order','preparing'=>'Inihahanda ang order','ready'=>'Handa na ang order','rider_assigned'=>'May rider na ang order','picked_up'=>'Nakuha na ng rider ang order','on_the_way'=>'Papunta na ang rider','delivered'=>'Naihatid na ang order','completed'=>'Tapos na ang order','cancelled'=>'Nakansela ang order','rejected'=>'Hindi tinanggap ang order','expired'=>'Nag-expire ang order'];
        $label=$labels[$status]??'Update sa order';
        self::send($order->user_id,$label,"Order {$order->order_number}: ".strtolower($label),'order',['order_id'=>$order->id,'status'=>$status]);
    }
}
