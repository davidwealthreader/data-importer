<?php

/*
 * SecretManager.php
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

namespace App\Services\Wealthreader\Authentication;

use App\Services\Shared\Configuration\Configuration;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;

/**
 * Class SecretManager
 */
final class SecretManager
{
    public const string API_KEY = 'wealthreader_api_key';

    public static function getApiKey(?Configuration $configuration = null): string
    {
        $fromSession = self::fromSession();
        if ('' !== $fromSession) {
            return $fromSession;
        }

        $fromConfig = (string) config('wealthreader.api_key');
        if ('' !== $fromConfig) {
            return $fromConfig;
        }

        Log::debug('Wealth Reader: no api_key in session or WEALTHREADER_API_KEY.');

        return (string) ($configuration?->toArray()['wealthreader_api_key'] ?? '');
    }

    public static function hasApiKeyAvailable(): bool
    {
        return '' !== self::getApiKey();
    }

    public static function saveApiKey(#[SensitiveParameter] string $apiKey): void
    {
        session()->put(self::API_KEY, $apiKey);
    }

    private static function fromSession(): string
    {
        return (string) session()->get(self::API_KEY, '');
    }
}
