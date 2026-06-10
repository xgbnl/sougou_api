<?php

namespace App\Http\Controllers;

use App\ThirdParty\Baidu\DeliveryMessage;
use Illuminate\Http\Request;

readonly class BaiduDeliveryController
{
    public function handle(Request $request, DeliveryMessage $message): string
    {
        $result = $message->handle($request->all());

        return $result ? 'success' : 'fail';
    }
}
