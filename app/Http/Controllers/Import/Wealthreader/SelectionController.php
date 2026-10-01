<?php

/*
 * SelectionController.php
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

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Http\Controllers\Controller;
use App\Http\Request\SelectionRequest;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Wealthreader\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class SelectionController extends Controller
{
    private ImportJobRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ImportJobRepository();
    }

    public function index(Request $request, string $identifier)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        $countries       = config('wr.countries');
        $mainTitle       = 'Select your country and bank';
        $pageTitle       = 'Select your country and bank';
        $subTitle        = 'Select your country and the bank you wish to use.';
        $importJob       = $this->repository->find($identifier);
        $configuration   = $importJob->getConfiguration();
        $token           = $configuration->getWealthreaderToken();
        $country         = $configuration->getWealthreaderCountry();
        $bank            = $configuration->getWealthreaderBank();

        if ('' !== $token && '' !== $country && '' !== $bank) {
            return redirect(route('configure-import.index', [$identifier]));
        }

        $selectedCountry = $request->query('country', $country);
        $response        = null;

        if ('' !== $selectedCountry) {
            try {
                $response = (new Client())->listEntities($selectedCountry);
            } catch (ImporterHttpException $e) {
                throw new ImporterErrorException($e->getMessage(), 0, $e);
            }
        }

        $flow = 'wr';

        return view('import.009-selection.index', compact(
            'mainTitle',
            'identifier',
            'pageTitle',
            'subTitle',
            'response',
            'countries',
            'configuration',
            'selectedCountry',
            'flow'
        ));
    }

    public function postIndex(SelectionRequest $request, string $identifier): RedirectResponse
    {
        $importJob     = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();
        $values        = $request->getAll();

        $configuration->setWealthreaderCountry($values['country']);
        $configuration->setWealthreaderBank($values['bank']);
        $importJob->setConfiguration($configuration);
        $this->repository->saveToDisk($importJob);

        return redirect(route('wr-connect.index', [$identifier]));
    }
}
