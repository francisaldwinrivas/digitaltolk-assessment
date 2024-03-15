<?php

namespace DTApi\Services;

use DTApi\Services\JobService;
use DTApi\Mailers\MailerInterface;

class BookingService
{
    protected $jobService,
              $mailterInterface;

    public function __construct(
        JobService $jobService,
        MailterInterface $mailterInterface
    ){
        $this->jobService = $jobService;
        $this->mailterInterface = $mailterInterface;
    }

    /**
     * Retrieves user jobs
     * @param $request
     * @param $user
     * 
     * @return array
     */
    public function getUserJobs(Request $request, $user)
    {
        return $user->isAdmin() || $user->isSuperAdmin() 
            ? $this->jobService->getAll($request);
            : $this->bookingRepository->getUsersJobs($request->get('user_id'));
    }

    public function getJob($id)
    {
        return $this->jobService->getJob($id)
    }

    public function store(array $data)
    {
        return $this->jobService->storeBooking($data);
    }

    public function update($jobId, $formData)
    {
        return $this->jobService->updateJob($jobId, $formData);
    }

    public function storeJobEmail($data)
    {
        return $this->jobService->storeJobEmail($data);
    }

    public function acceptJob($data)
    {
        return $this->jobService->acceptJob($data, auth()->user());
    }

    public function acceptJobWithId($jobId, $data)
    {
        return $this->jobService->acceptJobWithId($data, auth()->user());
    }


    
}
