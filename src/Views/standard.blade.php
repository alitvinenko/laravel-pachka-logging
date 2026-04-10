**{{ $appName }}** | {{ $appEnv }} | {{ $level_name }}
`{{ $datetime->format('Y-m-d H:i:s') }}`
@if(!empty($extra['url']))
**{{ $extra['http_method'] ?? 'CLI' }}** {{ $extra['url'] }}@if(!empty($extra['ip'])) (IP: {{ $extra['ip'] }})@endif
@endif
```
{{ $message }}
@if(!empty($context))
@foreach($context as $key => $value)
{{ $key }}: {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}
@endforeach
@endif
```