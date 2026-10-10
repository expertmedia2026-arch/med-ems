<x-app-layout>
    <x-slot name="header">Hospital Settings</x-slot>

    @php
        $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
        $label = 'block text-sm font-medium text-slate-700';
    @endphp

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data"
          class="max-w-3xl rounded-xl border bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <p class="mb-6 text-sm text-slate-600">These details appear on printed receipts and in the sidebar.</p>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="{{ $label }}">Hospital name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $settings->name) }}" required class="{{ $input }}">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <label for="address" class="{{ $label }}">Address</label>
                <textarea id="address" name="address" rows="2" class="{{ $input }}">{{ old('address', $settings->address) }}</textarea>
                @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="phone" class="{{ $label }}">Phone</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone', $settings->phone) }}" class="{{ $input }}">
                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="{{ $label }}">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $settings->email) }}" class="{{ $input }}">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="website" class="{{ $label }}">Website</label>
                <input id="website" name="website" type="text" value="{{ old('website', $settings->website) }}" class="{{ $input }}">
            </div>

            <div>
                <label for="receipt_footer" class="{{ $label }}">Receipt footer message</label>
                <input id="receipt_footer" name="receipt_footer" type="text"
                       value="{{ old('receipt_footer', $settings->receipt_footer) }}" placeholder="Thank you. Get well soon." class="{{ $input }}">
            </div>

            <div>
                <label for="registration_fee" class="{{ $label }}">Registration fee (₦)</label>
                <input id="registration_fee" name="registration_fee" type="number" step="0.01" min="0" required
                    value="{{ old('registration_fee', $settings->registration_fee) }}" class="{{ $input }}">
                <p class="mt-1 text-xs text-slate-500">Charged once to each newly registered patient. Use 0 for no fee.</p>
                @error('registration_fee')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2 border-t pt-5">
                <h2 class="font-semibold text-slate-800">Paystack payment gateway</h2>
                <p class="mt-1 text-sm text-slate-500">Saved credentials are used for patient online payments. The secret key is encrypted and never displayed after saving.</p>
            </div>

            <div class="sm:col-span-2">
                <label for="paystack_public_key" class="{{ $label }}">Paystack public key</label>
                <input id="paystack_public_key" name="paystack_public_key" type="text" autocomplete="off"
                       value="{{ old('paystack_public_key', $settings->paystack_public_key) }}" placeholder="pk_live_..." class="{{ $input }}">
                @error('paystack_public_key')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <label for="paystack_secret_key" class="{{ $label }}">Paystack secret key</label>
                <input id="paystack_secret_key" name="paystack_secret_key" type="password" autocomplete="new-password"
                       value="" placeholder="{{ filled($settings->paystack_secret_key) ? 'Saved securely; leave blank to keep current key' : 'sk_live_...' }}" class="{{ $input }}">
                <p class="mt-1 text-xs text-slate-500">Leave blank to keep the saved key. The environment key remains a fallback if no key is saved here.</p>
                @error('paystack_secret_key')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @if (filled($settings->paystack_secret_key))
                    <label class="mt-3 inline-flex items-center gap-2 text-sm text-red-700">
                        <input type="checkbox" name="clear_paystack_secret_key" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                        Clear saved secret key
                    </label>
                @endif
            </div>

            <div class="sm:col-span-2">
                <label for="logo" class="{{ $label }}">Logo</label>

                @if ($settings->logo_url)
                    <div class="mt-2 flex items-center gap-4">
                        <img src="{{ $settings->logo_url }}" alt="Current logo" class="h-16 max-w-[10rem] rounded border bg-white object-contain p-1">
                        <label class="flex items-center gap-2 text-sm text-red-600">
                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                            Remove logo
                        </label>
                    </div>
                @endif

                <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                       class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-teal-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-teal-700 hover:file:bg-teal-100">
                <p class="mt-1 text-xs text-slate-500">PNG, JPG or WebP, up to 2 MB. Uploading a new file replaces the current logo.</p>
                @error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="mt-6">
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">Save settings</button>
        </div>
    </form>
</x-app-layout>