<?php

/*
 * ClientTest.php
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

namespace Tests\Unit\Services\Wealthreader;

use App\Services\Wealthreader\Client;
use Tests\TestCase;

/**
 * @internal
 */
final class ClientTest extends TestCase
{
    public function testTokenFromPayloadReadsStatistics(): void
    {
        $token = Client::tokenFromPayload([
            'success'    => true,
            'statistics' => ['token' => 'tok-1', 'code' => 'bbva'],
        ]);

        $this->assertSame('tok-1', $token['token']);
        $this->assertSame('bbva', $token['code']);
    }

    public function testAccountsFromPayloadReadsNestedAccounts(): void
    {
        $accounts = Client::accountsFromPayload([
            'payload' => [
                'accounts' => [
                    ['uuid' => 'a1', 'code' => 'ES00'],
                ],
            ],
        ]);

        $this->assertCount(1, $accounts);
        $this->assertSame('a1', $accounts[0]['uuid']);
    }

    public function testResolveJobIdentifierPrefersNonceWhenStateIsMissing(): void
    {
        $client = new Client();
        session()->put('wealthreader_job_nonce-1', 'job-42');

        $this->assertSame('job-42', $client->resolveJobIdentifier(null, 'nonce-1'));
        $this->assertSame('job-42', $client->resolveJobIdentifier('', 'nonce-1'));
    }

    public function testResolveJobIdentifierFallsBackToState(): void
    {
        $client = new Client();
        session()->put('wealthreader_job_job-99', 'job-99');

        $this->assertSame('job-99', $client->resolveJobIdentifier('job-99', null));
    }
}
