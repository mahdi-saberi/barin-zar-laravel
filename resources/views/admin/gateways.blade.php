<div class="admin-studio-grid">
    @foreach($gatewayDefinitions as $provider => $definition)
        @php($record = $gatewayRecords->get($provider))
        <form class="admin-card admin-panel-form" method="post" action="{{ route('admin.gateways.save', $provider, false) }}" autocomplete="off">
            @csrf
            <h2>{{ $definition['name'] }}</h2>
            <p><span class="admin-badge {{ $record?->enabled ? 'badge-success' : 'badge-neutral' }}">{{ $record?->enabled ? 'فعال' : 'غیرفعال' }}</span></p>
            @foreach($definition['fields'] as $key => $label)
                <label>{{ $label }}<input type="password" dir="ltr" name="credentials[{{ $key }}]" maxlength="255" autocomplete="new-password"
                    @if(filled($record?->credentials[$key] ?? null)) placeholder="ذخیره شده" @endif></label>
            @endforeach
            <label class="admin-checkbox">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked($record?->enabled)>
                نمایش درگاه هنگام خرید
            </label>
            <button class="admin-button admin-primary" type="submit">ذخیرهٔ {{ $definition['name'] }}</button>
        </form>
    @endforeach
</div>
