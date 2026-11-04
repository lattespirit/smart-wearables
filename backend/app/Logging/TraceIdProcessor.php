<?php

namespace App\Logging;

use Illuminate\Support\Facades\Context;
use Monolog\LogRecord;

class TraceIdProcessor
{
    /**
     * 将请求级 trace_id 注入每条日志（Context 是 Octane 安全的，请求结束自动清理）。
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        if ($traceId = Context::get('trace_id')) {
            $record->extra['trace_id'] = $traceId;
        }

        return $record;
    }
}
