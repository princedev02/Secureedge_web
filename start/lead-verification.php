<?php
// Same-origin transport only. Apps Script remains responsible for OTPs and leads.
const SE_LEAD_WEB_APP = 'https://script.google.com/macros/s/AKfycbx5jipRke6udEwJ0_KxVRZinh4laT7j1b9cWQm7Dz1VfF2JL2izEYvOFUY3tiJ_2-4Piw/exec';

function se_relay_failure($status, $message)
{
    return [$status, ['success' => false, 'message' => $message]];
}

function se_relay_content_url($url)
{
    $parts = parse_url($url);
    return is_array($parts)
        && ($parts['scheme'] ?? '') === 'https'
        && ($parts['host'] ?? '') === 'script.googleusercontent.com'
        && ($parts['path'] ?? '') === '/macros/echo'
        && !isset($parts['user']) && !isset($parts['pass'])
        && !isset($parts['port']) && !isset($parts['fragment']);
}

function se_relay_http($url, $body, $seconds)
{
    $content = '';
    $location = '';
    $curl = curl_init($url);
    if ($curl === false) return ['status' => 0, 'body' => '', 'location' => '', 'error' => true];
    $options = [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => min(10, $seconds),
        CURLOPT_TIMEOUT => $seconds,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        // No browser Origin, Referer, cookies, or credentials are forwarded.
        CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$location) {
            if (stripos($line, 'Location:') === 0) $location = trim(substr($line, 9));
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => function ($handle, $chunk) use (&$content) {
            if (strlen($content) + strlen($chunk) > 65536) return 0;
            $content .= $chunk;
            return strlen($chunk);
        },
    ];
    // Windows PHP may lack a configured CA bundle; use its trusted OS store.
    // Certificate and hostname verification remain enabled.
    if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
        $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
    }
    if ($body !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $body;
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($curl, $options);
    $ok = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return ['status' => $status, 'body' => $content, 'location' => $location, 'error' => $ok === false];
}

function se_relay_handle($method, $contentType, $body, $request)
{
    if ($method !== 'POST') return se_relay_failure(405, 'Please use the verification form.');
    if (strtolower(trim(explode(';', $contentType)[0])) !== 'application/x-www-form-urlencoded') {
        return se_relay_failure(415, 'Unsupported verification request format.');
    }
    if (strlen($body) > 16000) return se_relay_failure(413, 'The request is too large.');
    parse_str($body, $values);
    foreach ($values as $value) {
        if (!is_string($value)) return se_relay_failure(400, 'Invalid verification request.');
    }
    if (!in_array($values['action'] ?? '', ['sendOtp', 'verifyOtp', 'submitLead'], true)) {
        return se_relay_failure(400, 'Invalid request action.');
    }
    $deadline = microtime(true) + 35;
    // Dispatch the POST once. Never retry an ambiguous email or CRM operation.
    $response = $request(SE_LEAD_WEB_APP, $body, 35);
    if ($response['error']) return se_relay_failure(502, 'The verification service could not be reached.');
    if (in_array($response['status'], [301, 302, 303], true)) {
        if (!se_relay_content_url($response['location'])) {
            return se_relay_failure(502, 'The verification deployment returned an unexpected redirect.');
        }
        $remaining = (int) ceil($deadline - microtime(true));
        if ($remaining < 1) return se_relay_failure(504, 'The verification service took too long to respond.');
        // Google ContentService redirects to a one-time GET response URL.
        $response = $request($response['location'], null, $remaining);
    }
    if ($response['error'] || $response['status'] < 200 || $response['status'] >= 300) {
        return se_relay_failure(502, 'The verification service response could not be confirmed.');
    }
    $result = json_decode($response['body'], true);
    if (!is_array($result) || !isset($result['success'], $result['message'])
        || !is_bool($result['success']) || !is_string($result['message'])) {
        return se_relay_failure(502, 'The verification deployment returned an unreadable response.');
    }
    // Keep the backend contract, including verificationToken and retryAfter.
    return [200, $result];
}

if (!defined('SE_RELAY_TEST')) {
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    if (!function_exists('curl_init')) {
        $result = se_relay_failure(503, 'The verification relay needs PHP cURL enabled.');
    } else {
        try {
            $input = fopen('php://input', 'rb');
            $body = $input ? stream_get_contents($input, 16001) : false;
            if ($input) fclose($input);
            $result = $body === false ? se_relay_failure(400, 'Invalid verification request.')
                : se_relay_handle($_SERVER['REQUEST_METHOD'] ?? '', $_SERVER['CONTENT_TYPE'] ?? '', $body, 'se_relay_http');
        } catch (Throwable $error) {
            // No upstream HTML, exception details, email, OTP, or token is exposed.
            $result = se_relay_failure(502, 'The verification service could not complete your request.');
        }
    }
    http_response_code($result[0]);
    if ($result[0] === 405) header('Allow: POST');
    echo json_encode($result[1]);
}
