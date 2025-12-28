<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Circuit Breaker voor OpenAI API calls
 *
 * Voorkomt dat 20+ affiliate sites de OpenAI API blijven bombarderen bij outages.
 * Beschermt server resources en voorkomt cascade failures.
 */
class OpenAICircuitBreaker
{
    private const CACHE_KEY = 'openai_circuit_breaker';
    private const FAILURE_THRESHOLD = 5; // Aantal failures voordat circuit opent
    private const RECOVERY_TIMEOUT = 300; // 5 minuten wachttijd voordat we opnieuw proberen
    private const SUCCESS_THRESHOLD = 2; // Aantal successen om circuit te sluiten

    /**
     * Check of circuit open is (API calls geblokkeerd)
     */
    public function isOpen(): bool
    {
        $state = $this->getState();

        if ($state['status'] === 'open') {
            // Check of recovery timeout voorbij is
            if (now()->timestamp >= $state['open_until']) {
                $this->halfOpen();
                return false; // Probeer het opnieuw
            }
            return true; // Nog steeds open
        }

        return false;
    }

    /**
     * Registreer een failure
     */
    public function recordFailure(): void
    {
        $state = $this->getState();
        $state['failures']++;
        $state['last_failure'] = now()->timestamp;

        // Check of we threshold bereikt hebben
        if ($state['failures'] >= self::FAILURE_THRESHOLD) {
            $state['status'] = 'open';
            $state['open_until'] = now()->addSeconds(self::RECOVERY_TIMEOUT)->timestamp;

            \Log::critical('OpenAI Circuit Breaker OPEN - API calls geblokkeerd', [
                'failures' => $state['failures'],
                'open_until' => date('Y-m-d H:i:s', $state['open_until'])
            ]);
        }

        $this->setState($state);
    }

    /**
     * Registreer een success
     */
    public function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state['status'] === 'half_open') {
            $state['consecutive_successes']++;

            // Genoeg successen om circuit te sluiten
            if ($state['consecutive_successes'] >= self::SUCCESS_THRESHOLD) {
                $this->close();

                \Log::info('OpenAI Circuit Breaker CLOSED - normale operatie hervat', [
                    'consecutive_successes' => $state['consecutive_successes']
                ]);

                return;
            }
        } else {
            // Reset failure counter bij success
            $state['failures'] = 0;
            $state['consecutive_successes'] = 0;
        }

        $this->setState($state);
    }

    /**
     * Zet circuit in half-open state (testing recovery)
     */
    private function halfOpen(): void
    {
        $state = $this->getState();
        $state['status'] = 'half_open';
        $state['consecutive_successes'] = 0;

        \Log::info('OpenAI Circuit Breaker HALF-OPEN - testing recovery');

        $this->setState($state);
    }

    /**
     * Sluit circuit (normale operatie)
     */
    private function close(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Krijg huidige state
     */
    private function getState(): array
    {
        return Cache::get(self::CACHE_KEY, [
            'status' => 'closed',
            'failures' => 0,
            'consecutive_successes' => 0,
            'last_failure' => null,
            'open_until' => null,
        ]);
    }

    /**
     * Set state
     */
    private function setState(array $state): void
    {
        Cache::put(self::CACHE_KEY, $state, now()->addHours(1));
    }

    /**
     * Get huidige status voor monitoring
     */
    public function getStatus(): array
    {
        $state = $this->getState();

        return [
            'status' => $state['status'],
            'failures' => $state['failures'],
            'is_blocked' => $this->isOpen(),
            'last_failure' => $state['last_failure'] ? date('Y-m-d H:i:s', $state['last_failure']) : null,
            'open_until' => $state['open_until'] ? date('Y-m-d H:i:s', $state['open_until']) : null,
        ];
    }

    /**
     * Manueel reset (admin functie)
     */
    public function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
        \Log::info('OpenAI Circuit Breaker manually reset');
    }
}
