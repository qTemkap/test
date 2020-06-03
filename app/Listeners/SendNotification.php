<?php

namespace App\Listeners;

use App\us_Contacts;
use App\Users_us;
use App\Flat;
use GuzzleHttp\Client;
use App\SPR_Notification_templates;
use GuzzleHttp\Exception\GuzzleException;
use App\Events\SendNotificationBitrix;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected $data = [];
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  SendNotificationBitrix $event
     * @return void
     * @throws GuzzleException
     */
    public function handle(SendNotificationBitrix $event)
    {
        if (!$event->credentials) return;

        $text = SPR_Notification_templates::GetNotificationForType($event->data['type']);
        $who_change = $event->user;

            switch ($event->data['type']) {
                case 'change_of_responsibility_global':
                    $status = SPR_Notification_templates::getStatus($event->data['type']);

                    if($status == 1) {
                        $user_old = Users_us::find($event->data['user_id_old']);
                        $user_new = Users_us::find($event->data['user_id_new']);
                        $text = str_replace('{{ResponsibleNameOld}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $user_old['bitrix_id'] . "/' target='_blank' class='name blue'>" . $user_old['name'] . " " . $user_old['last_name'] . "</a>", $text);
                        $text = str_replace('{{ResponsibleNameNew}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $user_new['bitrix_id'] . "/' target='_blank' class='name blue'>" . $user_new['name'] . " " . $user_new['last_name'] . "</a>", $text);
                        $text = str_replace('{{Type}}', $event->data['type_obj'], $text);
                        $text = str_replace('{{count}}', $event->data['count'], $text);
                        $text = str_replace('{{Change}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $who_change['bitrix_id'] . "/' target='_blank' class='name blue'>" . $who_change['name'] . " " . $who_change['last_name'] . "</a>", $text);

                    $client = new Client();
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/im.notify', [
                        'query' => [
                            "message" => $text,
                            'to' => $user_new['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);

                    $client = new Client();
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/im.notify', [
                        'query' => [
                            "message" => $text,
                            'to' => $user_old['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }
                break;
            case 'change_of_responsibility_new':
                $status = SPR_Notification_templates::getStatus($event->data['type']);

                    if($status == 1) {
                        $user_old = Users_us::find($event->data['user_id_old']);
                        $user_new = Users_us::find($event->data['user_id_new']);
                        $text = str_replace('{{ResponsibleName}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $user_old['bitrix_id'] . "/' target='_blank' class='name blue'>" . $user_old['name'] . " " . $user_old['last_name'] . "</a>", $text);
                        $text = str_replace('{{ID}}', " <a href='" . $event->data['url'] . "' target='_blank'>" . $event->data['obj_id'] . " " . $event->data['address'] . "</a>", $text);
                        $text = str_replace('{{Change}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $who_change['bitrix_id'] . "/' target='_blank' class='name blue'>" . $who_change['name'] . " " . $who_change['last_name'] . "</a>", $text);

                    $client = new Client();
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/im.notify', [
                        'query' => [
                            "message" => $text,
                            'to' => $user_new['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }
                break;
            case 'change_of_responsibility_old':
                $status = SPR_Notification_templates::getStatus($event->data['type']);

                    if($status == 1) {
                        $user_old = Users_us::find($event->data['user_id_old']);
                        $user_new = Users_us::find($event->data['user_id_new']);
                        $text = str_replace('{{ResponsibleName}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $user_new['bitrix_id'] . "/' target='_blank' class='name blue'>" . $user_new['name'] . " " . $user_new['last_name'] . "</a>", $text);
                        $text = str_replace('{{ID}}', " <a href='" . $event->data['url'] . "' target='_blank'>" . $event->data['obj_id'] . " " . $event->data['address'] . "</a>", $text);
                        $text = str_replace('{{Change}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $who_change['bitrix_id'] . "/' target='_blank' class='name blue'>" . $who_change['name'] . " " . $who_change['last_name'] . "</a>", $text);

                    $client = new Client();
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/im.notify', [
                        'query' => [
                            "message" => $text,
                            'to' => $user_old['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }
                break;
            case 'who_change_responsibility':
                $status = SPR_Notification_templates::getStatus($event->data['type']);

                    if($status == 1) {
                        $user_old = Users_us::find($event->data['user_id_old']);
                        $user_new = Users_us::find($event->data['user_id_new']);
                        $text = str_replace('{{ResponsibleNameOld}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $user_old['bitrix_id'] . "/' target='_blank' class='name blue'>" . $user_old['name'] . " " . $user_old['last_name'] . "</a>", $text);
                        $text = str_replace('{{ResponsibleNameNew}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $user_new['bitrix_id'] . "/' target='_blank' class='name blue'>" . $user_new['name'] . " " . $user_new['last_name'] . "</a>", $text);
                        $text = str_replace('{{ID}}', " <a href='" . $event->data['url'] . "' target='_blank'>" . $event->data['obj_id'] . " " . $event->data['address'] . "</a>", $text);
                        $text = str_replace('{{Change}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $who_change['bitrix_id'] . "/' target='_blank' class='name blue'>" . $who_change['name'] . " " . $who_change['last_name'] . "</a>", $text);

                    $client = new Client();
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/im.notify', [
                        'query' => [
                            "message" => $text,
                            'to' => $who_change['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }
                break;
            case 'set_responsibility':
                $status = SPR_Notification_templates::getStatus($event->data['type']);

                    if($status == 1) {
                        $user = Users_us::find($event->data['user_id']);
                        $text = str_replace('{{ID}}', " <a href='" . $event->data['url'] . "' target='_blank'>" . $event->data['obj_id'] . " " . $event->data['address'] . "</a>", $text);
                        $text = str_replace('{{Change}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $who_change['bitrix_id'] . "/' target='_blank' class='name blue'>" . $who_change['name'] . " " . $who_change['last_name'] . "</a>", $text);

                    $client = new Client();
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/im.notify', [
                        'query' => [
                            "message" => $text,
                            'to' => $user['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }
                break;
            case 'who_set_responsibility':
                $status = SPR_Notification_templates::getStatus($event->data['type']);

                    if($status == 1) {
                        $user = Users_us::find($event->data['user_id']);
                        $text = str_replace('{{ResponsibleName}}', "<a href='".env('BITRIX_DOMAIN')."/company/personal/user/".$user['bitrix_id']."/' target='_blank' class='name blue'>".$user['name']." ".$user['last_name']."</a>", $text);
                        $text = str_replace('{{ID}}', " <a href='".$event->data['url']."' target='_blank'>".$event->data['obj_id']." ".$event->data['address']."</a>", $text);
                        $text = str_replace('{{Change}}', "<a href='".env('BITRIX_DOMAIN')."/company/personal/user/".$who_change['bitrix_id']."/' target='_blank' class='name blue'>".$who_change['name']." ".$who_change['last_name']."</a>", $text);

                    $client = new Client();
                    $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/im.notify',[
                        'query' => [
                            "message" => $text,
                            'to' => $who_change['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }

                    break;
                case 'change_price':
                    $status = SPR_Notification_templates::getStatus($event->data['type']);

                    if($status == 1) {
                        $user = Users_us::find($event->data['user_id']);

                        $text_new_price = $event->data['new_price'];
                        $text_old_price = $event->data['old_price'];

                        $text = str_replace('{{ID}}', " <a href='".$event->data['url']."' target='_blank'>".$event->data['obj_id']." ".$event->data['address']."</a>", $text);
                        $text = str_replace('{{Old_price}}', $text_old_price, $text);
                        $text = str_replace('{{type_price}}', $event->data['type_price'], $text);
                        $text = str_replace('{{New_price}}', $text_new_price, $text);
                        $text = str_replace('{{Change}}', "<a href='".env('BITRIX_DOMAIN')."/company/personal/user/".$who_change['bitrix_id']."/' target='_blank' class='name blue'>".$who_change['name']." ".$who_change['last_name']."</a>", $text);

                    $client = new Client();
                    $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/im.notify',[
                        'query' => [
                            "message" => $text,
                            'to' => $user['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }

                    $text = SPR_Notification_templates::GetNotificationForType('who_'.$event->data['type']);

                    $status_who = SPR_Notification_templates::getStatus('who_'.$event->data['type']);

                    if($status_who == 1) {
                        $text = str_replace('{{ID}}', " <a href='".$event->data['url']."' target='_blank'>".$event->data['obj_id']." ".$event->data['address']."</a>", $text);
                        $text = str_replace('{{Old_price}}', $text_old_price, $text);
                        $text = str_replace('{{type_price}}', $event->data['type_price'], $text);
                        $text = str_replace('{{New_price}}', $text_new_price, $text);
                        $text = str_replace('{{ResponsibleName}}', "<a href='".env('BITRIX_DOMAIN')."/company/personal/user/".$user['bitrix_id']."/' target='_blank' class='name blue'>".$user['name']." ".$user['last_name']."</a>", $text);

                    $client = new Client();
                    $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/im.notify',[
                        'query' => [
                            "message" => $text,
                            'to' => $who_change['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }

                    break;
                case 'internal_comment':
                    $status = SPR_Notification_templates::getStatus($event->data['type']);

                    if($status == 1) {
                        $user = Users_us::find($event->data['user_id']);

                        $text = str_replace('{{ID}}', " <a href='" . $event->data['url'] . "' target='_blank'>" . $event->data['obj_id'] . " " . $event->data['address'] . "</a>", $text);
                        $text = str_replace('{{Change}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $who_change['bitrix_id'] . "/' target='_blank' class='name blue'>" . $who_change['name'] . " " . $who_change['last_name'] . "</a>", $text);
                        $text = str_replace('{{type_h}}', $event->data['type_h'], $text);
                        $text = str_replace('{{type_comment}}', $event->data['type_comment'], $text);

                    $client = new Client();
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/im.notify', [
                        'query' => [
                            "message" => $text,
                            'to' => $user['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }
                break;
            case 'general_comment':
                $status = SPR_Notification_templates::getStatus($event->data['type']);

                    if($status == 1) {
                        $user = Users_us::find($event->data['user_id']);

                        $text = str_replace('{{ID}}', " <a href='" . $event->data['url'] . "' target='_blank'>" . $event->data['obj_id'] . " " . $event->data['address'] . "</a>", $text);
                        $text = str_replace('{{Change}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $who_change['bitrix_id'] . "/' target='_blank' class='name blue'>" . $who_change['name'] . " " . $who_change['last_name'] . "</a>", $text);
                        $text = str_replace('{{type_h}}', $event->data['type_h'], $text);
                        $text = str_replace('{{type_comment}}', $event->data['type_comment'], $text);

                    $client = new Client();
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/im.notify', [
                        'query' => [
                            "message" => $text,
                            'to' => $user['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }

                    $text = SPR_Notification_templates::GetNotificationForType('who_'.$event->data['type']);

                    $status_who = SPR_Notification_templates::getStatus('who_'.$event->data['type']);

                    if($status_who == 1) {

                        $text = str_replace('{{ID}}', " <a href='" . $event->data['url'] . "' target='_blank'>" . $event->data['obj_id'] . " " . $event->data['address'] . "</a>", $text);
                        $text = str_replace('{{ResponsibleName}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $user['bitrix_id'] . "/' target='_blank' class='name blue'>" . $user['name'] . " " . $user['last_name'] . "</a>", $text);
                        $text = str_replace('{{type_h}}', $event->data['type_h'], $text);
                        $text = str_replace('{{type_comment}}', $event->data['type_comment'], $text);

                    $client = new Client();
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/im.notify', [
                        'query' => [
                            "message" => $text,
                            'to' => $who_change['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }

                    break;
                case 'web_show':
                    $status = SPR_Notification_templates::getStatus($event->data['type']);

                    if($status == 1) {
                        $text = str_replace('{{LINK}}', $event->data['link'], $text);

                    $client = new Client();
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/im.notify', [
                        'query' => [
                            "message" => $text,
                            'to' => $who_change['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }

                    break;
                case 'change_client':
                    $status = SPR_Notification_templates::getStatus($event->data['type']);

                    if($status == 1) {
                        $user = Users_us::find($event->data['user_id']);

                        $text = str_replace('{{ID}}', " <a href='" . $event->data['url'] . "' target='_blank'>" . $event->data['obj_id'] . " " . $event->data['address'] . "</a>", $text);
                        $text = str_replace('{{Change}}', "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $who_change['bitrix_id'] . "/' target='_blank' class='name blue'>" . $who_change['name'] . " " . $who_change['last_name'] . "</a>[br]", $text);

                        $old_list_clients = "";

                        if(isset($event->data['old']['main'])) {
                            $old = collect(json_decode($event->data['old']['main']))->toArray();
                            $old_list_clients .= "Основной контакт: [br]";
                            foreach ($old as $client_item) {
                                $client = us_Contacts::find($client_item);
                                if (!is_null($client)) {
                                    $phone = json_decode($client->phone);
                                    $phone_num = "";
                                    if (!is_null($phone) && !empty($phone)) {
                                        $phone_num = $phone->number;
                                    }
                                    $old_list_clients .= $client->fullName() . " " . $phone_num . "[br]";
                                }
                            }
                        }

                        if(isset($event->data['old']['multi'])) {
                            $old = collect(json_decode($event->data['old']['multi']))->toArray();
                            $old_list_clients .= "Дополнительные контакты: [br]";
                            foreach ($old as $client_item) {
                                $client = us_Contacts::find($client_item);
                                if (!is_null($client)) {
                                    $phone = json_decode($client->phone);
                                    $phone_num = "";
                                    if (!is_null($phone) && !empty($phone)) {
                                        $phone_num = $phone->number;
                                    }
                                    $old_list_clients .= $client->fullName() . " " . $phone_num . "[br]";
                                }
                            }
                        }

                    $new_list_clients = "";
                    if(isset($event->data['new']['main'])) {
                        $new = collect(json_decode($event->data['new']['main']))->toArray();
                        $new_list_clients .= "Основной контакт: [br]";
                        foreach ($new as $client_item) {
                            $client = us_Contacts::find($client_item);
                            if(!is_null($client)) {
                                $phone = json_decode($client->phone);
                                $phone_num = "";
                                if(!is_null($phone) && !empty($phone)) {
                                    $phone_num = $phone->number;
                                }
                                $new_list_clients.=$client->fullName()." ".$phone_num."[br]";
                            }
                        }
                    }

                    if(isset($event->data['new']['multi'])) {
                        $new = collect(json_decode($event->data['new']['multi']))->toArray();
                        $new_list_clients .= "Дополнительные контакты: [br]";
                        foreach ($new as $client_item) {
                            $client = us_Contacts::find($client_item);
                            if(!is_null($client)) {
                                $phone = json_decode($client->phone);
                                $phone_num = "";
                                if(!is_null($phone) && !empty($phone)) {
                                    $phone_num = $phone->number;
                                }
                                $new_list_clients.=$client->fullName()." ".$phone_num."[br]";
                            }
                        }
                    }

                        $text = str_replace('{{clients_old}}', $old_list_clients, $text);
                        $text = str_replace('{{clients_new}}', $new_list_clients, $text);

                    $client = new Client();
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/im.notify', [
                        'query' => [
                            "message" => $text,
                            'to' => $user['bitrix_id'],
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }
                break;
            case 'duplicate_added':
                $status = SPR_Notification_templates::getStatus($event->data['type']);

                if($status == 1) {

                    $text = str_replace('{{model}}',$event->data['model'],$text);
                    $text = str_replace('{{original_id}}',$event->data['original_id'],$text);
                    $text = str_replace('{{original_link}}',route('flat.show', [ 'id' => $event->data['original_id'] ]),$text);
                    $text = str_replace('{{duplicate_id}}',$event->data['duplicate_id'],$text);
                    $text = str_replace('{{duplicate_link}}',route('flat.show', [ 'id' => $event->data['duplicate_id'] ]),$text);
                    $text = str_replace('{{duplicate_owner_id}}',$event->data['duplicate_owner']->bitrix_id,$text);
                    $text = str_replace('{{duplicate_owner_link}}',env('BITRIX_DOMAIN') . "/company/personal/user/" . $event->data['duplicate_owner']->bitrix_id,$text);
                    $text = str_replace('{{duplicate_owner_name}}',$event->data['duplicate_owner']->fullName(),$text);

                    $client = new Client();
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/im.notify', [
                        'query' => [
                            "message" => $text,
                            'to' => $event->data['original_owner']->bitrix_id,
                            'auth' => $event->credentials->access_token
                        ]
                    ]);
                }
                break;
        }
    }
}
