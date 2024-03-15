<?php

namespace DTApi\Services;

use DTApi\Helpers\TeHelper;
use DTApi\Helpers\DateTimeHelper;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;
use Illuminate\Support\Facades\Http;

class NotificationService
{

    /**
     * Function to check if need to send the push
     * @param $userId
     * @return bool
     */
    public function isNeedToSendPush($userId)
    {
        $notGetNotification = TeHelper::getUsermeta($userId, 'not_get_notification');

        return $notGetNotification === 'yes' ? true : false;
    }

    /**
     * Function to delay the push
     * @param $userId
     * @return bool
     */
    public function isNeedToDelayPush($userId)
    {
        if (!DateTimeHelper::isNightTime()) 
            return false;
        
        $notGetNightTime = TeHelper::getUsermeta($userId, 'not_get_nighttime');

        return $notGetNightTime == 'yes' ? true : false;
    }

    /**
     * Function to send Onesignal Push Notifications with User-Tags
     * @param $users
     * @param $jobId
     * @param $data
     * @param $msgText
     * @param $isNeedDelay
     */
    public function sendPushNotificationToSpecificUsers(
        $users, 
        $jobId, 
        $data, 
        $msgText, 
        $isNeedDelay
    ){

        $logger = new Logger('push_logger');

        $logger->pushHandler(new StreamHandler(storage_path('logs/push/laravel-' . date('Y-m-d') . '.log'), Logger::DEBUG));
        $logger->pushHandler(new FirePHPHandler());
        $logger->addInfo(
            'Push send for job ' . $jobId, 
            [
                $users, 
                $data, 
                $msgText, 
                $isNeedDelay
            ]
        );
        if (App::environment == 'production') {
            $onesignalAppID = config('app.prodOnesignalAppID');
            $onesignalRestAuthKey = sprintf("Authorization: Basic %s", config('app.prodOnesignalApiKey'));
        } else {
            $onesignalAppID = config('app.devOnesignalAppID');
            $onesignalRestAuthKey = sprintf("Authorization: Basic %s", config('app.devOnesignalApiKey'));
        }

        $userTags = $this->getUserTagsStringFromArray($users);

        $data['job_id'] = $jobId;
        $iosSound = 'default';
        $androidSound = 'default';

        if ($data['notification_type'] == 'suitable_job') {
            $androidSound = $data['immediate'] == 'no' ? 'normal_booking' : 'emergency_booking';
            $iosSound = $data['immediate'] == 'no' ? 'normal_booking.mp3' : 'emergency_booking.mp3';
        }

        $fields = array(
            'app_id'         => $onesignalAppID,
            'tags'           => json_decode($userTags),
            'data'           => $data,
            'title'          => array('en' => 'DigitalTolk'),
            'contents'       => $msgText,
            'ios_badgeType'  => 'Increase',
            'ios_badgeCount' => 1,
            'android_sound'  => $androidSound,
            'ios_sound'      => $iosSound
        );

        if ($isNeedDelay) {
            $nextBusinessTime = DateTimeHelper::getNextBusinessTimeString();
            $fields['send_after'] = $nextBusinessTime;
        }
        
        $fields = json_encode($fields);
        $response = Http::post('https://onesignal.com/api/v1/notifications', $fields);
        $logger->addInfo('Push send for job ' . $jobId . ' curl answer', [$response]);
    }
    
}
