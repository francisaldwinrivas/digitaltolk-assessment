<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\UserService;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{
    /**
     * @var UserService
     */
    protected $userService;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    // ... other codes here

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(int $userId, Request $request)
    {
        $response = $this->userService->getUsersJobsHistory($userId, $request);
        return response($response);
    }

}
