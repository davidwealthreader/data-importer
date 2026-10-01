<?php

/*
 * Pkce.php
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

/**
 * Wealth Reader PKCE is NOT RFC 7636.
 *
 * nonce / state / code_verifier = bin2hex(randomAlnum(41)) (82 hex chars).
 * challenge_code = SHA-256 hex digest of the already-hex code_verifier.
 * wr_conf = JSON encoded as hex. See www.wealthreader.com/docs/en/oauth-backend/
 * and demo/oauth/index.php.
 */
final class Pkce
{
    public const int ALNUM_LENGTH = 41;

    public static function generate(string $entityCode): array
    {
        $nonce        = self::hexAlnum(self::ALNUM_LENGTH);
        $state        = self::hexAlnum(self::ALNUM_LENGTH);
        $codeVerifier = self::hexAlnum(self::ALNUM_LENGTH);
        $challenge    = hash('sha256', $codeVerifier);
        $wrConf       = [
            'operation_id'         => 'op_'.bin2hex(random_bytes(8)),
            'entities_to_display'  => '' === $entityCode ? [] : [$entityCode],
            'wait_full_response'   => true,
        ];

        return [
            'nonce'         => $nonce,
            'wr_state'      => $state,
            'code_verifier' => $codeVerifier,
            'challenge'     => $challenge,
            'wr_conf'       => bin2hex(json_encode($wrConf, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ];
    }

    public static function hexAlnum(int $length): string
    {
        return bin2hex(self::randomAlnum($length));
    }

    public static function randomAlnum(int $length): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $max      = strlen($alphabet) - 1;
        $value    = '';
        for ($i = 0; $i < $length; ++$i) {
            $value .= $alphabet[random_int(0, $max)];
        }

        return $value;
    }
}
