<?php

require_once 'config.php';

/**
 * تولید ID یکتا بر اساس نام فیلم
 */
function vodIdFromName($name) {
    $hash = crc32(trim($name));
    $id = sprintf('%u', $hash);
    return (int)($id % 1000000000);
}

/**
 * تولید ID یکتا بر اساس نام کانال زنده
 */
function channelIdFromName($name) {
    return vodIdFromName($name);
}

/**
 * دریافت محتوا با cURL برای بهینه‌سازی سرعت و ایمنی در Render/Docker
 */
function fetchRemoteVodContent($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) IPTV-Vod-Parser');
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    return @file_get_contents($url);
}

function runVodPlaylistGenerate() {
    global $INCLUDE_ADULT_VOD;

    $include = filter_var($INCLUDE_ADULT_VOD ?? false, FILTER_VALIDATE_BOOLEAN);

    // آدرس M3U مربوط به VOD
    $playlistUrl = $include
        ? 'https://raw.githubusercontent.com/Behnood1368/Iptv/refs/heads/main/Kodi.m3u'
        : 'https://raw.githubusercontent.com/Behnood1368/Iptv/refs/heads/main/Kodi.m3u';

    $categoriesFile = __DIR__ . "/channels/get_vod_categories.json";
    $streamsFile    = __DIR__ . "/channels/get_vod_streams.json";
    $m3uSavePath    = __DIR__ . '/channels/vod_playlist.m3u8';

    // دریافت لیست M3U
    $playlist = fetchRemoteVodContent($playlistUrl);
    
    // در صورت بروز خطا، بازگشت به کش قبلی
    if ($playlist === false || empty(trim($playlist))) {
        if (file_exists($streamsFile)) {
            return json_decode(file_get_contents($streamsFile), true);
        }
        die(json_encode(["error" => "Failed to fetch remote VOD M3U playlist."]));
    }

    if (!is_dir(__DIR__ . '/channels')) {
        mkdir(__DIR__ . '/channels', 0775, true);
    }
    file_put_contents($m3uSavePath, $playlist);

    $lines = explode("\n", $playlist);

    $parsedData  = [];
    $categories  = [];
    $categoryMap = [];
    $catCounter  = 500; // شروع ID دسته‌بندی فیلم‌ها از ۵۰۰
    $usedIds     = [];
    
    $currentVod = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        if (strpos($line, "#EXTINF:") === 0) {
            $attrs = [];
            if (preg_match_all('/([\w\-]+)\s*=\s*"([^"]*)"/', $line, $m)) {
                foreach ($m[1] as $i => $key) {
                    $attrs[strtolower($key)] = $m[2][$i];
                }
            }

            $vodName = '';
            if (preg_match('/,(.*)$/', $line, $m)) {
                $vodName = trim($m[1]);
            }

            $logo  = $attrs['tvg-logo'] ?? '';
            $group = trim($attrs['group-title'] ?? 'فیلم و مستند');

            if (!empty($vodName)) {
                // مدیریت دسته‌بندی VOD
                if (!isset($categoryMap[$group])) {
                    $categoryMap[$group] = (string)$catCounter;
                    $categories[] = [
                        "category_id"   => (string)$catCounter,
                        "category_name" => $group,
                        "parent_id"     => 0
                    ];
                    $catCounter++;
                }

                $streamId = vodIdFromName($vodName);
                while (isset($usedIds[$streamId])) {
                    $streamId = ($streamId + 1) % 1000000000;
                }
                $usedIds[$streamId] = true;

                // ساختار استاندارد VOD طبق پروتکل Xtream Codes
                $currentVod = [
                    "num"             => $streamId,
                    "name"            => $vodName,
                    "stream_type"     => "movie",
                    "stream_id"       => $streamId,
                    "stream_icon"     => $logo,
                    "rating"          => "5.0",
                    "rating_5based"   => 2.5,
                    "added"           => (string)time(),
                    "category_id"     => $categoryMap[$group],
                    "container_extension" => "mp4",
                    "custom_sid"      => "",
                    "direct_source"   => "",
                    "video_url"       => ""
                ];
            }
        } 
        elseif (strpos($line, "#") !== 0 && $currentVod !== null) {
            $currentVod["direct_source"] = $line;
            $currentVod["video_url"]     = $line;

            // تشخیص پسوند لینک ویدیو
            $pathInfo = pathinfo(parse_url($line, PHP_URL_PATH));
            if (isset($pathInfo['extension']) && !empty($pathInfo['extension'])) {
                $currentVod["container_extension"] = strtolower($pathInfo['extension']);
            }

            $parsedData[] = $currentVod;
            $currentVod = null;
        }
    }

    file_put_contents($categoriesFile, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents($streamsFile, json_encode($parsedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $parsedData;
}

/**
 * تابع پردازش لیست کانال‌های زنده (Live Channels)
 */
function runLivePlaylistGenerate() {
    $playlistUrl = 'https://raw.githubusercontent.com/Behnood1368/Iptv/refs/heads/main/Kodi.m3u';
    $categoriesFile = __DIR__ . "/channels/get_live_categories.json";
    
    $playlist = fetchRemoteVodContent($playlistUrl);
    if ($playlist === false || empty(trim($playlist))) {
        return [];
    }

    $lines = explode("\n", $playlist);
    $parsedData  = [];
    $categories  = [];
    $categoryMap = [];
    $catCounter  = 1;
    $usedIds     = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if (strpos($line, "#EXTINF:") === 0) {
            $attrs = [];
            if (preg_match_all('/([\w\-]+)\s*=\s*"([^"]*)"/', $line, $m)) {
                foreach ($m[1] as $i => $key) {
                    $attrs[$key] = $m[2][$i];
                }
            }
            $channelName = '';
            if (preg_match('/,(.*)$/', $line, $m)) {
                $channelName = $m[1];
            }

            $epgId = $attrs['tvg-id']   ?? '';
            $logo  = $attrs['tvg-logo'] ?? '';
            $group = trim($attrs['group-title'] ?? 'Uncategorized');

            if ($channelName && $epgId && $group) {
                if (!isset($categoryMap[$group])) {
                    $categoryMap[$group] = $catCounter++;
                    $categories[] = [
                        "category_id"   => (string)$categoryMap[$group],
                        "category_name" => $group,
                        "parent_id"     => 0
                    ];
                }

                $streamId = channelIdFromName($channelName);
                while (isset($usedIds[$streamId])) {
                    $streamId = ($streamId + 1) % 1000000000;
                }
                $usedIds[$streamId] = true;

                $parsedData[] = [
                    "num"                => $streamId,
                    "name"               => trim($channelName),
                    "stream_type"        => "live",
                    "stream_id"          => $streamId,
                    "stream_icon"        => $logo,
                    "epg_channel_id"     => $epgId,
                    "added"              => time(),
                    "category_id"        => $categoryMap[$group],
                    "custom_sid"         => "",
                    "tv_archive"         => 0,
                    "direct_source"      => "",
                    "tv_archive_duration"=> 0,
                    "video_url"          => ""
                ];
            }
        } elseif ($line && strpos($line, "#") !== 0) {
            $idx = count($parsedData) - 1;
            if ($idx >= 0) {
                $parsedData[$idx]["direct_source"] = $line;
                $parsedData[$idx]["video_url"]     = $line;
            }
        }
    }

    if (!is_dir(dirname($categoriesFile))) {
        mkdir(dirname($categoriesFile), 0777, true);
    }
    file_put_contents($categoriesFile, json_encode($categories, JSON_PRETTY_PRINT));
    file_put_contents(__DIR__ . '/channels/live_playlist.json', json_encode($parsedData, JSON_PRETTY_PRINT));

    return $parsedData;
}

// اجرا در حالت CLI یا درخواست مستقیم HTTP
if (php_sapi_name() === "cli" || isset($_GET["debug"]) || isset($_GET["run"])) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(runVodPlaylistGenerate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
