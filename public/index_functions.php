<?php

/**
 * 解析短網址路徑，回傳 ['valid' => bool, 'redirect' => ?string]
 * - valid: 路徑每一段都合法
 * - redirect: 需要 301 轉址的目標路徑（台→臺），不含 query string
 */
function resolve_short_url(string $path, array $options): array
{
    $segments = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));
    $segments = array_map('urldecode', $segments);

    // 台 → 臺 正規化
    $normalized = array_map(fn($s) => str_replace('台', '臺', $s), $segments);
    $needs_redirect = ($segments !== $normalized);
    $segments = $normalized;

    $valid = false;
    if (count($segments) >= 1 && count($segments) <= 3) {
        $area = $segments[0];
        if (array_key_exists($area, $options['area'])) {
            $valid = true;
            $subareas = $options['area'][$area];
            $types = $options['type'];

            if (count($segments) >= 2) {
                $seg1 = $segments[1];
                if (!in_array($seg1, $subareas) && !in_array($seg1, $types)) {
                    $valid = false;
                }
            }
            if (count($segments) >= 3) {
                $seg2 = $segments[2];
                if (!in_array($seg2, $types)) {
                    $valid = false;
                }
            }
        }
    }

    $redirect = null;
    if ($valid && $needs_redirect) {
        $redirect = '/' . implode('/', array_map('urlencode', $segments));
    }

    return ['valid' => $valid, 'redirect' => $redirect];
}
