<?php

namespace App\Mail;

use App\Models\ShareLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class PresentationMail extends Mailable
{
    use Queueable, SerializesModels;

    private $object;
    private $type;
    private $user;
    private $comment;
    private $bitrix;
    private $theme;
    private $isWeb;
    private $link;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($object, $type, $user, $comment, $theme, $bitrix, $isWeb, $link, $photo_type)
    {
        $this->object = $object;
        $this->type = $type;
        $this->user = $user;
        $this->comment = $comment;
        $this->bitrix = $bitrix;
        $this->theme = $theme;
        $this->isWeb = $isWeb;
        $this->link = $link;
        $this->photo_type = $photo_type;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $photos = json_decode($this->object->photo, 1);
        $pdf = collect($photos)->where('toPDF',1)->toArray();
        $empty_photo = false;

        $array_photos = array();
        if(!is_null($photos) && !empty($photos)) {
            foreach($photos as $photo) {
                if(isset($photo['toPDF']) && $photo['toPDF'] == 1) {
                    switch ($this->photo_type) {
                        case 1:
                            array_push($array_photos, asset($photo['url']));
                            break;
                        case 2:
                            if(isset($photo['with_text'])) {
                                array_push($array_photos, asset($photo['with_text']));
                            } else {
                                array_push($array_photos, asset($photo['url']));
                            }
                            break;
                        case 3:
                            array_push($array_photos, asset($photo['url']));
                            break;
                        case 4:
                            if(isset($photo['watermark'])) {
                                array_push($array_photos, asset($photo['watermark']));
                            } else {
                                array_push($array_photos, asset($photo['url']));
                            }
                            break;
                    }
                }
            }
        }

        if(empty($array_photos)) {
            array_push($array_photos, asset('img/empty_photo.png'));
            $empty_photo = true;
        }

        if($this->isWeb == 1) {
            $array = explode('/', $this->link);
            $search_link = end($array);
            $params = ShareLink::where('link', $search_link)->first();
            $par = json_decode($params->params);
            $email = $this->view('email.presentation')->subject($this->theme)->from(env("MAIL_USERNAME"),$this->user->fullName())->replyTo($this->user->email, $this->user->fullName())->with(['commerce'=>$this->object,'type'=>$this->type,'user'=>$this->user,'comment'=>$this->comment,'bitrix'=>$this->bitrix, 'main_photo'=>current($array_photos),'link'=>$this->link,'params'=>$par]);
        } else {
            $this->theme = $this->object->title;
            $email = $this->view('email.mail')->subject($this->theme)->from(env("MAIL_USERNAME"),$this->user->fullName())->replyTo($this->user->email, $this->user->fullName())->with(['commerce'=>$this->object,'type'=>$this->type,'user'=>$this->user,'comment'=>$this->comment,'bitrix'=>$this->bitrix, 'main_photo'=>current($array_photos)]);
        }

        if(!empty($array_photos) && !$empty_photo) {
            foreach($array_photos as $photo) {
                $email->attach($photo);
            }
        }

        return $email;
    }
}
