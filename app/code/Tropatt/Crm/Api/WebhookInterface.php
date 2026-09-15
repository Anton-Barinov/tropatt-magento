<?php

namespace Tropatt\Crm\Api;

/**
 * Inbound CRM webhook service contract (`POST /V1/tropatt/webhook`).
 */
interface WebhookInterface
{
    /**
     * @param string $payload JSON body produced by the CRM.
     * @param string $signature Base64 HMAC-SHA256 signature of `timestamp . '.' . payload`.
     * @param string $timestamp Unix timestamp of the request.
     * @return string JSON response.
     */
    public function execute($payload, $signature = '', $timestamp = '');
}
