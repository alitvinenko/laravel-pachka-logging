**{{ $appName }}** | {{ $appEnv }} | {{ $level_name }}
`{{ $datetime->format('Y-m-d H:i:s') }}`
@if(!empty($extra['url']))
**{{ $extra['http_method'] ?? 'CLI' }}** {{ $extra['url'] }}@if(!empty($extra['ip'])) (IP: {{ $extra['ip'] }})@endif
@endif
@if(!empty($context['exception']['file']))
{{ $context['exception']['file'] }}
@elseif(!empty($extra['file']))
{{ $extra['class'] ?? '' }}{{ isset($extra['class'], $extra['function']) ? '::' : '' }}{{ $extra['function'] ?? '' }} — {{ $extra['file'] }}:{{ $extra['line'] }}
@endif
```
{{ $message }}
@if(!empty($context))
{{ json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}
@endif
```