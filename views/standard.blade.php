🚨 <b>{{ $level }}</b>

📍 <b>Environment:</b> {{ $environment }}
📍 <b>Time:</b> {{ $datetime }}

@if($context)
📍 <b>Context:</b>
<pre>{{ json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
@endif

@if($extra)
📍 <b>Extra:</b>
<pre>{{ json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
@endif

📍 <b>Message:</b>
<pre>{{ $message }}</pre>

