<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sms\Services\SmsDispatcher;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendBulkRequest;
use App\Http\Requests\SendSmsRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SendController extends Controller
{
    public function __construct(protected SmsDispatcher $dispatcher) {}

    /**
     * @OA\Post(
     *     path="/send-sms", summary="Send one SMS", tags={"SMS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"to","message"},
     *         @OA\Property(property="to",       type="string", example="+4512345678"),
     *         @OA\Property(property="message",  type="string", example="Hello {{name}}"),
     *         @OA\Property(property="variables", type="object"),
     *         @OA\Property(property="from",      type="string"),
     *         @OA\Property(property="callback_url", type="string", format="url")
     *     )),
     *     @OA\Response(response=202, description="Accepted")
     * )
     */
    public function single(SendSmsRequest $request)
    {
        $team = app('current_team');
        $msg  = $this->dispatcher->dispatch(
            team:    $team,
            payload: $request->validated(),
            userId:  $request->user()->id,
        );

        return response()->json([
            'id'             => $msg->id,
            'status'         => $msg->status,
            'estimated_cost' => number_format($msg->cost_ore / 100, 2),
            'currency'       => config('sms.pricing.currency'),
        ], 202);
    }

    /**
     * @OA\Post(
     *     path="/send-bulk", summary="Send a batch of SMS", tags={"SMS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"messages"},
     *         @OA\Property(property="messages", type="array",
     *             @OA\Items(type="object",
     *                 @OA\Property(property="to", type="string"),
     *                 @OA\Property(property="message", type="string"),
     *             )),
     *         @OA\Property(property="batch_label", type="string")
     *     )),
     *     @OA\Response(response=202, description="Accepted")
     * )
     */
    public function bulk(SendBulkRequest $request)
    {
        $team    = app('current_team');
        $batchId = (string) Str::ulid();
        $items   = [];

        foreach ($request->validated()['messages'] as $row) {
            $row['batch_id'] = $batchId;
            $msg = $this->dispatcher->dispatch($team, $row, $request->user()->id);
            $items[] = ['id' => $msg->id, 'to' => $msg->to, 'status' => $msg->status];
        }

        return response()->json([
            'batch_id' => $batchId,
            'count'    => count($items),
            'messages' => $items,
        ], 202);
    }
}
