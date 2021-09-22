<?php

namespace Bilaliqbalr\LaravelRedis\Models;


use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\Access\Authorizable;

class User extends BaseModel implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract
{
    use Authenticatable, Authorizable, CanResetPassword, MustVerifyEmail;

    public const EMAIL_KEY = "email:%s";
    public const API_KEY = "api_token:%s";

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

    public function getByApiToken($apiToken)
    {
        $apiToken = $apiToken === false ? request()->bearerToken() : $apiToken;

        if (empty($apiToken)) {
            return false;
        }

        return once(function () use ($apiToken) {
            $userId = $this->redis->get($this->getColumnKey(self::API_KEY, $apiToken));
            return $this->getById($userId);
        });
    }
}
