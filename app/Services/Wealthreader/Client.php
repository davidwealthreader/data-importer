<?php

/*
 * Client.php
 * Copyright (c) 2026 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\Wealthreader;

use App\Exceptions\ImporterHttpException;
use App\Services\Wealthreader\Authentication\SecretManager;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for the public Wealth Reader AIS API.
 *
 * Auth is two-step: OAuth (oauth.wealthreader.com) then refresh
 * (POST api.wealthreader.com/entities/ with api_key + code + token).
 *
 * The documented callback is ?nonce=&code= and may omit state. Persist
 * PKCE + the import-job identifier keyed by nonce (and by Firefly state
 * if we also send the job id as `state`).
 */
final class Client
{
    public const array AUTH_ERROR_CODES = [1010, 1020, 2010, 2020];

    private const string PKCE_PREFIX = 'wealthreader_pkce_';
    private const string JOB_PREFIX  = 'wealthreader_job_';

    public function listEntities(?string $country = null): array
    {
        $want = null !== $country && '' !== $country ? strtoupper($country) : '';
        $data = $this->json('GET', $this->apiBase().'/entities/');
        $raw  = $data['payload'] ?? $data['entities'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $return = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['code'])) {
                if ('' !== $want && !$this->entityMatchesCountry($item, $want)) {
                    continue;
                }
                $return[] = $item;

                continue;
            }
            foreach ($item as $sub) {
                if (is_array($sub) && isset($sub['code'])) {
                    $return[] = $sub;
                }
            }
        }

        return $return;
    }

    private function entityMatchesCountry(array $item, string $country): bool
    {
        $primary = strtoupper((string) ($item['country_code'] ?? $item['country'] ?? ''));
        if ($primary === $country) {
            return true;
        }
        $list = $item['country_code_list'] ?? [];
        if (is_string($list)) {
            $decoded = json_decode($list, true);
            $list    = is_array($decoded) ? $decoded : [];
        }
        foreach ($list as $code) {
            if (strtoupper((string) $code) === $country) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the authorize URL and stash PKCE + job id under nonce and state.
     */
    public function buildAuthorizeUrl(string $redirectUri, string $jobIdentifier, string $entityCode): string
    {
        $challenge = Pkce::generate($entityCode);
        $payload   = [
            'code_verifier' => $challenge['code_verifier'],
            'nonce'         => $challenge['nonce'],
            'entity_code'   => $entityCode,
            'redirect_uri'  => $redirectUri,
            'job'           => $jobIdentifier,
        ];
        foreach ([$challenge['nonce'], $jobIdentifier] as $key) {
            if ('' === $key) {
                continue;
            }
            session()->put(self::PKCE_PREFIX.$key, $payload);
            session()->put(self::JOB_PREFIX.$key, $jobIdentifier);
        }

        $query = http_build_query([
            'challenge_code'         => $challenge['challenge'],
            'code_challenge_method'  => 'S256',
            'redirect_uri'           => $redirectUri,
            'response_type'          => 'code',
            'state'                  => $jobIdentifier,
            'nonce'                  => $challenge['nonce'],
            'wr_conf'                => $challenge['wr_conf'],
        ]);

        return $this->oauthBase().'/oauth2/?'.$query;
    }

    /**
     * Resolve the import-job identifier from callback state or nonce.
     */
    public function resolveJobIdentifier(?string $state, ?string $nonce): string
    {
        foreach ([$state, $nonce] as $key) {
            if (null === $key || '' === $key) {
                continue;
            }
            $job = (string) session()->get(self::JOB_PREFIX.$key, '');
            if ('' !== $job) {
                return $job;
            }
        }

        return (string) $state;
    }

    /**
     * Exchange the callback code. Look up PKCE by state (job id) or nonce.
     */
    public function exchangeCode(string $code, ?string $state, ?string $nonce): array
    {
        $challenge = $this->loadPkce($state, $nonce);
        if (null === $challenge) {
            throw new ImporterHttpException('Wealth Reader PKCE challenge missing or expired. Restart the bank link.');
        }

        return $this->form($this->oauthBase().'/token/', [
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $challenge['redirect_uri'],
            'code'          => $code,
            'code_verifier' => $challenge['code_verifier'],
        ]);
    }

    /**
     * @return array{token: string, code: string}
     */
    public static function tokenFromPayload(array $data): array
    {
        $stats = is_array($data['statistics'] ?? null) ? $data['statistics'] : [];

        return [
            'token' => (string) ($stats['token'] ?? ''),
            'code'  => (string) ($stats['code'] ?? ''),
        ];
    }

    public static function accountsFromPayload(array $data): array
    {
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        return $payload['accounts'] ?? $data['accounts'] ?? [];
    }

    public function refresh(string $token, string $entityCode, ?string $dateFrom = null): array
    {
        $form = [
            'api_key'       => SecretManager::getApiKey(),
            'code'          => $entityCode,
            'token'         => $token,
            'product_types' => 'accounts',
        ];
        if (null !== $dateFrom && '' !== $dateFrom) {
            $form['date_from'] = $dateFrom;
        }

        return $this->form($this->apiBase().'/entities/', $form);
    }

    /**
     * @return array<string, string>|null
     */
    private function loadPkce(?string ...$keys): ?array
    {
        foreach ($keys as $key) {
            if (null === $key || '' === $key) {
                continue;
            }
            $payload = session()->get(self::PKCE_PREFIX.$key);
            if (is_array($payload) && isset($payload['code_verifier'])) {
                return $payload;
            }
        }

        return null;
    }

    private function apiBase(): string
    {
        return rtrim((string) config('wealthreader.api_url'), '/');
    }

    private function oauthBase(): string
    {
        return rtrim((string) config('wealthreader.oauth_url'), '/');
    }

    /**
     * @throws ImporterHttpException
     */
    private function json(string $method, string $url, array $options = []): array
    {
        $options['headers'] = array_merge([
            'Accept'     => 'application/json',
            'User-Agent' => 'FireflyIII-DataImporter/wealthreader',
        ], $options['headers'] ?? []);
        $options['http_errors'] = false;
        $options['timeout']     = (float) config('importer.connection.timeout', 30);

        try {
            $response = $this->guzzle()->request($method, $url, $options);
        } catch (GuzzleException $e) {
            throw new ImporterHttpException(sprintf('Wealth Reader %s %s failed: %s', $method, $url, $e->getMessage()));
        }

        return $this->interpret($response->getStatusCode(), (string) $response->getBody(), $method.' '.$url);
    }

    /**
     * @throws ImporterHttpException
     */
    private function form(string $url, array $form): array
    {
        return $this->json('POST', $url, [
            'form_params' => $form,
            'headers'     => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);
    }

    /**
     * @throws ImporterHttpException
     */
    private function interpret(int $status, string $body, string $path): array
    {
        if (429 === $status) {
            throw new ImporterHttpException(sprintf('Wealth Reader %s → 429', $path));
        }
        if (in_array($status, [401, 410], true)) {
            throw new ImporterHttpException(sprintf('Wealth Reader session expired (%d) for %s', $status, $path));
        }
        if ($status >= 400) {
            throw new ImporterHttpException(sprintf('Wealth Reader %s → %d: %s', $path, $status, substr($body, 0, 300)));
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new ImporterHttpException(sprintf('Wealth Reader %s returned non-JSON', $path));
        }
        if (false === ($data['success'] ?? true)) {
            $error   = $data['error'] ?? [];
            $code    = (int) ($error['code'] ?? 0);
            $message = (string) ($error['message'] ?? 'success=false');
            if (in_array($code, self::AUTH_ERROR_CODES, true)) {
                throw new ImporterHttpException(sprintf('Wealth Reader reauth required (%d): %s', $code, $message));
            }

            throw new ImporterHttpException(sprintf('Wealth Reader error %d: %s', $code, $message));
        }

        return $data;
    }

    private function guzzle(): GuzzleClient
    {
        return new GuzzleClient();
    }
}
