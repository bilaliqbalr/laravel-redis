<?php

namespace Bilaliqbalr\LaravelRedis\Support;


use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait Auth
{
    public function getByApiToken($apiToken)
    {
        $apiToken = $apiToken === false ? request()->bearerToken() : $apiToken;

        if (empty($apiToken)) {
            return false;
        }

        return once(function () use ($apiToken) {
            $userId = $this->redis->get($this->getColumnKey(self::API_KEY, $apiToken));

            if (is_null($userId)) return null;
            return $this->get($userId);
        });
    }

    /**
     * Check if email exists in redis
     *
     * @param $email
     * @return mixed
     */
    public function isEmailExists($email)
    {
        return $this->redis->exists(
            $this->getColumnKey(self::EMAIL_KEY, $email),
        );
    }

    public function login($email, $password)
    {
        if ( ! self::isEmailExists($email)) {
            throw ValidationException::withMessages([
                'email' => [trans('passwords.user')],
            ]);
        }

        $userId = Redis::get($this->getColumnKey(self::EMAIL_KEY, $email));
        $userKey = $this->getColumnKey(self::ID_KEY, $userId);

        $dbPass = Redis::hget($userKey, 'password');

        if (Hash::check($password, $dbPass)) {
            // Deleting old token
            Redis::del($this->getColumnKey(self::API_KEY, Redis::hget($userKey, 'api_token')));

            // Setting new api token
            $authToken = Str::random(60);
            Redis::set($this->getColumnKey(self::API_KEY, $authToken), $userId);

            // User login & updating token
            Redis::hmset($userKey, 'api_token', $authToken, 'last_login', now()->timestamp);

            return $this->get($userId);

        } else {
            // Invalid login details
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }
    }

    public function logout()
    {
        $authToken = request()->bearerToken();
        if ( ! $user = self::getByApiToken($authToken)) {
            return false;
        }

        // logging out user
        Redis::hmset(
            $this->getColumnKey(self::API_KEY, $user['id'])
            , 'api_token', null
        );
        Redis::del($this->getColumnKey(self::API_KEY, $authToken));

        return true;
    }
}
