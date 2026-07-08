<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\DTOs\LoginDTO;
use App\Domain\Auth\DTOs\RegisterUserDTO;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Hotel\DTOs\CreateHotelDTO;
use App\Domain\Hotel\Services\HotelService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\HotelLoginRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterHotelRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\HotelResource;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handles user authentication endpoints.
 * Requirements: 1.1 – 1.8, 1.2, 1.4, 2.3, 2.4, 2.8
 */
class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly AuthService  $authService,
        private readonly HotelService $hotelService,
    ) {}

    /**
     * POST /auth/register
     * Requirements: 1.1
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register(
            RegisterUserDTO::fromArray($request->validated())
        );

        return $this->created('Cadastro realizado com sucesso.', [
            'token' => $result['token'],
            'user'  => new UserResource($result['user']),
        ]);
    }

    /**
     * POST /auth/login
     * Requirements: 1.3, 1.4
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            LoginDTO::fromArray($request->validated())
        );

        return $this->success('Login realizado com sucesso.', [
            'token' => $result['token'],
            'user'  => new UserResource($result['user']),
        ]);
    }

    /**
     * POST /auth/logout
     * Requirements: 1.5
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success('Logout realizado com sucesso.');
    }

    /**
     * POST /auth/forgot-password
     * Requirements: 1.6
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->validated('email'));

        return $this->success('Link de recuperação enviado para o e-mail informado.');
    }

    /**
     * POST /auth/reset-password
     * Requirements: 1.7
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->authService->resetPassword(
            token: $data['token'],
            email: $data['email'],
            password: $data['password'],
        );

        return $this->success('Senha redefinida com sucesso.');
    }

    /**
     * PUT /auth/password
     * Requirements: 1.8
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->authService->changePassword(
            user: $request->user(),
            currentPassword: $data['current_password'],
            newPassword: $data['password'],
        );

        return $this->success('Senha alterada com sucesso. Faça login novamente.');
    }

    /**
     * POST /auth/hotel-login
     * Authenticates via user credentials and verifies admin role for the given hotel CNPJ.
     * Requirements: 1.2, 1.4
     */
    public function hotelLogin(HotelLoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(LoginDTO::fromArray($request->validated()));
        $hotel  = Hotel::where('cnpj', $request->validated('cnpj'))->firstOrFail();

        $isAdmin = $hotel->hotelUsers()
            ->where('user_id', $result['user']->id)
            ->where('role', 'admin')
            ->where('status', 'approved')
            ->exists();

        if (! $isAdmin) {
            throw ValidationException::withMessages([
                'cnpj' => ['Você não é administrador deste hotel.'],
            ]);
        }

        return $this->success('Login realizado com sucesso.', [
            'token' => $result['token'],
            'user'  => new UserResource($result['user']),
            'hotel' => new HotelResource($hotel),
        ]);
    }

    /**
     * POST /auth/register-hotel
     * Creates a new user and hotel in a single DB transaction.
     * Requirements: 2.3, 2.4, 2.8
     */
    public function registerHotel(RegisterHotelRequest $request): JsonResponse
    {
        $data = $request->validated();

        [$result, $hotel] = DB::transaction(function () use ($data) {
            // Step 1: create the user account
            $result = $this->authService->register(
                RegisterUserDTO::fromArray([
                    'name'     => $data['name'],
                    'email'    => $data['email'],
                    'password' => $data['password'],
                ])
            );

            // Step 2: create the hotel (HotelService also creates hotel_users admin link)
            $hotel = $this->hotelService->createHotel(
                $result['user'],
                CreateHotelDTO::fromArray([
                    'name'          => $data['hotel_name'],
                    'description'   => '',
                    'cnpj'          => $data['cnpj'],
                    'contact_email' => $data['hotel_email'],
                    'city'          => $data['city'] ?? null,
                    'website'       => $data['website'] ?? null,
                ])
            );

            return [$result, $hotel];
        });

        return $this->created('Cadastro realizado com sucesso.', [
            'token' => $result['token'],
            'user'  => new UserResource($result['user']),
            'hotel' => new HotelResource($hotel),
        ]);
    }
}
