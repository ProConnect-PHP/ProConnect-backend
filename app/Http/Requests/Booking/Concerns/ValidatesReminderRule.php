<?php

namespace App\Http\Requests\Booking\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesReminderRule
{
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $channels = [
                    $this->boolean('send_email'),
                    $this->boolean('send_database_notification'),
                    $this->boolean('send_push'),
                    $this->boolean('send_whatsapp'),
                ];

                if (! in_array(true, $channels, true)) {
                    $validator->errors()->add(
                        'channels',
                        'Debe seleccionarse al menos un canal.'
                    );
                }

                $recipients = [
                    $this->boolean('notify_client'),
                    $this->boolean('notify_professional'),
                ];

                if (! in_array(true, $recipients, true)) {
                    $validator->errors()->add(
                        'recipients',
                        'Debe seleccionarse al menos un destinatario.'
                    );
                }
            },
        ];
    }

    protected function reminderRuleRules(): array
    {
        return [
            'minutes_before_start' => ['required', 'integer', 'min:5', 'max:10080'],
            'send_email' => ['required', 'boolean'],
            'send_database_notification' => ['required', 'boolean'],
            'send_push' => ['required', 'boolean'],
            'send_whatsapp' => ['required', 'boolean'],
            'notify_client' => ['required', 'boolean'],
            'notify_professional' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
