{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="__('aml.api')">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ __('aml.api') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('aml.api_docs_intro') }}</p>
    </x-slot>

    @php
        $token = __('aml.api_docs_placeholder');
        $headers = "Accept: application/json\nAccept-Language: en\nContent-Type: application/json";
        $sampleAddress = 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk';
        $authHeader = 'Authorization: Bearer '.$token;
        $curlAddress = <<<TXT
curl -X POST {$apiBase}/checks/address \\
  -H "{$authHeader}" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"address":"{$sampleAddress}","chain_id":"tron"}'
TXT;
        $curlShow = <<<TXT
curl {$apiBase}/checks/1 \\
  -H "{$authHeader}" \\
  -H "Accept: application/json"
TXT;
        $curlPdf = <<<TXT
curl "{$apiBase}/checks/1/pdf?variant=file" \\
  -H "{$authHeader}" \\
  -H "Accept-Language: en" \\
  -o TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk_file.pdf
TXT;
        $curlBatch = <<<TXT
curl -X POST {$apiBase}/checks/batch \\
  -H "{$authHeader}" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"addresses":["{$sampleAddress}"],"deep":false}'
TXT;
        $responseExample = <<<'JSON'
{
  "data": {
    "id": 1,
    "type": "address",
    "subject": "TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk",
    "chain_id": "tron",
    "chain": "Tron",
    "status": "completed",
    "verdict": "review",
    "risk_score": 20,
    "locale": "en",
    "flags": [],
    "raw_response": {},
    "enrichment": {},
    "asset_narrative": "...",
    "token_composition": [],
    "wallet_usd": { "formatted": "$0.00", "total_usd": 0 },
    "error_message": null,
    "created_at": "2026-08-20T14:00:00+00:00",
    "updated_at": "2026-08-20T14:00:00+00:00"
  }
}
JSON;
        $endpoints = [
            ['POST', '/checks/address', 'address, chain_id?', '201 / 502', 'api_docs_ep_address'],
            ['POST', '/checks/token', 'contract, chain_id?', '201 / 502', 'api_docs_ep_token'],
            ['POST', '/checks/phishing', 'url', '201 / 502', 'api_docs_ep_phishing'],
            ['POST', '/checks/dapp', 'url', '201 / 502', 'api_docs_ep_dapp'],
                            ['POST', '/checks/scan', 'address, chain_id?', '201 / 202 / 502', 'api_docs_ep_scan'],
                            ['POST', '/checks/batch', 'addresses[], deep?, case_id?', '202', 'api_docs_ep_batch'],
                            ['GET', '/checks', 'page', '200', 'api_docs_ep_list'],
                            ['GET', '/checks/{id}', '—', '200 / 403', 'api_docs_ep_show'],
                            ['GET', '/checks/{id}/pdf', 'variant=file|full', '200 / 409', 'api_docs_ep_pdf'],
        ];
    @endphp

    <div class="py-8">
        <div class="page space-y-10">
            <x-report-section :title="__('aml.api_tokens')">
                @if ($plainTextToken)
                    <div class="border border-amber-200 bg-amber-50 text-amber-900 p-4">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <div class="font-medium">{{ __('aml.token_created') }}</div>
                            <x-copy-button :text="$plainTextToken" />
                        </div>
                        <code class="break-all text-sm">{{ $plainTextToken }}</code>
                    </div>
                @endif

                <form method="POST" action="{{ route('tokens.store') }}" class="mt-4 flex gap-3 items-end">
                    @csrf
                    <div class="flex-1">
                        <x-input-label for="name" :value="__('aml.token_name')" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" required />
                    </div>
                    <x-primary-button>{{ __('aml.create_token') }}</x-primary-button>
                </form>

                <div class="mt-4 border border-ink-line divide-y divide-ink-line">
                    @forelse ($tokens as $item)
                        <div class="flex items-center justify-between p-4">
                            <div>
                                <div class="font-medium">{{ $item->name }}</div>
                                <div class="text-sm text-slate-500">{{ $item->created_at }} @if ($item->last_used_at) · {{ $item->last_used_at }} @endif</div>
                            </div>
                            <form method="POST" action="{{ route('tokens.destroy', $item->id) }}">
                                @csrf
                                @method('DELETE')
                                <x-danger-button>{{ __('aml.revoke') }}</x-danger-button>
                            </form>
                        </div>
                    @empty
                        <div class="p-6 text-slate-500">{{ __('aml.no_tokens') }}</div>
                    @endforelse
                </div>
            </x-report-section>

            <x-report-section :title="__('aml.api_docs_title')" :hint="__('aml.api_docs_chain')">
                <div class="space-y-8 text-sm">
                    <div>
                        <h3 class="font-semibold text-ink">{{ __('aml.api_docs_base') }}</h3>
                        <div class="mt-2 flex items-center gap-2 font-mono text-slate-800">
                            <span>{{ $apiBase }}</span>
                            <x-copy-button :text="$apiBase" />
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-ink">{{ __('aml.api_docs_auth_title') }}</h3>
                        <p class="mt-2 leading-6 text-slate-700">{{ __('aml.api_docs_auth_body') }}</p>
                        <x-code-sample class="mt-3" :code="$authHeader" />
                    </div>

                    <div>
                        <h3 class="font-semibold text-ink">{{ __('aml.api_docs_headers') }}</h3>
                        <p class="mt-2 leading-6 text-slate-700">{{ __('aml.api_docs_lang') }}</p>
                        <x-code-sample class="mt-3" :code="$headers" />
                    </div>

                    <div>
                        <h3 class="font-semibold text-ink">{{ __('aml.api_docs_endpoints') }}</h3>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-slate-500">
                                        <th class="pb-2 pr-3 font-medium">{{ __('aml.api_docs_method') }}</th>
                                        <th class="pb-2 pr-3 font-medium">{{ __('aml.api_docs_path') }}</th>
                                        <th class="pb-2 pr-3 font-medium">{{ __('aml.api_docs_body') }}</th>
                                        <th class="pb-2 pr-3 font-medium">{{ __('aml.api_docs_codes') }}</th>
                                        <th class="pb-2 font-medium">{{ __('aml.meaning') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($endpoints as $row)
                                        <tr class="border-t border-ink-line align-top">
                                            <td class="py-2 pr-3 font-mono text-xs font-semibold {{ str_starts_with($row[0], 'P') ? 'text-ink' : 'text-ink-muted' }}">{{ $row[0] }}</td>
                                            <td class="py-2 pr-3 font-mono text-xs">{{ $row[1] }}</td>
                                            <td class="py-2 pr-3 font-mono text-xs text-slate-600">{{ $row[2] }}</td>
                                            <td class="py-2 pr-3 font-mono text-xs">{{ $row[3] }}</td>
                                            <td class="py-2 text-slate-700">{{ __('aml.'.$row[4]) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-ink">{{ __('aml.api_docs_example') }} · POST /checks/address</h3>
                        <x-code-sample class="mt-3" :code="$curlAddress" />
                    </div>

                    <div>
                        <h3 class="font-semibold text-ink">{{ __('aml.api_docs_example') }} · GET /checks/{id}</h3>
                        <x-code-sample class="mt-3" :code="$curlShow" />
                    </div>

                    <div>
                        <h3 class="font-semibold text-ink">{{ __('aml.api_docs_example') }} · GET /checks/{id}/pdf</h3>
                        <x-code-sample class="mt-3" :code="$curlPdf" />
                    </div>

                    <div>
                        <h3 class="font-semibold text-ink">{{ __('aml.api_docs_example') }} · POST /checks/batch</h3>
                        <x-code-sample class="mt-3" :code="$curlBatch" />
                    </div>

                    <div>
                        <h3 class="font-semibold text-ink">{{ __('aml.api_docs_webhook') }}</h3>
                        <p class="mt-2 leading-6 text-slate-700">{{ __('aml.webhook_hint') }}</p>
                    </div>

                    <div>
                        <h3 class="font-semibold text-ink">{{ __('aml.api_docs_response') }}</h3>
                        <p class="mt-2 text-xs text-slate-500">{{ __('aml.api_docs_list_wrap') }}</p>
                        <x-code-sample class="mt-3" :code="$responseExample" />
                    </div>

                    <div>
                        <h3 class="font-semibold text-ink">{{ __('aml.api_docs_fields') }}</h3>
                        <table class="mt-3 w-full text-sm">
                            <tbody>
                                @foreach ([
                                    'id' => 'api_docs_field_id',
                                    'type' => 'api_docs_field_type',
                                    'subject' => 'api_docs_field_subject',
                                    'status' => 'api_docs_field_status',
                                    'verdict' => 'api_docs_field_verdict',
                                    'risk_score' => 'api_docs_field_score',
                                    'flags' => 'api_docs_field_flags',
                                    'raw_response' => 'api_docs_field_raw',
                                    'enrichment' => 'api_docs_field_enrichment',
                                    'asset_narrative' => 'api_docs_field_narrative',
                                    'wallet_usd' => 'api_docs_field_usd',
                                ] as $field => $help)
                                    <tr class="border-t border-ink-line align-top">
                                        <td class="py-2 pr-4 font-mono text-xs text-slate-600 whitespace-nowrap">{{ $field }}</td>
                                        <td class="py-2 text-slate-700">{{ __('aml.'.$help) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <h3 class="font-semibold text-ink">{{ __('aml.api_docs_errors') }}</h3>
                        <table class="mt-3 w-full text-sm">
                            <tbody>
                                @foreach ([
                                    '401' => 'api_docs_err_401',
                                    '403' => 'api_docs_err_403',
                                    '409' => 'api_docs_err_409',
                                    '422' => 'api_docs_err_422',
                                    '502' => 'api_docs_err_502',
                                ] as $code => $help)
                                    <tr class="border-t border-ink-line">
                                        <td class="py-2 pr-4 font-mono text-xs font-semibold w-16">{{ $code }}</td>
                                        <td class="py-2 text-slate-700">{{ __('aml.'.$help) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-report-section>
        </div>
    </div>
</x-app-layout>
