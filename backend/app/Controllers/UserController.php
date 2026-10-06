<?php
namespace App\Controllers;

use App\Services\CustomerAccountService;
use App\Models\User;
use Nemesis\Core\Controller;
use JarirAhmed\HTTPResponse\HTTPResponse;
use JarirAhmed\TimeHelper\TimeHelper;
use Nemesis\Auth\JWT;
use Nemesis\Helpers\Helpers;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Http\Session;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class UserController {

    public function login(Request $request) {
        $data = $request->all();

        // Validate input data
        if (empty($data['email']) || empty($data['password'])) {
            $accept = strtolower((string) $request->header('Accept', ''));
            if (str_contains($accept, 'text/html') || $accept === '') {
                return Response::redirect('/login?error=invalid_credentials');
            }
            return Response::json(['error' => true, 'message' => 'Email and password are required.'], 422);
        }

        $auth = (new CustomerAccountService())->authenticate(
            (string) $data['email'],
            (string) $data['password']
        );

        if ($auth === null) {
            $accept = strtolower((string) $request->header('Accept', ''));
            if (str_contains($accept, 'text/html') || $accept === '') {
                return Response::redirect('/login?error=invalid_credentials');
            }

            return Response::json([
                'error' => true,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // Regenerate the browser session after credential verification to
        // prevent session fixation before storing the minimal auth context.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        Session::set('auth', $auth);

        $authToken = JWT::encode($auth);

        $dashboardUrl = ($auth['role'] ?? '') === 'admin' ? '/admin' : '/profile';

        $accept = strtolower((string) $request->header('Accept', ''));
        $wantsHtml = str_contains($accept, 'text/html') || $accept === '';

        // Browser form submits should land on the dashboard page.
        if ($wantsHtml) {
            return Response::redirect($dashboardUrl);
        }

        return Response::json([
            'success' => true,
            'message' => 'Login successful.',
            'auth_token' => $authToken,
            'redirect_to' => $dashboardUrl,
            'user' => [
                'id' => $auth['sub'],
                'email' => $auth['email'],
                'role' => $auth['role'],
            ]
        ]);
    }

    /**
     * Hook points for tests and future auth providers.
     */
    protected function findUserByEmail(string $email): ?array
    {
        $user = new User();
        return $user->getByEmail($email);
    }

    protected function persistAuthToken(int|string $userId, string $authToken): mixed
    {
        $user = new User();
        return $user->updateAuthToken($userId, $authToken);
    }

    protected function generateAuthToken(): string
    {
        $user = new User();
        return $user->generateAuthToken();
    }


    public function logout() {
        Session::remove('auth');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        Helpers::json([
            'success' => true,
            'message' => 'Logout successful.'
        ], 200, true); // Pass `true` to enable pretty print
    }

    public function register(Request $request): Response
    {
        try {
            $auth = (new CustomerAccountService())->register($request->all());
        } catch (\InvalidArgumentException|\RuntimeException $error) {
            $accept = strtolower((string) $request->header('Accept', ''));
            if (str_contains($accept, 'text/html') || $accept === '') {
                return Response::redirect('/register?error=registration_failed');
            }
            return Response::json(['error' => true, 'message' => $error->getMessage()], 422);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        Session::set('auth', $auth);
        $authToken = JWT::encode($auth);
        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'text/html') || $accept === '') {
            return Response::redirect('/profile');
        }

        return Response::json([
            'success' => true,
            'message' => 'Account created successfully.',
            'auth_token' => $authToken,
            'redirect_to' => '/profile',
            'user' => ['id' => $auth['sub'], 'email' => $auth['email'], 'role' => $auth['role']],
        ], 201);
    }

    public function updateProfile(Request $request): Response
    {
        $auth = $request->getMeta('auth', []);
        if (!is_array($auth) || !isset($auth['sub']) || !is_numeric($auth['sub'])) {
            return Response::json(['error' => 'Unauthenticated.'], 401);
        }

        try {
            $profile = (new CustomerAccountService())->updateProfile((int) $auth['sub'], $request->all());
            return Response::json(['success' => true, 'data' => $profile]);
        } catch (\InvalidArgumentException $error) {
            return Response::json(['success' => false, 'message' => $error->getMessage()], 422);
        }
    }


    public function sendResetOtp() {
        $data = Helpers::getInput();
        $email = $data['email'] ?? '';

        if (empty($email)) {
            HTTPResponse::badRequest();
            Helpers::json(['error' => true, 'message' => 'Email is required']);
            return;
        }

        $user = new User();
        $userData = $user->getByEmail($email);

        if (!$userData) {
            HTTPResponse::notFound();
            Helpers::json(['error' => true, 'message' => 'User not found']);
            return;
        }

        $otp = TimeHelper::generateRandomNumber(6);
        $user->storeOtp($userData['id'], $otp);

        $mail = new PHPMailer(true);
        try {
            $mailHost = (string) (function_exists('config')
                ? config('mail.mailers.smtp.host', getenv('MAIL_HOST') ?: '')
                : (getenv('MAIL_HOST') ?: ''));
            $mailUsername = (string) (function_exists('config')
                ? config('mail.mailers.smtp.username', getenv('MAIL_USER') ?: '')
                : (getenv('MAIL_USER') ?: ''));
            $mailPassword = (string) (function_exists('config')
                ? config('mail.mailers.smtp.password', getenv('MAIL_PASS') ?: '')
                : (getenv('MAIL_PASS') ?: ''));
            $mailEncryption = (string) (function_exists('config')
                ? config('mail.mailers.smtp.encryption', getenv('MAIL_ENCRYPTION') ?: 'tls')
                : (getenv('MAIL_ENCRYPTION') ?: 'tls'));
            $mailPort = (int) (function_exists('config')
                ? config('mail.mailers.smtp.port', getenv('MAIL_PORT') ?: 587)
                : (getenv('MAIL_PORT') ?: 587));
            $mailFrom = (string) (function_exists('config')
                ? config('mail.from.address', getenv('MAIL_FROM') ?: $mailUsername)
                : (getenv('MAIL_FROM') ?: $mailUsername));
            $mailFromName = (string) (function_exists('config')
                ? config('mail.from.name', getenv('MAIL_FROM_NAME') ?: 'Password Reset')
                : (getenv('MAIL_FROM_NAME') ?: 'Password Reset'));

            if ($mailHost === '' || $mailUsername === '' || $mailPassword === '' || $mailFrom === '') {
                throw new \RuntimeException('Mail transport is not configured.');
            }

            $mail->isSMTP();
            $mail->Host = $mailHost;
            $mail->SMTPAuth = true;
            $mail->Username = $mailUsername;
            $mail->Password = $mailPassword;
            $mail->SMTPSecure = $mailEncryption;
            $mail->Port = $mailPort;

            $mail->setFrom($mailFrom, $mailFromName);
            $mail->addAddress($email);
            $mail->Subject = 'Your OTP for Password Reset';
            $mail->Body = "Your OTP is: $otp";

            $mail->send();
        } catch (\Throwable $e) {
            HTTPResponse::internalServerError();
            Helpers::json(['error' => true, 'message' => 'Failed to send OTP.']);
            return;
        }

        Helpers::json(['success' => true, 'message' => 'OTP sent to email']);
    }

    public function resetPassword() {
        $data = Helpers::getInput();
        $email = $data['email'] ?? '';
        $otp = $data['otp'] ?? '';
        $newPassword = $data['new_password'] ?? '';

        if (empty($email) || empty($otp) || empty($newPassword)) {
            HTTPResponse::badRequest();
            Helpers::json(['error' => true, 'message' => 'Email, OTP, and new password are required']);
            return;
        }

        $user = new User();
        $userData = $user->getByEmail($email);

        if (!$userData || $userData['otp'] !== $otp) {
            HTTPResponse::unauthorized();
            Helpers::json(['error' => true, 'message' => 'Invalid OTP or email']);
            return;
        }

        $user->updatePassword($userData['id'], $newPassword);
        $user->clearOtp($userData['id']);

        Helpers::json(['success' => true, 'message' => 'Password reset successful']);
    }

}
