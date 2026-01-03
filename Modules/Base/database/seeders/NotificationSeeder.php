<?php

namespace Modules\Base\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Modules\Base\Enums\NotificationReadTypeEnum;
use Modules\Base\Models\Account;
use Modules\Base\Models\Announcement;
use Modules\Base\Models\Message;
use Modules\Base\Models\NotificationRead;
use Modules\Base\Models\SystemNotification;

/**
 * 数据填充：通知管理
 */
class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 获取所有账号ID
        $accountIds = Account::query()->pluck('id')->toArray();
        if (empty($accountIds)) {
            $this->command->warn('没有找到账号数据，请先运行 AccountSeeder');
            return;
        }

        // 生成通知标题和内容的辅助函数
        $generateNotificationTitle = function ($type = 'system') {
            $titles = [
                'system'   => [
                    '系统维护通知',
                    '系统升级公告',
                    '功能更新通知',
                    '安全提醒',
                    '系统优化通知',
                ],
                'business' => [
                    '订单处理通知',
                    '支付成功提醒',
                    '业务审核通知',
                    '业务变更提醒',
                    '业务处理完成通知',
                ],
                'activity' => [
                    '限时活动开始',
                    '优惠活动通知',
                    '新用户福利',
                    '节日活动通知',
                    '会员专享活动',
                ],
                'other'    => [
                    '重要通知',
                    '温馨提示',
                    '通知公告',
                    '系统消息',
                    '其他通知',
                ],
            ];
            $typeTitles = $titles[$type] ?? $titles['system'];
            return $typeTitles[array_rand($typeTitles)];
        };

        $generateMessageTitle = function () {
            $titles = [
                '工作安排通知',
                '会议邀请',
                '任务分配',
                '工作汇报',
                '协作请求',
                '消息提醒',
                '重要通知',
                '日常沟通',
            ];
            return $titles[array_rand($titles)];
        };

        $generateAnnouncementTitle = function () {
            $titles = [
                '平台服务升级公告',
                '新功能上线公告',
                '系统维护公告',
                '重要政策公告',
                '用户协议更新公告',
                '平台规则调整公告',
                '节假日服务公告',
                '平台活动公告',
            ];
            return $titles[array_rand($titles)];
        };

        // 生成通知内容
        $generateContent = function ($type = 'system') {
            $contents = [
                'system'   => [
                    '系统将于今晚进行维护升级，预计维护时间为2小时，期间可能影响部分功能使用，请提前做好准备。',
                    '系统已完成升级，新增了多项功能，欢迎体验。如有问题，请联系客服。',
                    '为了提升用户体验，我们对系统进行了优化，现在访问速度更快，功能更稳定。',
                    '检测到您的账号存在安全风险，请及时修改密码，确保账号安全。',
                    '系统已优化，性能提升30%，使用体验更流畅。',
                ],
                'business' => [
                    '您的订单已处理完成，请及时查看订单状态。',
                    '您的支付已成功，订单正在处理中，请耐心等待。',
                    '您的业务申请已通过审核，请按照要求完成后续操作。',
                    '您的业务信息已更新，请及时查看确认。',
                    '您的业务处理已完成，请查看处理结果。',
                ],
                'activity' => [
                    '限时活动已开始，优惠力度空前，快来参与吧！',
                    '新用户专享福利，注册即送大礼包，数量有限，先到先得！',
                    '节日活动正在进行中，参与即可获得丰厚奖励。',
                    '会员专享活动开启，更多优惠等你来拿！',
                    '平台周年庆活动，多重好礼等你来领！',
                ],
                'other'    => [
                    '这是一条重要通知，请仔细阅读。',
                    '温馨提示：请注意账号安全，不要泄露个人信息。',
                    '通知公告：请关注平台最新动态。',
                    '系统消息：您的操作已成功完成。',
                    '其他通知：如有疑问，请联系客服。',
                ],
            ];
            $typeContents = $contents[$type] ?? $contents['system'];
            return $typeContents[array_rand($typeContents)];
        };

        $generateMessageContent = function () {
            $contents = [
                '请于明天上午10点参加部门会议，会议主题：项目进度汇报。',
                '您有一个新的任务需要处理，请及时查看并完成。',
                '工作汇报已收到，请继续保持良好的工作状态。',
                '协作请求已发送，请及时查看并回复。',
                '消息提醒：您有新的待办事项需要处理。',
                '重要通知：请关注最新的工作安排。',
                '日常沟通：如有问题，请随时联系。',
                '工作安排：请按照计划完成本周工作任务。',
            ];
            return $contents[array_rand($contents)];
        };

        $generateAnnouncementContent = function () {
            $contents = [
                '为了提供更好的服务，平台将于本周末进行服务升级，升级期间可能影响部分功能使用，敬请谅解。',
                '新功能已上线，包括：数据统计、报表导出、批量操作等，欢迎体验。',
                '系统将于今晚22:00-24:00进行维护，维护期间暂停服务，请提前做好准备。',
                '重要政策公告：平台已更新用户协议，请仔细阅读并确认。',
                '用户协议已更新，主要变更包括：隐私政策、服务条款等，请及时查看。',
                '平台规则已调整，主要涉及：用户行为规范、违规处理等，请遵守平台规则。',
                '节假日服务公告：春节期间平台服务时间调整，请关注最新通知。',
                '平台活动公告：限时优惠活动正在进行中，欢迎参与。',
            ];
            return $contents[array_rand($contents)];
        };

        // 1. 创建系统通知数据（50条）
        $this->command->info('  正在创建系统通知数据...');
        $systemNotifications = [];
        for ($i = 0; $i < 50; $i++) {
            $type = fake()->randomElement(['system', 'business', 'activity', 'other']);
            $accountId = $accountIds[array_rand($accountIds)];
            $targetPlatform = fake()->randomElement([
                'all',
                'user',
                'admin',
                'miniapp',
                'user,admin',
                'user,miniapp',
                'admin,miniapp',
            ]);

            // 随机生成开始和结束时间
            $startTime = fake()->dateTimeBetween('-30 days', '+30 days');
            // 确保结束时间在开始时间之后，且不早于当前时间太多
            $maxEndTime = max($startTime, now()->addDays(60));
            $endTime = fake()->dateTimeBetween($startTime, $maxEndTime);

            $systemNotification = SystemNotification::query()->create([
                'title'           => $generateNotificationTitle($type),
                'content'         => $generateContent($type),
                'target_platform' => $targetPlatform,
                'type'            => $type,
                'start_time'      => $startTime,
                'end_time'        => $endTime,
                'status'          => fake()->randomElement([0, 1]),
                'account_id'      => $accountId,
            ]);
            $systemNotifications[] = $systemNotification;
        }
        $this->command->info('  系统通知数据创建完成：' . count($systemNotifications) . ' 条');

        // 2. 创建站内信数据（50条）
        $this->command->info('  正在创建站内信数据...');
        $messages = [];
        for ($i = 0; $i < 50; $i++) {
            $senderId = $accountIds[array_rand($accountIds)];
            $receiverId = $accountIds[array_rand($accountIds)];
            // 确保发送者和接收者不是同一个人
            while ($senderId === $receiverId) {
                $receiverId = $accountIds[array_rand($accountIds)];
            }
            $targetPlatform = fake()->randomElement([
                'all',
                'user',
                'admin',
                'miniapp',
                'user,admin',
            ]);

            $message = Message::query()->create([
                'sender_id'       => $senderId,
                'receiver_id'     => $receiverId,
                'title'           => $generateMessageTitle(),
                'content'         => $generateMessageContent(),
                'target_platform' => $targetPlatform,
                'status'          => fake()->randomElement([0, 1]),
                'account_id'      => $senderId,
            ]);
            $messages[] = $message;
        }
        $this->command->info('  站内信数据创建完成：' . count($messages) . ' 条');

        // 3. 创建公告数据（30条）
        $this->command->info('  正在创建公告数据...');
        $announcements = [];
        for ($i = 0; $i < 30; $i++) {
            $accountId = $accountIds[array_rand($accountIds)];
            $targetPlatform = fake()->randomElement([
                'all',
                'user',
                'admin',
                'miniapp',
                'user,admin',
            ]);
            $position = fake()->randomElement(['home', 'top', 'bottom', 'sidebar', 'popup']);

            // 随机生成开始和结束时间
            $startTime = fake()->dateTimeBetween('-30 days', '+30 days');
            // 确保结束时间在开始时间之后，且不早于当前时间太多
            $maxEndTime = max($startTime, now()->addDays(60));
            $endTime = fake()->dateTimeBetween($startTime, $maxEndTime);

            $announcement = Announcement::query()->create([
                'title'           => $generateAnnouncementTitle(),
                'content'         => $generateAnnouncementContent(),
                'target_platform' => $targetPlatform,
                'position'        => $position,
                'start_time'      => $startTime,
                'end_time'        => $endTime,
                'status'          => fake()->randomElement([0, 1]),
                'account_id'      => $accountId,
            ]);
            $announcements[] = $announcement;
        }
        $this->command->info('  公告数据创建完成：' . count($announcements) . ' 条');

        // 4. 创建通知查看记录
        $this->command->info('  正在创建通知查看记录数据...');
        $readCount = 0;

        // 生成IP归属地的辅助函数
        $generateIpLocation = function () {
            $provinces = ['北京市', '上海市', '广东省', '浙江省', '江苏省', '山东省', '河南省', '四川省', '湖北省', '湖南省', '福建省', '安徽省', '河北省', '陕西省', '辽宁省'];
            $cities = ['北京', '上海', '深圳', '广州', '杭州', '南京', '济南', '郑州', '成都', '武汉', '长沙', '福州', '合肥', '石家庄', '西安', '沈阳'];
            $province = fake()->randomElement($provinces);
            $city = fake()->randomElement($cities);
            return "$province$city";
        };

        // 为系统通知创建查看记录
        foreach ($systemNotifications as $notification) {
            // 每个通知随机有 0-20 个查看记录
            $readNum = fake()->numberBetween(0, min(20, count($accountIds)));
            $readAccountIds = array_unique(fake()->randomElements($accountIds, $readNum));

            foreach ($readAccountIds as $readAccountId) {
                // 随机生成查看时间（在通知创建时间之后）
                $startTime = Carbon::parse($notification->created_at);
                $endTime = Carbon::now();

                // 如果开始时间大于等于结束时间，添加1秒间隔
                if ($startTime->gte($endTime)) {
                    $endTime = $startTime->copy()->addSecond();
                }

                $readAt = fake()->dateTimeBetween($startTime, $endTime);

                // 使用 updateOrCreate 避免重复
                NotificationRead::query()->updateOrCreate(
                    [
                        'read_type'  => NotificationReadTypeEnum::SystemNotification->value,
                        'target_id'  => $notification->id,
                        'account_id' => $readAccountId,
                    ],
                    [
                        'read_at'     => $readAt,
                        'ip_address'  => fake()->ipv4(),
                        'ip_location' => $generateIpLocation(),
                    ]
                );
                $readCount++;
            }
        }

        // 为站内信创建查看记录（站内信通常只有接收者查看）
        foreach ($messages as $message) {
            // 如果站内信状态为已读，则创建查看记录
            if ($message->status === 1) {
                $startTime = Carbon::parse($message->created_at);
                $endTime = Carbon::now();

                // 如果开始时间大于等于结束时间，添加1秒间隔
                if ($startTime->gte($endTime)) {
                    $endTime = $startTime->copy()->addSecond();
                }

                $readAt = fake()->dateTimeBetween($startTime, $endTime);
                // 使用 updateOrCreate 避免重复
                NotificationRead::query()->updateOrCreate(
                    [
                        'read_type'  => NotificationReadTypeEnum::Message->value,
                        'target_id'  => $message->id,
                        'account_id' => $message->receiver_id,
                    ],
                    [
                        'read_at'     => $readAt,
                        'ip_address'  => fake()->ipv4(),
                        'ip_location' => $generateIpLocation(),
                    ]
                );
                $readCount++;
            }
        }

        // 为公告创建查看记录
        foreach ($announcements as $announcement) {
            // 每个公告随机有 0-30 个查看记录
            $readNum = fake()->numberBetween(0, min(30, count($accountIds)));
            $readAccountIds = array_unique(fake()->randomElements($accountIds, $readNum));

            foreach ($readAccountIds as $readAccountId) {
                // 随机生成查看时间（在公告创建时间之后，且在结束时间之前）
                $startTime = Carbon::parse($announcement->created_at);
                $endTime = $announcement->end_time ? Carbon::parse($announcement->end_time) : null;
                $now = Carbon::now();

                // 确定最大查看时间
                if ($endTime === null) {
                    // 如果结束时间为空，使用当前时间
                    $maxReadTime = $now;
                } elseif ($endTime->lt($startTime)) {
                    // 如果结束时间早于创建时间，使用当前时间
                    $maxReadTime = $now;
                } elseif ($endTime->gt($now)) {
                    // 如果结束时间是未来时间，使用当前时间
                    $maxReadTime = $now;
                } else {
                    // 正常情况：使用结束时间
                    $maxReadTime = $endTime;
                }

                // 确保开始时间不晚于最大查看时间
                if ($startTime->gt($maxReadTime)) {
                    $maxReadTime = $now;
                }

                // 确保开始时间不晚于最大查看时间（再次检查，以防万一）
                $finalStartTime = $startTime->lt($maxReadTime) ? $startTime : $maxReadTime;

                // 如果开始时间和结束时间相同或开始时间大于结束时间，添加1秒的间隔
                if ($finalStartTime->gte($maxReadTime)) {
                    $maxReadTime = $finalStartTime->copy()->addSecond();
                }

                $readAt = fake()->dateTimeBetween($finalStartTime, $maxReadTime);

                // 使用 updateOrCreate 避免重复
                NotificationRead::query()->updateOrCreate(
                    [
                        'read_type'  => NotificationReadTypeEnum::Announcement->value,
                        'target_id'  => $announcement->id,
                        'account_id' => $readAccountId,
                    ],
                    [
                        'read_at'     => $readAt,
                        'ip_address'  => fake()->ipv4(),
                        'ip_location' => $generateIpLocation(),
                    ]
                );
                $readCount++;
            }
        }

        $this->command->info('  通知查看记录数据创建完成：' . $readCount . ' 条');
        $this->command->info('  通知数据填充完成！');
    }
}

