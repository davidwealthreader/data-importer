<?php

/*
 * Transaction.php
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
 * Class Transaction
 */
final class Transaction
{
    public string $uuid        = '';
    public string $date        = '';
    public string $amount      = '0';
    public string $currency    = 'EUR';
    public string $description = '';
    public string $concept     = '';

    public static function fromArray(array $raw, string $accountCurrency = 'EUR'): self
    {
        $tx              = new self();
        $tx->uuid        = (string) ($raw['uuid'] ?? '');
        $tx->date        = substr((string) ($raw['operation_date'] ?? $raw['value_date'] ?? ''), 0, 10);
        $tx->amount      = (string) ($raw['amount'] ?? '0');
        $tx->currency    = (string) ($raw['currency'] ?? $accountCurrency);
        $tx->description = trim((string) ($raw['description'] ?? ''));
        $transfer        = is_array($raw['transfer_details'] ?? null) ? $raw['transfer_details'] : [];
        $tx->concept     = trim((string) ($transfer['concept'] ?? ''));

        return $tx;
    }
}
