@php
    $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
    $label = 'block text-sm font-medium text-slate-700';
    $val   = fn ($field) => old($field, $patient?->{$field});
    $dob   = old('date_of_birth', $patient?->date_of_birth?->format('Y-m-d'));
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    @foreach ([['first_name', 'First name', 'text'], ['last_name', 'Last name', 'text']] as [$name, $text, $type])
        <div>
            <label for="{{ $name }}" class="{{ $label }}">{{ $text }}</label>
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $val($name) }}" required class="{{ $input }}">
            @error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endforeach

    <div>
        <label for="gender" class="{{ $label }}">Gender</label>
        <select id="gender" name="gender" required class="{{ $input }}">
            <option value="">Select…</option>
            @foreach (['male' => 'Male', 'female' => 'Female'] as $v => $t)
                <option value="{{ $v }}" @selected($val('gender') === $v)>{{ $t }}</option>
            @endforeach
        </select>
        @error('gender')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_of_birth" class="{{ $label }}">Date of birth</label>
        <input id="date_of_birth" name="date_of_birth" type="date" value="{{ $dob }}" max="{{ today()->toDateString() }}" required class="{{ $input }}">
        @error('date_of_birth')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2 rounded-lg border border-slate-200 p-4">
        <label for="photo" class="{{ $label }}">Patient photograph</label>
        <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start">
            <img id="patient-photo-preview"
                 src="{{ $patient?->photo_path ? route('patients.photo', $patient) : '' }}"
                 alt="Patient photograph preview"
                 @if (! $patient?->photo_path) hidden @endif
                 class="h-32 w-32 rounded-lg border bg-slate-50 object-cover">
            <div class="min-w-0 flex-1">
                <input id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp"
                       class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-teal-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-teal-700 hover:file:bg-teal-100">
                <p class="mt-1 text-xs text-slate-500">Upload JPG, PNG or WebP, up to 5 MB, or capture a photo with a connected camera.</p>
                @error('photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="mt-3 flex flex-wrap gap-2">
                    <button id="start-camera" type="button" class="rounded-lg border border-teal-700 px-3 py-2 text-sm font-medium text-teal-800 hover:bg-teal-50">Use camera</button>
                    <button id="capture-photo" type="button" hidden class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800">Capture photo</button>
                    <button id="stop-camera" type="button" hidden class="rounded-lg border px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">Close camera</button>
                </div>
                <label id="camera-picker-label" for="camera-picker" hidden class="mt-3 block text-sm text-slate-600">Camera
                    <select id="camera-picker" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm"></select>
                </label>
                <video id="camera-preview" autoplay playsinline muted hidden class="mt-3 max-h-64 w-full rounded-lg bg-black object-contain"></video>
                <p id="camera-message" role="status" class="mt-2 text-sm text-red-700"></p>
            </div>
        </div>
        <canvas id="photo-canvas" hidden></canvas>
    </div>

    <div class="sm:col-span-2">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input name="is_nhis" type="hidden" value="0">
            <input id="is_nhis" name="is_nhis" type="checkbox" value="1" @checked((bool) old('is_nhis', $patient?->is_nhis)) class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
            Patient is enrolled with NHIS
        </label>
        @error('is_nhis')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

        <div id="nhis_number_field" class="mt-4" @if (!old('is_nhis', $patient?->is_nhis)) hidden @endif>
            <label for="nhis_number" class="{{ $label }}">NHIS number</label>
            <input id="nhis_number" name="nhis_number" type="text" value="{{ $val('nhis_number') }}" maxlength="50" class="{{ $input }}">
            @error('nhis_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    @foreach ([['phone', 'Phone', 'tel'], ['email', 'Email', 'email']] as [$name, $text, $type])
        <div>
            <label for="{{ $name }}" class="{{ $label }}">{{ $text }}</label>
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $val($name) }}" class="{{ $input }}">
            @error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endforeach

    <div class="sm:col-span-2">
        <label for="address" class="{{ $label }}">Address</label>
        <textarea id="address" name="address" rows="2" class="{{ $input }}">{{ $val('address') }}</textarea>
        @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="blood_group" class="{{ $label }}">Blood group</label>
        <select id="blood_group" name="blood_group" class="{{ $input }}">
            <option value="">Unknown</option>
            @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg)
                <option value="{{ $bg }}" @selected($val('blood_group') === $bg)>{{ $bg }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="genotype" class="{{ $label }}">Genotype</label>
        <select id="genotype" name="genotype" class="{{ $input }}">
            <option value="">Unknown</option>
            @foreach (['AA', 'AS', 'SS', 'AC', 'SC'] as $g)
                <option value="{{ $g }}" @selected($val('genotype') === $g)>{{ $g }}</option>
            @endforeach
        </select>
    </div>

    <div class="sm:col-span-2">
        <label for="allergies" class="{{ $label }}">Known allergies</label>
        <input id="allergies" name="allergies" type="text" value="{{ $val('allergies') }}" placeholder="e.g. Penicillin" class="{{ $input }}">
    </div>

    @foreach ([['next_of_kin_name', 'Next of kin', 'text'], ['next_of_kin_phone', 'Next of kin phone', 'tel']] as [$name, $text, $type])
        <div>
            <label for="{{ $name }}" class="{{ $label }}">{{ $text }}</label>
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $val($name) }}" class="{{ $input }}">
        </div>
    @endforeach
</div>

<script>
    (() => {
        const enrolled = document.getElementById('is_nhis');
        const numberField = document.getElementById('nhis_number_field');
        const numberInput = document.getElementById('nhis_number');

        const updateNhisField = () => {
            numberField.hidden = !enrolled.checked;
            numberInput.required = enrolled.checked;
        };

        enrolled.addEventListener('change', updateNhisField);
        updateNhisField();
    })();
</script>

<script>
    (() => {
        const input = document.getElementById('photo');
        const preview = document.getElementById('patient-photo-preview');
        const startButton = document.getElementById('start-camera');
        const captureButton = document.getElementById('capture-photo');
        const stopButton = document.getElementById('stop-camera');
        const video = document.getElementById('camera-preview');
        const canvas = document.getElementById('photo-canvas');
        const cameraPicker = document.getElementById('camera-picker');
        const pickerLabel = document.getElementById('camera-picker-label');
        const message = document.getElementById('camera-message');
        let stream = null;

        const stopCamera = () => {
            stream?.getTracks().forEach((track) => track.stop());
            stream = null;
            video.hidden = true;
            captureButton.hidden = true;
            stopButton.hidden = true;
            pickerLabel.hidden = true;
        };

        const showPreview = (file) => {
            if (!file) return;
            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
        };

        input.addEventListener('change', () => showPreview(input.files[0]));
        startButton.addEventListener('click', async () => {
            message.textContent = '';
            if (!navigator.mediaDevices?.getUserMedia) {
                message.textContent = 'Camera access needs a secure connection (HTTPS or localhost) and a supported browser.';
                return;
            }

            try {
                stopCamera();
                const selectedCamera = cameraPicker.value;
                stream = await navigator.mediaDevices.getUserMedia({
                    video: selectedCamera ? { deviceId: { exact: selectedCamera } } : { facingMode: 'user' },
                    audio: false,
                });
                video.srcObject = stream;
                video.hidden = false;
                captureButton.hidden = false;
                stopButton.hidden = false;
                const cameras = await navigator.mediaDevices.enumerateDevices();
                const options = cameras.filter((device) => device.kind === 'videoinput');
                if (options.length > 1) {
                    cameraPicker.replaceChildren(...options.map((device, index) => new Option(device.label || `Camera ${index + 1}`, device.deviceId)));
                    cameraPicker.value = stream.getVideoTracks()[0].getSettings().deviceId || '';
                    pickerLabel.hidden = false;
                }
            } catch (error) {
                message.textContent = error.name === 'NotAllowedError'
                    ? 'Allow camera access in your browser, then try again.'
                    : 'Could not open a camera. Check that it is connected and not in use by another app.';
                stopCamera();
            }
        });

        cameraPicker.addEventListener('change', () => startButton.click());
        stopButton.addEventListener('click', stopCamera);
        captureButton.addEventListener('click', async () => {
            if (!video.videoWidth) return;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
            if (!blob) return;
            const transfer = new DataTransfer();
            transfer.items.add(new File([blob], 'patient-photo.jpg', { type: 'image/jpeg' }));
            input.files = transfer.files;
            showPreview(input.files[0]);
            stopCamera();
        });

        window.addEventListener('pagehide', stopCamera, { once: true });
    })();
</script>
