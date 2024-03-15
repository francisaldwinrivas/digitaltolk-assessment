<?php

namespace DTApi\Services;

use DTApi\Services\NotificationService;
use DTApi\Repository\JobRepository;
use DTApi\Repository\UserRepository;
use DTApi\Repository\TranslatorRepository;
use DTApi\Repository\TranslatorJobRelRepository;
use DTApi\Repository\UserLanguageRepository;
use DTApi\Data\JobData;
use DTApi\Mailers\MailerInterface;
use DTApi\Helpers\TeHelper;
use DB;
use Log;

class JobService
{
    protected $jobRepository,
              $userRepository,
              $translatorRepository,
              $translatorJobRelRepository,
              $userLanguageRepository,
              $notificationService;

    public function __construct(
        JobRepository $jobRepository, 
        UserRepository $userRepository,
        TranslatorRepository $translatorRepository,
        TranslatorJobRelRepository $translatorJobRelRepository,
        UserLanguageRepository $userLanguageRepository,
        NotificationService $notificationService
    ){
        $this->jobRepository = $jobRepository;
        $this->userRepository = $userRepository;
        $this->translatorRepository = $translatorRepository;
        $this->translatorJobRelRepository = $translatorJobRelRepository;
        $this->userLanguageRepository = $userLanguageRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Retrieves job by id
     * 
     * @param int $id
     * @return DTApi\Models\Job
     */
    public function getJob($id)
    {
        return $this->jobRepositor->with('translatorJobRel.user')->find($id);
    }

    /**
     * Retrieves Jobs by User ID
     * 
     * @param int $userId
     * @return mixed[]
     */
    public function getJobsByUserId($userId)
    {
        $user = $this->repository->find($userId);
        $userType = '';
        $emergencyJobs = array();
        $normalJobs = array();

        if($user->isCustomer()) {
            $jobs = $this->jobsRepository->query()
                ->where('user_id', $user->id)
                ->with(
                    'user.userMeta', 
                    'user.average', 
                    'translatorJobRel.user.average', 
                    'language', 
                    'feedback'
                )
                ->whereIn('status', [
                    'pending', 
                    'assigned', 
                    'started'
                ])
                ->orderBy('due', 'asc')
                ->get();
                
            $userType = 'customer';
        } elseif($user->isTranslator()) {
            $jobs = $this->jobRepository->getTranslatorJobs($user->id, 'new');
            $jobs = $jobs->pluck('jobs')->all();
            $userType = 'translator';
        }

        if($jobs) {
            foreach ($jobs as $jobitem) {
                $jobitem->immediate == 'yes' 
                    ? $emergencyJobs[] = $jobitem
                    : $normalJobs[] = $jobitem;
            }

            $normalJobs = collect($normalJobs)
                ->each(fn ($item, $key) use ($user_id) => 
                    $item['usercheck'] = $this->jobRepository->checkParticularJob($user_id, $item))
                ->sortBy('due')
                ->all();
        }

        return [
            'emergencyJobs' => $emergencyJobs, 
            'normalJobs' => $normalJobs, 
            'cuser' => $user, 
            'userType' => $userType
        ];
    }

    public function getAll(Request $request, $limit = 15)
    {
        $formData = $request->all();
        $user = auth()->user();
        $consumerType = $user->consumer_type;
        $query = $this->jobRepository->query();
        
        // If user is SUPERADMIN
        if($user->user_type == config('user.roles.superadmin')) {

            if(isset($formData['expired_at']) && $formData['expired_at'] != '') {
                $query->where('expired_at', '>=', $formData['expired_at']);
            }

            if(isset($formData['will_expire_at']) && $formData['will_expire_at'] != '') {
                $query->where('will_expire_at', '>=', $formData['will_expire_at']);
            }

            if(isset($formData['customer_email']) && count($formData['customer_email']) && $formData['customer_email'] != '') {
                $users = $this->userRepository->whereIn('email', $formData['customer_email'])->get();
                if($users) {
                    $query->whereIn('user_id', collect($users)->pluck('id')->all());
                }
            }

            if(isset($formData['translator_email']) && count($formData['translator_email'])) {
                $users = $this->userRepository->whereIn('email', $formData['translator_email'])->get();
                if($users) {
                    $translatorJobs = $this->translatorJobRelRepository
                        ->whereNull('cancel_at')
                        ->whereIn('user_id', collect($users)->pluck('id')->all())
                        ->lists('job_id');
                    $query->whereIn('id', $translatorJobs);
                }
            }

            if(isset($formData['physical'])) {
                $query->where('customer_physical_type', $formData['physical']);
                $query->where('ignore_physical', 0);
            }

            if(isset($formData['phone'])) {
                $query->where('customer_phone_type', $formData['phone']);
                if(isset($formData['physical'])) $query->where('ignore_physical_phone', 0);
            }

            if(isset($formData['flagged'])) {
                $query->where('flagged', $formData['flagged']);
                $query->where('ignore_flagged', 0);
            }

            if(isset($formData['distance']) && $formData['distance'] == 'empty') {
                $query->whereDoesntHave('distance');
            }

            if(isset($formData['salary']) &&  $formData['salary'] == 'yes') {
                $query->whereDoesntHave('user.salaries');
            }

            if(isset($formData['count']) && $formData['count'] == 'true') {
                $query = $query->count();
                return ['count' => $query];
            }

            if(isset($formData['consumer_type']) && $formData['consumer_type'] != '') {
                $query->whereHas('user.userMeta', fn($q) use ($formData) => 
                    $q->where('consumer_type', $formData['consumer_type']));
            }

            if(isset($formData['booking_type'])) {
                if($formData['booking_type'] == 'physical')
                    $query->where('customer_physical_type', 'yes');

                if($formData['booking_type'] == 'phone')
                    $query->where('customer_phone_type', 'yes');
            }

        } else {

            if($consumerType == 'RWS') {
                $query->where('job_type', 'rws');
            } else {
                $query->where('job_type', 'unpaid');
            }
            
            if(isset($formData['customer_email']) && $formData['customer_email'] != '') {
                $user = $this->userRepository->where('email', $formData['customer_email'])->first();
                if($user) {
                    $query->where('user_id', '=', $user->id);
                }
            }
        }

        if(isset($formData['id']) && $formData['id'] != '') {
            if(is_array($formData['id']))
                $query->whereIn('id', $formData['id']);
            else
                $query->where('id', $formData['id']);
            $formData = array_only($formData, ['id']);
        }

        if(isset($formData['feedback']) && $formData['feedback'] != 'false') {
            $query->where('ignore_feedback', '0');
            $query->whereHas('feedback', fn($q) => $q->where('rating', '<=', '3'));

            if(isset($formData['count']) && $formData['count'] != 'false') 
                return ['count' => $query->count()];
        }

        if(isset($formData['lang']) && $formData['lang'] != '') {
            $query->whereIn('from_language_id', $formData['lang']);
        }

        if(isset($formData['status']) && $formData['status'] != '') {
            $query->whereIn('status', $formData['status']);
        }

        if(isset($formData['filter_timetype'])) {
            $targetColumn = $formData['filter_timetype'] == 'created' ? 'created_at' : 'due;'

            if(isset($formData['from']) && $formData['from'] != "") {
                $query->where($targetColumn, '>=', $formData["from"]);
            }

            if(isset($formData['to']) && $formData['to'] != "") {
                $to = $formData["to"] . " 23:59:00";
                $query->where($targetColumn, '<=', $to);
            }

            $query->orderBy($targetColumn, 'desc');
        }

        if(isset($formData['job_type']) && $formData['job_type'] != '') {
            $query->whereIn('job_type', $formData['job_type']);
        }

        $query->orderBy('created_at', 'desc')
              ->with('user', 'language', 'feedback.user', 'translatorJobRel.user', 'distance');

        $query = $limit == 'all' ? $query->get() : $query->paginate(15);

        return $query;
    }

    /**
     * Create new booking
     * 
     * @param array $formData
     * @param int $immediateTime
     * @return mixed[]
     */
    public function storeBooking($formData = array(), $immediateTime = 5)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $consumerType = $user->userMeta->consumer_type;
            $customerPhysicalType = isset($formData['customer_physical_type']) ? 'yes' : 'no';

            isset($formData['customer_phone_type'])
                ? $formData['customer_phone_type'] = 'yes'
                : $formData['customer_phone_type'] = 'no';

            
            $formData['customer_physical_type'] = $customerPhysicalType;
            $formData['due'] = $formData['immediate'] == 'yes' 
                ? Carbon::now()->addMinute($immediateTime)->format('Y-m-d H:i:s')
                : Carbon::parse($formData['due_date'] . " " . $formData['due_time'])->format('Y-m-d H:i:s');;
            $response['customer_physical_type'] = $customerPhysicalType;
            $response['type'] = $['immediate'] == 'yes' ? 'immediate' : 'regular';
            
            if($formData['immediate'] == 'no' && Carbon::parse($formData['due'])->isPast()) {
                $response['status'] = 'fail';
                $response['message'] = "Can't create booking in past";
                return $response;
            }

            $formData['gender'] => in_array('male', $formData['job_for']) ? 'male' : 'female';
            $formData['certified'] => $this->getCertifiedValue($formData['job_for']);
            $formData['job_type'] = $this->getJobTypeValue($consumerType);
            $formData['b_created_at'] = Carbon::now()->format('Y-m-d H:i:s');
            $formData['will_expire_at'] = TeHelper::willExpireAt($formData['due'], $formData['b_created_at']);
            $formData['by_admin'] = isset($formData['by_admin']) ? $formData['by_admin'] : 'no';
            $formData['user_id'] = $user->id;

            $job = $this->jobRepository->create($formData);

            $response['status'] = 'success';
            $response['id'] = $job->id;

            DB::commit();
            
            return $response;
        } catch (\Exception $e) {
            Log::error($e);
            DB::rollback();

            return false;
        }
    }

    private function getCertifiedValue(array $jobFor)
    {
        if(in_array('normal', $jobFor)) {
            if(in_array('certified', $jobFor))
                return 'both';

            if(in_array('certified_in_law', $jobFor)) 
                return 'n_law';

            if(in_array('certified_in_helth', $jobFor)) {
                return 'n_health';
            }

            return 'normal';
        }

        if(in_array('certified', $jobFor)) {
            return 'yes';
        }

        if(in_array('certified_in_law', $jobFor)) {
            return 'law';
        }

        if(in_array('certified_in_helth', $jobFor)) {
            return 'health';
        }

        if(in_array('normal', $jobFor) && in_array('certified', $jobFor)) {
           return 'both';
        }
    }

    private function getJobTypeValue($consumerType)
    {
        if($consumerType == 'rwsconsumer')
            return 'rws';

        if($consumerType == 'ngo') 
            return 'unpaid';
        
        return 'paid';
    }

    /**
     * Updates a job
     * 
     * @param int $jobId
     * @param array $formData
     * @return mixed
     */
    public function updateJob($jobId, $formData)
    {
        try {
            $logData = [];
            $langChanged = false;
            $job = $this->jobRepository->find($jobId);

            $currentTranslator = $job->translatorJobRel->whereNull('cancel_at')->first();
            if(!($currentTranslator instanceof DTApi\Models\TranslatorJobRel))
                $currentTranslator = $job->translatorJobRel->where('completed_at', '!=', null)->first
            
            $changeTranslator = $this->changeTranslator($currentTranslator, $data, $job);

            if($changeTranslator['translatorChanged']) 
                $logData[] = $changeTranslator['log_data'];

            $changeDue = $this->changeDue($job->due, $data['due']);
            if($changeDue['dateChanged']) {
                $oldTime = $job->due;
                $job->due = $data['due'];
                $logData[] = $changeDue['log_data'];
            }

            if($job->from_language_id != $data['from_language_id']) {
                $logData[] = [
                    'old_lang' => TeHelper::fetchLanguageFromJobId($job->from_language_id),
                    'new_lang' => TeHelper::fetchLanguageFromJobId($data['from_language_id'])
                ];
                $oldLang = $job->from_language_id;
                $job->from_language_id = $data['from_language_id'];
                $langChanged = true;
            }

            $changeStatus = $this->changeStatus($job, $data, $changeTranslator['translatorChanged']);
            if($changeStatus['statusChanged'])
                $logData[] = $changeStatus['log_data'];

            $job->admin_comments = $data['admin_comments'];

            $this->logger->addInfo("USER #$user->id ($user->name) has been updated booking <a class='openjob' href='/admin/jobs/$id'>#$id</a> with data:  ", $logData);

            $job->reference = $data['reference'];
            $job->save();

            DB::commit();

            if($job->due <= Carbon::now())
                return ['Updated'];
            
            if($changeDue['dateChanged']) 
                $this->sendChangedDateNotification($job, $oldTime);

            if($changeTranslator['translatorChanged'])
                $this->sendChangedTranslatorNotification($job, $current_translator, $changeTranslator['new_translator']);

            if($langChanged)
                $this->sendChangedLangNotification($job, $old_lang);
        } catch (\Exception $e) {
            Log::error($e);
            DB::rollBack();

            return false;
        }
    }

    /**
     * @param $data
     * @return mixed
     */
    public function storeJobEmail($data)
    {
        DB::beginTransaction();

        try {
            $userType = $data['user_type'];
            $job = $this->jobRepository->findOrFail($data['user_email_job_id']);
            $job->user_email = $data['user_email'];
            $job->reference = isset($data['reference']) ? $data['reference'] : '';
            $user = $job->user->first();
            if(isset($data['address'])) {
                $job->address = $data['address'] != '' ? $data['address'] : $user->userMeta->address;
                $job->instructions = $data['instructions'] != '' ? $data['instructions'] : $user->userMeta->instructions;
                $job->town = $data['town'] != '' ? $data['town'] : $user->userMeta->city;
            }

            $job->save();
            
            $email = $job->user_email ?? $user->email;
            $subject = 'Vi har mottagit er tolkbokning. Bokningsnr: #' . $job->id;
            $sendData = [
                'user' => $user,
                'job'  => $job
            ];

            $this->mailer->send(
                $email, 
                $name, 
                $subject, 
                'emails.job-created', 
                $sendData
            );
            
            $data = new JobData($job)->attributes;
            JobWasCreated::dispatch($job, $data, '*')

            $response['type'] = $userType;
            $response['job'] = $job;
            $response['status'] = 'success';

            DB::commit();

            return $response;
        } catch (\Exception $e) {
            Log::error($e);
            DB::rollBack();

            return false;
        }

    }

    /**
     * @param DTApi\Models\TranslatorJobRel $currentTranslator
     * @param array $data
     * @param DTApi\Models\Job $job
     * 
     * @return array
     */
    private function changeTranslator($currentTranslator, $data, $job)
    {
        if(!$data['translator_email'] || !$data['translator'])
            return ['translatorChanged' => $translatorChanged];
        
        $logData = [];
        $data['translator'] = $this->userRepository
            ->where('email', $data['translator_email'])
            ->first()
            ->id;

        if($currentTranslator) {
            $newTranslator = $currentTranslator->toArray();
            $newTranslator['user_id'] = $data['translator'];
            unset($newTranslator['id']);
            $newTranslator = $this->translatorRepository->create($newTranslator);
            $currentTranslator->cancel_at = Carbon::now();
            $currentTranslator->save();
            $logData[] = [
                'old_translator' => $currentTranslator->user->email,
                'newTranslator' => $newTranslator->user->email
            ];
        } else {
            $newTranslator = $this->translatorRepository->create([
                'user_id' => $data['translator'], 
                'job_id' => $job->id
            ]);
            $logData[] = [
                'old_translator' => null,
                'newTranslator' => $newTranslator->user->email
            ];
        }
        
        return [
            'translatorChanged' => $translatorChanged, 
            'newTranslator' => $newTranslator, 
            'log_data' => $logData
        ];
    }

    /**
     * @param $oldDue
     * @param $newDue
     * @return array
     */
    private function changeDue($oldDue, $newDue)
    {
        $dateChanged = false;
        if($oldDue != $newDue) {
            $dateChanged = true;
            $logData = [
                'old_due' => $oldDue,
                'new_due' => $newDue
            ];

            return [
                'dateChanged' => $dateChanged, 
                'log_data' => $logData
            ];
        }

        return ['dateChanged' => $dateChanged];
    }

    /**
     * @param $job
     * @param $data
     * @param $changedTranslator
     * @return array
     */
    private function changeStatus($job, $data, $changedTranslator)
    {
        $oldStatus = $job->status;
        $statusChanged = false;
        if($oldStatus != $data['status']) {
            switch ($job->status) {
                case 'timedout':
                    $statusChanged = $this->changeTimedoutStatus($job, $data, $changedTranslator);
                    break;
                case 'completed':
                    $statusChanged = $this->changeCompletedStatus($job, $data);
                    break;
                case 'started':
                    $statusChanged = $this->changeStartedStatus($job, $data);
                    break;
                case 'pending':
                    $statusChanged = $this->changePendingStatus($job, $data, $changedTranslator);
                    break;
                case 'withdrawafter24':
                    $statusChanged = $this->changeWithdrawafter24Status($job, $data);
                    break;
                case 'assigned':
                    $statusChanged = $this->changeAssignedStatus($job, $data);
                    break;
                default:
                    $statusChanged = false;
                    break;
            }

            if($statusChanged) {
                $statusChanged = true;
                $logData = [
                    'old_status' => $oldStatus,
                    'new_status' => $data['status']
                ];
                return [
                    'statusChanged' => $statusChanged, 
                    'log_data' => $logData
                ];
            }
        }
    }

    /**
     * @param $job
     * @param $data
     * @param $changedTranslator
     * @return bool
     */
    private function changeTimedoutStatus($job, $data, $changedTranslator)
    {
        $old_status = $job->status;
        $job->status = $data['status'];
        $user = $job->user()->first();
        $email = $job->user_email ?? $user->email
        $name = $user->name;
        $dataEmail = [
            'user' => $user,
            'job'  => $job
        ];

        if($data['status'] == 'pending') {
            $job->created_at = date('Y-m-d H:i:s');
            $job->emailsent = 0;
            $job->emailsenttovirpal = 0;
            $job->save();
            $jobData = new JobData($job)->attributes;

            $subject = 'Vi har nu återöppnat er bokning av ' . TeHelper::fetchLanguageFromJobId($job->from_language_id) . 'tolk för bokning #' . $job->id;
            $this->mailer->send(
                $email, 
                $name, 
                $subject, 
                'emails.job-change-status-to-customer', 
                $dataEmail
            );

            $this->sendNotificationTranslator($job, $jobData, '*');

            return true;
        } elseif($changedTranslator) {
            $job->save();
            $subject = 'Bekräftelse - tolk har accepterat er bokning (bokning # ' . $job->id . ')';

            $this->mailer->send(
                $email, 
                $name, 
                $subject, 
                'emails.job-accepted', 
                $dataEmail
            );

            return true;
        }

        return false;
    }

    /**
     * @param $job
     * @param $data
     * @return bool
     */
    private function changeCompletedStatus($job, $data)
    {
        $job->status = $data['status'];
        if($data['status'] == 'timedout') {
            if($data['admin_comments'] == '') 
                return false;
            $job->admin_comments = $data['admin_comments'];
        }
        $job->save();
        return true;
    }

    /**
     * @param $job
     * @param $data
     * @return bool
     */
    private function changeStartedStatus($job, $data)
    {
        if($data['admin_comments'] == '' || data['sesion_time'] == '') 
            return false;

        $job->status = $data['status'];
        $job->admin_comments = $data['admin_comments'];
        $email = $job->user_email ?? $user->email

        if($data['status'] == 'completed') {
            $user = $job->user()->first();
            $interval = $data['sesion_time'];
            $diff = explode(':', $interval);
            $job->end_at = date('Y-m-d H:i:s');
            $job->session_time = $interval;
            $sessionTime = $diff[0] . ' tim ' . $diff[1] . ' min';
            $name = $user->name;
            $dataEmail = [
                'user'         => $user,
                'job'          => $job,
                'session_time' => $sessionTime,
                'for_text'     => 'faktura'
            ];

            $subject = 'Information om avslutad tolkning för bokningsnummer #' . $job->id;
            $this->mailer->send(
                $email, 
                $name, 
                $subject, 
                'emails.session-ended', 
                $dataEmail
            );

            $user = $job->translatorJobRel
                ->where('completed_at', null)
                ->where('cancel_at', null)
                ->first();

            $email = $user->user->email;
            $name = $user->user->name;
            $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;
            $dataEmail = [
                'user'         => $user,
                'job'          => $job,
                'session_time' => $session_time,
                'for_text'     => 'lön'
            ];
            $this->mailer->send(
                $email, 
                $name, 
                $subject, 
                'emails.session-ended', 
                $dataEmail
            );
        }
        $job->save();
        return true;
    }

    /**
     * @param $job
     * @param $data
     * @param $changedTranslator
     * @return bool
     */
    private function changePendingStatus($job, $data, $changedTranslator)
    {
        if($data['admin_comments'] == '' && $data['status'] == 'timedout')
            return false;

        $job->status = $data['status'];
        $job->admin_comments = $data['admin_comments'];
        $user = $job->user()->first();
        $email = $job->user_email ?? $user->email
        $name = $user->name;
        $dataEmail = [
            'user' => $user,
            'job'  => $job
        ];

        $job->save();

        if($data['status'] == 'assigned' && $changedTranslator) {
            $job_data = new JobData($job)->attributes;
            $subject = 'Bekräftelse - tolk har accepterat er bokning (bokning # ' . $job->id . ')';

            $this->mailer->send(
                $email, 
                $name, 
                $subject, 
                'emails.job-accepted', 
                $dataEmail
            );

            $translator = $this->jobRepository->getJobsAssignedTranslatorDetail($job);
            
            $this->mailer->send(
                $translator->email, 
                $translator->name, 
                $subject, 
                'emails.job-changed-translator-new-translator', 
                $dataEmail
            );

            $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);

            $this->sendSessionStartRemindNotification(
                $user, 
                $job, 
                $language, 
                $job->due, 
                $job->duration
            );

            $this->sendSessionStartRemindNotification(
                $translator, 
                $job, 
                $language, 
                $job->due, 
                $job->duration
            );
        } else {
            $subject = 'Avbokning av bokningsnr: #' . $job->id;

            $this->mailer->send(
                $email, 
                $name, 
                $subject, 
                'emails.status-changed-from-pending-or-assigned-customer',
                $dataEmail
            );
        }

        return true;
    }

    /**
     * @param $job
     * @param $data
     * @return bool
     */
    private function changeWithdrawafter24Status($job, $data)
    {
        if($data['admin_comments'] == '' || !in_array($data['status'], ['timedout'])) 
            return false;
        
        $job->status = $data['status'];
        $job->admin_comments = $data['admin_comments'];
        $job->save();
        return true;
    }

    /**
     * @param $job
     * @param $data
     * @return bool
     */
    private function changeAssignedStatus($job, $data)
    {
        if(!in_array($data['status'], [
                'withdrawbefore24', 
                'withdrawafter24', 
                'timedout'
            ]) || 
            ($data['admin_comments'] == '' && $data['status'] == 'timedout')
        )
            return false;

        $job->status = $data['status'];
        $job->admin_comments = $data['admin_comments'];

        if(in_array($data['status'], [
            'withdrawbefore24', 
            'withdrawafter24'
        ])) {
            $user = $job->user()->first();
            $email = $job->user_email ?? $user->email;
            $name = $user->name;
            $dataEmail = [
                'user' => $user,
                'job'  => $job
            ];

            $subject = 'Information om avslutad tolkning för bokningsnummer #' . $job->id;
            $this->mailer->send(
                $email, 
                $name, 
                $subject, 
                'emails.status-changed-from-pending-or-assigned-customer',
                $dataEmail
            );

            $user = $job->translatorJobRel->where('completed_at', null)->where('cancel_at', null)->first();

            $email = $user->user->email;
            $name = $user->user->name;
            $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;
            $dataEmail = [
                'user' => $user,
                'job'  => $job
            ];
            $this->mailer->send(
                $email, 
                $name, 
                $subject, 
                'emails.job-cancel-translator', 
                $dataEmail
            );
        }
        $job->save();

        return true;
    }

    /**
     * @param $data
     * @param $user
     */
    public function acceptJob($data, $user)
    {
        DB::beginTransaction();

        try {
            $adminEmail = config('app.admin_email');
            $adminSenderEmail = config('app.admin_sender_email');
            $job = $this->jobRepository->findOrFail($data['job_id']);

            if(!$this->jobRepository->isTranslatorAlreadyBooked(
                $jobId, 
                $user->id, $job->due
            )) {
                if(
                    $job->status == 'pending' &&
                    $this->jobRepository->insertTranslatorJobRel($user->id, $jobId)
                ) {
                    $job->status = 'assigned';
                    $job->save();
                    $user = $job->user->first();
                    $mailer = new AppMailer();

                    $email = $job->user_email ?? $user->email;
                    $name = $user->name;
                    $subject = "Bekräftelse - tolk har accepterat er bokning (bokning # $job->id)"; 

                    $data = [
                        'user' => $user,
                        'job'  => $job
                    ];
                    $mailer->send($email, $name, $subject, 'emails.job-accepted', $data);
                }
                
                $jobs = $this->getPotentialJobs($user);

                DB::commit();

                return array(
                    'status' => 'success',
                    'list' => collect(['jobs' => $jobs, 'job' => $job])->toJson()
                );
            }
            
            return array(
                'status' => 'fail',
                'message' => 'Du har redan en bokning den tiden! Bokningen är inte accepterad.';
            );
        } catch (\Exception $e) {
            Log::error($e);
            DB::rollBack();

            return false;
        }
    }


    /**
     * Function to get the potential jobs for paid,rws,unpaid translators
     * 
     * @param DTApi\Model\User $user
     * @return array
     */
    public function getPotentialJobs($user)
    {
        $userMeta = $user->userMeta;
        $jobType = 'unpaid';
        $translator_type = $userMeta->translator_type;
        if($translator_type == 'professional')
            $jobType = 'paid';   /*show all jobs for professionals.*/
        else if($translator_type == 'rwstranslator')
            $jobType = 'rws';  /* for rwstranslator only show rws jobs. */
        else if($translator_type == 'volunteer')
            $jobType = 'unpaid';  /* for volunteers only show unpaid jobs. */

        $languages = $this->userLanguageRepository->where('user_id', $user->id)->get();
        $userlanguage = collect($languages)->pluck('lang_id')->all();
        $gender = $userMeta->gender;
        $translator_level = $userMeta->translator_level;
        
        $jobIds = $this->jobRepository->getJobs(
            $user->id, 
            $jobType, 
            'pending', 
            $userlanguage, 
            $gender, 
            $translator_level
        );

        foreach ($jobIds as $k => $job) {
            $jobuserid = $job->user_id;
            $job->specific_job = $this->jobRepository->assignedToPaticularTranslator($user->id, $job->id);
            $job->check_particular_job = $this->jobRepository->checkParticularJob($user->id, $job);
            $checktown = $this->jobRepository->checkTowns($jobuserid, $user->id);

            if($job->specific_job == 'SpecificJob')
                if($job->check_particular_job == 'userCanNotAcceptJob')
                unset($jobIds[$k]);

            if(($job->customer_phone_type == 'no' || $job->customer_phone_type == '') && $job->customer_physical_type == 'yes' && $checktown == false) {
                unset($jobIds[$k]);
            }
        }
        
        return $jobIds;
    }

    /**
     * Function to accept the job with the job id
     * 
     * @param int $jobId
     * @param DTApi\Model\User $user
     */
    public function acceptJobWithId($jobId, $user)
    {
        DB::beginTransaction();

        try {
            $adminemail = config('app.admin_email');
            $adminSenderEmail = config('app.admin_sender_email');
            $job = $this->jobRepository->findOrFail($jobId);
            $response = array();

            if(!$this->jobRepository->isTranslatorAlreadyBooked($jobId, $user->id, $job->due)) {
                if($job->status == 'pending' && $this->jobRepository->insertTranslatorJobRel($user->id, $jobId)) {
                    $job->status = 'assigned';
                    $job->save();
                    $user = $job->user->first();
                    $mailer = new AppMailer();
                    
                    $email = $job->user_email ?? $user->email;
                    $name = $user->name;

                    $subject = 'Bekräftelse - tolk har accepterat er bokning (bokning # ' . $job->id . ')';
                    $data = [
                        'user' => $user,
                        'job'  => $job
                    ];
                    $mailer->send(
                        $email, 
                        $name, 
                        $subject, 
                        'emails.job-accepted', 
                        $data
                    );

                    $data = array();
                    $data['notification_type'] = 'job_accepted';
                    $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);
                    $msgText = array(
                        "en" => 'Din bokning för ' . $language . ' translators, ' . $job->duration . 'min, ' . $job->due . ' har accepterats av en tolk. Vänligen öppna appen för att se detaljer om tolken.'
                    );
                    if($this->notificationService->isNeedToSendPush($user->id)) {
                        $users_array = array($user);
                        $this->notificationService->sendPushNotificationToSpecificUsers(
                            $users_array, 
                            $jobId, 
                            $data, 
                            $msgText, 
                            $this->notificationService->isNeedToDelayPush($user->id)
                        );
                    }
                    // Your Booking is accepted sucessfully
                    $response['status'] = 'success';
                    $response['list']['job'] = $job;
                    $response['message'] = 'Du har nu accepterat och fått bokningen för ' . $language . 'tolk ' . $job->duration . 'min ' . $job->due;
                } else {
                    // Booking already accepted by someone else
                    $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);
                    $response['status'] = 'fail';
                    $response['message'] = 'Denna ' . $language . 'tolkning ' . $job->duration . 'min ' . $job->due . ' har redan accepterats av annan tolk. Du har inte fått denna tolkning';
                }
            } else {
                // You already have a booking the time
                $response['status'] = 'fail';
                $response['message'] = 'Du har redan en bokning den tiden ' . $job->due . '. Du har inte fått denna tolkning';
            }

            DB::commit();
            
            return $response;
        } catch (\Exception $e) {
            Log::error($e);
            DB::rollBack();

            return false;
        }
    }
    
}
