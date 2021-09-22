<?php

namespace Bilaliqbalr\LaravelRedis\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as AuthUserProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Hash;
use \Bilaliqbalr\LaravelRedis\Models\User;


class UserProvider implements AuthUserProvider
{
    /**
     * @var Application|mixed
     */
    private $user;

    public function __construct()
    {
        $this->user = app(User::class);
    }

    public function retrieveById($identifier)
    {
        $userData = $this->user->getUserById($identifier);

        return empty($userData) ? null : new User($this->user->getUserData());
    }

    public function retrieveByToken($identifier, $token)
    {
        $userData = $this->user->getUserByAuthToken($token);

        return is_array($userData) ? new User($this->user->getUserData()) : null;
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        $user->setRememberToken($token);
    }

    public function retrieveByCredentials(array $credentials)
    {
        if (! array_key_exists('email', $credentials)) {
            return null;
        }
        if (empty($credentials) ||
            (count($credentials) === 1 &&
             array_key_exists('password', $credentials))) {
            return null;
        }

        // User is a class from Laravel Auth System
        $userData = $this->user->login($credentials['email'], $credentials['password']);
        if (isset($userData['status']) && $userData['status'] === false) {
            return null;
        }

        return new User($this->user->getUserData());
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if (! array_key_exists('password', $credentials)) {
            return false;
        }

        return Hash::check(
            $credentials['password'], $user->getAuthPassword()
        );
    }
}
