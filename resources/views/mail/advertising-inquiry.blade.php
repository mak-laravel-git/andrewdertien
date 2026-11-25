Someone wants to advertise on the Pond.

Name: {{ $inquiry->name }}
Email: {{ $inquiry->email }}
@if ($inquiry->company)
Company / project: {{ $inquiry->company }}
@endif
@if ($inquiry->website)
Website: {{ $inquiry->website }}
@endif

What they'd like to promote:
{{ $inquiry->message }}

Reply to this email to answer them directly.
