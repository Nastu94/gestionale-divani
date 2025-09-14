@component('mail::message')
# @lang('orders.email.request_title')

@lang('orders.email.request_intro', ['order' => $order->orderNumber->full ?? ('#'.$order->id)])

@component('mail::button', ['url' => $confirmUrl])
@lang('orders.email.request_cta')
@endcomponent

@lang('orders.email.request_footer', ['days' => $ttlDays])

@if($replacePrevious)
> @lang('orders.email.request_replace_note')
@endif

@endcomponent
