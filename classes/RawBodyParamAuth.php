<?php

namespace common\classes;

use yii\filters\auth\AuthMethod;

class RawBodyParamAuth extends AuthMethod
{
    /**
     * @var string the parameter name for passing the access token
     */
    public $tokenParam = 'auth-token';


    /**
     * {@inheritdoc}
     */
    public function authenticate($user, $request, $response)
    {
        $rawBody = $request->getRawBody();
        $extraParams = json_decode($rawBody, true);
        $accessToken = $extraParams[$this->tokenParam] ?? null;

        if (is_string($accessToken)) {
            $identity = $user->loginByAccessToken($accessToken, get_class($this));
            if ($identity !== null) {
                return $identity;
            }
        }
        if ($accessToken !== null) {
            $this->handleFailure($response);
        }

        return null;
    }
}
