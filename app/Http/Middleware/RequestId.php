<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

class RequestId
{
    public function handle(Request $request, Closure $next)
    {
        // Generate on the server; do not trust IDs provided by callers.
        $id = (string) Str::uuid();
        if (config('telescope.enabled') && app()->environment('local') && ! Telescope::isRecording()) {
            Telescope::startRecording(false);
        }
        $request->attributes->set('request_id', $id);
        Log::withContext(['request_id' => $id]);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $id);
            if (class_exists(Telescope::class) && Telescope::isRecording()) {
                Telescope::recordRequest(IncomingEntry::make([
                    'ip_address' => $request->ip(),
                    'uri' => $request->path(),
                    'method' => $request->method(),
                    'controller_action' => $request->route()?->getActionName(),
                    'middleware' => [],
                    'headers' => array_intersect_key($request->headers->all(), array_flip(['accept', 'content-type', 'user-agent'])),
                    'payload' => [],
                    'session' => [],
                    'response_headers' => ['x-request-id' => $id],
                    'response_status' => $response->getStatusCode(),
                    'response' => '[omitted; inspect status and request_id]',
                    'duration' => null,
                    'memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
                ])->tags(['request_id:'.$id]));
            }

            return $response;
        } finally {
            Log::withoutContext();
        }
    }
}
