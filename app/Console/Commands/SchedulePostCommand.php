<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use \GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Pool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

class SchedulePostCommand extends Command
{
    protected $signature = 'app:schedule-post-command';
    protected $description = 'Command description';
    public function handle()
    {
        $sep_time = 10;
        $API = 'https://notify-api.line.me/api/notify';
        $now = Carbon::now();



        // 10分単位で切り捨て(15分→10分)
        $date_down = $now->subMinutes($now->minute % $sep_time);
        $date_down = date('Y-m-d H:i', strtotime($date_down));


        try {

            // 配信対象メッセージ抽出
            $messages = DB::table('schedules')
            ->where('plan_at', $date_down)
            ->whereNull('schedules.deleted_at')
            ->join('messages','schedules.message_id','=','messages.id')
            ->leftjoin('images','messages.id','=','images.message_id')
            ->select(
                'messages.store_id as store_id',
                'messages.id as message_id',
                'messages.title as title',
                'messages.content as content',
                'images.save_name as save_name',
                )
            ->get();

            if ($messages->count() == 0){
                // Log::info($date_down.' スケジュール配信 0件終了');
                return;
            }

            // 非同期リクエスト用パラメータリスト作成
            $requests_param = [];

            foreach($messages as $msg)
            {
                $lines = DB::table('lines')
                ->select('id','token', 'user_name')
                ->whereNull('deleted_at')
                ->where('is_valid', true)
                ->where('store_id', $msg->store_id
                )->get();

                $start_time = Carbon::now();

                if ($lines->count() == 0)
                {
                    DB::table('histories')
                    ->insert(
                        [
                            'store_id' => $msg->store_id,
                            'title'=> $msg->title,
                            'content'=> $msg->content,
                            'status'=> '対象0件',
                            'start_at'=> $start_time,
                            'img_url' => $msg->save_name == Null ? Null: Storage::disk('owner')->url($msg->save_name),
                            'err_info' => '―',
                            'created_at'=> $start_time,
                            'updated_at'=> $start_time
                        ]
                    );
                    continue;
                }

                $history_id = DB::table('histories')
                ->insertGetId(
                    [
                        'store_id' => $msg->store_id,
                        'title'=> $msg->title,
                        'content'=> $msg->content,
                        'status'=> '配信中',
                        'start_at'=> $now,
                        'img_url' => $msg->save_name == Null ? Null: Storage::disk('owner')->url($msg->save_name),
                        'created_at'=> $now
                    ]
                );
                


                foreach($lines as $line)
                {  
                    if($msg->save_name == null)
                    {
                        array_push($requests_param,
                        [
                            // 非同期リクエストの結果を特定するためのキー
                            'key' => $history_id. '_' . $msg->message_id . '_' . $line->id,
                            'history_id' => $history_id,
                            'user_name' => $line->user_name,
                            'params' =>  [
                                'headers' => ['Authorization'=> 'Bearer '.$line->token, ],
                                'http_errors' => false,
                                'multipart' => [
                                    ['name' => 'message','contents' => PHP_EOL . $msg->content]
                                ]
                            ]
                        ]);
                    } else {
                        array_push($requests_param,
                        [
                            'key' => $history_id. '_' . $msg->message_id . '_' . $line->id,
                            'history_id' => $history_id,
                            'user_name' => $line->user_name,
                            'params' =>  [
                                'headers' => ['Authorization'=> 'Bearer '.$line->token, ],
                                'http_errors' => false,
                                'multipart' => [
                                    ['name' => 'message','contents' => PHP_EOL . $msg->content],
                                    ['name' => 'imageFile','contents' => Psr7\Utils::tryFopen(Storage::disk('owner')->url($msg->save_name), 'r')]
                                ]
                            ]
                        ]);
                    }
                }
            }
            ini_set("max_execution_time",0);



            // $contents = [];
            // $pool = new Pool($client, $requests($requests_param), [
            //     'concurrency' => 50,
            //     'fulfilled' => function ($response, $index) use ($requests_param, &$contents) {
            //         $contents[$requests_param[$index]['key']] = [
            //         'html'             => $response->getBody()->getContents(),
            //         'status_code'      => $response->getStatusCode(),
            //         'response_header'  => $response->getHeaders()
            //         ];

            //         $contents[$requests_param[$index]['key']]['history_id'] = $requests_param[$index]['history_id'];
            //         $contents[$requests_param[$index]['key']]['user_name'] = $requests_param[$index]['user_name'];
            //     },
            //     'rejected' => function ($reason, $index) use ($requests_param, &$contents) {
            //         $contents[$requests_param[$index]['key']] = [
            //         'html'   => '',
            //         'reason' => $reason
            //         ];
            //         $contents[$requests_param[$index]['key']]['history_id'] = $requests_param[$index]['history_id'];
            //         $contents[$requests_param[$index]['key']]['user_name'] = $requests_param[$index]['user_name'];
            //     },
            // ]);

            $client = new Client();

            $log = new Logger('async_requests');
            $log->pushHandler(new RotatingFileHandler(public_path('logs/async_requests.log'), 7,Level::Error ));
        

            $requests = function ($requests_param) use ($client, $API) {
                foreach ($requests_param as $param) {
                    yield function() use ($client, $API, $param) {
                        return $client->requestAsync('POST', $API, $param['params']);
                    };
                }
            };

            $contents = [];
            $pool = new Pool($client, $requests($requests_param), [
                // 'concurrency' => 50,
                'concurrency' => 15,
                'fulfilled' => function ($response, $index) use ($requests_param, &$contents, $log) {
                    try {
                        if (!isset($requests_param[$index]['history_id'], $requests_param[$index]['key'])) {
                            $log->error("Response processing failed: Missing history_id or key at index $index");
                            return;
                        }

                        $contents[$requests_param[$index]['key']] = [
                            'html'             => $response->getBody()->getContents(),
                            'status_code'      => $response->getStatusCode(),
                            'response_header'  => $response->getHeaders()
                        ];
                        $contents[$requests_param[$index]['key']]['history_id'] = $requests_param[$index]['history_id'];
                        $contents[$requests_param[$index]['key']]['user_name'] = $requests_param[$index]['user_name'];

                        // // fulfilled のレスポンス確認
                        // if ($response->getStatusCode() != 200) {
                        //     $log->error("📬 配信結果: [history_id: {$requests_param[$index]['history_id']}] [user: {$requests_param[$index]['user_name']}] [status_code: {$response->getStatusCode()}]");
                        // }                        
                        $log->error("配信結果: [history_id: {$requests_param[$index]['history_id']}] [user: {$requests_param[$index]['user_name']}] [status_code: {$response->getStatusCode()}]");
 
                        
                    } catch (\Exception $e) {
                        $log->error("Response processing {$requests_param[$index]['history_id']}: " . $e->getMessage());
                    }
                },
                'rejected' => function ($reason, $index) use ($requests_param, &$contents,  $log) {
                    if (!isset($requests_param[$index]['history_id'], $requests_param[$index]['key'])) {
                        $log->error("Request failed: Missing history_id or key at index $index");
                        return;
                    }
                    $errorMessage = is_object($reason) && method_exists($reason, 'getMessage') ? $reason->getMessage():json_encode($reason);
                    $log->error("rejectedエラーhistory_id {$requests_param[$index]['history_id']}: " . $errorMessage);
        
                    $contents[$requests_param[$index]['key']] = [
                        'html'   => '',
                        'reason' => $reason
                    ];
                    $contents[$requests_param[$index]['key']]['history_id'] = $requests_param[$index]['history_id'];
                    $contents[$requests_param[$index]['key']]['user_name'] = $requests_param[$index]['user_name'];


                    // 4. rejected のエラー確認
                    if (isset($requests_param[$index]['history_id'], $requests_param[$index]['user_name'])) {
                        $historyId = $requests_param[$index]['history_id'];
                        $userName = $requests_param[$index]['user_name'];
                    } else {
                        $historyId = 'Unknown';
                        $userName = 'Unknown';
                    }
                    $statusCode = is_object($reason) && method_exists($reason, 'getCode') ? $reason->getCode() : 'N/A';
                    $errorMessage = is_object($reason) && method_exists($reason, 'getMessage') ? $reason->getMessage() : json_encode($reason, JSON_UNESCAPED_UNICODE);
                    $log->error("配信失敗: [history_id: {$historyId}] [user: {$userName}] [status_code: {$statusCode}] [error: {$errorMessage}]");
                }
            ]);

            $promise = $pool->promise();
            $promise->wait();

            // history_idでグルーピング
            function group_by(array $table, string $key): array
            {
                $groups = [];
                foreach ($table as $row) {
                    $groups[$row[$key]][] = $row;
                }
                return $groups;
            }

            // $contents のエラー内容を確認
            foreach ($contents as $key => $value) {
                if ($value['status_code'] != 200) {
                    $log->error("配信エラー: history_id={$value['history_id']}, user={$value['user_name']}, status_code={$value['status_code']}");
                    $log->error("レスポンス詳細: " . json_encode($value['html'], JSON_UNESCAPED_UNICODE));
                }
            }
            

            $history_group = group_by($contents, 'history_id');

        
            $end_time = Carbon::now();

            // historyテーブルの更新
            foreach ($history_group as $key => $value)
            {
                $history = DB::table('histories')->where('id', $key)->first();
                if (!$history) {
                    $log->error("histories.id = {$key} not found. Skipping update.");
                    continue;
                }

                $result = 'OK';
                $err = 'ー';
                
                $res = array_map(function ($col) use ($log){
                    $json = json_decode($col['html']);

                    if (!$json || !isset($json->status) || !isset($json->message)) {
                        $log->error("JSONデコードエラー: history_id={$col['history_id']}, user={$col['user_name']}, data=" . json_encode($col['html']));
                        return "[{$col['user_name']}] JSONデコードエラー";
                    }
                    return '['.$col['user_name'].']'.$json->status.'::'.$json->message;
                }, array_filter($value, function ($col) {
                    return $col['status_code'] != '200';
                }));

                if ($res)
                {
                    $result = 'NG';
                    $err = join('/', $res);
                }

                try {
                    $affectedRows = DB::table('histories')->where('id', $key)
                        ->update([
                            'status' => $result,
                            'end_at' => $end_time,
                            'err_info' => $err,
                            'updated_at' => $end_time
                        ]);
            
                    if ($affectedRows === 0) {

                        // 6. $affectedRows === 0 の場合のデバッグ
                        $historyCheck = DB::table('histories')->where('id', $key)->first();
                        if (!$historyCheck) {
                            $log->error("Histories id {$key} does not exist in the database before update.");
                        } else {
                            $log->error("Histories id {$key} exists but update did not affect any rows. Current status: " . $historyCheck->status);
                        }
                    }

                } catch (\Exception $e) {
                    $log->error("Failed to update histories.id = {$key}: " . $e->getMessage());
                }
            }
        }
        catch (\Exception $e) {
            Log::error('エラー機能:スケジュール配信 【配信時間:'.$date_down.'】');
            Log::error('エラー箇所:'.$e->getFile().'【'.$e->getLine().'行目】');
            Log::error('エラー内容:'.$e->getMessage());
        }
    }
}
