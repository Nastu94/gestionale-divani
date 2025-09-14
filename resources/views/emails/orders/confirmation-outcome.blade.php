@component('mail::message')
# @lang('orders.email.outcome_title')

@lang('orders.email.outcome_intro', ['order' => $order->orderNumber->full ?? ('#'.$order->id)])

@if($accepted)
- **@lang('orders.email.outcome_status')**: ✅ @lang('orders.email.outcome_confirmed')
@else
- **@lang('orders.email.outcome_status')**: ❌ @lang('orders.email.outcome_rejected')
- **@lang('orders.email.outcome_reason')**: "{{ $reason }}"
@endif

@if(!empty($poNumbers))
- **@lang('orders.email.outcome_ponumbers')**: {{ implode(', ', $poNumbers) }}
@endif

@endcomponent
