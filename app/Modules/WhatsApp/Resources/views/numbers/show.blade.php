<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Client Details</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <div class="grid gap-6 lg:grid-cols-[0.7fr_1.3fr]">

                {{-- Left: client + number details --}}
                <div class="space-y-6">
                    <section class="ui-card ui-card-pad">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="ui-title">Client</h3>
                            @if ($number->client)
                                <div class="flex items-center gap-2">
                                    @if (request('edit') === 'client')
                                        <a href="{{ route('modules.whatsapp.numbers.show', $number) }}" class="ui-pill">Cancel</a>
                                    @else
                                        <a href="{{ route('modules.whatsapp.numbers.show', $number) }}?edit=client" class="ui-pill">Edit</a>
                                    @endif
                                    <form method="POST" action="{{ route('modules.whatsapp.numbers.client.destroy', $number) }}" onsubmit="return confirm('Delete this client? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-pill">Delete</button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        @if (session('status'))
                            <div class="ui-alert mt-3">{{ session('status') }}</div>
                        @endif

                        @if ($number->client && request('edit') === 'client')
                            <form method="POST" action="{{ route('modules.whatsapp.numbers.client.update', $number) }}" class="mt-4 space-y-3 text-sm">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label class="ui-muted block mb-1">Full name</label>
                                    <input type="text" name="full_name" value="{{ old('full_name', $number->client->full_name) }}" class="ui-control w-full">
                                    @error('full_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Email</label>
                                    <input type="email" name="email" value="{{ old('email', $number->client->email) }}" class="ui-control w-full">
                                    @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Gender</label>
                                    <input type="text" name="gender" value="{{ old('gender', $number->client->gender) }}" class="ui-control w-full">
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Emirate</label>
                                    <select name="region_id" class="ui-control w-full">
                                        <option value="">— none —</option>
                                        @foreach ($regions as $region)
                                            <option value="{{ $region->id }}" @selected(old('region_id', $number->client->region_id) == $region->id)>{{ $region->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('region_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Community</label>
                                    <select name="community_id" class="ui-control w-full">
                                        <option value="">— none —</option>
                                        @foreach ($regions as $region)
                                            <optgroup label="{{ $region->name }}">
                                                @foreach ($region->communities->sortBy('name') as $community)
                                                    <option value="{{ $community->id }}" @selected(old('community_id', $number->client->community_id) == $community->id)>{{ $community->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                    @error('community_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Nationality</label>
                                    <input type="text" name="nationality" value="{{ old('nationality', $number->client->nationality) }}" class="ui-control w-full">
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Resident</label>
                                    <input type="text" name="resident" value="{{ old('resident', $number->client->resident) }}" class="ui-control w-full">
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Interest</label>
                                    <input type="text" name="interest" value="{{ old('interest', $number->client->interest) }}" class="ui-control w-full">
                                </div>
                                <div class="pt-1">
                                    <button type="submit" class="ui-button">Save changes</button>
                                </div>
                            </form>
                        @else
                            <dl class="mt-4 space-y-3 text-sm">
                                <div>
                                    <dt class="ui-muted">Full name</dt>
                                    <dd class="ui-strong">{{ $number->client?->full_name ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Email</dt>
                                    <dd class="ui-strong">{{ $number->client?->email ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Gender</dt>
                                    <dd class="ui-strong">{{ $number->client?->gender ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Emirate</dt>
                                    <dd class="ui-strong">{{ $number->client?->region?->name ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Community</dt>
                                    <dd class="ui-strong">{{ $number->client?->community?->name ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Country</dt>
                                    <dd class="ui-strong">{{ $number->client?->country?->name ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Nationality</dt>
                                    <dd class="ui-strong">{{ $number->client?->nationality ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Resident</dt>
                                    <dd class="ui-strong">{{ $number->client?->resident ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Interest</dt>
                                    <dd class="ui-strong">{{ $number->client?->interest ?: '-' }}</dd>
                                </div>
                            </dl>
                        @endif
                    </section>

                    <section class="ui-card ui-card-pad">
                        @php $editingNumber = request('edit') === 'number' || $errors->hasAny(['normalized_phone','raw_phone','country_code','national_number','detected_country','label','priority','verification_status']); @endphp
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="ui-title">Phone number</h3>
                            @if ($editingNumber)
                                <a href="{{ route('modules.whatsapp.numbers.show', $number) }}" class="ui-pill">Cancel</a>
                            @else
                                <a href="{{ route('modules.whatsapp.numbers.show', $number) }}?edit=number" class="ui-pill">Edit</a>
                            @endif
                        </div>

                        @if ($editingNumber)
                            <form method="POST" action="{{ route('modules.whatsapp.numbers.update', $number) }}" class="mt-4 space-y-3 text-sm">
                                @csrf
                                @method('PATCH')

                                @if ($errors->any())
                                    <div class="ui-alert">
                                        <ul class="list-disc list-inside space-y-1">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div>
                                    <label class="ui-muted block mb-1">Normalized phone <span class="text-red-500">*</span></label>
                                    <input type="text" name="normalized_phone" value="{{ old('normalized_phone', $number->normalized_phone) }}" class="ui-control w-full" required>
                                    @error('normalized_phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Raw phone</label>
                                    <input type="text" name="raw_phone" value="{{ old('raw_phone', $number->raw_phone) }}" class="ui-control w-full">
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Country code</label>
                                    <input type="text" name="country_code" value="{{ old('country_code', $number->country_code) }}" class="ui-control w-full" placeholder="e.g. 971">
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">National number</label>
                                    <input type="text" name="national_number" value="{{ old('national_number', $number->national_number) }}" class="ui-control w-full">
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Detected country</label>
                                    <input type="text" name="detected_country" value="{{ old('detected_country', $number->detected_country) }}" class="ui-control w-full" placeholder="e.g. AE">
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Label</label>
                                    <input type="text" name="label" value="{{ old('label', $number->label) }}" class="ui-control w-full">
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Priority <span class="text-red-500">*</span></label>
                                    <input type="number" name="priority" value="{{ old('priority', $number->priority) }}" class="ui-control w-full" min="0" max="9999" required>
                                    @error('priority') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="ui-muted block mb-1">Verification status <span class="text-red-500">*</span></label>
                                    <select name="verification_status" class="ui-control w-full">
                                        @foreach (['unverified', 'verified', 'invalid'] as $status)
                                            <option value="{{ $status }}" @selected(old('verification_status', $number->verification_status) === $status)>
                                                {{ ucfirst($status) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="space-y-2 pt-1">
                                    <label class="ui-muted block">Flags</label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $number->is_primary)) class="rounded">
                                        <span class="text-sm">Primary</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="is_whatsapp" value="1" @checked(old('is_whatsapp', $number->is_whatsapp)) class="rounded">
                                        <span class="text-sm">WhatsApp</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="is_uae" value="1" @checked(old('is_uae', $number->is_uae)) class="rounded">
                                        <span class="text-sm">UAE</span>
                                    </label>
                                </div>
                                <div class="pt-1">
                                    <button type="submit" class="ui-button">Save changes</button>
                                </div>
                            </form>
                        @else
                            <dl class="mt-4 space-y-3 text-sm">
                                <div>
                                    <dt class="ui-muted">Normalized</dt>
                                    <dd class="ui-strong">{{ $number->normalized_phone }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Raw</dt>
                                    <dd class="ui-strong">{{ $number->raw_phone ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Country code</dt>
                                    <dd class="ui-strong">{{ $number->country_code ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">National number</dt>
                                    <dd class="ui-strong">{{ $number->national_number ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Detected country</dt>
                                    <dd class="ui-strong">{{ $number->detected_country ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Label</dt>
                                    <dd class="ui-strong">{{ $number->label ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Priority</dt>
                                    <dd class="ui-strong">{{ $number->priority }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Verification</dt>
                                    <dd class="ui-strong">{{ ucfirst($number->verification_status) }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Flags</dt>
                                    <dd class="flex flex-wrap gap-1 mt-0.5">
                                        @if ($number->is_primary) <span class="ui-pill">Primary</span> @endif
                                        @if ($number->is_whatsapp) <span class="ui-pill">WhatsApp</span> @endif
                                        @if ($number->is_uae) <span class="ui-pill">UAE</span> @endif
                                        @if (! $number->is_primary && ! $number->is_whatsapp && ! $number->is_uae)
                                            <span class="ui-muted">None</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Last source</dt>
                                    <dd class="ui-strong">{{ $number->last_source_name ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Last imported</dt>
                                    <dd class="ui-strong">{{ optional($number->last_imported_at)->format('Y-m-d H:i') ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Consecutive failures</dt>
                                    <dd class="ui-strong">{{ $number->whatsAppProfile?->consecutive_failed_count ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Last message status</dt>
                                    <dd class="ui-strong">{{ $number->whatsAppProfile?->last_message_status ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">WhatsApp suppressed</dt>
                                    <dd class="ui-strong">
                                        @if ($number->suppressions->isNotEmpty())
                                            {{ ucfirst(str_replace('_', ' ', $number->suppressions->first()->reason)) }}
                                            &mdash; {{ optional($number->suppressions->first()->suppressed_at)->format('Y-m-d H:i') }}
                                        @else
                                            No
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Messages sent</dt>
                                    <dd class="ui-strong">{{ $number->whatsAppMessages->count() }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Last messaged</dt>
                                    <dd class="ui-strong">{{ optional($number->whatsAppMessages->first()?->scheduled_at)->format('Y-m-d H:i') ?: '-' }}</dd>
                                </div>
                            </dl>
                        @endif
                    </section>
                </div>

                {{-- Right: tables --}}
                <section class="space-y-6">

                    {{-- Client numbers --}}
                    <div class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Client numbers</h3>
                            <p class="mt-1 text-sm ui-muted">
                                @if ($number->client)
                                    {{ $number->client->phoneNumbers->count() }} number{{ $number->client->phoneNumbers->count() === 1 ? '' : 's' }} linked to this client.
                                @else
                                    No linked client.
                                @endif
                            </p>
                        </div>

                        @if ($number->client)
                            <div class="overflow-x-auto">
                                <table class="ui-table">
                                    <thead>
                                        <tr>
                                            <th>Phone</th>
                                            <th>Label</th>
                                            <th>Priority</th>
                                            <th>Messages</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($number->client->phoneNumbers as $clientNumber)
                                            <tr>
                                                <td>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        @if ($clientNumber->id === $number->id)
                                                            <span class="ui-pill ui-pill-active">Current</span>
                                                        @endif
                                                        @if ($clientNumber->is_primary)
                                                            <span class="ui-pill">Primary</span>
                                                        @endif
                                                        @if ($clientNumber->whats_app_messages_count > 0)
                                                            <a href="{{ route('modules.whatsapp.numbers.show', $clientNumber) }}" class="ui-link">
                                                                {{ $clientNumber->normalized_phone }}
                                                            </a>
                                                        @else
                                                            {{ $clientNumber->normalized_phone }}
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>{{ $clientNumber->label ?: '-' }}</td>
                                                <td>{{ $clientNumber->priority }}</td>
                                                <td>{{ $clientNumber->whats_app_messages_count }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="ui-empty">No linked client numbers available.</div>
                        @endif
                    </div>

                    {{-- Message history --}}
                    <div class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Message history</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="ui-table">
                                <thead>
                                    <tr>
                                        <th>Scheduled</th>
                                        <th>Campaign</th>
                                        <th>Template</th>
                                        <th>Status</th>
                                        <th>Clicked</th>
                                        <th>Quick Reply</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($number->whatsAppMessages as $message)
                                        <tr>
                                            <td>{{ optional($message->scheduled_at)->format('Y-m-d H:i') }}</td>
                                            <td>
                                                @if ($message->campaign)
                                                    <a href="{{ route('modules.whatsapp.campaigns.show', $message->campaign) }}" class="ui-link">
                                                        {{ $message->campaign->name }}
                                                    </a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $message->template_name ?: '-' }}</td>
                                            <td>{{ $message->delivery_status ?: '-' }}</td>
                                            <td>{{ $message->clicked ? 'Yes' : 'No' }}</td>
                                            <td>
                                                @if ($message->quick_reply_1)
                                                    {{ $message->quick_reply_1 }}
                                                    @if ($message->quick_reply_2) / {{ $message->quick_reply_2 }} @endif
                                                    @if ($message->quick_reply_3) / {{ $message->quick_reply_3 }} @endif
                                                @else
                                                    &ndash;
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="ui-empty">No message history.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Source history --}}
                    <div class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Source history</h3>
                        </div>
                        <div class="ui-divide">
                            @forelse ($number->sources as $source)
                                <div class="px-5 py-4 text-sm">
                                    <p class="font-medium ui-strong">{{ $source->source_name ?: $source->source_type }}</p>
                                    <p class="ui-muted">{{ $source->source_type }} &ndash; {{ $source->created_at->format('Y-m-d H:i') }}</p>
                                </div>
                            @empty
                                <div class="ui-empty">No source history available.</div>
                            @endforelse
                        </div>
                    </div>

                </section>
            </div>
        </div>
    </div>
</x-app-layout>
