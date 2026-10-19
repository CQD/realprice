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

/**
 * 解析短網址路徑（已經過 resolve_short_url 驗證），回傳 ['area' => ?string, 'subarea' => ?string, 'type' => ?string]
 * 「台」會正規化成「臺」
 */
function short_url_params(string $path, array $options): array
{
    $segments = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));
    $segments = array_map(fn($s) => str_replace('台', '臺', urldecode($s)), $segments);

    $params = ['area' => $segments[0] ?? null, 'subarea' => null, 'type' => null];
    if (isset($segments[1])) {
        if (in_array($segments[1], $options['type'], true)) {
            $params['type'] = $segments[1];
        } else {
            $params['subarea'] = $segments[1];
        }
    }
    if (isset($segments[2])) {
        $params['type'] = $segments[2];
    }
    return $params;
}

function build_short_url(?string $area, ?string $subarea = null, ?string $type = null): string
{
    $parts = array_filter([$area, $subarea, $type], fn($s) => $s !== null && $s !== '');
    return '/' . implode('/', array_map('rawurlencode', $parts));
}

/**
 * 舊格式 ?area=臺北市&subarea=大安區&type=住宅大樓 → 短網址路徑
 * 舊的 parking、age_min 等篩選條件新版已不支援，直接忽略
 * 縣市不合法時回傳 null
 */
function legacy_query_url(array $get, array $options): ?string
{
    $area = str_replace('台', '臺', (string) ($get['area'] ?? ''));
    if (!array_key_exists($area, $options['area'])) return null;

    $subarea = str_replace('台', '臺', (string) ($get['subarea'] ?? ''));
    if (!in_array($subarea, $options['area'][$area], true) || $subarea === $area) $subarea = null;

    $type = (string) ($get['type'] ?? '');
    if (!in_array($type, $options['type'], true)) $type = null;

    return build_short_url($area, $subarea, $type);
}
