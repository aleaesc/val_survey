@component('mail::message')
# Maraming salamat po!

We received your feedback for the Valenzuela City Client Satisfaction Survey.

@component('mail::panel')
- Service: **{{ $survey->service }}**  
- Date: **{{ optional($survey->created_at)->toDayDateTimeString() }}**
@endcomponent

@if(!empty($survey->comments))
Your comment:

> {{ $survey->comments }}
@endif

We appreciate your time and support in improving our services.

Thanks,
{{ config('app.name') }}
@endcomponent
