<?php
$redis = new Redis();
$redis->connect('127.0.0.1');
$mc = false;
$dungeons = false;
$cache["mc"] = $redis->get("api.mojang.metric.mc");
$cache["dungeons"] = $redis->get("api.mojang.metric.dungeons");
$useProxy = $_GET["proxy"] ?? false;
if ($useProxy == null)
    $useProxy = true;
if ($cache["mc"])
    $mc = json_decode($cache["mc"]);
if ($cache["dungeons"])
    $dungeons = json_decode($cache["dungeons"]);
try {
    if (!$mc) {
        // Flush MC data
        $payload = "{
    \"metricKeys\": [
        \"item_sold_minecraft\",
        \"prepaid_card_redeemed_minecraft\"
    ]
}";
        $mc = posturl("https://api.mojang.com/orders/statistics", $payload, $useProxy);
        $mcJson = json_encode($mc, true);
        if (!$mcJson)
            throw new Exception("Failed to pull mc stats");
        $redis->set("api.mojang.metric.mc", $mcJson, 600);
    }

    if (!$dungeons) {
        $payload = "{
    \"metricKeys\": [
        \"item_sold_dungeons\"
    ]
}";
        $dungeons = posturl("https://api.mojang.com/orders/statistics", $payload, $useProxy);
        $dungeonsJson = json_encode($dungeons, true);
        if (!$dungeonsJson)
            throw new Exception("Failed to pull dungeons stats");
        $redis->set("api.mojang.metric.dungeons", $dungeonsJson, 600);
    }

    $response["minecraft"] = $mc;
    $response["dungeons"] = $dungeons;
    header("Content-Type: application/json;charset=UTF-8");
    header("Cache-Control: max-age=600");
    echo(json_encode($response));
} catch (Exception $e) {
    $content["error"] = $e->getMessage();
    header("HTTP/1.1 503 Service Unavailable");
    header("Content-Type: application/json;charset=UTF-8");
    header("Cache-Control: no-cache");
    echo(json_encode($content));
    exit(503);
}

function geturl($url, $useProxy)
{
    $headerArray = array("Content-type:application/json;", "Accept:application/json");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 10000);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 25000);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
    if ($useProxy) {
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        curl_setopt($ch, CURLOPT_PROXY, "127.0.0.1:1080");
    }
    $output = curl_exec($ch);
    curl_close($ch);
    return json_decode($output, true);
}


function posturl($url, $data, $useProxy)
{
    $headerArray = array("Content-type:application/json;charset='utf-8'", "Accept:application/json");
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 10000);
    curl_setopt($curl, CURLOPT_TIMEOUT_MS, 25000);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headerArray);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    if ($useProxy) {
        curl_setopt($curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        curl_setopt($curl, CURLOPT_PROXY, "127.0.0.1:1080");
    }
    $out = curl_exec($curl);
    curl_close($curl);
    return json_decode($out, true);
}
