#!/usr/bin/env php
<?php

if ($argc < 2) {
    echo "Usage: php bin/pinger.php <csv_file> [base_url] [global_headers]\n";
    echo "Example: php bin/pinger.php data/url_tests.csv http://localhost:4000 \"Authorization: Bearer token123|X-Api-Key: xyz\"\n";
    exit(1);
}

$csvFile = $argv[1];
$baseUrl = $argv[2] ?? 'http://localhost:3000';
$baseUrl = rtrim($baseUrl, '/');
$globalHeadersArg = $argv[3] ?? '';

if (!file_exists($csvFile)) {
    fwrite(STDERR, "\e[31mError: The CSV file does not exist: {$csvFile}\e[0m\n");
    exit(1);
}

$file = fopen($csvFile, 'r');
if (!$file) {
    fwrite(STDERR, "\e[31mError: Could not open the CSV file: {$csvFile}\e[0m\n");
    exit(1);
}

$headers = @fgetcsv($file);
// Check if the first line is the actual header
if ($headers && strtolower($headers[0]) !== 'method') {
    // If it is not a header (doesn't start with 'method'), rewind the file pointer to the beginning
    rewind($file);
}

// Parse global headers
$globalHeaders = [];
if ($globalHeadersArg !== '') {
    $parts = explode('|', $globalHeadersArg);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $globalHeaders[] = $part;
        }
    }
}

$total = 0;
$passed = 0;
$failed = 0;

echo "\n\e[33m--- Running URL Tests with Pinger ---\e[0m\n";
echo "CSV File: {$csvFile}\n";
echo "Base URL: {$baseUrl}\n";
if (!empty($globalHeaders)) {
    echo "Headers:  " . implode(', ', $globalHeaders) . "\n";
}
echo "\n";

while (($row = @fgetcsv($file)) !== false) {
    if (empty($row) || count($row) < 3) {
        continue;
    }

    $method = strtoupper(trim($row[0]));
    $path = '/' . ltrim(trim($row[1]), '/');
    $expectedStatus = (int)trim($row[2]);
    $containsText = isset($row[3]) ? trim($row[3]) : '';
    $payload = isset($row[4]) ? trim($row[4]) : '';

    $url = $baseUrl . $path;
    $total++;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Do not follow redirects to allow validation of 3xx codes
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Parina-Pinger/1.0');

    // Configure headers (global + payload)
    $httpHeaders = $globalHeaders;
    if ($payload !== '') {
        $httpHeaders[] = 'Content-Type: application/json';
        $httpHeaders[] = 'Content-Length: ' . strlen($payload);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    if (!empty($httpHeaders)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo sprintf("[%s] %s -> ", $method, $path);

    if ($response === false) {
        $failed++;
        echo "\e[31mFAILED\e[0m (Connection/cURL Error: {$error})\n";
        continue;
    }

    $statusMatches = ($statusCode === $expectedStatus);
    $textMatches = true;

    if ($containsText !== '') {
        $textMatches = (stripos($response, $containsText) !== false);
    }

    if ($statusMatches && $textMatches) {
        $passed++;
        echo "\e[32mOK\e[0m (Status: {$statusCode}";
        if ($containsText !== '') {
            echo ", Contains: '{$containsText}'";
        }
        echo ")\n";
    } else {
        $failed++;
        echo "\e[31mFAILED\e[0m (Received Status: {$statusCode}, Expected: {$expectedStatus}";
        if (!$textMatches) {
            echo " | Does not contain expected text: '{$containsText}'";
        }
        echo ")\n";
    }
}

fclose($file);

echo "\n\e[33m--- Results Summary ---\e[0m\n";
echo "Total tests: {$total}\n";
echo "Passed:      \e[32m{$passed}\e[0m\n";
echo "Failed:      " . ($failed > 0 ? "\e[31m{$failed}\e[0m" : "0") . "\n\n";

exit($failed > 0 ? 1 : 0);
