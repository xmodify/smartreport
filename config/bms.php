<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BMS Ecosystem Endpoints
    |--------------------------------------------------------------------------
    |
    | Base URLs for BMS Session Paste resolver, Central AI Gateway,
    | and HOSxP Marketplace portal.
    |
    */

    'endpoints' => [
        'session_paste' => env('BMS_SESSION_PASTE_URL', 'https://hosxp.net/phapi/PasteJSON'),
        'ai_gateway' => env('BMS_AI_GATEWAY_URL', 'https://ai-api.kube.bmscloud.in.th'),
        'marketplace' => env('BMS_MARKETPLACE_URL', 'https://hosxp-marketplace.bmscloud.in.th'),
    ],

    'app_name' => env('BMS_APP_NAME', 'smartdata'),
    'verify_ssl' => env('BMS_VERIFY_SSL', false),

    /*
    |--------------------------------------------------------------------------
    | Hospital Multi-Tenant Configuration
    |--------------------------------------------------------------------------
    |
    | Default hospital code and maximum poster count per hospital.
    | Each hospital is isolated based on their opdconfig.hospitalcode.
    |
    */

    'default_hcode' => env('BMS_DEFAULT_HCODE', null),
    'default_hname' => env('BMS_DEFAULT_HNAME', null),
    'max_posters_per_hospital' => (int) env('BMS_MAX_POSTERS_PER_HOSPITAL', 8),

    /*
    |--------------------------------------------------------------------------
    | HOSxP Marketplace Credentials
    |--------------------------------------------------------------------------
    |
    | API Key registered on HOSxP Marketplace for this Addon (hxp_...).
    | Required for requesting write tokens (mkt_...) and storage access.
    |
    */

    'addon_key' => env('BMS_ADDON_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | HTTP Request Timeouts (in seconds)
    |--------------------------------------------------------------------------
    |
    | Tailored timeouts for each specific BMS service domain.
    | AI Chat completions allows up to 290s before hitting ingress timeout (300s).
    |
    */

    'timeouts' => [
        'session_paste' => 10,
        'sql' => 60,
        'rest' => 60,
        'function' => 15,
        'ai_chat' => 290,
        'ai_audio' => 60,
        'ai_ocr' => 60,
        'marketplace' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Tracking & Cookie Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for storing client workstation device keys in cookies
    | to remember sessions across browser restarts.
    |
    */

    'device_cookie' => [
        'name' => 'bms_device_key',
        'lifetime' => 43200, // 30 days in minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Default AI Models
    |--------------------------------------------------------------------------
    |
    | Default models to use when not explicitly specified in requests.
    |
    */

    'ai_defaults' => [
        'chat_model' => env('BMS_AI_CHAT_MODEL', 'deepseek-chat'),
        'asr_model' => env('BMS_AI_ASR_MODEL', 'Qwen/Qwen3-ASR-1.7B'),
        'tts_model' => env('BMS_AI_TTS_MODEL', 'voxcpm-thai'),
    ],
];
