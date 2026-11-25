@if($simkugHeader ?? false)
    <span data-header-copy-item><span data-header-copy-value>{{ $value }}</span>@if($value !== null && (string) $value !== '') <button type="button" class="btn btn-link btn-sm p-1" data-header-copy aria-label="Salin {{ $label }}" title="Salin {{ $label }}"><i class="ti ti-copy" aria-hidden="true"></i></button>@endif</span>
@else
    {{ $value }}
@endif
