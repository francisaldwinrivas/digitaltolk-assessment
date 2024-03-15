<?php

namespace DTApi\Services;

use DTApi\Repository\UserRepository;
use DTApi\Mailers\MailerInterface;
use Request;

class UserService
{
    protected $userRepository,
              $mailterInterface;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }
    
    public function find($id)
    {
        return $this->userRepository->find($id);
    }

    /**
     * @param int $id
     * @param Request $request
     * 
     * @return array
     */
    public function getUsersJobsHistory(int $id, Request $data)
    {
        $pagenum = $request->get('page', 1);
        $user = $this->userRepository->find($id);
        $emergencyJobs = array();
        $normalJobs = array();

        if ($user->isCustomer()) {
            $jobs = $user->jobs()
                ->with(
                    'user.userMeta', 
                    'user.average', 
                    'translatorJobRel.user.average', 
                    'language', 
                    'feedback', 
                    'distance'
                )->whereIn('status', [
                    'completed', 
                    'withdrawbefore24', 
                    'withdrawafter24', 
                    'timedout'
                ])
                ->orderBy('due', 'desc')
                ->paginate(15);

            $userType = 'customer';

            return [
                'emergencyJobs' => $emergencyJobs, 
                'normalJobs' => $normalJobs, 
                'jobs' => $jobs, 
                'cuser' => $user, 
                'usertype' => 'customer', 
                'numpages' => 0, 
                'pagenum' => 0
            ];
        } elseif ($user->isTranslator()) {
            $jobIds = $this->jobRepository->getTranslatorJobsHistoric($user->id, 'historic', $pagenum);
            $totaljobs = $jobIds->total();
            $numpages = ceil($totaljobs / 15);

            $userType = 'translator';
            
            return [
                'emergencyJobs' => $emergencyJobs, 
                'normalJobs' => $jobIds, 
                'jobs' => $jobIds, 
                'cuser' => $user, 
                'usertype' => 'translator', 
                'numpages' => $numpages, 
                'pagenum' => $pagenum
            ];
        }
    }


    
}
