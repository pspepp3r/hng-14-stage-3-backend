<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response;

final class RateLimitMiddleware
{
    private string $storageDir;

    public function __construct()
    {
        $this->storageDir = APP_ROOT . '/storage/ratelimit';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0777, true);
        }
    }

    public function handle(string $key, int $limit, int $period = 60): void
    {
        $file = $this->storageDir . '/' . md5($key) . '.json';
        $now = time();
        
        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : ['count' => 0, 'start' => $now];

        if ($now - $data['start'] > $period) {
            $data = ['count' => 1, 'start' => $now];
        } else {
            $data['count']++;
        }

        file_put_contents($file, json_encode($data));

        if ($data['count'] > $limit) {
            Response::error('Too Many Requests', 429)->send();
            exit;
        }
    }
}
