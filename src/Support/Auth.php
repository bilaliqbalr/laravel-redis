<?php

namespace Bilaliqbalr\LaravelRedis\Support;


use Bilaliqbalr\LaravelRedis\Models\Model;
use Bilaliqbalr\LaravelRedis\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait Auth
{
    /**
     * Check if email exists
     *
     * @param $email
     * @return bool
     */
    public function isEmailExists($email) : bool
    {
        return $this->getConnection()->exists(
            $this->getSearchColumnKey(self::EMAIL_COL, $email),
        );
    }

    /**
     * @param $email
     * @param $password
     *
     * @return Model|User|null
     *
     * @throws ValidationException
     */
    public function login($email, $password)
    {
        if ( ! $this->isEmailExists($email)) {
            throw ValidationException::withMessages([
                'email' => [trans('passwords.user')],
            ]);
        }

        $userId = $this->getConnection()->get(
            $this->getSearchColumnKey(self::EMAIL_COL, $email)
        );
        $userKey = $this->getColumnKey(self::ID_KEY, $userId);

        $dbPass = $this->getConnection()->hget($userKey, 'password');

        if (Hash::check($password, $dbPass)) {
            // Deleting old token
            $this->getConnection()->del(
                $this->getSearchColumnKey(self::API_COL, $this->getConnection()->hget($userKey, 'api_token'))
            );

            // Setting new api token
            $authToken = Str::random(60);
            $this->getConnection()->set(
                $this->getSearchColumnKey(self::API_COL, $authToken), $userId
            );

            // User login & updating token
            $this->getConnection()->hmset(
                $userKey, 'api_token', $authToken, 'last_login', now()->timestamp
            );

            return $this->get($userId);

        } else {
            // Invalid login details
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }
    }

    /**
     * User logout functionality in case of using API
     *
     * @return bool
     */
    public function logout() : bool
    {
        if ( ! Auth::check()) {
            return false;
        }

        // logging out user by removing token
        $user = Auth::user();

        $this->getConnection()->hmset(
            $this->getSearchColumnKey(self::API_COL, $user->id), 'api_token', null
        );
        $this->getConnection()->del(
            $this->getSearchColumnKey(self::API_COL, $user->api_token)
        );

        return true;
    }
}
