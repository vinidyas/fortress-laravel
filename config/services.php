<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bradesco_boleto' => [
        'base_url' => env('BRADESCO_BASE_URL'),
        'client_id' => env('BRADESCO_CLIENT_ID'),
        'client_secret' => env('BRADESCO_CLIENT_SECRET'),
        'cert_path' => env('BRADESCO_TLS_CERT_PATH', env('BRADESCO_CERT_PATH')),
        'cert_password' => env('BRADESCO_TLS_CERT_PASSWORD', env('BRADESCO_CERT_PASSWORD')),
        'key_path' => env('BRADESCO_TLS_KEY_PATH'),
        'key_password' => env('BRADESCO_TLS_KEY_PASS'),
        'webhook_secret' => env('BRADESCO_WEBHOOK_SECRET'),
        'environment' => env('BRADESCO_ENV', 'sandbox'),
        'timeout' => (int) env('BRADESCO_TIMEOUT', 10),
        'fake' => (bool) env('BRADESCO_USE_FAKE', false),
        'id_produto' => env('BRADESCO_ID_PRODUTO'),
        'negociacao' => env('BRADESCO_NEGOCIACAO'),
        'convenio' => env('BRADESCO_CONVENIO'),
        'cod_especie' => env('BRADESCO_COD_ESPECIE'),
        'cnpj_raiz' => env('BRADESCO_CNPJ_RAIZ'),
        'cnpj_filial' => env('BRADESCO_CNPJ_FILIAL'),
        'cnpj_controle' => env('BRADESCO_CNPJ_CONTROLE'),
        'codigo_usuario' => env('BRADESCO_CODIGO_USUARIO'),
        'registra_titulo' => env('BRADESCO_REGISTRA_TITULO', 'S'),
        'tipo_vencimento' => env('BRADESCO_TP_VENCIMENTO', '0'),
        'indicador_moeda' => env('BRADESCO_INDICADOR_MOEDA', '1'),
        'quantidade_moeda' => env('BRADESCO_QTDE_MOEDA', '00000000000000000'),
        'tp_protesto' => env('BRADESCO_TP_PROTESTO', '0'),
        'prazo_protesto' => env('BRADESCO_PRAZO_PROTESTO', '00'),
        'tipo_decurso' => env('BRADESCO_TIPO_DECURSO', '0'),
        'tipo_dias_decurso' => env('BRADESCO_TIPO_DIAS_DECURSO', '0'),
        'tipo_prazo_tres' => env('BRADESCO_TIPO_PRAZO_TRES', '000'),
    ],

];
