<?php
// Very simple file-based rate-limiter by IP

function rate_limit_check(string $key, int $max, int $windowSec = 600): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $id = preg_replace('/[^a-z0-9_\-]/i', '_', $key . '_' . $ip);
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rl_' . $id . '.json';
    $now = time();
    $data = ['ts'=>$now, 'cnt'=>0];
    if (is_file($file)) {
        $raw = file_get_contents($file);
        $j = json_decode($raw, true);
        if (is_array($j) && isset($j['ts'], $j['cnt'])) $data = $j;
    }
    if (($now - (int)$data['ts']) > $windowSec) { $data = ['ts'=>$now, 'cnt'=>0]; }
    $data['cnt'] = (int)$data['cnt'] + 1;
    file_put_contents($file, json_encode($data));
    return $data['cnt'] <= $max;
}

