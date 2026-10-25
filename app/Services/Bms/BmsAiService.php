<?php

namespace App\Services\Bms;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class BmsAiService
{
    /**
     * Get Central AI Gateway Base URL.
     */
    protected function getBaseUrl(): string
    {
        return rtrim(Config::get('bms.endpoints.ai_gateway', 'https://ai-api.kube.bmscloud.in.th'), '/');
    }

    /**
     * Build HTTP pending request with SSL verify settings.
     */
    protected function client(): PendingRequest
    {
        $verifySsl = (bool) Config::get('bms.verify_ssl', false);

        return Http::withOptions(['verify' => $verifySsl]);
    }

    /**
     * Fetch dynamic list of available AI models.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getModels(): array
    {
        $response = $this->client()->timeout(10)->get("{$this->getBaseUrl()}/v1/models");

        if (! $response->successful()) {
            throw new Exception("Unable to fetch AI models: HTTP {$response->status()}");
        }

        return $response->json()['data'] ?? [];
    }

    /**
     * Send LLM Chat Completions request (OpenAI standard).
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     *
     * @throws Exception
     */
    public function chat(
        string $bmsSessionId,
        array $messages,
        ?string $model = null,
        float $temperature = 0.3,
        int $maxTokens = 1500
    ): string {
        $model = $model ?? Config::get('bms.ai_defaults.chat_model', 'deepseek-chat');
        $timeout = (int) Config::get('bms.timeouts.ai_chat', 290);

        $response = $this->client()->withToken($bmsSessionId)
            ->timeout($timeout)
            ->post("{$this->getBaseUrl()}/v1/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);

        if (! $response->successful()) {
            throw new Exception('BMS AI Chat failed: '.$response->body());
        }

        $res = $response->json();

        return $res['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Speech-to-Text: Transcribe Thai audio recording into text.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function transcribe(string $bmsSessionId, string $audioPath, ?string $model = null): array
    {
        $model = $model ?? Config::get('bms.ai_defaults.asr_model', 'Qwen/Qwen3-ASR-1.7B');
        $timeout = (int) Config::get('bms.timeouts.ai_audio', 60);

        if (! file_exists($audioPath)) {
            throw new Exception("Audio file not found at path: {$audioPath}");
        }

        $response = $this->client()->withToken($bmsSessionId)
            ->timeout($timeout)
            ->attach('file', file_get_contents($audioPath), basename($audioPath))
            ->post("{$this->getBaseUrl()}/v1/audio/transcriptions", [
                'model' => $model,
                'language' => 'th',
                'response_format' => 'json',
            ]);

        if (! $response->successful()) {
            throw new Exception('BMS Audio Transcription failed: '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Normalize Thai text before Text-to-Speech (reads dates, times, units phonetically).
     *
     * @throws Exception
     */
    public function normalizeText(string $bmsSessionId, string $text): string
    {
        $timeout = (int) Config::get('bms.timeouts.ai_audio', 60);

        $response = $this->client()->withToken($bmsSessionId)
            ->timeout($timeout)
            ->post("{$this->getBaseUrl()}/v1/audio/normalize", [
                'text' => $text,
            ]);

        if (! $response->successful()) {
            throw new Exception('BMS Audio Normalization failed: '.$response->body());
        }

        $res = $response->json();

        return $res['normalized'] ?? $res['text'] ?? $text;
    }

    /**
     * Text-to-Speech: Synthesize text into natural Thai spoken MP3 audio.
     *
     * @return string Raw MP3 binary content
     *
     * @throws Exception
     */
    public function textToSpeech(
        string $bmsSessionId,
        string $text,
        string $voice = 'female',
        ?string $model = null
    ): string {
        $model = $model ?? Config::get('bms.ai_defaults.tts_model', 'voxcpm-thai');
        $timeout = (int) Config::get('bms.timeouts.ai_audio', 60);

        $response = $this->client()->withToken($bmsSessionId)
            ->timeout($timeout)
            ->post("{$this->getBaseUrl()}/v1/audio/speech", [
                'model' => $model,
                'input' => $text,
                'voice' => $voice,
                'response_format' => 'mp3',
            ]);

        if (! $response->successful()) {
            throw new Exception('BMS TTS Synthesis failed: '.$response->body());
        }

        return $response->body();
    }

    /**
     * ICD-11 Semantic Diagnosis Lookup from clinical notes.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function lookupIcd11(string $bmsSessionId, string $clinicalNote, int $topK = 5): array
    {
        $timeout = (int) Config::get('bms.timeouts.ai_ocr', 30);

        $response = $this->client()->withToken($bmsSessionId)
            ->timeout($timeout)
            ->post("{$this->getBaseUrl()}/v1/icd11/lookup", [
                'symptom' => $clinicalNote,
                'text' => $clinicalNote,
                'limit' => $topK,
                'top_k' => $topK,
            ]);

        if (! $response->successful()) {
            throw new Exception('ICD-11 Lookup failed: '.$response->body());
        }

        $data = $response->json();

        return $data['results'] ?? $data['data'] ?? [];
    }

    /**
     * Extract structured data from medical documents and lab slips.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function extractOcr(string $bmsSessionId, string $filePath, string $type = 'medical_lab'): array
    {
        $timeout = (int) Config::get('bms.timeouts.ai_ocr', 60);

        if (! file_exists($filePath)) {
            throw new Exception("OCR target file not found: {$filePath}");
        }

        $response = $this->client()->withToken($bmsSessionId)
            ->timeout($timeout)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post("{$this->getBaseUrl()}/v1/ocr/extract", [
                'type' => $type,
            ]);

        if (! $response->successful()) {
            throw new Exception('Medical OCR extraction failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
