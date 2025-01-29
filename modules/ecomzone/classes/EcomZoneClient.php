<?php

class EcomZoneClient
{
    private $apiToken;
    private $apiUrl;

    public function __construct()
    {
        // Hardcoded API token for testing
        $this->apiToken = 'klRyAdrXaxL0s6PEUp7LDlH6T8aPSCtBY8NiEHsHiWpc6646K2TZPi5KMxUg';
        $this->apiUrl = Configuration::get('ECOMZONE_API_URL');
        
        if (empty($this->apiUrl)) {
            throw new Exception('API URL not configured');
        }
    }

    public function getCatalog($perPage = 1000)
    {
        $url = $this->apiUrl . '/catalog';
        if ($perPage) {
            $url .= '?per_page=' . $perPage;
        }
        
        EcomZoneLogger::log("Making API request", 'INFO', ['url' => $url]);
        return $this->makeRequest('GET', $url);
    }

    public function getProduct($sku)
    {
        return $this->makeRequest('GET', $this->apiUrl . '/product/' . $sku);
    }

    public function createOrder($orderData)
    {
        return $this->makeRequest('POST', $this->apiUrl . '/ordering', $orderData);
    }

    public function getOrder($orderId)
    {
        return $this->makeRequest('GET', $this->apiUrl . '/order/' . $orderId);
    }

    private function makeRequest($method, $url, $data = null)
    {
        $curl = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false, // Temporarily disable SSL verification for testing
        ];

        if ($data !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        EcomZoneLogger::log("API Response", 'INFO', [
            'url' => $url,
            'method' => $method,
            'http_code' => $httpCode,
            'curl_error' => $err,
            'response' => $response
        ]);

        curl_close($curl);

        if ($err) {
            throw new Exception('cURL Error: ' . $err);
        }

        if ($httpCode >= 400) {
            throw new Exception('API Error: HTTP ' . $httpCode . ' - ' . $response);
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }
} 