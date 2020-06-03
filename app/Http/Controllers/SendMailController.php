<?php

namespace App\Http\Controllers;

use App\Mail\PresentationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
class SendMailController extends Controller
{
    public function sendMail($object) {
        Mail::to("artemka.relit@gmail.com")->send(new PresentationMail($object));
    }
}
