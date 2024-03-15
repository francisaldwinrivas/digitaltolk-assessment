<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Http\Requests\API\BookingStoreRequest;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{
    /**
     * @var BookingService
     */
    protected $bookingService;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $response = $this->bookingService->getUserJobs($request, auth()->user());

        return reponse()->json($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->bookingService->getJob($id);

        return reponse()->json($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(BookingStoreRequest $request)
    {
        $response = $this->bookingService->store($request->all());

        if(!$response)
            return response()->json(
                'status' => 'error',
                'message' => 'An error occurred. Failed to save booking.'
            );

        return response()->json($response);

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->except('_token', 'submit');
        $response = $this->bookingService->updateJob($id, array_except($data, ['_token', 'submit']));

        if(!$response)
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred. Failed to update booking.'
            ]);

        return response()->($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $data = $request->all();
        $response = $this->bookingService->storeJobEmail($data);

        if(!$response)
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred. Transaction failed.'
            ]);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();

        $response = $this->bookingService->acceptJob($data);

        if(!$response)
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred. Transaction failed.'
            ]);

        return response()->json($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $data = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($data, $user);

        if(!$response)
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred. Transaction failed.'
            ]);

        return response()->json($response);
    }

    /**
     * IMPORTANT!
     * I DID NOT PROCEED ON REFACTORING THE
     * CODE FROM THIS POINT ONWARDS
     */

     // Other methods ...
}
