<?php

namespace Bilaliqbalr\LaravelRedis\Auth;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as AuthUserProvider;
use Illuminate\Support\Facades\Hash;
use \Bilaliqbalr\LaravelRedis\Models\User;


class UserProvider implements AuthUserProvider
{
    /**
     * @var User
     */
    private $user;

    public function __construct()
    {
        $this->user = app(User::class);
    }

    public function retrieveById($identifier)
    {
        $user = $this->user->get($identifier);

        return $this->getGenericUser($user);
    }

    public function retrieveByToken($identifier, $token)
    {
        $user = $this->user->getByApiToken($token);

        return $this->getGenericUser($user);
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        $user->update([
            $user->getRememberTokenName(), $token
        ]);
//        $user->setRememberToken($token);
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
        $user = $this->user->login($credentials['email'], $credentials['password']);

        return $this->getGenericUser($user);
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

    protected function getGenericUser($user)
    {
        return $user;
        return $user instanceof User ? new GenericUser($user->getAttributes()) : null;
    }
}
