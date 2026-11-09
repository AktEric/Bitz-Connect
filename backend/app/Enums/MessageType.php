<?php

namespace App\Enums;

enum MessageType: string
{
    case Text     = 'text';
    case Job      = 'job';
    case Post     = 'post';
    case Profile  = 'profile';
    case Hotel    = 'hotel';
    case ImgStub  = 'img-stub';
    case FileStub = 'file-stub';
    case Image    = 'image';
    case Audio    = 'audio';
    case File     = 'file';
}
