<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation', 'token', 'api_key']);
        Telescope::hideRequestHeaders(['authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-csrf-token', 'x-xsrf-token']);

        Telescope::filter(function (IncomingEntry $entry) {
            if ($entry->type === 'request') {
                // Preserve timing/status, but never store arbitrary user data or URL query strings.
                $entry->content['uri'] = explode('?', $entry->content['uri'])[0];
                $entry->content['payload'] = [];
                $entry->content['session'] = [];
                $entry->content['response'] = '[omitted; inspect status and request_id]';
                $entry->content['headers'] = array_intersect_key($entry->content['headers'], array_flip(['accept', 'content-type', 'user-agent']));
                $entry->content['response_headers'] = array_intersect_key($entry->content['response_headers'], array_flip(['content-type', 'x-request-id']));
                $id = request()->attributes->get('request_id');
                $entry->tags(['request_id:'.$id]);
            }
            if ($entry->type === 'log') {
                if (! in_array($entry->content['message'] ?? '', [
                    'Section 3 logging demo completed.',
                    'Unexpected application exception. Use request_id and trace to investigate.',
                ], true)) {
                    $entry->content['message'] = '[omitted; inspect local application log]';
                }
                $entry->content['context'] = array_intersect_key($entry->content['context'] ?? [], array_flip(['request_id', 'method', 'route', 'exception_type', 'order_id']));
            }
            if ($entry->type === 'query') {
                // Telescope interpolates bindings into SQL: mask quoted values before storage.
                $entry->content['sql'] = preg_replace("/'(?:''|\\\\.|[^'])*'/s", "'[redacted]'", $entry->content['sql']);
            }

            return $this->app->environment('local');
        });
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', fn ($user) => false);
    }
}
