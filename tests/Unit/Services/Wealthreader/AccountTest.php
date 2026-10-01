<?php

/*
 * AccountTest.php
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

use App\Services\Wealthreader\Model\Account;
use Tests\TestCase;

/**
 * @internal
 */
final class AccountTest extends TestCase
{
    public function testFromArrayMapsIbanCurrencyAndTransactions(): void
    {
        $account = Account::fromArray([
            'uuid'     => 'acc-1',
            'name'     => 'Main',
            'code'     => 'ES9121000418450200051332',
            'subtype'  => 'checking',
            'currency' => 'GBP',
            'balances' => ['available' => '12.50'],
            'transactions' => [
                [
                    'uuid'           => 'tx-1',
                    'operation_date' => '2026-09-01T10:00:00Z',
                    'amount'         => '-4.00',
                    'description'    => 'Coffee',
                    'transfer_details' => ['concept' => 'CARD'],
                ],
            ],
        ]);

        $this->assertSame('acc-1', $account->uuid);
        $this->assertSame('ES9121000418450200051332', $account->iban);
        $this->assertSame('GBP', $account->currency);
        $this->assertSame('12.50', $account->balance);
        $this->assertCount(1, $account->transactions);
        $this->assertSame('2026-09-01', $account->transactions[0]->date);
        $this->assertSame('GBP', $account->transactions[0]->currency);
        $this->assertSame('CARD', $account->transactions[0]->concept);
    }
}
