<?php

/*
 * LinkController.php
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

namespace App\Http\Controllers\Import\Wealthreader;

use App\Exceptions\ImporterHttpException;
use App\Http\Controllers\Controller;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Wealthreader\Client;
use App\Services\Wealthreader\Model\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class LinkController extends Controller
{
    private ImportJobRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ImportJobRepository();
    }

    public function build(string $identifier): RedirectResponse
    {
        $importJob     = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();

        if ('' === $configuration->getWealthreaderBank()) {
            return redirect()->route('wr-select-bank.index', [$identifier]);
        }

        $callback = route('wr-connect.callback');
        $url      = (new Client())->buildAuthorizeUrl(
            $callback,
            $identifier,
            $configuration->getWealthreaderBank()
        );

        $host     = parse_url($url, PHP_URL_HOST);
        $expected = parse_url((string) config('wealthreader.oauth_url'), PHP_URL_HOST);
        if ($expected !== $host) {
            throw new ImporterHttpException('Unexpected Wealthreader host.');
        }

        return redirect($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $code   = trim((string) $request->get('code'));
        $state  = trim((string) $request->get('state'));
        $nonce  = trim((string) $request->get('nonce'));
        $client = new Client();

        if ('' === $code) {
            throw new ImporterHttpException(sprintf(
                'Wealthreader authorization failed: %s',
                $request->get('error', 'Unknown error')
            ));
        }

        $identifier = $client->resolveJobIdentifier('' === $state ? null : $state, '' === $nonce ? null : $nonce);
        if ('' === $identifier) {
            throw new ImporterHttpException('Wealthreader callback missing state and nonce.');
        }

        $importJob     = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();

        if ('' !== $configuration->getWealthreaderToken() && count($importJob->getServiceAccounts()) > 0) {
            return redirect(route('configure-import.index', [$identifier]));
        }

        $data  = $client->exchangeCode($code, '' === $state ? null : $state, '' === $nonce ? null : $nonce);
        $token = Client::tokenFromPayload($data);
        if ('' === $token['token']) {
            throw new ImporterHttpException('Wealthreader did not return a token.');
        }

        $configuration->setWealthreaderToken($token['token']);
        if ('' !== $token['code']) {
            $configuration->setWealthreaderBank($token['code']);
        }
        $importJob->setConfiguration($configuration);

        $accounts = [];
        foreach (Client::accountsFromPayload($data) as $raw) {
            if (is_array($raw)) {
                $accounts[] = Account::fromArray($raw);
            }
        }
        if (count($accounts) > 0) {
            $importJob->setServiceAccounts($accounts);
        }

        $importJob->setState('contains_content');
        $this->repository->saveToDisk($importJob);

        return redirect(route('configure-import.index', [$identifier]));
    }
}
