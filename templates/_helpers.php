<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function date_fmt(?string $iso, string $fmt = 'M j, Y'): string
{
    if (!$iso) {
        return '';
    }

    $timestamp = strtotime($iso);

    return $timestamp ? date($fmt, $timestamp) : '';
}

function url_for(string $base, array $params = []): string
{
    return $params ? $base . '?' . http_build_query($params) : $base;
}
