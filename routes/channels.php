<?php

use Illuminate\Support\Facades\Broadcast;

//Listens to notifications channel 
Broadcast::channel('notifications.{id}', function ($user, $id) {
    //Debuggin    
    logger('[WS AUTH] user:', [
        'auth_user_id' => $user->id,
        'channel_user_id' => $id
    ]);

    return (string) $user->id === (string) $id;
});

Broadcast::channel('bookings.client.{clientId}', function ($user, $clientId) {
    return (string) $user->id === (string) $clientId;
});

Broadcast::channel('bookings.professional.{professionalId}', function ($user, $professionalId) {
    return (string) $user->professionalProfile?->id === (string) $professionalId;
});
