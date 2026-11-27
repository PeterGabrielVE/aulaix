<table class="header-table">
    <tr>
        @if ($header->logoDataUri)
            <td class="header-logo">
                <img src="{{ $header->logoDataUri }}" alt="Logo del plantel">
            </td>
        @endif
        <td class="header-info">
            <div class="institution-name">{{ $header->name }}</div>
            @if ($header->deaCode || $header->rif)
                <div class="institution-meta">
                    @if ($header->deaCode)Código DEA: {{ $header->deaCode }}@endif
                    @if ($header->deaCode && $header->rif) &nbsp;·&nbsp; @endif
                    @if ($header->rif)RIF: {{ $header->rif }}@endif
                </div>
            @endif
            @if ($header->location || $header->address)
                <div class="institution-meta">
                    {{ implode(' — ', array_filter([$header->address, $header->location])) }}
                </div>
            @endif
            @if ($header->phone || $header->email)
                <div class="institution-meta">
                    {{ implode(' · ', array_filter([$header->phone, $header->email])) }}
                </div>
            @endif
        </td>
        @if ($header->logoDataUri)
            <td class="header-logo"></td>
        @endif
    </tr>
</table>
