<?php

namespace App\Services\Gateways;

use App\Models\Deposit;

interface PaymentGateway
{
    public function key(): string;

    public function label(): string;

    /** Whether credentials are present, so the channel can be hidden if not. */
    public function isConfigured(): bool;

    /**
     * Start a payment and return the URL to send the user to.
     */
    public function initiate(Deposit $deposit, string $callbackUrl): string;

    /**
     * Ask the gateway what actually happened. Called on callback and webhook --
     * never trust the amount or status the browser hands back.
     */
    public function verify(string $reference): GatewayResult;
}
