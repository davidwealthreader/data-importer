<?php

/*
 * GenerateTransactions.php
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

namespace App\Services\Wealthreader\Conversion\Routine;

use App\Models\ImportJob;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Wealthreader\Model\Transaction;
use App\Support\Http\CollectsAccounts;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;

final class GenerateTransactions
{
    use CollectsAccounts;

    private array $accounts = [];
    private Configuration $configuration;

    public function setImportJob(ImportJob $importJob): void
    {
        bcscale(12);
        $this->configuration = $importJob->getConfiguration();
        $this->accounts      = $this->configuration->getAccounts();
    }

    /**
     * @throws ApiHttpException
     */
    public function collectTargetAccounts(): void
    {
        $this->collectAllTargetAccounts();
    }

    /**
     * @param array<string, list<Transaction>> $transactions
     */
    public function getTransactions(array $transactions): array
    {
        $return = [];
        foreach ($transactions as $accountUid => $entries) {
            foreach ($entries as $entry) {
                $return[] = $this->generateTransaction((string) $accountUid, $entry);
            }
        }

        return $return;
    }

    private function generateTransaction(string $accountUid, Transaction $entry): array
    {
        $amount      = (string) $entry->amount;
        $isDeposit   = 1 === bccomp($amount, '0');
        $mapped      = isset($this->accounts[$accountUid]) ? (int) $this->accounts[$accountUid] : 0;
        $description = '' !== $entry->description ? $entry->description : ($entry->concept ?: 'Wealthreader');
        $transaction = [
            'type'               => $isDeposit ? 'deposit' : 'withdrawal',
            'date'               => $entry->date,
            'amount'             => ltrim($amount, '+'),
            'description'        => $description,
            'currency_code'      => '' !== $entry->currency ? $entry->currency : 'EUR',
            'external_id'        => '' !== $entry->uuid ? $entry->uuid : sprintf('%s-%s-%s', $accountUid, $entry->date, $amount),
            'notes'              => $entry->concept,
        ];
        if ($isDeposit) {
            $transaction['destination_id'] = $mapped;
        } else {
            $transaction['source_id'] = $mapped;
            $transaction['amount']    = ltrim($amount, '-');
        }

        return [
            'error_if_duplicate_hash' => $this->configuration->isIgnoreDuplicateTransactions(),
            'apply_rules'             => $this->configuration->isRules(),
            'fire_webhooks'           => $this->configuration->isWebhooks(),
            'transactions'            => [$transaction],
        ];
    }
}
