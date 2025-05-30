<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginLogout extends Model
{
    use HasFactory;

    protected $table = 'login_logout';

    protected $fillable = ['user_id', 'login_time', 'logout_time', 'date','note'];

    public $timestamps = true;

    // Define the relationship with the User model
    public function user()
    {
        return $this->belongsTo(User::class); // A login/logout time belongs to a user
    }
}