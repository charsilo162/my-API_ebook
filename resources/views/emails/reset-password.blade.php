@component('mail::message')
# Hello {{ $user->name }}

You requested to reset your password. Click the button below to reset it:

@component('mail::button', ['url' => $resetLink])
Reset Password
@endcomponent

If you did not request a password reset, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
