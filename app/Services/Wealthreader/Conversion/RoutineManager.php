<?php

/*
 * RoutineManager.php
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

namespace App\Services\Wealthreader\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\Wealthreader\Conversion\Routine\GenerateTransactions;
use App\Services\Wealthreader\Conversion\Routine\TransactionProcessor;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use Illuminate\Support\Facades\Log;
use Override;

final class RoutineManager implements RoutineManagerInterface
{
    private GenerateTransactions $transactionGenerator;
    private TransactionProcessor $transactionProcessor;
    private ImportJobRepository $repository;
    private ImportJob $importJob;
    private array $downloaded = [];

    public function __construct(ImportJob $importJob)
    {
        $this->transactionProcessor = new TransactionProcessor();
        $this->transactionGenerator = new GenerateTransactions();
        $this->repository           = new ImportJobRepository();
        $this->importJob            = $importJob;
        $this->importJob->refreshInstanceIdentifier();
        $this->transactionProcessor->setImportJob($this->importJob);
        $this->transactionGenerator->setImportJob($this->importJob);
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        return $this->importJob->getServiceAccounts();
    }

    public function start(): array
    {
        $this->downloaded = $this->transactionProcessor->download();
        $this->importJob  = $this->transactionProcessor->getImportJob();

        $total = 0;
        foreach ($this->downloaded as $transactions) {
            $total += count($transactions);
        }
        if (0 === $total) {
            $this->importJob->conversionStatus->addError(0, '[wr001]: No transactions were downloaded from Wealthreader.');
            $this->repository->saveToDisk($this->importJob);

            return [];
        }

        try {
            $this->transactionGenerator->collectTargetAccounts();
        } catch (ApiHttpException $e) {
            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }

        $transactions = $this->transactionGenerator->getTransactions($this->downloaded);
        Log::debug(sprintf('Generated %d Firefly III transactions.', count($transactions)));

        return $transactions;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }
}
