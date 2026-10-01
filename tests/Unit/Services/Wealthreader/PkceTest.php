<?php

/*
 * PkceTest.php
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

use App\Services\Wealthreader\Pkce;
use Tests\TestCase;

/**
 * @internal
 */
final class PkceTest extends TestCase
{
    public function testGenerateMatchesWealthreaderContract(): void
    {
        $challenge = Pkce::generate('bbva');

        $this->assertSame(82, strlen($challenge['nonce']));
        $this->assertSame(82, strlen($challenge['code_verifier']));
        $this->assertSame(82, strlen($challenge['wr_state']));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{82}$/', $challenge['nonce']);
        $this->assertSame(hash('sha256', $challenge['code_verifier']), $challenge['challenge']);

        $raw = hex2bin($challenge['wr_conf']);
        $this->assertNotFalse($raw);
        $this->assertStringContainsString('"bbva"', $raw);
        $this->assertStringContainsString('wait_full_response', $raw);
    }
}
