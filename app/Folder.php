<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    public function files(){
    	return $this->hasMany('App\File', "parent_id");
    }

    public function folders(){
    	return $this->hasMany("App\Folder", "parent_id");
    }

    public function parent(){
    	return $this->belongsTo("App\Folder");
    }

    public function user(){
    	return $this->hasOne("App\User");
    }
}
