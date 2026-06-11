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

