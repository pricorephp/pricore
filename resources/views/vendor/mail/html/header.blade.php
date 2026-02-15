@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo-v2.1.png" class="logo" alt="Laravel Logo">
@elseif (trim($slot) === config('app.name'))
<img src="{{ asset('logo.svg') }}" alt="{{ config('app.name') }}" style="height: 40px; width: auto;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
