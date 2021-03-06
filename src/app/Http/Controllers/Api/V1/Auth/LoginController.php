<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Base\Auth\Auth;
use App\Base\Auth\Exceptions\LoginException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\LoginRequest;
use App\Notifications\NotifyLogin;
use App\Notifications\VerifyEmail;
use App\Repositories\User as UserRepository;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class LoginController extends ApiController
{
    protected Auth $auth;

    public function __construct()
    {
        $this->auth = new Auth();
        parent::__construct();
    }

    /**
     * @OA\Post (
     *     path="/auth/login",
     *     tags = {"Auth"},
     *     summary="Авторизация",
     *     description="Авторизация через OAuth",
     *
     *
     *     @OA\Parameter(
     *          name = "email",
     *          in = "query",
     *          description = "Почта пользователя",
     *          required=true,
     *          @OA\Schema(
     *             type="email"
     *         )
     *     ),
     *
     *     @OA\Parameter(
     *          name = "password",
     *          in = "query",
     *          description = "Пароль пользователя",
     *          required=true,
     *          @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\Response(
     *          response="200",
     *          description="Вернет refresh/access токены и данные пользователя",
     *          @OA\JsonContent(
     *              @OA\Property(ref="#/components/schemas/Tokens", property="tokens"),
     *              @OA\Property(
     *                   property="users",
     *                   type="array",
     *                   @OA\Items(ref="#/components/schemas/User")
     *              ),
     *          )
     *      ),
     *     @OA\Response(
     *          response="404",
     *          description="Пользователь не найден",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                 property="messages",
     *                 type="array",
     *                  @OA\Items(
     *                      type="string",
     *                      example="user not found",
     *                      @OA\Schema(type="string")
     *                  ),
     *              )
     *          )
     *      ),
     *     @OA\Response(
     *          response="400",
     *          description="Неверный пароль",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                 property="messages",
     *                 type="array",
     *                  @OA\Items(
     *                      type="string",
     *                      example="wrong password",
     *                      @OA\Schema(type="string")
     *                  ),
     *              )
     *          )
     *      ),
     *
     * )
     */
    public function callback(LoginRequest $request)
    {
        $user = app(UserRepository::class)->getByEmail($request->get('email'));

        if (empty($user)) {
            return $this->response->withNotFound('user not found');
        }

        try {
            $tokens = $this->auth->login($request->get('email'), $request->get('password'));

        } catch (LoginException $e) {
            return $this->response->withError('wrong password');
        }

        $geo_ip = geoip($request->getClientIp());

        $user->notify(new NotifyLogin(
            'site',
            '/', //На данный момент фронтенда нет, поэтому и адреса тоже нет. В будущем надо переделать
            $geo_ip->ip,
            $geo_ip->country,
            $geo_ip->city,
        ));

        return $this->response->json([
            'tokens'=> [
                'expires_in'     => $tokens->getExpiresIn(),
                'token_type'     => $tokens->getTokenType(),
                'access_token'   => $tokens->getAccessToken(),
                'refresh_token'  => $tokens->getRefreshToken(),
            ],
            'user'  => $user
        ]);
    }
}
