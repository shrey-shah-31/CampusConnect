<?php
function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function json_response(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function cc_format_inr_compact(float $inr): string {
    $value = max(0.0, $inr);
    if ($value >= 10000000.0) return '₹' . rtrim(rtrim(number_format($value / 10000000.0, 2, '.', ''), '0'), '.') . 'Cr';
    if ($value >= 100000.0) return '₹' . rtrim(rtrim(number_format($value / 100000.0, 1, '.', ''), '0'), '.') . 'L';
    return '₹' . number_format($value, 0, '.', ',');
}

function cc_parse_usd_amount(string $raw): ?float {
    $s = strtolower(trim($raw));
    if ($s === '') return null;
    if (!preg_match('/(\d+(?:\.\d+)?)/', $s, $m)) return null;
    $n = (float)$m[1];
    $mult = 1.0;
    if (preg_match('/\b(m|mn)\b/', $s)) $mult = 1000000.0;
    elseif (str_contains($s, 'k')) $mult = 1000.0;
    return $n * $mult;
}

function cc_format_compensation_inr(?string $value, float $usdToInr = 83.0): string {
    $v = trim((string)$value);
    if ($v === '') return '';

    // Already INR/rupee-ish.
    if (str_contains($v, '₹') || str_contains(strtolower($v), 'inr')) return $v;

    // Unit-based INR strings (LPA/Lakh/Cr): ensure ₹ prefix.
    $lower = strtolower($v);
    if (preg_match('/\b(lpa|lakh|lac|cr|crore)\b/', $lower)) {
        return str_starts_with($v, '₹') ? $v : ('₹' . $v);
    }

    // USD ranges like "$120k - $160k"
    if (str_contains($v, '$')) {
        $parts = preg_split('/\s*(?:-|to)\s*/i', str_replace('$', '', $v)) ?: [];
        $numbers = [];
        foreach ($parts as $p) {
            $usd = cc_parse_usd_amount($p);
            if ($usd !== null) $numbers[] = $usd * $usdToInr;
        }
        if (count($numbers) >= 2) return cc_format_inr_compact($numbers[0]) . ' - ' . cc_format_inr_compact($numbers[1]);
        if (count($numbers) === 1) return cc_format_inr_compact($numbers[0]);
    }

    // Plain numeric => INR amount.
    if (preg_match('/^\d+(?:\.\d+)?$/', $v)) return cc_format_inr_compact((float)$v);

    // Fallback: make it explicit INR.
    return str_starts_with($v, '₹') ? $v : ('₹' . $v);
}
