<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GenerateTraceId
{
    /**
     * 读取上游 W3C Trace Context（traceparent），无则生成新 trace_id；
     * 注入请求上下文与响应头，供日志关联与链路追踪。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $this->resolveTraceId($request);

        Context::add('trace_id', $traceId);
        $request->attributes->set('trace_id', $traceId);

        $response = $next($request);
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }

    private function resolveTraceId(Request $request): string
    {
        // traceparent: 00-<32hex trace-id>-<16hex span-id>-<2hex flags>
        $traceparent = $request->header('traceparent');

        if ($traceparent && preg_match('/^00-([0-9a-f]{32})-[0-9a-f]{16}-[0-9a-f]{2}$/', $traceparent, $m)) {
            return $m[1];
        }

        return Str::uuid()->toString();
    }
}
