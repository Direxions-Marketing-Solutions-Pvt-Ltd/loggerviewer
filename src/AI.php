<?php

declare(strict_types=1);

namespace App;

class AI
{
    private string $apiUrl;
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiUrl = AI_API_URL;
        $this->model = AI_MODEL;
        // Decrypt key for usage
        $this->apiKey = \App\Encryption::decrypt(AI_API_KEY);
    }

    public function analyzeError(string $errorMessage): string|bool
    {
        if (!AI_ENABLED) {
            return "AI Analysis is disabled in settings.";
        }

        if (empty($this->apiKey)) {
            return "AI API Key is missing or could not be decrypted. Please check your settings.";
        }

        $systemPrompt = "You are an expert in NGINX / APACHE and PHP log analysis with 10+ years of experience in PHP development. "
                      . "Your goal is to provide a concise, actionable analysis for the following error log entry. "
                      . "Identify the cause and suggest a specific fix. Keep your response short and professional.";

        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Analyze this error:\n\n" . $errorMessage]
            ],
            'temperature' => 0.3,
            'max_tokens' => 300
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("AI API Request Failed: $error");
            return "Error connecting to AI service.";
        }

        if ($httpCode !== 200) {
            error_log("AI API HTTP Error ($httpCode): $response");
            return "AI Service returned an error. Please check your configuration.";
        }

        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? "Could not parse AI response.";
    }
}
