<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\District;
use GuzzleHttp\Psr7\Request as HttpRequest;
use Http\Client\Curl\Client as CurlClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Razorpay\IFSC\IFSC as RazorpayIFSC;

class BankController extends Controller
{
    private const SCOPED_ROLES = ['district', 'block', 'block_admin', 'block_cadre'];

    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Bank::with('district:district_id,district_name')->orderBy('bank_name');

        if (!$request->boolean('all') && in_array($user->role, self::SCOPED_ROLES) && $user->district_id) {
            $query->where(function ($q) use ($user) {
                $q->where('district_id', $user->district_id)
                  ->orWhereNull('district_id');
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user       = $request->user();
        $districtId = in_array($user->role, self::SCOPED_ROLES) ? $user->district_id : null;

        $data = $request->validate([
            'bank_name'      => 'required|string|max:200',
            'bank_full_name' => 'nullable|string|max:200',
            'ifsc'           => 'required|string|max:11',
            'branch_name'    => 'nullable|string|max:200',
            'address'        => 'nullable|string|max:500',
            'district_id'    => 'nullable|integer|exists:district,district_id',
        ]);

        // Scoped roles always get their own district; state admin can supply one explicitly
        $data['district_id'] = \in_array($user->role, self::SCOPED_ROLES)
            ? $user->district_id
            : ($data['district_id'] ?? null);

        return response()->json(Bank::create($data), 201);
    }

    public function names(Request $request): JsonResponse
    {
        $rows = DB::table('bank')
            ->select('bank_name', DB::raw('MAX(bank_full_name) as bank_full_name'))
            ->whereNotNull('bank_name')
            ->where('bank_name', '!=', '')
            ->groupBy('bank_name')
            ->orderBy('bank_name')
            ->get();

        return response()->json($rows);
    }

    public function search(Request $request): JsonResponse
    {
        $q    = trim((string) $request->query('q', ''));
        $bank = trim((string) $request->query('bank', ''));

        if (strlen($q) < 2 && $bank === '') {
            return response()->json([]);
        }

        $query = Bank::with('district:district_id,district_name')
            ->orderBy('bank_name')
            ->orderBy('ifsc')
            ->limit(50);

        if ($bank !== '') {
            $query->where('bank_name', $bank);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('ifsc', 'like', "%{$q}%")
                    ->orWhere('branch_name', 'like', "%{$q}%")
                    ->orWhere('bank_name', 'like', "%{$q}%")
                    ->orWhere('bank_full_name', 'like', "%{$q}%");
            });
        }

        return response()->json($query->get());
    }

    public function unassigned(Request $request): JsonResponse
    {
        $user    = $request->user();
        $allowed = ['state', 'district', 'block', 'block_admin'];
        if (!in_array($user->role, $allowed)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(
            Bank::with('district:district_id,district_name')
                ->whereNull('district_id')
                ->orderBy('bank_name')
                ->get()
        );
    }

    public function update(Request $request, Bank $bank): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'bank_name'      => 'sometimes|string|max:200',
            'bank_full_name' => 'nullable|string|max:200',
            'ifsc'           => 'sometimes|string|max:11',
            'branch_name'    => 'nullable|string|max:200',
            'address'        => 'nullable|string|max:500',
            'district_id'    => 'nullable|integer|exists:district,district_id',
        ]);

        // Scoped roles can only assign to their own district — ignore any submitted value
        if (in_array($user->role, self::SCOPED_ROLES) && array_key_exists('district_id', $data)) {
            $data['district_id'] = $user->district_id;
        }

        $bank->update($data);
        return response()->json($bank);
    }

    public function destroy(Bank $bank): JsonResponse
    {
        $bank->delete();
        return response()->json(['message' => 'Bank deleted.']);
    }

    public function bulkUpload(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['district', 'block', 'block_admin'])) {
            return response()->json(['message' => 'Insufficient permissions to bulk upload banks.'], 403);
        }

        $districtId = $user->district_id;

        $payload = $request->validate([
            'banks'               => 'required|array|min:1|max:500',
            'banks.*.bank_name'   => 'required|string|max:200',
            'banks.*.ifsc'        => 'required|string|max:11',
            'banks.*.branch_name' => 'nullable|string|max:200',
            'banks.*.address'     => 'nullable|string|max:500',
        ]);

        $inserted = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($payload['banks'] as $i => $row) {
            $ifsc       = strtoupper(trim($row['ifsc']));
            $branchName = trim($row['branch_name'] ?? '') ?: null;

            if (Bank::where('ifsc', $ifsc)->where('branch_name', $branchName)->exists()) {
                $skipped++;
                $errors[] = 'Row ' . ($i + 2) . ": IFSC {$ifsc} with branch '{$branchName}' already exists.";
                continue;
            }

            Bank::create([
                'bank_name'   => trim($row['bank_name']),
                'ifsc'        => $ifsc,
                'branch_name' => $branchName,
                'address'     => trim($row['address'] ?? '') ?: null,
                'district_id' => $districtId,
            ]);
            $inserted++;
        }

        return response()->json([
            'inserted' => $inserted,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ]);
    }

    public function pullIfsc(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'state') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        try {
            $curlBase = [
                CURLOPT_USERAGENT      => 'ASRLM-Portal/1.0',
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ];

            // 1. Resolve latest release via GitHub API (razorpay/ifsc)
            $metaHttp    = new CurlClient(null, null, $curlBase + [CURLOPT_TIMEOUT => 30]);
            $releaseData = json_decode(
                (string) $metaHttp->sendRequest(
                    new HttpRequest('GET', 'https://api.github.com/repos/razorpay/ifsc/releases/latest')
                )->getBody(),
                true
            );

            if (empty($releaseData['tag_name'])) {
                return response()->json(['message' => 'Unable to reach GitHub API. Check server internet connectivity.'], 502);
            }

            $version = $releaseData['tag_name'];

            // 2. Locate IFSC.csv direct download URL from release assets
            $csvUrl = null;
            foreach ($releaseData['assets'] ?? [] as $asset) {
                if (strtolower($asset['name']) === 'ifsc.csv') {
                    $csvUrl = $asset['browser_download_url'];
                    break;
                }
            }
            $csvUrl ??= "https://github.com/razorpay/ifsc/releases/download/{$version}/IFSC.csv";

            // 3. Download IFSC.csv (extended timeout for ~20 MB file)
            $dlHttp     = new CurlClient(null, null, $curlBase + [CURLOPT_TIMEOUT => 180]);
            $csvContent = (string) $dlHttp->sendRequest(new HttpRequest('GET', $csvUrl))->getBody();

            if (empty($csvContent)) {
                return response()->json(['message' => 'Downloaded IFSC.csv is empty.'], 502);
            }

            // 4. Build district-name → district_id map (case-insensitive)
            $districtMap = District::all(['district_id', 'district_name'])
                ->keyBy(fn($d) => strtoupper(trim($d->district_name)))
                ->map(fn($d) => $d->district_id)
                ->all();

            // 5. Load existing IFSCs into a PHP hash set for O(1) duplicate detection.
            //    No DB unique constraint is used — deduplication is done in application code.
            $existingIfscs = array_flip(DB::table('bank')->pluck('ifsc')->toArray());

            // 6. Parse CSV — v2 columns: BANK,IFSC,BRANCH,CENTRE,DISTRICT,STATE,ADDRESS,CONTACT,IMPS,RTGS,CITY,ISO3166,NEFT,MICR,UPI,SWIFT
            //    BANK column = bank full name; bank_name (code) = first 4 chars of IFSC
            //    RazorpayIFSC::getBankName() used as authoritative name source from razorpay/ifsc package
            $lines    = preg_split('/\r?\n/', $csvContent);
            $headers  = null;
            $inserted = 0;
            $skipped  = 0;
            $batch    = [];
            $now      = now()->toDateTimeString();

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;

                $cols = str_getcsv($line);

                if ($headers === null) {
                    $headers = array_map('strtoupper', $cols);
                    continue;
                }

                if (count($cols) < count($headers)) continue;
                $row = array_combine($headers, $cols);

                if (strtolower(trim($row['STATE'] ?? '')) !== 'assam') continue;

                $ifsc       = strtoupper(trim($row['IFSC'] ?? ''));
                $bankCode   = strtoupper(substr($ifsc, 0, 4));
                $branchName = trim($row['BRANCH'] ?? '') ?: null;

                if (strlen($ifsc) !== 11) continue;

                // Skip IFSCs already in the database (or already queued in this batch)
                if (isset($existingIfscs[$ifsc])) {
                    $skipped++;
                    continue;
                }
                $existingIfscs[$ifsc] = true; // mark so intra-run duplicates are also caught

                // razorpay/ifsc: getBankName() resolves 4-letter code → full name.
                // Falls back to CSV BANK column for banks added after the package data.
                $bankFullName = RazorpayIFSC::getBankName($bankCode) ?? trim($row['BANK'] ?? $bankCode);

                $districtKey = strtoupper(trim($row['DISTRICT'] ?? ''));
                $districtId  = $districtMap[$districtKey] ?? null;
                // Fall back to CITY column when DISTRICT doesn't match any known district
                if ($districtId === null) {
                    $cityKey    = strtoupper(trim($row['CITY'] ?? ''));
                    $districtId = $districtMap[$cityKey] ?? null;
                }

                $batch[] = [
                    'bank_name'      => $bankCode,
                    'bank_full_name' => $bankFullName,
                    'ifsc'           => $ifsc,
                    'branch_name'    => $branchName,
                    'address'        => trim($row['ADDRESS'] ?? '') ?: null,
                    'city'           => trim($row['CITY']    ?? '') ?: null,
                    'state'          => trim($row['STATE']   ?? '') ?: null,
                    'district_id'    => $districtId,
                    'micr'           => trim($row['MICR']    ?? '') ?: null,
                    'contact'        => trim($row['CONTACT'] ?? '') ?: null,
                    'imps'           => strtolower(trim($row['IMPS'] ?? '')) === 'true',
                    'rtgs'           => strtolower(trim($row['RTGS'] ?? '')) === 'true',
                    'neft'           => strtolower(trim($row['NEFT'] ?? '')) === 'true',
                    'upi'            => strtolower(trim($row['UPI']  ?? '')) === 'true',
                    'source'         => 'razorpay',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                if (count($batch) >= 200) {
                    DB::table('bank')->insert($batch);
                    $inserted += count($batch);
                    $batch     = [];
                }
            }

            if (!empty($batch)) {
                DB::table('bank')->insert($batch);
                $inserted += count($batch);
            }

            // Sync existing unassigned banks: match city → district name
            $synced = 0;
            foreach ($districtMap as $nameUpper => $dId) {
                $synced += DB::table('bank')
                    ->whereNull('district_id')
                    ->whereRaw('UPPER(TRIM(city)) = ?', [$nameUpper])
                    ->update(['district_id' => $dId, 'updated_at' => $now]);
            }

            $parts = [];
            if ($inserted > 0) {
                $parts[] = "Added {$inserted} new branch" . ($inserted !== 1 ? 'es' : '');
            }
            if ($synced > 0) {
                $parts[] = "synced {$synced} existing branch" . ($synced !== 1 ? 'es' : '') . " to district via city name";
            }
            $msg = empty($parts)
                ? "Database is already up to date ({$skipped} branches already exist)."
                : implode('; ', $parts) . " (skipped {$skipped} already existing).";

            return response()->json([
                'message'  => $msg,
                'inserted' => $inserted,
                'synced'   => $synced,
                'skipped'  => $skipped,
                'version'  => $version,
            ]);

        } catch (\Throwable $e) {
            Log::error('IFSC pull error: ' . $e->getMessage());
            return response()->json(['message' => 'IFSC pull failed: ' . $e->getMessage()], 500);
        }
    }
}
