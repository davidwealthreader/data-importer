<?php

/*
 * NewJobDataCollector.php
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

namespace App\Services\Wealthreader\Validation;

use App\Exceptions\ImporterHttpException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Shared\Validation\NewJobDataCollectorInterface;
use App\Services\Wealthreader\Client;
use App\Services\Wealthreader\Model\Account;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;

final class NewJobDataCollector implements NewJobDataCollectorInterface
{
    private ImportJob $importJob;
    private ImportJobRepository $repository;

    public function __construct()
    {
        $this->repository = new ImportJobRepository();
    }

    public function collectAccounts(): MessageBag
    {
        $messageBag    = new MessageBag();
        $configuration = $this->importJob->getConfiguration();

        if ('' === $configuration->getWealthreaderToken() || '' === $configuration->getWealthreaderBank()) {
            $messageBag->add('missing_token', 'true');

            return $messageBag;
        }

        if (count($this->importJob->getServiceAccounts()) > 0) {
            return $messageBag;
        }

        try {
            $data = (new Client())->refresh(
                $configuration->getWealthreaderToken(),
                $configuration->getWealthreaderBank()
            );
        } catch (ImporterHttpException $e) {
            $messageBag->add('no_accounts', $e->getMessage());

            return $messageBag;
        }

        $accounts = [];
        foreach (Client::accountsFromPayload($data) as $raw) {
            if (is_array($raw)) {
                $account = Account::fromArray($raw);
                if ('' !== $account->uuid) {
                    $accounts[] = $account;
                }
            }
        }
        if (0 === count($accounts)) {
            $messageBag->add('no_accounts', 'No accounts were returned from Wealthreader.');

            return $messageBag;
        }

        $this->importJob->setServiceAccounts($accounts);
        $this->repository->saveToDisk($this->importJob);

        return $messageBag;
    }

    public function getFlowName(): string
    {
        return 'wr';
    }

    public function validate(): MessageBag
    {
        return new MessageBag();
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function setImportJob(ImportJob $importJob): void
    {
        $this->importJob = $importJob;
    }
}
