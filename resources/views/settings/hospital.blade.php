<x-app-layout>
    <x-slot name="header">Hospital Settings</x-slot>

    @php
        $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
        $label = 'block text-sm font-medium text-slate-700';
        $onlinePaymentsEnabled = auth()->user()->hospital?->hasModule('online_payments') ?? false;
        $sections = [
            'basic' => ['title' => 'Basic details', 'description' => 'Hospital name and contact details'],
            'admission-workflow' => ['title' => 'Admission workflow', 'description' => 'Choose the inpatient charts your hospital uses'],
            'payments' => ['title' => 'Payments', 'description' => 'Registration, A&E, and mortuary charges'.($onlinePaymentsEnabled ? ', plus patient online checkout' : '')],
            'branding' => ['title' => 'Branding', 'description' => 'Colors, logo, and patient card signature'],
        ];
        $settingsRoute = fn ($target) => match ($target) {
            'basic' => route('settings.edit'),
            'admission-workflow' => route('settings.admission-workflow'),
            default => route('settings.section', ['section' => $target]),
        };
        $saveRoute = match ($section) {
            'basic' => route('settings.update'),
            'admission-workflow' => route('settings.admission-workflow.update'),
            default => route('settings.update.section', ['section' => $section]),
        };
    @endphp

    <div class="max-w-6xl">
        @if (session('success'))
            <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-semibold">Please check the fields below.</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="mb-6">
            <h2 class="text-xl font-semibold text-slate-900">Configure your hospital</h2>
            <p class="mt-1 text-sm text-slate-600">Manage hospital information, admission charts, payment options, and branding.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
            <nav aria-label="Hospital settings sections" class="h-fit rounded-xl border bg-white p-2 shadow-sm">
                @foreach ($sections as $key => $item)
                    <a href="{{ $settingsRoute($key) }}" @if ($section === $key) aria-current="page" @endif
                       class="mb-1 block rounded-lg px-4 py-3 last:mb-0 {{ $section === $key ? 'bg-teal-50 text-teal-900 ring-1 ring-teal-200' : 'text-slate-700 hover:bg-slate-50' }}">
                        <span class="block text-sm font-semibold">{{ $item['title'] }}</span>
                        <span class="mt-0.5 block text-xs text-slate-500">{{ $item['description'] }}</span>
                    </a>
                @endforeach
            </nav>

            <section class="min-w-0 rounded-xl border bg-white shadow-sm">
                <div class="border-b px-5 py-4 sm:px-6">
                    <h3 class="font-semibold text-slate-900">{{ $sections[$section]['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $sections[$section]['description'] }}</p>
                </div>

                <form method="POST" action="{{ $saveRoute }}" enctype="multipart/form-data" class="p-5 sm:p-6" x-data="{ gateway: @js(old('payment_gateway', $settings->payment_gateway ?: 'paystack')) }">
                    @csrf
                    @method('PUT')

                    @if ($section === 'basic')
                        <p class="mb-5 text-sm text-slate-600">These details appear on receipts, patient cards, and throughout the hospital portal.</p>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="name" class="{{ $label }}">Hospital name <span class="text-red-600">*</span></label>
                                <input id="name" name="name" type="text" value="{{ old('name', $settings->name) }}" required maxlength="150" class="{{ $input }}">
                                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="address" class="{{ $label }}">Address</label>
                                <textarea id="address" name="address" rows="3" maxlength="500" class="{{ $input }}">{{ old('address', $settings->address) }}</textarea>
                                @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="phone" class="{{ $label }}">Phone</label>
                                <input id="phone" name="phone" type="text" value="{{ old('phone', $settings->phone) }}" maxlength="50" class="{{ $input }}">
                                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="email" class="{{ $label }}">Email</label>
                                <input id="email" name="email" type="email" value="{{ old('email', $settings->email) }}" maxlength="150" class="{{ $input }}">
                                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="website" class="{{ $label }}">Website</label>
                                <input id="website" name="website" type="text" value="{{ old('website', $settings->website) }}" maxlength="150" class="{{ $input }}">
                                @error('website')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="receipt_footer" class="{{ $label }}">Receipt footer message</label>
                                <input id="receipt_footer" name="receipt_footer" type="text" value="{{ old('receipt_footer', $settings->receipt_footer) }}" placeholder="Thank you. Get well soon." maxlength="255" class="{{ $input }}">
                                @error('receipt_footer')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    @elseif ($section === 'admission-workflow')
                        <div class="space-y-5">
                            <div>
                                <h4 class="text-base font-semibold text-slate-900">Choose admission chart sections</h4>
                                <p class="mt-1 text-sm text-slate-600">Admission details, vitals, and care notes stay available. Choose which specialized charts your hospital uses. Saving creates a new workflow version and keeps existing records.</p>
                            </div>
                            <fieldset class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <legend class="mb-3 text-sm font-semibold text-slate-900 sm:col-span-2 xl:col-span-3">Optional sections · current version {{ $version ?: 'default' }}</legend>
                                @foreach ($workflowSections as $key => $label)
                                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4 text-sm text-slate-700">
                                        <input type="checkbox" name="sections[]" value="{{ $key }}" @checked(in_array($key, old('sections', $enabledSections), true)) class="mt-0.5 rounded border-slate-300 text-teal-700">
                                        <span><strong>{{ $label }}</strong></span>
                                    </label>
                                @endforeach
                            </fieldset>
                        </div>
                    @elseif ($section === 'payments')
                        <div class="grid gap-5">
                            @if ($onlinePaymentsEnabled)
                                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                                    <p class="font-semibold">Patient online checkout</p>
                                    <p class="mt-1">Patients can pay invoices in the portal using Paystack. You can choose in-person billing to keep online checkout off while continuing to record payments at the billing desk.</p>
                                </div>
                                <div>
                                    <label for="payment_gateway" class="{{ $label }}">Payment option <span class="text-red-600">*</span></label>
                                    <select id="payment_gateway" name="payment_gateway" required x-model="gateway" class="{{ $input }}">
                                        <option value="paystack" @selected(old('payment_gateway', $settings->payment_gateway ?: 'paystack') === 'paystack')>Paystack online payments</option>
                                        <option value="manual" @selected(old('payment_gateway', $settings->payment_gateway ?: 'paystack') === 'manual')>In-person billing only</option>
                                    </select>
                                    @error('payment_gateway')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            @else
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                    <p class="font-semibold">Online Payments is not enabled</p>
                                    <p class="mt-1">Patients will not see an online payment link. Staff can still record in-person payments at the billing desk. Enable Online Payments in the global hospital configuration to connect a gateway.</p>
                                </div>
                            @endif
                            <div>
                                <label for="registration_fee" class="{{ $label }}">Patient registration fee (₦) <span class="text-red-600">*</span></label>
                                <input id="registration_fee" name="registration_fee" type="number" step="0.01" min="0" max="99999999" required value="{{ old('registration_fee', $settings->registration_fee) }}" class="{{ $input }}">
                                <p class="mt-1 text-xs text-slate-500">Charged once to each newly registered patient. Use 0 for no fee.</p>
                                @error('registration_fee')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="emergency_fee" class="{{ $label }}">A&E attendance fee (₦) <span class="text-red-600">*</span></label>
                                <input id="emergency_fee" name="emergency_fee" type="number" step="0.01" min="0" max="99999999" required value="{{ old('emergency_fee', $settings->emergency_fee ?? 0) }}" class="{{ $input }}">
                                <p class="mt-1 text-xs text-slate-500">Added to each new A&E visit when it is registered. It does not delay emergency care. Use 0 for no fixed fee.</p>
                                @error('emergency_fee')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="mortuary_intake_fee" class="{{ $label }}">Mortuary intake fee (₦) <span class="text-red-600">*</span></label>
                                <input id="mortuary_intake_fee" name="mortuary_intake_fee" type="number" step="0.01" min="0" max="99999999" required value="{{ old('mortuary_intake_fee', $settings->mortuary_intake_fee ?? 0) }}" class="{{ $input }}">
                                <p class="mt-1 text-xs text-slate-500">Charged once when the body is received into mortuary custody. Use 0 for no intake fee.</p>
                                @error('mortuary_intake_fee')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="mortuary_daily_storage_fee" class="{{ $label }}">Mortuary storage fee per started 24 hours (₦) <span class="text-red-600">*</span></label>
                                <input id="mortuary_daily_storage_fee" name="mortuary_daily_storage_fee" type="number" step="0.01" min="0" max="99999999" required value="{{ old('mortuary_daily_storage_fee', $settings->mortuary_daily_storage_fee ?? 0) }}" class="{{ $input }}">
                                <p class="mt-1 text-xs text-slate-500">Storage is billed in started 24-hour periods. A final partial period is charged when the body is released. Use 0 for no recurring fee.</p>
                                @error('mortuary_daily_storage_fee')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="state_mortuary_tax_daily_fee" class="{{ $label }}">State mortuary tax per started 24 hours (₦) <span class="text-red-600">*</span></label>
                                <input id="state_mortuary_tax_daily_fee" name="state_mortuary_tax_daily_fee" type="number" step="0.01" min="0" max="99999999" required value="{{ old('state_mortuary_tax_daily_fee', $settings->state_mortuary_tax_daily_fee ?? 0) }}" class="{{ $input }}">
                                <p class="mt-1 text-xs text-slate-500">Added to the mortuary invoice alongside storage and must be paid before release. Use 0 if no State tax applies.</p>
                                @error('state_mortuary_tax_daily_fee')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="border-t pt-5">
                                <h4 class="font-semibold text-slate-900">Inpatient care charges</h4>
                                <p class="mt-1 text-sm text-slate-500">Oxygen and insulin machine use are service charges, separate from pharmacy stock items.</p>
                            </div>
                            <div>
                                <label for="oxygen_per_administration_fee" class="{{ $label }}">Oxygen fee per administration (₦) <span class="text-red-600">*</span></label>
                                <input id="oxygen_per_administration_fee" name="oxygen_per_administration_fee" type="number" step="0.01" min="0" max="99999999" required value="{{ old('oxygen_per_administration_fee', $settings->oxygen_per_administration_fee ?? 0) }}" class="{{ $input }}">
                                <p class="mt-1 text-xs text-slate-500">Added for each recorded oxygen administration. Use 0 for no charge.</p>
                                @error('oxygen_per_administration_fee')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="nebulizer_fee_per_three_uses" class="{{ $label }}">Insulin machine fee per block of up to 3 bottles (₦) <span class="text-red-600">*</span></label>
                                <input id="nebulizer_fee_per_three_uses" name="nebulizer_fee_per_three_uses" type="number" step="0.01" min="0" max="99999999" required value="{{ old('nebulizer_fee_per_three_uses', $settings->nebulizer_fee_per_three_uses ?? 0) }}" class="{{ $input }}">
                                <p class="mt-1 text-xs text-slate-500">A block covers up to 3 insulin bottles. A new invoice block is required after the third bottle. Use 0 for no machine charge.</p>
                                @error('nebulizer_fee_per_three_uses')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="sugar_check_fee" class="{{ $label }}">Blood sugar check fee (₦) <span class="text-red-600">*</span></label>
                                <input id="sugar_check_fee" name="sugar_check_fee" type="number" step="0.01" min="0" max="99999999" required value="{{ old('sugar_check_fee', $settings->sugar_check_fee ?? 0) }}" class="{{ $input }}">
                                <p class="mt-1 text-xs text-slate-500">Added to the inpatient invoice for each sugar check recorded by nursing staff. Use 0 for no charge.</p>
                                @error('sugar_check_fee')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            @if ($onlinePaymentsEnabled)
                            <div class="border-t pt-5">
                                <div class="mb-4">
                                    <h4 class="font-semibold text-slate-900">Paystack credentials</h4>
                                    <p class="mt-1 text-sm text-slate-500">The secret key is encrypted at rest and never shown after saving. Leave it blank to keep the saved key.</p>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2" x-show="gateway === 'paystack'" x-cloak>
                                    <div>
                                        <label for="paystack_public_key" class="{{ $label }}">Public key</label>
                                        <input id="paystack_public_key" name="paystack_public_key" type="text" autocomplete="off" value="{{ old('paystack_public_key', $settings->paystack_public_key) }}" placeholder="pk_live_..." class="{{ $input }}">
                                        @error('paystack_public_key')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="paystack_secret_key" class="{{ $label }}">Secret key</label>
                                        <input id="paystack_secret_key" name="paystack_secret_key" type="password" autocomplete="new-password" value="" placeholder="{{ filled($settings->paystack_secret_key) ? 'Saved securely; leave blank to keep current key' : 'sk_live_...' }}" class="{{ $input }}">
                                        @error('paystack_secret_key')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                        @if (filled($settings->paystack_secret_key))
                                            <label class="mt-3 inline-flex items-center gap-2 text-sm text-red-700">
                                                <input type="checkbox" name="clear_paystack_secret_key" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                                                Clear saved secret key
                                            </label>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600" x-show="gateway === 'manual'" x-cloak>
                                    Online checkout will be unavailable while in-person billing is selected. Saved Paystack credentials are retained in case online payments are re-enabled.
                                </div>
                            </div>
                            @endif
                        </div>
                    @else
                        <p class="mb-5 text-sm text-slate-600">Choose two required brand colors and up to two additional colors. Changes apply across the staff interface and patient portal.</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ([
                                ['primary_color', 'Primary color', 'Main navigation, buttons, links, and focus states'],
                                ['secondary_color', 'Secondary color', 'Supporting highlights and information panels'],
                            ] as [$field, $title, $description])
                                <label for="{{ $field }}" class="rounded-xl border p-4">
                                    <span class="block text-sm font-semibold text-slate-900">{{ $title }} <span class="text-red-600">*</span></span>
                                    <span class="mt-1 block text-xs text-slate-500">{{ $description }}</span>
                                    <input id="{{ $field }}" name="{{ $field }}" type="color" required value="{{ old($field, $settings->{$field} ?: ($field === 'primary_color' ? '#0f766e' : '#1d4ed8')) }}" class="mt-3 h-12 w-full cursor-pointer rounded-lg border border-slate-200 bg-white p-1">
                                    @error($field)<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                                </label>
                            @endforeach

                            @foreach ([
                                ['accent_color', 'use_accent_color', 'Accent color'],
                                ['highlight_color', 'use_highlight_color', 'Highlight color'],
                            ] as [$field, $toggle, $title])
                                <div class="rounded-xl border p-4" x-data="{ enabled: @js((bool) old($toggle, filled($settings->{$field}))) }">
                                    <span class="block text-sm font-semibold text-slate-900">{{ $title }} <span class="font-normal text-slate-400">(optional)</span></span>
                                    <input type="hidden" name="{{ $toggle }}" value="0">
                                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" name="{{ $toggle }}" value="1" x-model="enabled" class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                                        Use this color
                                    </label>
                                    <input name="{{ $field }}" type="color" value="{{ old($field, $settings->{$field} ?: ($field === 'accent_color' ? '#0f766e' : '#1d4ed8')) }}" class="mt-3 h-12 w-full cursor-pointer rounded-lg border border-slate-200 bg-white p-1" x-bind:disabled="!enabled">
                                    @error($field)<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 grid gap-5 border-t pt-6 sm:grid-cols-2">
                            <div>
                                <label for="logo" class="{{ $label }}">Hospital logo</label>
                                @if ($settings->logo_url)
                                    <div class="mt-3 flex items-center gap-4">
                                        <img src="{{ $settings->logo_url }}" alt="Current hospital logo" class="h-16 max-w-[10rem] rounded border bg-white object-contain p-1">
                                        <label class="inline-flex items-center gap-2 text-sm text-red-600"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">Remove logo</label>
                                    </div>
                                @endif
                                <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-teal-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-teal-700 hover:file:bg-teal-100">
                                <p class="mt-1 text-xs text-slate-500">PNG, JPG, or WebP up to 2 MB.</p>
                                @error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="administrator_signature" class="{{ $label }}">Authorized signature for patient cards</label>
                                @if ($settings->administrator_signature_path)
                                    <div class="mt-3 flex items-center gap-4">
                                        <img src="{{ route('settings.administrator-signature') }}" alt="Current authorized signature" class="h-16 max-w-48 rounded border bg-white object-contain p-2">
                                        <label class="inline-flex items-center gap-2 text-sm text-red-600"><input type="checkbox" name="remove_administrator_signature" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">Remove signature</label>
                                    </div>
                                @endif
                                <input id="administrator_signature" name="administrator_signature" type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-teal-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-teal-700 hover:file:bg-teal-100">
                                <p class="mt-1 text-xs text-slate-500">PNG, JPG, or WebP up to 2 MB. Stored privately and shown on printed patient cards.</p>
                                @error('administrator_signature')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    @endif

                    <div class="mt-7 flex justify-end border-t pt-5">
                        <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-800">Save {{ strtolower($sections[$section]['title']) }}</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
