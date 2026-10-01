<?php

/*
 * TransactionProcessor.php
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

use App\Exceptions\ImporterHttpException;
use App\Models\ImportJob;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Wealthreader\Client;
use App\Services\Wealthreader\Model\Account;
use App\Services\Wealthreader\Model\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class TransactionProcessor
{
    private Configuration $configuration;
    private ImportJob $importJob;

    public function setImportJob(ImportJob $importJob): void
    {
        $this->importJob     = $importJob;
        $this->configuration = $importJob->getConfiguration();
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    /**
     * @return array<string, list<Transaction>>
     */
    public function download(): array
    {
        $dateFrom = $this->configuration->getDateNotBefore();
        $notAfter = '' !== $this->configuration->getDateNotAfter()
            ? new Carbon($this->configuration->getDateNotAfter())
            : null;

        try {
            $data = (new Client())->refresh(
                $this->configuration->getWealthreaderToken(),
                $this->configuration->getWealthreaderBank(),
                '' !== $dateFrom ? $dateFrom : null
            );
        } catch (ImporterHttpException $e) {
            Log::error($e->getMessage());

            return [];
        }

        $selected = $this->configuration->getAccounts();
        $return   = [];
        foreach (Client::accountsFromPayload($data) as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $account = Account::fromArray($raw);
            if ('' === $account->uuid) {
                continue;
            }
            if ([] !== $selected && !array_key_exists($account->uuid, $selected) && !in_array($account->uuid, $selected, true)) {
                continue;
            }
            $return[$account->uuid] = [];
            foreach ($account->transactions as $transaction) {
                if ('' === $transaction->date || !is_numeric($transaction->amount)) {
                    continue;
                }
                if ($notAfter instanceof Carbon && Carbon::parse($transaction->date)->gt($notAfter)) {
                    continue;
                }
                $return[$account->uuid][] = $transaction;
            }
        }

        return $return;
    }
}
