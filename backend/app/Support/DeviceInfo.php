<?php

namespace App\Support;

/**
 * Lê o User-Agent e devolve navegador, sistema e tipo de aparelho.
 * Observação: o navegador NÃO informa o nome (hostname) do computador; o nome que importa para o negócio é o do
 * dispositivo cadastrado pelo RH (ver TimeDevice).
 */
class DeviceInfo
{
    /** @return array{browser: string, os: string, type: string, label: string} */
    public static function parse(?string $ua): array
    {
        $ua = (string) $ua;

        $browser = 'Navegador desconhecido';
        if (preg_match('~Edg(?:e|A|iOS)?/(\d+)~', $ua, $m)) {
            $browser = "Edge {$m[1]}";
        } elseif (preg_match('~OPR/(\d+)~', $ua, $m)) {
            $browser = "Opera {$m[1]}";
        } elseif (preg_match('~(?:Firefox|FxiOS)/(\d+)~', $ua, $m)) {
            $browser = "Firefox {$m[1]}";
        } elseif (preg_match('~(?:Chrome|CriOS)/(\d+)~', $ua, $m)) {
            $browser = "Chrome {$m[1]}";
        } elseif (preg_match('~Version/(\d+)[^ ]* (?:Mobile/\S+ )?Safari~', $ua, $m)) {
            $browser = "Safari {$m[1]}";
        }

        $os = 'Sistema desconhecido';
        if (preg_match('~Windows NT (\d+\.\d+)~', $ua, $m)) {
            $os = match ($m[1]) { '10.0' => 'Windows 10/11', '6.3' => 'Windows 8.1', '6.1' => 'Windows 7', default => 'Windows' };
        } elseif (preg_match('~Android (\d+)~', $ua, $m)) {
            $os = "Android {$m[1]}";
        } elseif (preg_match('~iPhone OS (\d+)~', $ua, $m)) {
            $os = "iOS {$m[1]}";
        } elseif (str_contains($ua, 'iPad')) {
            $os = 'iPadOS';
        } elseif (str_contains($ua, 'Mac OS X')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'CrOS')) {
            $os = 'ChromeOS';
        } elseif (str_contains($ua, 'Linux')) {
            $os = 'Linux';
        }

        $type = 'desktop';
        if (str_contains($ua, 'iPad') || str_contains($ua, 'Tablet') || (str_contains($ua, 'Android') && ! str_contains($ua, 'Mobile'))) {
            $type = 'tablet';
        } elseif (str_contains($ua, 'Mobi') || str_contains($ua, 'iPhone') || str_contains($ua, 'Android')) {
            $type = 'mobile';
        }

        return ['browser' => $browser, 'os' => $os, 'type' => $type, 'label' => "{$browser} · {$os} · {$type}"];
    }
}
