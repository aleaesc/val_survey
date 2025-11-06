@component('mail::message')
# New Survey Submitted

A new client satisfaction survey was submitted.

@component('mail::panel')
@endcomponent

@if(!empty($survey->comments))
**Comments:**

> {{ $survey->comments }}
@endif

**Ratings**

@php($ratings = $survey->ratings ?? collect())
@if($ratings && count($ratings))
@component('mail::table')
| Question | Value |
|:--------:|:-----:|
@foreach($ratings as $r)
| {{ $r->question_id }} | {{ $r->value }} |
@endforeach
@endcomponent
@else
No ratings found.
@endif

 Thanks,
 {{ config('app.name') }}
@endcomponent
