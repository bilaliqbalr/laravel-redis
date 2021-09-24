<?php

namespace Bilaliqbalr\LaravelRedis\Models;


use Bilaliqbalr\LaravelRedis\Support\Auth;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class User extends BaseModel implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract
{
    use Authenticatable,
        Authorizable,
        CanResetPassword,
        MustVerifyEmail,
        Auth;

    public const EMAIL_KEY = "{model}:email:%s";
    public const API_KEY = "{model}:api_token:%s";

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'api_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that will be searchable.
     *
     * @var array
     */
    protected $searchBy = [
        'email' => self::EMAIL_KEY,
        'api_token' => self::API_KEY,
    ];

    public function getById($id)
    {
        $userData = $this->redis->hgetall(self::getColumnKey(self::ID_KEY, $id));
        return new static($userData);
    }

    public function create($attributes)
    {
        $attributes['api_token'] = Str::random(60);
        $attributes['password'] = Hash::make($attributes['password']);

        return parent::create($attributes);
    }
}
