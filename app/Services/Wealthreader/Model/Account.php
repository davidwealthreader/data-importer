<?php

/*
 * Account.php
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

namespace App\Services\Wealthreader\Model;

/**
 * Class Account
 */
final class Account
{
    public string $uuid     = '';
    public string $name     = '';
    public string $iban     = '';
    public string $subtype  = 'checking';
    public string $currency = 'EUR';
    public string $balance  = '0';

    /** @var list<Transaction> */
    public array $transactions = [];

    public static function fromArray(array $raw): self
    {
        $account            = new self();
        $account->uuid      = (string) ($raw['uuid'] ?? $raw['code'] ?? '');
        $account->name      = (string) ($raw['name'] ?? 'Account');
        $account->iban      = (string) ($raw['code'] ?? '');
        $account->subtype   = (string) ($raw['subtype'] ?? 'checking');
        $account->currency  = (string) ($raw['currency'] ?? 'EUR');
        $balances           = is_array($raw['balances'] ?? null) ? $raw['balances'] : [];
        $account->balance   = (string) ($balances['available'] ?? $balances['current'] ?? '0');
        foreach ($raw['transactions'] ?? [] as $item) {
            if (is_array($item)) {
                $account->transactions[] = Transaction::fromArray($item, $account->currency);
            }
        }

        return $account;
    }
}
