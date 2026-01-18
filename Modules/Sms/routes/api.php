<?php

use Illuminate\Support\Facades\Route;
use Modules\Sms\Http\Controllers\SmsController;
use Modules\Sms\Http\Controllers\SmsLogController;

// 不需要认证的接口
Route::post('/sms/send', [SmsController::class, 'sendSms']);  // 发送短信验证码

// API鉴权 管理员 路由组
Route::middleware(['auth:sanctum'])->prefix('/admin')->group(function () {
    // 短信发送记录管理
    Route::post('/sms/log/index', [SmsLogController::class, 'index']);  // 短信发送记录列表
    Route::post('/sms/log/getSmsLogSearchData', [SmsLogController::class, 'getSmsLogSearchData']);  // 短信发送记录搜索数据
});
