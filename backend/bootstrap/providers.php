<?php

use App\Modules\Admin\Providers\AdminServiceProvider;
use App\Modules\Analytics\Providers\AnalyticsServiceProvider;
use App\Modules\Authorization\Providers\AuthorizationServiceProvider;
use App\Modules\Device\Providers\DeviceServiceProvider;
use App\Modules\Feedback\Providers\FeedbackServiceProvider;
use App\Modules\Firmware\Providers\FirmwareServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Notification\Providers\NotificationServiceProvider;
use App\Modules\Ota\Providers\OtaServiceProvider;
use App\Modules\System\Providers\SystemServiceProvider;
use App\Modules\User\Providers\UserServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    AuthorizationServiceProvider::class,
    AdminServiceProvider::class,
    UserServiceProvider::class,
    DeviceServiceProvider::class,
    FirmwareServiceProvider::class,
    OtaServiceProvider::class,
    FeedbackServiceProvider::class,
    AnalyticsServiceProvider::class,
    NotificationServiceProvider::class,
    SystemServiceProvider::class,
];
